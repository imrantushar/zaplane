<?php
namespace Zaplane\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateWorkflowVersionsTable {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . ZAPLANE_PLUGIN_SLUG . '_workflow_versions';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workflow_id BIGINT UNSIGNED NOT NULL,
            graph_json LONGTEXT NOT NULL,
            graph_hash CHAR(64) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY workflow_id (workflow_id),
            KEY graph_hash (graph_hash)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
