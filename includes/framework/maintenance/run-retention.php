<?php

namespace Zaplane\Framework\Maintenance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prunes old workflow run history so the runs / node_runs tables don't
 * grow unbounded on busy sites (plan Risk #4).
 *
 * Runs daily via Action Scheduler in the same `zaplane` group the worker
 * daemon drains, so customers don't need a second daemon. Retention period
 * is configurable per-site (option `zaplane_run_retention`, default 30 days).
 * Setting days = 0 disables cleanup entirely.
 */
class RunRetention {

	public const HOOK            = 'zaplane_cleanup_runs';
	public const GROUP           = 'zaplane';
	public const SETTINGS_OPTION = 'zaplane_run_retention';
	public const LAST_RUN_OPTION = 'zaplane_run_retention_last';
	public const DEFAULT_DAYS    = 30;
	public const BATCH_SIZE      = 1000;

	public static function bootstrap(): void {
		add_action( self::HOOK, [ self::class, 'run_cleanup' ] );
		add_action( 'init', [ self::class, 'maybe_schedule' ], 20 );
	}

	public static function maybe_schedule(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}
		if ( as_has_scheduled_action( self::HOOK, [], self::GROUP ) ) {
			return;
		}
		// Stagger first run by 60s so activation isn't immediately followed
		// by a heavy delete.
		as_schedule_recurring_action(
			time() + 60,
			DAY_IN_SECONDS,
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

	public static function run_cleanup(): array {
		$days = self::get_retention_days();

		if ( $days <= 0 ) {
			$result = [
				'skipped'           => true,
				'reason'            => 'retention_disabled',
				'days'              => 0,
				'runs_deleted'      => 0,
				'node_runs_deleted' => 0,
			];
			update_option( self::LAST_RUN_OPTION, array_merge( $result, [ 'finished_at' => time() ] ), false );
			return $result;
		}

		$result = self::cleanup_older_than( $days );
		update_option( self::LAST_RUN_OPTION, array_merge( $result, [ 'finished_at' => time() ] ), false );
		return $result;
	}

	public static function cleanup_older_than( int $days ): array {
		global $wpdb;

		$cutoff_ts = time() - ( $days * DAY_IN_SECONDS );
		$cutoff    = gmdate( 'Y-m-d H:i:s', $cutoff_ts );

		$runs_table      = $wpdb->prefix . 'zaplane_runs';
		$node_runs_table = $wpdb->prefix . 'zaplane_node_runs';

		$total_runs      = 0;
		$total_node_runs = 0;
		$started_at      = microtime( true );

		while ( true ) {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$runs_table} WHERE finished_at IS NOT NULL AND finished_at < %s ORDER BY id ASC LIMIT %d",
					$cutoff,
					self::BATCH_SIZE
				)
			);

			if ( empty( $ids ) ) {
				break;
			}

			$ids          = array_map( 'intval', $ids );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$node_runs_table} WHERE run_id IN ({$placeholders})",
					...$ids
				)
			);
			$total_node_runs += (int) $wpdb->rows_affected;

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$runs_table} WHERE id IN ({$placeholders})",
					...$ids
				)
			);
			$total_runs += (int) $wpdb->rows_affected;

			if ( count( $ids ) < self::BATCH_SIZE ) {
				break;
			}

			// Yield briefly so we don't pin the DB for huge backlogs.
			if ( function_exists( 'usleep' ) ) {
				usleep( 50000 );
			}
		}

		return [
			'skipped'           => false,
			'days'              => $days,
			'cutoff'            => $cutoff,
			'runs_deleted'      => $total_runs,
			'node_runs_deleted' => $total_node_runs,
			'duration_ms'       => (int) ( ( microtime( true ) - $started_at ) * 1000 ),
		];
	}

	public static function get_retention_days(): int {
		$opt  = get_option( self::SETTINGS_OPTION, [] );
		$days = isset( $opt['days'] ) ? (int) $opt['days'] : self::DEFAULT_DAYS;
		return max( 0, $days );
	}

	public static function set_retention_days( int $days ): void {
		update_option( self::SETTINGS_OPTION, [ 'days' => max( 0, $days ) ], false );
	}

	public static function get_last_run(): array {
		$last = get_option( self::LAST_RUN_OPTION, [] );
		return is_array( $last ) ? $last : [];
	}
}
