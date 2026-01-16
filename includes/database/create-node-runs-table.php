<?php
namespace Zaplane\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateNodeRunsTable {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . ZAPLANE_PLUGIN_SLUG . '_node_runs';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            run_id BIGINT UNSIGNED NOT NULL,
            node_key VARCHAR(64) NOT NULL,
            parent_node_run_id BIGINT UNSIGNED NULL,
            iteration INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'pending',
            input_json LONGTEXT,
            output_json LONGTEXT,
            attempts INT DEFAULT 0,
            max_attempts INT DEFAULT 3,
            resume_at DATETIME NULL,
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY run_id (run_id),
            KEY node_key (node_key),
            KEY parent_node_run_id (parent_node_run_id)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
