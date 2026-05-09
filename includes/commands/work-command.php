<?php

namespace Zaplane\Commands;

use Zaplane\Framework\Console\Command;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Long-lived worker daemon for Zaplane.
 *
 * Drains the `zaplane` Action Scheduler group continuously, like Laravel
 * Horizon. Designed to run under systemd or supervisor on a VPS so that
 * scheduled hooks fire on time and PHP timeouts never apply to workflow
 * execution.
 */
class WorkCommand extends Command {

	protected string $signature   = 'work';
	protected string $description = 'Run the Zaplane worker daemon (drains the zaplane Action Scheduler queue).';

	private const HEARTBEAT_OPTION = 'zaplane_worker_status';
	private const LOCK_OPTION      = 'zaplane_worker_lock';
	private const LOCK_STALE_AFTER = 60;
	private const GROUP            = 'zaplane';

	private bool $should_stop      = false;
	private string $stop_reason    = '';
	private int $jobs_processed    = 0;
	private int $jobs_failed       = 0;
	private float $started_at      = 0.0;
	private float $last_heartbeat  = 0.0;
	private array $recent_runtimes = [];

	public function handle( array $args, array $assoc_args ): void {
		$opts = $this->parse_options( $assoc_args );

		if ( ! $this->acquire_lock( $opts ) ) {
			return;
		}

		$this->started_at = microtime( true );
		$this->register_signal_handlers();
		$this->write_heartbeat( 'starting', $opts );

		$this->info( sprintf(
			'Zaplane worker started (pid=%d, group=%s, batch=%d, sleep=%ds, max-jobs=%d, max-time=%ds, memory=%dMB)',
			getmypid(),
			$opts['group'],
			$opts['batch'],
			$opts['sleep'],
			$opts['max_jobs'],
			$opts['max_time'],
			$opts['memory']
		) );

		while ( ! $this->should_stop ) {
			$this->check_stop_conditions( $opts );
			if ( $this->should_stop ) {
				break;
			}

			$processed = $this->drain_batch( $opts );

			if ( $opts['once'] && 0 === $processed ) {
				// `--once` means "drain everything that can be claimed right
				// now, then exit". processed === 0 here means stake_claim
				// returned no due actions — either the queue is empty or
				// every pending action is scheduled for the future. Either
				// way, there's nothing more for this run to do. The single-
				// process lock prevents the claim-contention case that the
				// previous "verify depth" check tried to defend against.
				$this->stop_reason = 'once';
				break;
			}

			$this->write_heartbeat( 'running', $opts );

			if ( 0 === $processed ) {
				$this->idle_sleep( $opts['sleep'] );
			}
		}

		$this->write_heartbeat( 'stopped', $opts );
		$this->release_lock();
		$this->success( sprintf(
			'Worker stopped (reason=%s, jobs=%d, failed=%d, uptime=%ds)',
			$this->stop_reason ?: 'signal',
			$this->jobs_processed,
			$this->jobs_failed,
			(int) ( microtime( true ) - $this->started_at )
		) );
	}

	private function parse_options( array $assoc_args ): array {
		return [
			'group'    => (string) ( $assoc_args['queue'] ?? self::GROUP ),
			'batch'    => max( 1, (int) ( $assoc_args['batch'] ?? 25 ) ),
			'sleep'    => max( 1, (int) ( $assoc_args['sleep'] ?? 1 ) ),
			'max_jobs' => max( 0, (int) ( $assoc_args['max-jobs'] ?? 1000 ) ),
			'max_time' => max( 0, (int) ( $assoc_args['max-time'] ?? 3600 ) ),
			'memory'   => max( 64, (int) ( $assoc_args['memory'] ?? 256 ) ),
			'once'     => isset( $assoc_args['once'] ),
			'force'    => isset( $assoc_args['force'] ),
		];
	}

