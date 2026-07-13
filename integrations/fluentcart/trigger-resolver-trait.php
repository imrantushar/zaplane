<?php
namespace Zaplane\Integrations\Fluentcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait TriggerResolverTrait {
	public static function resolve_trigger( array $node, array $args ) {
		if ( ! self::is_fluentcart_available() ) {
			return false;
		}

		$event  = self::resolve_node_event( $node, 'trigger' );
		$config = self::resolve_node_config( $node );
		if ( '' === $event ) {
			return false;
		}

		$payload = is_array( $args[0] ?? null ) ? $args[0] : [];

		if ( in_array( $event, self::ORDER_EVENTS, true ) ) {
			return self::resolve_order_event_trigger( $event, $payload, $args, $config );
		}

		if ( in_array( $event, self::SUBSCRIPTION_EVENTS, true ) ) {
			return self::resolve_subscription_event_trigger( $event, $payload, $args, $config );
		}

		if ( in_array( $event, self::PRODUCT_EVENTS, true ) ) {
			return self::resolve_product_event_trigger( $event, $payload, $args, $config );
		}

		if ( 'product_stock_changed' === $event ) {
			return self::resolve_stock_event_trigger( $event, $payload, $args, $config );
		}

		return false;
	}

	private static function resolve_order_event_trigger( string $event, array $payload, array $args, array $config ) {
		$order_payload = self::normalize_order_value( $payload['order'] ?? $payload );
		$order_id      = self::parse_positive_int( $payload['order_id'] ?? ( $order_payload['id'] ?? ( $order_payload['order_id'] ?? 0 ) ) );
		$customer      = self::normalize_payload_value( $payload['customer'] ?? ( $order_payload['customer'] ?? [] ) );
		$customer_id   = self::parse_positive_int( $payload['customer_id'] ?? ( $customer['id'] ?? ( $order_payload['customer_id'] ?? 0 ) ) );

		if ( ! self::matches_id_filter( $config, 'order_id', $order_id ) ) {
			return false;
		}

		if ( ! self::matches_id_filter( $config, 'customer_id', $customer_id ) ) {
			return false;
		}

		if ( self::is_duplicate_order_event( $event, $order_id, $customer_id, $payload ) ) {
			return false;
		}

		return [
			'event'          => $event,
			'event_time'     => current_time( 'mysql' ),
			'args'           => self::normalize_payload_value( array_slice( $args, 0, 4 ) ),
			'order_id'       => $order_id,
			'customer_id'    => $customer_id,
			'order'          => $order_payload,
			'customer'       => $customer,
			'transaction'    => self::normalize_payload_value( $payload['transaction'] ?? [] ),
			'old_status'     => (string) ( $payload['old_status'] ?? '' ),
			'new_status'     => (string) ( $payload['new_status'] ?? '' ),
			'reason'         => (string) ( $payload['reason'] ?? '' ),
			'type'           => (string) ( $payload['type'] ?? '' ),
			'subscription'   => self::normalize_payload_value( $payload['subscription'] ?? [] ),
			'connected_order_ids' => self::normalize_payload_value( $payload['connected_order_ids'] ?? [] ),
			'refunded_items' => self::normalize_payload_value( $payload['refunded_items'] ?? [] ),
			'refunded_amount' => isset( $payload['refunded_amount'] ) ? (float) $payload['refunded_amount'] : 0.0,
		];
	}

	private static function resolve_subscription_event_trigger( string $event, array $payload, array $args, array $config ) {
		$subscription    = self::normalize_payload_value( $payload['subscription'] ?? $payload );
		$subscription_id = self::parse_positive_int( $payload['subscription_id'] ?? ( $subscription['id'] ?? 0 ) );
		$order_payload   = self::normalize_order_value( $payload['order'] ?? [] );
		$order_id        = self::parse_positive_int( $payload['order_id'] ?? ( $order_payload['id'] ?? ( $order_payload['order_id'] ?? 0 ) ) );
		$customer        = self::normalize_payload_value( $payload['customer'] ?? [] );
		$customer_id     = self::parse_positive_int( $payload['customer_id'] ?? ( $customer['id'] ?? ( $subscription['customer_id'] ?? ( $order_payload['customer_id'] ?? 0 ) ) ) );

		if ( ! self::matches_id_filter( $config, 'subscription_id', $subscription_id ) ) {
			return false;
		}

		if ( ! self::matches_id_filter( $config, 'customer_id', $customer_id ) ) {
			return false;
		}

		if ( ! self::matches_id_filter( $config, 'order_id', $order_id ) ) {
			return false;
		}

		return [
			'event'           => $event,
			'event_time'      => current_time( 'mysql' ),
			'args'            => self::normalize_payload_value( array_slice( $args, 0, 4 ) ),
			'subscription_id' => $subscription_id,
			'customer_id'     => $customer_id,
			'order_id'        => $order_id,
			'subscription'    => $subscription,
			'order'           => $order_payload,
			'customer'        => $customer,
			'reason'          => (string) ( $payload['reason'] ?? '' ),
			'meta'            => self::normalize_payload_value( $payload['meta'] ?? [] ),
		];
	}

