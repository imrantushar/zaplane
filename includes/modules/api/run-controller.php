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
            'methods'  => 'GET',
            'callback' => [$this, 'list_runs'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/runs/(?P<id>\d+)', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_run'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/runs/(?P<id>\d+)/live', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_live_run'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/queue', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_queue'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/node-runs/(?P<id>\d+)', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_node_run'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/node-runs/(?P<id>\d+)/retry', [
            'methods'  => 'POST',
            'callback' => [$this, 'retry_node'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/runs/(?P<id>\d+)/timeline', [
            'methods'=>'GET',
            'callback'=>[$this,'get_timeline'],
            'permission_callback'=>[$this,'permissions']
        ]);


        register_rest_route($ns, '/runs/(?P<id>\d+)/replay', [
            'methods'  => 'POST',
            'callback' => [$this, 'replay_run'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/runs/(?P<id>\d+)/stop', [
            'methods'=>'POST',
            'callback'=>[$this,'stop_run'],
            'permission_callback'=>[$this,'permissions']
        ]);

    }

    public function permissions() {
        return current_user_can('manage_options');
    }

    /* ======================================================
     * RUNS LIST
     * ====================================================== */

    public function list_runs($req) {
        global $wpdb;

        $page     = max(1, (int) ($req['page'] ?? 1));
        $per_page = min(200, max(10, (int) ($req['per_page'] ?? 50)));
        $offset   = ($page - 1) * $per_page;

        $where = [];
        $args  = [];

        // Filter by status
        if (!empty($req['status'])) {
            $where[] = "r.status = %s";
            $args[]  = sanitize_text_field($req['status']);
        }

        // Filter by workflow
        if (!empty($req['workflow_id'])) {
            $where[] = "v.workflow_id = %d";
            $args[]  = (int)$req['workflow_id'];
        }

        // Search in error message
        if (!empty($req['search'])) {
            $where[] = "r.last_error LIKE %s";
            $args[]  = '%' . $wpdb->esc_like($req['search']) . '%';
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total count (for pagination UI)
        $total = $wpdb->get_var(
            $wpdb->prepare("
                SELECT COUNT(*)
                FROM {$wpdb->prefix}zaplane_runs r
                LEFT JOIN {$wpdb->prefix}zaplane_workflow_versions v
                ON v.graph_hash = r.workflow_version_hash
                $where_sql
            ", $args)
        );

        // Data
        $rows = $wpdb->get_results(
            $wpdb->prepare("
                SELECT 
                    r.id,
                    r.workflow_version_hash,
                    v.workflow_id,
                    r.status,
                    r.started_at,
                    r.finished_at,
                    r.last_error
                FROM {$wpdb->prefix}zaplane_runs r
                LEFT JOIN {$wpdb->prefix}zaplane_workflow_versions v
                ON v.graph_hash = r.workflow_version_hash
                $where_sql
                ORDER BY r.id DESC
                LIMIT %d OFFSET %d
            ", array_merge($args, [$per_page, $offset])),
            ARRAY_A
        );

        return [
            'page'      => $page,
            'per_page' => $per_page,
            'total'    => (int)$total,
            'runs'     => $rows
        ];
    }


    /* ======================================================
     * SINGLE RUN INSPECTOR
     * ====================================================== */

    public function get_run($req) {
        global $wpdb;
        $run_id = (int)$req['id'];

        $run = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT r.*, w.title, w.id AS workflow_id
                FROM {$wpdb->prefix}zaplane_runs r
                JOIN {$wpdb->prefix}zaplane_workflow_versions v ON v.graph_hash=r.workflow_version_hash
                JOIN {$wpdb->prefix}zaplane_workflows w ON w.id=v.workflow_id
                WHERE r.id=%d",
                $run_id
            ),
            ARRAY_A
        );

        $nodes = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE run_id=%d",$run_id),
            ARRAY_A
        );

        $edges = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*, nr.node_key AS from_node
                FROM {$wpdb->prefix}zaplane_execution_edges e
                JOIN {$wpdb->prefix}zaplane_node_runs nr ON nr.id=e.from_node_run_id
                WHERE e.run_id=%d ORDER BY e.id",
                $run_id
            ),
            ARRAY_A
        );

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, nr.node_key
                FROM {$wpdb->prefix}zaplane_node_logs l
                JOIN {$wpdb->prefix}zaplane_node_runs nr ON nr.id=l.node_run_id
                WHERE nr.run_id=%d ORDER BY l.id",
                $run_id
            ),
            ARRAY_A
        );

        return compact('run','nodes','edges','logs');
    }


    /* ======================================================
     * QUEUE
     * ====================================================== */

    public function get_queue() {
        global $wpdb;

        return $wpdb->get_results("
            SELECT 
                q.id,
                q.run_id,
                q.node_run_id,
                q.available_at,
                q.locked_at,
                q.locked_by,
                q.attempts,
                q.last_error,
                nr.node_key,
                nr.status
            FROM {$wpdb->prefix}zaplane_queue q
            JOIN {$wpdb->prefix}zaplane_node_runs nr ON nr.id = q.node_run_id
            ORDER BY q.available_at ASC
        ", ARRAY_A);
    }


    /* ======================================================
     * RETRY NODE
     * ====================================================== */

    public function retry_node($req) {
        global $wpdb;
        $id = (int)$req['id'];

        $nr = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE id=%d", $id),
            ARRAY_A
        );
        if (!$nr) return new \WP_Error('not_found', 'Node run not found', ['status'=>404]);

        // remove old queue entries
        $wpdb->delete($wpdb->prefix.'zaplane_queue', ['node_run_id'=>$id]);

        // reset node state
        $wpdb->update(
            $wpdb->prefix.'zaplane_node_runs',
            [
                'status' => 'pending',
                'attempts' => 0,
                'started_at' => null,
                'finished_at' => null,
                'output_json' => null
            ],
            ['id' => $id]
        );

        // requeue
        $wpdb->insert(
            $wpdb->prefix.'zaplane_queue',
            [
                'run_id' => $nr['run_id'],
                'node_run_id' => $id,
                'available_at' => current_time('mysql')
            ]
        );

        return ['status'=>'requeued'];
    }


    /* ======================================================
     * REPLAY RUN
     * ====================================================== */

    public function replay_run($req) {
        global $wpdb;
        $old_id = (int) $req['id'];

        $old = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id=%d", $old_id),
            ARRAY_A
        );
        if (!$old) return ['error' => 'Run not found'];

        // Create new run
        $wpdb->insert($wpdb->prefix.'zaplane_runs', [
            'workflow_version_hash' => $old['workflow_version_hash'],
            'trigger_data' => $old['trigger_data'],
            'status' => 'running',
            'started_at' => current_time('mysql')
        ]);
        $new_run = $wpdb->insert_id;

        // Load graph
        $graph_json = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT graph_json FROM {$wpdb->prefix}zaplane_workflow_versions WHERE graph_hash=%s",
                $old['workflow_version_hash']
            )
        );
        $graph = json_decode($graph_json, true);
    

        $automation = $this->container->get('automation');
        // Find trigger
        foreach ($graph['nodes'] as $n) {
            if ($n['type'] === 'trigger') {
                foreach ($graph['edges'] as $e) {
                    if ($e['source'] === $n['id']) {
                        $automation->spawn_node_run(
                            $new_run,
                            $e['target'],
                            json_decode($old['trigger_data'], true),
                            null
                        );
                    }
                }
                break;
            }
        }

        return ['new_run_id' => $new_run];
    }

    public function get_live_run($req) {
        global $wpdb;
        $run_id = (int)$req['id'];

        $run = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id=%d", $run_id),
            ARRAY_A
        );
        if (!$run) return new \WP_Error('not_found','Run not found',['status'=>404]);

        $nodes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, node_key, status, attempts, started_at, finished_at
                FROM {$wpdb->prefix}zaplane_node_runs
                WHERE run_id=%d",
                $run_id
            ),
            ARRAY_A
        );

        $edges = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    e.from_node_run_id,
                    nr.node_key AS from_node,
                    e.to_node_key,
                    e.payload_json,
                    e.created_at
                FROM {$wpdb->prefix}zaplane_execution_edges e
                JOIN {$wpdb->prefix}zaplane_node_runs nr ON nr.id=e.from_node_run_id
                WHERE e.run_id=%d
                ORDER BY e.id",
                $run_id
            ),
            ARRAY_A
        );

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.id,l.level,l.message,l.created_at,nr.node_key
                FROM {$wpdb->prefix}zaplane_node_logs l
                JOIN {$wpdb->prefix}zaplane_node_runs nr ON nr.id=l.node_run_id
                WHERE nr.run_id=%d
                ORDER BY l.id DESC
                LIMIT 200",
                $run_id
            ),
            ARRAY_A
        );

        return [
            'run'=>[
                'id'=>$run['id'],
                'status'=>$run['status'],
                'started_at'=>$run['started_at'],
                'finished_at'=>$run['finished_at'],
                'last_error'=>$run['last_error']
            ],
            'nodes'=>$nodes,
            'edges'=>$edges,
            'logs'=>array_reverse($logs)
        ];
    }


    public function get_node_run($req) {
        global $wpdb;
        $id = (int)$req['id'];

        $node = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE id=%d", $id),
            ARRAY_A
        );

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zaplane_node_logs WHERE node_run_id=%d ORDER BY id",
                $id
            ),
            ARRAY_A
        );

        return [
            'node' => $node,
            'logs' => $logs
        ];
    }

    public function get_timeline($req){
        global $wpdb;
        $id=(int)$req['id'];

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zaplane_execution_edges WHERE run_id=%d ORDER BY id",
                $id
            ),
            ARRAY_A
        );
    }

    public function stop_run($req){
        global $wpdb;
        $id=(int)$req['id'];

        // remove queued jobs
        $wpdb->delete($wpdb->prefix.'zaplane_queue',['run_id'=>$id]);

        // unlock running jobs
        $wpdb->update(
            $wpdb->prefix.'zaplane_queue',
            ['locked_at'=>null,'lock_token'=>null],
            ['run_id'=>$id]
        );

        // cancel all nodes
        $wpdb->update(
            $wpdb->prefix.'zaplane_node_runs',
            ['status'=>'cancelled','finished_at'=>current_time('mysql')],
            ['run_id'=>$id]
        );

        // cancel run
        $wpdb->update(
            $wpdb->prefix.'zaplane_runs',
            ['status'=>'cancelled','finished_at'=>current_time('mysql')],
            ['id'=>$id]
        );

        return ['stopped'=>true];
    }





}
