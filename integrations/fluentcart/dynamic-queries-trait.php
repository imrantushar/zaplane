<?php
namespace Zaplane\Integrations\Fluentcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait DynamicQueriesTrait {
	public static function get_dynamic_queries(): array {
		return [
			'orders'               => [ self::class, 'orders_query' ],
			'customers'            => [ self::class, 'customers_query' ],
			'subscriptions'        => [ self::class, 'subscriptions_query' ],
			'products'             => [ self::class, 'products_query' ],
			'order_statuses'       => [ self::class, 'order_statuses_query' ],
			'payment_statuses'     => [ self::class, 'payment_statuses_query' ],
			'customer_statuses'    => [ self::class, 'customer_statuses_query' ],
			'subscription_statuses' => [ self::class, 'subscription_statuses_query' ],
			'post_statuses'        => [ self::class, 'post_statuses_query' ],
			'fulfillment_types'    => [ self::class, 'fulfillment_types_query' ],
			'stock_statuses'       => [ self::class, 'stock_statuses_query' ],
			'payment_types'        => [ self::class, 'payment_types_query' ],
		];
	}

	public static function orders_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$options = [
			[
				'name'  => 'any',
				'label' => 'Any Order',
			],
		];

		$rows = self::list_orders(
			max( 1, (int) ( $q['limit'] ?? 50 ) ),
			1,
			trim( (string) ( $q['search'] ?? '' ) ),
			self::parse_positive_int( $q['customer_id'] ?? 0 ),
			'',
			''
		);

		foreach ( $rows as $row ) {
			$order_id = self::parse_positive_int( $row['id'] ?? ( $row['order_id'] ?? 0 ) );
			if ( $order_id <= 0 ) {
				continue;
			}

			$status = (string) ( $row['payment_status'] ?? ( $row['status'] ?? '' ) );
			$label  = '#' . $order_id;
			if ( '' !== $status ) {
				$label .= ' - ' . ucfirst( str_replace( '_', ' ', $status ) );
			}

			$options[] = [
				'name'  => (string) $order_id,
				'label' => $label,
			];
		}

