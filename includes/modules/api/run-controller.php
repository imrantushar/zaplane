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

    public function list_runs() {
        global $wpdb;
        return $wpdb->get_results("
            SELECT id,status,started_at,finished_at
            FROM {$wpdb->prefix}zaplane_runs
            ORDER BY id DESC
            LIMIT 100
        ", ARRAY_A);
    }

    /* ================= RUN VIEW ================= */

    public function get_run($req) {
        global $wpdb;
        $id = (int) $req['id'];

        return [
            'run' => $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id=%d",$id
            ),ARRAY_A),
            'nodes' => $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE run_id=%d",$id
            ),ARRAY_A)
        ];
    }

    /* ================= REPLAY ================= */

    public function replay_run($req) {
        global $wpdb;
        $old = (int)$req['id'];

        $run = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id=%d",$old
        ),ARRAY_A);

        if(!$run) return new \WP_Error('not_found','Run not found');

        $wpdb->insert("{$wpdb->prefix}zaplane_runs",[
            'workflow_version_hash'=>$run['workflow_version_hash'],
            'trigger_data'=>$run['trigger_data'],
            'status'=>'running',
            'start_node_key'=>$run['start_node_key'],
            'target_node_key'=>$run['target_node_key'],
            'started_at'=>current_time('mysql')
        ]);

        $new = $wpdb->insert_id;

        $this->container->get('automation')->spawn_node_run(
            $new,
            $run['start_node_key'],
            json_decode($run['trigger_data'],true),
            null
        );

        return ['run_id'=>$new];
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
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE id=%d",(int)$req['id']
        ),ARRAY_A);
    }

    public function retry_node($req){
        global $wpdb;
        $id=(int)$req['id'];

        $wpdb->update("{$wpdb->prefix}zaplane_node_runs",[
            'status'=>'pending',
            'started_at'=>null,
            'finished_at'=>null,
            'output_json'=>null
        ],['id'=>$id]);

        as_enqueue_async_action('zaplane_execute_node_run',['node_run_id'=>$id],'zaplane');
        return ['requeued'=>true];
    }

    /* ================= EXECUTE FULL ================= */

    public function execute_workflow($req){
        global $wpdb;

        $workflow = $req['workflow_hash'];
        $data = $req['data'] ?? [];

        $graph=json_decode($wpdb->get_var(
            $wpdb->prepare("SELECT graph_json FROM {$wpdb->prefix}zaplane_workflow_versions WHERE graph_hash=%s",$workflow)
        ),true);

        foreach($graph['nodes'] as $n){
            if($n['type']==='trigger'){ $trigger=$n; break; }
        }

        if(empty($trigger)) return new \WP_Error('no_trigger','No trigger node');

        $wpdb->insert("{$wpdb->prefix}zaplane_runs",[
            'workflow_version_hash'=>$workflow,
            'status'=>'running',
            'trigger_data'=>json_encode($data),
            'start_node_key'=>$trigger['id'],
            'target_node_key'=>null,
            'started_at'=>current_time('mysql')
        ]);

        $run=$wpdb->insert_id;

        $this->container->get('automation')->spawn_node_run($run,$trigger['id'],$data,null);

        return ['run_id'=>$run];
    }

    /* ================= EXECUTE SINGLE NODE ================= */

    public function execute_single_node($req){
        global $wpdb;

        $workflow = $req['workflow_hash'];
        $target   = (string)$req['node_key'];
        $input    = $req['input'] ?? [];

        $graph=json_decode($wpdb->get_var(
            $wpdb->prepare("SELECT graph_json FROM {$wpdb->prefix}zaplane_workflow_versions WHERE graph_hash=%s",$workflow)
        ),true);

        foreach($graph['nodes'] as $n){
            if($n['type']==='trigger'){ $trigger=$n; break; }
        }

        if(empty($trigger)) return new \WP_Error('no_trigger','No trigger node');

        $wpdb->insert("{$wpdb->prefix}zaplane_runs",[
            'workflow_version_hash'=>$workflow,
            'status'=>'running',
            'trigger_data'=>json_encode($input),
            'start_node_key'=>$trigger['id'],
            'target_node_key'=>$target,
            'started_at'=>current_time('mysql')
        ]);

        $run=$wpdb->insert_id;

        $this->container->get('automation')->spawn_node_run(
            $run,
            $trigger['id'],
            $input,
            null
        );

        return [
            'run_id'=>$run,
            'target_node'=>$target
        ];
    }
}
