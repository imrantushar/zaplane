<?php

if (!defined('WP_CLI')) {
    return;
}

class Zaplane_CLI_Command {

    public function demo() {
        global $wpdb;

        WP_CLI::log('🚀 Creating demo workflow...');

        // 1. Create Workflow
        $wpdb->insert($wpdb->prefix . ZAPLANE_PLUGIN_SLUG . '_workflows', [
            'user_id' => 1,
            'name'    => 'Demo: Post Publish → MailerLite',
            'status'  => 'active',
        ]);
        $workflow_id = $wpdb->insert_id;

        WP_CLI::success("Workflow created (ID: $workflow_id)");

        // 2. Nodes
        $nodes = [];
        $nodes['trigger']     = $this->insert_node($workflow_id, 'trigger', 'wordpress', 'Post Published', 'publish_post');
        $nodes['mailerlite']  = $this->insert_node($workflow_id, 'action', 'mailerlite', 'Add Subscriber to MailerLite');

        // 3. Ports
        $ports = [];
        $ports['trigger_out']    = $this->insert_port($nodes['trigger'], 'output', 'main');
        $ports['mailerlite_in']  = $this->insert_port($nodes['mailerlite'], 'input', 'main');

        // 4. Connect Trigger → MailerLite
        $this->insert_edge($workflow_id, $ports['trigger_out'], $ports['mailerlite_in']);

        WP_CLI::success('✅ Demo workflow with MailerLite inserted successfully!');
    }

    // ------------------------
    // Helper Methods
    // ------------------------

    private function insert_node($workflow_id, $type, $app, $name, $event = '') {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . ZAPLANE_PLUGIN_SLUG . '_nodes', [
            'workflow_id' => $workflow_id,
            'node_type'   => $type,
            'app'         => $app,
            'name'        => $name,
            'event'       => $event,
            'config'      => json_encode(['post_type' => 'post'])
        ]);

        WP_CLI::log("Node created: $name");
        return $wpdb->insert_id;
    }

    private function insert_port($node_id, $type, $label) {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . ZAPLANE_PLUGIN_SLUG . '_node_ports', [
            'node_id'   => $node_id,
            'port_type' => $type,
            'label'     => $label
        ]);

        WP_CLI::log("Port created: $type::$label");
        return $wpdb->insert_id;
    }

    private function insert_edge($workflow_id, $from_port, $to_port) {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . ZAPLANE_PLUGIN_SLUG . '_edges', [
            'workflow_id'  => $workflow_id,
            'from_port_id' => $from_port,
            'to_port_id'   => $to_port
        ]);

        WP_CLI::log("Edge created: $from_port → $to_port");
    }
}

// Register command
WP_CLI::add_command('zaplane', 'Zaplane_CLI_Command');
