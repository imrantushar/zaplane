<?php

namespace Zaplane\Tests\Utils;

use Zaplane\Tests\TestCase;
use Zaplane\Services\RecipeGroupBuilder;

class RecipeGroupBuilderTest extends TestCase {

	private function node( string $id, string $type = 'action', array $config = [] ): array {
		return [
			'id'   => $id,
			'type' => $type,
			'data' => [
				'app'    => 'trigger' === $type ? 'woocommerce' : 'gemcrm',
				'event'  => 'trigger' === $type ? 'order_status_completed' : 'send_email',
				'config' => $config,
			],
		];
	}

	private function edge( string $source, string $target, array $extra = [] ): array {
		return array_merge(
			[
				'id'     => 'e' . $source . '-' . $target,
				'source' => $source,
				'target' => $target,
			],
			$extra
		);
	}

	/**
	 * Two workflows: one on by default, with a follow-up that can be switched
	 * off, and one off by default.
	 */
	private function group(): array {
		return [
			'values'    => [
				[
					'key'     => 'percent',
					'type'    => 'number',
					'label'   => 'Coupon discount',
					'default' => 15,
					'min'     => 1,
					'max'     => 100,
				],
			],
			'workflows' => [
				[
					'key'     => 'thanks',
					'title'   => 'Thank buyers',
					'graph'   => [
						'nodes' => [
							$this->node( '1', 'trigger' ),
							$this->node( '2', 'action', [ 'body' => 'Take {{setup.percent}}% off' ] ),
							$this->node( '3' ),
							$this->node( '4', 'action', [ 'body' => 'Still {{ setup.percent }}% off' ] ),
						],
						'edges' => [ $this->edge( '1', '2' ), $this->edge( '2', '3' ), $this->edge( '3', '4' ) ],
					],
					'options' => [
						[
							'key'   => 'follow_up',
							'label' => 'Follow up',
							'nodes' => [ '3', '4' ],
						],
					],
				],
				[
					'key'     => 'birthday',
					'title'   => 'Birthday coupon',
					'default' => false,
					'graph'   => [
						'nodes' => [ $this->node( '1', 'trigger' ), $this->node( '2' ) ],
						'edges' => [ $this->edge( '1', '2' ) ],
					],
				],
			],
		];
	}

	/** @return array<int,string> */
	private function nodeIds( array $graph ): array {
		return array_column( $graph['nodes'], 'id' );
	}

	public function test_answers_left_out_take_the_group_defaults(): void {
		$answers = RecipeGroupBuilder::answers( $this->group(), [] );

		$this->assertSame( [ 'thanks' => true, 'birthday' => false ], $answers['workflows'] );
		$this->assertSame( [ 'follow_up' => true ], $answers['options']['thanks'] );
		$this->assertSame( '15', $answers['values']['percent'] );
	}

	public function test_only_the_workflows_switched_on_are_built(): void {
		$group   = $this->group();
		$answers = RecipeGroupBuilder::answers( $group, [ 'workflows' => [ 'thanks' => false, 'birthday' => 'true' ] ] );

		$this->assertSame( [ 'birthday' ], array_column( RecipeGroupBuilder::build( $group, $answers ), 'key' ) );
	}

	public function test_an_option_switched_off_takes_its_steps_out(): void {
		$group   = $this->group();
		$answers = RecipeGroupBuilder::answers( $group, [ 'options' => [ 'thanks' => [ 'follow_up' => false ] ] ] );

		$graph = RecipeGroupBuilder::build( $group, $answers )[0]['graph'];

		$this->assertSame( [ '1', '2' ], $this->nodeIds( $graph ) );
		$this->assertSame( [ 'e1-2' ], array_column( $graph['edges'], 'id' ) );
	}

	public function test_taking_out_steps_in_the_middle_joins_the_steps_either_side(): void {
		$graph = [
			'nodes' => [ $this->node( '1', 'trigger' ), $this->node( '2' ), $this->node( '3' ), $this->node( '4' ) ],
			'edges' => [
				$this->edge( '1', '2', [ 'sourceHandle' => 'yes' ] ),
				$this->edge( '2', '3' ),
				$this->edge( '3', '4', [ 'targetHandle' => 'in' ] ),
			],
		];

		$result = RecipeGroupBuilder::without_nodes( $graph, [ '2', '3' ] );

		$this->assertSame( [ '1', '4' ], $this->nodeIds( $result ) );
		$this->assertSame(
			[
				[
					'id'           => 'e1-yes-4',
					'source'       => '1',
					'target'       => '4',
					'sourceHandle' => 'yes',
					'targetHandle' => 'in',
				],
			],
			$result['edges']
		);
	}

