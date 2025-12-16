<?php
namespace Zaplane\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateNodesTable {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . ZAPLANE_PLUGIN_SLUG . '_nodes';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workflow_id BIGINT UNSIGNED NOT NULL,
            connection_id BIGINT UNSIGNED NULL,
            node_type ENUM('trigger','action','logic') NOT NULL,
            app VARCHAR(100) NOT NULL,     -- slack, webhook, if, merge
            name VARCHAR(255) NOT NULL,
            event VARCHAR(255) NOT NULL,
            config JSON NULL,
            position_x INT DEFAULT 0,
            position_y INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            INDEX (workflow_id),
            INDEX (connection_id),
            INDEX (node_type),
            INDEX (app)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
