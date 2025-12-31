<?php
namespace Zaplane\Classes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AutomationBase { 

    /* =====================================================
     * TRIGGERS
     * ===================================================== */

    public function get_active_trigger_events(): array {
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

    public function get_active_workflows_for_event(string $event): array {
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

    public function create_run(array $node, array $payload): int {
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

    public function handle_trigger_node(array $node, array $payload): void {
        global $wpdb;

        // 1️⃣ Create a run
        $run_id = self::create_run($node, $payload);
        if (!$run_id) return;

        // 2️⃣ Load frozen graph for this run
        $graph_json = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT graph_json 
                FROM {$wpdb->prefix}zaplane_workflow_versions
                WHERE graph_hash = %s
                LIMIT 1",
                $node['workflow_version_hash']
            )
        );
        if (!$graph_json) return;

        $graph = json_decode($graph_json, true);
        if (!$graph) return;

        // 3️⃣ Schedule ONLY nodes connected to the trigger
        self::schedule_next_nodes_from_graph($run_id, (string)$node['id'], $graph);
    }

    /* =====================================================
     * EXECUTION
     * ===================================================== */

    public function schedule_node(int $run_id, string $node_id, int $delay = 0): void {
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

    public function execute_node(int $run_id, string $node_id): void {
        global $wpdb;

        // 1️⃣ Load run
        $run = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id = %d",
                $run_id
            ),
            ARRAY_A
        );
        if (!$run || $run['status'] !== 'running') return;

        // 2️⃣ Load frozen workflow graph
        $graph_json = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT graph_json 
                FROM {$wpdb->prefix}zaplane_workflow_versions
                WHERE graph_hash = %s
                LIMIT 1",
                $run['workflow_version_hash']
            )
        );
        if (!$graph_json) return;

        $graph = json_decode($graph_json, true);
        if (!$graph) return;

        // 3️⃣ Find this node in graph
        $node = null;
        foreach ($graph['nodes'] ?? [] as $n) {
            if ((string)$n['id'] === (string)$node_id) {
                $node = $n;
                break;
            }
        }
        if (!$node) return;

        // 4️⃣ SAFETY: never execute trigger again
        if (($node['type'] ?? '') === 'trigger') {
            return;
        }

        // 5️⃣ Load integration
        $integration = IntegrationLoader::get(strtolower($node['data']['app'] ?? ''));
        if (!$integration) return;

        // 6️⃣ Prepare input
        $input = json_decode($run['trigger_data'], true) ?: [];
        $input['_run_id'] = $run_id;

        try {
            // 7️⃣ Execute node
            $result = $integration::execute_node($node, $input);

            // 8️⃣ Schedule next connected nodes
            self::schedule_next_nodes_from_graph(
                $run_id,
                (string)$node_id,
                $graph,
                $result['data'] ?? []
            );

        } catch (\Throwable $e) {
            // Mark run failed
            $wpdb->update(
                $wpdb->prefix . 'zaplane_runs',
                [
                    'status'     => 'failed',
                    'last_error' => $e->getMessage(),
                    'finished_at' => current_time('mysql')
                ],
                [ 'id' => $run_id ]
            );
        }
    }


    private function schedule_next_nodes_from_graph(
        int $run_id,
        string $node_id,
        array $graph,
        array $data = []
    ): void {
        foreach ($graph['edges'] ?? [] as $edge) {
            if ((string)$edge['source'] === (string)$node_id) {
                $this->schedule_node($run_id, $edge['target']);
            }
        }
    }


    /* =====================================================
     * CACHE HELPERS
     * ===================================================== */

    public function flush_trigger_cache(): void {
        wp_cache_delete('zaplane_active_trigger_events', 'zaplane');
    }

    public function resume_paused_runs(): void {
        global $wpdb;
        $runs = $wpdb->get_results("
            SELECT id, current_node_id
            FROM {$wpdb->prefix}zaplane_runs
            WHERE status = 'paused'
              AND resume_at <= NOW()
        ");
        foreach ($runs as $run) {
            if (!empty($run->current_node_id)) {
                $this->schedule_node($run->id, $run->current_node_id);
            }
        }
    }

    public function log_node_execution($run_id, $node_id, $status, $result): void {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'zaplane_run_logs',
            [
                'run_id'  => $run_id,
                'node_id' => $node_id,
                'status'  => $status,
                'payload' => wp_json_encode($result),
                'created_at' => current_time('mysql')
            ]
        );
    }
}