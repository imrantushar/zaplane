<?php
namespace Zaplane\Integrations\Surecart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Helper {

	protected static function respond( array $data = [], string $port = 'main' ): array {
		return [
			'port' => $port,
			'data' => $data
		];
	}

	protected static function error( string $message, array $data = [] ): array {
		return self::respond( array_merge( [ 'error' => $message ], $data ), 'error' );
	}

	protected static function get_node_action( array $node ): string {
		return $node['data']['event'] ?? $node['config']['action'] ?? $node['data']['action'] ?? '';
	}

	protected static function get_node_config( array $node ): array {
		if ( ! empty( $node['data']['config'] ) && is_array( $node['data']['config'] ) ) {
			return $node['data']['config'];
		}
		if ( ! empty( $node['config']['data'] ) && is_array( $node['config']['data'] ) ) {
			return $node['config']['data'];
		}
		if ( ! empty( $node['config'] ) && is_array( $node['config'] ) ) {
			return $node['config'];
		}
		return [];
	}

	protected static function ensure_surecart(): ?array {
		if ( ! class_exists( 'SureCart' ) ) {
			return self::error( 'SureCart plugin is not active' );
		}
		return null;
	}

	protected static function create_model( string $class, array $config, string $key ): array {
		$error = self::ensure_surecart();
		if ( null !== $error ) {
			return $error;
		}
		if ( ! class_exists( $class ) ) {
			return self::error( 'Model class not found', [ 'class' => $class ] );
		}

		$data = self::parse_json_array( $config['data'] ?? [] );
		$model = new $class();
		self::apply_model_context( $model, $config );

		$result = $model->create( $data );
		if ( false === $result ) {
			return self::error( 'Create failed' );
		}
		if ( is_wp_error( $result ) ) {
			return self::error( $result->get_error_message(), [ 'code' => $result->get_error_code() ] );
		}

		return self::respond( [ $key => self::model_to_array( $result ) ] );
	}

	protected static function update_model( string $class, array $config, string $id_key, string $key ): array {
		$error = self::ensure_surecart();
		if ( null !== $error ) {
			return $error;
		}
		if ( ! class_exists( $class ) ) {
			return self::error( 'Model class not found', [ 'class' => $class ] );
		}

		$id = $config[ $id_key ] ?? '';
		if ( '' === $id || null === $id ) {
			return self::error( 'ID is required', [ 'field' => $id_key ] );
		}

		$data = self::parse_json_array( $config['data'] ?? [] );
		$model = new $class( $id );
		self::apply_model_context( $model, $config );

		$result = $model->update( $data );
		if ( false === $result ) {
			return self::error( 'Update failed' );
		}
		if ( is_wp_error( $result ) ) {
			return self::error( $result->get_error_message(), [ 'code' => $result->get_error_code() ] );
		}

		return self::respond( [ $key => self::model_to_array( $result ) ] );
	}

	protected static function delete_model( string $class, array $config, string $id_key ): array {
		$error = self::ensure_surecart();
		if ( null !== $error ) {
			return $error;
		}
		if ( ! class_exists( $class ) ) {
			return self::error( 'Model class not found', [ 'class' => $class ] );
		}

		$id = $config[ $id_key ] ?? '';
		if ( '' === $id || null === $id ) {
			return self::error( 'ID is required', [ 'field' => $id_key ] );
		}

		$model = new $class( $id );
		self::apply_model_context( $model, $config );

		$result = $model->delete( $id );
		if ( false === $result ) {
			return self::error( 'Delete failed' );
		}
		if ( is_wp_error( $result ) ) {
			return self::error( $result->get_error_message(), [ 'code' => $result->get_error_code() ] );
		}

		return self::respond( [
			'id' => $id,
			'deleted' => true
		] );
	}

