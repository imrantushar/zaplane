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

        $run = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id = %d", $run_id),
            ARRAY_A
        );
        if (!$run) return;

        $graph_json = $wpdb->get_var("
            SELECT wv.graph_json
            FROM {$wpdb->prefix}zaplane_workflow_versions wv
            JOIN {$wpdb->prefix}zaplane_runs r ON wv.graph_hash = r.workflow_version_hash
            WHERE r.id = {$run_id} AND wv.is_active = 1
        ");
        error_log(print_r('Execute Node', true));
        error_log(print_r($graph_json, true));
        error_log(print_r('End Execute Node', true));
        if (!$graph_json) return;

        $graph = json_decode($graph_json, true);
        $node = null;
        foreach (($graph['nodes'] ?? []) as $n) {
            if (($n['id'] ?? '') === $node_id) {
                $node = $n;
                break;
            }
        }
        if (!$node) return;

        $integration = IntegrationLoader::get(strtolower($node['data']['app']) ?? '');
        if (!$integration) return;

        $input = json_decode($run['trigger_data'], true) ?: [];
        $input['_run_id'] = $run_id;

        try {
            $result = $integration::execute_node($node, $input);

            // do_action('zaplane_node_executed', $run_id, $node_id, 'success', $result);

            if (!empty($result['port'])) {
                self::schedule_next_nodes_from_graph($run_id, $node_id, $result['port'], $graph, $result['data'] ?? []);
            }
        } catch (\Throwable $e) {
            $wpdb->update(
                $wpdb->prefix . 'zaplane_runs',
                ['attempts' => ((int)$run['attempts'] + 1), 'last_error' => $e->getMessage(), 'status' => 'failed'],
                ['id' => $run_id]
            );
            self::schedule_node($run_id, $node_id, 60);
            // do_action('zaplane_node_executed', $run_id, $node_id, 'failed', ['error' => $e->getMessage()]);
        }
    }

    protected static function schedule_next_nodes_from_graph(int $run_id, string $node_id, string $port, array $graph, array $data): void {
        foreach (($graph['edges'] ?? []) as $edge) {
            if (($edge['source'] ?? '') === $node_id && ($edge['sourceHandle'] ?? 'main') === $port) {
                self::schedule_node($run_id, $edge['target'], (int)($edge['delay'] ?? 0));
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
