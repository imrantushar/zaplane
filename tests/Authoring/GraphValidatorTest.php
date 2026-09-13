<?php

namespace Zaplane\Tests\Authoring;

use PHPUnit\Framework\TestCase;
use Zaplane\Authoring\Catalog;
use Zaplane\Authoring\GraphValidator;

class GraphValidatorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Catalog::flush();
	}

	/** A graph that should pass cleanly, used as the base for negative cases. */
	private function validGraph(): array {
		return [
			'nodes' => [
				[
					'id'       => '1',
					'type'     => 'trigger',
					'position' => [
						'x' => 80,
						'y' => 200,
					],
					'data'     => [
						'app'    => 'storeengine',
						'event'  => 'product_purchased',
						'config' => [],
					],
				],
				[
					'id'       => '2',
					'type'     => 'action',
					'position' => [
						'x' => 420,
						'y' => 200,
					],
					'data'     => [
						'app'    => 'storeengine',
						'event'  => 'update_order_status',
						'config' => [
							'order_id'     => '{{1.order_id}}',
							'order_status' => 'processing',
						],
					],
				],
			],
			'edges' => [
				[
					'id'     => 'e1-2',
					'source' => '1',
					'target' => '2',
				],
			],
		];
	}

	/** @return array<int,string> */
	private function messages( array $report, string $bucket = 'errors' ): array {
		return array_column( $report[ $bucket ], 'message' );
	}

	private function assertReportMentions( array $report, string $needle, string $bucket = 'errors' ): void {
		$joined = implode( ' || ', $this->messages( $report, $bucket ) );
		$this->assertStringContainsString( $needle, $joined );
	}

	/**
	 * @test
	 */
	public function it_accepts_a_well_formed_graph(): void {
		$report = GraphValidator::check( $this->validGraph() );

		$this->assertTrue(
			$report['valid'],
			'Expected a valid graph, got: ' . implode( ' || ', $this->messages( $report ) )
		);
		$this->assertSame( [], $report['errors'] );
	}

	/**
	 * @test
	 */
	public function it_rejects_a_non_numeric_node_id(): void {
		$graph = $this->validGraph();
		$graph['nodes'][0]['id']      = 'trigger_1';
		$graph['edges'][0]['source']  = 'trigger_1';

		$report = GraphValidator::check( $graph );

		$this->assertFalse( $report['valid'] );
		$this->assertReportMentions( $report, 'positive whole number' );
	}

	/**
	 * @test
	 */
	public function it_rejects_a_zero_node_id(): void {
		$graph = $this->validGraph();
		$graph['nodes'][0]['id']     = '0';
		$graph['edges'][0]['source'] = '0';

		$this->assertFalse( GraphValidator::check( $graph )['valid'] );
	}

	/**
	 * @test
	 */
	public function it_rejects_duplicate_node_ids(): void {
		$graph = $this->validGraph();
		$graph['nodes'][1]['id']     = '1';
		$graph['edges'][0]['target'] = '1';

		$report = GraphValidator::check( $graph );

		$this->assertFalse( $report['valid'] );
		$this->assertReportMentions( $report, 'Duplicate node id' );
	}

	/**
	 * @test
	 */
	public function it_rejects_an_unknown_app(): void {
		$graph = $this->validGraph();
		$graph['nodes'][1]['data']['app'] = 'definitely-not-an-app';

		$report = GraphValidator::check( $graph );

		$this->assertFalse( $report['valid'] );
		$this->assertReportMentions( $report, 'Unknown app' );
	}

	/**
	 * @test
	 */
	public function it_rejects_an_event_that_belongs_to_the_other_bucket(): void {
		$graph = $this->validGraph();
		// A real trigger key, used on an action node.
		$graph['nodes'][1]['data']['event'] = 'product_purchased';

		$report = GraphValidator::check( $graph );

		$this->assertFalse( $report['valid'] );
		$this->assertReportMentions( $report, 'is not a action of app' );
	}

	/**
	 * @test
	 */
	public function it_rejects_a_missing_required_field(): void {
		$graph = $this->validGraph();
		unset( $graph['nodes'][1]['data']['config']['order_id'] );

		$report = GraphValidator::check( $graph );

		$this->assertFalse( $report['valid'] );
		$this->assertReportMentions( $report, 'Required field "order_id"' );
	}

	/**
	 * @test
	 */
	public function it_rejects_a_select_value_outside_its_options(): void {
		$graph = $this->validGraph();
		$graph['nodes'][1]['data']['config']['order_status'] = 'shipped';

		$report = GraphValidator::check( $graph );

		$this->assertFalse( $report['valid'] );
		$this->assertReportMentions( $report, 'not one of' );
	}

	/**
	 * @test
	 */
	public function it_allows_an_expression_where_a_fixed_option_is_expected(): void {
		$graph = $this->validGraph();
		$graph['nodes'][1]['data']['config']['order_status'] = '{{1.status}}';

		$this->assertTrue( GraphValidator::check( $graph )['valid'] );
	}

	/**
	 * @test
	 */
	public function it_requires_at_least_one_trigger(): void {
		$noTrigger                    = $this->validGraph();
		$noTrigger['nodes'][0]['type'] = 'action';
		$noTrigger['nodes'][0]['data']['event'] = 'update_order_status';
		$noTrigger['nodes'][0]['data']['config'] = [
			'order_id'     => '1',
			'order_status' => 'processing',
		];

		$report = GraphValidator::check( $noTrigger );
		$this->assertFalse( $report['valid'] );
		$this->assertReportMentions( $report, 'no trigger node' );
	}

	/**
	 * @test
	 */
	public function it_accepts_a_workflow_that_starts_from_several_triggers(): void {
		$graph            = $this->validGraph();
		$graph['nodes'][] = [
			'id'       => '3',
			'type'     => 'trigger',
			'position' => [
				'x' => 80,
				'y' => 360,
			],
			'data'     => [
				'app'    => 'storeengine',
				'event'  => 'product_purchased',
				'config' => [],
			],
		];
		$graph['edges'][] = [
			'id'     => 'e3-2',
			'source' => '3',
			'target' => '2',
		];

		$report = GraphValidator::check( $graph );

		$this->assertTrue( $report['valid'], implode( ' | ', $this->messages( $report ) ) );

		// Advisory, not blocking: the two triggers are identical, and the step reads
		// {{1.order_id}}, which trigger 3 does not match to trigger 1.
		$codes = array_column( $report['warnings'], 'code' );
		$this->assertContains( 'trigger_duplicate', $codes );
		$this->assertContains( 'trigger_field_gap', $codes );
	}

	/**
	 * @test
	 */
	public function it_rejects_an_edge_pointing_at_a_node_that_does_not_exist(): void {
		$graph = $this->validGraph();
		$graph['edges'][0]['target'] = '99';

		$report = GraphValidator::check( $graph );

		$this->assertFalse( $report['valid'] );
		$this->assertReportMentions( $report, 'is not a node in this graph' );
	}

	/**
	 * @test
	 */
	public function it_rejects_an_inbound_edge_on_the_trigger(): void {
		$graph   = $this->validGraph();
		$graph['edges'][] = [
			'id'     => 'e2-1',
			'source' => '2',
			'target' => '1',
		];

		$report = GraphValidator::check( $graph );

		$this->assertFalse( $report['valid'] );
		$this->assertReportMentions( $report, 'trigger node cannot have an incoming edge' );
	}

	/**
	 * @test
	 */
	public function it_rejects_an_empty_or_malformed_graph(): void {
		$this->assertFalse( GraphValidator::check( [] )['valid'] );
		$this->assertFalse( GraphValidator::check( [ 'nodes' => [], 'edges' => [] ] )['valid'] );
		$this->assertFalse( GraphValidator::check( [ 'nodes' => 'nope', 'edges' => [] ] )['valid'] );
	}

	/**
	 * @test
	 */
	public function an_unlinked_connection_is_a_warning_not_an_error(): void {
		$graph            = $this->validGraph();
		$graph['nodes'][] = [
			'id'       => '3',
			'type'     => 'action',
			'position' => [
				'x' => 760,
				'y' => 200,
			],
			'data'     => [
				'app'    => 'slack',
				'event'  => 'send_message',
				'config' => [
					'channel' => 'general',
					'text'    => 'hi',
				],
			],
		];
		$graph['edges'][] = [
			'id'     => 'e2-3',
			'source' => '2',
			'target' => '3',
		];

		$report = GraphValidator::check( $graph );

		$this->assertTrue(
			$report['valid'],
			'Expected valid, got: ' . implode( ' || ', $this->messages( $report ) )
		);
		$this->assertReportMentions( $report, 'needs a connection', 'warnings' );
	}

	/**
	 * @test
	 */
	public function findings_carry_a_machine_readable_code(): void {
		$graph            = $this->validGraph();
		$graph['nodes'][] = [
			'id'       => '3',
			'type'     => 'action',
			'position' => [
				'x' => 760,
				'y' => 200,
			],
			'data'     => [
				'app'    => 'slack',
				'event'  => 'send_message',
				'config' => [
					'channel' => 'general',
					'text'    => 'hi',
				],
			],
		];
		$graph['edges'][] = [
			'id'     => 'e2-3',
			'source' => '2',
			'target' => '3',
		];

		$report = GraphValidator::check( $graph );

		// set_status() singles this one out to block activation, so it has to be
		// identifiable without matching on the message text.
		$this->assertContains( 'missing_connection', array_column( $report['warnings'], 'code' ) );

		foreach ( array_merge( $report['errors'], $report['warnings'] ) as $finding ) {
			$this->assertArrayHasKey( 'code', $finding );
			$this->assertNotEmpty( $finding['code'] );
		}
	}

	/**
	 * @test
	 */
	public function a_broken_graph_still_reports_a_code_on_every_error(): void {
		$graph = $this->validGraph();
		$graph['nodes'][0]['id']     = 'trigger_1';
		$graph['edges'][0]['source'] = 'trigger_1';

		$report = GraphValidator::check( $graph );

		$this->assertFalse( $report['valid'] );
		foreach ( $report['errors'] as $error ) {
			$this->assertNotEmpty( $error['code'] );
		}
	}

	/**
	 * @test
	 */
	public function an_orphan_action_is_a_warning_not_an_error(): void {
		$graph            = $this->validGraph();
		$graph['nodes'][] = [
			'id'       => '3',
			'type'     => 'action',
			'position' => [
				'x' => 760,
				'y' => 400,
			],
			'data'     => [
				'app'    => 'storeengine',
				'event'  => 'update_order_status',
				'config' => [
					'order_id'     => '1',
					'order_status' => 'processing',
				],
			],
		];

		$report = GraphValidator::check( $graph );

		$this->assertTrue( $report['valid'] );
		$this->assertReportMentions( $report, 'never run', 'warnings' );
	}

	/**
	 * @test
	 */
	public function an_unknown_config_key_is_a_warning_not_an_error(): void {
		$graph = $this->validGraph();
		$graph['nodes'][1]['data']['config']['totally_made_up'] = 'x';

		$report = GraphValidator::check( $graph );

		$this->assertTrue( $report['valid'] );
		$this->assertReportMentions( $report, 'is not in this event\'s schema', 'warnings' );
	}
}
