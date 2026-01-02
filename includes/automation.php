<?php
namespace Zaplane;

use Zaplane\Classes\AutomationBase;
use Zaplane\Classes\IntegrationLoader;
use Zaplane\Classes\Logger;

if (!defined('ABSPATH')) exit;

class Automation extends AutomationBase {

    protected static ?self $instance = null;
    protected array $registered_hooks = [];

    public static function instance(): self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function init(): void {
        $self = self::instance();

        // Load integrations dynamically
        IntegrationLoader::load();

        add_action('init', [$self, 'dispatch_active_triggers']);
        add_action('zaplane_resume_runs', [$self, 'resume_paused_runs']);
        add_action('zaplane_node_executed', [$self, 'log_node_execution'], 10, 4);
        add_action('zaplane_workflow_updated', [$self, 'reload_triggers']);

        // Action Scheduler hook
        add_action('zaplane_execute_node', [$self, 'dispatch_execute_node'], 10, 2);
    }


    public function dispatch_execute_node($run_id, $node_id) {
        if (empty($run_id) || empty($node_id)) return;

        global $wpdb;

        // Fetch the correct node_run from queue
        $node_run = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}zaplane_node_runs 
                WHERE run_id=%d AND node_key=%s AND status='pending' 
                ORDER BY id ASC LIMIT 1",
                $run_id,
                $node_id
            ),
            ARRAY_A
        );

        if (!$node_run) return;

        // Call base class method to execute it
        $this->execute_node_run($node_run);
        // After processing this node, check if run is complete
        $this->finalize_run((int)$node_run['run_id']);
    }

    public function reload_triggers(): void {
        // $this->flush_trigger_cache();
        $this->deregister_hooks();
        $this->dispatch_active_triggers();
    }

    public function dispatch_active_triggers(): void {
        $events = $this->get_active_trigger_events();
        if (empty($events)) return;
        foreach ($events as $event) {
            if (isset($this->registered_hooks[$event])) continue;
            $callback = [$this, 'automation_trigger_router'];
            add_action($event, $callback, 10, 99);
            $this->registered_hooks[$event] = $callback;
        }
    }

    protected function deregister_hooks(): void {
        foreach ($this->registered_hooks as $event => $callback) {
            remove_action($event, $callback, 10);
        }
        $this->registered_hooks = [];
    }

    public function automation_trigger_router(): void {
        $event = current_filter();
        $args  = func_get_args();
        
        // Developer only
        Logger::reset();
        Logger::log("Trigger fired: automation_trigger_router", [
            'event' => current_filter(),
            'args' => $args
        ]);
        // End Developer only

        $trigger_nodes = $this->get_active_workflows_for_event($event);
        if (empty($trigger_nodes)) return;
        foreach ($trigger_nodes as $node) {
            $integration = IntegrationLoader::get(strtolower($node['app']) ?? '');
            if (!$integration) continue;
            $payload = $integration::resolve_trigger((array)$node['graph_node']['data'], $args);
            if (!$payload) continue;

            $this->handle_trigger_node($node, $payload);

        }
    }

    
}