	protected static function get_model_single( string $class, array $config, string $id_key, string $key ): array {
		$error = self::ensure_surecart();
		if ( null !== $error ) {
			return $error;
		}
		if ( ! class_exists( $class ) ) {
			return self::error( 'Model class not found', [ 'class' => $class ] );
		}

		$id = $config[ $id_key ] ?? '';
		if ( '' === $id || null === $id ) {
			return self::error( 'ID is required', [ 'field' => $id_key ] );
		}

		$model = new $class();
		self::apply_model_context( $model, $config );

		$result = $model->find( $id );
		if ( false === $result ) {
			return self::error( 'Not found' );
		}
		if ( is_wp_error( $result ) ) {
			return self::error( $result->get_error_message(), [ 'code' => $result->get_error_code() ] );
		}

		return self::respond( [ $key => self::model_to_array( $result ) ] );
	}

	protected static function list_models( string $class, array $config ): array {
		$error = self::ensure_surecart();
		if ( null !== $error ) {
			return $error;
		}
		if ( ! class_exists( $class ) ) {
			return self::error( 'Model class not found', [ 'class' => $class ] );
		}

		$pagination = self::get_pagination_args( $config );
		$query = self::parse_json_array( $config['query'] ?? [] );

		$model = new $class();
		self::apply_model_context( $model, $config );
		if ( ! empty( $query ) ) {
			$model->where( $query );
		}

		$collection = $model->paginate([
			'page' => $pagination['page'],
			'per_page' => $pagination['limit'],
		]);

		if ( is_wp_error( $collection ) ) {
			return self::error( $collection->get_error_message(), [ 'code' => $collection->get_error_code() ] );
		}

		$items = [];
		$data = $collection->data ?? [];
		if ( is_array( $data ) ) {
			foreach ( $data as $item ) {
				$items[] = self::model_to_array( $item );
			}
		}

		return self::respond([
			'count' => (int) ( $collection->total() ?? count( $items ) ),
			'items' => $items,
			'page' => $pagination['page'],
			'limit' => $pagination['limit'],
		]);
	}

	protected static function apply_model_context( $model, array $config ): void {
		$mode = $config['mode'] ?? '';
		if ( '' !== $mode ) {
			$mode = self::normalize_mode( $mode );
			if ( '' !== $mode ) {
				$model->setMode( $mode );
			}
		}

		$expand = self::parse_list( $config['expand'] ?? [] );
		if ( ! empty( $expand ) ) {
			$model->with( $expand );
		}
	}

	protected static function normalize_mode( $mode ): string {
		$mode = strtolower( trim( (string) $mode ) );
		if ( 'test' === $mode || 'live' === $mode ) {
			return $mode;
		}
		return '';
	}

	protected static function payload_from_model( $model, string $key ): array {
		return [ $key => self::model_to_array( $model ) ];
	}

	protected static function payload_checkout_confirmed( array $args ): array {
		$checkout = $args[0] ?? null;
		$request = $args[1] ?? null;

		$payload = [ 'checkout' => self::model_to_array( $checkout ) ];

		if ( $request instanceof \WP_REST_Request ) {
			$payload['request'] = [
				'method' => $request->get_method(),
				'route' => $request->get_route(),
				'params' => $request->get_params(),
			];
		} elseif ( is_array( $request ) ) {
			$payload['request'] = $request;
		}

		return $payload;
	}

	protected static function model_to_array( $model ): array {
		if ( is_null( $model ) ) {
			return [];
		}
		if ( is_array( $model ) ) {
			return $model;
		}
		if ( is_object( $model ) ) {
			if ( method_exists( $model, 'toArray' ) ) {
				return $model->toArray();
			}
			if ( $model instanceof \WP_Post ) {
				return self::normalize_post( $model );
			}
			return get_object_vars( $model );
		}
		return [ 'value' => $model ];
	}

	protected static function normalize_post( $post ): array {
		if ( is_numeric( $post ) ) {
			$post = get_post( (int) $post );
		}
		if ( $post instanceof \WP_Post ) {
			return [
				'ID' => $post->ID,
				'post_title' => $post->post_title,
				'post_name' => $post->post_name,
				'post_status' => $post->post_status,
				'post_type' => $post->post_type,
				'post_date' => $post->post_date,
				'post_modified' => $post->post_modified,
				'post_author' => $post->post_author,
				'guid' => $post->guid,
			];
		}
		return is_array( $post ) ? $post : [];
	}

