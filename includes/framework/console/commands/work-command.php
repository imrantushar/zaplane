<?php

namespace Zaplane\Framework\Console\Commands;

use Zaplane\Framework\Console\Command;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Long-lived worker daemon — `wp zaplane work`.
 *
 * Drains Action Scheduler's `zaplane` group on a tight loop so paired
 * sites get sub-second precision instead of waiting for wp-cron. Meant
 * to run under systemd / supervisor on a customer's VPS.
 *
 *   wp zaplane work --max-jobs=500 --memory=256
 *
 * Honors SIGTERM gracefully (when pcntl is available) so systemd
 * restarts are clean. Auto-restarts itself when memory exceeds the
 * limit, matching Laravel Horizon's behavior — needed because PHP
 * leaks under continuous load.
 */
class WorkCommand extends Command {

	protected string $signature = 'work';
	protected string $description = 'Long-running worker that drains the Action Scheduler queue (replaces wp-cron).';

	private bool $shouldExit = false;
	private int $processed = 0;
	private int $startedAt = 0;

	public function handle( array $args, array $assoc_args ): void {
		$max_jobs   = (int) ( $assoc_args['max-jobs']   ?? 0 ); // 0 = forever
		$memory_mb  = (int) ( $assoc_args['memory']     ?? 256 );
		$sleep_ms   = (int) ( $assoc_args['sleep']      ?? 200 ); // when queue empty
		$batch      = max( 1, (int) ( $assoc_args['batch'] ?? 5 ) );

		ini_set( 'memory_limit', $memory_mb . 'M' );
		set_time_limit( 0 );

		$this->startedAt = time();
		$this->info( "▶ Zaplane worker booted. memory={$memory_mb}MB max_jobs={$max_jobs} batch={$batch}" );
		$this->info( "  group=zaplane (Action Scheduler) — Ctrl-C to stop." );

		$this->installSignalHandlers();

		while ( ! $this->shouldExit ) {
			$ran = $this->drainOnce( $batch );
			$this->processed += $ran;

			if ( $ran === 0 ) {
				usleep( $sleep_ms * 1000 );
				$this->writeHeartbeat( 'idle' );
				continue;
			}

			$this->writeHeartbeat( 'running' );

			if ( $max_jobs > 0 && $this->processed >= $max_jobs ) {
				$this->info( "✓ Hit --max-jobs={$max_jobs}; exiting cleanly so systemd can restart us." );
				break;
			}

			if ( ( memory_get_usage( true ) / 1024 / 1024 ) >= $memory_mb * 0.9 ) {
				$this->warning( '⚠ Memory near cap — exiting so systemd restarts us fresh.' );
				break;
			}
		}

		$this->writeHeartbeat( 'stopped' );
		$this->success( "Worker exit. Processed {$this->processed} job(s) over " . ( time() - $this->startedAt ) . 's.' );
	}

	/**
	 * Run up to $batch due actions in the `zaplane` group. Returns the
	 * number actually executed.
	 */
	private function drainOnce( int $batch ): int {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return 0; // Action Scheduler not loaded
		}

		$due = as_get_scheduled_actions( [
			'group'    => 'zaplane',
			'status'   => \ActionScheduler_Store::STATUS_PENDING,
			'date'     => time(),
			'date_compare' => '<=',
			'per_page' => $batch,
			'orderby'  => 'date',
			'order'    => 'ASC',
		], 'ids' );

		if ( empty( $due ) ) {
			return 0;
		}

		$ran = 0;
		foreach ( $due as $action_id ) {
			if ( $this->shouldExit ) break;
			try {
				\ActionScheduler::runner()->process_action( $action_id, 'CLI' );
				$ran++;
			} catch ( \Throwable $e ) {
				$this->warning( "Action #{$action_id} failed: " . $e->getMessage() );
			}
		}
		return $ran;
	}

	/**
	 * Drop a row in the cache (transient) so the plugin admin's "Worker
	 * health" panel can confirm the daemon is alive without polling AS.
	 */
	private function writeHeartbeat( string $state ): void {
		$pid = function_exists( 'getmypid' ) ? getmypid() : null;
		set_transient( 'zaplane_worker_heartbeat', [
			'state'        => $state,
			'pid'          => $pid,
			'last_seen'    => time(),
			'started_at'   => $this->startedAt,
			'processed'    => $this->processed,
			'memory_mb'    => round( memory_get_usage( true ) / 1024 / 1024, 1 ),
		], 120 );
	}

	private function installSignalHandlers(): void {
		if ( ! function_exists( 'pcntl_signal' ) ) {
			return; // Windows / hosts without pcntl — best-effort only
		}
		pcntl_async_signals( true );
		pcntl_signal( SIGTERM, function () { $this->shouldExit = true; } );
		pcntl_signal( SIGINT,  function () { $this->shouldExit = true; } );
	}
}
