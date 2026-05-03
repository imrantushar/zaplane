<?php

namespace {
	require_once __DIR__ . '/../mocks/fluentcart.php';
}

namespace Zaplane\Tests\Integrations {

use Zaplane\Integrations\FluentCart;
use Zaplane\Tests\WPMocks;

class FluentCartTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return FluentCart::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();
		\FluentCartTestStore::reset();
		WPMocks::setPost(
			701,
			[
				'post_title'  => 'FluentCart Product One',
				'post_status' => 'publish',
				'post_type'   => 'fluent-products',
			]
		);
		WPMocks::setPost(
			702,
			[
				'post_title'  => 'FluentCart Product Two',
				'post_status' => 'publish',
				'post_type'   => 'fluent-products',
			]
		);
	}

	protected function getTriggerTests(): array {
		return [
			'order_created' => [
				[
					'order_id'       => 501,
					'order'          => [ 'id' => 501, 'customer_id' => 401, 'status' => 'completed' ],
					'customer_id'    => 401,
					'customer'       => [ 'id' => 401, 'email' => 'customer-one@example.com' ],
					'transaction'    => [ 'id' => 'txn_order_created_1' ],
					'old_status'     => 'pending',
					'new_status'     => 'completed',
				],
			],
			'order_paid' => [
				[
					'order_id'       => 501,
					'order'          => [ 'id' => 501, 'customer_id' => 401, 'status' => 'completed' ],
					'customer_id'    => 401,
					'customer'       => [ 'id' => 401 ],
					'transaction'    => [ 'id' => 'txn_order_paid_1' ],
				],
			],
			'order_paid_done' => [
				[
					'order'        => [ 'id' => 501, 'customer_id' => 401, 'status' => 'completed' ],
					'customer'     => [ 'id' => 401 ],
					'transaction'  => [ 'id' => 'txn_order_paid_done_1' ],
					'subscription' => [ 'id' => 601, 'customer_id' => 401 ],
				],
			],
			'order_payment_failed' => [
				[
					'order_id'       => 502,
					'order'          => [ 'id' => 502, 'customer_id' => 402, 'status' => 'pending' ],
					'customer_id'    => 402,
					'customer'       => [ 'id' => 402 ],
					'reason'         => 'card_declined',
				],
			],
			'order_updated' => [
				[
					'order_id'    => 501,
					'order'       => [ 'id' => 501, 'customer_id' => 401, 'status' => 'processing' ],
					'customer_id' => 401,
				],
			],
			'order_canceled' => [
				[
					'order_id'    => 502,
					'order'       => [ 'id' => 502, 'customer_id' => 402, 'status' => 'canceled' ],
					'customer_id' => 402,
					'reason'      => 'requested_by_customer',
				],
			],
			'order_deleted' => [
				[
					'order_id'            => 501,
					'order'               => [ 'id' => 501, 'customer_id' => 401, 'status' => 'completed' ],
					'customer_id'         => 401,
					'connected_order_ids' => [ 601, 602 ],
				],
			],
			'renewal_order_deleted' => [
				[
					'order_id'    => 502,
					'order'       => [ 'id' => 502, 'customer_id' => 402, 'status' => 'completed' ],
					'customer_id' => 402,
				],
			],
			'order_refunded' => [
				[
					'order_id'        => 501,
					'order'           => [ 'id' => 501, 'customer_id' => 401, 'status' => 'refunded' ],
					'customer_id'     => 401,
					'refunded_amount' => 12.50,
				],
			],
			'order_fully_refunded' => [
				[
					'order_id'        => 501,
					'order'           => [ 'id' => 501, 'customer_id' => 401, 'status' => 'refunded' ],
					'customer_id'     => 401,
					'refunded_amount' => 129.50,
					'refunded_items'  => [ [ 'product_id' => 701, 'qty' => 1 ] ],
				],
			],
			'order_partially_refunded' => [
				[
					'order_id'        => 502,
					'order'           => [ 'id' => 502, 'customer_id' => 402, 'status' => 'partially_refunded' ],
					'customer_id'     => 402,
					'refunded_amount' => 10,
					'refunded_items'  => [ [ 'product_id' => 702, 'qty' => 1 ] ],
				],
			],
			'order_status_changed' => [
				[
					'order_id'    => 501,
					'order'       => [ 'id' => 501, 'customer_id' => 401, 'status' => 'completed' ],
					'customer_id' => 401,
					'old_status'  => 'processing',
					'new_status'  => 'completed',
				],
			],
			'payment_status_changed' => [
				[
					'order_id'    => 501,
					'order'       => [ 'id' => 501, 'customer_id' => 401, 'payment_status' => 'paid' ],
					'customer_id' => 401,
					'old_status'  => 'pending',
					'new_status'  => 'paid',
				],
			],
			'shipping_status_changed' => [
				[
					'order_id'    => 501,
					'order'       => [ 'id' => 501, 'customer_id' => 401, 'shipping_status' => 'fulfilled' ],
					'customer_id' => 401,
					'old_status'  => 'pending',
					'new_status'  => 'fulfilled',
				],
			],
			'subscription_activated' => [
				[
					'subscription_id' => 601,
					'subscription'    => [ 'id' => 601, 'customer_id' => 401, 'status' => 'active' ],
					'order_id'        => 501,
					'order'           => [ 'id' => 501, 'customer_id' => 401 ],
					'customer_id'     => 401,
					'customer'        => [ 'id' => 401 ],
				],
			],
			'subscription_canceled' => [
				[
					'subscription_id' => 602,
					'subscription'    => [ 'id' => 602, 'customer_id' => 402, 'status' => 'canceled' ],
					'order_id'        => 502,
					'order'           => [ 'id' => 502, 'customer_id' => 402 ],
					'customer_id'     => 402,
					'customer'        => [ 'id' => 402 ],
					'reason'          => 'expired',
				],
			],
			'subscription_renewed' => [
				[
					'subscription_id' => 601,
					'subscription'    => [ 'id' => 601, 'customer_id' => 401, 'status' => 'active' ],
					'order_id'        => 501,
					'order'           => [ 'id' => 501, 'customer_id' => 401 ],
					'customer_id'     => 401,
					'customer'        => [ 'id' => 401 ],
				],
			],
			'subscription_eot' => [
				[
					'subscription_id' => 601,
					'subscription'    => [ 'id' => 601, 'customer_id' => 401, 'status' => 'active' ],
					'order_id'        => 501,
					'order'           => [ 'id' => 501, 'customer_id' => 401 ],
					'customer_id'     => 401,
					'customer'        => [ 'id' => 401 ],
				],
			],
			'subscription_expired_validity' => [
				[
					'subscription_id' => 602,
					'subscription'    => [ 'id' => 602, 'customer_id' => 402, 'status' => 'expired' ],
					'order_id'        => 502,
					'order'           => [ 'id' => 502, 'customer_id' => 402 ],
					'customer_id'     => 402,
					'customer'        => [ 'id' => 402 ],
				],
			],
			'product_created' => [
				701,
				(object) [
					'ID'          => 701,
					'post_type'   => 'fluent-products',
					'post_title'  => 'FluentCart Product One',
					'post_status' => 'publish',
				],
				false,
			],
			'product_updated' => [
				[
					'data'    => [ 'post_title' => 'Updated Product Name' ],
					'product' => [ 'ID' => 701, 'post_title' => 'Updated Product Name', 'post_status' => 'publish' ],
				],
			],
			'product_duplicated' => [
				[
					'original_product_id' => 701,
					'new_product_id'      => 702,
					'options'             => [ 'import_stock_management' => true ],
				],
			],
			'product_stock_changed' => [
				[
					'post_ids' => [ 701, 702 ],
				],
			],
		];
	}

	protected function getActionTests(): array {
		return [
			'get_order_single' => [
				'order_id' => 501,
			],
			'get_orders_all' => [
				'limit'          => 10,
				'page'           => 1,
				'search'         => '',
				'customer_id'    => 401,
				'order_status'   => 'completed',
				'payment_status' => 'paid',
			],
			'get_customer_single' => [
				'customer_id' => 401,
			],
			'get_customers_all' => [
				'limit'           => 10,
				'page'            => 1,
				'search'          => 'Customer',
				'customer_status' => 'active',
			],
			'get_subscription_single' => [
				'subscription_id' => 601,
			],
				'get_subscriptions_all' => [
					'limit'               => 10,
					'page'                => 1,
					'search'              => '601',
					'customer_id'         => 401,
					'subscription_status' => 'active',
				],
			'get_product_single' => [
				'product_id' => 701,
			],
				'get_products_all' => [
					'limit'  => 10,
					'page'   => 1,
					'search' => 'FluentCart Product',
					'post_status' => 'publish',
				],
				'create_product' => [
					'post_title'       => 'Created From Test',
					'post_name'        => 'created-from-test',
					'post_content'     => 'Created product description',
					'post_excerpt'     => 'Created product short description',
					'post_status'      => 'draft',
					'fulfillment_type' => 'physical',
					'stock_status'     => 'in-stock',
					'payment_type'     => 'onetime',
					'total_stock'      => 5,
				],
			'update_product' => [
				'product_id'  => 701,
				'post_title'  => 'FluentCart Product One Updated',
				'post_status' => 'publish',
			],
			'add_action' => [
				'hook_name'     => 'fluentcart_test_hook',
				'accepted_args' => 2,
			],
			'do_action' => [
				'hook_name' => 'fluentcart_test_hook',
				'arg_1'     => [ 'source' => 'test' ],
				'arg_2'     => 'ok',
			],
		];
	}

	public function test_integration_exposes_introduction(): void {
		$this->assertSame(
			'Track FluentCart order, payment, subscription, and stock events and run hook-based actions without webhooks.',
			FluentCart::get_introduction()
		);
	}

	public function test_trigger_order_created_filters_by_selected_customer(): void {
		$result = FluentCart::resolve_trigger(
			$this->makeTriggerNode(
				'order_created',
				[
					'customer_id' => 402,
				]
			),
			[
				[
					'order_id'    => 501,
					'customer_id' => 401,
					'order'       => [ 'id' => 501, 'customer_id' => 401 ],
				],
			]
		);

		$this->assertFalse( $result );
	}

	public function test_trigger_product_stock_changed_filters_by_selected_product(): void {
		$result = FluentCart::resolve_trigger(
			$this->makeTriggerNode(
				'product_stock_changed',
				[
					'product_id' => 702,
				]
			),
			[
				[
					'post_ids' => [ 701 ],
				],
			]
		);

		$this->assertFalse( $result );
	}

	public function test_trigger_subscription_activated_filters_by_selected_order(): void {
		$result = FluentCart::resolve_trigger(
			$this->makeTriggerNode(
				'subscription_activated',
				[
					'order_id' => 999,
				]
			),
			[
				[
					'subscription_id' => 601,
					'subscription'    => [ 'id' => 601, 'customer_id' => 401, 'status' => 'active' ],
					'order_id'        => 501,
					'order'           => [ 'id' => 501, 'customer_id' => 401 ],
					'customer_id'     => 401,
					'customer'        => [ 'id' => 401 ],
				],
			]
		);

		$this->assertFalse( $result );
	}

	public function test_trigger_order_canceled_is_deduplicated_in_same_request(): void {
		$node = $this->makeTriggerNode( 'order_canceled', [] );
		$args = [
			[
				'order_id'    => 502,
				'order'       => [ 'id' => 502, 'customer_id' => 402, 'status' => 'canceled' ],
				'customer_id' => 402,
				'reason'      => 'requested_by_customer',
			],
		];

		$first = FluentCart::resolve_trigger( $node, $args );
		$second = FluentCart::resolve_trigger( $node, $args );

		$this->assertIsArray( $first );
		$this->assertFalse( $second );
	}

	public function test_trigger_product_created_ignores_updates(): void {
		$result = FluentCart::resolve_trigger(
			$this->makeTriggerNode( 'product_created', [] ),
			[
				701,
				(object) [
					'ID'          => 701,
					'post_type'   => 'fluent-products',
					'post_title'  => 'FluentCart Product One',
					'post_status' => 'publish',
				],
				true,
			]
		);

		$this->assertFalse( $result );
	}

	public function test_action_get_order_single_resolves_order_id_from_input_payload(): void {
		$result = FluentCart::execute_node(
			$this->makeActionNode( 'get_order_single', [] ),
			[
				'order' => [
					'id' => 501,
				],
			]
		);

		$this->assertSame( 'main', $result['port'] );
		$this->assertEquals( 501, $result['data']['order']['id'] );
	}

	public function test_action_get_order_single_returns_error_without_id(): void {
		$result = FluentCart::execute_node(
			$this->makeActionNode( 'get_order_single', [] ),
			[]
		);

		$this->assertSame( 'error', $result['port'] );
		$this->assertSame( 'Order ID is required', $result['data']['error'] );
	}

	public function test_action_do_action_returns_triggered_payload(): void {
		$result = FluentCart::execute_node(
			$this->makeActionNode(
				'do_action',
				[
					'hook_name' => 'fluentcart_runtime_hook',
					'arg_1'     => [ 'order_id' => 501 ],
					'arg_2'     => 'ok',
				]
			),
			[]
		);

		$this->assertSame( 'main', $result['port'] );
		$this->assertTrue( $result['data']['triggered'] );
		$this->assertSame( 'fluentcart_runtime_hook', $result['data']['hook'] );
	}

	public function test_action_update_product_returns_error_for_missing_product(): void {
		$result = FluentCart::execute_node(
			$this->makeActionNode(
				'update_product',
				[
					'product_id' => 9999,
					'post_title' => 'Missing Product',
				]
			),
			[]
		);

		$this->assertSame( 'error', $result['port'] );
		$this->assertSame( 'Product not found', $result['data']['error'] );
	}

	public function test_action_get_product_single_resolves_product_id_from_input_payload(): void {
		$result = FluentCart::execute_node(
			$this->makeActionNode( 'get_product_single', [] ),
			[
				'product' => [
					'id' => 701,
				],
			]
		);

		$this->assertSame( 'main', $result['port'] );
		$this->assertEquals( 701, $result['data']['product']['id'] );
	}

	public function test_action_get_products_all_filters_by_post_status(): void {
		$result = FluentCart::execute_node(
			$this->makeActionNode(
				'get_products_all',
				[
					'limit'       => 10,
					'page'        => 1,
					'search'      => '',
					'post_status' => 'draft',
				]
			),
			[]
		);

		$this->assertSame( 'main', $result['port'] );
		$this->assertSame( 0, $result['data']['total'] );
	}

	public function test_action_get_subscriptions_all_supports_search(): void {
		$result = FluentCart::execute_node(
			$this->makeActionNode(
				'get_subscriptions_all',
				[
					'limit'               => 10,
					'page'                => 1,
					'search'              => '602',
					'subscription_status' => 'any',
				]
			),
			[]
		);

		$this->assertSame( 'main', $result['port'] );
		$this->assertSame( 1, $result['data']['total'] );
		$this->assertSame( 602, (int) ( $result['data']['items'][0]['id'] ?? 0 ) );
	}

	public function test_action_create_product_accepts_extended_fields(): void {
		$result = FluentCart::execute_node(
			$this->makeActionNode(
				'create_product',
				[
					'post_title'       => 'Extended Product',
					'post_name'        => 'extended-product-slug',
					'post_content'     => 'Long description',
					'post_excerpt'     => 'Short description',
					'post_status'      => 'draft',
					'fulfillment_type' => 'physical',
					'stock_status'     => 'in-stock',
					'payment_type'     => 'onetime',
					'total_stock'      => 7,
				]
			),
			[]
		);

		$this->assertSame( 'main', $result['port'] );
		$product_id = (int) ( $result['data']['product_id'] ?? 0 );
		$this->assertGreaterThan( 0, $product_id );

		$post = WPMocks::getPost( $product_id );
		$this->assertNotNull( $post );
		$this->assertSame( 'Extended Product', (string) ( $post->post_title ?? '' ) );
		$this->assertSame( 'extended-product-slug', (string) ( $post->post_name ?? '' ) );
		$this->assertSame( 'Long description', (string) ( $post->post_content ?? '' ) );
		$this->assertSame( 'Short description', (string) ( $post->post_excerpt ?? '' ) );
		$this->assertSame( 'draft', (string) ( $post->post_status ?? '' ) );
	}

	public function test_dynamic_queries_include_seeded_entities(): void {
		$queries = FluentCart::get_dynamic_queries();

		$this->assertArrayHasKey( 'orders', $queries );
		$this->assertArrayHasKey( 'customers', $queries );
		$this->assertArrayHasKey( 'subscriptions', $queries );
		$this->assertArrayHasKey( 'products', $queries );
		$this->assertArrayHasKey( 'order_statuses', $queries );
		$this->assertArrayHasKey( 'payment_statuses', $queries );
		$this->assertArrayHasKey( 'customer_statuses', $queries );
		$this->assertArrayHasKey( 'subscription_statuses', $queries );
		$this->assertArrayHasKey( 'post_statuses', $queries );
		$this->assertArrayHasKey( 'fulfillment_types', $queries );
		$this->assertArrayHasKey( 'stock_statuses', $queries );
		$this->assertArrayHasKey( 'payment_types', $queries );

		$orders = FluentCart::orders_query( [] );
		$customers = FluentCart::customers_query( [] );
		$subscriptions = FluentCart::subscriptions_query( [] );
		$products = FluentCart::products_query( [] );
		$order_statuses = FluentCart::order_statuses_query( [] );
		$payment_statuses = FluentCart::payment_statuses_query( [] );
		$customer_statuses = FluentCart::customer_statuses_query( [] );
		$subscription_statuses = FluentCart::subscription_statuses_query( [] );
		$post_statuses = FluentCart::post_statuses_query( [] );
		$fulfillment_types = FluentCart::fulfillment_types_query( [] );
		$stock_statuses = FluentCart::stock_statuses_query( [] );
		$payment_types = FluentCart::payment_types_query( [] );

		$this->assertContains(
			[
				'name'  => '501',
				'label' => '#501 - Paid',
			],
			$orders
		);

		$this->assertContains(
			[
				'name'  => '401',
				'label' => 'Customer One (#401)',
			],
			$customers
		);

		$this->assertContains(
			[
				'name'  => '601',
				'label' => '#601 - Active',
			],
			$subscriptions
		);

		$this->assertContains(
			[
				'name'  => '701',
				'label' => 'FluentCart Product One (#701)',
			],
			$products
		);

		$this->assertContains(
			[
				'name'  => 'completed',
				'label' => 'Completed',
			],
			$order_statuses
		);

		$this->assertContains(
			[
				'name'  => 'paid',
				'label' => 'Paid',
			],
			$payment_statuses
		);

		$this->assertContains(
			[
				'name'  => 'active',
				'label' => 'Active',
			],
			$customer_statuses
		);

		$this->assertContains(
			[
				'name'  => 'active',
				'label' => 'Active',
			],
			$subscription_statuses
		);

		$this->assertContains(
			[
				'name'  => 'publish',
				'label' => 'Publish',
			],
			$post_statuses
		);

		$this->assertContains(
			[
				'name'  => 'physical',
				'label' => 'Physical',
			],
			$fulfillment_types
		);

		$this->assertContains(
			[
				'name'  => 'in-stock',
				'label' => 'In-stock',
			],
			$stock_statuses
		);

		$this->assertContains(
			[
				'name'  => 'onetime',
				'label' => 'Onetime',
			],
			$payment_types
		);
	}

	public function test_status_and_product_option_fields_are_dynamic(): void {
		$get_orders_all_schema = FluentCart::get_action_config_schema( 'get_orders_all' );
		$get_customers_all_schema = FluentCart::get_action_config_schema( 'get_customers_all' );
		$get_subscriptions_all_schema = FluentCart::get_action_config_schema( 'get_subscriptions_all' );
		$get_products_all_schema = FluentCart::get_action_config_schema( 'get_products_all' );
		$create_product_schema = FluentCart::get_action_config_schema( 'create_product' );
		$update_product_schema = FluentCart::get_action_config_schema( 'update_product' );
		$subscription_trigger_schema = FluentCart::get_trigger_config_schema( 'subscription_activated' );

		$order_status = $this->find_schema_field( $get_orders_all_schema, 'order_status' );
		$payment_status = $this->find_schema_field( $get_orders_all_schema, 'payment_status' );
		$customer_status = $this->find_schema_field( $get_customers_all_schema, 'customer_status' );
		$subscription_status = $this->find_schema_field( $get_subscriptions_all_schema, 'subscription_status' );
		$subscription_search = $this->find_schema_field( $get_subscriptions_all_schema, 'search' );
		$product_post_status = $this->find_schema_field( $get_products_all_schema, 'post_status' );
		$create_post_status = $this->find_schema_field( $create_product_schema, 'post_status' );
		$fulfillment_type = $this->find_schema_field( $create_product_schema, 'fulfillment_type' );
		$stock_status = $this->find_schema_field( $create_product_schema, 'stock_status' );
		$payment_type = $this->find_schema_field( $create_product_schema, 'payment_type' );
		$total_stock = $this->find_schema_field( $create_product_schema, 'total_stock' );
		$create_post_name = $this->find_schema_field( $create_product_schema, 'post_name' );
		$create_post_content = $this->find_schema_field( $create_product_schema, 'post_content' );
		$create_post_excerpt = $this->find_schema_field( $create_product_schema, 'post_excerpt' );
		$update_post_status = $this->find_schema_field( $update_product_schema, 'post_status' );
		$subscription_order_id = $this->find_schema_field( $subscription_trigger_schema, 'order_id' );

		$this->assertSame( 'order_statuses', $order_status['dynamic']['query'] ?? '' );
		$this->assertSame( 'payment_statuses', $payment_status['dynamic']['query'] ?? '' );
		$this->assertSame( 'customer_statuses', $customer_status['dynamic']['query'] ?? '' );
		$this->assertSame( 'subscription_statuses', $subscription_status['dynamic']['query'] ?? '' );
		$this->assertSame( 'text', $subscription_search['type'] ?? '' );
		$this->assertSame( 'post_statuses', $product_post_status['dynamic']['query'] ?? '' );
		$this->assertSame( 'post_statuses', $create_post_status['dynamic']['query'] ?? '' );
		$this->assertSame( 'fulfillment_types', $fulfillment_type['dynamic']['query'] ?? '' );
		$this->assertSame( 'stock_statuses', $stock_status['dynamic']['query'] ?? '' );
		$this->assertSame( 'payment_types', $payment_type['dynamic']['query'] ?? '' );
		$this->assertSame( 'number', $total_stock['type'] ?? '' );
		$this->assertSame( 'text', $create_post_name['type'] ?? '' );
		$this->assertSame( 'textarea', $create_post_content['type'] ?? '' );
		$this->assertSame( 'textarea', $create_post_excerpt['type'] ?? '' );
		$this->assertSame( 'post_statuses', $update_post_status['dynamic']['query'] ?? '' );
		$this->assertSame( 'orders', $subscription_order_id['dynamic']['query'] ?? '' );
	}

	private function find_schema_field( array $schema, string $key ): array {
		foreach ( $schema as $field ) {
			if ( ( $field['key'] ?? '' ) === $key ) {
				return $field;
			}
		}

		$this->fail( "Schema field '{$key}' not found" );
		return [];
	}
}
}
