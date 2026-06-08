<?php
/**
 * End-to-end recipe runner.
 *
 * Unlike RecipeRunner::fire() (which calls resolve_trigger directly), this
 * inserts a REAL active workflow, fires the integration's actual WordPress hook,
 * and lets the Zaplane engine run it — creating real Run + NodeRun log records,
 * exactly like production. It then asserts against those logs and tears the
 * temporary workflow/run down again.
 *
 * Requires the dependency plugin to be active and loaded for this request, so
 * the CLI runs it inside a fresh subprocess (see RecipeCommand).
 *
 * @package Zaplane
 */

namespace Zaplane\Testing;

use Zaplane\Framework\Core\IntegrationLoader;
use Zaplane\Models\Run;
use Zaplane\Models\NodeRun;
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RecipeE2eRunner {

	/** Safety cap on the node-run drain loop. */
	protected const MAX_DRAIN = 200;

	/**
	 * @param array $recipe Decoded recipe (node.kind must be 'trigger').
	 * @param array $opts   [ 'keep_workflow' => bool ]
	 */
	public static function run( array $recipe, array $opts = [] ): RecipeResult {
		$name        = (string) ( $recipe['name'] ?? 'unnamed' );
		$integration = (string) ( $recipe['integration'] ?? '' );
		$result      = new RecipeResult( $name, $integration );

		if ( '' === $integration ) {
			return $result->abort( 'Recipe is missing the "integration" slug.' );
		}

		$node = $recipe['node'] ?? [];
		if ( 'trigger' !== ( $node['kind'] ?? '' ) ) {
			return $result->abort( 'E2E mode requires a trigger recipe (node.kind = "trigger").' );
		}

		$instance = IntegrationLoader::get( $integration );
		if ( ! $instance ) {
			return $result->abort( "Unknown integration: {$integration}" );
		}
		$class = get_class( $instance );

		$event = (string) ( $node['event'] ?? '' );
		$hook  = $class::get_triggers()[ $event ]['hook'] ?? '';
		if ( '' === $hook ) {
			return $result->abort( "Trigger '{$event}' on '{$integration}' declares no WordPress hook to fire." );
		}

		$workflow = null;
		$run      = null;
		try {
			// 1. Seed data (factory or action) and resolve {{vars}}.
			$vars   = RecipeRunner::seed_vars( $recipe );
			$config = RecipeRunner::interpolate_value( (array) ( $node['config'] ?? [] ), $vars );
			$input  = array_values( RecipeRunner::interpolate_value( (array) ( $node['input'] ?? [] ), $vars ) );

			// 2. Insert a real, active workflow: trigger -> optional action chain.
			$graph    = self::build_graph( $integration, $event, $hook, $config, (array) ( $node['then'] ?? [] ) );
			$workflow = self::create_workflow( $integration, $event, $graph );
			$result->log_lines[] = "workflow #{$workflow->id} inserted ({$integration}.{$event}, hook: {$hook})";

			// 3. Register the engine's trigger hooks (boot() skips this under WP-CLI), then fire.
			$automation = \Zaplane::init()->container->get( 'automation' );
			$automation->dispatch_active_triggers();

			do_action_ref_array( $hook, $input );

			// 4. Locate the run the engine created for this workflow.
			$run = Run::where( 'workflow_id', $workflow->id )->orderBy( 'id', 'desc' )->first();
			if ( ! $run ) {
				$result->fail( 'Trigger fired but the engine created no run (resolve_trigger filtered it out — check config/input).' );
				return self::finish( $result, $workflow, $run, $opts );
			}
			$result->log_lines[] = "run #{$run->id} started";

			// 5. Drain pending node runs synchronously through the real engine path.
			$dispatched = self::drain( $automation, $run->id );

			// 6. Read the logs back.
			$run = Run::find( $run->id );
			self::record_node_logs( $result, $run->id );
			$result->log_lines[] = "run #{$run->id} finished: {$run->status}";

			// 7. Assert: run completed, no failed node, trigger payload matches expect.
			if ( 'completed' !== $run->status ) {
				$result->fail( "Run finished with status '{$run->status}', expected 'completed'." );
			}

			$trigger_run = NodeRun::where( 'run_id', $run->id )
				->where( 'node_key', 1 )
				->orderBy( 'id', 'asc' )
				->first();
			$payload = $trigger_run ? $trigger_run->getOutput() : false;

			RecipeRunner::evaluate( $result, 'trigger', (array) ( $recipe['expect'] ?? [] ), $payload );

			self::cancel_orphan_actions( $dispatched );
		} catch ( RecipeSkip $e ) {
			$result->skip( $e->getMessage() );
		} catch ( \Throwable $e ) {
			$result->abort( 'E2E error: ' . $e->getMessage() );
		}

		return self::finish( $result, $workflow, $run, $opts );
	}

	/** Build a numeric-id graph: trigger node #1 -> optional action nodes #2.. */
	/**
	 * Build a graph in the exact shape the Zaplane editor stores and renders:
	 * numeric string ids, a `type` of trigger/action (the editor re-derives
	 * data.action from it on load), a `position` (React Flow requires it), and
	 * `data` carrying app/event/icon/name/label/config so the node card renders.
	 *
	 * Mirrors the frontend's mapNodesForBackend()/mapGraphFromBackend() contract.
	 */
	protected static function build_graph( string $integration, string $event, string $hook, array $config, array $then ): array {
		$nodes = [
			[
				'id'       => '1',
				'type'     => 'trigger',
				'position' => [ 'x' => 400, 'y' => 140 ],
				'data'     => self::node_data( $integration, 'trigger', $event, $config, $hook ),
			],
		];
		$edges = [];

		$prev = 1;
		foreach ( $then as $i => $action ) {
			$id        = (string) ( $i + 2 );
			$app       = $action['app'] ?? $integration;
			$nodes[]   = [
				'id'       => $id,
				'type'     => 'action',
				'position' => [ 'x' => 400, 'y' => 140 + ( (int) $id - 1 ) * 180 ],
				'data'     => self::node_data( $app, 'action', $action['event'] ?? '', (array) ( $action['config'] ?? [] ) ),
			];
			$edges[]   = [
				'id'     => "e{$prev}-{$id}",
				'source' => (string) $prev,
				'target' => $id,
			];
			$prev = (int) $id;
		}

		return [
			'nodes' => $nodes,
			'edges' => $edges,
		];
	}

	/** Build a node's `data` using the integration's own metadata (icon/name/label). */
	protected static function node_data( string $slug, string $kind, string $event, array $config, string $hook = '' ): array {
		$instance = IntegrationLoader::get( $slug );
		$class    = $instance ? get_class( $instance ) : null;

		$name  = $class ? $class::get_name() : ucfirst( $slug );
		$icon  = $class ? $class::get_icon() : '';
		$defs  = $class ? ( 'trigger' === $kind ? $class::get_triggers() : $class::get_actions() ) : [];
		$label = $defs[ $event ]['label'] ?? $event;

		$data = [
			'app'    => $slug,
			'event'  => $event,
			'name'   => $name,
			'icon'   => $icon,
			'label'  => $label,
			'config' => (object) $config,
		];

		if ( 'trigger' === $kind ) {
			$data['hook'] = $hook ?: ( $defs[ $event ]['hook'] ?? '' );
		}

		return $data;
	}

	protected static function create_workflow( string $integration, string $event, array $graph ): Workflow {
		$icons = [];
		foreach ( $graph['nodes'] as $n ) {
			if ( ! empty( $n['data']['icon'] ) ) {
				$icons[] = $n['data']['icon'];
			}
		}

		$workflow = Workflow::create(
			[
				'user_id'           => get_current_user_id() ?: 1,
				'title'             => "[recipe-e2e] {$integration}.{$event}",
				'status'            => 'active',
				'layout'            => 'TB',
				'integration_icons' => array_values( array_unique( $icons ) ),
			]
		);

		WorkflowVersion::create(
			[
				'workflow_id' => $workflow->id,
				'graph_json'  => $graph,
				'graph_hash'  => hash( 'sha256', wp_json_encode( $graph ) ),
				'is_active'   => 1,
			]
		);

		return $workflow;
	}

	/** Dispatch pending node runs until none remain. @return int[] dispatched node-run ids. */
	protected static function drain( $automation, int $run_id ): array {
		$dispatched = [];

		for ( $i = 0; $i < self::MAX_DRAIN; $i++ ) {
			$pending = NodeRun::where( 'run_id', $run_id )
				->where( 'status', 'pending' )
				->fresh()
				->orderBy( 'id', 'asc' )
				->get();

			if ( 0 === count( $pending ) ) {
				break;
			}

			foreach ( $pending as $nodeRun ) {
				$dispatched[] = (int) $nodeRun->id;
				$automation->dispatch_node_run( (int) $nodeRun->id );
			}
		}

		return $dispatched;
	}

	protected static function record_node_logs( RecipeResult $result, int $run_id ): void {
		$nodeRuns = NodeRun::where( 'run_id', $run_id )->orderBy( 'id', 'asc' )->fresh()->get();

		foreach ( $nodeRuns as $nr ) {
			$meta  = is_array( $nr->node_meta_json ) ? $nr->node_meta_json : [];
			$label = trim( ( $meta['app'] ?? '?' ) . '.' . ( $meta['event'] ?? '?' ), '.' );
			$out   = wp_json_encode( $nr->getOutput() );
			if ( is_string( $out ) && strlen( $out ) > 200 ) {
				$out = substr( $out, 0, 200 ) . '…';
			}
			$result->log_lines[] = sprintf( '  node #%s (%s) [%s] -> %s', $nr->node_key, $label, $nr->status, $out );
		}
	}

	protected static function cancel_orphan_actions( array $node_run_ids ): void {
		if ( ! function_exists( 'as_unschedule_action' ) ) {
			return;
		}
		foreach ( $node_run_ids as $id ) {
			as_unschedule_action( 'zaplane_execute_node_run', [ 'node_run_id' => $id ], 'zaplane' );
		}
	}

	/** Tear down the temp workflow + run unless the caller asked to keep them. */
	protected static function finish( RecipeResult $result, ?Workflow $workflow, ?Run $run, array $opts ): RecipeResult {
		if ( ! empty( $opts['keep_workflow'] ) ) {
			if ( $workflow ) {
				// Pause it so it stays visible in the UI but won't fire on real events.
				$workflow->status = 'paused';
				$workflow->save();
				$result->log_lines[] = "kept workflow #{$workflow->id} (paused — inspect in the UI)";
			}
			return $result->finalize();
		}

		try {
			if ( $run ) {
				NodeRun::where( 'run_id', $run->id )->delete();
				$run->delete();
			}
			if ( $workflow ) {
				WorkflowVersion::where( 'workflow_id', $workflow->id )->delete();
				$workflow->delete();
			}
		} catch ( \Throwable $e ) {
			$result->log_lines[] = 'cleanup warning: ' . $e->getMessage();
		}

		return $result->finalize();
	}
}
