<?php
namespace Zaplane\Integrations\Fluentcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Helper {

	private static function create_product_schema_fields(): array {
		return [
			[
				'key'      => 'post_title',
				'label'    => 'Product Title',
				'type'     => 'text',
				'required' => true,
			],
			[
				'key'   => 'post_name',
				'label' => 'Product Slug',
				'type'  => 'text',
			],
			[
				'key'   => 'post_content',
				'label' => 'Product Description',
				'type'  => 'textarea',
			],
			[
				'key'   => 'post_excerpt',
				'label' => 'Short Description',
				'type'  => 'textarea',
			],
			[
				'key'      => 'post_status',
				'label'    => 'Post Status',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'fluentcart',
					'query'       => 'post_statuses',
					'select'      => [ 'name', 'label' ],
				],
				'default'  => 'draft',
				'required' => false,
			],
			[
				'key'      => 'fulfillment_type',
				'label'    => 'Fulfillment Type',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'fluentcart',
					'query'       => 'fulfillment_types',
					'select'      => [ 'name', 'label' ],
				],
				'default'  => 'physical',
				'required' => false,
			],
			[
				'key'      => 'stock_status',
				'label'    => 'Stock Status',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'fluentcart',
					'query'       => 'stock_statuses',
					'select'      => [ 'name', 'label' ],
				],
				'default'  => 'in-stock',
				'required' => false,
			],
			[
				'key'      => 'payment_type',
				'label'    => 'Payment Type',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'fluentcart',
					'query'       => 'payment_types',
					'select'      => [ 'name', 'label' ],
				],
				'default'  => 'onetime',
				'required' => false,
			],
			[
				'key'     => 'total_stock',
				'label'   => 'Total Stock',
				'type'    => 'number',
				'default' => 1,
			],
		];
	}

	private static function sanitize_select_config_value( array $config, string $key, string $default ): string {
		$value = sanitize_key( (string) ( $config[ $key ] ?? $default ) );
		if ( '' === $value || 'any' === $value ) {
			return $default;
		}

		return $value;
	}

	private static function build_create_product_post_args( array $config, string $post_title ): array {
		$post_name = sanitize_title( (string) ( $config['post_name'] ?? '' ) );
		if ( '' === $post_name ) {
			$post_name = sanitize_title( $post_title );
		}

		return [
			'post_title'   => $post_title,
			'post_content' => trim( (string) ( $config['post_content'] ?? '' ) ),
			'post_excerpt' => trim( (string) ( $config['post_excerpt'] ?? '' ) ),
			'post_status'  => self::sanitize_select_config_value( $config, 'post_status', 'draft' ),
			'post_type'    => 'fluent-products',
			'post_name'    => $post_name,
		];
	}

	private static function build_create_product_meta_args( array $config ): array {
		return [
			'fulfillment_type' => self::sanitize_select_config_value( $config, 'fulfillment_type', 'physical' ),
			'stock_status'     => self::sanitize_select_config_value( $config, 'stock_status', 'in-stock' ),
			'payment_type'     => self::sanitize_select_config_value( $config, 'payment_type', 'onetime' ),
			'total_stock'      => max( 0, (int) ( $config['total_stock'] ?? 1 ) ),
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'orders'               => [ self::class, 'orders_query' ],
			'customers'            => [ self::class, 'customers_query' ],
			'subscriptions'        => [ self::class, 'subscriptions_query' ],
			'products'             => [ self::class, 'products_query' ],
			'order_statuses'       => [ self::class, 'order_statuses_query' ],
			'payment_statuses'     => [ self::class, 'payment_statuses_query' ],
			'customer_statuses'    => [ self::class, 'customer_statuses_query' ],
			'subscription_statuses'=> [ self::class, 'subscription_statuses_query' ],
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
			'refunded_amount'=> isset( $payload['refunded_amount'] ) ? (float) $payload['refunded_amount'] : 0.0,
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
		}

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

	private static function list_orders( int $limit, int $page, string $search, int $customer_id, string $order_status, string $payment_status ): array {
		$filters = [];
		if ( $customer_id > 0 ) {
			$filters['customer_id'] = $customer_id;
		}
		if ( '' !== $order_status && 'any' !== $order_status ) {
			$filters['status'] = $order_status;
		}
		if ( '' !== $payment_status && 'any' !== $payment_status ) {
			$filters['payment_status'] = $payment_status;
		}

		return self::query_model_collection( self::order_model_class(), $filters, $limit, $page, $search );
	}

	private static function list_customers( int $limit, int $page, string $search, string $status ): array {
		$filters = [];
		if ( '' !== $status && 'any' !== $status ) {
			$filters['status'] = $status;
		}

		return self::query_model_collection( self::customer_model_class(), $filters, $limit, $page, $search );
	}

	private static function list_subscriptions( int $limit, int $page, string $search, int $customer_id, string $status ): array {
		$filters = [];
		if ( $customer_id > 0 ) {
			$filters['customer_id'] = $customer_id;
		}
		if ( '' !== $status && 'any' !== $status ) {
			$filters['status'] = $status;
		}

		return self::query_model_collection( self::subscription_model_class(), $filters, $limit, $page, $search );
	}

	private static function list_products( int $limit, int $page, string $search, string $post_status ): array {
		$filters = [];
		if ( '' !== $post_status && 'any' !== $post_status ) {
			$filters['post_status'] = $post_status;
		}

		return self::query_model_collection( self::product_model_class(), $filters, $limit, $page, $search );
	}

	private static function query_model_collection( string $model_class, array $filters, int $limit, int $page, string $search ): array {
		$query = self::new_model_query( $model_class );
		if ( ! $query ) {
			return [];
		}

		if ( '' !== $search && method_exists( $query, 'searchBy' ) ) {
			$query->searchBy( $search );
		}

		foreach ( $filters as $key => $value ) {
			if ( ! method_exists( $query, 'where' ) ) {
				continue;
			}

			$query->where( $key, $value );
		}

		if ( method_exists( $query, 'orderBy' ) ) {
			$query->orderBy( self::model_order_column( $model_class ), 'DESC' );
		}

		if ( method_exists( $query, 'limit' ) ) {
			$query->limit( $limit );
		}

		if ( method_exists( $query, 'offset' ) ) {
			$query->offset( max( 0, ( $page - 1 ) * $limit ) );
		}

		$rows = method_exists( $query, 'get' ) ? $query->get() : [];
		if ( is_object( $rows ) && method_exists( $rows, 'toArray' ) ) {
			$rows = $rows->toArray();
		}

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$items = [];
		foreach ( $rows as $row ) {
			$items[] = self::normalize_payload_value( $row );
		}

		return $items;
	}

	private static function find_model_by_id( string $model_class, int $id ) {
		if ( $id <= 0 ) {
			return null;
		}

		$query = self::new_model_query( $model_class );
		if ( ! $query || ! method_exists( $query, 'find' ) ) {
			return null;
		}

		return $query->find( $id );
	}

	private static function new_model_query( string $model_class ) {
		if ( '' === $model_class || ! class_exists( $model_class ) || ! method_exists( $model_class, 'query' ) ) {
			return null;
		}

		$query = $model_class::query();
		return is_object( $query ) ? $query : null;
	}

	private static function order_model_class(): string {
		return '\\FluentCart\\App\\Models\\Order';
	}

	private static function customer_model_class(): string {
		return '\\FluentCart\\App\\Models\\Customer';
	}

	private static function subscription_model_class(): string {
		return '\\FluentCart\\App\\Models\\Subscription';
	}

	private static function product_model_class(): string {
		return '\\FluentCart\\App\\Models\\Product';
	}

	private static function is_fluentcart_available(): bool {
		return function_exists( 'fluentCart' ) || class_exists( '\\FluentCart\\App\\App' ) || class_exists( self::order_model_class() );
	}

	private static function model_order_column( string $model_class ): string {
		return self::product_model_class() === $model_class ? 'ID' : 'id';
	}

	private static function load_product_payload( int $product_id ): array {
		if ( $product_id <= 0 ) {
			return [];
		}

		$product = self::find_model_by_id( self::product_model_class(), $product_id );
		if ( $product ) {
			return (array) self::normalize_payload_value( $product );
		}

		$post = get_post( $product_id );
		if ( ! $post ) {
			return [];
		}

		return [
			'ID'          => self::parse_positive_int( $post->ID ?? 0 ),
			'id'          => self::parse_positive_int( $post->ID ?? 0 ),
			'post_title'  => (string) ( $post->post_title ?? '' ),
			'post_status' => (string) ( $post->post_status ?? '' ),
			'post_type'   => (string) ( $post->post_type ?? '' ),
		];
	}

	private static function bootstrap_created_product_meta(
		int $product_id,
		string $post_title,
		string $fulfillment_type,
		string $stock_status,
		string $payment_type,
		int $total_stock
	): void {
		if ( $product_id <= 0 ) {
			return;
		}

		$stock_status = sanitize_key( $stock_status );
		if ( '' === $stock_status || 'any' === $stock_status ) {
			$stock_status = 'in-stock';
		}

		$payment_type = sanitize_key( $payment_type );
		if ( '' === $payment_type || 'any' === $payment_type ) {
			$payment_type = 'onetime';
		}

		$total_stock = max( 0, $total_stock );
		$available   = $total_stock > 0 ? 1 : 0;

		$product_detail_class = '\\FluentCart\\App\\Models\\ProductDetail';
		if ( class_exists( $product_detail_class ) && method_exists( $product_detail_class, 'query' ) ) {
			try {
				$product_detail_class::query()->create(
					[
						'post_id'          => $product_id,
						'fulfillment_type' => $fulfillment_type,
						'variation_type'   => 'simple',
						'manage_stock'     => $available,
						'other_info'       => [],
					]
				);
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		$product_variation_class = '\\FluentCart\\App\\Models\\ProductVariation';
		if ( class_exists( $product_variation_class ) && method_exists( $product_variation_class, 'query' ) ) {
			try {
				$product_variation_class::query()->create(
					[
						'post_id'          => $product_id,
						'serial_index'     => 1,
						'variation_title'  => $post_title,
						'stock_status'     => $stock_status,
						'payment_type'     => $payment_type,
						'total_stock'      => $total_stock,
						'available'        => $available,
						'fulfillment_type' => $fulfillment_type,
						'other_info'       => [
							'payment_type'      => $payment_type,
							'is_bundle_product' => 'no',
						],
					]
				);
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}
	}

	private static function resolve_node_event( array $node, string $type ): string {
		$candidates = [
			$node['event'] ?? null,
			$node['data']['event'] ?? null,
			$node[ $type ] ?? null,
			$node['data'][ $type ] ?? null,
			$node['config']['event'] ?? null,
			$node['config'][ $type ] ?? null,
		];

		foreach ( $candidates as $candidate ) {
			if ( ! is_scalar( $candidate ) ) {
				continue;
			}

			$event = trim( (string) $candidate );
			if ( '' !== $event ) {
				return $event;
			}
		}

		return '';
	}

	private static function resolve_node_config( array $node ): array {
		if ( isset( $node['data']['config'] ) && is_array( $node['data']['config'] ) ) {
			return $node['data']['config'];
		}

		if ( isset( $node['config']['data'] ) && is_array( $node['config']['data'] ) ) {
			return $node['config']['data'];
		}

		if ( isset( $node['config']['config'] ) && is_array( $node['config']['config'] ) ) {
			return $node['config']['config'];
		}

		if ( isset( $node['config'] ) && is_array( $node['config'] ) ) {
			return self::strip_structural_node_keys( $node['config'] );
		}

		return self::strip_structural_node_keys( $node );
	}

	private static function strip_structural_node_keys( array $config ): array {
		unset( $config['app'], $config['event'], $config['action'], $config['trigger'], $config['type'], $config['hook'], $config['label'], $config['connection_id'], $config['data'] );
		return $config;
	}

	private static function resolve_entity_id_for_action( array $config, array $input, string $id_key, array $entity_keys ): int {
		$id = self::parse_positive_int( $config[ $id_key ] ?? 0 );
		if ( $id > 0 ) {
			return $id;
		}

		$id = self::parse_positive_int( $input[ $id_key ] ?? 0 );
		if ( $id > 0 ) {
			return $id;
		}

		foreach ( $entity_keys as $entity_key ) {
			$id = self::parse_positive_int( $input[ $entity_key ] ?? null );
			if ( $id > 0 ) {
				return $id;
			}
		}

		return 0;
	}

	private static function normalize_order_value( $value ): array {
		$payload = self::normalize_payload_value( $value );
		if ( ! is_array( $payload ) ) {
			$payload = [];
		}

		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'get_id' ) ) {
				$payload['id'] = self::parse_positive_int( $value->get_id() );
			}
			if ( method_exists( $value, 'get_status' ) ) {
				$payload['status'] = (string) $value->get_status();
			}
			if ( method_exists( $value, 'get_total' ) ) {
				$payload['total_amount'] = (float) $value->get_total();
			}
			if ( method_exists( $value, 'get_currency' ) ) {
				$payload['currency'] = (string) $value->get_currency();
			}
			if ( method_exists( $value, 'get_customer_id' ) ) {
				$payload['customer_id'] = (int) $value->get_customer_id();
			}
		}

		$order_id = self::parse_positive_int( $payload['id'] ?? ( $payload['order_id'] ?? 0 ) );
		if ( $order_id > 0 ) {
			$payload['id']       = $order_id;
			$payload['order_id'] = $order_id;
		}

		return $payload;
	}

	private static function normalize_payload_value( $value ) {
		if ( null === $value || is_scalar( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::normalize_payload_value( $item );
			}

			return $value;
		}

		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'toArray' ) ) {
				return self::normalize_payload_value( $value->toArray() );
			}

			if ( method_exists( $value, 'get_id' ) ) {
				return [
					'id' => self::parse_positive_int( $value->get_id() ),
				];
			}
		}

		return [];
	}

	private static function matches_id_filter( array $config, string $config_key, int $actual_id ): bool {
		if ( ! array_key_exists( $config_key, $config ) ) {
			return true;
		}

		$selected = $config[ $config_key ];
		if ( self::is_any_selection( $selected ) ) {
			return true;
		}

		$selected_id = self::parse_positive_int( $selected );
		if ( $selected_id <= 0 ) {
			return true;
		}

		if ( $actual_id <= 0 ) {
			return false;
		}

		return $selected_id === $actual_id;
	}

	private static function is_any_selection( $value ): bool {
		if ( null === $value || false === $value ) {
			return true;
		}

		if ( is_string( $value ) ) {
			$normalized = sanitize_key( trim( $value ) );
			return '' === $normalized || 'any' === $normalized;
		}

		if ( is_array( $value ) ) {
			foreach ( [ 'value', 'name', 'id' ] as $key ) {
				if ( array_key_exists( $key, $value ) && self::is_any_selection( $value[ $key ] ) ) {
					return true;
				}
			}

			return false;
		}

		if ( is_object( $value ) ) {
			foreach ( [ 'value', 'name', 'id' ] as $key ) {
				if ( isset( $value->{$key} ) && self::is_any_selection( $value->{$key} ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function sanitize_status( $value ): string {
		$status = sanitize_key( (string) $value );
		return 'any' === $status ? '' : $status;
	}

	private static function build_select_options( array $values, array $fallback_values, string $any_label ): array {
		$options  = [
			[
				'name'  => 'any',
				'label' => $any_label,
			],
		];
		$prepared = [];

		foreach ( $values as $value ) {
			$status = sanitize_key( (string) $value );
			if ( '' === $status || 'any' === $status ) {
				continue;
			}
			$prepared[ $status ] = true;
		}

		foreach ( $fallback_values as $value ) {
			$status = sanitize_key( (string) $value );
			if ( '' === $status || isset( $prepared[ $status ] ) ) {
				continue;
			}
			$prepared[ $status ] = true;
		}

		foreach ( array_keys( $prepared ) as $status ) {
			$options[] = [
				'name'  => $status,
				'label' => ucfirst( str_replace( '_', ' ', $status ) ),
			];
		}

		return $options;
	}

	private static function is_duplicate_order_event( string $event, int $order_id, int $customer_id, array $payload ): bool {
		if ( 'order_canceled' !== $event ) {
			return false;
		}

		static $seen = [];

		$signature = [
			'event'       => $event,
			'order_id'    => $order_id,
			'customer_id' => $customer_id,
			'reason'      => (string) ( $payload['reason'] ?? '' ),
			'old_status'  => (string) ( $payload['old_status'] ?? '' ),
			'new_status'  => (string) ( $payload['new_status'] ?? '' ),
		];

		$key = md5( wp_json_encode( $signature ) ?: '' );
		if ( isset( $seen[ $key ] ) ) {
			return true;
		}

		$seen[ $key ] = true;
		return false;
	}

	private static function parse_positive_int( $value ): int {
		if ( is_int( $value ) || is_float( $value ) ) {
			$parsed = (int) $value;
			return $parsed > 0 ? $parsed : 0;
		}

		if ( is_string( $value ) ) {
			$value = trim( $value );
			if ( '' === $value || 'any' === strtolower( $value ) ) {
				return 0;
			}

			if ( is_numeric( $value ) ) {
				$parsed = (int) $value;
				return $parsed > 0 ? $parsed : 0;
			}

			if ( preg_match( '/\d+/', $value, $matches ) ) {
				$parsed = isset( $matches[0] ) ? (int) $matches[0] : 0;
				return $parsed > 0 ? $parsed : 0;
			}

			return 0;
		}

		if ( is_array( $value ) ) {
			foreach ( [ 'id', 'ID', 'value', 'name', 'order_id', 'customer_id', 'subscription_id', 'product_id', 'user_id' ] as $key ) {
				if ( array_key_exists( $key, $value ) ) {
					return self::parse_positive_int( $value[ $key ] );
				}
			}

			if ( isset( $value[0] ) ) {
				return self::parse_positive_int( $value[0] );
			}

			return 0;
		}

		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'get_id' ) ) {
				return self::parse_positive_int( $value->get_id() );
			}

			foreach ( [ 'ID', 'id', 'value', 'name', 'order_id', 'customer_id', 'subscription_id', 'product_id', 'user_id' ] as $key ) {
				if ( isset( $value->{$key} ) ) {
					return self::parse_positive_int( $value->{$key} );
				}
			}

			return 0;
		}

		$parsed = (int) $value;
		return $parsed > 0 ? $parsed : 0;
	}

	private static function main_response( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	private static function error_response( string $message, array $input = [] ): array {
		return [
			'port' => 'error',
			'data' => array_merge(
				$input,
				[
					'error' => $message,
				]
			),
		];
	}
}
