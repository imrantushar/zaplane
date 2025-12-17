<?php
namespace Zaplane\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateWorkflowConnectionsTable {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . ZAPLANE_PLUGIN_SLUG . '_workflow_connections';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			workflow_id BIGINT UNSIGNED NOT NULL,
			from_node_id BIGINT UNSIGNED NOT NULL,
			from_port VARCHAR(50) NOT NULL,
			to_node_id BIGINT UNSIGNED NOT NULL,
			to_port VARCHAR(50) NOT NULL,
			delay INT DEFAULT 0,
			connection_condition LONGTEXT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY workflow_id (workflow_id),
			KEY from_node_id (from_node_id),
			KEY to_node_id (to_node_id)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
