<?php

namespace {
	require_once __DIR__ . '/../mocks/woocommerce.php';
}

namespace Zaplane\Tests\Integrations {

use Zaplane\Integrations\Woocommerce;
use Zaplane\Tests\WPMocks;

class WoocommerceTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Woocommerce::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();
		\WooTestStore::reset();

		foreach ( [ 'product_cat', 'product_tag', 'product_type', 'product_brand', 'product_shipping_class', 'pa_color' ] as $taxonomy ) {
			register_taxonomy( $taxonomy, 'product' );
		}

		WPMocks::setPost( 901, [ 'post_title' => 'Simple Product', 'post_status' => 'publish', 'post_type' => 'product' ] );
		WPMocks::setPost( 902, [ 'post_title' => 'Variable Product', 'post_status' => 'publish', 'post_type' => 'product' ] );
		WPMocks::setPost( 903, [ 'post_title' => 'Grouped Product', 'post_status' => 'publish', 'post_type' => 'product' ] );
		WPMocks::setPost( 904, [ 'post_title' => 'External Product', 'post_status' => 'publish', 'post_type' => 'product' ] );
		WPMocks::setPost( 905, [ 'post_title' => 'Variation Product', 'post_status' => 'publish', 'post_type' => 'product' ] );
		WPMocks::setPost( 906, [ 'post_title' => 'Subscription Product', 'post_status' => 'publish', 'post_type' => 'product' ] );
		WPMocks::setPost( 301, [ 'post_title' => 'Coupon SAVE10', 'post_status' => 'publish', 'post_type' => 'shop_coupon' ] );
		WPMocks::setPost( 302, [ 'post_title' => 'Coupon WELCOME', 'post_status' => 'publish', 'post_type' => 'shop_coupon' ] );
		WPMocks::setTerm( 21, [ 'term_id' => 21, 'name' => 'Default Category', 'slug' => 'default-cat', 'taxonomy' => 'product_cat' ] );
		WPMocks::setTerm( 22, [ 'term_id' => 22, 'name' => 'Featured', 'slug' => 'featured', 'taxonomy' => 'product_tag' ] );
		WPMocks::setTerm( 23, [ 'term_id' => 23, 'name' => 'Simple', 'slug' => 'simple', 'taxonomy' => 'product_type' ] );
		WPMocks::setTerm( 24, [ 'term_id' => 24, 'name' => 'BrandX', 'slug' => 'brandx', 'taxonomy' => 'product_brand' ] );
		WPMocks::setTerm( 25, [ 'term_id' => 25, 'name' => 'Fast', 'slug' => 'fast', 'taxonomy' => 'product_shipping_class' ] );
		WPMocks::setTerm( 26, [ 'term_id' => 26, 'name' => 'Blue', 'slug' => 'blue', 'taxonomy' => 'pa_color' ] );
	}

	protected function getTriggerTests(): array {
		return [
			'new_order'                 => [ 501, new \WC_Order( 501 ) ],
			'restore_order'             => [ 501, 'trash', null, new \WC_Order( 501 ) ],
			'order_status_pending'      => [ 501, new \WC_Order( 501 ), [ 'from' => 'draft', 'to' => 'pending' ] ],
			'order_status_failed'       => [ 501, new \WC_Order( 501 ), [ 'from' => 'pending', 'to' => 'failed' ] ],
			'order_status_on_hold'      => [ 501, new \WC_Order( 501 ), [ 'from' => 'pending', 'to' => 'on-hold' ] ],
			'order_status_processing'   => [ 501, new \WC_Order( 501 ), [ 'from' => 'pending', 'to' => 'processing' ] ],
			'order_status_completed'    => [ 501, new \WC_Order( 501 ), [ 'from' => 'processing', 'to' => 'completed' ] ],
			'order_status_refunded'     => [ 501, new \WC_Order( 501 ), [ 'from' => 'completed', 'to' => 'refunded' ] ],
			'order_status_cancelled'    => [ 501, new \WC_Order( 501 ), [ 'from' => 'pending', 'to' => 'cancelled' ] ],
			'order_status_changed'      => [ 501, 'pending', 'completed', new \WC_Order( 501 ) ],
			'new_coupon'                => [ 301, new \WC_Coupon( 301 ) ],
			'create_customer'           => [ 701, new \WC_Customer( 701 ), true ],
			'update_customer'           => [ 701, new \WC_Customer( 701 ) ],
			'delete_customer'           => [ 701 ],
			'create_product'            => [ 901, wc_get_product( 901 ) ],
			'update_product'            => [ 901, wc_get_product( 901 ) ],
			'delete_product'            => [ 901 ],
			'restore_product'           => [ 901 ],
			'product_status_updated'    => [ 901, 'outofstock', wc_get_product( 901 ) ],
			'product_status_changed'    => [ 'publish', 'draft', 901 ],
			'product_added_to_cart'     => [ 'cart-item-1', 901, 2, 0 ],
			'product_removed_from_cart' => [ 'cart-item-1', \WooTestStore::getCart() ],
		];
	}

	protected function getActionTests(): array {
		return array_merge(
			$this->getOrderActionTests(),
			$this->getCustomerActionTests(),
			$this->getProductActionTests(),
			$this->getTaxonomyActionTests(),
			$this->getAttributeActionTests(),
			$this->getCartActionTests(),
			$this->getCouponActionTests(),
			$this->getReviewActionTests()
		);
	}

	private function getOrderActionTests(): array {
		return [
			'create_order'                => [ 'customer_id' => 701, 'currency' => 'USD', 'order_status' => 'wc_order_processing', 'note' => 'Created by test' ],
			'update_order'                => [ 'order_id' => 501, 'customer_id' => 701, 'total' => '55.50', 'currency' => 'USD', 'billing' => [ 'email' => 'customer701@example.com' ] ],
			'update_order_status'         => [ 'order_id' => 501, 'order_status' => 'wc_order_completed' ],
			'add_or_update_order_meta'    => [ 'order_id' => 501, 'meta_key' => 'tracking', 'meta_value' => 'ZX-123' ],
			'get_total_orders_count'      => [ 'order_status' => 'wc_order_processing' ],
			'get_refunded_orders'         => [ 'limit' => 5, 'page' => 1 ],
			'get_orders_all'              => [ 'limit' => 5, 'page' => 1 ],
			'get_orders_by_status'        => [ 'order_status' => 'wc_order_processing' ],
			'get_orders_by_billing_email' => [ 'billing_email' => 'customer701@example.com' ],
			'get_orders_by_customer_id'   => [ 'customer_id' => 701 ],
			'get_order_single'            => [ 'order_id' => 501 ],
			'get_customer_total_spent'    => [ 'customer_id' => 701 ],
			'get_customer_last_order'     => [ 'customer_id' => 701 ],
			'add_order_note'              => [ 'order_id' => 501, 'note' => 'Internal note' ],
		];
	}

	private function getCustomerActionTests(): array {
		return [
			'get_customers_all'    => [ 'limit' => 5, 'page' => 1 ],
			'get_customer_single'  => [ 'customer_id' => 701 ],
			'get_customer_by_email' => [ 'email' => 'customer701@example.com' ],
			'create_customer'      => [ 'email' => 'newcustomer@example.com', 'username' => 'newcustomer', 'first_name' => 'New', 'last_name' => 'Customer' ],
		];
	}

	private function getProductActionTests(): array {
		return [
			'create_product'                => [ 'name' => 'Created Product', 'sku' => 'SKU-NEW', 'price' => '11.99', 'regular_price' => '14.99', 'stock_quantity' => 4, 'manage_stock' => '1', 'stock_status' => 'instock', 'category_ids' => [ 21 ], 'tag_ids' => [ 22 ] ],
			'create_product_variation'      => [ 'parent_id' => 902, 'attributes' => [ 'pa_color' => 'blue' ], 'sku' => 'SKU-VAR-NEW', 'regular_price' => '12.99' ],
			'update_product'                => [ 'product_id' => 901, 'name' => 'Updated Product', 'price' => '33.00', 'stock_quantity' => 8 ],
			'get_products_all'              => [ 'limit' => 10, 'page' => 1 ],
			'get_products_by_category'      => [ 'category_slug' => 'default-cat' ],
			'get_products_simple'           => [ 'limit' => 10 ],
			'get_products_variable'         => [ 'limit' => 10 ],
			'get_products_grouped'          => [ 'limit' => 10 ],
			'get_products_external'         => [ 'limit' => 10 ],
			'get_products_variation'        => [ 'limit' => 10 ],
			'get_products_subscription'     => [ 'limit' => 10 ],
			'get_product_by_id'             => [ 'product_id' => 901 ],
			'get_product_by_sku'            => [ 'sku' => 'SKU-901' ],
			'update_product_stock'          => [ 'product_id' => 901, 'manage_stock' => '1', 'stock_quantity' => 20, 'stock_status' => 'instock' ],
			'delete_product_permanently'    => [ 'product_id' => 904 ],
			'delete_product_soft'           => [ 'product_id' => 903 ],
			'get_products_totals'           => [ 'include_variations' => '1' ],
			'get_product_sales_count_by_id' => [ 'product_id' => 901 ],
			'update_product_status'         => [ 'product_id' => 901, 'product_status' => 'wc_product_publish' ],
		];
	}

	private function getTaxonomyActionTests(): array {
		$map = [
			'product_category'       => [ 'create' => 'create_product_category', 'update' => 'update_product_category', 'delete' => 'delete_product_category', 'all' => 'get_product_category_all', 'single' => 'get_product_category_single', 'term_id' => 21, 'name' => 'Default Category' ],
			'product_tag'            => [ 'create' => 'create_product_tag', 'update' => 'update_product_tag', 'delete' => 'delete_product_tag', 'all' => 'get_product_tag_all', 'single' => 'get_product_tag_single', 'term_id' => 22, 'name' => 'Featured' ],
			'product_type'           => [ 'create' => 'create_product_type', 'update' => 'update_product_type', 'delete' => 'delete_product_type', 'all' => 'get_product_type_all', 'single' => 'get_product_type_single', 'term_id' => 23, 'name' => 'Simple' ],
			'product_brand'          => [ 'create' => 'create_product_brand', 'update' => 'update_product_brand', 'delete' => 'delete_product_brand', 'all' => 'get_product_brand_all', 'single' => 'get_product_brand_single', 'term_id' => 24, 'name' => 'BrandX' ],
			'product_shipping_class' => [ 'create' => 'create_product_shipping_class', 'update' => 'update_product_shipping_class', 'delete' => 'delete_product_shipping_class', 'all' => 'get_product_shipping_class_all', 'single' => 'get_product_shipping_class_single', 'term_id' => 25, 'name' => 'Fast' ],
		];

		$tests = [];
		foreach ( $map as $item ) {
			$tests[ $item['create'] ] = [ 'name' => $item['name'] . ' New', 'slug' => sanitize_key( $item['name'] . '-new' ) ];
			$tests[ $item['update'] ] = [ 'term_id' => $item['term_id'], 'name' => $item['name'] . ' Updated' ];
			$tests[ $item['all'] ] = [ 'limit' => 10, 'page' => 1 ];
			$tests[ $item['single'] ] = [ 'term_id' => $item['term_id'] ];
			$tests[ $item['delete'] ] = [ 'term_id' => $item['term_id'] ];
		}

		return $tests;
	}

	private function getAttributeActionTests(): array {
		return [
			'add_or_update_product_attribute' => [ 'product_id' => 901, 'attribute_name' => 'pa_color', 'taxonomy' => 'pa_color', 'is_taxonomy' => '1', 'options' => [ 'Blue' ] ],
			'remove_product_attribute'        => [ 'product_id' => 901, 'attribute_name' => 'pa_color' ],
			'create_attribute'                => [ 'name' => 'Size', 'slug' => 'pa_size', 'type' => 'select' ],
			'update_attribute'                => [ 'attribute_id' => 1, 'name' => 'Colour', 'slug' => 'pa_color' ],
			'get_attribute'                   => [ 'attribute_id' => 1 ],
			'delete_attribute'                => [ 'attribute_id' => 1 ],
		];
	}

	private function getCartActionTests(): array {
		return [
			'get_cart_items_all'       => [ 'product_id' => 901 ],
			'get_cart_totals'          => [ 'with_items_count' => '1' ],
			'add_product_to_cart'      => [ 'product_id' => 901, 'quantity' => 1 ],
			'remove_product_from_cart' => [ 'cart_item_key' => 'cart-item-1' ],
		];
	}

	private function getCouponActionTests(): array {
		return [
			'create_coupon'                    => [ 'code' => 'NEW10', 'amount' => '10', 'discount_type' => 'percent' ],
			'update_coupon_data'               => [ 'coupon_id' => 301, 'amount' => '15', 'discount_type' => 'fixed_cart' ],
			'update_coupon_code'               => [ 'coupon_id' => 301, 'new_code' => 'SAVE15' ],
			'add_coupon_emails'                => [ 'coupon_id' => 301, 'emails' => [ 'allowed@example.com' ] ],
			'apply_coupon_to_cart'             => [ 'code' => 'SAVE10' ],
			'get_applied_coupons_from_cart'    => [ 'include_totals' => '1' ],
			'remove_coupon_from_cart'          => [ 'code' => 'SAVE10' ],
			'get_coupons_all'                  => [ 'limit' => 10, 'page' => 1 ],
			'get_coupon_single'                => [ 'coupon_id' => 301 ],
			'get_coupon_totals_by_discount_type' => [ 'include_empty_types' => '1' ],
			'delete_coupon'                    => [ 'coupon_id' => 302 ],
		];
	}

	private function getReviewActionTests(): array {
		return [
			'get_reviews_all'             => [ 'limit' => 10, 'page' => 1 ],
			'top_selling_products_report' => [ 'limit' => 3 ],
		];
	}

	public function test_trigger_new_order_returns_order_payload(): void {
		$result = Woocommerce::resolve_trigger( $this->makeTriggerNode( 'new_order' ), [ 501, new \WC_Order( 501 ) ] );
		$this->assertIsArray( $result );
		$this->assertEquals( 501, $result['order_id'] );
	}

	public function test_trigger_new_order_returns_false_without_order(): void {
		$this->assertFalse( Woocommerce::resolve_trigger( $this->makeTriggerNode( 'new_order' ), [ 0, null ] ) );
	}

	public function test_action_create_order_returns_order_payload(): void {
		$result = Woocommerce::execute_node( $this->makeActionNode( 'create_order', [ 'customer_id' => 701, 'order_status' => 'wc_order_processing' ] ), [] );
		$this->assertEquals( 'main', $result['port'] );
		$this->assertArrayHasKey( 'order', $result['data'] );
	}

	public function test_action_get_products_by_category_returns_collection(): void {
		$result = Woocommerce::execute_node( $this->makeActionNode( 'get_products_by_category', [ 'category_slug' => 'default-cat' ] ), [] );
		$this->assertEquals( 'main', $result['port'] );
		$this->assertArrayHasKey( 'items', $result['data'] );
	}

	public function test_action_remove_product_from_cart_returns_error_without_key(): void {
		$result = Woocommerce::execute_node( $this->makeActionNode( 'remove_product_from_cart', [] ), [] );
		$this->assertEquals( 'error', $result['port'] );
		$this->assertEquals( 'Cart item key is required', $result['data']['error'] );
	}
}
}
