<?php

namespace Zaplane\Framework\Core;

use Zaplane\Framework\Classes\Container;
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
            if (is_string($event) && !isset($this->registered_hooks[$event])) {
                $cb = [$this, 'trigger_router'];
                add_action($event, $cb, 10, 99);
                $this->registered_hooks[$event] = $cb;
            }
        }
    }

    public function trigger_router()
    {
        $event = current_filter();
        error_log(print_r('events' . $event , true ));

        $args = func_get_args();
        error_log(print_r('args:'. $args , true ));

        foreach (Query::get_active_workflows_for_event($event) as $trigger) {
            $integration = $this->container->get('integrations')->get(strtolower($trigger['app']));
            $payload = $integration::resolve_trigger($trigger['graph_node']['data'], $args);
            if (!$payload) continue;

            $this->start_trigger_run($trigger, $payload);
        }
    }

    private function start_trigger_run(array $trigger, array $payload)
    {
        $run = Run::create([
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

        foreach ($graph['edges'] as $edge) {
            if ($edge['source'] === $nodeRun->node_key) {
                $this->spawn_node_run(
                    $nodeRun->run_id,
                    $edge['target'],
                    $output,
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
