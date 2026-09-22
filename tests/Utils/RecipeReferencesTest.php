<?php

namespace Zaplane\Tests\Utils;

use Zaplane\Recipes\RecipeCompiler;
use Zaplane\Recipes\ShippedRecipes;
use Zaplane\Services\RecipeGroupBuilder;
use Zaplane\Tests\TestCase;

/**
 * The recipes that ship with Zaplane never read a step by its number.
 *
 * A step reads the trigger as {{trigger.*}}, and a field of an earlier step by its
 * bare name. A bare name is a field of the step's input, so it only works when the
 * field reaches the step: from the step just before, or through steps that pass
 * their input on.
 */
class RecipeReferencesTest extends TestCase {

	/**
	 * The fields a step adds to its output, as its integration's action returns them.
	 */
	private const GIVES = [
		'woocommerce.create_coupon'            => [ 'coupon' ],
		'woocommerce.get_products_by_category' => [ 'products' ],
		'storeengine.create_coupon'            => [ 'code' ],
		'knowledge.retrieve'                   => [ 'context' ],
		'ai-agent.run_agent'                   => [ 'reply' ],
		'ai.transcribe'                        => [ 'text' ],
		'wordpress.reply_comment'              => [ 'comment_id', 'parent_id' ],
		'inbox.receive_message'                => [ 'conversation_id', 'message_id' ],
	];

	/**
	 * Steps whose output carries their input on, so a field from before them is still
	 * there after them. A Wait resumes with what it was given.
	 */
	private const PASSES_ON = [
		'delay.wait',
		'filter.filter',
		'gemcrm.send_email',
		'memory.append',
		'knowledge.retrieve',
		'ai-agent.run_agent',
		'ai.transcribe',
	];

	/** Roots that name something other than a field of an earlier step. */
	private const OTHER_ROOTS = [ 'trigger', 'setup', 'wp', 'workflow', 'contact', 'unsubscribe_link', 'update_preferences_link' ];

	public function test_the_shipped_recipes_build_graphs_to_check(): void {
		$this->assertGreaterThan( 20, count( $this->shippedGraphs() ) );
	}

	public function test_no_step_is_read_by_its_number(): void {
		foreach ( $this->shippedGraphs() as $label => $graph ) {
			$this->assertDoesNotMatchRegularExpression( '/\{\{\s*\d+\./', (string) wp_json_encode( $graph ), $label );
		}
	}

	public function test_each_field_a_step_reads_by_name_reaches_it(): void {
		$reads = 0;

		foreach ( $this->shippedGraphs() as $label => $graph ) {
			foreach ( $graph['nodes'] as $node ) {
				preg_match_all( '/\{\{\s*([A-Za-z_][A-Za-z0-9_-]*)[^}]*\}\}/', (string) wp_json_encode( $node['data'] ?? [] ), $matches );

				foreach ( array_unique( $matches[1] ) as $root ) {
					if ( in_array( $root, self::OTHER_ROOTS, true ) ) {
						continue;
					}

					$this->assertTrue(
						$this->reaches( $graph, (string) $node['id'], $root ),
						$label . ': step ' . $node['id'] . ' reads {{' . $root . '}}, which no earlier step passes to it'
					);
					$reads++;
				}
			}
		}

		$this->assertGreaterThan( 0, $reads );
	}

	/**
	 * Whether a field of an earlier step reaches a step's input.
	 *
	 * @param array<string,mixed> $graph
	 */
	private function reaches( array $graph, string $step, string $field ): bool {
		$nodes = array_column( $graph['nodes'], null, 'id' );

		// A sub-node (a model, memory or tool wired into an AI agent) is read with its agent's data.
		foreach ( $graph['edges'] as $edge ) {
			if ( (string) $edge['source'] === $step && in_array( $edge['targetHandle'] ?? '', RecipeGroupBuilder::SUB_NODE_PORTS, true ) ) {
				$step = (string) $edge['target'];
				break;
			}
		}

		for ( $hops = 0; $hops < 50; $hops++ ) {
			$parents = [];
			foreach ( $graph['edges'] as $edge ) {
				if ( (string) $edge['target'] === $step && ! in_array( $edge['targetHandle'] ?? '', RecipeGroupBuilder::SUB_NODE_PORTS, true ) ) {
					$parents[] = (string) $edge['source'];
				}
			}

			if ( 1 !== count( $parents ) ) {
				return false;
			}

			$data  = $nodes[ $parents[0] ]['data'] ?? [];
			$event = ( $data['app'] ?? '' ) . '.' . ( $data['event'] ?? '' );

			if ( in_array( $field, self::GIVES[ $event ] ?? [], true ) ) {
				return true;
			}

			if ( ! in_array( $event, self::PASSES_ON, true ) ) {
				return false;
			}

			$step = $parents[0];
		}

		return false;
	}

	/**
	 * Every graph the shipped recipes can create: each workflow of each recipe, in
	 * every way its options can be set.
	 *
	 * @return array<string,array<string,mixed>> Label => graph.
	 */
	private function shippedGraphs(): array {
		$graphs = [];

		foreach ( ShippedRecipes::files() as $slug => $file ) {
			$graphs += $this->groupGraphs( $slug, RecipeCompiler::blueprint( require $file ) );
		}

		return $graphs;
	}

	/**
	 * @param array<string,mixed> $group
	 * @return array<string,array<string,mixed>>
	 */
	private function groupGraphs( string $label, array $group ): array {
		$values = [];
		foreach ( RecipeGroupBuilder::values( $group ) as $value ) {
			// A required address with no default, such as where store alerts go.
			$values[ $value['key'] ] = ( 'text' === $value['type'] && '' === (string) $value['default'] ) ? 'orders@example.com' : $value['default'];
		}

		$graphs    = [];
		$workflows = RecipeGroupBuilder::workflows( $group );

		foreach ( $workflows as $workflow ) {
			$options = array_column( $workflow['options'], 'key' );

			for ( $mask = 0; $mask < ( 1 << count( $options ) ); $mask++ ) {
				$given = [
					'workflows' => array_fill_keys( array_column( $workflows, 'key' ), false ),
					'options'   => [],
					'values'    => $values,
				];

				$given['workflows'][ $workflow['key'] ] = true;

				foreach ( $options as $bit => $option ) {
					$given['options'][ $workflow['key'] ][ $option ] = (bool) ( $mask & ( 1 << $bit ) );
				}

				$built = RecipeGroupBuilder::build( $group, RecipeGroupBuilder::answers( $group, $given ) );

				$graphs[ $label . ' [' . $workflow['key'] . ' ' . wp_json_encode( $given['options'] ) . ']' ] = $built[0]['graph'];
			}
		}

		return $graphs;
	}
}
