<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Cartflows;
class CartflowsTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Cartflows::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();

		\CartflowsTestStore::reset();

		\Zaplane\Tests\WPMocks::setPost( 601, [
			'post_type'     => 'cartflows_flow',
			'post_title'    => 'Main Funnel',
			'post_status'   => 'publish',
			'post_date'     => '2026-04-01 10:00:00',
			'post_modified' => '2026-04-01 11:00:00',
		] );

		\Zaplane\Tests\WPMocks::setPost( 701, [
			'post_type'     => 'cartflows_step',
			'post_title'    => 'Landing Step',
			'post_status'   => 'publish',
			'post_date'     => '2026-04-01 10:05:00',
			'post_modified' => '2026-04-01 10:06:00',
		] );

		\Zaplane\Tests\WPMocks::setPost( 702, [
			'post_type'     => 'cartflows_step',
			'post_title'    => 'Checkout Step',
			'post_status'   => 'publish',
			'post_date'     => '2026-04-01 10:07:00',
			'post_modified' => '2026-04-01 10:08:00',
		] );

		\Zaplane\Tests\WPMocks::setPost( 703, [
			'post_type'     => 'cartflows_step',
			'post_title'    => 'Thank You Step',
			'post_status'   => 'publish',
			'post_date'     => '2026-04-01 10:09:00',
			'post_modified' => '2026-04-01 10:10:00',
		] );

		\Zaplane\Tests\WPMocks::setPost( 9001, [
			'post_type'     => 'shop_order',
			'post_title'    => 'Order #9001',
			'post_status'   => 'wc-processing',
			'post_date'     => '2026-04-02 09:00:00',
			'post_modified' => '2026-04-02 09:05:00',
		] );
	}

	protected function getTriggerTests(): array {
		return [
			'cartflows_init'           => [],
			'cartflows_body_top'       => [],
			'cartflows_container_top'  => [],
			'cartflows_container_bottom'=> [],
			'cartflows_wp_footer'      => [],
			'cartflows_import_complete'=> [],
			'step_viewed'              => [ 701 ],
			'checkout_before_shortcode'=> [ 702 ],
			'optin_before_shortcode'   => [ 702 ],
			'gutenberg_before_checkout_shortcode' => [ 702 ],
			'checkout_review_init'     => [ [ 'wcf_checkout_id' => 702, 'billing_email' => 'buyer@example.com' ] ],
			'checkout_review_updated'  => [ [ 'wcf_checkout_id' => 702, 'payment_method' => 'cod' ] ],
			'template_imported'        => [ 703, [ 'status' => 'success' ] ],
			'instant_thankyou_before'  => [ 9001 ],
			'instant_thankyou_after'   => [ 9001 ],
			'order_overview_cancelled' => [ new \Cartflows_Test_Order( 9001 ) ],
			'order_create_wc'          => [ 9001, [ 'billing_email' => 'buyer@example.com' ], new \Cartflows_Test_Order( 9001 ) ],
			'pro_loaded'               => [],
			'pro_init'                 => [],
			'order_started'            => [ new \Cartflows_Test_Order( 9001 ) ],
			'order_status_change_to_main_order' => [ 'completed', 'processing', new \Cartflows_Test_Order( 9001 ) ],
			'offer_accepted'           => [ new \Cartflows_Test_Order( 9001 ), [ 'step_id' => 703, 'title' => 'Upsell' ] ],
			'offer_rejected'           => [ new \Cartflows_Test_Order( 9001 ), [ 'step_id' => 703, 'title' => 'Upsell' ] ],
			'offer_child_order_created'=> [ new \Cartflows_Test_Order( 9001 ), new \Cartflows_Test_Order( 9001 ), 'txn_9001' ],
			'offer_subscription_created'=> [ [ 'id' => 5010 ], new \Cartflows_Test_Order( 9001 ), [ 'step_id' => 703 ] ],
			'checkout_before_multistep_layout' => [ 702 ],
			'checkout_after_multistep_layout'  => [ 702 ],
			'pre_checkout_offer_item_added' => [ 702, 'cart_hash_001' ],
			'after_quantity_update'    => [ 701 ],
			'after_single_selection'   => [ 701 ],
			'after_multiple_selection' => [ 701 ],
			'order_bump_item_added'    => [ 701 ],
			'order_bump_item_removed'  => [ 701 ],
			'after_order_bump_process' => [ [ 'product_id' => 701, 'quantity' => 1 ] ],
			'quick_view_selection'     => [ 701 ],
			'quick_view_title_before'  => [ 701 ],
			'quick_view_title_after'   => [ 701 ],
			'quick_view_price_before'  => [ 701 ],
			'quick_view_price_after'   => [ 701 ],
		];
	}

	protected function getActionTests(): array {
		return [
			'get_flow_single' => [
				'flow_id' => 601,
			],
			'get_step_single' => [
				'step_id' => 701,
			],
			'get_next_step' => [
				'step_id' => 701,
			],
			'add_action' => [
				'hook_name' => 'cartflows_custom_hook',
				'accepted_args' => 2,
			],
			'do_action' => [
				'hook_name' => 'cartflows_custom_hook',
				'arg_1' => [ 'source' => 'test' ],
				'arg_2' => 99,
			],
			'add_filter' => [
				'hook_name' => 'cartflows_filter_hook',
				'return_value' => 'filtered',
				'accepted_args' => 1,
			],
			'apply_filters' => [
				'hook_name' => 'cartflows_filter_hook',
				'value' => 'initial',
				'arg_1' => [ 'source' => 'test' ],
			],
			'remove_action' => [
				'hook_name' => 'cartflows_custom_hook',
			],
			'has_action' => [
				'hook_name' => 'cartflows_custom_hook',
			],
			'current_filter' => [],
		];
	}

	public function test_trigger_step_viewed_returns_step_payload(): void {
		$result = Cartflows::resolve_trigger(
			$this->makeTriggerNode( 'step_viewed' ),
			[ 701 ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 701, $result['step_id'] );
		$this->assertEquals( 601, $result['flow_id'] );
		$this->assertEquals( 'landing', $result['step_type'] );
	}

	public function test_action_get_next_step_returns_next_step_payload(): void {
		$result = Cartflows::execute_node(
			$this->makeActionNode( 'get_next_step', [ 'step_id' => 701 ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 701, $result['data']['current_step']['step_id'] );
		$this->assertEquals( 702, $result['data']['next_step']['step_id'] );
	}

	public function test_action_do_action_returns_triggered_payload(): void {
		$result = Cartflows::execute_node(
			$this->makeActionNode( 'do_action', [
				'hook_name' => 'cartflows_test_runtime_hook',
				'arg_1' => [ 'id' => 701 ],
				'arg_2' => 'ok',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertTrue( $result['data']['triggered'] );
		$this->assertEquals( 'cartflows_test_runtime_hook', $result['data']['hook'] );
	}

	public function test_trigger_order_create_wc_returns_order_payload(): void {
		$result = Cartflows::resolve_trigger(
			$this->makeTriggerNode( 'order_create_wc' ),
			[ 9001, [ 'payment_method' => 'cod' ], new \Cartflows_Test_Order( 9001 ) ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 9001, $result['order_id'] );
		$this->assertEquals( 702, $result['checkout_id'] );
		$this->assertEquals( 'processing', $result['order']['status'] );
		$this->assertEquals( 701, $result['order_products']['line_items'][0]['product_id'] );
	}

	public function test_trigger_order_create_wc_respects_selected_step(): void {
		$result = Cartflows::resolve_trigger(
			$this->makeTriggerNode( 'order_create_wc', [ 'step_id' => 703 ] ),
			[ 9001, [], new \Cartflows_Test_Order( 9001 ) ]
		);

		$this->assertFalse( $result );
	}

	public function test_trigger_schema_order_create_wc_has_dynamic_step_field(): void {
		$schema = Cartflows::get_trigger_config_schema( 'order_create_wc' );

		$this->assertIsArray( $schema );
		$this->assertNotEmpty( $schema );
		$this->assertEquals( 'step_id', $schema[0]['key'] );
		$this->assertEquals( 'select', $schema[0]['type'] );
		$this->assertArrayHasKey( 'dynamic', $schema[0] );
	}

	public function test_dynamic_queries_checkout_steps_registered(): void {
		$queries = Cartflows::get_dynamic_queries();

		$this->assertArrayHasKey( 'checkout_steps', $queries );
		$this->assertIsCallable( $queries['checkout_steps'] );
	}

	public function test_trigger_order_started_payload_has_order_info(): void {
		$result = Cartflows::resolve_trigger(
			$this->makeTriggerNode( 'order_started' ),
			[ new \Cartflows_Test_Order( 9001 ) ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'order_started', $result['event'] );
		$this->assertEquals( 9001, $result['order_id'] );
	}

	public function test_action_apply_filters_returns_filtered_value_key(): void {
		$result = Cartflows::execute_node(
			$this->makeActionNode( 'apply_filters', [
				'hook_name' => 'cartflows_filter_hook',
				'value' => 'test',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertArrayHasKey( 'filtered_value', $result['data'] );
	}
}
