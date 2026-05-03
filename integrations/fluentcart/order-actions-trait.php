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
}
