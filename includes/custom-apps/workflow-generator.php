<?php

namespace Zaplane\CustomApps;

use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scaffolds a starter workflow when a custom app is created.
 *
 * The graph is intentionally minimal — the app's first trigger (if any) wired to
 * its first action (if any) — so the user lands on a ready-to-edit draft that
 * already references their new app instead of a blank canvas.
 *
 * The graph is built straight from the manifest array (not via IntegrationLoader)
 * because the loader's registry is cached earlier in the same request, before the
 * new app is persisted, so it would not yet know this slug.
 */
class WorkflowGenerator {

	/**
	 * Create a draft workflow for a freshly-saved manifest.
	 *
	 * @param array<string,mixed> $manifest The saved custom app manifest.
	 * @return int|null The new workflow id, or null when nothing was generated.
	 */
	public static function generate_for_manifest( array $manifest ): ?int {
		$slug = ManifestValidator::sanitize_slug( (string) ( $manifest['slug'] ?? '' ) );
		if ( '' === $slug ) {
			return null;
		}

		$graph = self::build_graph( $slug, $manifest );

		// Nothing to scaffold if the app has neither a trigger nor an action.
		if ( empty( $graph['nodes'] ) ) {
			return null;
		}

		$name = ! empty( $manifest['name'] ) ? (string) $manifest['name'] : ucfirst( $slug );
		$icon = (string) ( $manifest['icon'] ?? '' );

		$workflow = Workflow::create( [
			'user_id'           => get_current_user_id(),
			// translators: %s is the custom app name.
			'title'             => sprintf( __( '%s Workflow', 'zaplane' ), $name ),
			'status'            => 'draft',
			'layout'            => 'LR',
			'integration_icons' => '' !== $icon ? [ $icon ] : [],
		] );

		WorkflowVersion::create( [
			'workflow_id'    => $workflow->id,
			'graph_json'     => $graph,
			'graph_hash'     => hash( 'sha256', wp_json_encode( $graph ) ),
			'is_active'      => 1,
			'version_number' => null,
		] );

		return (int) $workflow->id;
	}

	/**
	 * Build a { nodes, edges } graph: first trigger → first action.
	 *
	 * @param array<string,mixed> $manifest
	 * @return array{nodes:array<int,array>,edges:array<int,array>}
	 */
	protected static function build_graph( string $slug, array $manifest ): array {
		$name = ! empty( $manifest['name'] ) ? (string) $manifest['name'] : ucfirst( $slug );
		$icon = (string) ( $manifest['icon'] ?? '' );
		$kind = 'local' === ( $manifest['kind'] ?? 'http' ) ? 'local' : 'http';

		$trigger = self::first_event( $manifest['triggers'] ?? [] );
		$action  = self::first_event( $manifest['actions'] ?? [] );

		$nodes = [];
		$edges = [];
		$nextId = 1;

		$triggerId = null;
		if ( null !== $trigger ) {
			$triggerId = (string) $nextId++;
			$key       = (string) $trigger['key'];

			// Local apps listen on the real hook declared in the manifest; HTTP apps
			// use the synthetic hook the poller/webhook dispatch on.
			// @see \Zaplane\Integrations\CustomAppBase::trigger_hook()
			$hook = 'local' === $kind
				? (string) ( $trigger['hook'] ?? '' )
				: 'zaplane_ca_' . $slug . '_' . $key;

			$nodes[] = [
				'id'       => $triggerId,
				'type'     => 'trigger',
				'position' => [ 'x' => 250, 'y' => 50 ],
				'data'     => [
					'app'    => $slug,
					'event'  => $key,
					'hook'   => $hook,
					'label'  => $name,
					'icon'   => $icon,
					'name'   => (string) ( $trigger['label'] ?? $key ),
					'config' => (object) [],
				],
			];
		}

		if ( null !== $action ) {
			$actionId = (string) $nextId++;
			$key      = (string) $action['key'];

			$nodes[] = [
				'id'       => $actionId,
				'type'     => 'action',
				'position' => [ 'x' => 250, 'y' => null === $triggerId ? 50 : 200 ],
				'data'     => [
					'app'    => $slug,
					'event'  => $key,
					'label'  => $name,
					'icon'   => $icon,
					'name'   => (string) ( $action['label'] ?? $key ),
					'config' => (object) [],
				],
			];

			if ( null !== $triggerId ) {
				$edges[] = [
					'id'     => 'e' . $triggerId . '-' . $actionId,
					'source' => $triggerId,
					'target' => $actionId,
				];
			}
		}

		return [
			'nodes' => $nodes,
			'edges' => $edges,
		];
	}

	/**
	 * The first manifest event ({ key, label, ... }) that has a usable key.
	 *
	 * @param mixed $events
	 * @return array<string,mixed>|null
	 */
	protected static function first_event( $events ): ?array {
		if ( ! is_array( $events ) ) {
			return null;
		}
		foreach ( $events as $event ) {
			if ( is_array( $event ) && ! empty( $event['key'] ) ) {
				return $event;
			}
		}
		return null;
	}
}
