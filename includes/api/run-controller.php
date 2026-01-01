<?php
namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;

if (!defined('ABSPATH')) exit;

class RunController extends WP_REST_Controller {

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

        register_rest_route($ns, '/queue', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_queue'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/node-runs/(?P<id>\d+)/retry', [
            'methods'  => 'POST',
            'callback' => [$this, 'retry_node'],
            'permission_callback' => [$this, 'permissions']
        ]);

        register_rest_route($ns, '/runs/(?P<id>\d+)/replay', [
            'methods'  => 'POST',
            'callback' => [$this, 'replay_run'],
            'permission_callback' => [$this, 'permissions']
        ]);
    }

    public function permissions() {
        return current_user_can('manage_options');
    }

    /* ======================================================
     * RUNS LIST
     * ====================================================== */

    public function list_runs() {
        global $wpdb;

        return $wpdb->get_results("
            SELECT id, workflow_version_hash, status, started_at, finished_at, last_error
            FROM {$wpdb->prefix}zaplane_runs
            ORDER BY id DESC
            LIMIT 200
        ", ARRAY_A);
    }

    /* ======================================================
     * SINGLE RUN INSPECTOR
     * ====================================================== */

    public function get_run($req) {
        global $wpdb;
        $run_id = (int) $req['id'];

        $run = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id=%d", $run_id),
            ARRAY_A
        );

        $nodes = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE run_id=%d", $run_id),
            ARRAY_A
        );

        $edges = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_execution_edges WHERE run_id=%d", $run_id),
            ARRAY_A
        );

        $logs = $wpdb->get_results(
            $wpdb->prepare("
                SELECT l.*, nr.node_key
                FROM {$wpdb->prefix}zaplane_node_logs l
                JOIN {$wpdb->prefix}zaplane_node_runs nr ON nr.id = l.node_run_id
                WHERE nr.run_id = %d
                ORDER BY l.id
            ", $run_id),
            ARRAY_A
        );

        return [
            'run'   => $run,
            'nodes' => $nodes,
            'edges' => $edges,
            'logs'  => $logs
        ];
    }

    /* ======================================================
     * QUEUE
     * ====================================================== */

    public function get_queue() {
        global $wpdb;

        return $wpdb->get_results("
            SELECT q.id, q.run_id, q.node_run_id, q.available_at, q.locked_at,
                   nr.node_key, nr.status
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
        $id = (int) $req['id'];

        $nr = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}zaplane_node_runs WHERE id=%d", $id),
            ARRAY_A
        );
        if (!$nr) return ['error' => 'Node run not found'];

        $wpdb->update(
            $wpdb->prefix.'zaplane_node_runs',
            [
                'status' => 'pending',
                'started_at' => null,
                'finished_at' => null
            ],
            ['id' => $id]
        );

        $wpdb->insert(
            $wpdb->prefix.'zaplane_queue',
            [
                'run_id'      => $nr['run_id'],
                'node_run_id' => $id,
                'available_at' => current_time('mysql')
            ]
        );

        return ['status' => 'requeued'];
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
        $automation = new \Zaplane\Automation();
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
}