	protected static function parse_json_array( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) || trim( $value ) === '' ) {
			return [];
		}
		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	protected static function parse_list( $value ): array {
		if ( is_array( $value ) ) {
			return array_values( array_filter( $value, 'strlen' ) );
		}
		if ( ! is_string( $value ) ) {
			return [];
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return [];
		}
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			return array_values( array_filter( $decoded, 'strlen' ) );
		}
		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' ) );
	}

	protected static function build_product_data_from_manual( array $config ): array {
		$data = [];

		$name = trim( (string) ( $config['name'] ?? '' ) );
		if ( '' !== $name ) {
			$data['name'] = $name;
		}

		$description = $config['description'] ?? '';
		if ( '' !== $description ) {
			$data['description'] = $description;
		}

		$status = self::normalize_product_status( $config['product_status'] ?? ( $config['status'] ?? '' ) );
		if ( '' !== $status ) {
			$data['status'] = $status;
		}

		$price = [];
		$amount = $config['price_amount'] ?? '';
		if ( '' !== $amount && is_numeric( $amount ) ) {
			$price['amount'] = (int) $amount;
		}

		$currency = strtoupper( trim( (string) ( $config['currency'] ?? '' ) ) );
		if ( '' !== $currency ) {
			$price['currency'] = $currency;
		}

		$interval = strtolower( trim( (string) ( $config['recurring_interval'] ?? '' ) ) );
		if ( '' !== $interval ) {
			$price['recurring_interval'] = $interval;
			$count = $config['recurring_interval_count'] ?? 1;
			if ( '' !== $count && null !== $count ) {
				$price['recurring_interval_count'] = max( 1, (int) $count );
			}
		}

		if ( ! empty( $price ) ) {
			$data['prices'] = [ $price ];
		}

		return $data;
	}

	protected static function normalize_product_status( $status ): string {
		$status = strtolower( trim( (string) $status ) );
		if ( 0 === strpos( $status, 'surecart_product_' ) ) {
			return substr( $status, strlen( 'surecart_product_' ) );
		}

		return $status;
	}

	protected static function get_pagination_args( array $config, int $default_limit = 20 ): array {
		$limit = isset( $config['limit'] ) ? (int) $config['limit'] : $default_limit;
		if ( $limit <= 0 ) {
			$limit = $default_limit;
		}
		$page = isset( $config['page'] ) ? max( 1, (int) $config['page'] ) : 1;
		return [
			'limit' => $limit,
			'page' => $page
		];
	}

	protected static function field_data( string $label ): array {
		return [
			[
				'key' => 'data',
				'label' => $label,
				'type' => 'textarea',
			]
		];
	}

	protected static function field_mode(): array {
		return [
			[
				'key' => 'mode',
				'label' => 'Mode',
				'type' => 'select',
				'required' => true,
				'options' => [
					[
						'label' => 'Live',
						'value' => 'live'
					],
					[
						'label' => 'Test',
						'value' => 'test'
					],
				],
			]
		];
	}

	protected static function field_expand(): array {
		return [
			[
				'key' => 'expand',
				'label' => 'Expand (CSV/JSON)',
				'type' => 'textarea',
			]
		];
	}

	protected static function field_query(): array {
		return [
			[
				'key' => 'query',
				'label' => 'Query (JSON)',
				'type' => 'textarea',
			]
		];
	}

	protected static function field_limit_page(): array {
		return [
			[
				'key' => 'limit',
				'label' => 'Limit',
				'type' => 'number',
				'required' => true
			],
			[
				'key' => 'page',
				'label' => 'Page',
				'type' => 'number',
				'required' => true
			],
		];
	}

	protected static function field_order_id(): array {
		return [
			[
				'key' => 'order_id',
				'label' => 'Order ID',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'surecart',
					'query' => 'orders',
					'select' => [ 'id', 'label' ],
				],
				'required' => true,
			]
		];
	}

	protected static function field_customer_id(): array {
		return [
			[
				'key' => 'customer_id',
				'label' => 'Customer ID',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'surecart',
					'query' => 'customers',
					'select' => [ 'id', 'label' ],
				],
				'required' => true,
			]
		];
	}

	protected static function field_product_id(): array {
		return [
			[
				'key' => 'product_id',
				'label' => 'Product ID',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'surecart',
					'query' => 'products',
					'select' => [ 'id', 'name' ],
				],
				'required' => true,
			]
		];
	}

	protected static function field_coupon_id(): array {
		return [
			[
				'key' => 'coupon_id',
				'label' => 'Coupon ID',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'surecart',
					'query' => 'coupons',
					'select' => [ 'id', 'code' ],
				],
				'required' => true,
			]
		];
	}

	protected static function field_subscription_id(): array {
		return [
			[
				'key' => 'subscription_id',
				'label' => 'Subscription ID',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'surecart',
					'query' => 'subscriptions',
					'select' => [ 'id', 'label' ],
				],
				'required' => true,
			]
		];
	}

	public static function query_orders( $q ): array {
		return self::query_surecart_models( \SureCart\Models\Order::class, $q, 'order' );
	}

	public static function query_customers( $q ): array {
		return self::query_surecart_models( \SureCart\Models\Customer::class, $q, 'customer' );
	}

	public static function query_products( $q ): array {
		return self::query_surecart_models( \SureCart\Models\Product::class, $q, 'product' );
	}

	public static function query_coupons( $q ): array {
		return self::query_surecart_models( \SureCart\Models\Coupon::class, $q, 'coupon' );
	}

	public static function query_subscriptions( $q ): array {
		return self::query_surecart_models( \SureCart\Models\Subscription::class, $q, 'subscription' );
	}

	protected static function query_surecart_models( string $class, $q, string $type ): array {
		$error = self::ensure_surecart();
		if ( null !== $error ) {
			return [];
		}
		if ( ! class_exists( $class ) ) {
			return [];
		}

		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		$model = new $class();
		$collection = $model->paginate([
			'page' => 1,
			'per_page' => $limit,
		]);

		$data = $collection->data ?? [];
		$items = [];

		if ( is_array( $data ) ) {
			foreach ( $data as $item ) {
				$values = self::model_to_array( $item );
				$id = $values['id'] ?? '';
				if ( '' === $id ) {
					continue;
				}

				$label = '';
				if ( 'order' === $type ) {
					$label = $values['number'] ?? $values['order_number'] ?? '';
				} elseif ( 'customer' === $type ) {
					$label = $values['email'] ?? $values['name'] ?? '';
				} elseif ( 'product' === $type ) {
					$label = $values['name'] ?? '';
				} elseif ( 'coupon' === $type ) {
					$label = $values['code'] ?? '';
				} elseif ( 'subscription' === $type ) {
					$label = $values['name'] ?? $values['status'] ?? '';
				}

				if ( '' === $label ) {
					$label = ucfirst( $type ) . ' #' . $id;
				}

				if ( ! self::matches_dynamic_search( $search, $label ) ) {
					continue;
				}

				$item_row = [
					'id' => (string) $id,
					'label' => $label,
				];

				if ( 'product' === $type ) {
					$item_row['name'] = $label;
				}
				if ( 'coupon' === $type ) {
					$item_row['code'] = $values['code'] ?? '';
				}

				$items[] = $item_row;
			}//end foreach
		}//end if

		return array_slice( $items, 0, $limit );
	}

	protected static function normalize_dynamic_limit( array $q ): int {
		$limit = (int) ( $q['limit'] ?? 20 );
		if ( $limit < 1 ) {
			$limit = 20;
		}
		if ( $limit > 200 ) {
			$limit = 200;
		}
		return $limit;
	}

	protected static function normalize_dynamic_search( array $q ): string {
		return trim( (string) ( $q['search'] ?? '' ) );
	}

	protected static function matches_dynamic_search( string $search, string $value ): bool {
		if ( '' === $search ) {
			return true;
		}
		return stripos( $value, $search ) !== false;
	}
}
