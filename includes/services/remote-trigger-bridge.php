<?php
namespace Zaplane\Services;

use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Framework\Core\Automation;
use Zaplane\Framework\Core\IntegrationLoader;
use Zaplane\Models\Workflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brings a connected site's triggers into the workflows running here.
 *
 * WordPress has no REST route that subscribes to a hook, so a trigger firing
 * on another site cannot be read the way a step runs — over the core API with
 * an Application Password. It needs Zaplane on both ends: this half walks its
 * own active workflows, tells each connected site which hooks to watch and
 * what a trigger means there, and collects what has fired since the last call.
 *
 * Collection runs on a recurring Action Scheduler job with a WP-Cron
 * fallback, and again a moment after a workflow is saved so a trigger just
 * added is watched before anything can fire on it. Only a connection in
 * `zaplane` mode is contacted; a connection in `rest` mode has nothing to
 * watch and is left alone.
 */
class RemoteTriggerBridge {

	/**
	 * Action Scheduler job that collects trigger events.
	 *
	 * @var string
	 */
	public const HOOK = 'zaplane_remote_trigger_poll';

	/**
	 * Action Scheduler group the job is scheduled in.
	 *
	 * @var string
	 */
	public const GROUP = 'zaplane_remote_triggers';

	/**
	 * WP-Cron hook used when Action Scheduler is unavailable.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'zaplane_remote_wpcron';

	/**
	 * Interval WP-Cron schedules the fallback on.
	 *
	 * @var string
	 */
	public const CRON_SCHEDULE = 'zaplane_remote_trigger_interval';

	/**
	 * Seconds between collections.
	 *
	 * The default; the zaplane_remote_trigger_poll_interval filter overrides
	 * it per site.
	 *
	 * @var int
	 */
	protected const INTERVAL = 60;

	/**
	 * Option holding the last sequence number collected, per connection.
	 *
	 * @var string
	 */
	protected const SEQ_OPTION = 'zaplane_remote_trigger_seq';

	/**
	 * Option holding which connection was last given a watch, and for which
	 * app, so a connection that has lost its trigger nodes is released.
	 *
	 * @var string
	 */
	protected const WATCHED_OPTION = 'zaplane_remote_watched_connections';

	/**
	 * Option holding the last REST poll's state, per connection: where the
	 * rising IDs stood and which statuses were seen.
	 *
	 * @var string
	 */
	protected const REST_STATE = 'zaplane_remote_rest_state';

	/**
	 * Option recording the interval the current schedule was made for, so a
	 * changed interval replaces the schedule on the next boot.
	 *
	 * @var string
	 */
	protected const INTERVAL_OPTION = 'zaplane_remote_poll_interval';

	/**
	 * Transient keeping a workflow save from collecting several times over.
	 *
	 * @var string
	 */
	protected const SYNC_TRANSIENT = 'zaplane_remote_trigger_syncing';

	/**
	 * Wire up collection. Idempotent — safe on every boot.
	 */
	public static function boot(): void {
		add_action( self::HOOK, [ self::class, 'run' ] );
		add_action( self::CRON_HOOK, [ self::class, 'run' ] );
		add_action( 'zaplane_workflow_updated', [ self::class, 'sync_soon' ] );
		add_filter( 'cron_schedules', [ self::class, 'cron_schedules' ] );

		if ( did_action( 'init' ) ) {
			self::schedule();
			return;
		}

		add_action( 'init', [ self::class, 'schedule' ] );
	}

