<?php

namespace Zaplane\Framework\Classes;

use Zaplane\Framework\Core\IntegrationLoader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Query {

	private const CACHE_KEY = 'zaplane_trigger_map';

	/**
	 * In-request memo of the resolved event => triggers map.
	 *
	 * @var array<string,array<int,array<string,mixed>>>|null
	 */
	private static ?array $trigger_map = null;

	/**
	 * WP hook names that at least one active workflow listens on.
	 *
	 * @return string[]
	 */
	public static function get_active_trigger_events(): array {
		return array_keys( self::get_trigger_map() );
	}

	/**
	 * Active-workflow triggers bound to a given event.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_active_workflows_for_event( string $event ): array {
		$map = self::get_trigger_map();
		return $map[ $event ] ?? [];
	}

	/**
	 * The event => triggers map, served from cache.
	 *
	 * Building it walks every active workflow's active version graph (an N+1 that
	 * used to run on every request). Instead we cache it keyed by a cheap
	 * fingerprint of the active set — a single indexed JOIN returning
	 * (workflow_id, active_version_id, graph_hash) rows. Any save, version
	 * activation, status change or delete shifts those rows, so the cache
	 * self-invalidates without depending on every mutation firing a hook.
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public static function get_trigger_map(): array {
		if ( null !== self::$trigger_map ) {
			return self::$trigger_map;
		}

		$signature = self::active_signature();
		$cached    = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) && isset( $cached['sig'], $cached['map'] ) && $cached['sig'] === $signature ) {
			self::$trigger_map = $cached['map'];
			return self::$trigger_map;
		}

		$map = self::build_trigger_map();
		set_transient( self::CACHE_KEY, [
			'sig' => $signature,
			'map' => $map
		], HOUR_IN_SECONDS );
		self::$trigger_map = $map;
		return $map;
	}

	/**
	 * Drop the cached map (in-request memo + transient). Called on workflow
	 * changes as belt-and-suspenders; the signature already self-invalidates.
	 */
	public static function flush_trigger_map(): void {
		self::$trigger_map = null;
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Cheap fingerprint of the active-workflow set: each active workflow's active
	 * version id and graph hash. The hash has to be part of it. Saving a live
	 * workflow without adding or removing a step edits its active version in
	 * place, so a trigger changed that way (a new event, a different form) keeps
	 * the same version id and would otherwise go on firing with its old settings.
	 */
	private static function active_signature(): string {
		global $wpdb;
		$workflows = $wpdb->prefix . 'zaplane_workflows';
		$versions  = $wpdb->prefix . 'zaplane_workflow_versions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table names only; no user input.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT w.id AS wid, v.id AS vid, v.graph_hash AS vh
				 FROM %i w
				 INNER JOIN %i v ON v.workflow_id = w.id AND v.is_active = 1
				 WHERE w.status = 'active'
				 ORDER BY w.id",
				$workflows,
				$versions
			),
			ARRAY_A
		);

		return md5( (string) wp_json_encode( $rows ) );
	}

	/**
	 * Build the event => triggers map from every active workflow's active
	 * version. Only runs on a cache miss.
	 *
	 * Reads through a single indexed JOIN (fresh, straight from the DB) rather
	 * than the ORM: this replaces the old 1+N `activeVersion()` walk AND avoids
	 * the ORM's per-request query cache ever serving a stale active set — which
	 * could otherwise cache a wrong map under an already-correct signature.
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	private static function build_trigger_map(): array {
		global $wpdb;
		$workflows = $wpdb->prefix . 'zaplane_workflows';
		$versions  = $wpdb->prefix . 'zaplane_workflow_versions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table names only; no user input. Cached by the caller.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT w.id AS workflow_id, v.id AS version_id, v.graph_json AS graph_json
				 FROM %i w
				 INNER JOIN %i v ON v.workflow_id = w.id AND v.is_active = 1
				 WHERE w.status = 'active'",
				$workflows,
				$versions
			),
			ARRAY_A
		);

		$map = [];

		foreach ( (array) $rows as $row ) {
			$graph = json_decode( (string) $row['graph_json'], true );
			if ( ! is_array( $graph ) ) {
				continue;
			}

			foreach ( $graph['nodes'] ?? [] as $node ) {
				if ( ( $node['type'] ?? '' ) !== 'trigger' ) {
					continue;
				}

				$entry = [
					'workflow_version_id' => (int) $row['version_id'],
					'workflow_id'         => (int) $row['workflow_id'],
					'id'                  => $node['id'],
					'app'                 => $node['data']['app'] ?? '',
					'graph_node'          => $node,
				];

				foreach ( self::resolve_hooks( $node ) as $hook ) {
					$map[ $hook ][] = $entry;
				}
			}
		}//end foreach

		return $map;
	}

	/**
	 * Every WP hook a trigger node listens on, for code outside this class (the
	 * Test Trigger listener) that needs the same answer the trigger map uses.
	 *
	 * @param array<string,mixed> $node
	 * @return string[]
	 */
	public static function hooks_for( array $node ): array {
		return self::resolve_hooks( $node );
	}

	/**
	 * Every WP hook a trigger node listens on. Uses the stored data.hook when
	 * present; falls back to the integration's get_triggers() definition so that
	 * workflows created before the hook was persisted still fire correctly.
	 *
	 * A trigger may declare an array of hooks when the same event reaches it by
	 * more than one route (ACF's user-meta updates arrive as either
	 * updated_user_meta or added_user_meta). Those used to be returned as an
	 * array and then silently dropped by the is_string() guard in
	 * dispatch_active_triggers(), so the trigger never registered at all.
	 *
	 * @return string[]
	 */
	private static function resolve_hooks( array $node ): array {
		$hook = $node['data']['hook'] ?? null;

		if ( ! $hook ) {
			$app   = strtolower( $node['data']['app'] ?? '' );
			$event = $node['data']['event'] ?? '';

			if ( ! $app || ! $event ) {
				return [];
			}

			$integration = IntegrationLoader::get( $app );
			if ( ! $integration ) {
				return [];
			}

			$triggers = $integration::get_triggers();
			$hook     = $triggers[ $event ]['hook'] ?? null;
		}

		$hooks = [];
		foreach ( (array) $hook as $candidate ) {
			if ( is_string( $candidate ) && '' !== $candidate ) {
				$hooks[] = $candidate;
			}
		}

		return array_values( array_unique( $hooks ) );
	}
}
