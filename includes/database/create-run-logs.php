<?php
namespace Zaplane\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateRunLogsTable {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . ZAPLANE_PLUGIN_SLUG . '_run_logs';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            run_id BIGINT,
            node_id BIGINT,
            status VARCHAR(20),
            payload JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
