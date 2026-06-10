<?php
/**
 * Recipe data factories for the woocommerce integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'zaplane_recipe_factories',
	function ( array $factories ) {
		$unique = static function ( string $prefix ): string {
			return $prefix . '_' . substr( md5( uniqid( 'zaplane', true ) ), 0, 10 );
		};

		$create_customer = static function () use ( $unique ): array {
			$login   = $unique( 'zaplane_customer' );
			$user_id = wp_insert_user(
				[
					'user_login' => $login,
					'user_email' => "{$login}@example.test",
					'user_pass'  => wp_generate_password( 16 ),
					'role'       => 'customer',
				]
			);

			if ( is_wp_error( $user_id ) ) {
				throw new \RuntimeException( 'create_customer failed: ' . $user_id->get_error_message() );
			}

			$customer = new \WC_Customer( (int) $user_id );
			$customer->set_first_name( 'Zaplane' );
			$customer->set_last_name( 'Recipe' );
			$customer->save();

			return [
				'customer_id' => (int) $user_id,
				'customer'    => $customer,
			];
		};

		$create_order = static function ( string $status = 'processing', float $total = 50.0 ): array {
			$order = wc_create_order();
			$order->set_total( $total );
			$order->set_status( $status );
			$order->save();

			return [
				'order_id' => (int) $order->get_id(),
				'order'    => $order,
				'status'   => $order->get_status(),
				'total'    => (float) $order->get_total(),
			];
		};

		$create_product = static function ( string $status = 'publish' ): array {
			$product = new \WC_Product_Simple();
			$product->set_name( 'Zaplane Recipe Product' );
			$product->set_regular_price( '19.99' );
			$product->set_status( $status );
			$product_id = $product->save();
			$post       = get_post( (int) $product_id );

			if ( ! $post ) {
				throw new \RuntimeException( 'create_product failed: product post was not created.' );
			}

			return [
				'product_id' => (int) $product_id,
				'product'    => $product,
				'post'       => $post,
			];
		};

		$create_coupon = static function () use ( $unique ): array {
			$coupon = new \WC_Coupon();
			$coupon->set_code( $unique( 'zaplane_coupon' ) );
			$coupon->set_discount_type( 'fixed_cart' );
			$coupon->set_amount( 5 );
			$coupon_id = $coupon->save();

			return [
				'coupon_id' => (int) $coupon_id,
				'coupon'    => $coupon,
			];
		};

		$factories['create_woocommerce_new_order'] = static function ( array $args ) use ( $create_order ) {
			$vars = $create_order( $args['status'] ?? 'processing', (float) ( $args['total'] ?? 50 ) );
			return [
				'arg0' => $vars['order_id'],
				'arg1' => $vars['order'],
			];
		};

		$factories['create_woocommerce_restore_order'] = static function ( array $args ) use ( $create_order ) {
			$vars = $create_order( $args['status'] ?? 'processing', (float) ( $args['total'] ?? 50 ) );
			return [
				'arg0' => $vars['order_id'],
				'arg1' => $args['previous_status'] ?? 'trash',
			];
		};

		$factories['create_woocommerce_order_status_changed'] = static function ( array $args ) use ( $create_order ) {
			$vars = $create_order( $args['new_status'] ?? 'processing', (float) ( $args['total'] ?? 50 ) );
			return [
				'arg0' => $vars['order_id'],
				'arg1' => $args['old_status'] ?? 'pending',
				'arg2' => $args['new_status'] ?? 'processing',
			];
		};

		$factories['create_woocommerce_new_coupon'] = static function () use ( $create_coupon ) {
			$vars = $create_coupon();
			return [
				'arg0' => $vars['coupon_id'],
				'arg1' => $vars['coupon'],
			];
		};

		$factories['create_woocommerce_create_customer'] = static function () use ( $create_customer ) {
			$vars = $create_customer();
			return [
				'arg0' => $vars['customer_id'],
				'arg1' => $vars['customer'],
				'arg2' => false,
			];
		};

		$factories['create_woocommerce_update_customer'] = static function () use ( $create_customer ) {
			$vars = $create_customer();
			return [
				'arg0' => $vars['customer_id'],
				'arg1' => $vars['customer'],
			];
		};

		$factories['create_woocommerce_delete_customer'] = static function () use ( $create_customer ) {
			$vars = $create_customer();
			return [
				'arg0' => $vars['customer_id'],
			];
		};

		$factories['create_woocommerce_create_product'] = static function () use ( $create_product ) {
			$vars = $create_product();
			return [
				'arg0' => $vars['product_id'],
				'arg1' => $vars['post'],
			];
		};

		$factories['create_woocommerce_update_product'] = static function () use ( $create_product ) {
			$vars = $create_product();
			return [
				'arg0' => $vars['product_id'],
				'arg1' => $vars['post'],
			];
		};

		$factories['create_woocommerce_delete_product'] = static function () use ( $create_product ) {
			$vars = $create_product();
			return [
				'arg0' => 'trash',
				'arg1' => 'publish',
				'arg2' => $vars['post'],
			];
		};

		$factories['create_woocommerce_restore_product'] = static function () use ( $create_product ) {
			$vars = $create_product( 'trash' );
			return [
				'arg0' => 'publish',
				'arg1' => 'trash',
				'arg2' => $vars['post'],
			];
		};

		$factories['create_woocommerce_product_status_updated'] = static function () use ( $create_product ) {
			$vars = $create_product();
			return [
				'arg0' => $vars['product_id'],
				'arg1' => 'instock',
			];
		};

		$factories['create_woocommerce_product_status_changed'] = static function () use ( $create_product ) {
			$vars = $create_product();
			return [
				'arg0' => 'draft',
				'arg1' => 'publish',
				'arg2' => $vars['post'],
			];
		};

		$factories['create_woocommerce_product_added_to_cart'] = static function () use ( $create_product ) {
			$vars = $create_product();
			return [
				'arg0' => 'zaplane_cart_item',
				'arg1' => $vars['product_id'],
				'arg2' => 1,
				'arg3' => 0,
			];
		};

		$factories['create_woocommerce_product_removed_from_cart'] = static function () {
			return [
				'arg0' => 'zaplane_cart_item',
				'arg1' => null,
			];
		};

		return $factories;
	}
);
