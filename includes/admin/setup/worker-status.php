<?php

namespace Zaplane\Admin\Setup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the heartbeat written by the WorkCommand daemon and exposes a
 * normalized payload the admin UI can render. A heartbeat older than
 * `STALE_AFTER_SECONDS` is treated as the worker being dead — even if
 * the option still says "running" — because the daemon updates this
 * row at least every second while alive.
 */
class WorkerStatus {

	public const HEARTBEAT_OPTION    = 'zaplane_worker_status';
	public const STALE_AFTER_SECONDS = 30;

	public static function get(): array {
		$raw = get_option( self::HEARTBEAT_OPTION, [] );

		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return self::empty_state( 'never_started' );
		}

		$updated_at = (int) ( $raw['updated_at'] ?? 0 );
		$age        = time() - $updated_at;
		$reported   = (string) ( $raw['state'] ?? 'unknown' );

		if ( in_array( $reported, [ 'starting', 'running' ], true ) && $age > self::STALE_AFTER_SECONDS ) {
			$state  = 'stale';
			$health = 'down';
		} elseif ( 'stopped' === $reported ) {
			$state  = 'stopped';
			$health = 'down';
		} elseif ( 'starting' === $reported ) {
			$state  = 'starting';
			$health = 'warming';
		} elseif ( 'running' === $reported ) {
			$state  = 'running';
			$health = 'up';
		} else {
			$state  = $reported;
			$health = 'unknown';
		}

		return [
			'health'         => $health,
			'state'          => $state,
			'reported_state' => $reported,
			'age_seconds'    => $age,
			'pid'            => (int) ( $raw['pid'] ?? 0 ),
			'host'           => (string) ( $raw['host'] ?? '' ),
			'started_at'     => (int) ( $raw['started_at'] ?? 0 ),
			'updated_at'     => $updated_at,
			'jobs_processed' => (int) ( $raw['jobs_processed'] ?? 0 ),
			'jobs_failed'    => (int) ( $raw['jobs_failed'] ?? 0 ),
			'memory_mb'      => (int) ( $raw['memory_mb'] ?? 0 ),
			'memory_peak_mb' => (int) ( $raw['memory_peak_mb'] ?? 0 ),
			'avg_runtime_ms' => (int) ( $raw['avg_runtime_ms'] ?? 0 ),
			'queue_depth'    => (int) ( $raw['queue_depth'] ?? -1 ),
			'group'          => (string) ( $raw['group'] ?? 'zaplane' ),
			'stop_reason'    => (string) ( $raw['stop_reason'] ?? '' ),
			'plugin_version' => (string) ( $raw['plugin_version'] ?? '' ),
		];
	}

	public static function jobs_per_minute(): float {
		$status = self::get();
		if ( 0 === $status['started_at'] || 0 === $status['jobs_processed'] ) {
			return 0.0;
		}
		$uptime = max( 1, time() - $status['started_at'] );
		return round( ( $status['jobs_processed'] / $uptime ) * 60, 2 );
	}

	private static function empty_state( string $reason ): array {
		return [
			'health'         => 'down',
			'state'          => $reason,
			'reported_state' => $reason,
			'age_seconds'    => 0,
			'pid'            => 0,
			'host'           => '',
			'started_at'     => 0,
			'updated_at'     => 0,
			'jobs_processed' => 0,
			'jobs_failed'    => 0,
			'memory_mb'      => 0,
			'memory_peak_mb' => 0,
			'avg_runtime_ms' => 0,
			'queue_depth'    => -1,
			'group'          => 'zaplane',
			'stop_reason'    => '',
			'plugin_version' => '',
		];
	}
}
