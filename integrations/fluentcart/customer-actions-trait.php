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
		$limit    = max( 1, (int) ( $config['limit'] ?? 20 ) );
		$page     = max( 1, (int) ( $config['page'] ?? 1 ) );
		$search   = trim( (string) ( $config['search'] ?? '' ) );
		$status   = self::sanitize_status( $config['customer_status'] ?? '' );
		$items    = self::list_customers( $limit, $page, $search, $status );

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
}
