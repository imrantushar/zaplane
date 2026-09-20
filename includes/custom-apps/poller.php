<?php
namespace Zaplane\CustomApps;

use Zaplane\Framework\Core\IntegrationLoader;
use Zaplane\Framework\Core\Automation;
use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Framework\Models\Option;
use Zaplane\Models\Workflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drives polling triggers for Custom Apps.
 *
 * A recurring Action Scheduler job walks every active workflow, finds trigger
 * nodes belonging to a custom app whose trigger is `mode: polling`, calls the
 * app's list endpoint, and starts a run for each item it hasn't seen before.
 *
 * "Seen" state is kept per trigger node (workflow + node id) so two workflows
 * polling the same app stay independent, and so the very first poll only
 * establishes a baseline instead of replaying the entire history.
 */
class Poller {

	public const HOOK  = 'zaplane_customapp_poll';
	public const GROUP = 'zaplane_customapp';

	protected const SEEN_PREFIX  = 'zaplane_ca_poll_seen_';
	protected const SEEN_MAX     = 200;
	protected const INTERVAL     = 300; // 5 minutes.

	/**
	 * Wire up the recurring poll. Idempotent — safe on every boot. The poll
	 * handler is registered immediately; scheduling is deferred to `init`, where
	 * Action Scheduler is guaranteed ready (matching the other Zaplane crons).
	 */
	public static function boot(): void {
		add_action( self::HOOK, [ self::class, 'run' ] );
		add_action( 'init', [ self::class, 'schedule' ] );
	}

	public static function schedule(): void {
		if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}
		if ( as_next_scheduled_action( self::HOOK, [], self::GROUP ) ) {
			return;
		}
		as_schedule_recurring_action( time() + self::INTERVAL, self::INTERVAL, self::HOOK, [], self::GROUP );
	}

	public static function unschedule(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, [], self::GROUP );
		}
	}

	/**
	 * Poll every active custom-app polling trigger once.
	 */
	public static function run(): void {
		try {
			$workflows = Workflow::active();
		} catch ( \Throwable $e ) {
			return;
		}

		foreach ( $workflows as $workflow ) {
			$version = $workflow->activeVersion();
			if ( ! $version ) {
				continue;
			}

			$graph = $version->getGraph();
			foreach ( $graph['nodes'] ?? [] as $node ) {
				if ( ( $node['type'] ?? '' ) !== 'trigger' ) {
					continue;
				}
				self::poll_node( (int) $workflow->id, $node );
			}
		}
	}

	/**
	 * Poll a single trigger node if it is a custom-app polling trigger.
	 *
	 * @param array<string,mixed> $node
	 */
	protected static function poll_node( int $workflow_id, array $node ): void {
		$app   = strtolower( (string) ( $node['data']['app'] ?? '' ) );
		$event = (string) ( $node['data']['event'] ?? '' );
		if ( '' === $app || '' === $event ) {
			return;
		}

		$manifest = ManifestStore::get( $app );
		if ( ! is_array( $manifest ) ) {
			return; // Not a custom app.
		}

		$trigger = self::find_trigger( $manifest, $event );
		if ( empty( $trigger ) || 'polling' !== ( $trigger['mode'] ?? '' ) ) {
			return;
		}

		$polling = is_array( $trigger['polling'] ?? null ) ? $trigger['polling'] : [];
		if ( empty( $polling['request'] ) ) {
			return;
		}

		$credentials = self::node_credentials( $node );
		$config      = isset( $node['data']['config'] ) && is_array( $node['data']['config'] ) ? $node['data']['config'] : [];
		$context     = Template::build_context( $config, $credentials );

		$request  = RequestBuilder::build( $polling['request'], $manifest, $context );
		$response = HttpClient::request( $request['method'], $request['url'], $request['headers'], $request['body'] );

		if ( ! empty( $response['error'] ) || $response['status'] < 200 || $response['status'] >= 300 ) {
			return;
		}

		$items      = ResponseMapper::extract_items( $response['body'], (string) ( $polling['items_path'] ?? '' ) );
		$dedupe_key = (string) ( $polling['dedupe_key'] ?? 'id' );

		$node_id  = (string) ( $node['id'] ?? '' );
		$seen_key = self::SEEN_PREFIX . $workflow_id . '_' . $node_id;
		$seen     = Option::get( $seen_key, null );
		$first    = ! is_array( $seen );
		$seen     = is_array( $seen ) ? $seen : [];

		$fresh   = [];
		$new_ids = [];
		foreach ( $items as $item ) {
			$id = self::stringify( ResponseMapper::extract( $item, $dedupe_key ) );
			if ( '' === $id || in_array( $id, $seen, true ) ) {
				continue;
			}
			$new_ids[] = $id;
			$fresh[]   = is_array( $item ) ? $item : [ 'value' => $item ];
		}

		if ( empty( $new_ids ) ) {
			return;
		}

		// Persist the updated baseline first, so a mid-run failure can't cause the
		// same items to fire twice on the next poll.
		$merged = array_slice( array_merge( $new_ids, $seen ), 0, self::SEEN_MAX );
		Option::set( $seen_key, $merged, 'no' );

		if ( $first ) {
			return; // Baseline poll: record, don't replay history.
		}

		$automation = Automation::get_instance();
		if ( ! $automation ) {
			return;
		}

		// Fire oldest-first so runs are chronological.
		foreach ( array_reverse( $fresh ) as $item ) {
			$automation->run_workflow( $workflow_id, $item, $node_id );
		}
	}

	/**
	 * Resolve the connection credentials attached to a trigger node, if any.
	 *
	 * @param array<string,mixed> $node
	 * @return array<string,mixed>
	 */
	protected static function node_credentials( array $node ): array {
		$connection_id = (int) ( $node['data']['connection_id'] ?? 0 );
		if ( $connection_id <= 0 ) {
			return [];
		}
		try {
			return ( new ConnectionManager() )->get_execution_credentials( $connection_id );
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	/**
	 * @param array<string,mixed> $manifest
	 * @return array<string,mixed>
	 */
	protected static function find_trigger( array $manifest, string $key ): array {
		foreach ( $manifest['triggers'] ?? [] as $trigger ) {
			if ( is_array( $trigger ) && (string) ( $trigger['key'] ?? '' ) === $key ) {
				return $trigger;
			}
		}
		return [];
	}

	/**
	 * @param mixed $value
	 */
	protected static function stringify( $value ): string {
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}
		return '';
	}
}