	/**
	 * Single-process lock. Refuses to start if another worker on this host
	 * is alive (PID still running and lock < 60s old). Stale locks (no
	 * heartbeat in 60s, or PID gone) are taken over with a warning.
	 * `--force` bypasses the check entirely.
	 */
	private function acquire_lock( array $opts ): bool {
		$existing = get_option( self::LOCK_OPTION, [] );
		$now      = time();
		$host     = gethostname() ?: 'unknown';

		if ( ! $opts['force'] && is_array( $existing ) && ! empty( $existing['pid'] ) ) {
			$age = $now - (int) ( $existing['updated_at'] ?? 0 );
			$same_host = ( $existing['host'] ?? '' ) === $host;
			$pid_alive = $same_host && $this->pid_alive( (int) $existing['pid'] );

			if ( $pid_alive && $age < self::LOCK_STALE_AFTER ) {
				$this->error( sprintf(
					'Another worker is already running on %s (pid=%d, last heartbeat %ds ago). Use --force to override.',
					$existing['host'],
					$existing['pid'],
					$age
				) );
				return false;
			}

			if ( $age >= self::LOCK_STALE_AFTER || ! $pid_alive ) {
				$this->warning( sprintf(
					'Taking over stale lock from pid=%d on %s (age=%ds, alive=%s).',
					$existing['pid'],
					$existing['host'] ?? 'unknown',
					$age,
					$pid_alive ? 'yes' : 'no'
				) );
			}
		}

		update_option( self::LOCK_OPTION, [
			'pid'        => getmypid(),
			'host'       => $host,
			'started_at' => $now,
			'updated_at' => $now,
		], false );

		return true;
	}

