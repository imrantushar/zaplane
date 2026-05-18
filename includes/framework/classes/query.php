<?php

namespace Zaplane\Framework\Classes;

use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;
use Zaplane\Framework\Core\IntegrationLoader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Query {

	public static function get_active_trigger_events(): array {
		try {
			$activeWorkflows = Workflow::active();
		} catch ( \Exception $e ) {
			return [];
		}

		$events = [];

		foreach ( $activeWorkflows as $workflow ) {
			$version = $workflow->activeVersion();
			if ( ! $version ) {
				continue;
			}

			$graph = $version->getGraph();
			foreach ( $graph['nodes'] ?? [] as $node ) {
				if ( ( $node['type'] ?? '' ) !== 'trigger' ) {
					continue;
				}

				$hook = self::resolve_hook( $node );
				if ( $hook ) {
					$events[] = $hook;
				}
			}
		}
		return array_unique( $events );
	}

	public static function get_active_workflows_for_event( string $event ): array {
		try {
			$activeWorkflows = Workflow::active();
		} catch ( \Exception $e ) {
			return [];
		}

		$out = [];

		foreach ( $activeWorkflows as $workflow ) {
			$version = $workflow->activeVersion();
			if ( ! $version ) {
				continue;
			}

			$graph = $version->getGraph();
			foreach ( $graph['nodes'] ?? [] as $node ) {
				if ( ( $node['type'] ?? '' ) !== 'trigger' ) {
					continue;
				}

				if ( self::resolve_hook( $node ) === $event ) {
					$out[] = [
						'workflow_version_id' => $version->id,
						'workflow_id'         => $workflow->id,
						'id'                  => $node['id'],
						'app'                 => $node['data']['app'] ?? '',
						'graph_node'          => $node,
					];
				}
			}
		}

		return $out;
	}

	/**
	 * Returns the WP hook for a trigger node. Uses the stored data.hook when
	 * present; falls back to the integration's get_triggers() definition so
	 * that workflows created before the hook was persisted still fire correctly.
	 */
	private static function resolve_hook( array $node ): ?string {
		$hook = $node['data']['hook'] ?? null;
		if ( $hook ) {
			return $hook;
		}

		$app   = strtolower( $node['data']['app'] ?? '' );
		$event = $node['data']['event'] ?? '';

		if ( ! $app || ! $event ) {
			return null;
		}

		$integration = IntegrationLoader::get( $app );
		if ( ! $integration ) {
			return null;
		}

		$triggers = $integration::get_triggers();
		return $triggers[ $event ]['hook'] ?? null;
	}
}
