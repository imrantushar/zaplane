<?php
namespace Zaplane\Integrations\Fluentcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SubscriptionActionsTrait {

	private static function action_get_subscription_single( array $config, array $input ): array {
		$subscription_id = self::resolve_entity_id_for_action( $config, $input, 'subscription_id', [ 'subscription' ] );
		if ( $subscription_id <= 0 ) {
			return self::error_response( 'Subscription ID is required', $input );
		}

		$subscription = self::find_model_by_id( self::subscription_model_class(), $subscription_id );
		if ( ! $subscription ) {
			return self::error_response( 'Subscription not found', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'subscription' => self::normalize_payload_value( $subscription ),
				]
			)
		);
	}

	private static function action_get_subscriptions_all( array $config, array $input ): array {
		$limit       = max( 1, (int) ( $config['limit'] ?? 20 ) );
		$page        = max( 1, (int) ( $config['page'] ?? 1 ) );
		$search      = trim( (string) ( $config['search'] ?? '' ) );
		$customer_id = self::parse_positive_int( $config['customer_id'] ?? 0 );
		$status      = self::sanitize_status( $config['subscription_status'] ?? '' );
		$items       = self::list_subscriptions( $limit, $page, $search, $customer_id, $status );

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