	/**
	 * Stop the recurring collection when the plugin is deactivated.
	 */
	public static function unschedule(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, [], self::GROUP );
		}

		if ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_unschedule_event' ) ) {
			$timestamp = wp_next_scheduled( self::CRON_HOOK );

			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK );
			}
		}
	}

	/**
	 * Provide the interval WP-Cron falls back to.
	 *
	 * @param array<string,array<string,int|string>> $schedules Intervals core knows.
	 * @return array<string,array<string,int|string>>
	 */
	public static function cron_schedules( array $schedules ): array {
		$schedules[ self::CRON_SCHEDULE ] = [
			'interval' => self::interval(),
			'display'  => 'Zaplane remote trigger polling',
		];

		return $schedules;
	}

	/**
	 * Schedule the collection, on Action Scheduler where it is available and
	 * on WP-Cron where it is not.
	 */
	/**
	 * Schedule the collection, on Action Scheduler where it is available and
	 * on WP-Cron where it is not.
	 *
	 * A schedule left by an interval that has since changed is replaced, so
	 * editing the interval does not wait for the old recurrence to matter.
	 */
	public static function schedule(): void {
		if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			if (
				function_exists( 'wp_next_scheduled' )
				&& function_exists( 'wp_schedule_event' )
			) {
				$scheduled = wp_next_scheduled( self::CRON_HOOK );

				if ( $scheduled && self::interval_changed() ) {
					wp_unschedule_event( $scheduled, self::CRON_HOOK );
					$scheduled = false;
				}

				if ( ! $scheduled ) {
					wp_schedule_event( time() + self::interval(), self::CRON_SCHEDULE, self::CRON_HOOK );
				}
			}

			self::remember_interval();
			return;
		}

		$scheduled = (bool) as_next_scheduled_action( self::HOOK, [], self::GROUP );

		if ( $scheduled && self::interval_changed() && function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, [], self::GROUP );
			$scheduled = false;
		}

		if ( ! $scheduled ) {
			as_schedule_recurring_action( time() + self::interval(), self::interval(), self::HOOK, [], self::GROUP );
		}

		self::remember_interval();
	}

	/**
	 * Seconds between collections on this site.
	 *
	 * The zaplane_remote_trigger_poll_interval filter overrides the default —
	 * a site that wants near-real-time triggers can poll every few seconds,
	 * one watching a slow or shared host can back off. Never below one.
	 */
	private static function interval(): int {
		$seconds = (int) apply_filters( 'zaplane_remote_trigger_poll_interval', self::INTERVAL );

		return $seconds >= 1 ? $seconds : self::INTERVAL;
	}

	/**
	 * Whether the schedule on record was made for a different interval than
	 * the one this site now runs on.
	 */
	private static function interval_changed(): bool {
		return (int) get_option( self::INTERVAL_OPTION, 0 ) !== self::interval();
	}

	/**
	 * Record the interval the current schedule was made for.
	 */
	private static function remember_interval(): void {
		update_option( self::INTERVAL_OPTION, self::interval(), false );
	}

	/**
	 * Collect as soon as a workflow is saved, so a trigger just added is
	 * watched on the other side before anything can fire on it. Throttled,
	 * because a workflow with several triggers saves several times over.
	 */
	public static function sync_soon(): void {
		if ( get_transient( self::SYNC_TRANSIENT ) ) {
			return;
		}

		set_transient( self::SYNC_TRANSIENT, 1, 5 );

		self::run();
	}

	/**
	 * Collect every connected site once. Never throws: one site that cannot
	 * be reached must not stop the rest, or leave the schedule to fail.
	 */
	public static function run(): void {
		try {
			self::collect();
		} catch ( \Throwable $e ) {
			if ( function_exists( 'do_action' ) ) {
				do_action( 'zaplane_remote_trigger_error', $e );
			}
		}
	}

	/**
	 * Tell each connected site what to watch, take what has fired, and start
	 * the workflows it belongs to.
	 */
	private static function collect(): void {
		$groups     = self::spec_groups();
		$watched    = self::stored( self::WATCHED_OPTION );
		$seqs       = self::stored( self::SEQ_OPTION );
		$rest_state = self::stored( self::REST_STATE );

		$ids = array_values(
			array_unique(
				array_merge( array_keys( $groups ), array_keys( $watched ) )
			)
		);

		if ( array() === $ids ) {
			return;
		}

		sort( $ids );

		$manager = new ConnectionManager();
		$changed  = false;

		foreach ( $ids as $connection_id ) {
			$connection_id = (int) $connection_id;

			if ( $connection_id <= 0 ) {
				continue;
			}

			try {
				$credentials = $manager->get_execution_credentials( $connection_id );
			} catch ( \Throwable $e ) {
				$credentials = [];
			}

			if ( empty( $credentials ) ) {
				// The connection is gone. It can no longer be released on the
				// other side, but it must not be collected for either.
				if ( isset( $watched[ $connection_id ] ) || isset( $seqs[ $connection_id ] ) || isset( $rest_state[ $connection_id ] ) ) {
					unset( $watched[ $connection_id ], $seqs[ $connection_id ], $rest_state[ $connection_id ] );
					$changed = true;
				}

				continue;
			}

			$specs = $groups[ $connection_id ] ?? [];
			$app   = (string) ( $specs[0]['app'] ?? ( $watched[ $connection_id ] ?? '' ) );

			if ( '' === $app ) {
				continue;
			}

			$integration = IntegrationLoader::get( $app );

			if ( ! $integration || ( ! method_exists( $integration, 'remote_watch' ) && ! method_exists( $integration, 'rest_watch' ) ) ) {
				continue;
			}

			// A connection the site talks to over the core REST API — no
			// Zaplane on the other end — is watched by polling its routes for
			// what changed, not by asking it what fired.
			if (
				method_exists( $integration, 'remote_connection_mode' )
				&& 'rest' === $integration::remote_connection_mode( $credentials )
			) {
				if ( ! method_exists( $integration, 'rest_watch' ) ) {
					continue;
				}

				$state  = is_array( $rest_state[ $connection_id ] ?? null ) ? $rest_state[ $connection_id ] : [];
				$answer = $integration::rest_watch( $credentials, $specs, $state );

				if ( ! $answer['ok'] ) {
					if ( '' !== $answer['error'] && function_exists( 'do_action' ) ) {
						do_action( 'zaplane_remote_trigger_error', $connection_id, $answer['error'] );
					}

					continue;
				}

				$rest_state[ $connection_id ] = $answer['state'];
				$watched[ $connection_id ]    = $app;
				$changed                      = true;

				foreach ( $answer['events'] as $event ) {
					self::dispatch( $connection_id, $event );
				}

				continue;
			}//end if

			if ( ! method_exists( $integration, 'remote_watch' ) ) {
				continue;
			}

			$had_seq = isset( $seqs[ $connection_id ] );
			$answer  = $integration::remote_watch(
				$credentials,
				$specs,
				(int) ( $seqs[ $connection_id ] ?? 0 )
			);

			if ( ! $answer['ok'] ) {
				if ( '' !== $answer['error'] && function_exists( 'do_action' ) ) {
					do_action( 'zaplane_remote_trigger_error', $connection_id, $answer['error'] );
				}

				continue;
			}

			$seqs[ $connection_id ]    = (int) $answer['seq'];
			$watched[ $connection_id ] = $app;
			$changed                   = true;

			// First contact with this connection records where it stands and
			// starts from there, so a sequence this site has no memory of is
			// never replayed as a run.
			if ( ! $had_seq ) {
				continue;
			}

			foreach ( $answer['events'] as $event ) {
				self::dispatch( $connection_id, $event );
			}
		}//end foreach

		if ( $changed ) {
			update_option( self::SEQ_OPTION, $seqs, false );
			update_option( self::WATCHED_OPTION, $watched, false );
			update_option( self::REST_STATE, $rest_state, false );
		}
	}

	/**
	 * Hand one collected event to the workflows bound to it on this site.
	 *
	 * @param int                 $connection_id Connection it arrived over.
	 * @param array<string,mixed> $event         Sequence, hook and payload.
	 */
	private static function dispatch( int $connection_id, array $event ): void {
		$hook    = (string) ( $event['hook'] ?? '' );
		$payload = $event['payload'] ?? null;

		if ( '' === $hook || ! is_array( $payload ) || array() === $payload ) {
			return;
		}

		$automation = Automation::get_instance();

		if ( ! $automation ) {
			return;
		}

		$automation->trigger_event_for_connection( $hook, $payload, $connection_id );
	}

	/**
	 * The triggers to watch on each connected site, keyed by connection.
	 *
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	private static function spec_groups(): array {
		$groups = [];

		foreach ( self::active_trigger_nodes() as $node ) {
			$data = isset( $node['data'] ) && is_array( $node['data'] ) ? $node['data'] : [];

			$app = strtolower( (string) ( $data['app'] ?? '' ) );

			if ( ! in_array( $app, self::watchable_apps(), true ) ) {
				continue;
			}

			$connection_id = (int) ( $data['connection_id'] ?? 0 );

			if ( $connection_id <= 0 ) {
				continue;
			}

			$event = (string) ( $data['event'] ?? '' );
			$hook  = (string) ( $data['hook'] ?? '' );

			if ( '' === $event ) {
				continue;
			}

			if ( '' === $hook ) {
				$hook = self::hook_for( $app, $event );
			}

			if ( '' === $hook ) {
				continue;
			}

			$groups[ $connection_id ][] = [
				'app'    => $app,
				'event'  => $event,
				'hook'   => $hook,
				'config' => is_array( $data['config'] ?? null ) ? $data['config'] : [],
			];
		}//end foreach

		return $groups;
	}

	/**
	 * Every trigger node of every active workflow.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function active_trigger_nodes(): array {
		try {
			$workflows = Workflow::active();
		} catch ( \Throwable $e ) {
			return [];
		}

		// active() answers with a Collection, not an array — the nodes below
		// are read the same way either kind is iterated.
		if ( empty( $workflows ) || ! is_iterable( $workflows ) ) {
			return [];
		}

		$nodes = [];

		foreach ( $workflows as $workflow ) {
			try {
				$version = $workflow->activeVersion();
			} catch ( \Throwable $e ) {
				continue;
			}

			if ( ! $version ) {
				continue;
			}

			$graph = $version->getGraph();

			if ( ! is_array( $graph ) ) {
				continue;
			}

			foreach ( (array) ( $graph['nodes'] ?? [] ) as $node ) {
				if ( ! is_array( $node ) || ( $node['type'] ?? '' ) !== 'trigger' ) {
					continue;
				}

				$nodes[] = $node;
			}
		}//end foreach

		return $nodes;
	}

	/**
	 * The WordPress hook a trigger event fires on, for a node that did not
	 * record one of its own.
	 *
	 * @param string $app   Integration the trigger belongs to.
	 * @param string $event Trigger event.
	 */
	private static function hook_for( string $app, string $event ): string {
		$integration = IntegrationLoader::get( $app );

		if ( ! $integration || ! method_exists( $integration, 'get_triggers' ) ) {
			return '';
		}

		$triggers = $integration::get_triggers();

		if ( ! isset( $triggers[ $event ] ) || ! is_array( $triggers[ $event ] ) ) {
			return '';
		}

		return (string) ( $triggers[ $event ]['hook'] ?? '' );
	}

	/**
	 * Integrations whose triggers this bridge carries.
	 *
	 * @return array<int,string>
	 */
	private static function watchable_apps(): array {
		$apps = apply_filters( 'zaplane_remote_watch_apps', [ 'wordpress' ] );

		if ( ! is_array( $apps ) ) {
			return [ 'wordpress' ];
		}

		$allowed = [];

		foreach ( $apps as $app ) {
			if ( is_string( $app ) ) {
				$allowed[] = sanitize_key( $app );
			}
		}

		return $allowed;
	}

	/**
	 * One of this bridge's stored maps, never a value written by anything else.
	 *
	 * @param string $key Option name.
	 * @return array<int|string,mixed>
	 */
	private static function stored( string $key ): array {
		$value = get_option( $key, [] );

		return is_array( $value ) ? $value : [];
	}
}
