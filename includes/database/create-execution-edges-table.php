<?php
namespace Zaplane\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateExecutionEdgesTable {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . ZAPLANE_PLUGIN_SLUG . '_execution_edges';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            run_id BIGINT UNSIGNED NOT NULL,
            from_node_run_id BIGINT UNSIGNED NOT NULL,
            to_node_key VARCHAR(64) NOT NULL,
            payload_json LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY run_id (run_id),
            KEY from_node_run_id (from_node_run_id),
            KEY to_node_key (to_node_key)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
