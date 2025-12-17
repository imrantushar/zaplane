<?php

if ( ! defined( 'WP_CLI' ) ) {
    return;
}

class Zaplane_CLI_Command {

    public function demo() {
        global $wpdb;

        WP_CLI::log('🚀 Creating demo workflow...');

        // 1. Create Workflow
        $wpdb->insert(
            $wpdb->prefix . ZAPLANE_PLUGIN_SLUG . '_workflows',
            [
                'user_id' => 1,
                'name'    => 'Demo: Post Publish → MailerLite',
                'status'  => 'active',
            ]
        );

        $workflow_id = $wpdb->insert_id;

        WP_CLI::success("Workflow created (ID: $workflow_id)");

        // 2. Create Nodes
        $trigger_node_id = $this->insert_node(
            $workflow_id,
            'trigger',
            'wordpress',
            'Post Published',
            'publish_post'
        );

        $mailerlite_node_id = $this->insert_node(
            $workflow_id,
            'action',
            'mailerlite',
            'Add Subscriber to MailerLite'
        );

        // 3. Create Workflow Connection (Trigger → MailerLite)
        $this->insert_connection(
            $workflow_id,
            $trigger_node_id,
            'main',
            $mailerlite_node_id,
            'main'
        );

        WP_CLI::success('✅ Demo workflow with MailerLite inserted successfully!');
    }

    // --------------------------------------------------
    // Helpers
    // --------------------------------------------------

    private function insert_node(
        int $workflow_id,
        string $type,
        string $app,
        string $name,
        string $event = ''
    ): int {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . ZAPLANE_PLUGIN_SLUG . '_nodes',
            [
                'workflow_id' => $workflow_id,
                'node_type'   => $type,
                'app'         => $app,
                'name'        => $name,
                'event'       => $event,
                'config'      => wp_json_encode( [ 'post_type' => 'post' ] ),
            ]
        );

        WP_CLI::log("Node created: {$name}");

        return (int) $wpdb->insert_id;
    }

    private function insert_connection(
        int $workflow_id,
        int $from_node_id,
        string $from_port,
        int $to_node_id,
        string $to_port,
        int $delay = 0
    ): void {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . ZAPLANE_PLUGIN_SLUG . '_workflow_connections',
            [
                'workflow_id' => $workflow_id,
                'from_node_id'=> $from_node_id,
                'from_port'   => $from_port,
                'to_node_id'  => $to_node_id,
                'to_port'     => $to_port,
                'delay'       => $delay,
            ]
        );

        WP_CLI::log(
            "Connection created: {$from_node_id}:{$from_port} → {$to_node_id}:{$to_port}"
        );
    }
}

// Register command
WP_CLI::add_command( 'zaplane', 'Zaplane_CLI_Command' );
