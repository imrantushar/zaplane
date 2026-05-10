<?php

namespace Zaplane\Framework\Cloud;

use Zaplane\Models\Workflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pushes locally-defined Zaplane workflows to a paired cloud workspace.
 *
 * The sync model is "plugin pushes a complete picture of its workflows".
 * Cloud upserts each row keyed by (site_id, plugin_workflow_id), and
 * pauses any cloud-side plugin-origin workflows that the plugin no
 * longer reports.
 *
 * Triggered at three points:
 *   - On pair (initial sync of everything)
 *   - On a 5-minute AS recurring action (drift correction)
 *   - Manually via `wp zaplane sync-workflows` (CLI, future)
 *
 * Cloud-origin workflows in the cloud are never touched — only the
 * plugin's own workflows roundtrip through this class.
 */
class WorkflowSync {

	public const HOOK     = 'zaplane_cloud_sync_workflows';
	public const GROUP    = 'zaplane';
	public const INTERVAL = 300; // 5 minutes

	public static function bootstrap(): void {
		add_action( self::HOOK, [ self::class, 'sync_all' ] );
		add_action( 'init', [ self::class, 'sync_schedule' ], 26 );
	}

	public static function sync_schedule(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}
		$has = as_has_scheduled_action( self::HOOK, [], self::GROUP );
		if ( Bridge::is_paired() && ! $has ) {
			as_schedule_recurring_action(
				time() + 60,
				self::INTERVAL,
				self::HOOK,
				[],
				self::GROUP
			);
		} elseif ( ! Bridge::is_paired() && $has ) {
			as_unschedule_all_actions( self::HOOK, [], self::GROUP );
		}
	}

	/**
	 * @return array{ok: bool, error?: string, count?: int, response?: array}
	 */
	public static function sync_all(): array {
		if ( ! Bridge::is_paired() ) {
			return [ 'ok' => false, 'error' => 'not_paired' ];
		}

		$state    = Bridge::get_state();
		$site_id  = (int) $state['site_id'];
		$endpoint = rtrim( $state['cloud_url'], '/' ) . '/api/sites/' . $site_id . '/workflows/sync';

		$workflows = self::collect_workflows();

		$res = HttpClient::post_signed(
			$endpoint,
			[ 'workflows' => $workflows ],
			$site_id,
			(string) $state['site_secret'],
			30
		);

		update_option( 'zaplane_cloud_workflow_sync_last', [
			'ok'          => (bool) $res['ok'],
			'error'       => $res['error'] ?? '',
			'count'       => count( $workflows ),
			'finished_at' => time(),
		], false );

		if ( ! $res['ok'] ) {
			throw new \RuntimeException( 'Workflow sync failed: ' . $res['error'] );
		}

		return [
			'ok'       => true,
			'count'    => count( $workflows ),
			'response' => $res['body'],
		];
	}

	/**
	 * Returns the workflow definitions in the cloud-friendly shape.
	 */
	public static function collect_workflows(): array {
		$out = [];

		try {
			$workflows = Workflow::all();
		} catch ( \Throwable $e ) {
			return $out;
		}

		foreach ( $workflows as $wf ) {
			$version = method_exists( $wf, 'activeVersion' ) ? $wf->activeVersion() : null;
			if ( ! $version ) {
				continue;
			}

			$graph = method_exists( $version, 'getGraph' ) ? $version->getGraph() : null;
			if ( ! is_array( $graph ) || empty( $graph['nodes'] ) ) {
				continue;
			}

			$out[] = [
				'plugin_workflow_id' => (int) $wf->id,
				'name'               => (string) ( $wf->title ?? $wf->name ?? 'Workflow #' . $wf->id ),
				'trigger_event_type' => self::extract_trigger_event( $graph ),
				'status'             => self::normalize_status( $wf->status ?? 'active' ),
				'graph'              => self::normalize_graph( $graph ),
				'version_hash'       => substr( hash( 'sha256', wp_json_encode( $graph ) ), 0, 32 ),
			];
		}

		return $out;
	}

	/**
	 * Find the trigger node and return its WP hook name. The plugin stores
	 * the hook in `data.hook` (see Query::get_active_workflows_for_event).
	 * Returns null if no hook is wired up — the cloud will store the
	 * workflow but never match it.
	 */
	private static function extract_trigger_event( array $graph ): ?string {
		foreach ( $graph['nodes'] ?? [] as $node ) {
			if ( ( $node['type'] ?? '' ) !== 'trigger' ) {
				continue;
			}
			$hook = $node['data']['hook'] ?? null;
			if ( is_string( $hook ) && '' !== $hook ) {
				return $hook;
			}
			// Fall back to data.event if the integration uses that field.
			$event = $node['data']['event'] ?? null;
			if ( is_string( $event ) && '' !== $event ) {
				return $event;
			}
		}
		return null;
	}

	private static function normalize_status( $status ): string {
		$s = is_string( $status ) ? strtolower( $status ) : 'active';
		return in_array( $s, [ 'active', 'paused', 'draft' ], true ) ? $s : 'active';
	}

	/**
	 * The cloud expects `{nodes: [...], edges: [...]}`. Plugin's graph is
	 * already in that shape but may include extra fields the cloud
	 * tolerates. We pass it through unchanged for now.
	 */
	private static function normalize_graph( array $graph ): array {
		return [
			'nodes' => array_values( $graph['nodes'] ?? [] ),
			'edges' => array_values( $graph['edges'] ?? [] ),
		];
	}

	public static function get_last_sync(): array {
		$last = get_option( 'zaplane_cloud_workflow_sync_last', [] );
		return is_array( $last ) ? $last : [];
	}
}
