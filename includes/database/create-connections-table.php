<?php
namespace Zaplane\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateConnectionsTable {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . ZAPLANE_PLUGIN_SLUG . '_connections';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            app VARCHAR(100) NOT NULL,
            name VARCHAR(255) NOT NULL,
            auth_type ENUM('oauth','api_key','basic','token') NOT NULL,
            credentials JSON NOT NULL,  -- ENCRYPTED
            status ENUM('active','expired','revoked') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX (user_id),
            INDEX (app)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
