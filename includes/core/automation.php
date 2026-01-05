<?php
namespace Zaplane\Core;

use Zaplane\Classes\Container;
use Zaplane\Classes\Logger;
use Zaplane\Classes\Query;

if (!defined('ABSPATH')) exit;

class Automation {

    protected static ?self $instance = null;
    protected Container $container;
    protected array $registered_hooks = [];

    public static function init(Container $container): self {
        if (!self::$instance) {
            self::$instance = new self($container);
            self::$instance->boot();
        }
        return self::$instance;
    }

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function boot(): void {
        add_action('init', [$this, 'dispatch_active_triggers']);
        add_action('zaplane_execute_node_run', [$this, 'dispatch_node_run'], 10, 1);
        add_action('zaplane_workflow_updated', [$this, 'reload_triggers']);
    }

    /* =====================================================
     * TRIGGER REGISTRATION
     * ===================================================== */

    public function reload_triggers() {
        foreach ($this->registered_hooks as $event => $cb) {
            remove_action($event, $cb, 10);
        }
        $this->registered_hooks = [];
        $this->dispatch_active_triggers();
    }

    public function dispatch_active_triggers(): void {
        foreach (Query::get_active_trigger_events() as $event) {
            if (!isset($this->registered_hooks[$event])) {
                $cb = [$this, 'trigger_router'];
                add_action($event, $cb, 10, 99);
                $this->registered_hooks[$event] = $cb;
            }
        }
    }

    /* =====================================================
     * TRIGGER → RUN
     * ===================================================== */

    public function trigger_router() {
        $event = current_filter();
        $args  = func_get_args();

        foreach (Query::get_active_workflows_for_event($event) as $trigger) {
            $integration = $this->container->get('integrations')
                ->get(strtolower($trigger['app']));

            $payload = $integration::resolve_trigger($trigger['graph_node']['data'], $args);
            if (!$payload) continue;

            $this->start_run_from_trigger($trigger, $payload);
        }
    }

    private function start_run_from_trigger(array $trigger, array $payload) {
        global $wpdb;

        // Create run
        $wpdb->insert('wp_zaplane_runs', [
            'workflow_version_hash' => $trigger['workflow_version_hash'],
            'status' => 'running',
            'trigger_data' => json_encode($payload),
            'started_at' => current_time('mysql')
        ]);

        $run_id = $wpdb->insert_id;

        // Create TRIGGER node_run (root)
        $this->spawn_node_run(
            $run_id,
            $trigger['id'],
            $payload,
            null
        );
    }

    /* =====================================================
     * NODE SPAWNING
     * ===================================================== */

    public function spawn_node_run(int $run_id, string $node_key, array $input, ?int $parent): int {
        global $wpdb;

        $wpdb->insert('wp_zaplane_node_runs', [
            'run_id' => $run_id,
            'node_key' => $node_key,
            'parent_node_run_id' => $parent,
            'status' => 'pending',
            'input_json' => json_encode($input),
            'started_at' => current_time('mysql')
        ]);

        $id = $wpdb->insert_id;

        as_enqueue_async_action(
            'zaplane_execute_node_run',
            ['node_run_id' => $id],
            'zaplane'
        );

        return $id;
    }

    /* =====================================================
     * ACTION SCHEDULER → NODE RUNNER
     * ===================================================== */

    public function dispatch_node_run(int $node_run_id) {
        global $wpdb;

        // Atomic lock
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE wp_zaplane_node_runs 
                 SET status='running', started_at=NOW() 
                 WHERE id=%d AND status='pending'",
                $node_run_id
            )
        );

        if (!$updated) return;

        $nr = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM wp_zaplane_node_runs WHERE id=%d", $node_run_id),
            ARRAY_A
        );

        $this->execute_node($nr);
    }

    /* =====================================================
     * NODE EXECUTION
     * ===================================================== */

    private function execute_node(array $nr) {
        global $wpdb;

        $run = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM wp_zaplane_runs WHERE id=%d", $nr['run_id']),
            ARRAY_A
        );

        $graph = $this->load_graph_by_hash($run['workflow_version_hash']);

        foreach ($graph['nodes'] as $n) {
            if ((string)$n['id'] === (string)$nr['node_key']) {
                $node = $n;
                break;
            }
        }

        $input = json_decode($nr['input_json'], true);

        try {
            // TRIGGER node
            if ($node['type'] === 'trigger') {
                $output = $input;
            } else {
                $integration = $this->container->get('integrations')
                    ->get(strtolower($node['data']['app']));
                $output = $integration::execute_node($node, $input);
            }

            $wpdb->update('wp_zaplane_node_runs', [
                'status' => 'completed',
                'output_json' => json_encode($output),
                'finished_at' => current_time('mysql')
            ], ['id' => $nr['id']]);

            $this->spawn_children($nr, $node, $output, $graph);

        } catch (\Throwable $e) {

            $wpdb->update('wp_zaplane_node_runs', [
                'status' => 'failed',
                'output_json' => json_encode(['error' => $e->getMessage()]),
                'finished_at' => current_time('mysql')
            ], ['id' => $nr['id']]);

            $this->spawn_error_children($nr, $graph, $e->getMessage());
        }

        $this->finalize_run($nr['run_id']);
    }

    /* =====================================================
     * GRAPH ROUTING
     * ===================================================== */

    private function spawn_children($nr, $node, $output, $graph) {
        foreach ($graph['edges'] as $e) {
            if ($e['source'] === $nr['node_key']) {
                $this->spawn_node_run(
                    $nr['run_id'],
                    $e['target'],
                    $output,
                    $nr['id']
                );
            }
        }
    }

    private function spawn_error_children($nr, $graph, $error) {
        foreach ($graph['edges'] as $e) {
            if ($e['source'] === $nr['node_key'] && ($e['type'] ?? '') === 'error') {
                $this->spawn_node_run(
                    $nr['run_id'],
                    $e['target'],
                    ['error' => $error],
                    $nr['id']
                );
            }
        }
    }

    /* =====================================================
     * RUN FINALIZATION
     * ===================================================== */

    private function finalize_run(int $run_id) {
        global $wpdb;

        $left = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM wp_zaplane_node_runs 
                 WHERE run_id=%d AND status IN ('pending','running')",
                $run_id
            )
        );

        if ($left == 0) {
            $failed = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM wp_zaplane_node_runs 
                     WHERE run_id=%d AND status='failed'",
                    $run_id
                )
            );

            $wpdb->update('wp_zaplane_runs', [
                'status' => $failed ? 'failed' : 'completed',
                'finished_at' => current_time('mysql')
            ], ['id' => $run_id]);
        }
    }

    /* =====================================================
     * GRAPH + TRIGGER DISCOVERY
     * ===================================================== */

    private function load_graph_by_hash(string $hash): array {
        global $wpdb;
        return json_decode(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT graph_json FROM wp_zaplane_workflow_versions WHERE graph_hash=%s",
                    $hash
                )
            ),
            true
        );
    }
}
