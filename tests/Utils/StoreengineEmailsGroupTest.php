<?php

namespace Zaplane\Tests\Utils;

use Zaplane\Authoring\Catalog;
use Zaplane\Authoring\GraphValidator;
use Zaplane\Database\Seeders\StoreengineEmailsGroupSeeder;
use Zaplane\Services\RecipeGroupBuilder;
use Zaplane\Tests\TestCase;

class StoreengineEmailsGroupTest extends TestCase {

	private const VALUES = [
		'store_email'    => 'orders@example.com',
		'review_days'    => 7,
		'coupon_percent' => 10,
	];

	protected function setUp(): void {
		parent::setUp();
		Catalog::flush();
	}

	/**
	 * For each workflow, a setup that switches on that one alone, in every way its
	 * options can be set. The workflows don't share steps, so this covers what
	 * trying every combination of all of them would.
	 *
	 * @return \Generator<string,array<string,mixed>>
	 */
	private function setupsOfEachWorkflow( array $group ): \Generator {
		$workflows = RecipeGroupBuilder::workflows( $group );

		foreach ( $workflows as $workflow ) {
			$options = array_column( $workflow['options'], 'key' );

			for ( $mask = 0; $mask < ( 1 << count( $options ) ); $mask++ ) {
				$given = [
					'workflows' => array_fill_keys( array_column( $workflows, 'key' ), false ),
					'options'   => [],
					'values'    => self::VALUES,
				];

				$given['workflows'][ $workflow['key'] ] = true;

				foreach ( $options as $bit => $option ) {
					$given['options'][ $workflow['key'] ][ $option ] = (bool) ( $mask & ( 1 << $bit ) );
				}

				yield $workflow['key'] => $given;
			}
		}
	}

	public function test_each_workflow_builds_with_its_options_on_or_off_and_every_step_resolves(): void {
		$group = StoreengineEmailsGroupSeeder::definition();
		$built = [];

		foreach ( $this->setupsOfEachWorkflow( $group ) as $key => $given ) {
			$workflows = RecipeGroupBuilder::build( $group, RecipeGroupBuilder::answers( $group, $given ) );
			$label     = $key . ' with ' . wp_json_encode( $given['options'] );

			$this->assertCount( 1, $workflows, $label );

			$graph = $workflows[0]['graph'];
			$ids   = array_column( $graph['nodes'], 'id' );

			$this->assertSame( [], RecipeGroupBuilder::dangling_references( $graph ), $label );
			$this->assertStringNotContainsString( '{{setup.', (string) wp_json_encode( $graph ), $label );

			foreach ( $graph['edges'] as $edge ) {
				$this->assertContains( $edge['source'], $ids, $label );
				$this->assertContains( $edge['target'], $ids, $label );
			}

			$built[ $key ] = true;
		}

		$this->assertCount( 14, $built );
	}

	public function test_every_workflow_passes_the_validator_with_its_options_on_and_off(): void {
		$group = StoreengineEmailsGroupSeeder::definition();

		foreach ( [ true, false ] as $on ) {
			$given = [
				'workflows' => [],
				'options'   => [],
				'values'    => self::VALUES,
			];

			foreach ( RecipeGroupBuilder::workflows( $group ) as $workflow ) {
				$given['workflows'][ $workflow['key'] ] = true;
				foreach ( $workflow['options'] as $option ) {
					$given['options'][ $workflow['key'] ][ $option['key'] ] = $on;
				}
			}

			foreach ( RecipeGroupBuilder::build( $group, RecipeGroupBuilder::answers( $group, $given ) ) as $workflow ) {
				$report = GraphValidator::check( $workflow['graph'] );

				$this->assertSame( [], $report['errors'], $workflow['key'] . ( $on ? ' with its options on' : ' with its options off' ) );
			}
		}
	}

	public function test_the_setup_values_reach_the_store_alert_the_wait_and_the_coupon(): void {
		$group = StoreengineEmailsGroupSeeder::definition();
		$none  = array_fill_keys( array_column( RecipeGroupBuilder::workflows( $group ), 'key' ), false );

		$answers = RecipeGroupBuilder::answers(
			$group,
			[
				'workflows' => array_merge(
					$none,
					[
						'new_order_alert' => true,
						'review_request'  => true,
						'order_cancelled' => true,
					]
				),
				'options'   => [ 'order_cancelled' => [ 'come_back_coupon' => true ] ],
				'values'    => [
					'store_email'    => 'orders@example.com',
					'review_days'    => '14',
					'coupon_percent' => 25,
				],
			]
		);

		$graphs = array_column( RecipeGroupBuilder::build( $group, $answers ), 'graph', 'key' );

		$this->assertSame( 'orders@example.com', $graphs['new_order_alert']['nodes'][1]['data']['config']['custom_email'] );
		$this->assertSame( '14', $graphs['review_request']['nodes'][1]['data']['config']['amount'] );
		$this->assertSame( 'Wait 14 Days', $graphs['review_request']['nodes'][1]['data']['name'] );
		$this->assertSame( '25', $graphs['order_cancelled']['nodes'][4]['data']['config']['amount'] );
		$this->assertStringContainsString( '25% off', $graphs['order_cancelled']['nodes'][5]['data']['config']['body'] );
	}

