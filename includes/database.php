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
        $prefix = $wpdb->prefix;
        $charset_collate = $wpdb->get_charset_collate();

        /**
        *   workflow_version
        *   ↓
        *   run
        *   ↓
        *   node_runs   ← execution state
        *   ↓
        *   execution_edges   ← data flow
        *   ↓
        *   queue      ← scheduler
        *   ↓
        *   node_logs  ← debug
         */

        Database\CreateWorkflowsTable::up($prefix, $charset_collate);
        Database\CreateWorkflowVersionsTable::up($prefix, $charset_collate);

        Database\CreateRunsTable::up($prefix, $charset_collate);
        Database\CreateNodeRunsTable::up($prefix, $charset_collate);
        Database\CreateExecutionEdgesTable::up($prefix, $charset_collate);
        Database\CreateQueueTable::up($prefix, $charset_collate);
        Database\CreateNodeLogsTable::up($prefix, $charset_collate);

        Database\CreateConnectionsTable::up($prefix, $charset_collate);
    }

}
