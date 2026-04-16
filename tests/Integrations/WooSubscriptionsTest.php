<?php

namespace {
	require_once __DIR__ . '/../mocks/woocommerce.php';
	require_once __DIR__ . '/../mocks/woosubscriptions.php';
}

namespace Zaplane\Tests\Integrations {

use Zaplane\Integrations\WooSubscriptions;

class WooSubscriptionsTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return WooSubscriptions::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();
		\WooTestStore::reset();
		\WooSubscriptionsTestStore::reset();
	}

	protected function getTriggerTests(): array {
		return [
			'subscription_created'          => [ new \WC_Subscription( 801 ), new \WC_Order( 501 ) ],
			'subscription_status_updated'   => [ new \WC_Subscription( 801 ), 'active', 'on-hold' ],
			'subscription_payment_complete' => [ 801 ],
			'subscription_payment_failed'   => [ new \WC_Subscription( 802 ) ],
			'renewal_payment_complete'      => [ new \WC_Subscription( 801 ), new \WC_Order( 502 ) ],
			'renewal_payment_failed'        => [ new \WC_Subscription( 802 ), 502 ],
		];
	}

	protected function getActionTests(): array {
		return [
			'create_subscription' => [
				'customer_id' => 701,
				'parent_order_id' => 501,
				'subscription_status' => 'wc_subscription_active',
				'billing_period' => 'month',
				'billing_interval' => 1,
				'total' => '15.00',
				'currency' => 'USD',
			],
			'get_subscriptions_all' => [
				'limit' => 10,
				'page' => 1,
			],
			'get_subscription_single' => [
				'subscription_id' => 801,
			],
			'update_subscription_status' => [
				'subscription_id' => 801,
				'subscription_status' => 'wc_subscription_on-hold',
				'note' => 'Paused by automation',
			],
			'cancel_subscription' => [
				'subscription_id' => 801,
				'note' => 'Cancelled by automation',
			],
			'suspend_subscription' => [
				'subscription_id' => 802,
				'note' => 'Suspended by automation',
			],
			'reactivate_subscription' => [
				'subscription_id' => 802,
				'note' => 'Reactivated by automation',
			],
			'add_subscription_note' => [
				'subscription_id' => 801,
				'note' => 'Test note',
				'is_customer_note' => true,
			],
		];
	}

	public function test_subscription_created_returns_subscription_payload(): void {
		$result = WooSubscriptions::resolve_trigger(
			$this->makeTriggerNode( 'subscription_created' ),
			[ new \WC_Subscription( 801 ), new \WC_Order( 501 ) ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 801, $result['subscription_id'] );
		$this->assertEquals( 501, $result['order_id'] );
		$this->assertEquals( 'active', $result['status'] );
	}

	public function test_subscription_created_accepts_subscription_id_argument(): void {
		$result = WooSubscriptions::resolve_trigger(
			$this->makeTriggerNode( 'subscription_created' ),
			[ 801 ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 801, $result['subscription_id'] );
	}

	public function test_subscription_status_updated_includes_old_and_new_status(): void {
		$result = WooSubscriptions::resolve_trigger(
			$this->makeTriggerNode( 'subscription_status_updated' ),
			[ new \WC_Subscription( 801 ), 'cancelled', 'active' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'cancelled', $result['new_status'] );
		$this->assertEquals( 'active', $result['old_status'] );
	}

	public function test_renewal_payment_complete_includes_renewal_order_id(): void {
		$result = WooSubscriptions::resolve_trigger(
			$this->makeTriggerNode( 'renewal_payment_complete' ),
			[ new \WC_Subscription( 801 ), 502 ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 502, $result['renewal_order_id'] );
	}

	public function test_resolve_trigger_returns_false_for_invalid_subscription(): void {
		$result = WooSubscriptions::resolve_trigger(
			$this->makeTriggerNode( 'subscription_payment_complete' ),
			[ 0 ]
		);

		$this->assertFalse( $result );
	}

	public function test_subscription_created_resolves_subscription_from_mixed_args(): void {
		$result = WooSubscriptions::resolve_trigger(
			$this->makeTriggerNode( 'subscription_created' ),
			[ null, new \WC_Subscription( 801 ), new \WC_Order( 501 ) ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 801, $result['subscription_id'] );
		$this->assertEquals( 501, $result['order_id'] );
	}

	public function test_resolve_trigger_supports_legacy_trigger_node_shape(): void {
		$result = WooSubscriptions::resolve_trigger(
			[
				'type' => 'trigger',
				'data' => [
					'app' => 'woosubscriptions',
					'event' => 'subscription_payment_complete',
				],
			],
			[ 801 ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 801, $result['subscription_id'] );
	}

	public function test_action_get_subscription_single_returns_subscription_payload(): void {
		$result = WooSubscriptions::execute_node(
			$this->makeActionNode( 'get_subscription_single', [ 'subscription_id' => 801 ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 801, $result['data']['subscription']['subscription_id'] );
	}

	public function test_action_update_subscription_status_updates_status(): void {
		$result = WooSubscriptions::execute_node(
			$this->makeActionNode( 'update_subscription_status', [
				'subscription_id' => 801,
				'subscription_status' => 'wc_subscription_cancelled',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'cancelled', $result['data']['subscription']['new_status'] );
	}

	public function test_action_create_subscription_returns_created_payload(): void {
		$result = WooSubscriptions::execute_node(
			$this->makeActionNode( 'create_subscription', [
				'customer_id' => 701,
				'parent_order_id' => 501,
				'subscription_status' => 'wc_subscription_active',
				'billing_period' => 'month',
				'billing_interval' => 1,
				'total' => '29.99',
				'currency' => 'USD',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertArrayHasKey( 'subscription', $result['data'] );
		$this->assertGreaterThan( 0, $result['data']['subscription']['subscription_id'] );
		$this->assertEquals( 'active', $result['data']['subscription']['status'] );
	}

	public function test_action_get_subscriptions_all_returns_items(): void {
		$result = WooSubscriptions::execute_node(
			$this->makeActionNode( 'get_subscriptions_all', [
				'limit' => 10,
				'page' => 1,
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertArrayHasKey( 'items', $result['data'] );
		$this->assertNotEmpty( $result['data']['items'] );
	}

	public function test_action_cancel_subscription_sets_cancelled_status(): void {
		$result = WooSubscriptions::execute_node(
			$this->makeActionNode( 'cancel_subscription', [
				'subscription_id' => 801,
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'cancelled', $result['data']['subscription']['new_status'] );
	}

	public function test_action_suspend_and_reactivate_subscription_set_expected_status(): void {
		$suspend_result = WooSubscriptions::execute_node(
			$this->makeActionNode( 'suspend_subscription', [
				'subscription_id' => 801,
			] ),
			[]
		);

		$this->assertEquals( 'main', $suspend_result['port'] );
		$this->assertEquals( 'on-hold', $suspend_result['data']['subscription']['new_status'] );

		$reactivate_result = WooSubscriptions::execute_node(
			$this->makeActionNode( 'reactivate_subscription', [
				'subscription_id' => 801,
			] ),
			[]
		);

		$this->assertEquals( 'main', $reactivate_result['port'] );
		$this->assertEquals( 'active', $reactivate_result['data']['subscription']['new_status'] );
	}

	public function test_action_add_subscription_note_returns_note_data(): void {
		$result = WooSubscriptions::execute_node(
			$this->makeActionNode( 'add_subscription_note', [
				'subscription_id' => 801,
				'note' => 'Manual follow-up',
				'is_customer_note' => true,
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'Manual follow-up', $result['data']['note'] );
		$this->assertTrue( $result['data']['is_customer_note'] );
	}

	public function test_execute_node_supports_legacy_action_node_shape(): void {
		$result = WooSubscriptions::execute_node(
			[
				'type' => 'action',
				'config' => [
					'action' => 'get_subscription_single',
					'data' => [
						'subscription_id' => 801,
					],
				],
			],
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 801, $result['data']['subscription']['subscription_id'] );
	}

	public function test_action_update_subscription_status_returns_main_when_status_is_already_target(): void {
		$result = WooSubscriptions::execute_node(
			$this->makeActionNode( 'update_subscription_status', [
				'subscription_id' => 801,
				'subscription_status' => 'wc_subscription_active',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'active', $result['data']['subscription']['old_status'] );
		$this->assertEquals( 'active', $result['data']['subscription']['new_status'] );
	}
}
}
