<?php
namespace Zaplane\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateRunsTable {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . ZAPLANE_PLUGIN_SLUG . '_runs';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workflow_version_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) DEFAULT 'running',
            trigger_data LONGTEXT,
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,
            last_error TEXT,
            PRIMARY KEY (id),
            KEY workflow_version_id (workflow_version_id),
            KEY status (status)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
