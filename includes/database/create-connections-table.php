<?php
namespace Zaplane\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CreateConnectionsTable {
    public static function up( $prefix, $charset_collate ) {
        $table_name = $prefix . ZAPLANE_PLUGIN_SLUG . '_connections';
        $sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            app VARCHAR(100) NOT NULL,
            name VARCHAR(255) NOT NULL,
            auth_type VARCHAR(20) NOT NULL,
            encrypted_credentials LONGTEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            oauth_expires_at DATETIME DEFAULT NULL,
            last_used_at DATETIME DEFAULT NULL,
            last_tested_at DATETIME DEFAULT NULL,
            last_test_status VARCHAR(20) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY app (app),
            KEY status (status),
            KEY user_app (user_id, app)
        ) $charset_collate;";
        dbDelta( $sql );
    }
}