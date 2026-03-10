<?php
namespace Zaplane\Integrations\Woo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CartActionsTrait {

	private static function action_get_cart_items_all( array $config, array $input ): array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return self::error( 'Cart is not available' );
		}
		$product_id = isset( $config['product_id'] ) ? (int) $config['product_id'] : 0;
		$cart = WC()->cart;
		$items = [];
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( $product_id > 0 && (int) ( $item['product_id'] ?? 0 ) !== $product_id ) {
				continue;
			}
			$items[] = [
				'cart_item_key' => $key,
				'product_id' => $item['product_id'] ?? 0,
				'variation_id' => $item['variation_id'] ?? 0,
				'quantity' => $item['quantity'] ?? 0,
				'line_subtotal' => $item['line_subtotal'] ?? 0,
				'line_total' => $item['line_total'] ?? 0,
			];
		}
		return self::respond( [
			'count' => count( $items ),
			'items' => $items
		] );
	}

	private static function action_get_cart_totals( array $config, array $input ): array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return self::error( 'Cart is not available' );
		}
		$cart = WC()->cart;
		$response = [ 'totals' => $cart->get_totals() ];
		if ( isset( $config['with_items_count'] ) && self::parse_bool( $config['with_items_count'] ) ) {
			$response['items_count'] = (int) $cart->get_cart_contents_count();
		}
		return self::respond( $response );
	}

	private static function action_add_product_to_cart( array $config, array $input ): array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return self::error( 'Cart is not available' );
		}
		$product_id = (int) ( $config['product_id'] ?? 0 );
		if ( ! $product_id ) {
			return self::error( 'Product ID is required' );
		}
		$quantity = isset( $config['quantity'] ) ? max( 1, (int) $config['quantity'] ) : 1;
		$variation_id = (int) ( $config['variation_id'] ?? 0 );
		$variations = self::parse_json_array( $config['variations'] ?? [] );
		$cart_item_data = self::parse_json_array( $config['cart_item_data'] ?? [] );

		$cart_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations, $cart_item_data );
		if ( ! $cart_key ) {
			return self::error( 'Failed to add product to cart' );
		}
		return self::respond( [ 'cart_item_key' => $cart_key ] );
	}

	private static function action_remove_product_from_cart( array $config, array $input ): array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return self::error( 'Cart is not available' );
		}
		$cart_item_key = $config['cart_item_key'] ?? '';
		if ( '' === $cart_item_key ) {
			return self::error( 'Cart item key is required' );
		}
		$removed = WC()->cart->remove_cart_item( $cart_item_key );
		if ( ! $removed ) {
			return self::error( 'Failed to remove item from cart' );
		}
		return self::respond( [ 'cart_item_key' => $cart_item_key ] );
	}
}
