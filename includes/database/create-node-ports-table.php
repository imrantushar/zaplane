<?php
namespace Zaplane\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateNodePortsTable {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . ZAPLANE_PLUGIN_SLUG . '_node_ports';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            node_id BIGINT UNSIGNED NOT NULL,
            port_type ENUM('input','output') NOT NULL,
            label VARCHAR(100) NOT NULL,
            data_type VARCHAR(50) DEFAULT 'any',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            UNIQUE KEY unique_port (node_id, port_type, label),
            INDEX (node_id),
            INDEX (port_type)
        ) $charset_collate;";
		dbDelta( $sql );
	}
}
