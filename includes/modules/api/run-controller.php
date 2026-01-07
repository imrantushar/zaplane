<?php
namespace Zaplane\Modules\API;

use WP_REST_Controller;
use Zaplane\Classes\Container;

if (!defined('ABSPATH')) exit;

class RunController extends WP_REST_Controller {

    protected Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function register_routes() {
        $ns = 'zaplane/v1';

        register_rest_route($ns, '/runs', [
            'methods' => 'GET',
            'callback' => [$this,'list_runs'],
            'permission_callback'=>[$this,'permissions']
        ]);

        register_rest_route($ns, '/runs/(?P<id>\d+)', [
            'methods'=>'GET',
            'callback'=>[$this,'get_run'],
            'permission_callback'=>[$this,'permissions']
        ]);

        register_rest_route($ns, '/runs/(?P<id>\d+)/replay', [
            'methods'=>'POST',
            'callback'=>[$this,'replay_run'],
            'permission_callback'=>[$this,'permissions']
        ]);

        register_rest_route($ns, '/runs/(?P<id>\d+)/stop', [
            'methods'=>'POST',
            'callback'=>[$this,'stop_run'],
            'permission_callback'=>[$this,'permissions']
        ]);

        register_rest_route($ns, '/runs/(?P<id>\d+)/live', [
            'methods'=>'GET',
            'callback'=>[$this,'live_run'],
            'permission_callback'=>[$this,'permissions']
        ]);

        register_rest_route($ns, '/node-runs/(?P<id>\d+)', [
            'methods'=>'GET',
            'callback'=>[$this,'get_node_run'],
            'permission_callback'=>[$this,'permissions']
        ]);

        register_rest_route($ns, '/node-runs/(?P<id>\d+)/retry', [
            'methods'=>'POST',
            'callback'=>[$this,'retry_node'],
            'permission_callback'=>[$this,'permissions']
        ]);

        register_rest_route($ns, '/execute', [
            'methods'=>'POST',
            'callback'=>[$this,'execute_workflow'],
            'permission_callback'=>[$this,'permissions']
        ]);

        register_rest_route($ns, '/execute-node', [
            'methods'=>'POST',
            'callback'=>[$this,'execute_single_node'],
            'permission_callback'=>[$this,'permissions']
        ]);
    }

    public function permissions() {
        return current_user_can('manage_options');
    }

    /* ================= RUN LIST ================= */

    public function list_runs($req){
        global $wpdb;
        return $wpdb->get_results("
            SELECT id,status,started_at,finished_at
            FROM {$wpdb->prefix}zaplane_runs
            ORDER BY id DESC
            LIMIT 100
        ", ARRAY_A);
    }

    /* ================= RUN VIEW ================= */

    public function get_run($req){
        global $wpdb;
        $id=(int)$req['id'];

        $run = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id=%d",$id),
            ARRAY_A
        );

        $nodes = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE run_id=%d",$id),
            ARRAY_A
        );

        return [
            'run'=>$run,
            'nodes'=>$nodes
        ];
    }

    /* ================= LIVE ================= */

    public function live_run($req){
        global $wpdb;
        $id=(int)$req['id'];

        return [
            'run'=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id=%d",$id),ARRAY_A),
            'nodes'=>$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE run_id=%d",$id),ARRAY_A)
        ];
    }

    /* ================= REPLAY ================= */

    public function replay_run($req){
        global $wpdb;

        $old = (int) $req['id'];

        $run = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id=%d", $old),
            ARRAY_A
        );
        if (!$run) {
            return new \WP_Error('not_found', 'Run not found');
        }

        // Create new run
        $wpdb->insert("{$wpdb->prefix}zaplane_runs", [
            'workflow_version_hash' => $run['workflow_version_hash'],
            'trigger_data' => $run['trigger_data'],
            'status' => 'running',
            'started_at' => current_time('mysql')
        ]);
        $new_run_id = $wpdb->insert_id;

        // Load graph
        $graph_json = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT graph_json FROM {$wpdb->prefix}zaplane_workflow_versions WHERE graph_hash=%s",
                $run['workflow_version_hash']
            )
        );
        $graph = json_decode($graph_json, true);

        // Find trigger node
        $triggerNode = null;
        foreach ($graph['nodes'] as $n) {
            if ($n['type'] === 'trigger') {
                $triggerNode = $n;
                break;
            }
        }

        if (!$triggerNode) {
            return new \WP_Error('no_trigger', 'Workflow has no trigger node');
        }

        $automation = $this->container->get('automation');

        // Spawn trigger node run
        $automation->spawn_node_run(
            $new_run_id,
            (string)$triggerNode['id'],
            json_decode($run['trigger_data'], true),
            null
        );

        return ['run_id' => $new_run_id];
    }


    /* ================= STOP ================= */

    public function stop_run($req){
        global $wpdb;
        $id=(int)$req['id'];

        $wpdb->update("{$wpdb->prefix}zaplane_runs",[
            'status'=>'cancelled',
            'finished_at'=>current_time('mysql')
        ],['id'=>$id]);

        $wpdb->update("{$wpdb->prefix}zaplane_node_runs",[
            'status'=>'cancelled',
            'finished_at'=>current_time('mysql')
        ],['run_id'=>$id]);

        return ['stopped'=>true];
    }

    /* ================= NODE ================= */

    public function get_node_run($req){
        global $wpdb;
        $id=(int)$req['id'];

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE id=%d",$id),
            ARRAY_A
        );
    }

    public function retry_node($req){
        global $wpdb;
        $id=(int)$req['id'];

        $nr=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE id=%d",$id),ARRAY_A);

        $wpdb->update("{$wpdb->prefix}zaplane_node_runs",[
            'status'=>'pending',
            'started_at'=>null,
            'finished_at'=>null,
            'output_json'=>null
        ],['id'=>$id]);

        as_enqueue_async_action('zaplane_execute_node_run',['node_run_id'=>$id],'zaplane');

        return ['requeued'=>true];
    }

    /* ================= MANUAL EXECUTION ================= */

    // n8n: "Execute workflow"
    public function execute_workflow($req){
        global $wpdb;
        $workflow_hash=$req['workflow_hash'];
        $data=$req['data'] ?? [];

        $wpdb->insert("{$wpdb->prefix}zaplane_runs",[
            'workflow_version_hash'=>$workflow_hash,
            'status'=>'running',
            'trigger_data'=>json_encode($data),
            'started_at'=>current_time('mysql')
        ]);

        $run_id=$wpdb->insert_id;

        $automation=$this->container->get('automation');
        $automation->spawn_node_run($run_id,'trigger',$data,null);

        return ['run_id'=>$run_id];
    }

    // n8n: "Execute node"
    public function execute_single_node($req){
        global $wpdb;
        $node=$req['node_key'];
        $workflow=$req['workflow_hash'];
        $input=$req['input'] ?? [];

        $wpdb->insert("{$wpdb->prefix}zaplane_runs",[
            'workflow_version_hash'=>$workflow,
            'status'=>'running',
            'trigger_data'=>json_encode($input),
            'started_at'=>current_time('mysql')
        ]);

        $run=$wpdb->insert_id;

        $automation=$this->container->get('automation');
        $automation->spawn_node_run($run,$node,$input,null);

        return ['run_id'=>$run];
    }
}
