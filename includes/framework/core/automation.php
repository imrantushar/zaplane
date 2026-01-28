<?php

namespace Zaplane\Framework\Core;

use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Classes\DataMapper;
use Zaplane\Framework\Classes\ExecutionContext;
use Zaplane\Framework\Classes\Query;
use Zaplane\Framework\Exceptions\WorkflowException;
use Zaplane\Framework\Exceptions\IntegrationException;
use Zaplane\Models\Run;
use Zaplane\Models\NodeRun;
use Zaplane\Models\WorkflowVersion;

if (!defined('ABSPATH')) exit;

class Automation
{
    protected static ?self $instance = null;
    protected Container $container;
    protected array $registered_hooks = [];

    /**
     * Default max attempts for node retries.
     */
    const DEFAULT_MAX_ATTEMPTS = 3;

    /**
     * Base delay in seconds for exponential backoff.
     */
    const RETRY_BASE_DELAY = 30;

    public static function init(Container $container): self
    {
        if (!self::$instance) {
            self::$instance = new self($container);
            self::$instance->boot();
        }
        return self::$instance;
    }

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function boot(): void
    {
        // Don't register automation hooks during WP-CLI execution
        if (defined('WP_CLI') && WP_CLI) {
            return;
        }

        add_action('init', [$this, 'dispatch_active_triggers']);
        add_action('zaplane_execute_node_run', [$this, 'dispatch_node_run'], 10, 1);
        add_action('zaplane_workflow_updated', [$this, 'reload_triggers']);
    }

    public function reload_triggers()
    {
        foreach ($this->registered_hooks as $event => $cb) {
            remove_action($event, $cb, 10);
        }
        $this->registered_hooks = [];
        $this->dispatch_active_triggers();
    }

    public function dispatch_active_triggers(): void
    {
        foreach (Query::get_active_trigger_events() as $event) {
            if (!isset($this->registered_hooks[$event])) {
                $cb = [$this, 'trigger_router'];
                add_action($event, $cb, 10, 99);
                $this->registered_hooks[$event] = $cb;
            }
        }
    }

    public function trigger_router()
    {
        $event = current_filter();
        $args = func_get_args();

        foreach (Query::get_active_workflows_for_event($event) as $trigger) {
            $integration = $this->container->get('integrations')->get(strtolower($trigger['app']));
            if (!$integration) {
                continue;
            }
            $payload = $integration::resolve_trigger($trigger['graph_node']['data'], $args);
            if (!$payload) continue;

            $this->start_trigger_run($trigger, $payload);
        }
    }

    private function start_trigger_run(array $trigger, array $payload)
    {
        $run = Run::create([
            'workflow_id' => $trigger['workflow_id'] ?? null,
            'workflow_version_hash' => $trigger['workflow_version_hash'],
            'trigger_data' => $payload,
            'status' => 'running',
            'start_node_key' => $trigger['id'],
            'target_node_key' => null,
            'started_at' => current_time('mysql'),
        ]);

        $this->spawn_node_run($run->id, $trigger['id'], $payload, null);
    }

    public function spawn_node_run(int $run_id, string $node_key, array $input, ?int $parent)
    {
        $nodeRun = NodeRun::create([
            'run_id' => $run_id,
            'node_key' => $node_key,
            'parent_node_run_id' => $parent,
            'status' => 'pending',
            'input_json' => $input,
            'max_attempts' => self::DEFAULT_MAX_ATTEMPTS,
            'started_at' => current_time('mysql'),
        ]);

        as_enqueue_async_action(
            'zaplane_execute_node_run',
            ['node_run_id' => $nodeRun->id],
            'zaplane'
        );
    }

    public function dispatch_node_run(int $node_run_id)
    {
        $nodeRun = NodeRun::find($node_run_id);

        if (!$nodeRun || !$nodeRun->isPending()) {
            return;
        }

        $nodeRun->markAsRunning();
        $nodeRun->log('info', 'Node execution started');
        $this->execute_node($nodeRun);
    }

