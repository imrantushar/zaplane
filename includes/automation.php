<?php
namespace Zaplane;

use Zaplane\Classes\IntegrationLoader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Automation {

    protected static ?self $instance = null;

    public static function instance(): self {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function init() {
        $self = self::instance();

        IntegrationLoader::load();

        add_action( 'init', [ $self, 'dispatch_active_triggers' ] );
        add_action( 'zaplane_resume_runs', [ $self, 'resume_paused_runs' ] );
        add_action( 'zaplane_node_executed', [ $self, 'log_node_execution' ], 10, 4 );
        add_action( 'zaplane_workflow_updated', [ $self, 'reload_triggers' ] );
    }

    public function reload_triggers() {
        Query::flush_trigger_cache();
        $this->dispatch_active_triggers();
    }

    public function dispatch_active_triggers() {
        $events = Query::get_active_trigger_events();
        if ( empty( $events ) ) return;

        foreach ( $events as $event ) {
            add_action( $event, [ $this, 'automation_trigger_router' ], 10, 99 );
        }
    }

    public function automation_trigger_router() {
        $event = current_filter();
        $args  = func_get_args();
        $trigger_nodes = Query::get_active_workflows_for_event( $event );

        if ( empty( $trigger_nodes ) ) return;

        foreach ( $trigger_nodes as $node ) {
            $integration = IntegrationLoader::get( $node['app'] );
            if ( ! $integration ) continue;

            $payload = $integration::resolve_trigger($node, $args);
            if ( ! $payload ) continue;

            Query::handle_trigger_node( $node, $payload );
        }
    }

    public function resume_paused_runs() {
        global $wpdb;
        $runs = $wpdb->get_results("
            SELECT id, current_node_id
            FROM {$wpdb->prefix}zaplane_runs
            WHERE status = 'paused'
              AND resume_at <= NOW()
        ");
        foreach ( $runs as $run ) {
            if ( ! empty( $run->current_node_id ) ) {
                Query::schedule_node( $run->id, $run->current_node_id );
            }
        }
    }

    public function log_node_execution( $run_id, $node_id, $status, $result ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'zaplane_run_logs',
            [
                'run_id'  => $run_id,
                'node_id' => $node_id,
                'status'  => $status,
                'payload' => wp_json_encode( $result ),
            ]
        );
    }
}