	public function test_a_store_alert_needs_an_address(): void {
		$group = StoreengineEmailsGroupSeeder::definition();

		$this->expectException( \InvalidArgumentException::class );

		RecipeGroupBuilder::answers(
			$group,
			[
				'workflows' => [ 'new_order_alert' => true ],
				'values'    => [ 'store_email' => '' ],
			]
		);
	}

	public function test_the_refund_email_reads_whichever_refund_trigger_fired(): void {
		$group   = StoreengineEmailsGroupSeeder::definition();
		$none    = array_fill_keys( array_column( RecipeGroupBuilder::workflows( $group ), 'key' ), false );
		$answers = RecipeGroupBuilder::answers(
			$group,
			[
				'workflows' => array_merge( $none, [ 'order_refund' => true ] ),
				'values'    => self::VALUES,
			]
		);

		$graph = RecipeGroupBuilder::build( $group, $answers )[0]['graph'];

		$this->assertSame( [ 'trigger', 'trigger', 'action' ], array_column( $graph['nodes'], 'type' ) );
		$this->assertEqualsCanonicalizing( [ '1', '2' ], array_column( $graph['edges'], 'source' ) );
		$this->assertSame( [ '3', '3' ], array_column( $graph['edges'], 'target' ) );
		$this->assertStringContainsString( '{{trigger.order_number}}', $graph['nodes'][2]['data']['config']['subject'] );
	}

	public function test_each_workflow_that_takes_over_an_email_names_one_storeengine_sends(): void {
		$keys     = array_column( RecipeGroupBuilder::workflows( StoreengineEmailsGroupSeeder::definition() ), 'key' );
		$handover = StoreengineEmailsGroupSeeder::HANDOVER;

		foreach ( $handover as $workflow => $email ) {
			$this->assertContains( $workflow, $keys );
			$this->assertMatchesRegularExpression( '/^[a-z_]+\.(customer|admin)$/', $email, $workflow );
		}

		$this->assertSame( array_values( array_unique( $handover ) ), array_values( $handover ), 'Two workflows take over the same email.' );
		$this->assertSame( [ 'review_request' ], array_values( array_diff( $keys, array_keys( $handover ) ) ), 'Only the review request is an email StoreEngine lacks.' );
	}

	public function test_no_step_is_read_by_its_number(): void {
		$group = StoreengineEmailsGroupSeeder::definition();

		foreach ( $this->setupsOfEachWorkflow( $group ) as $key => $given ) {
			$graph = RecipeGroupBuilder::build( $group, RecipeGroupBuilder::answers( $group, $given ) )[0]['graph'];

			$this->assertDoesNotMatchRegularExpression( '/\{\{\s*\d+\./', (string) wp_json_encode( $graph ), $key . ' with ' . wp_json_encode( $given['options'] ) );
		}
	}

	/**
	 * A bare {{field}} reads the step just before, and Create Coupon passes on only
	 * its own fields, so every {{code}} has to come right after a coupon step.
	 */
	public function test_each_bare_field_is_read_right_after_the_step_that_gives_it(): void {
		$group = StoreengineEmailsGroupSeeder::definition();
		$reads = 0;

		foreach ( $this->setupsOfEachWorkflow( $group ) as $key => $given ) {
			$graph = RecipeGroupBuilder::build( $group, RecipeGroupBuilder::answers( $group, $given ) )[0]['graph'];
			$nodes = array_column( $graph['nodes'], null, 'id' );

			foreach ( $graph['nodes'] as $node ) {
				preg_match_all( '/\{\{\s*([A-Za-z_]+)\s*\}\}/', (string) wp_json_encode( $node['data'] ), $matches );

				foreach ( $matches[1] as $field ) {
					$sources = array_values( array_column( array_filter( $graph['edges'], static fn( $edge ) => $edge['target'] === $node['id'] ), 'source' ) );
					$label   = $key . ': step ' . $node['id'] . ' reads {{' . $field . '}}';

					$this->assertSame( 'code', $field, $label );
					$this->assertCount( 1, $sources, $label );
					$this->assertSame( 'create_coupon', $nodes[ $sources[0] ]['data']['event'] ?? '', $label );
					$reads++;
				}
			}
		}

		$this->assertGreaterThan( 0, $reads );
	}
}
