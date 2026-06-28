<?php

namespace Zaplane\Scheduler;

use Zaplane\Framework\Classes\Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fires schedule-triggered workflows. A recurring Action Scheduler tick runs
 * every minute, finds active workflows whose trigger is the Schedule app, and
 * runs the ones that are due — tracking last-run per workflow.
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
			$wf_id  = (int) ( $trigger['workflow_id'] ?? 0 );
			$config = $trigger['graph_node']['data']['config'] ?? [];
			if ( ! $wf_id ) {
				continue;
			}

			if ( ! self::is_due( $wf_id, (array) $config ) ) {
				continue;
			}

			zaplane_run_workflow( $wf_id, [
				'timestamp' => current_time( 'mysql' ),
				'unix'      => time(),
			] );

			update_option( self::LAST_OPT . $wf_id, time(), false );
		}
	}

	private static function is_due( int $wf_id, array $config ): bool {
		$last      = (int) get_option( self::LAST_OPT . $wf_id, 0 );
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
