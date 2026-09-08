<?php
namespace Zaplane\Integrations\Fluentcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CustomerActionsTrait {

	private static function action_get_customer_single( array $config, array $input ): array {
		$customer_id = self::resolve_entity_id_for_action( $config, $input, 'customer_id', [ 'customer' ] );
		if ( $customer_id <= 0 ) {
			return self::error_response( 'Customer ID is required', $input );
		}

		$customer = self::find_model_by_id( self::customer_model_class(), $customer_id );
		if ( ! $customer ) {
			return self::error_response( 'Customer not found', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'customer' => self::normalize_payload_value( $customer ),
				]
			)
		);
	}

	private static function action_get_customers_all( array $config, array $input ): array {
		$limit  = max( 1, (int) ( $config['limit'] ?? 20 ) );
		$page   = max( 1, (int) ( $config['page'] ?? 1 ) );
		$search = trim( (string) ( $config['search'] ?? '' ) );
		$status = self::sanitize_status( $config['customer_status'] ?? '' );
		$items  = self::list_customers( $limit, $page, $search, $status );

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

	private static function action_create_customer( array $config, array $input ): array {
		$data = self::build_customer_data_from_mapping( $config );
		if ( empty( $data ) ) {
			return self::error_response( 'At least one customer field is required', $input );
		}

		$customer = self::create_model( self::customer_model_class(), $data );
		if ( ! $customer ) {
			return self::error_response( 'Customer creation failed', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'customer' => self::normalize_payload_value( $customer ), 'created' => true, 'event_time' => current_time( 'mysql' ) ] )
		);
	}

	private static function action_update_customer( array $config, array $input ): array {
		$customer_id = self::resolve_entity_id_for_action( $config, $input, 'customer_id', [ 'customer' ] );
		if ( $customer_id <= 0 ) {
			return self::error_response( 'Customer ID is required', $input );
		}

		$data = self::build_customer_data_from_mapping( $config );
		if ( empty( $data ) ) {
			return self::error_response( 'At least one field is required to update', $input );
		}

		if ( ! self::update_model_by_id( self::customer_model_class(), $customer_id, $data ) ) {
			return self::error_response( 'Customer update failed', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'customer_id' => $customer_id,
					'customer'    => self::normalize_payload_value( self::find_model_by_id( self::customer_model_class(), $customer_id ) ),
					'updated'     => true,
					'event_time'  => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_delete_customer( array $config, array $input ): array {
		$customer_id = self::resolve_entity_id_for_action( $config, $input, 'customer_id', [ 'customer' ] );
		if ( $customer_id <= 0 ) {
			return self::error_response( 'Customer ID is required', $input );
		}

		if ( ! self::delete_model_by_id( self::customer_model_class(), $customer_id ) ) {
			return self::error_response( 'Customer deletion failed', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'customer_id' => $customer_id, 'deleted' => true ] )
		);
	}

	private static function action_get_customer_orders( array $config, array $input ): array {
		return self::relation_list_response( $config, $input, 'customer_id', [ 'customer' ], self::customer_model_class(), 'orders', 'items' );
	}

	private static function action_get_customer_subscriptions( array $config, array $input ): array {
		return self::relation_list_response( $config, $input, 'customer_id', [ 'customer' ], self::customer_model_class(), 'subscriptions', 'items' );
	}

	private static function action_get_customer_shipping_address( array $config, array $input ): array {
		return self::relation_single_response( $config, $input, 'customer_id', [ 'customer' ], self::customer_model_class(), 'shippingAddress', 'shipping_address' );
	}

	private static function action_get_customer_billing_address( array $config, array $input ): array {
		return self::relation_single_response( $config, $input, 'customer_id', [ 'customer' ], self::customer_model_class(), 'billingAddress', 'billing_address' );
	}

	private static function action_get_customer_primary_shipping_address( array $config, array $input ): array {
		return self::relation_single_response( $config, $input, 'customer_id', [ 'customer' ], self::customer_model_class(), 'primaryShippingAddress', 'shipping_address' );
	}

	private static function action_get_customer_primary_billing_address( array $config, array $input ): array {
		return self::relation_single_response( $config, $input, 'customer_id', [ 'customer' ], self::customer_model_class(), 'primaryBillingAddress', 'billing_address' );
	}

	private static function action_get_customer_metadata( array $config, array $input ): array {
		$customer_id = self::resolve_entity_id_for_action( $config, $input, 'customer_id', [ 'customer' ] );
		if ( $customer_id <= 0 ) {
			return self::error_response( 'Customer ID is required', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'customer_id' => $customer_id, 'metadata' => self::get_meta_all( self::customer_meta_model_class(), 'customer_id', $customer_id ) ] )
		);
	}

	private static function action_get_customer_labels( array $config, array $input ): array {
		return self::relation_list_response( $config, $input, 'customer_id', [ 'customer' ], self::customer_model_class(), 'labels', 'items' );
	}

	private static function build_customer_data_from_mapping( array $config ): array {
		$rows = is_array( $config['customer_fields_mapping'] ?? null ) ? $config['customer_fields_mapping'] : [];
		$data = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$field = sanitize_key( (string) ( $row['customer_field'] ?? '' ) );
			if ( '' === $field ) {
				continue;
			}

			$data[ $field ] = $row['value'] ?? '';
		}

		return $data;
	}
}
