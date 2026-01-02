<?php
namespace Zaplane\Modules;

use Zaplane\Classes\Container;
use Zaplane\Core\ModuleInterface;
use Zaplane\Classes\Logger;

if (!defined('ABSPATH')) exit;

class Automation implements ModuleInterface {

    protected static ?self $instance = null;
    protected array $registered_hooks = [];

    // Hold container reference
    protected Container $container;

    /**
     * Singleton instance
     */
    public static function init(Container $container): self {
        if (!self::$instance) {
            self::$instance = new self($container);
            self::$instance->register_hooks();
        }
        return self::$instance;
    }

    public function __construct(Container $container) {
        $this->container = $container;
    }

    /**
     * Register WordPress hooks
     */
    public function register_hooks(): void {

        add_action('init', [$this, 'dispatch_active_triggers']);
        add_action('zaplane_resume_runs', [$this, 'resume_paused_runs']);
        add_action('zaplane_node_executed', [$this, 'log_node_execution'], 10, 4);
        add_action('zaplane_workflow_updated', [$this, 'reload_triggers']);


        // Action Scheduler hook
        add_action('zaplane_execute_node', [$this, 'dispatch_execute_node'], 10, 2);
    }


    public function dispatch_execute_node($run_id, $node_id) {
        if (empty($run_id) || empty($node_id)) return;

        global $wpdb;

        // Fetch the correct node_run from queue
        $node_run = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zaplane_node_runs 
                WHERE run_id=%d AND node_key=%s AND status='pending' 
                ORDER BY id ASC LIMIT 1",
                $run_id,
                $node_id
            ),
            ARRAY_A
        );

        if (!$node_run) return;

        // Call base class method to execute it
        $this->execute_node_run($node_run);
        // After processing this node, check if run is complete
        $this->finalize_run((int)$node_run['run_id']);
    }

    public function reload_triggers(): void {
        $this->deregister_hooks();
        $this->dispatch_active_triggers();
    }

    public function dispatch_active_triggers(): void {
        $events = $this->get_active_trigger_events();
        if (empty($events)) return;
        foreach ($events as $event) {
            if (isset($this->registered_hooks[$event])) continue;
            $callback = [$this, 'automation_trigger_router'];
            add_action($event, $callback, 10, 99);
            $this->registered_hooks[$event] = $callback;
        }
    }

    protected function deregister_hooks(): void {
        foreach ($this->registered_hooks as $event => $callback) {
            remove_action($event, $callback, 10);
        }
        $this->registered_hooks = [];
    }

    public function automation_trigger_router(): void {
        $event = current_filter();
        $args  = func_get_args();

        Logger::reset();
        Logger::log("Trigger fired: automation_trigger_router", [
            'event' => $event,
            'args'  => $args
        ]);

        $trigger_nodes = $this->get_active_workflows_for_event($event);
        if (empty($trigger_nodes)) return;

        // ✅ Get integration via container
        $integrationLoader = $this->container->get('integrations');

        foreach ($trigger_nodes as $node) {
            error_log(print_r($node, true));

            $integration = $integrationLoader->get(strtolower($node['app'] ?? ''));
            error_log(print_r($integration, true));
            if (!$integration) continue;

            $payload = $integration::resolve_trigger((array)$node['graph_node']['data'], $args);
            if (!$payload) continue;

            $this->handle_trigger_node($node, $payload);
        }
    }

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

        // Start Developer Only
        Logger::log("Run Created: handle_trigger_node", [
            'run_id' => $run_id
        ]);
        // End Developer Only

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

    public function spawn_node_run(int $run_id, string $node_key, array $data, ?int $parent_node_run_id) {
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

        // Start Developer Only
        Logger::log("Spawning spawn_node_run", [
            'run_id' => $run_id,
            'node_run_id' => $node_run_id,
            'parent_node_run_id' => $parent_node_run_id
        ]);
        // End Developer Only
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

        Logger::log("Queue tick");

        $job = $wpdb->get_row("
            SELECT * FROM {$wpdb->prefix}zaplane_queue
            WHERE locked_at IS NULL
              AND available_at <= NOW()
            ORDER BY id
            LIMIT 1
        ", ARRAY_A);

        if (!$job) {
            Logger::log("Queue empty");
            return;
        }

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

        Logger::log("Job locked", [
            'job' => $job['id'],
            'node_run' => $job['node_run_id']
        ]);

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

        Logger::log("Executing node", [
            'node_run' => $nr['id'],
            'node' => $nr['node_key']
        ]);


        if (!$node) return;

        Logger::log("Calling integration", [
            'app' => $node['data']['app']
        ]);

        $integrationLoader = $this->container->get('integrations');
        $integration = $integrationLoader->get(strtolower($node['data']['app'] ?? ''));
        $input = json_decode($nr['input_json'], true);

        try {
            $result = $integration::execute_node($node, $input);
            Logger::log("Node success", [
                'node_run' => $nr['id']
            ]);
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
            Logger::log("Routing success edges", [
                'from' => $nr['node_key']
            ]);

        } catch (\Throwable $e) {
            global $wpdb;
            // 1️⃣ Update node_run as failed
            $wpdb->update(
                $wpdb->prefix . 'zaplane_node_runs',
                [
                    'status' => 'failed',
                    'output_json' => json_encode(['error'=>$e->getMessage()]),
                    'finished_at' => current_time('mysql')
                ],
                ['id' => $nr['id']]
            );
            // 2️⃣ Log node error
            $wpdb->insert(
                $wpdb->prefix . 'zaplane_node_logs',
                [
                    'node_run_id' => $nr['id'],
                    'level' => 'error',
                    'message' => $e->getMessage(),
                    'created_at' => current_time('mysql')
                ]
            );
            // 3️⃣ Handle error edges
            $this->route_error_path($nr, $e);
            // 4️⃣ Finalize workflow run
            $this->finalize_run((int)$nr['run_id']);
        }
    }

    public function finalize_run(int $run_id): void {
        global $wpdb;

        // Check if any node is still pending/running
        $pending_nodes = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}zaplane_node_runs 
                WHERE run_id=%d AND status IN ('pending','running')",
                $run_id
            )
        );

        if ((int)$pending_nodes === 0) {
            // Determine if any node failed
            $failed_node = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT output_json FROM {$wpdb->prefix}zaplane_node_runs 
                    WHERE run_id=%d AND status='failed' ORDER BY id DESC LIMIT 1",
                    $run_id
                )
            );

            $status = $failed_node ? 'failed' : 'completed';
            $last_error = $failed_node ? json_decode($failed_node, true)['error'] ?? '' : null;

            // Update run
            $wpdb->update(
                $wpdb->prefix.'zaplane_runs',
                [
                    'status' => $status,
                    'finished_at' => current_time('mysql'),
                    'last_error' => $last_error
                ],
                ['id'=>$run_id]
            );
        }
    }


    /* =====================================================
     * ERROR PATHS
     * ===================================================== */

    private function route_error_path(array $nr, \Throwable $e) {
        global $wpdb;

        Logger::log("Routing error edges", [
            'run_id' => $nr['run_id'],
            'error' => $e->getMessage()
        ], 'warn');

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
