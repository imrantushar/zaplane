<?php
namespace Zaplane;

use Zaplane\Classes\IntegrationLoader;

if (!defined('ABSPATH')) exit;

class Query {

    /* =====================================================
     * TRIGGERS
     * ===================================================== */

    public static function get_active_trigger_events(): array {
        global $wpdb;

        $cache_key = 'zaplane_active_trigger_events';
        $events = wp_cache_get($cache_key, 'zaplane');
        if ($events !== false) return $events;

        $rows = $wpdb->get_results("
            SELECT wv.graph_json
            FROM {$wpdb->prefix}zaplane_workflows w
            JOIN {$wpdb->prefix}zaplane_workflow_versions wv
            ON w.id = wv.workflow_id
            WHERE w.status = 'active' AND wv.is_active = 1
        ", ARRAY_A);

        $events = [];
        foreach ($rows as $row) {
            $graph = json_decode($row['graph_json'], true);
            foreach (($graph['nodes'] ?? []) as $node) {
                if (($node['type'] ?? '') === 'trigger' && !empty($node['data']['event'])) {
                    $events[] = $node['data']['event'];
                }
            }
        }

        $events = array_unique($events);
        wp_cache_set($cache_key, $events, 'zaplane', 60);

        return $events;
    }

    public static function get_active_workflows_for_event(string $event): array {
        global $wpdb;
        $cache_key = 'zaplane_triggers_' . md5($event);
        $nodes = wp_cache_get($cache_key, 'zaplane');
        if ($nodes !== false) return $nodes;

        $rows = $wpdb->get_results("
            SELECT w.id AS workflow_id, w.user_id, wv.graph_json, wv.graph_hash
            FROM {$wpdb->prefix}zaplane_workflows w
            JOIN {$wpdb->prefix}zaplane_workflow_versions wv
            ON w.id = wv.workflow_id
            WHERE w.status = 'active' AND wv.is_active = 1
        ", ARRAY_A);

        error_log(print_r('Rows', true));
        error_log(print_r($rows, true));
        $nodes = [];
        foreach ($rows as $row) {
            $graph = json_decode($row['graph_json'], true);
            foreach (($graph['nodes'] ?? []) as $node) {
                if (($node['type'] ?? '') === 'trigger' && ($node['data']['event'] ?? '') === $event) {
                    $nodes[] = [
                        'workflow_id' => $row['workflow_id'],
                        'workflow_version_hash' => $row['graph_hash'],
                        'user_id' => $row['user_id'],
                        'id' => $node['id'],
                        'app' => $node['data']['app'] ?? '',
                        'data' => $node['data'] ?? [],
                        'graph_node' => $node,
                    ];
                }
            }
        }

        wp_cache_set($cache_key, $nodes, 'zaplane', 60);
        return $nodes;
    }

    /* =====================================================
     * RUNS
     * ===================================================== */

    public static function create_run(array $node, array $payload): int {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'zaplane_runs',
            [
                'workflow_version_hash'     => $node['workflow_version_hash'],
                'trigger_data'    => wp_json_encode($payload),
                'status'          => 'running',
                'started_at'      => current_time('mysql')
            ]
        );
        return (int) $wpdb->insert_id;
    }

    public static function handle_trigger_node(array $node, array $payload): void {
        error_log(print_r('handle_trigger_node', true));
        $run_id = self::create_run($node, $payload);
        if (!$run_id) return;
        self::schedule_node($run_id, $node['id']);
    }

    /* =====================================================
     * EXECUTION
     * ===================================================== */

    public static function schedule_node(int $run_id, string $node_id, int $delay = 0): void {
        if (!function_exists('as_enqueue_async_action')) return;
        error_log(print_r('schedule node', true));
        error_log(print_r('Node ID', true));
        error_log(print_r($node_id, true));
        as_enqueue_async_action(
            'zaplane_execute_node',
            ['run_id' => $run_id, 'node_id' => $node_id],
            'zaplane-workflows',
            time() + $delay
        );
    }

    public static function execute_node(int $run_id, string $node_id): void {
        global $wpdb;

        // 1️⃣ Load the run
        $run = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id = %d", $run_id),
            ARRAY_A
        );
        if (!$run) return;

        // 2️⃣ Load the frozen graph JSON using workflow_version_hash
        $graph_json = $wpdb->get_var(
            $wpdb->prepare("
                SELECT graph_json
                FROM {$wpdb->prefix}zaplane_workflow_versions
                WHERE graph_hash = %s
                LIMIT 1
            ", $run['workflow_version_hash'])
        );
        if (!$graph_json) return;

        $graph = json_decode($graph_json, true);
        if (!$graph) return;

        // 3️⃣ Find the current node in the graph
        $node = null;
        foreach (($graph['nodes'] ?? []) as $n) {
            if ((string)($n['id'] ?? '') === (string)$node_id) {
                $node = $n;
                break;
            }
        }
        if (!$node) return;


        // 4️⃣ Load the integration
        $integration_name = strtolower($node['data']['app'] ?? '');
        $integration = IntegrationLoader::get($integration_name);
        if (!$integration) return;

        // 5️⃣ Prepare input for the node
        $input = json_decode($run['trigger_data'], true) ?: [];
        $input['_run_id'] = $run_id;
        $max_attempts = 3; // Maximum retry attempts
        try {
            // 6️⃣ Execute the node
            $result = $integration::execute_node($node, $input);
            error_log(print_r('Print Result', true));
            error_log(print_r($result, true));
            error_log(print_r('End Print Result', true));
            // 7️⃣ Log node execution (optional)
            // do_action('zaplane_node_executed', $run_id, $node_id, 'success', $result);

            // 8️⃣ Schedule next nodes based on edges
            if (!empty($result['port'])) {
                self::schedule_next_nodes_from_graph($run_id, $node_id, $graph, $result['data'] ?? []);
            }

        } catch (\Throwable $e) {
            global $wpdb;
            // Increment attempts
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}zaplane_runs 
                    SET attempts = attempts + 1, last_error = %s
                    WHERE id = %d",
                    $e->getMessage(),
                    $run_id
                )
            );

            // Get current attempts
            $attempts = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT attempts FROM {$wpdb->prefix}zaplane_runs WHERE id = %d", $run_id)
            );

            // Retry only if attempts < max
            if ($attempts < $max_attempts) {
                self::schedule_node($run_id, $node_id, 60); // retry after 60s
            } else {
                // Mark run as failed
                $wpdb->update(
                    $wpdb->prefix . 'zaplane_runs',
                    ['status' => 'failed'],
                    ['id' => $run_id]
                );
            }

            // do_action('zaplane_node_executed', $run_id, $node_id, 'failed', ['error' => $e->getMessage()]);
        }
    }

    protected static function schedule_next_nodes_from_graph(
        int $run_id,
        string $node_id,
        array $graph,
        array $data = []
    ): void {
        $edges = $graph['edges'] ?? [];

        foreach ($edges as $edge) {
            if ((string)($edge['source'] ?? '') === (string)$node_id) {
                $target_node_id = $edge['target'];
                self::schedule_node($run_id, $target_node_id);
            }
        }
    }


    /* =====================================================
     * CACHE HELPERS
     * ===================================================== */

    public static function flush_trigger_cache(): void {
        wp_cache_delete('zaplane_active_trigger_events', 'zaplane');
    }
}
