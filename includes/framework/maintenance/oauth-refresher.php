<?php

namespace Zaplane\Framework\Maintenance;

use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Framework\Classes\IntegrationLoader;
use Zaplane\Models\Connection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Proactive OAuth-token refresher for standalone (unpaired) plugin mode.
 *
 * The cloud has its own refresher; the plugin does lazy refresh whenever
 * a workflow asks for a connection's credentials. That's fine for active
 * connections but a problem for cron-only workflows whose triggers
 * usually run minutes after the lazy refresh would otherwise fire —
 * the very first action then has to refresh + retry, doubling latency
 * and sometimes hitting rate limits.
 *
 * This walker refreshes any connection whose token expires inside
 * `REFRESH_LEAD_TIME` so the next workflow run finds it warm.
 */
class OAuthRefresher {

	public const HOOK = 'zaplane_oauth_refresh_tick';
	public const GROUP = 'zaplane';

	/** Refresh tokens that expire within this window. */
	public const REFRESH_LEAD_TIME = HOUR_IN_SECONDS;

	public static function bootstrap(): void {
		add_action( self::HOOK, [ self::class, 'tick' ] );
		add_action( 'init', [ self::class, 'sync_schedule' ] );
	}

	public static function sync_schedule(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}
		// Run twice an hour. Cheap when there are no expiring tokens.
		if ( ! as_has_scheduled_action( self::HOOK, [], self::GROUP ) ) {
			as_schedule_recurring_action( time() + 60, 30 * MINUTE_IN_SECONDS, self::HOOK, [], self::GROUP );
		}
	}

	/**
	 * Find every active connection with a refresh_token that expires in
	 * the next REFRESH_LEAD_TIME and refresh it. Failures are logged
	 * (the connection's `last_test_result` already surfaces them in the
	 * admin UI) and don't break the loop.
	 */
	public static function tick(): void {
		try {
			$candidates = Connection::query()
				->where( 'status', 'active' )
				->whereNotNull( 'oauth_expires_at' )
				->where( 'oauth_expires_at', '<=', date( 'Y-m-d H:i:s', time() + self::REFRESH_LEAD_TIME ) )
				->limit( 50 )
				->get();
		} catch ( \Throwable $e ) {
			error_log( '[zaplane] oauth refresher query failed: ' . $e->getMessage() );
			return;
		}

		if ( empty( $candidates ) ) {
			return;
		}

		$loader = new IntegrationLoader();
		$cm     = new ConnectionManager( $loader );

		$refreshed = 0;
		foreach ( $candidates as $conn ) {
			try {
				// `getDecryptedCredentials()` triggers ConnectionManager's
				// internal refresh_oauth_if_needed() — same code path as a
				// runtime credential fetch, so behavior matches what
				// happens during a workflow run.
				$cm->getDecryptedCredentials( $conn );
				$refreshed++;
			} catch ( \Throwable $e ) {
				error_log( sprintf(
					'[zaplane] oauth refresh failed: connection_id=%d app=%s error=%s',
					$conn->id, $conn->app, $e->getMessage()
				) );
			}
		}

		if ( $refreshed > 0 ) {
			error_log( sprintf( '[zaplane] oauth refresh: refreshed %d connection(s).', $refreshed ) );
		}
	}
}
