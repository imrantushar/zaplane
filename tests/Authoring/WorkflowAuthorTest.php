<?php

namespace Zaplane\Tests\Authoring;

use PHPUnit\Framework\TestCase;
use Zaplane\Authoring\Catalog;
use Zaplane\Authoring\GraphValidator;
use Zaplane\Authoring\WorkflowAuthor;

/**
 * Covers normalize() — the pure half. create()/save()/set_status() persist and
 * are exercised by the API-level tests.
 */
class WorkflowAuthorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Catalog::flush();
	}

	/** Intent only: no ids, no positions, no labels, no edges. */
	private function intentGraph(): array {
		return [
			'nodes' => [
				[
					'type' => 'trigger',
					'data' => [
						'app'   => 'storeengine',
						'event' => 'product_purchased',
					],
				],
				[
					'type' => 'action',
					'data' => [
						'app'    => 'storeengine',
						'event'  => 'update_order_status',
						'config' => [
							'order_id'     => '{{1.order_id}}',
							'order_status' => 'processing',
						],
					],
				],
			],
		];
	}

	/**
	 * @test
	 */
	public function it_assigns_sequential_numeric_ids(): void {
		$graph = WorkflowAuthor::normalize( $this->intentGraph() );

		$this->assertSame( [ '1', '2' ], array_column( $graph['nodes'], 'id' ) );
	}

	/**
	 * @test
	 */
	public function it_lays_nodes_out_left_to_right(): void {
		$graph = WorkflowAuthor::normalize( $this->intentGraph() );

		$this->assertSame( [ 'x' => 80, 'y' => 200 ], $graph['nodes'][0]['position'] );
		$this->assertSame( [ 'x' => 420, 'y' => 200 ], $graph['nodes'][1]['position'] );
	}

	/**
	 * @test
	 */
	public function it_chains_nodes_when_no_edges_are_given(): void {
		$graph = WorkflowAuthor::normalize( $this->intentGraph() );

		$this->assertCount( 1, $graph['edges'] );
		$this->assertSame( '1', $graph['edges'][0]['source'] );
		$this->assertSame( '2', $graph['edges'][0]['target'] );
		$this->assertSame( 'e1-2', $graph['edges'][0]['id'] );
	}

	/**
	 * @test
	 */
	public function it_fills_label_icon_and_name_from_the_manifest(): void {
		$graph = WorkflowAuthor::normalize( $this->intentGraph() );
		$data  = $graph['nodes'][0]['data'];

		$this->assertSame( 'StoreEngine', $data['name'] );
		$this->assertSame( 'storeengine.svg', $data['icon'] );
		$this->assertSame( 'Product Purchased', $data['label'] );
	}

	/**
	 * @test
	 */
	public function it_sets_the_trigger_hook_from_the_manifest_not_the_caller(): void {
		$intent = $this->intentGraph();
		$intent['nodes'][0]['data']['hook'] = 'something/made/up';

		$graph = WorkflowAuthor::normalize( $intent );

		$expected = Catalog::describe_capability( 'storeengine', 'trigger', 'product_purchased' )['hook'];

		$this->assertSame( $expected, $graph['nodes'][0]['data']['hook'] );
		$this->assertNotSame( 'something/made/up', $graph['nodes'][0]['data']['hook'] );
	}

	/**
	 * @test
	 */
	public function it_adds_a_null_connection_slot_for_apps_that_need_one(): void {
		$graph = WorkflowAuthor::normalize( [
			'nodes' => [
				[
					'type' => 'trigger',
					'data' => [
						'app'   => 'storeengine',
						'event' => 'product_purchased',
					],
				],
				[
					'type' => 'action',
					'data' => [
						'app'    => 'slack',
						'event'  => 'send_message',
						'config' => [
							'channel' => 'general',
							'text'    => 'hi',
						],
					],
				],
			],
		] );

		$this->assertArrayHasKey( 'connection_id', $graph['nodes'][1]['data'] );
		$this->assertNull( $graph['nodes'][1]['data']['connection_id'] );
		$this->assertArrayNotHasKey( 'connection_id', $graph['nodes'][0]['data'] );
	}

	/**
	 * @test
	 */
	public function it_renumbers_non_numeric_ids_and_rewrites_the_edges(): void {
		$graph = WorkflowAuthor::normalize( [
			'nodes' => [
				[
					'id'   => 'start',
					'type' => 'trigger',
					'data' => [
						'app'   => 'storeengine',
						'event' => 'product_purchased',
					],
				],
				[
					'id'   => 'set_status',
					'type' => 'action',
					'data' => [
						'app'    => 'storeengine',
						'event'  => 'update_order_status',
						'config' => [
							'order_id'     => '1',
							'order_status' => 'processing',
						],
					],
				],
			],
			'edges' => [
				[
					'id'     => 'edge-a',
					'source' => 'start',
					'target' => 'set_status',
				],
			],
		] );

		$this->assertSame( [ '1', '2' ], array_column( $graph['nodes'], 'id' ) );
		$this->assertSame( '1', $graph['edges'][0]['source'] );
		$this->assertSame( '2', $graph['edges'][0]['target'] );
		$this->assertTrue( GraphValidator::check( $graph )['valid'] );
	}

	/**
	 * @test
	 */
	public function it_leaves_usable_numeric_ids_and_supplied_positions_alone(): void {
		$graph = WorkflowAuthor::normalize( [
			'nodes' => [
				[
					'id'       => '7',
					'type'     => 'trigger',
					'position' => [
						'x' => 12,
						'y' => 34,
					],
					'data'     => [
						'app'   => 'storeengine',
						'event' => 'product_purchased',
					],
				],
				[
					'id'   => '9',
					'type' => 'action',
					'data' => [
						'app'    => 'storeengine',
						'event'  => 'update_order_status',
						'config' => [
							'order_id'     => '1',
							'order_status' => 'processing',
						],
					],
				],
			],
			'edges' => [
				[
					'id'     => 'e7-9',
					'source' => '7',
					'target' => '9',
				],
			],
		] );

		$this->assertSame( [ '7', '9' ], array_column( $graph['nodes'], 'id' ) );
		$this->assertSame( [ 'x' => 12, 'y' => 34 ], $graph['nodes'][0]['position'] );
		$this->assertSame( '7', $graph['edges'][0]['source'] );
	}

	/**
	 * @test
	 */
	public function it_emits_the_canonical_key_order(): void {
		$graph = WorkflowAuthor::normalize( $this->intentGraph() );

		$this->assertSame(
			[ 'id', 'type', 'position', 'data' ],
			array_keys( $graph['nodes'][0] )
		);
	}

	/**
	 * @test
	 */
	public function it_defaults_the_first_node_to_a_trigger_and_the_rest_to_actions(): void {
		$graph = WorkflowAuthor::normalize( [
			'nodes' => [
				[
					'data' => [
						'app'   => 'storeengine',
						'event' => 'product_purchased',
					],
				],
				[
					'data' => [
						'app'    => 'storeengine',
						'event'  => 'update_order_status',
						'config' => [
							'order_id'     => '1',
							'order_status' => 'processing',
						],
					],
				],
			],
		] );

		$this->assertSame( 'trigger', $graph['nodes'][0]['type'] );
		$this->assertSame( 'action', $graph['nodes'][1]['type'] );
	}

	/**
	 * @test
	 */
	public function a_normalized_intent_graph_passes_validation(): void {
		$report = GraphValidator::check( WorkflowAuthor::normalize( $this->intentGraph() ) );

		$this->assertTrue(
			$report['valid'],
			'Expected valid, got: ' . implode( ' || ', array_column( $report['errors'], 'message' ) )
		);
	}

	/**
	 * @test
	 */
	public function it_handles_an_empty_graph_without_fataling(): void {
		$this->assertSame(
			[
				'nodes' => [],
				'edges' => [],
			],
			WorkflowAuthor::normalize( [] )
		);
	}
}