	private function refresh_lock(): void {
		$existing = get_option( self::LOCK_OPTION, [] );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}
		$existing['pid']        = getmypid();
		$existing['host']       = gethostname() ?: 'unknown';
		$existing['updated_at'] = time();
		if ( empty( $existing['started_at'] ) ) {
			$existing['started_at'] = (int) $this->started_at;
		}
		update_option( self::LOCK_OPTION, $existing, false );
	}

	private function release_lock(): void {
		$existing = get_option( self::LOCK_OPTION, [] );
		// Only clear the lock if it's ours — defends against a takeover
		// process clearing the new owner's lock on its way out.
		if ( is_array( $existing ) && (int) ( $existing['pid'] ?? 0 ) === getmypid() ) {
			delete_option( self::LOCK_OPTION );
		}
	}

	private function pid_alive( int $pid ): bool {
		if ( $pid <= 0 ) {
			return false;
		}
		if ( function_exists( 'posix_kill' ) ) {
			return @\posix_kill( $pid, 0 );
		}
		// Without POSIX, conservatively assume alive — admin can use --force.
		return true;
	}

	private function register_signal_handlers(): void {
		if ( ! function_exists( 'pcntl_async_signals' ) ) {
			return;
		}
		\pcntl_async_signals( true );
		\pcntl_signal( SIGTERM, [ $this, 'handle_signal' ] );
		\pcntl_signal( SIGINT, [ $this, 'handle_signal' ] );
		\pcntl_signal( SIGQUIT, [ $this, 'handle_signal' ] );
	}

	public function handle_signal( int $signo ): void {
		$this->should_stop = true;
		$this->stop_reason = 'signal:' . $signo;
	}

	private function check_stop_conditions( array $opts ): void {
		if ( $opts['max_jobs'] > 0 && $this->jobs_processed >= $opts['max_jobs'] ) {
			$this->should_stop = true;
			$this->stop_reason = 'max-jobs';
			return;
		}
		if ( $opts['max_time'] > 0 && ( microtime( true ) - $this->started_at ) >= $opts['max_time'] ) {
			$this->should_stop = true;
			$this->stop_reason = 'max-time';
			return;
		}
		$mem_mb = (int) ( memory_get_usage( true ) / 1024 / 1024 );
		if ( $mem_mb >= $opts['memory'] ) {
			$this->should_stop = true;
			$this->stop_reason = 'memory:' . $mem_mb . 'MB';
		}
	}

	/**
	 * Claim and process one batch from the zaplane group.
	 * Returns the number of actions actually run.
	 */
	private function drain_batch( array $opts ): int {
		if ( ! class_exists( '\\ActionScheduler' ) ) {
			$this->error( 'Action Scheduler is not loaded. Cannot drain queue.' );
			$this->should_stop = true;
			$this->stop_reason = 'no-action-scheduler';
			return 0;
		}

		$store  = \ActionScheduler::store();
		$runner = \ActionScheduler::runner();

		// Pre-check queue depth so the daemon idles silently when the
		// group is empty (or does not exist yet). stake_claim() throws
		// "group does not exist" until the first action is enqueued.
		if ( $this->queue_depth( $opts['group'] ) <= 0 ) {
			return 0;
		}

		try {
			$claim = $store->stake_claim( $opts['batch'], null, [], $opts['group'] );
		} catch ( \Throwable $e ) {
			$msg = $e->getMessage();
			if ( false === stripos( $msg, 'does not exist' ) ) {
				$this->warning( 'Could not stake claim: ' . $msg );
			}
			return 0;
		}

		$actions = $claim->get_actions();
		if ( empty( $actions ) ) {
			$store->release_claim( $claim );
			return 0;
		}

		$processed = 0;
		foreach ( $actions as $action_id ) {
			if ( $this->should_stop ) {
				break;
			}

			$t0 = microtime( true );
			try {
				$runner->process_action( (int) $action_id, 'Zaplane Worker' );
				++$this->jobs_processed;
				++$processed;
			} catch ( \Throwable $e ) {
				++$this->jobs_failed;
				$this->warning( sprintf( 'Action %d failed: %s', $action_id, $e->getMessage() ) );
			}
			$this->record_runtime( microtime( true ) - $t0 );

			$this->check_stop_conditions( $opts );
		}

		$store->release_claim( $claim );
		return $processed;
	}

	private function record_runtime( float $seconds ): void {
		$this->recent_runtimes[] = $seconds;
		if ( count( $this->recent_runtimes ) > 50 ) {
			array_shift( $this->recent_runtimes );
		}
	}

	private function idle_sleep( int $seconds ): void {
		// Break sleep into 1s chunks so signals are handled promptly.
		for ( $i = 0; $i < $seconds; $i++ ) {
			if ( $this->should_stop ) {
				return;
			}
			sleep( 1 );
		}
	}

	private function write_heartbeat( string $state, array $opts ): void {
		$now = microtime( true );
		// Throttle writes — at most once per second except on state transitions.
		if ( 'running' === $state && $now - $this->last_heartbeat < 1.0 ) {
			return;
		}
		$this->last_heartbeat = $now;
		// Keep the lock fresh while running. On `stopped` we leave it
		// alone so the upcoming release_lock() can actually delete it
		// instead of re-writing a fresh timestamp.
		if ( 'stopped' !== $state ) {
			$this->refresh_lock();
		}

		$avg_runtime = $this->recent_runtimes
			? array_sum( $this->recent_runtimes ) / count( $this->recent_runtimes )
			: 0.0;

		$queue_depth = $this->queue_depth( $opts['group'] );

		$payload = [
			'state'           => $state,
			'pid'             => getmypid(),
			'host'            => gethostname() ?: 'unknown',
			'started_at'      => (int) $this->started_at,
			'updated_at'      => time(),
			'jobs_processed'  => $this->jobs_processed,
			'jobs_failed'     => $this->jobs_failed,
			'memory_mb'       => (int) ( memory_get_usage( true ) / 1024 / 1024 ),
			'memory_peak_mb'  => (int) ( memory_get_peak_usage( true ) / 1024 / 1024 ),
			'avg_runtime_ms'  => (int) ( $avg_runtime * 1000 ),
			'queue_depth'     => $queue_depth,
			'group'           => $opts['group'],
			'stop_reason'     => $this->stop_reason,
			'plugin_version'  => defined( 'ZAPLANE_VERSION' ) ? ZAPLANE_VERSION : 'unknown',
		];

		update_option( self::HEARTBEAT_OPTION, $payload, false );
	}

	private function queue_depth( string $group ): int {
		if ( ! class_exists( '\\ActionScheduler' ) ) {
			return -1;
		}
		try {
			return (int) \ActionScheduler::store()->query_actions(
				[
					'group'  => $group,
					'status' => \ActionScheduler_Store::STATUS_PENDING,
				],
				'count'
			);
		} catch ( \Throwable $e ) {
			return -1;
		}
	}
}