	public function test_a_sub_node_is_not_joined_to_the_step_after_a_removed_agent(): void {
		$graph = [
			'nodes' => [ $this->node( '1', 'trigger' ), $this->node( '2' ), $this->node( '3' ), $this->node( '4' ) ],
			'edges' => [
				$this->edge( '1', '2' ),
				$this->edge( '2', '3' ),
				$this->edge( '4', '2', [ 'sourceHandle' => 'sub_out', 'targetHandle' => 'ai_model' ] ),
			],
		];

		$result = RecipeGroupBuilder::without_nodes( $graph, [ '2' ] );

		$this->assertSame( [ 'e1-3' ], array_column( $result['edges'], 'id' ) );
	}

	public function test_setup_values_are_written_into_the_steps(): void {
		$group   = $this->group();
		$answers = RecipeGroupBuilder::answers( $group, [ 'values' => [ 'percent' => '20' ] ] );

		$graph = RecipeGroupBuilder::build( $group, $answers )[0]['graph'];

		$this->assertSame( 'Take 20% off', $graph['nodes'][1]['data']['config']['body'] );
		$this->assertSame( 'Still 20% off', $graph['nodes'][3]['data']['config']['body'] );
	}

	public function test_a_number_outside_its_range_is_refused(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Coupon discount must be between 1 and 100.' );

		RecipeGroupBuilder::answers( $this->group(), [ 'values' => [ 'percent' => 150 ] ] );
	}

	public function test_a_value_that_no_chosen_step_reads_is_not_checked(): void {
		// Only the birthday workflow is on, and none of its steps read the discount.
		$answers = RecipeGroupBuilder::answers(
			$this->group(),
			[
				'workflows' => [ 'thanks' => false, 'birthday' => true ],
				'values'    => [ 'percent' => 'lots' ],
			]
		);

		$this->assertSame( '15', $answers['values']['percent'] );
	}

	public function test_picking_nothing_is_refused(): void {
		$this->expectException( \InvalidArgumentException::class );

		RecipeGroupBuilder::answers( $this->group(), [ 'workflows' => [ 'thanks' => false ] ] );
	}

	public function test_value_usage_says_which_option_reads_a_value(): void {
		$usage = RecipeGroupBuilder::value_usage( $this->group() );

		$this->assertSame(
			[
				[ 'workflow' => 'thanks', 'option' => null ],
				[ 'workflow' => 'thanks', 'option' => 'follow_up' ],
			],
			$usage['percent']
		);
	}

	public function test_it_finds_a_step_reading_one_that_was_taken_out(): void {
		$graph = [
			'nodes' => [
				$this->node( '1', 'trigger' ),
				$this->node( '3', 'action', [ 'body' => '{{2.coupon.code}} for {{1.email}}' ] ),
			],
			'edges' => [ $this->edge( '1', '3' ) ],
		];

		$this->assertSame(
			[
				[
					'node_id' => '3',
					'missing' => '2',
				],
			],
			RecipeGroupBuilder::dangling_references( $graph )
		);
	}

	public function test_steps_follow_the_lines_and_name_the_option_that_adds_each(): void {
		$steps = RecipeGroupBuilder::steps( RecipeGroupBuilder::workflows( $this->group() )[0] );

		$this->assertSame( [ '1', '2', '3', '4' ], array_column( $steps, 'id' ) );
		$this->assertSame( [ true, false, false, false ], array_map( fn( $step ) => $step['trigger'], $steps ) );
		$this->assertSame( [ null, null, 'follow_up', 'follow_up' ], array_map( fn( $step ) => $step['option'], $steps ) );
	}

	public function test_steps_are_in_run_order_whatever_order_the_graph_lists_them_in(): void {
		$workflow = RecipeGroupBuilder::workflows(
			[
				'workflows' => [
					[
						'key'   => 'shuffled',
						'graph' => [
							'nodes' => [ $this->node( '3' ), $this->node( '1', 'trigger' ), $this->node( '2' ) ],
							'edges' => [ $this->edge( '2', '3' ), $this->edge( '1', '2' ) ],
						],
					],
				],
			]
		)[0];

		$this->assertSame( [ '1', '2', '3' ], array_column( RecipeGroupBuilder::steps( $workflow ), 'id' ) );
	}
}
