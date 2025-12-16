<?php
namespace Zaplane;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Query { 
    public static function get_active_trigger_nodes(){
        global $wpdb;
        $nodes = $wpdb->get_results("
            SELECT n.*
            FROM {$wpdb->prefix}zaplane_nodes n
            JOIN {$wpdb->prefix}zaplane_workflows w ON n.workflow_id = w.id
            WHERE n.node_type = 'trigger'
            AND w.status = 'active'
        ");
        return $nodes;
    }

    public static function get_active_workflows_for_event($event) {
        global $wpdb;
        // Cache key per event to reduce DB load
        $cache_key = 'automation_active_' . $event;
        $workflows = wp_cache_get($cache_key, 'automation');

        if ($workflows !== false) {
            return $workflows;
        }

        // Only get workflows that are active and whose trigger node listens to this event
        $workflows = $wpdb->get_results(
            $wpdb->prepare("
                SELECT n.*, w.id as workflow_id, w.user_id
                FROM {$wpdb->prefix}zaplane_nodes n
                JOIN {$wpdb->prefix}zaplane_workflows w ON w.id = n.workflow_id
                WHERE n.node_type = 'trigger'
                AND n.event = %s
                AND w.status = 'active'
            ", $event),
            ARRAY_A
        );

        // Cache results for 60 seconds
        wp_cache_set($cache_key, $workflows, 'automation', 60);

        return $workflows;
    }

    public static function handle_trigger_node($node, $hook_args) {
        global $wpdb;

        // Resolve event payload using the node's app resolver
        $resolver_func = self::get_trigger_resolver($node['app']);

        if (!$resolver_func || !function_exists($resolver_func)) {
            return; // No resolver available for this integration
        }

        $payload = call_user_func($resolver_func, $node, $hook_args);

        if (!$payload) {
            return; // Filters not matched or payload invalid
        }

        // Create a workflow run
        $wpdb->insert($wpdb->prefix . 'zaplane_runs', [
            'workflow_id'     => $node['workflow_id'],
            'trigger_node_id' => $node['id'],
            'trigger_data'    => wp_json_encode($payload),
            'status'          => 'running',
            'created_at'      => current_time('mysql'),
        ]);

        $run_id = $wpdb->insert_id;

        // Enqueue the first node for execution via Action Scheduler
        self::automation_schedule_node($run_id, $node['id']);
    }

    public static function get_trigger_resolver($app) {
        $resolvers = [
            'wordpress'      => 'automation_resolve_post_published',
            'woocommerce'    => 'automation_resolve_wc_order',
            'custom_webhook' => 'automation_resolve_webhook',
            // Add your 200+ integrations here
        ];

        return $resolvers[$app] ?? null;
    }

    public static function automation_schedule_node($run_id, $node_id, $delay = 0) {
        if (!function_exists('as_enqueue_async_action')) return;

        as_enqueue_async_action(
            'automation_execute_node',
            [
                'run_id'  => $run_id,
                'node_id' => $node_id,
            ],
            'zaplane-workflows',
            time() + $delay
        );
    }



} 