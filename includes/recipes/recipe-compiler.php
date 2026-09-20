<?php

namespace Zaplane\Recipes;

use Zaplane\Authoring\Catalog;
use Zaplane\Models\Recipe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a recipe written as a list of steps into what the recipes table stores.
 *
 * A recipe names each step by its app and event, such as `gemcrm.send_email`, and
 * gives its settings. Everything else a workflow needs is worked out here: the step
 * ids, where each step sits on the canvas, the lines between them, and each step's
 * label, icon and trigger hook, from the app catalog. See Registry for the shape.
 */
final class RecipeCompiler {

	/** The keys an AI Agent step gives its sub-nodes under, and the handle each plugs into. */
	const SUB_NODES = [
		'model'  => 'ai_model',
		'memory' => 'ai_memory',
		'tools'  => 'ai_tool',
	];

	/**
	 * A recipe as the recipes table stores it.
	 *
	 * @param array<string,mixed> $recipe
	 * @return array{type:string,title:string,description:string,blueprint:string,integration_icons:string}
	 * @throws \InvalidArgumentException When the recipe can't be built.
	 */
	public static function record( array $recipe ): array {
		$blueprint = self::blueprint( $recipe );
		$icons     = isset( $recipe['icons'] ) ? array_values( (array) $recipe['icons'] ) : self::icons( $blueprint );

		return [
			'type'              => isset( $recipe['workflows'] ) ? Recipe::TYPE_GROUP : Recipe::TYPE_WORKFLOW,
			'title'             => trim( (string) $recipe['title'] ),
			'description'       => (string) ( $recipe['description'] ?? '' ),
			'blueprint'         => (string) wp_json_encode( $blueprint ),
			'integration_icons' => (string) wp_json_encode( $icons ),
		];
	}

	/**
	 * A recipe's blueprint, in the shape a group recipe has. A recipe with steps
	 * instead of workflows is a group of one, under the key `workflow`.
	 *
	 * @param array<string,mixed> $recipe
	 * @return array{folder:string,values:array<int,array<string,mixed>>,workflows:array<int,array<string,mixed>>}
	 * @throws \InvalidArgumentException When the recipe can't be built.
	 */
	public static function blueprint( array $recipe ): array {
		$title = trim( (string) ( $recipe['title'] ?? '' ) );
		if ( '' === $title ) {
			throw new \InvalidArgumentException( 'A recipe needs a title.' );
		}

		$workflows = isset( $recipe['workflows'] )
			? array_values( (array) $recipe['workflows'] )
			: [ array_merge( $recipe, [ 'key' => 'workflow' ] ) ];

		if ( ! $workflows ) {
			throw new \InvalidArgumentException( 'A group recipe needs at least one workflow.' );
		}

		$compiled = [];
		foreach ( $workflows as $workflow ) {
			$workflow = self::workflow( (array) $workflow );

			if ( isset( $compiled[ $workflow['key'] ] ) ) {
				throw new \InvalidArgumentException( esc_html( sprintf( 'Two workflows share the key "%s".', $workflow['key'] ) ) );
			}

			$compiled[ $workflow['key'] ] = $workflow;
		}

		return [
			'folder'    => (string) ( $recipe['folder'] ?? $title ),
			'values'    => array_values( (array) ( $recipe['values'] ?? [] ) ),
			'workflows' => array_values( $compiled ),
		];
	}

