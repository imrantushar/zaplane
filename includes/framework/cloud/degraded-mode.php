<?php

namespace Zaplane\Framework\Cloud;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cloud-outage fallback. Tracks the freshness of the most recent
 * round-trip with the cloud (heartbeat success or event POST 2xx). If
 * we go DEGRADED_AFTER_SECONDS without a successful cloud round-trip,
 * the plugin falls back to running workflows locally with the
 * last-known-good cached graph — so customers don't see automations
 * silently stop during a cloud incident.
 *
 * Events that fired during the outage are buffered in `wp_options` and
 * replayed back to the cloud once heartbeats succeed again.
 *
 * The fallback runner uses the local Automation engine that already
 * exists for unpaired sites — there's no second engine to maintain.
 */
class DegradedMode {

	public const HEALTH_OPTION   = 'zaplane_cloud_health';
	public const SNAPSHOT_OPTION = 'zaplane_cloud_workflow_snapshot';
	public const BUFFER_OPTION   = 'zaplane_cloud_outage_buffer';

	/** Cloud is "down" once we go this long without a 2xx round-trip. */
	public const DEGRADED_AFTER_SECONDS = 600; // 10 min

	/** Cap how many events we buffer during an outage — drop oldest beyond this. */
	public const MAX_BUFFER = 500;

	public static function bootstrap(): void {
		// Replay buffered events whenever a heartbeat succeeds.
		add_action( 'zaplane/cloud_round_trip_ok', [ self::class, 'on_round_trip_ok' ] );
		// Mark unhealthy whenever any cloud call fails.
		add_action( 'zaplane/cloud_round_trip_failed', [ self::class, 'on_round_trip_failed' ], 10, 1 );
	}

	public static function record_success(): void {
		update_option( self::HEALTH_OPTION, [
			'last_ok_at'   => time(),
			'last_fail_at' => null,
			'fail_count'   => 0,
		], false );
		do_action( 'zaplane/cloud_round_trip_ok' );
	}

	public static function record_failure( string $reason = '' ): void {
		$prev = is_array( get_option( self::HEALTH_OPTION ) ) ? get_option( self::HEALTH_OPTION ) : [];
		update_option( self::HEALTH_OPTION, [
			'last_ok_at'   => $prev['last_ok_at'] ?? null,
			'last_fail_at' => time(),
			'fail_count'   => (int) ( $prev['fail_count'] ?? 0 ) + 1,
			'last_reason'  => $reason,
		], false );
		do_action( 'zaplane/cloud_round_trip_failed', $reason );
	}

	/**
	 * Returns true when we should bypass the cloud forwarder and run
	 * workflows locally because the cloud has been silent too long.
	 */
	public static function is_degraded(): bool {
		$h = get_option( self::HEALTH_OPTION );
		if ( ! is_array( $h ) || empty( $h['last_ok_at'] ) ) {
			// Never seen the cloud at all — don't degrade until we've
			// proven it works at least once. Pairing handshake counts
			// as a successful round-trip.
			return false;
		}
		return ( time() - (int) $h['last_ok_at'] ) > self::DEGRADED_AFTER_SECONDS;
	}

	/**
	 * Cache the workflow graphs the cloud sent us during the last sync.
	 * Used as the source of truth when running locally during an outage.
	 */
	public static function snapshot_workflows( array $workflows ): void {
		update_option( self::SNAPSHOT_OPTION, [
			'workflows' => $workflows,
			'cached_at' => time(),
		], false );
	}

	public static function get_snapshot(): array {
		$row = get_option( self::SNAPSHOT_OPTION );
		return is_array( $row ) ? $row : [ 'workflows' => [], 'cached_at' => null ];
	}

	/** Buffer an event we couldn't deliver during the outage. */
	public static function buffer_event( array $envelope ): void {
		$buffer = (array) get_option( self::BUFFER_OPTION, [] );
		$buffer[] = [ 'at' => time(), 'envelope' => $envelope ];
		// Keep only the most recent MAX_BUFFER entries.
		if ( count( $buffer ) > self::MAX_BUFFER ) {
			$buffer = array_slice( $buffer, -self::MAX_BUFFER );
		}
		update_option( self::BUFFER_OPTION, $buffer, false );
	}

	/** Drain the buffer back to the cloud after recovery. */
	public static function on_round_trip_ok(): void {
		$buffer = (array) get_option( self::BUFFER_OPTION, [] );
		if ( empty( $buffer ) ) {
			return;
		}
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}
		foreach ( $buffer as $entry ) {
			$envelope = $entry['envelope'] ?? null;
			if ( ! is_array( $envelope ) ) continue;
			as_enqueue_async_action( EventForwarder::HOOK_FORWARD, [ 'envelope' => $envelope ], EventForwarder::GROUP );
		}
		delete_option( self::BUFFER_OPTION );
	}

	public static function on_round_trip_failed( string $reason = '' ): void {
		// Hook for future telemetry. The state is already recorded by
		// record_failure(); this is just a notification firing point.
	}

	/**
	 * Public summary for the admin UI banner.
	 *
	 *   [
	 *     'degraded'   => bool,
	 *     'last_ok_at' => timestamp|null,
	 *     'fail_count' => int,
	 *     'buffered'   => int,
	 *     'snapshot_age_seconds' => int|null,
	 *   ]
	 */
	public static function status(): array {
		$h        = (array) get_option( self::HEALTH_OPTION, [] );
		$buffer   = (array) get_option( self::BUFFER_OPTION, [] );
		$snap     = self::get_snapshot();
		$snap_age = $snap['cached_at'] ? ( time() - (int) $snap['cached_at'] ) : null;

		return [
			'degraded'             => self::is_degraded(),
			'last_ok_at'           => $h['last_ok_at']   ?? null,
			'last_fail_at'         => $h['last_fail_at'] ?? null,
			'fail_count'           => (int) ( $h['fail_count'] ?? 0 ),
			'buffered'             => count( $buffer ),
			'snapshot_age_seconds' => $snap_age,
			'snapshot_workflow_count' => count( $snap['workflows'] ?? [] ),
		];
	}
}
