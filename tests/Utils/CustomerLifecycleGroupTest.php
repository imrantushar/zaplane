<?php

namespace Zaplane\Tests\Utils;

use Zaplane\Authoring\Catalog;
use Zaplane\Authoring\GraphValidator;
use Zaplane\Database\Seeders\CustomerLifecycleGroupSeeder;
use Zaplane\Services\RecipeGroupBuilder;
use Zaplane\Tests\TestCase;

class CustomerLifecycleGroupTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Catalog::flush();
	}

	/**
	 * Every way the setup can be answered: each workflow, and each option, on or off.
	 *
	 * @return \Generator<int,array<string,mixed>>
	 */
	private function everySetup( array $group ): \Generator {
		$switches = [];
		foreach ( RecipeGroupBuilder::workflows( $group ) as $workflow ) {
			$switches[] = [ $workflow['key'] ];
			foreach ( $workflow['options'] as $option ) {
				$switches[] = [ $workflow['key'], $option['key'] ];
			}
		}

		for ( $mask = 0; $mask < ( 1 << count( $switches ) ); $mask++ ) {
			$given = [
				'workflows' => [],
				'options'   => [],
			];

			foreach ( $switches as $bit => $switch ) {
				$on = (bool) ( $mask & ( 1 << $bit ) );

				if ( 1 === count( $switch ) ) {
					$given['workflows'][ $switch[0] ] = $on;
				} else {
					$given['options'][ $switch[0] ][ $switch[1] ] = $on;
				}
			}

			if ( in_array( true, $given['workflows'], true ) ) {
				yield $given;
			}
		}
	}

	public function test_every_setup_builds_workflows_whose_steps_all_resolve(): void {
		$group = CustomerLifecycleGroupSeeder::definition();
		$built = 0;

		foreach ( $this->everySetup( $group ) as $given ) {
			$answers = RecipeGroupBuilder::answers( $group, $given );

			foreach ( RecipeGroupBuilder::build( $group, $answers ) as $workflow ) {
				$graph = $workflow['graph'];
				$ids   = array_column( $graph['nodes'], 'id' );
				$label = $workflow['key'] . ' in ' . wp_json_encode( $given );

				$this->assertSame( [], RecipeGroupBuilder::dangling_references( $graph ), $label );
				$this->assertStringNotContainsString( '{{setup.', (string) wp_json_encode( $graph ), $label );

				foreach ( $graph['edges'] as $edge ) {
					$this->assertContains( $edge['source'], $ids, $label );
					$this->assertContains( $edge['target'], $ids, $label );
				}

				$built++;
			}
		}

		$this->assertGreaterThan( 0, $built );
	}

	public function test_every_workflow_passes_the_validator_with_its_options_on_and_off(): void {
		$group = CustomerLifecycleGroupSeeder::definition();

		foreach ( [ true, false ] as $on ) {
			$given = [
				'workflows' => [],
				'options'   => [],
			];

			foreach ( RecipeGroupBuilder::workflows( $group ) as $workflow ) {
				$given['workflows'][ $workflow['key'] ] = true;
				foreach ( $workflow['options'] as $option ) {
					$given['options'][ $workflow['key'] ][ $option['key'] ] = $on;
				}
			}

			$answers = RecipeGroupBuilder::answers( $group, $given );

			foreach ( RecipeGroupBuilder::build( $group, $answers ) as $workflow ) {
				$report = GraphValidator::check( $workflow['graph'] );

				$this->assertSame( [], $report['errors'], $workflow['key'] . ( $on ? ' with its options on' : ' with its options off' ) );
			}
		}
	}

	public function test_the_setup_values_reach_the_trigger_the_coupon_and_the_email(): void {
		$group   = CustomerLifecycleGroupSeeder::definition();
		$answers = RecipeGroupBuilder::answers(
			$group,
			[
				'workflows' => [
					'abandoned_cart'   => false,
					'thank_you_coupon' => false,
					'feedback_request' => false,
					'win_back'         => true,
					'birthday_coupon'  => false,
				],
				'values'    => [
					'coupon_percent' => 25,
					'inactive_days'  => '90',
				],
			]
		);

		$nodes = RecipeGroupBuilder::build( $group, $answers )[0]['graph']['nodes'];

		$this->assertSame( '90', $nodes[0]['data']['config']['days'] );
		$this->assertSame( '25', $nodes[3]['data']['config']['amount'] );
		$this->assertStringContainsString( '25% off', $nodes[4]['data']['config']['body'] );
	}
}
