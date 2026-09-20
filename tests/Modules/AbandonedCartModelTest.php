<?php

namespace Zaplane\Tests\Modules;

use Zaplane\Tests\TestCase;
use Zaplane\Modules\AbandonedCart\AbandonedCartModel;

class AbandonedCartModelTest extends TestCase {

	// -------------------------------------------------------------------------
	// get_cart_contents
	// -------------------------------------------------------------------------

	public function test_get_cart_contents_returns_empty_array_when_cart_field_is_null(): void {
		$model = AbandonedCartModel::hydrate( [ 'id' => 1, 'cart' => null ] );

		$this->assertSame( [], $model->get_cart_contents() );
	}

	public function test_get_cart_contents_returns_empty_array_when_cart_field_is_empty_string(): void {
		$model = AbandonedCartModel::hydrate( [ 'id' => 1, 'cart' => '' ] );

		$this->assertSame( [], $model->get_cart_contents() );
	}

	public function test_get_cart_contents_returns_decoded_array(): void {
		$items = [
			[ 'product_id' => 10, 'quantity' => 2, 'name' => 'Shirt' ],
			[ 'product_id' => 11, 'quantity' => 1, 'name' => 'Hat' ],
		];
		$model = AbandonedCartModel::hydrate( [ 'id' => 1, 'cart' => json_encode( $items ) ] );

		$this->assertSame( $items, $model->get_cart_contents() );
	}

	public function test_get_cart_contents_returns_empty_array_for_invalid_json(): void {
		$model = AbandonedCartModel::hydrate( [ 'id' => 1, 'cart' => '{not valid json' ] );

		$this->assertSame( [], $model->get_cart_contents() );
	}

	public function test_get_cart_contents_returns_empty_array_for_json_scalar(): void {
		$model = AbandonedCartModel::hydrate( [ 'id' => 1, 'cart' => '"just a string"' ] );

		$this->assertSame( [], $model->get_cart_contents() );
	}

	// -------------------------------------------------------------------------
	// to_list_item
	// -------------------------------------------------------------------------

	private function makeCart(): AbandonedCartModel {
		return AbandonedCartModel::hydrate( [
			'id'           => 7,
			'full_name'    => 'Jane Doe',
			'email'        => 'jane@example.com',
			'status'       => 'draft',
			'total'        => '149.99',
			'currency'     => 'USD',
			'subtotal'     => '139.99',
			'shipping'     => '5.00',
			'tax'          => '5.00',
			'discounts'    => '0.00',
			'fees'         => '0.00',
			'checkout_key' => 'key-abc-xyz',
			'cart_hash'    => 'hash123',
			'is_optout'    => 0,
			'user_id'      => 3,
			'contact_id'   => null,
			'order_id'     => null,
			'click_counts' => 0,
			'note'         => '',
			'provider'     => 'woo',
			'cart'         => json_encode( [ [ 'product_id' => 5, 'quantity' => 1 ] ] ),
			'abandoned_at' => '2026-01-15 10:00:00',
			'recovered_at' => null,
			'created_at'   => '2026-01-15 09:30:00',
			'updated_at'   => '2026-01-15 10:00:00',
		] );
	}

	public function test_to_list_item_returns_array(): void {
		$item = $this->makeCart()->to_list_item();

		$this->assertIsArray( $item );
	}

	public function test_to_list_item_contains_all_expected_keys(): void {
		$item = $this->makeCart()->to_list_item();

		$expected_keys = [
			'id', 'full_name', 'email', 'status', 'total', 'currency',
			'subtotal', 'shipping', 'tax', 'discounts', 'fees',
			'checkout_key', 'cart_hash', 'is_optout', 'user_id',
			'contact_id', 'order_id', 'click_counts', 'note', 'provider',
			'cart', 'abandoned_at', 'recovered_at', 'created_at', 'updated_at',
		];

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $item, "to_list_item() missing key: {$key}" );
		}
	}

	public function test_to_list_item_maps_scalar_fields_correctly(): void {
		$item = $this->makeCart()->to_list_item();

		$this->assertSame( 7, $item['id'] );
		$this->assertSame( 'Jane Doe', $item['full_name'] );
		$this->assertSame( 'jane@example.com', $item['email'] );
		$this->assertSame( 'draft', $item['status'] );
		$this->assertSame( 'USD', $item['currency'] );
		$this->assertSame( 'woo', $item['provider'] );
		$this->assertSame( 'key-abc-xyz', $item['checkout_key'] );
	}

	public function test_to_list_item_cart_field_delegates_to_get_cart_contents(): void {
		$model = $this->makeCart();
		$item  = $model->to_list_item();

		$this->assertSame( $model->get_cart_contents(), $item['cart'] );
	}

	public function test_to_list_item_cart_field_is_array(): void {
		$item = $this->makeCart()->to_list_item();

		$this->assertIsArray( $item['cart'] );
		$this->assertCount( 1, $item['cart'] );
		$this->assertSame( 5, $item['cart'][0]['product_id'] );
	}
}
