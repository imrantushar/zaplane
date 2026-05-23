<?php

namespace {
	require_once __DIR__ . '/../mocks/dokan.php';
}

namespace Zaplane\Tests\Integrations {

use Zaplane\Integrations\Dokan;
use Zaplane\Tests\WPMocks;

class DokanTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Dokan::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();
		\DokanTestStore::reset();

		WPMocks::setUser( 801, [
			'user_login'   => 'vendor_one',
			'user_email'   => 'vendor-one@example.com',
			'display_name' => 'Vendor One',
			'roles'        => [ 'seller' ],
		] );

		WPMocks::setUser( 802, [
			'user_login'   => 'vendor_two',
			'user_email'   => 'vendor-two@example.com',
			'display_name' => 'Vendor Two',
			'roles'        => [ 'seller' ],
		] );

		WPMocks::setPost( 9101, [
			'post_type'     => 'product',
			'post_author'   => 801,
			'post_title'    => 'Dokan Product One',
			'post_status'   => 'publish',
			'post_date'     => '2026-04-10 10:00:00',
			'post_modified' => '2026-04-10 10:10:00',
		] );

		WPMocks::setPost( 9102, [
			'post_type'     => 'product',
			'post_author'   => 802,
			'post_title'    => 'Dokan Product Two',
			'post_status'   => 'draft',
			'post_date'     => '2026-04-11 10:00:00',
			'post_modified' => '2026-04-11 10:10:00',
		] );

