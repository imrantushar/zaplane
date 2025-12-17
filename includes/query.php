<?php
namespace Zaplane;

use Zaplane\Classes\IntegrationLoader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Query {

    /* =====================================================
     * TRIGGERS
     * ===================================================== */

    /**
     * Get unique active trigger events
     * Used ONLY for registering WordPress hooks
     */
    public static function get_active_trigger_events(): array {
        global $wpdb;

        $cache_key = 'zaplane_active_trigger_events';
        $events = wp_cache_get( $cache_key, 'zaplane' );

        if ( $events !== false ) {
            return $events;
        }

        $events = $wpdb->get_col("
            SELECT DISTINCT n.event
            FROM {$wpdb->prefix}zaplane_nodes n
            JOIN {$wpdb->prefix}zaplane_workflows w ON w.id = n.workflow_id
            WHERE n.node_type = 'trigger'
              AND w.status = 'active'
              AND n.event != ''
        ");

        wp_cache_set( $cache_key, $events, 'zaplane', 60 );

        return $events ?: [];
    }

    /**
     * Get trigger nodes for a specific event
     */
    public static function get_active_workflows_for_event( string $event ): array {
        global $wpdb;

        $cache_key = 'zaplane_triggers_' . md5( $event );
        $nodes = wp_cache_get( $cache_key, 'zaplane' );

        if ( $nodes !== false ) {
            return $nodes;
        }

        $nodes = $wpdb->get_results(
            $wpdb->prepare("
                SELECT 
                    n.*,
                    w.user_id,
                    w.status AS workflow_status
                FROM {$wpdb->prefix}zaplane_nodes n
                JOIN {$wpdb->prefix}zaplane_workflows w ON w.id = n.workflow_id
                WHERE n.node_type = 'trigger'
                  AND n.event = %s
                  AND w.status = 'active'
            ", $event ),
            ARRAY_A
        );

        wp_cache_set( $cache_key, $nodes, 'zaplane', 60 );

        return $nodes ?: [];
    }

    /* =====================================================
     * RUNS
     * ===================================================== */

    /**
     * Create a workflow run after trigger resolved
     */
    public static function create_run( array $node, array $payload ): int {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'zaplane_runs',
            [
                'workflow_id'     => $node['workflow_id'],
                'trigger_node_id' => $node['id'],
                'trigger_data'    => wp_json_encode( $payload ),
                'status'          => 'running',
                // 'created_at'      => current_time( 'mysql' ),
            ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Entry point after trigger resolution
     */
    public static function handle_trigger_node( array $node, array $payload ): void {

        $run_id = self::create_run( $node, $payload );

        if ( ! $run_id ) {
            return;
        }

        // Enqueue first node execution
        self::schedule_node(
            $run_id,
            $node['id']
        );
    }

    /* =====================================================
     * EXECUTION
     * ===================================================== */

    /**
     * Schedule node execution using Action Scheduler
     */
    public static function schedule_node(
        int $run_id,
        int $node_id,
        int $delay = 0
    ): void {

        if ( ! function_exists( 'as_enqueue_async_action' ) ) {
            return;
        }

        as_enqueue_async_action(
            'zaplane_execute_node',
            [
                'run_id'  => $run_id,
                'node_id' => $node_id,
            ],
            'zaplane-workflows',
            time() + $delay
        );
    }

    /**
     * Execute a node (called by Action Scheduler)
     */
    public static function execute_node( int $run_id, int $node_id ): void {
        global $wpdb;

        // Load run
        $run = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zaplane_runs WHERE id = %d",
                $run_id
            ),
            ARRAY_A
        );

        if ( ! $run || (int) $run['attempts'] >= 3 ) {
            return;
        }

        // Load node
        $node = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zaplane_nodes WHERE id = %d",
                $node_id
            ),
            ARRAY_A
        );

        if ( ! $node ) {
            return;
        }

        // Resolve integration
        $integration = IntegrationLoader::get( $node['app'] );

        if ( ! $integration ) {
            return;
        }

        // Prepare input
        $input = json_decode( $run['trigger_data'], true ) ?: [];
        $input['_run_id'] = $run_id;

        try {

            // Execute integration node
            $result = $integration::execute_node( $node, $input );

            // Log execution
            do_action(
                'zaplane_node_executed',
                $run_id,
                $node_id,
                'success',
                $result
            );

            if ( empty( $result['port'] ) ) {
                return;
            }

            // Schedule next nodes
            self::schedule_next_nodes(
                $run_id,
                $node_id,
                $result['port'],
                $result['data'] ?? []
            );

        } catch ( \Throwable $e ) {

            // Update run error
            $wpdb->update(
                $wpdb->prefix . 'zaplane_runs',
                [
                    'attempts'  => (int) $run['attempts'] + 1,
                    'last_error'=> $e->getMessage(),
                    'status'    => 'failed',
                ],
                [ 'id' => $run_id ]
            );

            // Retry after 60s
            self::schedule_node( $run_id, $node_id, 60 );

            do_action(
                'zaplane_node_executed',
                $run_id,
                $node_id,
                'failed',
                [ 'error' => $e->getMessage() ]
            );
        }
    }



    /**
     * Schedule next connected nodes
     */
    protected static function schedule_next_nodes(
        int $run_id,
        int $node_id,
        string $port,
        array $data
    ): void {
        global $wpdb;

        $connections = $wpdb->get_results(
            $wpdb->prepare("
                SELECT to_node_id, delay
                FROM {$wpdb->prefix}zaplane_connections
                WHERE from_node_id = %d
                  AND from_port = %s
            ", $node_id, $port ),
            ARRAY_A
        );

        foreach ( $connections as $conn ) {

            self::schedule_node(
                $run_id,
                (int) $conn['to_node_id'],
                (int) $conn['delay']
            );
        }
    }

    /* =====================================================
     * CACHE HELPERS
     * ===================================================== */

    public static function flush_trigger_cache(): void {
        wp_cache_delete( 'zaplane_active_trigger_events', 'zaplane' );
    }
}
