<?php

namespace Zaplane\Framework\Logging\Handlers;

use Zaplane\Framework\Logging\LogEntry;
use Zaplane\Framework\Logging\LogLevel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DatabaseHandler extends AbstractHandler {

	protected string $table;
	protected int $retentionDays;

	public function __construct(
		string $minLevel = LogLevel::DEBUG,
		string $table = 'zaplane_logs',
		int $retentionDays = 30
	) {
		parent::__construct( $minLevel );
		$this->table = $table;
		$this->retentionDays = $retentionDays;
	}



	public function handle( LogEntry $entry ): bool {
		if ( ! $this->isHandling( $entry->getLevel() ) ) {
			return false;
		}

		global $wpdb;

		$tableName = $wpdb->prefix . $this->table;

		if ( ! $this->tableExists( $tableName ) ) {
			return false;
		}

		$result = $wpdb->insert(
			$tableName,
			[
				'level' => $entry->getLevel(),
				'channel' => $entry->getChannel(),
				'message' => $entry->getMessage(),
				'context' => json_encode( $entry->getContext() ),
				'extra' => json_encode( $entry->getExtra() ),
				'trace_id' => $entry->getTraceId(),
				'user_id' => $entry->getUserId(),
				'created_at' => $entry->getTimestamp(),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( mt_rand( 1, 100 ) === 1 ) {
			$this->cleanOldLogs();
		}

		return $result !== false;
	}



	protected function tableExists( string $tableName ): bool {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $tableName )
		);

		return $result === $tableName;
	}



	protected function cleanOldLogs(): void {
		global $wpdb;

		$tableName = $wpdb->prefix . $this->table;
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$this->retentionDays} days" ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$tableName} WHERE created_at < %s",
				$cutoff
			)
		);
	}



	public function getLogs( array $filters = [], int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$tableName = $wpdb->prefix . $this->table;

		if ( ! $this->tableExists( $tableName ) ) {
			return [];
		}

		$where = [ '1=1' ];
		$params = [];

		if ( ! empty( $filters['level'] ) ) {
			$where[] = 'level = %s';
			$params[] = $filters['level'];
		}

		if ( ! empty( $filters['channel'] ) ) {
			$where[] = 'channel = %s';
			$params[] = $filters['channel'];
		}

		if ( ! empty( $filters['trace_id'] ) ) {
			$where[] = 'trace_id = %s';
			$params[] = $filters['trace_id'];
		}

		if ( ! empty( $filters['user_id'] ) ) {
			$where[] = 'user_id = %s';
			$params[] = $filters['user_id'];
		}

		if ( ! empty( $filters['from'] ) ) {
			$where[] = 'created_at >= %s';
			$params[] = $filters['from'];
		}

		if ( ! empty( $filters['to'] ) ) {
			$where[] = 'created_at <= %s';
			$params[] = $filters['to'];
		}

		if ( ! empty( $filters['search'] ) ) {
			$where[] = 'message LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
		}

		$whereClause = implode( ' AND ', $where );
		$params[] = $limit;
		$params[] = $offset;

		$sql = "SELECT * FROM {$tableName} WHERE {$whereClause} ORDER BY created_at DESC LIMIT %d OFFSET %d";

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, ...$params );
		}

		return $wpdb->get_results( $sql, ARRAY_A ) ?: [];
	}



	public static function createTable(): void {
		global $wpdb;

		$tableName = $wpdb->prefix . 'zaplane_logs';
		$charsetCollate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			level VARCHAR(20) NOT NULL,
			channel VARCHAR(100) NOT NULL DEFAULT 'default',
			message TEXT NOT NULL,
			context LONGTEXT,
			extra LONGTEXT,
			trace_id VARCHAR(64) NULL,
			user_id VARCHAR(20) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY level_idx (level),
			KEY channel_idx (channel),
			KEY trace_id_idx (trace_id),
			KEY user_id_idx (user_id),
			KEY created_at_idx (created_at)
		) {$charsetCollate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
