<?php

namespace Zaplane\Tests\Utils;

use Zaplane\Tests\TestCase;
use Zaplane\Framework\Classes\TriggerNodes;

class TriggerNodesTest extends TestCase {

	/**
	 * Two triggers leading into one step, which leads to a second step.
	 */
	private function graph( array $extraNodes = [] ): array {
		return [
			'nodes' => array_merge(
				[
					[
						'id'   => '1',
						'type' => 'trigger',
						'data' => [
							'app'   => 'woocommerce',
							'event' => 'order_status_completed',
							'name'  => 'WooCommerce',
						],
					],
					[
						'id'   => '2',
						'type' => 'trigger',
						'data' => [
							'app'   => 'webhook',
							'event' => 'catch_hook',
							'name'  => 'Webhook',
						],
					],
					[
						'id'   => '3',
						'type' => 'action',
						'data' => [
							'app'   => 'variable',
							'event' => 'set',
						],
					],
					[
						'id'   => '4',
						'type' => 'action',
						'data' => [
							'app'   => 'formatter',
							'event' => 'format_text',
						],
					],
				],
				$extraNodes
			),
			'edges' => [
				[
					'source' => '1',
					'target' => '3',
				],
				[
					'source' => '2',
					'target' => '3',
				],
				[
					'source' => '3',
					'target' => '4',
				],
			],
		];
	}

	public function test_all_returns_the_trigger_nodes_in_graph_order(): void {
		$this->assertSame( [ '1', '2' ], array_column( TriggerNodes::all( $this->graph() ), 'id' ) );
	}

	public function test_the_default_trigger_is_the_first_one(): void {
		$this->assertSame( '1', TriggerNodes::default_node( $this->graph() )['id'] );
	}

	public function test_the_default_trigger_is_the_manual_one_when_there_is_one(): void {
		$graph = $this->graph( [
			[
				'id'   => '5',
				'type' => 'trigger',
				'data' => [
					'app'   => 'manual',
					'event' => 'run_manually',
				],
			],
		] );

		$this->assertSame( '5', TriggerNodes::default_node( $graph )['id'] );
	}

	public function test_resolve_returns_the_named_trigger(): void {
		$this->assertSame( '2', TriggerNodes::resolve( $this->graph(), '2' )['id'] );
		$this->assertSame( '2', TriggerNodes::resolve( $this->graph(), 2 )['id'] );
	}

	public function test_resolve_does_not_fall_back_when_the_name_is_not_a_trigger(): void {
		$this->assertNull( TriggerNodes::resolve( $this->graph(), '3' ) );
		$this->assertNull( TriggerNodes::resolve( $this->graph(), '99' ) );
	}

	public function test_resolve_without_a_name_uses_the_default_trigger(): void {
		$this->assertSame( '1', TriggerNodes::resolve( $this->graph(), null )['id'] );
		$this->assertSame( '1', TriggerNodes::resolve( $this->graph(), '' )['id'] );
	}

	public function test_a_trigger_with_no_app_picked_is_not_configured(): void {
		$graph = $this->graph( [
			[
				'id'   => '5',
				'type' => 'trigger',
				'data' => [
					'app'  => 'Select an app',
					'icon' => 'plus',
				],
			],
		] );

		$this->assertSame( [ '1', '2' ], array_column( TriggerNodes::configured( $graph ), 'id' ) );
	}

	public function test_upstream_ids_reach_every_trigger_that_leads_to_a_node(): void {
		$this->assertSame( [ '1', '2' ], TriggerNodes::upstream_ids( $this->graph(), '4' ) );
		$this->assertSame( [], TriggerNodes::upstream_ids( $this->graph(), '1' ) );
	}

	public function test_downstream_ids_follow_edges_forward(): void {
		$this->assertSame( [ '3', '4' ], TriggerNodes::downstream_ids( $this->graph(), '2' ) );
	}

	public function test_number_counts_triggers_from_one(): void {
		$this->assertSame( 2, TriggerNodes::number( $this->graph(), '2' ) );
		$this->assertSame( 0, TriggerNodes::number( $this->graph(), '3' ) );
	}

	public function test_a_field_map_writes_matched_fields_over_the_payload(): void {
		$node = [
			'id'   => '2',
			'type' => 'trigger',
			'data' => [
				'field_map' => [
					'target' => '1',
					'fields' => [
						'billing_email'      => '{{customer.mail}}',
						'billing.first_name' => '{{2.name}}',
						'source'             => 'webhook',
						'items[].sku'        => '{{name}}',
						'unused'             => '',
					],
				],
			],
		];

		$mapped = TriggerNodes::apply_field_map( $node, [
			'customer' => [ 'mail' => 'ada@example.test' ],
			'name'     => 'Ada',
		] );

		$this->assertSame( 'ada@example.test', $mapped['billing_email'] );
		$this->assertSame( 'Ada', $mapped['billing']['first_name'] );
		$this->assertSame( 'webhook', $mapped['source'] );
		$this->assertSame( 'Ada', $mapped['name'], 'The payload keeps its own fields.' );
		$this->assertArrayNotHasKey( 'items[]', $mapped );
		$this->assertArrayNotHasKey( 'items', $mapped );
		$this->assertArrayNotHasKey( 'unused', $mapped );
	}

	public function test_without_a_field_map_the_payload_is_unchanged(): void {
		$payload = [ 'email' => 'ada@example.test' ];

		$this->assertSame( $payload, TriggerNodes::apply_field_map( [ 'id' => '2', 'data' => [] ], $payload ) );
	}

	public function test_trigger_holds_the_output_of_the_trigger_that_started_the_run(): void {
		$context = TriggerNodes::with_aliases( [ 2 => [ 'email' => 'ada@example.test' ] ], $this->graph(), '2' );

		$this->assertSame( [ 'email' => 'ada@example.test' ], $context['trigger'] );
		$this->assertArrayNotHasKey( 1, $context, 'Without a field map the other trigger stays empty.' );
	}

	public function test_a_matched_trigger_answers_with_the_starting_triggers_output(): void {
		$graph                                  = $this->graph();
		$graph['nodes'][1]['data']['field_map'] = [
			'target' => '1',
			'fields' => [ 'billing_email' => '{{email}}' ],
		];

		$context = TriggerNodes::with_aliases( [ 2 => [ 'billing_email' => 'ada@example.test' ] ], $graph, '2' );

		$this->assertSame( [ 'billing_email' => 'ada@example.test' ], $context[1] );
	}

	public function test_an_alias_never_replaces_a_trigger_that_ran(): void {
		$graph                                  = $this->graph();
		$graph['nodes'][1]['data']['field_map'] = [
			'target' => '1',
			'fields' => [ 'billing_email' => '{{email}}' ],
		];

		$context = TriggerNodes::with_aliases(
			[
				1 => [ 'own' => true ],
				2 => [ 'billing_email' => 'ada@example.test' ],
			],
			$graph,
			'2'
		);

		$this->assertSame( [ 'own' => true ], $context[1] );
	}

	public function test_label_names_the_app_and_the_event(): void {
		$this->assertSame(
			'WooCommerce · Order status completed',
			TriggerNodes::label( TriggerNodes::find( $this->graph(), '1' ) )
		);
	}
}