	/**
	 * A workflow's graph from its steps, and the ids of the steps each option adds.
	 *
	 * Triggers come first, stacked on the left, and each leads to the first action.
	 * Each action leads to the next. An AI Agent's model, memory and tools sit under
	 * it, each wired into its handle.
	 *
	 * @param array<int,array<string,mixed>> $steps
	 * @return array{0: array{nodes: array<int,array<string,mixed>>, edges: array<int,array<string,string>>}, 1: array<string,array<int,string>>}
	 * @throws \InvalidArgumentException When the steps don't make a workflow.
	 */
	public static function graph( array $steps ): array {
		$triggers = [];
		$actions  = [];

		foreach ( array_values( $steps ) as $step ) {
			if ( isset( $step['trigger'] ) ) {
				if ( $actions ) {
					throw new \InvalidArgumentException( 'Triggers come before the actions they start.' );
				}
				$triggers[] = (array) $step;
			} elseif ( isset( $step['action'] ) ) {
				$actions[] = (array) $step;
			} else {
				throw new \InvalidArgumentException( 'Each step is a trigger or an action, such as [ \'action\' => \'gemcrm.send_email\' ].' );
			}
		}

		if ( ! $triggers ) {
			throw new \InvalidArgumentException( 'A workflow starts with a trigger.' );
		}

		$nodes   = [];
		$edges   = [];
		$options = [];
		$next    = 1;
		$count   = count( $triggers );
		$sources = [];

		foreach ( $triggers as $index => $step ) {
			$id        = (string) $next++;
			$nodes[]   = self::node( $id, 'trigger', (string) $step['trigger'], $step, 80, 200 + ( 2 * $index - $count + 1 ) * 90 );
			$sources[] = $id;
			self::add_to_option( $options, $step, $id );
		}

		$sub_nodes = [];
		foreach ( $actions as $index => $step ) {
			$id      = (string) $next++;
			$x       = 420 + $index * 340;
			$nodes[] = self::node( $id, 'action', (string) $step['action'], $step, $x, 200 );
			self::add_to_option( $options, $step, $id );

			foreach ( $sources as $source ) {
				$edges[] = [
					'id'     => 'e' . $source . '-' . $id,
					'source' => $source,
					'target' => $id,
				];
			}
			$sources = [ $id ];

			foreach ( self::SUB_NODES as $key => $handle ) {
				$list = 'tools' === $key ? (array) ( $step[ $key ] ?? [] ) : ( isset( $step[ $key ] ) ? [ $step[ $key ] ] : [] );

				foreach ( $list as $sub_node ) {
					$sub_nodes[] = [ $id, $x, $handle, (array) $sub_node, $step ];
				}
			}
		}//end foreach

		$slots = [];
		foreach ( $sub_nodes as list( $agent, $x, $handle, $sub_node, $owner ) ) {
			if ( ! isset( $sub_node['action'] ) ) {
				throw new \InvalidArgumentException( 'An agent\'s model, memory and tools are actions, such as [ \'action\' => \'ai.generate_response\' ].' );
			}

			$slots[ $agent ] = ( $slots[ $agent ] ?? -1 ) + 1;

			$id      = (string) $next++;
			$nodes[] = self::node( $id, 'action', (string) $sub_node['action'], $sub_node, $x - 120 + $slots[ $agent ] * 160, 420 );
			$edges[] = [
				'id'           => 'e' . $id . '-' . $agent . '-' . substr( $handle, 3 ),
				'source'       => $id,
				'sourceHandle' => 'sub_out',
				'target'       => $agent,
				'targetHandle' => $handle,
			];

			// A sub-node comes and goes with its agent.
			self::add_to_option( $options, $owner, $id );
		}

		return [
			[
				'nodes' => $nodes,
				'edges' => $edges,
			],
			$options,
		];
	}

	/**
	 * The icons of a blueprint's apps, in the order its steps first use them.
	 *
	 * @param array<string,mixed> $blueprint
	 * @return array<int,string>
	 */
	public static function icons( array $blueprint ): array {
		$icons = [];

		foreach ( (array) ( $blueprint['workflows'] ?? [] ) as $workflow ) {
			foreach ( (array) ( $workflow['graph']['nodes'] ?? [] ) as $node ) {
				$icon = (string) ( $node['data']['icon'] ?? '' );

				if ( '' !== $icon && ! in_array( $icon, $icons, true ) ) {
					$icons[] = $icon;
				}
			}
		}

		return $icons;
	}