	private static function resolve_stock_event_trigger( string $event, array $payload, array $args, array $config ) {
		$post_ids = $payload['post_ids'] ?? ( $payload['product_ids'] ?? ( $args[0] ?? [] ) );
		if ( ! is_array( $post_ids ) ) {
			$post_ids = [ $post_ids ];
		}

		$post_ids = array_values(
			array_filter(
				array_map( [ self::class, 'parse_positive_int' ], $post_ids )
			)
		);

		$selected_product_id = self::parse_positive_int( $config['product_id'] ?? 0 );
		if ( $selected_product_id > 0 && ! in_array( $selected_product_id, $post_ids, true ) ) {
			return false;
		}

		return [
			'event'       => $event,
			'event_time'  => current_time( 'mysql' ),
			'args'        => self::normalize_payload_value( array_slice( $args, 0, 4 ) ),
			'product_ids' => $post_ids,
			'other_info'  => self::normalize_payload_value( $payload['other_info'] ?? [] ),
		];
	}

	private static function resolve_product_event_trigger( string $event, array $payload, array $args, array $config ) {
		if ( 'product_created' === $event ) {
			$post_id  = self::parse_positive_int( $args[0] ?? 0 );
			$post     = $args[1] ?? null;
			$is_update = (bool) ( $args[2] ?? false );

			if ( $is_update ) {
				return false;
			}

			if ( $post && isset( $post->post_type ) && 'fluent-products' !== (string) $post->post_type ) {
				return false;
			}

			if ( ! self::matches_id_filter( $config, 'product_id', $post_id ) ) {
				return false;
			}

			return [
				'event'      => $event,
				'event_time' => current_time( 'mysql' ),
				'args'       => self::normalize_payload_value( array_slice( $args, 0, 4 ) ),
				'product_id' => $post_id,
				'product'    => self::load_product_payload( $post_id ),
			];
		}//end if

		if ( 'product_updated' === $event ) {
			$change_data = self::normalize_payload_value( $payload['data'] ?? [] );
			$product     = self::normalize_payload_value( $payload['product'] ?? [] );
			$product_id  = self::parse_positive_int( $product['ID'] ?? ( $product['id'] ?? 0 ) );

			if ( ! self::matches_id_filter( $config, 'product_id', $product_id ) ) {
				return false;
			}

			return [
				'event'      => $event,
				'event_time' => current_time( 'mysql' ),
				'args'       => self::normalize_payload_value( array_slice( $args, 0, 4 ) ),
				'product_id' => $product_id,
				'data'       => $change_data,
				'product'    => $product,
			];
		}

		if ( 'product_duplicated' === $event ) {
			$original_product_id = self::parse_positive_int( $payload['original_product_id'] ?? 0 );
			$new_product_id      = self::parse_positive_int( $payload['new_product_id'] ?? 0 );

			$selected_product_id = self::parse_positive_int( $config['product_id'] ?? 0 );
			if ( $selected_product_id > 0 && $selected_product_id !== $new_product_id && $selected_product_id !== $original_product_id ) {
				return false;
			}

			return [
				'event'               => $event,
				'event_time'          => current_time( 'mysql' ),
				'args'                => self::normalize_payload_value( array_slice( $args, 0, 4 ) ),
				'original_product_id' => $original_product_id,
				'new_product_id'      => $new_product_id,
				'options'             => self::normalize_payload_value( $payload['options'] ?? [] ),
				'product'             => self::load_product_payload( $new_product_id ),
			];
		}

		return false;
	}
}
