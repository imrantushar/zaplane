<?php
namespace Zaplane\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateRunsTable {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . ZAPLANE_PLUGIN_SLUG . '_runs';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            workflow_id BIGINT UNSIGNED NOT NULL,
            trigger_node_id BIGINT UNSIGNED NOT NULL,
            trigger_data JSON,
            status ENUM('pending','running','success','failed') DEFAULT 'pending',
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,

            INDEX (workflow_id),
            INDEX (status)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
