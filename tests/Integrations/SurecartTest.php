<?php

namespace {
	require_once __DIR__ . '/../mocks/surecart.php';
}

namespace Zaplane\Tests\Integrations {

use Zaplane\Integrations\Surecart;

class SurecartTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Surecart::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();
		\SureCartTestStore::reset();
	}

	protected function getTriggerTests(): array {
		return [
			'purchase_created'     => [ $this->makeModel( [ 'id' => 'pur_1', 'status' => 'created' ] ) ],
			'purchase_invoked'     => [ $this->makeModel( [ 'id' => 'pur_1', 'status' => 'invoked' ] ) ],
			'purchase_revoked'     => [ $this->makeModel( [ 'id' => 'pur_1', 'status' => 'revoked' ] ) ],
			'checkout_confirmed'   => [ $this->makeModel( [ 'id' => 'chk_1', 'status' => 'confirmed' ] ), [ 'source' => 'checkout' ] ],
			'product_sync_created' => [ $this->makePostPayload(), $this->makeModel( [ 'id' => 'prod_1', 'name' => 'SureCart Product' ] ) ],
			'product_sync_updated' => [ $this->makePostPayload(), $this->makeModel( [ 'id' => 'prod_1', 'name' => 'SureCart Product' ] ) ],
			'integrations_created' => [ [ 'provider' => 'stripe' ] ],
			'integrations_deleted' => [ [ 'provider' => 'stripe' ] ],
			'post_created'         => [ $this->makePostPayload(), [ 'page_service' => 'checkout' ] ],
		];
	}

	protected function getActionTests(): array {
		return [
			'create_order'             => [ 'data' => [ 'status' => 'paid', 'number' => '1002' ] ],
			'update_order'             => [ 'order_id' => 'ord_1', 'data' => [ 'status' => 'refunded' ] ],
			'get_orders_all'           => [],
			'get_order_single'         => [ 'order_id' => 'ord_1' ],
			'create_customer'          => [ 'data' => [ 'email' => 'new@example.com' ] ],
			'update_customer'          => [ 'customer_id' => 'cus_1', 'data' => [ 'email' => 'updated@example.com' ] ],
			'get_customers_all'        => [],
			'get_customer_single'      => [ 'customer_id' => 'cus_1' ],
			'create_product_manual'    => [ 'name' => 'Manual Product', 'price_amount' => 2500, 'currency' => 'USD' ],
			'create_product'           => [ 'data' => [ 'name' => 'JSON Product' ] ],
			'update_product'           => [ 'product_id' => 'prod_1', 'data' => [ 'name' => 'Updated Product' ] ],
			'get_products_all'         => [],
			'get_product_single'       => [ 'product_id' => 'prod_1' ],
			'delete_product'           => [ 'product_id' => 'prod_1' ],
			'create_coupon'            => [ 'data' => [ 'name' => 'WELCOME' ] ],
			'update_coupon'            => [ 'coupon_id' => 'cpn_1', 'data' => [ 'name' => 'WELCOME2' ] ],
			'get_coupons_all'          => [],
			'get_coupon_single'        => [ 'coupon_id' => 'cpn_1' ],
			'delete_coupon'            => [ 'coupon_id' => 'cpn_1' ],
			'create_subscription'      => [ 'data' => [ 'status' => 'trialing' ] ],
			'update_subscription'      => [ 'subscription_id' => 'sub_1', 'data' => [ 'status' => 'paused' ] ],
			'get_subscriptions_all'    => [],
			'get_subscription_single'  => [ 'subscription_id' => 'sub_1' ],
		];
	}

	private function makeModel( array $data ): object {
		return new class( $data ) {
			private array $data;

			public function __construct( array $data ) {
				$this->data = $data;
			}

			public function toArray(): array {
				return $this->data;
			}
		};
	}

	private function makePostPayload(): array {
		return [
			'ID'            => 700,
			'post_title'    => 'SureCart Page',
			'post_name'     => 'surecart-page',
			'post_status'   => 'publish',
			'post_type'     => 'page',
			'post_date'     => '2024-01-01 00:00:00',
			'post_modified' => '2024-01-01 00:00:00',
			'post_author'   => 1,
			'guid'          => 'https://example.com/surecart-page',
		];
	}

	public function test_trigger_checkout_confirmed_includes_request_array(): void {
		$result = Surecart::resolve_trigger(
			$this->makeTriggerNode( 'checkout_confirmed' ),
			[
				$this->makeModel( [ 'id' => 'chk_1', 'status' => 'confirmed' ] ),
				[ 'source' => 'checkout', 'coupon' => 'WELCOME' ],
			]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'chk_1', $result['checkout']['id'] );
		$this->assertEquals( 'WELCOME', $result['request']['coupon'] );
	}

	public function test_trigger_help_widget_loaded_returns_empty_array(): void {
		$result = Surecart::resolve_trigger(
			$this->makeTriggerNode( 'help_widget_loaded' ),
			[]
		);

		$this->assertSame( [], $result );
	}

	public function test_action_create_product_manual_returns_error_when_name_is_missing(): void {
		$result = Surecart::execute_node(
			$this->makeActionNode( 'create_product_manual', [ 'price_amount' => 2500, 'currency' => 'USD' ] ),
			[]
		);

		$this->assertEquals( 'error', $result['port'] );
		$this->assertEquals( 'Product name is required', $result['data']['error'] );
	}

	public function test_action_get_order_single_returns_model_payload(): void {
		$result = Surecart::execute_node(
			$this->makeActionNode( 'get_order_single', [ 'order_id' => 'ord_1' ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'ord_1', $result['data']['order']['id'] );
	}
}
}