		global $zaplane_wp_posts;
		$zaplane_wp_posts = $zaplane_wp_posts ?? [];
		$zaplane_wp_posts[9101] = (object) [
			'ID'            => 9101,
			'post_type'     => 'product',
			'post_author'   => 801,
			'post_title'    => 'Dokan Product One',
			'post_status'   => 'publish',
			'post_date'     => '2026-04-10 10:00:00',
			'post_modified' => '2026-04-10 10:10:00',
		];
		$zaplane_wp_posts[9102] = (object) [
			'ID'            => 9102,
			'post_type'     => 'product',
			'post_author'   => 802,
			'post_title'    => 'Dokan Product Two',
			'post_status'   => 'draft',
			'post_date'     => '2026-04-11 10:00:00',
			'post_modified' => '2026-04-11 10:10:00',
		];
	}

	protected function getTriggerTests(): array {
		return [
			'new_seller_created' => [ 801, [ 'store_name' => 'Vendor One Store' ] ],
			'store_profile_saved' => [ 801, [ 'store_name' => 'Vendor One Updated' ], [ 'store_name' => 'Vendor One Store' ] ],
			'new_product_added' => [ 9101, [ 'post_author' => 801 ] ],
			'product_updated' => [ 9102, [ 'post_author' => 802 ] ],
			'product_deleted' => [ 9102 ],
			'checkout_update_order_meta' => [ 9201, 801 ],
			'vendor_enabled' => [ 801 ],
			'vendor_disabled' => [ 802 ],
			'withdraw_request_created' => [ 801, 120.5, 'paypal', 5001 ],
			'withdraw_created' => [ new \Dokan_Test_Withdraw( 5001 ) ],
			'withdraw_request_pending' => [ new \Dokan_Test_Withdraw( 5001 ) ],
			'withdraw_request_approved' => [ new \Dokan_Test_Withdraw( 5002 ) ],
			'withdraw_request_cancelled' => [ new \Dokan_Test_Withdraw( 5003 ) ],
			'withdraw_status_updated' => [ 1, 801, 5002 ],
		];
	}

	protected function getActionTests(): array {
		return [
			'get_vendor_single' => [
				'vendor_id' => 801,
			],
			'get_vendors_all' => [
				'limit' => 10,
				'page' => 1,
			],
			'get_withdraw_single' => [
				'withdraw_id' => 5001,
			],
			'get_withdraws_all' => [
				'vendor_id' => 801,
				'withdraw_status' => 'any',
				'limit' => 10,
				'page' => 1,
			],
			'add_action' => [
				'hook_name' => 'dokan_custom_hook',
				'accepted_args' => 2,
			],
			'do_action' => [
				'hook_name' => 'dokan_custom_hook',
				'arg_1' => [ 'source' => 'test' ],
				'arg_2' => 99,
			],
		];
	}

	public function test_trigger_new_product_added_returns_product_payload(): void {
		$result = Dokan::resolve_trigger(
			$this->makeTriggerNode( 'new_product_added' ),
			[ 9101, [ 'post_author' => 801 ] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 9101, $result['product_id'] );
		$this->assertEquals( 801, $result['vendor_id'] );
		$this->assertNotEmpty( $result['product']['post_title'] );
	}

	public function test_trigger_new_product_added_respects_selected_vendor(): void {
		$result = Dokan::resolve_trigger(
			$this->makeTriggerNode( 'new_product_added', [ 'vendor_id' => '802' ] ),
			[ 9101, [ 'post_author' => 801 ] ]
		);

		$this->assertFalse( $result );
	}

	public function test_trigger_schema_new_product_added_has_dynamic_vendor_and_product_fields(): void {
		$schema = Dokan::get_trigger_config_schema( 'new_product_added' );

		$this->assertCount( 2, $schema );
		$this->assertEquals( 'vendor_id', $schema[0]['key'] );
		$this->assertEquals( 'product_id', $schema[1]['key'] );
		$this->assertArrayHasKey( 'dynamic', $schema[0] );
		$this->assertArrayHasKey( 'dynamic', $schema[1] );
	}

	public function test_action_get_vendor_single_returns_vendor_payload(): void {
		$result = Dokan::execute_node(
			$this->makeActionNode( 'get_vendor_single', [ 'vendor_id' => 801 ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 801, $result['data']['vendor']['vendor_id'] );
		$this->assertEquals( 'Vendor One Store', $result['data']['vendor']['store_name'] );
	}

	public function test_action_get_vendor_single_supports_dynamic_select_object_value(): void {
		$result = Dokan::execute_node(
			$this->makeActionNode( 'get_vendor_single', [ 'vendor_id' => [ 'name' => '801', 'label' => 'Vendor One Store (#801)' ] ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 801, $result['data']['vendor']['vendor_id'] );
	}

	public function test_action_get_withdraw_single_returns_withdraw_payload(): void {
		$result = Dokan::execute_node(
			$this->makeActionNode( 'get_withdraw_single', [ 'withdraw_id' => 5002 ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 5002, $result['data']['withdraw']['withdraw_id'] );
		$this->assertEquals( 801, $result['data']['withdraw']['vendor_id'] );
		$this->assertEquals( 'approved', $result['data']['withdraw']['status'] );
	}

	public function test_action_get_withdraw_single_supports_dynamic_select_object_value(): void {
		$result = Dokan::execute_node(
			$this->makeActionNode( 'get_withdraw_single', [ 'withdraw_id' => [ 'value' => '5002', 'label' => '#5002 - Approved' ] ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 5002, $result['data']['withdraw']['withdraw_id'] );
	}

	public function test_execute_node_supports_legacy_action_node_shape(): void {
		$result = Dokan::execute_node(
			[
				'type' => 'action',
				'config' => [
					'action' => 'get_vendor_single',
					'data' => [
						'vendor_id' => 801,
					],
				],
			],
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 801, $result['data']['vendor']['vendor_id'] );
	}

	public function test_execute_node_supports_config_event_node_shape(): void {
		$result = Dokan::execute_node(
			[
				'type' => 'action',
				'config' => [
					'event' => 'get_vendor_single',
					'vendor_id' => [ 'value' => '801' ],
				],
			],
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 801, $result['data']['vendor']['vendor_id'] );
	}

	public function test_trigger_withdraw_status_updated_can_resolve_vendor_from_withdraw(): void {
		$result = Dokan::resolve_trigger(
			$this->makeTriggerNode( 'withdraw_status_updated', [ 'vendor_id' => [ 'name' => '801' ] ] ),
			[ 1, 0, 5002 ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 801, $result['vendor_id'] );
		$this->assertEquals( 5002, $result['withdraw_id'] );
		$this->assertEquals( 'approved', $result['withdraw_status'] );
	}

	public function test_dynamic_queries_registered(): void {
		$queries = Dokan::get_dynamic_queries();

		$this->assertArrayHasKey( 'vendors', $queries );
		$this->assertArrayHasKey( 'products', $queries );
		$this->assertArrayHasKey( 'withdraws', $queries );
		$this->assertIsCallable( $queries['vendors'] );
		$this->assertIsCallable( $queries['products'] );
		$this->assertIsCallable( $queries['withdraws'] );
	}
}
}