		return $options;
	}

	public static function customers_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$options = [
			[
				'name'  => 'any',
				'label' => 'Any Customer',
			],
		];

		$rows = self::list_customers(
			max( 1, (int) ( $q['limit'] ?? 50 ) ),
			1,
			trim( (string) ( $q['search'] ?? '' ) ),
			''
		);

		foreach ( $rows as $row ) {
			$customer_id = self::parse_positive_int( $row['id'] ?? ( $row['customer_id'] ?? 0 ) );
			if ( $customer_id <= 0 ) {
				continue;
			}

			$name = trim( (string) ( $row['full_name'] ?? ( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) ) ) );
			if ( '' === $name ) {
				$name = (string) ( $row['email'] ?? '' );
			}
			if ( '' === $name ) {
				$name = 'Customer #' . $customer_id;
			}

			$options[] = [
				'name'  => (string) $customer_id,
				'label' => $name . ' (#' . $customer_id . ')',
			];
		}

		return $options;
	}

	public static function subscriptions_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$options = [
			[
				'name'  => 'any',
				'label' => 'Any Subscription',
			],
		];

		$rows = self::list_subscriptions(
			max( 1, (int) ( $q['limit'] ?? 50 ) ),
			1,
			trim( (string) ( $q['search'] ?? '' ) ),
			self::parse_positive_int( $q['customer_id'] ?? 0 ),
			''
		);

		foreach ( $rows as $row ) {
			$subscription_id = self::parse_positive_int( $row['id'] ?? ( $row['subscription_id'] ?? 0 ) );
			if ( $subscription_id <= 0 ) {
				continue;
			}

			$status = (string) ( $row['status'] ?? '' );
			$label  = '#' . $subscription_id;
			if ( '' !== $status ) {
				$label .= ' - ' . ucfirst( str_replace( '_', ' ', $status ) );
			}

			$options[] = [
				'name'  => (string) $subscription_id,
				'label' => $label,
			];
		}

		return $options;
	}

	public static function products_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$options = [
			[
				'name'  => 'any',
				'label' => 'Any Product',
			],
		];

		$rows = self::list_products(
			max( 1, (int) ( $q['limit'] ?? 50 ) ),
			1,
			trim( (string) ( $q['search'] ?? '' ) ),
			''
		);

		foreach ( $rows as $row ) {
			$product_id = self::parse_positive_int( $row['id'] ?? ( $row['ID'] ?? 0 ) );
			if ( $product_id <= 0 ) {
				continue;
			}

			$title = trim( (string) ( $row['post_title'] ?? ( $row['title'] ?? '' ) ) );
			if ( '' === $title ) {
				$title = 'Product';
			}

			$options[] = [
				'name'  => (string) $product_id,
				'label' => $title . ' (#' . $product_id . ')',
			];
		}

		return $options;
	}

	public static function order_statuses_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$rows = self::list_orders(
			max( 1, (int) ( $q['limit'] ?? 50 ) ),
			1,
			trim( (string) ( $q['search'] ?? '' ) ),
			0,
			'',
			''
		);

		$statuses = [];
		foreach ( $rows as $row ) {
			$statuses[] = (string) ( $row['status'] ?? '' );
		}

		return self::build_select_options(
			$statuses,
			[
				'pending',
				'processing',
				'completed',
				'canceled',
				'failed',
				'refunded',
			],
			'Any Order Status'
		);
	}

	public static function payment_statuses_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$rows = self::list_orders(
			max( 1, (int) ( $q['limit'] ?? 50 ) ),
			1,
			trim( (string) ( $q['search'] ?? '' ) ),
			0,
			'',
			''
		);

		$statuses = [];
		foreach ( $rows as $row ) {
			$statuses[] = (string) ( $row['payment_status'] ?? '' );
		}

		return self::build_select_options(
			$statuses,
			[
				'pending',
				'paid',
				'failed',
				'refunded',
			],
			'Any Payment Status'
		);
	}

	public static function customer_statuses_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$rows = self::list_customers(
			max( 1, (int) ( $q['limit'] ?? 50 ) ),
			1,
			trim( (string) ( $q['search'] ?? '' ) ),
			''
		);

		$statuses = [];
		foreach ( $rows as $row ) {
			$statuses[] = (string) ( $row['status'] ?? '' );
		}

		return self::build_select_options(
			$statuses,
			[
				'active',
				'inactive',
			],
			'Any Customer Status'
		);
	}

	public static function subscription_statuses_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$rows = self::list_subscriptions(
			max( 1, (int) ( $q['limit'] ?? 50 ) ),
			1,
			trim( (string) ( $q['search'] ?? '' ) ),
			0,
			''
		);

		$statuses = [];
		foreach ( $rows as $row ) {
			$statuses[] = (string) ( $row['status'] ?? '' );
		}

		return self::build_select_options(
			$statuses,
			[
				'active',
				'canceled',
				'expired',
				'eot',
			],
			'Any Subscription Status'
		);
	}

	public static function post_statuses_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$values = [];
		if ( function_exists( 'get_post_stati' ) ) {
			$post_stati = get_post_stati( [ 'internal' => false ], 'names' );
			if ( is_array( $post_stati ) ) {
				$values = array_values( $post_stati );
			}
		}

		if ( empty( $values ) ) {
			$rows = self::list_products(
				max( 1, (int) ( $q['limit'] ?? 50 ) ),
				1,
				trim( (string) ( $q['search'] ?? '' ) ),
				''
			);
			foreach ( $rows as $row ) {
				$values[] = (string) ( $row['post_status'] ?? '' );
			}
		}

		return self::build_select_options(
			$values,
			[
				'draft',
				'publish',
				'pending',
				'private',
			],
			'Any Post Status'
		);
	}

	public static function fulfillment_types_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$values = [ 'physical', 'digital' ];

		return self::build_select_options( $values, [], 'Any Fulfillment Type' );
	}

	public static function stock_statuses_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$values = [ 'in-stock', 'out-of-stock' ];

		return self::build_select_options( $values, [], 'Any Stock Status' );
	}

	public static function payment_types_query( $q ): array {
		$q = is_array( $q ) ? $q : [];

		$values = [ 'onetime', 'subscription' ];

		return self::build_select_options( $values, [], 'Any Payment Type' );
	}
}
