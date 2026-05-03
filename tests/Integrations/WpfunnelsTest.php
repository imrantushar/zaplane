<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Wpfunnels;
use Zaplane\Tests\WPMocks;

class WpfunnelsTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Wpfunnels::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();

		\WpfunnelsTestStore::reset();

		foreach ( \WpfunnelsTestStore::getFunnels() as $funnel ) {
			WPMocks::setPost(
				(int) $funnel['id'],
				[
					'post_type'     => 'wpfunnels',
					'post_title'    => $funnel['title'],
					'post_status'   => $funnel['status'],
					'post_date'     => '2026-04-01 10:00:00',
					'post_modified' => '2026-04-01 11:00:00',
				]
			);
		}

		foreach ( \WpfunnelsTestStore::getSteps() as $step ) {
			WPMocks::setPost(
				(int) $step['id'],
				[
					'post_type'     => 'wpfunnel_steps',
					'post_title'    => $step['title'],
					'post_status'   => $step['status'],
					'post_date'     => '2026-04-01 10:05:00',
					'post_modified' => '2026-04-01 10:06:00',
				]
			);
		}
	}

	protected function getTriggerTests(): array {
		return [
			'loaded'                 => [],
			'init'                   => [],
			'pro_init'               => [],
			'import_complete'        => [],
			'after_funnel_creation'  => [ 601 ],
			'after_step_creation'    => [ 701 ],
			'after_step_duplicate'   => [ 601, 701 ],
			'funnel_journey_starts'  => [ 701, 601 ],
			'funnel_journey_end'     => [ 703, 601 ],
			'funnel_order_placed'    => [ 9001, 601, 702 ],
			'order_bump_accepted'    => [ 702, 1001 ],
			'order_bump_rejected'    => [ 702, 1001 ],
			'offer_accepted'         => [ new \WpfunnelsTestOrder( 9001 ), [ 'step_id' => 702, 'id' => 1002, 'name' => 'Upsell Product', 'qty' => 1 ] ],
			'offer_rejected'         => [ new \WpfunnelsTestOrder( 9001 ), [ 'step_id' => 702, 'id' => 1002, 'name' => 'Upsell Product', 'qty' => 1 ] ],
			'child_order_created'    => [ new \WpfunnelsTestOrder( 9001 ), new \WpfunnelsTestOrder( 9002 ), 'txn_1' ],
			'subscription_created'   => [ (object) [ 'id' => 55, 'status' => 'active' ], [ 'step_id' => 702, 'id' => 1002 ], new \WpfunnelsTestOrder( 9001 ) ],
			'setup_wizard_complete'  => [ 601, 'launch', 'sales' ],
			'template_body_top'      => [],
			'template_container_top' => [],
			'template_container_bottom' => [],
			'template_wp_footer'     => [],
		];
	}

	protected function getActionTests(): array {
		return [
			'get_funnel_single' => [ 'funnel_id' => 601 ],
			'get_step_single'   => [ 'step_id' => 701 ],
			'get_next_step'     => [ 'step_id' => 701 ],
			'add_action'        => [ 'hook_name' => 'wpfunnels/custom_hook', 'accepted_args' => 2 ],
			'do_action'         => [ 'hook_name' => 'wpfunnels/custom_hook', 'arg_1' => 'one', 'arg_2' => 'two' ],
			'add_filter'        => [ 'hook_name' => 'wpfunnels/custom_filter', 'accepted_args' => 1, 'return_value' => 'patched' ],
			'apply_filters'     => [ 'hook_name' => 'wpfunnels/custom_filter', 'value' => 'original', 'arg_1' => 'one', 'arg_2' => 'two' ],
			'remove_action'     => [ 'hook_name' => 'wpfunnels/custom_hook' ],
			'has_action'        => [ 'hook_name' => 'wpfunnels/custom_hook' ],
			'current_filter'    => [ 'hook_name' => 'wpfunnels/custom_hook' ],
		];
	}

	public function test_integration_exposes_introduction(): void {
		$this->assertSame(
			'Monitor funnel lifecycle, checkout journey, offer acceptance/rejection, and run hook-based automation actions for WPFunnels without webhooks.',
			Wpfunnels::get_introduction()
		);
	}

	public function test_trigger_funnel_order_placed_returns_order_step_and_funnel_payload(): void {
		$result = Wpfunnels::resolve_trigger(
			$this->makeTriggerNode( 'funnel_order_placed' ),
			[ 9001, 601, 702 ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 9001, $result['order_id'] );
		$this->assertEquals( 601, $result['funnel_id'] );
		$this->assertEquals( 702, $result['step_id'] );
	}

	public function test_trigger_offer_accepted_filters_by_selected_step(): void {
		$result = Wpfunnels::resolve_trigger(
			$this->makeTriggerNode(
				'offer_accepted',
				[
					'step_id' => 701,
				]
			),
			[
				new \WpfunnelsTestOrder( 9001 ),
				[
					'step_id' => 702,
					'id'      => 1002,
				],
			]
		);

		$this->assertFalse( $result );
	}

	public function test_action_get_next_step_returns_current_and_next_step_payload(): void {
		$result = Wpfunnels::execute_node(
			$this->makeActionNode(
				'get_next_step',
				[
					'step_id' => 701,
				]
			),
			[]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'main', $result['port'] );
		$this->assertEquals( 701, $result['data']['current_step']['step_id'] );
		$this->assertEquals( 702, $result['data']['next_step']['step_id'] );
	}

	public function test_action_get_funnel_single_resolves_funnel_id_from_input_payload(): void {
		$result = Wpfunnels::execute_node(
			$this->makeActionNode( 'get_funnel_single', [] ),
			[
				'event'     => 'funnel_order_placed',
				'order_id'  => 9001,
				'funnel_id' => 601,
				'step_id'   => 702,
			]
		);

		$this->assertSame( 'main', $result['port'] );
		$this->assertEquals( 601, $result['data']['funnel']['funnel_id'] );
	}

	public function test_action_get_step_single_resolves_step_id_from_input_payload(): void {
		$result = Wpfunnels::execute_node(
			$this->makeActionNode( 'get_step_single', [] ),
			[
				'event'     => 'funnel_order_placed',
				'order_id'  => 9001,
				'funnel_id' => 601,
				'step_id'   => 702,
			]
		);

		$this->assertSame( 'main', $result['port'] );
		$this->assertEquals( 702, $result['data']['step']['step_id'] );
	}

	public function test_action_get_next_step_resolves_step_id_from_input_payload(): void {
		$result = Wpfunnels::execute_node(
			$this->makeActionNode( 'get_next_step', [] ),
			[
				'step_id' => 701,
			]
		);

		$this->assertSame( 'main', $result['port'] );
		$this->assertEquals( 701, $result['data']['current_step']['step_id'] );
		$this->assertEquals( 702, $result['data']['next_step']['step_id'] );
	}

	public function test_dynamic_queries_include_seeded_funnel_and_steps(): void {
		$queries = Wpfunnels::get_dynamic_queries();

		$this->assertArrayHasKey( 'funnels', $queries );
		$this->assertArrayHasKey( 'steps', $queries );

		$funnels = Wpfunnels::query_funnels( [] );
		$steps   = Wpfunnels::query_steps( [] );

		$this->assertContains(
			[
				'name'  => '601',
				'label' => 'Main Funnel',
			],
			$funnels
		);

		$this->assertContains(
			[
				'name'  => '702',
				'label' => 'Checkout Step (checkout)',
			],
			$steps
		);
	}
}
