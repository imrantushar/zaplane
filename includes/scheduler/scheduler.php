<?php

namespace Zaplane\Scheduler;

use Zaplane\Framework\Classes\Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fires schedule-triggered workflows. A recurring Action Scheduler tick runs
 * every minute, finds active workflows whose trigger is the Schedule app, and
 * runs the ones that are due — tracking when each schedule trigger last ran.
 */
class Scheduler {

	private const HOOK      = 'zaplane/schedule/tick';
	private const TICK      = 'zaplane_scheduler_tick';
	private const LAST_OPT  = 'zaplane_sched_last_';

	public function boot(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		add_action( self::TICK, [ $this, 'run_due' ] );

		add_action( 'init', function () {
			if ( function_exists( 'as_has_scheduled_action' ) && function_exists( 'as_schedule_recurring_action' ) ) {
				if ( ! as_has_scheduled_action( self::TICK ) ) {
					as_schedule_recurring_action( time() + MINUTE_IN_SECONDS, MINUTE_IN_SECONDS, self::TICK, [], 'zaplane' );
				}
			}
		} );
	}

	/** Runs every minute: fire each due scheduled workflow. */
	public function run_due(): void {
		if ( ! function_exists( 'zaplane_run_workflow' ) ) {
			return;
		}

		foreach ( Query::get_active_workflows_for_event( self::HOOK ) as $trigger ) {
			$wf_id   = (int) ( $trigger['workflow_id'] ?? 0 );
			$node_id = (string) ( $trigger['id'] ?? '' );
			$config  = $trigger['graph_node']['data']['config'] ?? [];
			if ( ! $wf_id || '' === $node_id ) {
				continue;
			}

			if ( ! self::is_due( self::last_run( $wf_id, $node_id ), (array) $config ) ) {
				continue;
			}

			// Start from this schedule, not the workflow's default trigger: a
			// schedule can sit alongside other triggers in the same workflow.
			zaplane_run_workflow( $wf_id, [
				'timestamp' => current_time( 'mysql' ),
				'unix'      => time(),
			], $node_id );

			self::mark_run( $wf_id, $node_id, time() );
		}
	}

	/**
	 * When a schedule trigger last fired, as a Unix time, or 0 if it never has.
	 *
	 * Each schedule trigger keeps its own clock, so two schedules in one workflow
	 * don't hold each other back. There used to be one clock per workflow. It is
	 * read as a fallback, so updating doesn't fire every scheduled workflow at once.
	 */
	public static function last_run( int $wf_id, string $node_id ): int {
		$last = (int) get_option( self::LAST_OPT . $wf_id . '_' . $node_id, 0 );

		return $last ? $last : (int) get_option( self::LAST_OPT . $wf_id, 0 );
	}

	/**
	 * Record that a schedule trigger fired. Once a trigger has its own clock the
	 * old per-workflow one is removed, so it can't hold back a schedule added later.
	 */
	public static function mark_run( int $wf_id, string $node_id, int $time ): void {
		update_option( self::LAST_OPT . $wf_id . '_' . $node_id, $time, false );
		delete_option( self::LAST_OPT . $wf_id );
	}

	private static function is_due( int $last, array $config ): bool {
		$now       = time();
		$frequency = $config['frequency'] ?? 'every_minutes';

		switch ( $frequency ) {
			case 'hourly':
				return ( $now - $last ) >= HOUR_IN_SECONDS;

			case 'daily':
				$time = (string) ( $config['time'] ?? '09:00' );
				// Due once per day at/after the configured local time.
				$today_target = strtotime( wp_date( 'Y-m-d' ) . ' ' . ( preg_match( '/^\d{1,2}:\d{2}$/', $time ) ? $time : '09:00' ) );
				if ( false === $today_target ) {
					return false;
				}
				return $now >= $today_target && $last < $today_target;

			case 'every_minutes':
			default:
				$minutes = max( 1, (int) ( $config['interval_minutes'] ?? 15 ) );
				return ( $now - $last ) >= ( $minutes * MINUTE_IN_SECONDS );
		}
	}
}
