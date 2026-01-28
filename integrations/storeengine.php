<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\WordPressPluginIntegration;

class Storeengine extends WordPressPluginIntegration {

	/* ---------------------------------------------------------
	 * Identity
	 * --------------------------------------------------------- */

	public static function get_slug(): string { return 'storeengine'; }
	public static function get_name(): string { return 'StoreEngine'; }
	public static function get_icon(): string { return 'storeengine'; }

	/* ---------------------------------------------------------
	 * Triggers
	 * --------------------------------------------------------- */

	public static function get_triggers(): array {
		return [
			'product_purchased'            => [ 'label' => 'Product Purchased',                   'hook' => 'storeengine/checkout/after_place_order' ],
			'created_product'              => [ 'label' => 'Created Product',                     'hook' => 'storeengine/product/created' ],
			'updated_product'              => [ 'label' => 'Updated Product',                     'hook' => 'storeengine/product/updated' ],
			'order_status_update'          => [ 'label' => 'Order Status Updated',                'hook' => 'storeengine/order/status_changed' ],
			'order_status_on_hold'         => [ 'label' => 'Order Status Set To On Hold',         'hook' => 'storeengine/order_status_on_hold' ],
			'order_status_pending_payment' => [ 'label' => 'Order Status Set To Pending Payment', 'hook' => 'storeengine/order_status_pending_payment' ],
			'order_status_processing'      => [ 'label' => 'Order Status Set To Processing',      'hook' => 'storeengine/order_status_processing' ],
			'order_status_completed'       => [ 'label' => 'Order Status Set To Completed',       'hook' => 'storeengine/order_status_completed' ],
			'order_status_cancelled'       => [ 'label' => 'Order Status Set To Cancelled',       'hook' => 'storeengine/order_status_cancelled' ],
			'order_status_draft'           => [ 'label' => 'Order Status Set To Draft',           'hook' => 'storeengine/order_status_auto-draft' ],
			'order_status_trash'           => [ 'label' => 'Order Status Set To Trash',           'hook' => 'storeengine/order_status_trash' ],
			'order_customer_note_added'    => [ 'label' => 'Customer Note Added to Order',        'hook' => 'storeengine/order/new_customer_note' ],
			'order_customer_note_deleted'  => [ 'label' => 'Customer Note Deleted From Order',    'hook' => 'storeengine/order/note_deleted' ],
			'order_restored'               => [ 'label' => 'Order Restored',                      'hook' => 'storeengine/order/status_changed' ],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( $trigger === 'updated_product' ) {
			return [
				[ 'key' => 'product_id', 'label' => 'ID', 'type' => 'expression', 'required' => true ],
			];
		}
		return [];
	}

	/* ---------------------------------------------------------
	 * Trigger Payload Resolution
	 * --------------------------------------------------------- */

	public static function resolve_trigger( array $node, array $args ) {
		$event = $node['event'] ?? '';

		// Product triggers
		if ( $event === 'product_purchased' ) {
			return self::resolve_order( $args[0] ?? 0 );
		}

		if ( $event === 'created_product' || $event === 'updated_product' ) {
			return self::resolve_product( $args[0] ?? 0 );
		}

		// Order status triggers
		if ( in_array( $event, [
			'order_status_update', 'order_status_on_hold', 'order_status_pending_payment',
			'order_status_processing', 'order_status_completed', 'order_status_cancelled',
			'order_status_draft', 'order_status_trash',
		], true ) ) {
			$order_id = $args[0] ?? 0;
			$payload  = self::resolve_order( $order_id );
			if ( ! $payload ) return false;

			$payload['old_status'] = $args[1] ?? '';
			$payload['new_status'] = $args[2] ?? '';
			return $payload;
		}

		// Order restored — only fire when coming from trash
		if ( $event === 'order_restored' ) {
			$old_status = $args[1] ?? '';
			if ( $old_status !== 'trash' ) return false;

			$payload = self::resolve_order( $args[0] ?? 0 );
			if ( ! $payload ) return false;

			$payload['old_status']      = $old_status;
			$payload['restored_status'] = $args[2] ?? '';
			return $payload;
		}

		// Customer note triggers
		if ( $event === 'order_customer_note_added' ) {
			$payload = self::resolve_order( $args[0] ?? 0 );
			if ( ! $payload ) return false;

			$payload['note']        = $args[1] ?? '';
			$payload['note_author'] = $args[2] ?? '';
			return $payload;
		}

		if ( $event === 'order_customer_note_deleted' ) {
			$payload = self::resolve_order( $args[0] ?? 0 );
			if ( ! $payload ) return false;

			$payload['deleted_note'] = $args[1] ?? '';
			$payload['deleted_by']   = $args[2] ?? '';
			return $payload;
		}

		return false;
	}

	/* ---------------------------------------------------------
	 * Actions
	 * --------------------------------------------------------- */

	public static function get_actions(): array {
		return [
			'create_product' => [ 'label' => 'Create Product' ],
			'update_product' => [ 'label' => 'Update Product' ],
			'create_order'   => [ 'label' => 'Create Order' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [
			'create_product' => [
				[ 'key' => 'product_name', 'label' => 'Product Name',  'type' => 'text', 'required' => true ],
				[ 'key' => 'price',        'label' => 'Price',         'type' => 'number', 'required' => true ],
				[ 'key' => 'description',  'label' => 'Description',   'type' => 'textarea' ],
				[ 'key' => 'status',       'label' => 'Status',        'type' => 'select', 'options' => [
					[ 'label' => 'Draft',   'value' => 'draft' ],
					[ 'label' => 'Publish', 'value' => 'publish' ],
				] ],
			],
			'update_product' => [
				[ 'key' => 'product_id',   'label' => 'Product ID',    'type' => 'expression', 'required' => true ],
				[ 'key' => 'product_name', 'label' => 'Product Name',  'type' => 'text' ],
				[ 'key' => 'price',        'label' => 'Price',         'type' => 'number' ],
				[ 'key' => 'description',  'label' => 'Description',   'type' => 'textarea' ],
				[ 'key' => 'status',       'label' => 'Status',        'type' => 'select', 'options' => [
					[ 'label' => 'Draft',   'value' => 'draft' ],
					[ 'label' => 'Publish', 'value' => 'publish' ],
				] ],
			],
			'create_order' => [
				[ 'key' => 'customer_email', 'label' => 'Customer Email', 'type' => 'expression', 'required' => true ],
				[ 'key' => 'status',         'label' => 'Order Status',   'type' => 'select', 'options' => [
					[ 'label' => 'Pending',    'value' => 'pending' ],
					[ 'label' => 'Processing', 'value' => 'processing' ],
				], 'default' => 'pending' ],
			],
		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? $node['config']['data'] ?? [];
		$event  = $node['data']['event'] ?? $node['config']['action'] ?? '';

		if ( $event === 'create_product' ) {
			$name = $config['product_name'] ?? '';
			$price = $config['price'] ?? '';

			if ( ! $name || $price === '' ) {
				throw new \Exception( 'Product name and price are required' );
			}

			$product_id = storeengine_create_product( [
				'name'        => $name,
				'price'       => $price,
				'description' => $config['description'] ?? '',
				'status'      => $config['status'] ?? 'publish',
			] );

			if ( ! $product_id ) {
				throw new \Exception( 'Failed to create product' );
			}

			return [ 'port' => 'main', 'data' => array_merge( $input, [
				'product_id'   => $product_id,
				'product_name' => $name,
				'price'        => $price,
			] ) ];
		}

		if ( $event === 'update_product' ) {
			$product_id = $config['product_id'] ?? 0;
			if ( ! $product_id ) throw new \Exception( 'Product ID is required' );

			$update_data = [];
			if ( ! empty( $config['product_name'] ) ) $update_data['name']        = $config['product_name'];
			if ( ! empty( $config['price'] ) )         $update_data['price']       = $config['price'];
			if ( ! empty( $config['description'] ) )   $update_data['description'] = $config['description'];
			if ( ! empty( $config['status'] ) )        $update_data['status']      = $config['status'];

			storeengine_update_product( $product_id, $update_data );

			return [ 'port' => 'main', 'data' => array_merge( $input, [ 'product_id' => $product_id, 'updated' => true ] ) ];
		}

		if ( $event === 'create_order' ) {
			$order_id = storeengine_create_order( [
				'customer_email' => $config['customer_email'] ?? '',
				'status'         => $config['status'] ?? 'pending',
			] );

			if ( ! $order_id ) throw new \Exception( 'Failed to create order' );

			return [ 'port' => 'main', 'data' => array_merge( $input, [ 'order_id' => $order_id ] ) ];
		}

		return [ 'port' => 'main', 'data' => $input ];
	}

	/* ---------------------------------------------------------
	 * Payload Helpers
	 * --------------------------------------------------------- */

	private static function resolve_order( int $order_id ) {
		if ( ! $order_id || ! function_exists( 'storeengine_get_order' ) ) return false;

		$order = storeengine_get_order( $order_id );
		if ( ! $order ) return false;

		return [
			'order_id'       => $order_id,
			'order_number'   => $order->get_order_number(),
			'order_status'   => $order->get_status(),
			'total'          => $order->get_total(),
			'currency'       => $order->get_currency(),
			'payment_method' => $order->get_payment_method(),
			'customer_email' => $order->get_customer_email(),
			'customer_name'  => $order->get_customer_name(),
			'items'          => $order->get_items(),
		];
	}

	private static function resolve_product( int $product_id ) {
		if ( ! $product_id || ! function_exists( 'storeengine_get_product' ) ) return false;

		$product = storeengine_get_product( $product_id );
		if ( ! $product ) return false;

		return [
			'product_id'   => $product_id,
			'product_name' => $product->get_name(),
			'price'        => $product->get_price(),
			'description'  => $product->get_description(),
			'status'       => $product->get_status(),
		];
	}
}