	/**
	 * @param array<string,mixed> $workflow
	 * @return array<string,mixed>
	 * @throws \InvalidArgumentException When the workflow can't be built.
	 */
	private static function workflow( array $workflow ): array {
		$key = (string) ( $workflow['key'] ?? '' );
		if ( ! preg_match( '/^[a-z0-9_]+$/', $key ) ) {
			throw new \InvalidArgumentException( esc_html( sprintf( 'A workflow key is lowercase letters, digits and underscores; got "%s".', $key ) ) );
		}

		$declared = [];
		foreach ( (array) ( $workflow['options'] ?? [] ) as $option ) {
			$declared[ (string) ( $option['key'] ?? '' ) ] = (array) $option;
		}

		if ( isset( $workflow['graph'] ) ) {
			// A graph written out by hand is used as it is, and its options name their steps' ids.
			$graph   = (array) $workflow['graph'];
			$options = array_values( $declared );
		} else {
			list( $graph, $option_steps ) = self::graph( (array) ( $workflow['steps'] ?? [] ) );

			foreach ( array_keys( $option_steps ) as $option_key ) {
				if ( ! isset( $declared[ $option_key ] ) ) {
					throw new \InvalidArgumentException( esc_html( sprintf( 'A step of "%1$s" belongs to the option "%2$s", which the workflow doesn\'t list.', $key, $option_key ) ) );
				}
			}

			$options = [];
			foreach ( $declared as $option_key => $option ) {
				$options[] = array_merge( $option, [ 'nodes' => $option_steps[ $option_key ] ?? [] ] );
			}
		}

		return [
			'key'         => $key,
			'title'       => (string) ( $workflow['title'] ?? $key ),
			'description' => (string) ( $workflow['description'] ?? '' ),
			'default'     => ! array_key_exists( 'default', $workflow ) || (bool) $workflow['default'],
			'layout'      => (string) ( $workflow['layout'] ?? 'LR' ),
			'graph'       => $graph,
			'options'     => $options,
		];
	}

	/**
	 * A step as the canvas stores it, with what the app catalog knows about it.
	 *
	 * @param array<string,mixed> $step
	 * @return array<string,mixed>
	 * @throws \InvalidArgumentException When the step isn't named by its app and event.
	 */
	private static function node( string $id, string $type, string $name, array $step, int $x, int $y ): array {
		list( $app, $event ) = array_pad( explode( '.', $name, 2 ), 2, '' );
		if ( '' === $app || '' === $event ) {
			throw new \InvalidArgumentException( esc_html( sprintf( 'Name a step by its app and event, such as "gemcrm.send_email"; got "%s".', $name ) ) );
		}

		$entry      = Catalog::entry( $app ) ?? [];
		$capability = (array) ( $entry[ 'trigger' === $type ? 'triggers' : 'actions' ][ $event ] ?? [] );

		$data = [
			'app'   => $app,
			'event' => $event,
		];

		if ( 'trigger' === $type ) {
			$data['hook'] = (string) ( $step['hook'] ?? Catalog::primary_hook( $capability ) );
		}

		$data['label']  = (string) ( $step['label'] ?? $entry['name'] ?? $app );
		$data['icon']   = (string) ( $step['icon'] ?? $entry['icon'] ?? $app );
		$data['name']   = (string) ( $step['name'] ?? $capability['label'] ?? $event );
		$data['config'] = (array) ( $step['config'] ?? [] );

		return [
			'id'       => $id,
			'type'     => $type,
			'position' => [
				'x' => $x,
				'y' => $y,
			],
			'data'     => $data,
		];
	}

	/**
	 * @param array<string,array<int,string>> $options
	 * @param array<string,mixed>             $step
	 */
	private static function add_to_option( array &$options, array $step, string $id ): void {
		if ( isset( $step['option'] ) && '' !== (string) $step['option'] ) {
			$options[ (string) $step['option'] ][] = $id;
		}
	}
}
