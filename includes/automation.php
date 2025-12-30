<?php
namespace Zaplane;

use Zaplane\Classes\IntegrationLoader;

if (!defined('ABSPATH')) exit;

class Automation {

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
        add_action('zaplane_execute_node', function ($run_id, $node_id) {
            error_log(print_r('zaplane_execute_node', true));
            if (empty($run_id) || empty($node_id)) return;
            Query::execute_node((int)$run_id, $node_id);
        }, 10, 2);
    }

    public function reload_triggers(): void {
        Query::flush_trigger_cache();
        $this->deregister_hooks();
        $this->dispatch_active_triggers();
    }

    public function dispatch_active_triggers(): void {
        $events = Query::get_active_trigger_events();
        if (empty($events)) return;

        error_log(print_r($events, true));
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

        $trigger_nodes = Query::get_active_workflows_for_event($event);
        if (empty($trigger_nodes)) return;
        foreach ($trigger_nodes as $node) {
            $integration = IntegrationLoader::get(strtolower($node['app']) ?? '');
            if (!$integration) continue;
            $payload = $integration::resolve_trigger((array)$node['graph_node']['data'], $args);
            if (!$payload) continue;

            Query::handle_trigger_node($node, $payload);
        }
    }

    public function resume_paused_runs(): void {
        global $wpdb;
        $runs = $wpdb->get_results("
            SELECT id, current_node_id
            FROM {$wpdb->prefix}zaplane_runs
            WHERE status = 'paused'
              AND resume_at <= NOW()
        ");
        foreach ($runs as $run) {
            if (!empty($run->current_node_id)) {
                Query::schedule_node($run->id, $run->current_node_id);
            }
        }
    }

    public function log_node_execution($run_id, $node_id, $status, $result): void {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'zaplane_run_logs',
            [
                'run_id'  => $run_id,
                'node_id' => $node_id,
                'status'  => $status,
                'payload' => wp_json_encode($result),
                'created_at' => current_time('mysql')
            ]
        );
    }
}
