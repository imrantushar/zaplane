<?php
namespace Zaplane\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateQueueTable {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . ZAPLANE_PLUGIN_SLUG . '_queue';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            run_id BIGINT UNSIGNED NOT NULL,
            node_id BIGINT UNSIGNED NULL,
            available_at DATETIME NOT NULL,
            locked_at DATETIME NULL,
            attempts INT DEFAULT 0,

            INDEX (available_at),
            INDEX (locked_at)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
