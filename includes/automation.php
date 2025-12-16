<?php
namespace Zaplane;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Automation { 
    public static function init(){
        $self = new self();
        add_action('init', [$self, 'dispatch_active_triggers']);
    }
    public function dispatch_active_triggers(){
        $nodes = Query::get_active_trigger_nodes();
        foreach ( $nodes as $node ) {
            add_action( $node->event, function () use ( $node ) {
                $this->automation_trigger_router( $node->app, func_get_args() );
            }, 10, 99 );
        }
    }
    public function automation_trigger_router($args) {
        $event = current_filter();
        $workflows = Query::get_active_workflows_for_event($event);
        error_log(print_r( $workflows, true));
        if (!$workflows) {
            return;
        }

        foreach($workflows as $wf) {
            Query::handle_trigger_node($wf, $args);
        }
    }
}