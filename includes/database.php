<?php
namespace Zaplane;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Database {

	public static function init() {
		$self = new self();
		
	}

	public static function create_initial_custom_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$prefix          = $wpdb->prefix;
		$charset_collate = $wpdb->get_charset_collate();
        /**
         * Workflow
         * ├── Nodes (Trigger, Action, Logic)
         * ├── Ports (inputs / outputs with labels)
         * └── Edges (connections between ports)
         * 
         * Design Layers
         * Workflow - workflows
         * Graph - nodes, node_ports, edges
         * Credentials - connections
         * Execution - runs, node_runs, queue
         * Logs - node_logs
         * 
         * Final Results
         * Trigger
         *   └─(main)─▶ IF
         *      ├─(true)─▶ Slack
         *      └─(false)─▶ Email
         */
		Database\CreateWorkflowsTable::up( $prefix, $charset_collate );
		Database\CreateNodesTable::up( $prefix, $charset_collate );
        /**
         * Examples:
         * IF node → outputs: true, false
         * Trigger → output: main
         * Merge → inputs: input1, input2
         */
		Database\CreateNodePortsTable::up( $prefix, $charset_collate );
        /**
         * One-to-many
         * Many-to-one
         * Conditional paths
         * Loops (if you allow them)
         */
		Database\CreateWorkflowConnectionsTable::up( $prefix, $charset_collate );
		Database\CreateConnectionsTable::up( $prefix, $charset_collate );
		Database\CreateRunsTable::up( $prefix, $charset_collate );
		Database\CreateRunLogsTable::up( $prefix, $charset_collate );
		Database\CreateNodeRunsTable::up( $prefix, $charset_collate );
		Database\CreateNodeLogsTable::up( $prefix, $charset_collate );
		Database\CreateQueueTable::up( $prefix, $charset_collate );
	}
}
