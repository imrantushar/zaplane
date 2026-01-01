<?php
namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;

if (!defined('ABSPATH')) exit;

class RunController extends WP_REST_Controller {

    public function register_routes() {
        $namespace = 'zaplane/v1';
        $rest_base = 'workflows';

        register_rest_route($namespace,'/runs',[
            'methods'=>'GET',
            'callback'=> [$this, 'api_runs'],
            'permission_callback'=>[$this, 'permissions_check']
        ]);

        register_rest_route($namespace,'/run/(?P<id>\d+)',[
            'methods'=>'GET',
            'callback'=> [$this, 'api_run'],
            'permission_callback'=>[$this, 'permissions_check']
        ]);

        register_rest_route($namespace,'/queue',[
            'methods'=>'GET',
            'callback'=> [$this, 'api_queue'],
            'permission_callback'=>[$this, 'permissions_check']
        ]);

        register_rest_route($namespace,'/node-run/(?P<id>\d+)/retry',[
            'methods'=>'POST',
            'callback'=> [$this, 'api_retry_node'],
            'permission_callback'=>[$this, 'permissions_check']
        ]);

        register_rest_route($namespace,'/run/(?P<id>\d+)/replay',[
            'methods'=>'POST',
            'callback'=> [$this, 'api_replay_run'],
            'permission_callback'=> [$this, 'permissions_check']
        ]);
    }

    public function permissions_check() {
        return current_user_can('manage_options');
    }

    public function api_runs(){
        global $wpdb;

        return $wpdb->get_results("
            SELECT id, workflow_version_hash, status, started_at, finished_at, last_error
            FROM {$wpdb->prefix}zaplane_runs
            ORDER BY id DESC
            LIMIT 100
        ",ARRAY_A);
    }

    public function api_run($req){
        global $wpdb;
        $id=(int)$req['id'];

        $run=$wpdb->get_row("SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id=$id",ARRAY_A);
        $nodes=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE run_id=$id",ARRAY_A);
        $edges=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}zaplane_execution_edges WHERE run_id=$id",ARRAY_A);
        $logs=$wpdb->get_results("
            SELECT l.*,nr.node_key
            FROM {$wpdb->prefix}zaplane_node_logs l
            JOIN {$wpdb->prefix}zaplane_node_runs nr ON nr.id=l.node_run_id
            WHERE nr.run_id=$id
            ORDER BY l.id
        ",ARRAY_A);

        return [
            'run'=>$run,
            'nodes'=>$nodes,
            'edges'=>$edges,
            'logs'=>$logs
        ];
    }

    public function api_queue(){
        global $wpdb;

        return $wpdb->get_results("
            SELECT q.id, q.available_at, q.locked_at, nr.node_key, q.run_id
            FROM {$wpdb->prefix}zaplane_queue q
            JOIN {$wpdb->prefix}zaplane_node_runs nr ON nr.id=q.node_run_id
            ORDER BY q.available_at
        ",ARRAY_A);
    }

    public function api_retry_node($req){
        global $wpdb;
        $id=(int)$req['id'];

        $nr=$wpdb->get_row("SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE id=$id",ARRAY_A);
        if(!$nr) return ['error'=>'Not found'];

        $wpdb->update($wpdb->prefix.'zaplane_node_runs',[
            'status'=>'pending',
            'started_at'=>null,
            'finished_at'=>null
        ],['id'=>$id]);

        $wpdb->insert($wpdb->prefix.'zaplane_queue',[
            'run_id'=>$nr['run_id'],
            'node_run_id'=>$id,
            'available_at'=>current_time('mysql')
        ]);

        return ['status'=>'queued'];
    }

    public function api_replay_run($req){
        global $wpdb;
        $id=(int)$req['id'];

        $old=$wpdb->get_row("SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id=$id",ARRAY_A);
        if(!$old) return ['error'=>'Not found'];

        // Create new run with same trigger
        $wpdb->insert($wpdb->prefix.'zaplane_runs',[
            'workflow_version_hash'=>$old['workflow_version_hash'],
            'trigger_data'=>$old['trigger_data'],
            'status'=>'running'
        ]);

        $new_id=$wpdb->insert_id;

        // Find trigger node
        $graph=$wpdb->get_var($wpdb->prepare(
            "SELECT graph_json FROM {$wpdb->prefix}zaplane_workflow_versions WHERE graph_hash=%s",
            $old['workflow_version_hash']
        ));
        $graph=json_decode($graph,true);

        foreach($graph['nodes'] as $n){
            if($n['type']==='trigger'){
                foreach($graph['edges'] as $e){
                    if($e['source']===$n['id']){
                        Zaplane()->automation->spawn_node_run(
                            $new_id,
                            $e['target'],
                            json_decode($old['trigger_data'],true),
                            null
                        );
                    }
                }
                break;
            }
        }

        return ['new_run'=>$new_id];
    }






}