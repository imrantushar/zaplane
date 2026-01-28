<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\WordPressPluginIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Woo extends WordPressPluginIntegration {

	/* ---------------------------------------------------------
	 * Identity
	 * --------------------------------------------------------- */

	public static function get_slug(): string { return 'woocommerce'; }
	public static function get_name(): string { return 'WooCommerce'; }
	public static function get_icon(): string { return 'woocommerce'; }

	/* ---------------------------------------------------------
	 * Triggers
	 * --------------------------------------------------------- */

	public static function get_triggers(): array {
		return [
			'new_order'            => [ 'label' => 'New Order',             'hook' => 'woocommerce_new_order' ],
			'order_status_changed' => [ 'label' => 'Order Status Changed',  'hook' => 'woocommerce_order_status_changed' ],
			'order_completed'      => [ 'label' => 'Order Completed',       'hook' => 'woocommerce_order_status_completed' ],
			'order_processing'     => [ 'label' => 'Order Processing',      'hook' => 'woocommerce_order_status_processing' ],
			'order_cancelled'      => [ 'label' => 'Order Cancelled',       'hook' => 'woocommerce_order_status_cancelled' ],
			'order_refunded'       => [ 'label' => 'Order Refunded',        'hook' => 'woocommerce_order_status_refunded' ],
			'product_published'    => [ 'label' => 'Product Published',     'hook' => 'woocommerce_new_product' ],
			'add_to_cart'          => [ 'label' => 'Product Added to Cart', 'hook' => 'woocommerce_add_to_cart' ],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( $trigger === 'order_status_changed' ) {
			return [
				[ 'key' => 'from_status', 'label' => 'From Status', 'type' => 'select', 'options' => self::order_status_options() ],
				[ 'key' => 'to_status',   'label' => 'To Status',   'type' => 'select', 'options' => self::order_status_options() ],
			];
		}
		return [];
	}

	/* ---------------------------------------------------------
	 * Trigger Payload Resolution
	 * --------------------------------------------------------- */

	public static function resolve_trigger( array $node, array $args ) {
		$event = $node['event'] ?? '';

		if ( in_array( $event, [
			'new_order', 'order_completed', 'order_processing',
			'order_cancelled', 'order_refunded',
		], true ) ) {
			return self::resolve_wc_order( $args[0] ?? 0 );
		}

		if ( $event === 'order_status_changed' ) {
			$payload = self::resolve_wc_order( $args[0] ?? 0 );
			if ( ! $payload ) return false;

			$payload['old_status'] = $args[1] ?? '';
			$payload['new_status'] = $args[2] ?? '';
			return $payload;
		}

		if ( $event === 'product_published' ) {
			return self::resolve_wc_product( $args[0] ?? 0 );
		}

		if ( $event === 'add_to_cart' ) {
			return [
				'cart_item_key' => $args[0] ?? '',
				'product_id'    => $args[1] ?? 0,
				'quantity'      => $args[2] ?? 1,
				'variation_id'  => $args[3] ?? 0,
			];
		}

		return false;
	}

	/* ---------------------------------------------------------
	 * Actions
	 * --------------------------------------------------------- */

	public static function get_actions(): array {
		return [
			'create_order'   => [ 'label' => 'Create Order' ],
			'update_order'   => [ 'label' => 'Update Order Status' ],
			'add_order_note' => [ 'label' => 'Add Order Note' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [
			'create_order' => [
				[ 'key' => 'customer_email', 'label' => 'Customer Email', 'type' => 'expression', 'required' => true ],
				[ 'key' => 'status', 'label' => 'Order Status', 'type' => 'select', 'options' => self::order_status_options(), 'default' => 'pending' ],
			],
			'update_order' => [
				[ 'key' => 'order_id', 'label' => 'Order ID',    'type' => 'expression', 'required' => true ],
				[ 'key' => 'status',   'label' => 'New Status',  'type' => 'select', 'required' => true, 'options' => self::order_status_options() ],
			],
			'add_order_note' => [
				[ 'key' => 'order_id',         'label' => 'Order ID',      'type' => 'expression', 'required' => true ],
				[ 'key' => 'note',             'label' => 'Note',          'type' => 'textarea', 'required' => true ],
				[ 'key' => 'is_customer_note', 'label' => 'Customer Note', 'type' => 'boolean', 'default' => false ],
			],
		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? $node['config']['data'] ?? [];
		$event  = $node['data']['event'] ?? $node['config']['action'] ?? '';

		if ( $event === 'create_order' ) {
			if ( ! function_exists( 'wc_create_order' ) ) {
				throw new \Exception( 'WooCommerce is not active' );
			}

			$order = wc_create_order();
			if ( is_wp_error( $order ) ) {
				throw new \Exception( 'Failed to create order: ' . $order->get_error_message() );
			}

			$email = $config['customer_email'] ?? '';
			if ( $email ) {
				$order->set_billing_email( $email );
			}

			$order->set_status( $config['status'] ?? 'pending' );
			$order->save();

			return [ 'port' => 'main', 'data' => array_merge( $input, [
				'order_id' => $order->get_id(),
				'status'   => $order->get_status(),
			] ) ];
		}

		if ( $event === 'update_order' ) {
			$order_id = $config['order_id'] ?? 0;
			if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
				throw new \Exception( 'Order ID is required and WooCommerce must be active' );
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				throw new \Exception( 'Order not found: ' . $order_id );
			}

			$order->set_status( $config['status'] ?? 'processing' );
			$order->save();

			return [ 'port' => 'main', 'data' => array_merge( $input, [
				'order_id' => $order_id,
				'status'   => $order->get_status(),
				'updated'  => true,
			] ) ];
		}

		if ( $event === 'add_order_note' ) {
			$order_id = $config['order_id'] ?? 0;
			if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
				throw new \Exception( 'Order ID is required and WooCommerce must be active' );
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				throw new \Exception( 'Order not found: ' . $order_id );
			}

			$note_id = $order->add_order_note(
				$config['note'] ?? '',
				! empty( $config['is_customer_note'] )
			);

			return [ 'port' => 'main', 'data' => array_merge( $input, [
				'order_id' => $order_id,
				'note_id'  => $note_id,
			] ) ];
		}

		return [ 'port' => 'main', 'data' => $input ];
	}

	/* ---------------------------------------------------------
	 * Payload Helpers
	 * --------------------------------------------------------- */

	private static function resolve_wc_order( int $order_id ) {
		if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) return false;

		$order = wc_get_order( $order_id );
		if ( ! $order ) return false;

		return [
			'order_id'       => $order_id,
			'order_number'   => $order->get_order_number(),
			'order_status'   => $order->get_status(),
			'total'          => $order->get_total(),
			'currency'       => $order->get_currency(),
			'payment_method' => $order->get_payment_method(),
			'customer_email' => $order->get_billing_email(),
			'customer_name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'billing_address' => [
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'email'      => $order->get_billing_email(),
				'phone'      => $order->get_billing_phone(),
				'address_1'  => $order->get_billing_address_1(),
				'city'       => $order->get_billing_city(),
				'country'    => $order->get_billing_country(),
			],
		];
	}

	private static function resolve_wc_product( int $product_id ) {
		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) return false;

		$product = wc_get_product( $product_id );
		if ( ! $product ) return false;

		return [
			'product_id'    => $product_id,
			'product_name'  => $product->get_name(),
			'price'         => $product->get_price(),
			'regular_price' => $product->get_regular_price(),
			'sale_price'    => $product->get_sale_price(),
			'sku'           => $product->get_sku(),
			'status'        => $product->get_status(),
			'stock_status'  => $product->get_stock_status(),
		];
	}

	private static function order_status_options(): array {
		return [
			[ 'label' => 'Pending',    'value' => 'pending' ],
			[ 'label' => 'Processing', 'value' => 'processing' ],
			[ 'label' => 'On Hold',    'value' => 'on-hold' ],
			[ 'label' => 'Completed',  'value' => 'completed' ],
			[ 'label' => 'Cancelled',  'value' => 'cancelled' ],
			[ 'label' => 'Refunded',   'value' => 'refunded' ],
			[ 'label' => 'Failed',     'value' => 'failed' ],
		];
	}
}
