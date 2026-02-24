<?php

namespace Zaplane\Framework\Core;

use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Classes\Query;
use Zaplane\Framework\Exceptions\WorkflowException;
use Zaplane\Framework\Exceptions\IntegrationException;
use Zaplane\Framework\Models\Option;
use Zaplane\Models\Run;
use Zaplane\Models\NodeRun;
use Zaplane\Models\WorkflowVersion;

if (!defined('ABSPATH')) exit;

class Automation
{
    protected static ?self $instance = null;
    protected Container $container;
    protected array $registered_hooks = [];

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
        add_action('init', [$this, 'dispatch_active_listeners']);
        add_action('zaplane_execute_node_run', [$this, 'dispatch_node_run'], 10, 1);
        add_action('zaplane_workflow_updated', [$this, 'reload_triggers']);
    }

    public function reload_triggers()
    {
        foreach ($this->registered_hooks as $event => $cb) {
            if (is_array($cb)) {
                remove_action($event, $cb, 10);
            }
        }
        $this->registered_hooks = [];
        $this->dispatch_active_triggers();
        $this->dispatch_active_listeners();
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

    public function dispatch_active_listeners(): void
    {
        $listeners = Option::where('option_name', 'LIKE', 'zaplane_listener_state_%')->get();

        foreach ($listeners as $listener) {
            $state = $listener->getValue();

            if (!$state || !is_array($state) || ($state['status'] ?? '') !== 'listening') {
                continue;
            }

            $hook = $state['hook'] ?? '';
            if (!$hook) {
                continue;
            }

            // Register hook if not already registered
            if (!isset($this->registered_hooks['listener_' . $hook])) {
                add_action($hook, [$this, 'listener_hook_handler'], 1, 99);
                $this->registered_hooks['listener_' . $hook] = true;
            }
        }
    }

    public function listener_hook_handler()
    {
        $currentHook = current_filter();
        $args = func_get_args();

        $listeners = Option::where('option_name', 'LIKE', 'zaplane_listener_state_%')->get();

        foreach ($listeners as $listener) {
            $state = $listener->getValue();

            if (!$state || !is_array($state) || ($state['status'] ?? '') !== 'listening') {
                continue;
            }

            if (($state['hook'] ?? '') !== $currentHook) {
                continue;
            }

            $workflowId = $state['workflow_id'] ?? 0;
            $hookInfoOption = 'zaplane_listener_hook_' . $workflowId;
            $hookInfo = Option::get($hookInfoOption);

            if (!$hookInfo || !is_array($hookInfo)) {
                continue;
            }

            $node = $hookInfo['node'];

            // Resolve trigger data using the integration
            $integration = $this->container->get('integrations')->get(strtolower($node['data']['app']));
            if (!$integration) {
                continue;
            }

            $payload = $integration::resolve_trigger($node['data'], $args);
            if (!$payload) {
                continue;
            }

            // Update state with captured data
            $state['status'] = 'triggered';
            $state['data'] = $payload;
            $state['triggered_at'] = current_time('mysql');

            Option::set($listener->option_name, $state, 'no');

            // Handle this listener, continue to check others
        }
    }

    public function trigger_router()
    {
        $event = current_filter();
        $args = func_get_args();

        foreach (Query::get_active_workflows_for_event($event) as $trigger) {
            // Skip if there's an active listener for this workflow — the listener will handle it
            $listenerState = Option::get('zaplane_listener_state_' . $trigger['workflow_id']);
            if (is_array($listenerState) && ($listenerState['status'] ?? '') === 'listening') {
                continue;
            }

            $integration = $this->container->get('integrations')->get(strtolower($trigger['app']));
            $payload = $integration::resolve_trigger($trigger['graph_node']['data'], $args);
            if (!$payload) continue;

            $this->start_trigger_run($trigger, $payload);
        }
    }

    private function start_trigger_run(array $trigger, array $payload)
    {
        $nodeKey = (int) $trigger['id'];

        $run = Run::create([
            'workflow_version_hash' => $trigger['workflow_version_hash'],
            'trigger_data' => $payload,
            'status' => 'running',
            'start_node_key' => $nodeKey,
            'target_node_key' => null,
            'started_at' => current_time('mysql'),
        ]);

        $this->spawn_node_run($run->id, $nodeKey, $payload, null);
    }

    public function spawn_node_run(int $run_id, int $node_key, array $input, ?int $parent)
    {
        $nodeRun = NodeRun::create([
            'run_id' => $run_id,
            'node_key' => $node_key,
            'parent_node_run_id' => $parent,
            'status' => 'pending',
            'input_json' => $input,
            'started_at' => current_time('mysql'),
        ]);

        self::enqueue_node_run($nodeRun->id);
    }

    public static function enqueue_node_run(int $node_run_id): void
    {
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(
                'zaplane_execute_node_run',
                ['node_run_id' => $node_run_id],
                'zaplane'
            );
            return;
        }

        do_action('zaplane_execute_node_run', $node_run_id);
    }

    public function dispatch_node_run(int $node_run_id)
    {
        $nodeRun = NodeRun::find($node_run_id);

        if (!$nodeRun || !$nodeRun->isPending()) {
            return;
        }

        $nodeRun->markAsRunning();
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
            } elseif ($node['type'] === 'condition' || $node['type'] === 'filter') {
                $integration = $this->container->get('integrations')->get(strtolower($node['data']['app']));
                if (!$integration) {
                    throw IntegrationException::notFound($node['data']['app']);
                }
                $context = $this->buildNodeContext($run->id);
                $output = $integration::execute_node($node, $context);
            } else {
                $integration = $this->container->get('integrations')->get(strtolower($node['data']['app']));
                if (!$integration) {
                    throw IntegrationException::notFound($node['data']['app']);
                }
                $output = $integration::execute_node($node, $input);
            }

            $nodeRun->setOutput($output);

            $this->spawn_children($nodeRun, $output, $graph, $run);

        } catch (\Throwable $e) {
            $error_data = [
                'error' => $e->getMessage(),
                'error_code' => method_exists($e, 'getErrorCode') ? $e->getErrorCode() : 'unknown',
            ];

            if (method_exists($e, 'getContext')) {
                $error_data['context'] = $e->getContext();
            }

            $nodeRun->output_json = $error_data;
            $nodeRun->status = 'failed';
            $nodeRun->finished_at = current_time('mysql');
            $nodeRun->save();
        }

        $this->finalize_run($run->id);
    }

    private function spawn_children(NodeRun $nodeRun, array $output, array $graph, Run $run)
    {
        if ($run->target_node_key && $nodeRun->node_key === $run->target_node_key) {
            return;
        }

        // Filter gate: if filter returned pass=false, stop execution entirely
        // If pass=true, extract the data for downstream nodes
        $pass = $output['pass'] ?? $output['data']['pass'] ?? null;
        if ($pass !== null) {
            if ($pass === false) {
                return;
            }
            $output = $output['data'] ?? $output;
        }

        $port = $output['port'] ?? null;
        $childInput = $port ? ($output['data'] ?? $output) : $output;

        foreach ($graph['edges'] as $edge) {
            if ((int) $edge['source'] !== $nodeRun->node_key) {
                continue;
            }

            // If node returned a port (condition), only follow matching edges
            if ($port !== null) {
                $edgeHandle = $edge['sourceHandle'] ?? null;
                if ($edgeHandle !== null && $edgeHandle !== $port) {
                    continue;
                }
            }

            $this->spawn_node_run(
                $nodeRun->run_id,
                (int) $edge['target'],
                $childInput,
                $nodeRun->id
            );
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

    /**
     * Build a context array keyed by node_key from all completed node runs in this run.
     * Result: [1 => ['post_title' => 'Hello', ...], 5 => ['id' => 456, ...]]
     */
    private function buildNodeContext(int $run_id): array
    {
        $nodeRuns = NodeRun::where('run_id', $run_id)
            ->where('status', 'completed')
            ->orderBy('id', 'asc')
            ->get();

        $context = [];
        foreach ($nodeRuns as $nr) {
            $output = $nr->getOutput();
            $context[$nr->node_key] = is_array($output) ? $output : ['value' => $output];
        }

        return $context;
    }

    private function load_graph(string $hash): array
    {
        $version = WorkflowVersion::where('graph_hash', $hash)->first();
        return $version ? $version->getGraph() : ['nodes' => [], 'edges' => []];
    }

    private function find_node(array $graph, int $key, int $run_id = 0): array
    {
        foreach ($graph['nodes'] as $node) {
            if ((int) $node['id'] === $key) {
                return $node;
            }
        }
        throw WorkflowException::nodeNotFound($run_id, $key);
    }
}
