<?php
namespace Zaplane\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateNodeRunsTable {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . ZAPLANE_PLUGIN_SLUG . '_node_runs';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            run_id BIGINT UNSIGNED NOT NULL,
            node_id BIGINT UNSIGNED NOT NULL,
            status ENUM('pending','running','success','failed') DEFAULT 'pending',
            input_data JSON,
            output_data JSON,
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,

            UNIQUE KEY unique_node_run (run_id, node_id),
            INDEX (run_id),
            INDEX (node_id)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
