<?php
namespace Zaplane\Integrations\Fluentcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait OrderActionsTrait {

	private static function action_get_order_single( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		if ( $order_id <= 0 ) {
			return self::error_response( 'Order ID is required', $input );
		}

		$order = self::find_model_by_id( self::order_model_class(), $order_id );
		if ( ! $order ) {
			return self::error_response( 'Order not found', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'order' => self::normalize_order_value( $order ),
				]
			)
		);
	}

	private static function action_get_orders_all( array $config, array $input ): array {
		$limit          = max( 1, (int) ( $config['limit'] ?? 20 ) );
		$page           = max( 1, (int) ( $config['page'] ?? 1 ) );
		$search         = trim( (string) ( $config['search'] ?? '' ) );
		$customer_id    = self::parse_positive_int( $config['customer_id'] ?? 0 );
		$order_status   = self::sanitize_status( $config['order_status'] ?? '' );
		$payment_status = self::sanitize_status( $config['payment_status'] ?? '' );

		$items = self::list_orders( $limit, $page, $search, $customer_id, $order_status, $payment_status );

		return self::main_response(
			array_merge(
				$input,
				[
					'items' => $items,
					'total' => count( $items ),
					'limit' => $limit,
					'page'  => $page,
				]
			)
		);
	}

	private static function action_create_order( array $config, array $input ): array {
		$customer_id = self::parse_positive_int( $config['customer_id'] ?? 0 );
		if ( $customer_id <= 0 ) {
			return self::error_response( 'Customer is required', $input );
		}

		$products = is_array( $config['products'] ?? null ) ? $config['products'] : [];
		if ( empty( $products ) ) {
			return self::error_response( 'At least one product is required', $input );
		}

		$order_data              = self::build_order_data_from_config( $config );
		$order_data['customer_id'] = $customer_id;

		$order    = self::create_model( self::order_model_class(), $order_data );
		$order_id = $order ? self::parse_positive_int( self::normalize_payload_value( $order )['id'] ?? 0 ) : 0;

		if ( $order_id <= 0 ) {
			return self::error_response( 'Order creation failed', $input );
		}

		foreach ( $products as $product_row ) {
			if ( ! is_array( $product_row ) ) {
				continue;
			}

			self::create_model(
				self::order_item_model_class(),
				[
					'order_id'   => $order_id,
					'product_id' => self::parse_positive_int( $product_row['product_id'] ?? 0 ),
					'quantity'   => max( 1, (int) ( $product_row['quantity'] ?? 1 ) ),
					'unit_price' => (float) ( $product_row['unit_price'] ?? 0 ),
				]
			);
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'order_id'   => $order_id,
					'order'      => self::normalize_order_value( self::find_model_by_id( self::order_model_class(), $order_id ) ),
					'created'    => true,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_update_order( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		if ( $order_id <= 0 ) {
			return self::error_response( 'Order ID is required', $input );
		}

		$order_data = self::build_order_data_from_config( $config );

		$customer_id = self::parse_positive_int( $config['customer_id'] ?? 0 );
		if ( $customer_id > 0 ) {
			$order_data['customer_id'] = $customer_id;
		}

		if ( empty( $order_data ) ) {
			return self::error_response( 'At least one field is required to update', $input );
		}

		if ( ! self::update_model_by_id( self::order_model_class(), $order_id, $order_data ) ) {
			return self::error_response( 'Order update failed', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'order_id'   => $order_id,
					'order'      => self::normalize_order_value( self::find_model_by_id( self::order_model_class(), $order_id ) ),
					'updated'    => true,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_delete_order( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		if ( $order_id <= 0 ) {
			return self::error_response( 'Order ID is required', $input );
		}

		if ( ! self::delete_model_by_id( self::order_model_class(), $order_id ) ) {
			return self::error_response( 'Order deletion failed', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'order_id' => $order_id, 'deleted' => true, 'event_time' => current_time( 'mysql' ) ] )
		);
	}

	private static function action_update_order_status( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		$status   = self::sanitize_status( $config['order_status'] ?? '' );
		if ( $order_id <= 0 || '' === $status ) {
			return self::error_response( 'Order ID and order status are required', $input );
		}

		if ( ! self::update_model_by_id( self::order_model_class(), $order_id, [ 'status' => $status ] ) ) {
			return self::error_response( 'Order status update failed', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'order_id'     => $order_id,
					'order_status' => $status,
					'updated'      => true,
					'event_time'   => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_update_payment_status( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		$status   = self::sanitize_status( $config['payment_status'] ?? '' );
		if ( $order_id <= 0 || '' === $status ) {
			return self::error_response( 'Order ID and payment status are required', $input );
		}

		if ( ! self::update_model_by_id( self::order_model_class(), $order_id, [ 'payment_status' => $status ] ) ) {
			return self::error_response( 'Payment status update failed', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'order_id'       => $order_id,
					'payment_status' => $status,
					'updated'        => true,
					'event_time'     => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_update_shipping_status( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		$status   = sanitize_key( (string) ( $config['shipping_status'] ?? '' ) );
		if ( $order_id <= 0 || '' === $status ) {
			return self::error_response( 'Order ID and shipping status are required', $input );
		}

		if ( ! self::update_model_by_id( self::order_model_class(), $order_id, [ 'shipping_status' => $status ] ) ) {
			return self::error_response( 'Shipping status update failed', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'order_id'        => $order_id,
					'shipping_status' => $status,
					'updated'         => true,
					'event_time'      => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_get_order_transactions( array $config, array $input ): array {
		return self::relation_list_response( $config, $input, 'order_id', [ 'order' ], self::order_model_class(), 'transactions', 'items' );
	}

	private static function action_get_order_subscriptions( array $config, array $input ): array {
		return self::relation_list_response( $config, $input, 'order_id', [ 'order' ], self::order_model_class(), 'subscriptions', 'items' );
	}

	private static function action_get_order_items( array $config, array $input ): array {
		return self::relation_list_response( $config, $input, 'order_id', [ 'order' ], self::order_model_class(), 'items', 'items' );
	}

	private static function action_get_order_customer( array $config, array $input ): array {
		return self::relation_single_response( $config, $input, 'order_id', [ 'order' ], self::order_model_class(), 'customer', 'customer' );
	}

	private static function action_get_order_coupons( array $config, array $input ): array {
		return self::relation_list_response( $config, $input, 'order_id', [ 'order' ], self::order_model_class(), 'coupons', 'items' );
	}

	private static function action_get_order_shipping_address( array $config, array $input ): array {
		return self::relation_single_response( $config, $input, 'order_id', [ 'order' ], self::order_model_class(), 'shippingAddress', 'shipping_address' );
	}

	private static function action_get_order_billing_address( array $config, array $input ): array {
		return self::relation_single_response( $config, $input, 'order_id', [ 'order' ], self::order_model_class(), 'billingAddress', 'billing_address' );
	}

	private static function action_get_order_addresses( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		if ( $order_id <= 0 ) {
			return self::error_response( 'Order ID is required', $input );
		}

		$order = self::find_model_by_id( self::order_model_class(), $order_id );
		if ( ! $order ) {
			return self::error_response( 'Order not found', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'order_id'         => $order_id,
					'shipping_address' => self::normalize_payload_value( self::get_model_relation( $order, 'shippingAddress' ) ),
					'billing_address'  => self::normalize_payload_value( self::get_model_relation( $order, 'billingAddress' ) ),
				]
			)
		);
	}

	private static function action_get_order_licenses( array $config, array $input ): array {
		return self::relation_list_response( $config, $input, 'order_id', [ 'order' ], self::order_model_class(), 'licenses', 'items' );
	}

	private static function action_get_order_labels( array $config, array $input ): array {
		return self::relation_list_response( $config, $input, 'order_id', [ 'order' ], self::order_model_class(), 'labels', 'items' );
	}

	private static function action_get_order_renewals( array $config, array $input ): array {
		return self::relation_list_response( $config, $input, 'order_id', [ 'order' ], self::order_model_class(), 'renewals', 'items' );
	}

	private static function action_get_order_tax_rates( array $config, array $input ): array {
		return self::relation_list_response( $config, $input, 'order_id', [ 'order' ], self::order_model_class(), 'taxRates', 'items' );
	}

	private static function action_get_total_paid_amount( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		$order    = self::find_model_by_id( self::order_model_class(), $order_id );
		if ( ! $order ) {
			return self::error_response( 'Order not found', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'order_id' => $order_id, 'total_paid_amount' => self::sum_transactions_by_status( $order, [ 'paid', 'completed' ] ) ] )
		);
	}

	private static function action_get_total_refund_amount( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		$order    = self::find_model_by_id( self::order_model_class(), $order_id );
		if ( ! $order ) {
			return self::error_response( 'Order not found', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'order_id' => $order_id, 'total_refund_amount' => self::sum_transactions_by_status( $order, [ 'refunded', 'refund' ] ) ] )
		);
	}

	private static function action_generate_receipt_number( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		if ( $order_id <= 0 ) {
			return self::error_response( 'Order ID is required', $input );
		}

		$order  = self::find_model_by_id( self::order_model_class(), $order_id );
		$number = $order ? (string) self::get_model_relation( $order, 'receipt_number' ) : '';

		if ( '' === $number ) {
			// Placeholder pattern — swap for FluentCart's real receipt-number
			// generator if it exposes one on the Order model.
			$number = 'RCPT-' . str_pad( (string) $order_id, 6, '0', STR_PAD_LEFT );
		}

		return self::main_response(
			array_merge( $input, [ 'order_id' => $order_id, 'receipt_number' => $number ] )
		);
	}

	private static function action_get_receipt_url( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		if ( $order_id <= 0 ) {
			return self::error_response( 'Order ID is required', $input );
		}

		$order = self::find_model_by_id( self::order_model_class(), $order_id );
		$url   = $order ? (string) self::get_model_relation( $order, 'receipt_url' ) : '';

		if ( '' === $url ) {
			// Placeholder — point at FluentCart's real receipt-page URL structure.
			$url = add_query_arg( [ 'fct_order' => $order_id ], home_url( '/' ) );
		}

		return self::main_response(
			array_merge( $input, [ 'order_id' => $order_id, 'receipt_url' => $url ] )
		);
	}

	private static function action_get_transactions_all( array $config, array $input ): array {
		$limit = max( 1, (int) ( $config['limit'] ?? 20 ) );
		$page  = max( 1, (int) ( $config['page'] ?? 1 ) );
		$items = self::list_transactions( $limit, $page, trim( (string) ( $config['search'] ?? '' ) ) );

		return self::main_response(
			array_merge( $input, [ 'items' => $items, 'total' => count( $items ), 'limit' => $limit, 'page' => $page ] )
		);
	}

	private static function action_get_transaction_single( array $config, array $input ): array {
		$transaction_id = self::parse_positive_int( $config['transaction_id'] ?? ( $input['transaction_id'] ?? 0 ) );
		if ( $transaction_id <= 0 ) {
			return self::error_response( 'Transaction ID is required', $input );
		}

		$transaction = self::find_model_by_id( self::transaction_model_class(), $transaction_id );
		if ( ! $transaction ) {
			return self::error_response( 'Transaction not found', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'transaction' => self::normalize_payload_value( $transaction ) ] )
		);
	}

	private static function action_get_refund_transactions( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		$order    = self::find_model_by_id( self::order_model_class(), $order_id );
		if ( ! $order ) {
			return self::error_response( 'Order not found', $input );
		}

		$transactions = self::normalize_payload_value( self::get_model_relation( $order, 'transactions' ) );
		$refunds      = [];
		foreach ( (array) $transactions as $transaction ) {
			$status = strtolower( (string) ( $transaction['status'] ?? '' ) );
			if ( false !== strpos( $status, 'refund' ) ) {
				$refunds[] = $transaction;
			}
		}

		return self::main_response(
			array_merge( $input, [ 'order_id' => $order_id, 'items' => $refunds ] )
		);
	}

	private static function action_get_latest_transaction( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		$order    = self::find_model_by_id( self::order_model_class(), $order_id );
		if ( ! $order ) {
			return self::error_response( 'Order not found', $input );
		}

		$transactions = self::normalize_payload_value( self::get_model_relation( $order, 'transactions' ) );
		$latest       = ( is_array( $transactions ) && ! empty( $transactions ) ) ? end( $transactions ) : [];

		return self::main_response(
			array_merge( $input, [ 'order_id' => $order_id, 'transaction' => $latest ] )
		);
	}

	private static function action_get_order_metadata_all( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		if ( $order_id <= 0 ) {
			return self::error_response( 'Order ID is required', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'order_id' => $order_id, 'metadata' => self::get_meta_all( self::order_meta_model_class(), 'order_id', $order_id ) ] )
		);
	}

	private static function action_get_order_metadata_single( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		$key      = trim( (string) ( $config['metadata_key'] ?? '' ) );
		if ( $order_id <= 0 || '' === $key ) {
			return self::error_response( 'Order ID and metadata key are required', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'order_id' => $order_id, 'metadata_key' => $key, 'metadata' => self::get_meta_single( self::order_meta_model_class(), 'order_id', $order_id, $key ) ] )
		);
	}

	private static function action_update_order_metadata( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		$key      = trim( (string) ( $config['metadata_key'] ?? '' ) );
		$value    = $config['metadata_value'] ?? '';
		if ( $order_id <= 0 || '' === $key ) {
			return self::error_response( 'Order ID and metadata key are required', $input );
		}

		if ( ! self::update_meta( self::order_meta_model_class(), 'order_id', $order_id, $key, $value ) ) {
			return self::error_response( 'Metadata update failed', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'order_id' => $order_id, 'metadata_key' => $key, 'metadata_value' => $value, 'updated' => true ] )
		);
	}

	private static function action_delete_order_metadata( array $config, array $input ): array {
		$order_id = self::resolve_entity_id_for_action( $config, $input, 'order_id', [ 'order' ] );
		$key      = trim( (string) ( $config['metadata_key'] ?? '' ) );
		if ( $order_id <= 0 || '' === $key ) {
			return self::error_response( 'Order ID and metadata key are required', $input );
		}

		if ( ! self::delete_meta( self::order_meta_model_class(), 'order_id', $order_id, $key ) ) {
			return self::error_response( 'Metadata deletion failed', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'order_id' => $order_id, 'metadata_key' => $key, 'deleted' => true ] )
		);
	}

	private static function build_order_data_from_config( array $config ): array {
		$map = [
			'order_status'          => 'status',
			'shipping_status'       => 'shipping_status',
			'fulfillment_type'      => 'fulfillment_type',
			'order_type'            => 'type',
			'order_mode'            => 'mode',
			'payment_method'        => 'payment_method',
			'payment_method_title'  => 'payment_method_title',
			'payment_status'        => 'payment_status',
			'currency_code'         => 'currency',
			'subtotal'              => 'subtotal',
			'discount_tax'          => 'discount_tax',
			'manual_discount_total' => 'manual_discount_total',
			'coupon_discount_total' => 'coupon_discount_total',
			'shipping_tax'          => 'shipping_tax',
			'shipping_total'        => 'shipping_total',
			'tax_total'             => 'tax_total',
			'total_amount'          => 'total_amount',
			'exchange_rate'         => 'exchange_rate',
			'tax_behavior'          => 'tax_behavior',
			'order_note'            => 'note',
		];

		$data = [];
		foreach ( $map as $config_key => $column ) {
			if ( ! array_key_exists( $config_key, $config ) || '' === $config[ $config_key ] ) {
				continue;
			}
			$data[ $column ] = $config[ $config_key ];
		}

		return $data;
	}
}