    private function execute_node(NodeRun $nodeRun)
    {
        $run = $nodeRun->run();

        if (!$run) {
            $nodeRun->markAsFailed('Run not found');
            return;
        }

        $graph = $this->load_graph($run->workflow_version_hash);
        $node = $this->find_node($graph, $nodeRun->node_key, $run->id);
        $input = $nodeRun->getInput();

        try {
            if ($node['type'] === 'trigger') {
                $output = $input;
                $nodeRun->log('info', 'Trigger node passed through');
            } else {
                $app_slug = strtolower($node['data']['app'] ?? '');
                $integration = $this->container->get('integrations')->get($app_slug);
                if (!$integration) {
                    throw IntegrationException::notFound($node['data']['app']);
                }

                // --- Phase 5.2: Rate limiting ---
                $this->enforce_rate_limit($integration, $nodeRun);

                // --- Phase 1.1: Inject connection credentials automatically ---
                $node = $this->inject_credentials($node, $integration);

                // --- Phase 1.2: Resolve variables in node config ---
                $node = DataMapper::resolve_node_config($node, $input);

                // --- Phase 3.3: Attach ExecutionContext for structured access ---
                $node['_context'] = new ExecutionContext(
                    $run->id,
                    $nodeRun->node_key,
                    $input,
                    $node['_connection_credentials'] ?? [],
                    $node['config']['data'] ?? $node['data']['config'] ?? [],
                    $node,
                    $nodeRun
                );

                $output = $integration::execute_node($node, $input);
                $nodeRun->log('info', 'Node executed successfully');
            }

            $nodeRun->setOutput($output);

            $this->spawn_children($nodeRun, $output, $graph, $run);

        } catch (\Throwable $e) {
            $nodeRun->incrementAttempts();

            $error_message = $e->getMessage();
            $error_code = method_exists($e, 'getErrorCode') ? $e->getErrorCode() : 'unknown';

            $nodeRun->log('error', sprintf(
                'Node failed (attempt %d/%d): [%s] %s',
                $nodeRun->attempts,
                $nodeRun->max_attempts ?? self::DEFAULT_MAX_ATTEMPTS,
                $error_code,
                $error_message
            ));

            // --- Phase 1.4: Retry with exponential backoff ---
            if ($nodeRun->canRetry()) {
                $delay_seconds = self::RETRY_BASE_DELAY * pow(2, $nodeRun->attempts - 1);
                $available_at = gmdate('Y-m-d H:i:s', time() + $delay_seconds);

                $nodeRun->status = 'pending';
                $nodeRun->save();

                $nodeRun->log('info', sprintf(
                    'Scheduling retry in %d seconds (attempt %d/%d)',
                    $delay_seconds,
                    $nodeRun->attempts + 1,
                    $nodeRun->max_attempts ?? self::DEFAULT_MAX_ATTEMPTS
                ));

                as_schedule_single_action(
                    time() + $delay_seconds,
                    'zaplane_execute_node_run',
                    ['node_run_id' => $nodeRun->id],
                    'zaplane'
                );
                return; // Don't finalize — we're retrying
            }

            // All retries exhausted
            $error_data = [
                'error' => $error_message,
                'error_code' => $error_code,
            ];

            if (method_exists($e, 'getContext')) {
                $error_data['context'] = $e->getContext();
            }

            $nodeRun->output_json = $error_data;
            $nodeRun->status = 'failed';
            $nodeRun->finished_at = current_time('mysql');
            $nodeRun->save();

            $nodeRun->log('error', 'Node permanently failed after all retry attempts');
        }

        $this->finalize_run($run->id);
    }

