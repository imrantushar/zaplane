<?php
namespace Zaplane\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateNodeLogsTable {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . ZAPLANE_PLUGIN_SLUG . '_node_logs';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            node_run_id BIGINT UNSIGNED NOT NULL,
            level ENUM('info','warning','error') DEFAULT 'info',
            message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            INDEX (node_run_id)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
