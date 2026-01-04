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

    /* =========================
     * BOOTSTRAP
     * ========================= */

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
        add_action('zaplane_workflow_updated', [$this, 'reload_triggers']);

        // Action Scheduler execution hook
        add_action('zaplane_execute_node_run', [$this, 'dispatch_node_run'], 10, 1);
    }

    /* =========================
     * ACTION SCHEDULER
     * ========================= */

    public function schedule_node_run(int $node_run_id): void {
        if (!function_exists('as_enqueue_async_action')) return;

        as_enqueue_async_action(
            'zaplane_execute_node_run',
            ['node_run_id' => $node_run_id],
            'zaplane'
        );
    }

    public function dispatch_node_run(int $node_run_id): void {
        global $wpdb;

        $nr = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE id=%d", $node_run_id),
            ARRAY_A
        );

        if (!$nr || $nr['status'] !== 'pending') return;

        // Lock THIS execution only
        $wpdb->update(
            $wpdb->prefix.'zaplane_node_runs',
            ['status'=>'running','started_at'=>current_time('mysql')],
            ['id'=>$node_run_id]
        );

        $this->execute_node_run($nr);
    }

    /* =========================
     * TRIGGERS
     * ========================= */

    public function reload_triggers(): void {
        foreach ($this->registered_hooks as $event=>$cb) {
            remove_action($event, $cb, 10);
        }
        $this->registered_hooks = [];
        $this->dispatch_active_triggers();
    }

    public function dispatch_active_triggers(): void {
        foreach (Query::get_active_trigger_events() as $event) {
            if (isset($this->registered_hooks[$event])) continue;
            $cb = [$this,'automation_trigger_router'];
            add_action($event,$cb,10,99);
            $this->registered_hooks[$event]=$cb;
        }
    }

    public function automation_trigger_router(): void {
        $event = current_filter();
        $args = func_get_args();

        foreach (Query::get_active_workflows_for_event($event) as $trigger) {
            $integration = $this->container->get('integrations')->get(strtolower($trigger['app']));
            if (!$integration) continue;

            $payload = $integration::resolve_trigger($trigger['graph_node']['data'],$args);
            if (!$payload) continue;

            $this->start_workflow($trigger,$payload);
        }
    }

    /* =========================
     * START RUN
     * ========================= */

    private function start_workflow(array $trigger, array $payload): void {
        global $wpdb;

        $wpdb->insert($wpdb->prefix.'zaplane_runs',[
            'workflow_version_hash'=>$trigger['workflow_version_hash'],
            'trigger_data'=>wp_json_encode($payload),
            'status'=>'running',
            'started_at'=>current_time('mysql')
        ]);

        $run_id = $wpdb->insert_id;
        $graph = $this->load_graph_by_hash($trigger['workflow_version_hash']);

        foreach ($graph['edges'] as $e) {
            if ($e['source']===$trigger['id']) {
                $this->spawn_node_run($run_id,$e['target'],$payload,null);
            }
        }
    }

    /* =========================
     * NODE SPAWNING
     * ========================= */

    private function spawn_node_run(int $run_id,string $node_key,array $data,?int $parent): void {
        global $wpdb;

        $wpdb->insert($wpdb->prefix.'zaplane_node_runs',[
            'run_id'=>$run_id,
            'node_key'=>$node_key,
            'parent_node_run_id'=>$parent,
            'status'=>'pending',
            'input_json'=>wp_json_encode($data),
            'started_at'=>current_time('mysql')
        ]);

        $node_run_id=$wpdb->insert_id;
        $this->schedule_node_run($node_run_id);
    }

    /* =========================
     * NODE EXECUTION
     * ========================= */

    private function execute_node_run(array $nr): void {
        global $wpdb;

        $run=$wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id=%d",$nr['run_id']
        ),ARRAY_A);

        $graph=$this->load_graph_by_hash($run['workflow_version_hash']);

        foreach ($graph['nodes'] as $n) {
            if ($n['id']===$nr['node_key']) $node=$n;
        }
        if (!isset($node)) return;

        try {
            $integration=$this->container->get('integrations')->get(strtolower($node['data']['app']));
            $input=json_decode($nr['input_json'],true);
            $result=$integration::execute_node($node,$input);

            $wpdb->update($wpdb->prefix.'zaplane_node_runs',[
                'status'=>'completed',
                'output_json'=>wp_json_encode($result),
                'finished_at'=>current_time('mysql')
            ],['id'=>$nr['id']]);

            foreach ($graph['edges'] as $e) {
                if ($e['source']===$nr['node_key']) {
                    $this->spawn_node_run($nr['run_id'],$e['target'],$result['data']??[],$nr['id']);
                }
            }

        } catch (\Throwable $e) {
            $wpdb->update($wpdb->prefix.'zaplane_node_runs',[
                'status'=>'failed',
                'output_json'=>json_encode(['error'=>$e->getMessage()]),
                'finished_at'=>current_time('mysql')
            ],['id'=>$nr['id']]);
        }

        $this->finalize_run($nr['run_id']);
    }

    /* =========================
     * FINALIZE
     * ========================= */

    private function finalize_run(int $run_id): void {
        global $wpdb;

        $open=$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zaplane_node_runs 
             WHERE run_id=%d AND status IN ('pending','running')",$run_id));

        if ($open>0) return;

        $failed=$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}zaplane_node_runs 
             WHERE run_id=%d AND status='failed'",$run_id));

        $wpdb->update($wpdb->prefix.'zaplane_runs',[
            'status'=>$failed?'failed':'completed',
            'finished_at'=>current_time('mysql')
        ],['id'=>$run_id]);
    }

    /* =========================
     * GRAPH LOADING
     * ========================= */

    private function load_graph_by_hash(string $hash): array {
        global $wpdb;
        return json_decode($wpdb->get_var(
            $wpdb->prepare("SELECT graph_json FROM {$wpdb->prefix}zaplane_workflow_versions WHERE graph_hash=%s",$hash)
        ),true) ?: [];
    }
}