    /**
     * Inject decrypted connection credentials into the node array.
     *
     * Looks for a connection_id in the node config and uses
     * ConnectionManager to decrypt and inject credentials.
     *
     * @param array  $node        The node definition.
     * @param object $integration The integration instance.
     * @return array The node with _connection_credentials injected.
     */
    private function inject_credentials(array $node, object $integration): array
    {
        $class = get_class($integration);

        // Only inject for integrations that require a connection
        if (!$class::requires_connection()) {
            return $node;
        }

        $connection_id = $node['data']['connection_id']
            ?? $node['config']['connection_id']
            ?? null;

        if (!$connection_id) {
            return $node;
        }

        try {
            $connection_manager = $this->container->get('connections');
            $credentials = $connection_manager->get_execution_credentials((int) $connection_id);
            $node['_connection_credentials'] = $credentials;
        } catch (\Throwable $e) {
            // Log but don't block — the integration will throw its own
            // error if credentials are required and missing.
            error_log('Zaplane: Failed to inject credentials for connection ' . $connection_id . ': ' . $e->getMessage());
        }

        return $node;
    }

    /**
     * Enforce integration rate limit using a WordPress transient counter.
     *
     * If the integration declares a rate limit via get_rate_limit(),
     * this checks how many requests have been made in the current minute.
     * If the limit is exceeded, the node run is deferred.
     *
     * @param object  $integration The integration instance.
     * @param NodeRun $nodeRun     The node run being executed.
     * @throws \Exception If rate limit is exceeded (caught by retry logic).
     */
    private function enforce_rate_limit(object $integration, NodeRun $nodeRun): void
    {
        $class = get_class($integration);
        $limit = $class::get_rate_limit();

        if ($limit <= 0) {
            return; // No rate limit
        }

        $slug = $class::get_slug();
        $transient_key = 'zaplane_rate_' . $slug . '_' . gmdate('YmdHi');

        $current = (int) get_transient($transient_key);

        if ($current >= $limit) {
            $nodeRun->log('warning', sprintf(
                'Rate limit reached for %s (%d/%d per minute). Deferring execution.',
                $slug,
                $current,
                $limit
            ));
            throw new \Exception(
                sprintf('Rate limit exceeded for %s: %d/%d requests per minute', $slug, $current, $limit)
            );
        }

        // Increment counter with 120s TTL (covers current + next minute boundary)
        set_transient($transient_key, $current + 1, 120);
    }

    private function spawn_children(NodeRun $nodeRun, array $output, array $graph, Run $run)
    {
        if ($run->target_node_key && $nodeRun->node_key === $run->target_node_key) {
            return;
        }

        $port = $output['port'] ?? 'main';
        $data = $output['data'] ?? $output;

        foreach ($graph['edges'] as $edge) {
            if ($edge['source'] === $nodeRun->node_key) {
                // Support port-based routing: if edge specifies a sourceHandle,
                // only follow edges matching the output port.
                $edge_port = $edge['sourceHandle'] ?? 'main';
                if ($edge_port !== $port && $port !== 'main') {
                    continue;
                }

                $this->spawn_node_run(
                    $nodeRun->run_id,
                    $edge['target'],
                    $data,
                    $nodeRun->id
                );
            }
        }
    }

    private function finalize_run(int $run_id)
    {
        $pendingCount = NodeRun::where('run_id', $run_id)
            ->whereIn('status', ['pending', 'running'])
            ->count();

        if ($pendingCount == 0) {
            $failedCount = NodeRun::where('run_id', $run_id)
                ->where('status', 'failed')
                ->count();

            $run = Run::find($run_id);
            if ($run) {
                $run->status = $failedCount ? 'failed' : 'completed';
                $run->finished_at = current_time('mysql');
                $run->save();
            }
        }
    }

    private function load_graph(string $hash): array
    {
        $version = WorkflowVersion::where('graph_hash', $hash)->first();
        return $version ? $version->getGraph() : ['nodes' => [], 'edges' => []];
    }

    private function find_node(array $graph, string $key, int $run_id = 0): array
    {
        foreach ($graph['nodes'] as $node) {
            if ((string) $node['id'] === (string) $key) {
                return $node;
            }
        }
        throw WorkflowException::nodeNotFound($run_id, $key);
    }
}
