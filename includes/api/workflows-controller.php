<?php
namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;

if (!defined('ABSPATH')) exit;

class WorkflowsController extends WP_REST_Controller {

    public function register_routes() {
        $namespace = 'zaplane/v1';
        $rest_base = 'workflows';

        // List & Create workflows
        register_rest_route($namespace, '/' . $rest_base, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);

        // Get / Update / Delete workflow
        register_rest_route($namespace, '/' . $rest_base . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);

        // Graph (React Flow)
        register_rest_route($namespace, '/' . $rest_base . '/(?P<id>\d+)/graph', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_graph'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);
    }

    public function permissions_check() {
        return current_user_can('manage_options');
    }

    // -------------------------
    // Workflows
    // -------------------------

    public function get_items() {
        global $wpdb;

        $rows = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}zaplane_workflows
            ORDER BY id DESC
        ");

        return rest_ensure_response($rows);
    }

    public function get_item($request) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zaplane_workflows WHERE id = %d",
                $request['id']
            )
        );

        return rest_ensure_response($row);
    }

    public function create_item($request) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'zaplane_workflows',
            [
                'user_id' => get_current_user_id(),
                'name' => sanitize_text_field($request['name']),
                'status' => 'draft'
            ]
        );

        return rest_ensure_response([
            'id' => $wpdb->insert_id
        ]);
    }

    /**
     * React Flow SAVE
     * Creates a new immutable workflow version
     */
    public function update_item($request) {
        global $wpdb;

        $workflow_id = intval($request['id']);
        $graph = $request->get_json_params();

        if (!isset($graph['nodes']) || !isset($graph['edges'])) {
            return new WP_Error('invalid_graph', 'Invalid React Flow graph', ['status'=>400]);
        }

        $json = wp_json_encode($graph);
        $hash = hash('sha256', $json);

        // deactivate previous versions
        $wpdb->update(
            $wpdb->prefix.'zaplane_workflow_versions',
            ['is_active' => 0],
            ['workflow_id' => $workflow_id]
        );

        // insert frozen version
        $wpdb->insert(
            $wpdb->prefix.'zaplane_workflow_versions',
            [
                'workflow_id' => $workflow_id,
                'graph_json' => $json,
                'graph_hash' => $hash,
                'is_active' => 1
            ]
        );

        return rest_ensure_response([
            'workflow_id' => $workflow_id,
            'version_id' => $wpdb->insert_id
        ]);
    }

    public function delete_item($request) {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix.'zaplane_workflows',
            ['id' => intval($request['id'])]
        );

        return rest_ensure_response(['deleted' => true]);
    }

    // -------------------------
    // React Flow Graph
    // -------------------------

    public function get_graph($request) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT graph_json
                 FROM {$wpdb->prefix}zaplane_workflow_versions
                 WHERE workflow_id = %d AND is_active = 1",
                $request['id']
            )
        );

        if (!$row) {
            return rest_ensure_response(['nodes'=>[], 'edges'=>[]]);
        }

        return rest_ensure_response(json_decode($row->graph_json, true));
    }
}
