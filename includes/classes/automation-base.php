<?php
namespace Zaplane\Classes;

use Zaplane\Classes\IntegrationLoader;

if (!defined('ABSPATH')) exit;

class AutomationBase {

    /* =====================================================
     * TRIGGER SYSTEM
     * ===================================================== */

    public function get_active_trigger_events(): array {
        global $wpdb;

        $rows = $wpdb->get_results("
            SELECT wv.graph_json
            FROM {$wpdb->prefix}zaplane_workflows w
            JOIN {$wpdb->prefix}zaplane_workflow_versions wv 
            ON w.id = wv.workflow_id
            WHERE w.status='active' AND wv.is_active=1
        ", ARRAY_A);

        $events = [];
        foreach ($rows as $r) {
            $g = json_decode($r['graph_json'], true);
            foreach ($g['nodes'] ?? [] as $n) {
                if (($n['type'] ?? '') === 'trigger' && !empty($n['data']['event'])) {
                    $events[] = $n['data']['event'];
                }
            }
        }

        return array_unique($events);
    }

    public function get_active_workflows_for_event(string $event): array {
        global $wpdb;

        $rows = $wpdb->get_results("
            SELECT w.id,w.user_id,wv.graph_json,wv.graph_hash
            FROM {$wpdb->prefix}zaplane_workflows w
            JOIN {$wpdb->prefix}zaplane_workflow_versions wv 
            ON w.id=wv.workflow_id
            WHERE w.status='active' AND wv.is_active=1
        ", ARRAY_A);

        $out = [];
        foreach ($rows as $r) {
            $g = json_decode($r['graph_json'], true);
            foreach ($g['nodes'] ?? [] as $n) {
                if (($n['type'] ?? '') === 'trigger' && ($n['data']['event'] ?? '') === $event) {
                    $out[] = [
                        'workflow_version_hash' => $r['graph_hash'],
                        'id' => $n['id'],
                        'app' => $n['data']['app'] ?? '',
                        'graph_node' => $n
                    ];
                }
            }
        }

        return $out;
    }

    /* =====================================================
     * RUN CREATION & TRIGGER HANDLING
     * ===================================================== */

    public function handle_trigger_node(array $trigger, array $payload) {
        global $wpdb;

        // 1️⃣ Create workflow run
        $wpdb->insert($wpdb->prefix . 'zaplane_runs', [
            'workflow_version_hash' => $trigger['workflow_version_hash'],
            'trigger_data' => wp_json_encode($payload),
            'status' => 'running',
            'started_at' => current_time('mysql')
        ]);
        $run_id = $wpdb->insert_id;

        // 2️⃣ Load graph
        $graph = $this->load_graph_by_hash($trigger['workflow_version_hash']);

        // 3️⃣ Schedule child nodes
        foreach ($graph['edges'] ?? [] as $e) {
            if ((string)$e['source'] === (string)$trigger['id']) {
                $this->spawn_node_run($run_id, $e['target'], $payload, null);
            }
        }
    }

    /* =====================================================
     * NODE SPAWNING
     * ===================================================== */

    private function spawn_node_run(int $run_id, string $node_key, array $data, ?int $parent_node_run_id) {
        global $wpdb;

        // 1️⃣ Create node_run
        $wpdb->insert($wpdb->prefix . 'zaplane_node_runs', [
            'run_id' => $run_id,
            'node_key' => $node_key,
            'parent_node_run_id' => $parent_node_run_id,
            'status' => 'pending',
            'input_json' => wp_json_encode($data)
        ]);
        $node_run_id = $wpdb->insert_id;

        // 2️⃣ Insert into queue
        $wpdb->insert($wpdb->prefix . 'zaplane_queue', [
            'run_id' => $run_id,
            'node_run_id' => $node_run_id,
            'available_at' => current_time('mysql')
        ]);

        // 3️⃣ Schedule via Action Scheduler
        $this->schedule_node($run_id, $node_key);

        // 4️⃣ Insert execution edge if parent exists
        if ($parent_node_run_id) {
            $wpdb->insert($wpdb->prefix . 'zaplane_execution_edges', [
                'run_id' => $run_id,
                'from_node_run_id' => $parent_node_run_id,
                'to_node_key' => $node_key,
                'payload_json' => wp_json_encode($data),
                'created_at' => current_time('mysql')
            ]);
        }
    }

    /* =====================================================
     * ACTION SCHEDULER
     * ===================================================== */

    public function schedule_node(int $run_id, string $node_id, int $delay = 0): void {
        if (!function_exists('as_enqueue_async_action')) return;

        as_enqueue_async_action(
            'zaplane_execute_node',
            [
                'run_id' => $run_id,
                'node_id' => $node_id
            ],
            'zaplane-workflows',
            time() + $delay
        );
    }

    /* =====================================================
     * WORKER TICK
     * ===================================================== */

    public function worker_tick() {
        global $wpdb;

        $job = $wpdb->get_row("
            SELECT * FROM {$wpdb->prefix}zaplane_queue
            WHERE locked_at IS NULL
              AND available_at <= NOW()
            ORDER BY id
            LIMIT 1
        ", ARRAY_A);

        if (!$job) return;

        // Lock
        $lock = wp_generate_uuid4();
        $wpdb->update(
            $wpdb->prefix . 'zaplane_queue',
            [
                'locked_at' => current_time('mysql'),
                'lock_token' => $lock
            ],
            ['id' => $job['id']]
        );

        // Execute
        $node_run = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE id=%d",
                $job['node_run_id']
            ),
            ARRAY_A
        );

        if ($node_run) {
            $this->execute_node_run($node_run);
        }

        // Remove from queue
        $wpdb->delete($wpdb->prefix . 'zaplane_queue', ['id' => $job['id']]);
    }

    /* =====================================================
     * NODE EXECUTION
     * ===================================================== */

    public function execute_node_run(array $nr) {
        global $wpdb;

        if ($nr['status'] !== 'pending') return;

        $wpdb->update($wpdb->prefix . 'zaplane_node_runs', [
            'status' => 'running',
            'started_at' => current_time('mysql')
        ], ['id' => $nr['id']]);

        $run = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id=%d", $nr['run_id']),
            ARRAY_A
        );

        $graph = $this->load_graph_by_hash($run['workflow_version_hash']);

        // Find node in graph
        $node = null;
        foreach ($graph['nodes'] ?? [] as $n) {
            if ((string)$n['id'] === (string)$nr['node_key']) {
                $node = $n;
                break;
            }
        }

        if (!$node) return;

        $integration = IntegrationLoader::get(strtolower($node['data']['app'] ?? ''));
        $input = json_decode($nr['input_json'], true);

        try {
            $result = $integration::execute_node($node, $input);

            // Update node_run
            $wpdb->update($wpdb->prefix . 'zaplane_node_runs', [
                'status' => 'completed',
                'output_json' => wp_json_encode($result),
                'finished_at' => current_time('mysql')
            ], ['id' => $nr['id']]);

            // Log node success
            $wpdb->insert($wpdb->prefix . 'zaplane_node_logs', [
                'node_run_id' => $nr['id'],
                'level' => 'info',
                'message' => wp_json_encode(['input'=>$input,'output'=>$result]),
                'created_at' => current_time('mysql')
            ]);

            // Spawn child nodes
            foreach ($graph['edges'] ?? [] as $e) {
                if ((string)$e['source'] === (string)$nr['node_key']) {
                    $this->spawn_node_run(
                        $nr['run_id'],
                        $e['target'],
                        $result['data'] ?? [],
                        $nr['id']
                    );
                }
            }

        } catch (\Throwable $e) {
            // Update node_run as failed
            $wpdb->update($wpdb->prefix . 'zaplane_node_runs', [
                'status' => 'failed',
                'output_json' => json_encode(['error'=>$e->getMessage()])
            ], ['id' => $nr['id']]);

            // Log node error
            $wpdb->insert($wpdb->prefix . 'zaplane_node_logs', [
                'node_run_id' => $nr['id'],
                'level' => 'error',
                'message' => $e->getMessage(),
                'created_at' => current_time('mysql')
            ]);

            // Handle error edges
            $this->route_error_path($nr, $e);
        }
    }

    /* =====================================================
     * ERROR PATHS
     * ===================================================== */

    private function route_error_path(array $nr, \Throwable $e) {
        global $wpdb;

        $graph = $this->load_graph_by_hash(
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT workflow_version_hash FROM {$wpdb->prefix}zaplane_runs WHERE id=%d",
                    $nr['run_id']
                )
            )
        );

        foreach ($graph['edges'] ?? [] as $edge) {
            if ((string)$edge['source'] === (string)$nr['node_key']
                && ($edge['type'] ?? '') === 'error') {

                $this->spawn_node_run(
                    $nr['run_id'],
                    $edge['target'],
                    ['error' => $e->getMessage()],
                    $nr['id']
                );
            }
        }
    }

    /* =====================================================
     * GRAPH LOADING
     * ===================================================== */

    protected function load_graph_by_hash(string $hash): array {
        global $wpdb;
        $json = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT graph_json FROM {$wpdb->prefix}zaplane_workflow_versions WHERE graph_hash=%s",
                $hash
            )
        );
        return json_decode($json, true) ?: [];
    }
}
