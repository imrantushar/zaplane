<?php
namespace Zaplane\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateQueueTable {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . ZAPLANE_PLUGIN_SLUG . '_queue';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            run_id BIGINT UNSIGNED NOT NULL,
            node_run_id BIGINT UNSIGNED NOT NULL,
            available_at DATETIME NOT NULL,
            locked_at DATETIME NULL,
            lock_token CHAR(36) NULL,
            locked_by VARCHAR(50) NULL,
            attempts INT DEFAULT 0,
            last_error TEXT,
            PRIMARY KEY (id),
            KEY available_at (available_at),
            KEY locked_at (locked_at),
            KEY run_id (run_id)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
