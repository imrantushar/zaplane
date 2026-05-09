<?php

namespace Zaplane\Framework\Cloud;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Periodic heartbeat from the plugin to its paired Zaplane Cloud workspace.
 *
 * Runs as a recurring Action Scheduler action in the `zaplane` group, so
 * the existing worker daemon drains it sub-second. With no daemon, it
 * still fires via wp-cron at wp-cron's pace.
 *
 * Lifecycle:
 *   - On pair: Pairing::pair() calls Heartbeat::maybe_schedule()
 *   - On disconnect: Pairing::disconnect() calls Heartbeat::unschedule()
 *   - On boot: bootstrap() registers the action handler and (defensively)
 *     re-schedules if we're paired but the AS row was lost.
 */
class Heartbeat {

	public const HOOK     = 'zaplane_cloud_heartbeat';
	public const GROUP    = 'zaplane';
	public const INTERVAL = 300; // 5 minutes

	public static function bootstrap(): void {
		add_action( self::HOOK, [ self::class, 'run' ] );
		add_action( 'init', [ self::class, 'sync_schedule' ], 25 );
	}

	/**
	 * Reconciles AS state with Bridge state. Paired but not scheduled →
	 * schedule. Unpaired but still scheduled → unschedule. Idempotent.
	 */
	public static function sync_schedule(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		$has = as_has_scheduled_action( self::HOOK, [], self::GROUP );

		if ( Bridge::is_paired() && ! $has ) {
			self::schedule();
		} elseif ( ! Bridge::is_paired() && $has ) {
			self::unschedule();
		}
	}

	public static function schedule(): void {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}
		if ( as_has_scheduled_action( self::HOOK, [], self::GROUP ) ) {
			return;
		}
		as_schedule_recurring_action(
			time() + 30,
			self::INTERVAL,
			self::HOOK,
			[],
			self::GROUP
		);
	}

	public static function unschedule(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, [], self::GROUP );
		}
	}

	public static function run(): void {
		if ( ! Bridge::is_paired() ) {
			return;
		}

		$result = Pairing::heartbeat();

		// Track outcome for the admin UI. Two consecutive failures over
		// the last 30 minutes will eventually surface in a "Connection
		// degraded" warning (UI work for a future iteration).
		update_option( 'zaplane_cloud_heartbeat_last', [
			'ok'         => (bool) $result['ok'],
			'error'      => $result['error'] ?? '',
			'finished_at' => time(),
		], false );
	}

	public static function get_last_run(): array {
		$last = get_option( 'zaplane_cloud_heartbeat_last', [] );
		return is_array( $last ) ? $last : [];
	}
}
