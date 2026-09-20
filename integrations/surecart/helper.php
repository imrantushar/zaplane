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

	protected static function create_model( string $class, array $config, array $data, string $key ): array {
		$error = self::ensure_surecart();
		if ( null !== $error ) {
			return $error;
		}
		if ( ! class_exists( $class ) ) {
			return self::error( 'Model class not found', [ 'class' => $class ] );
		}

		$query = self::apply_model_context_static( $class, $config );

		$result = $query ? $query->create( $data ) : $class::create( $data );
		if ( false === $result ) {
			return self::error( 'Create failed' );
		}
		if ( is_wp_error( $result ) ) {
			return self::error( $result->get_error_message(), [
				'code'    => $result->get_error_code(),
				'details' => $result->get_error_data(),
			] );
		}

		return self::respond( [ $key => self::model_to_array( $result ) ] );
	}

	protected static function update_model( string $class, array $config, string $id_key, array $data, string $key ): array {
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

		$data['id'] = $id;

		$result = $class::update( $data );
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

		$result = $class::delete( $id );
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

		$query  = self::apply_model_context_static( $class, $config );
		$result = $query ? $query->find( $id ) : $class::find( $id );

		if ( false === $result || null === $result ) {
			return self::error( 'Not found' );
		}
		if ( is_wp_error( $result ) ) {
			return self::error( $result->get_error_message(), [ 'code' => $result->get_error_code() ] );
		}

		return self::respond( [ $key => self::model_to_array( $result ) ] );
	}

	protected static function list_models( string $class, array $config, string $items_key = 'items' ): array {
		$error = self::ensure_surecart();
		if ( null !== $error ) {
			return $error;
		}
		if ( ! class_exists( $class ) ) {
			return self::error( 'Model class not found', [ 'class' => $class ] );
		}

		$pagination = self::get_pagination_args( $config );
		$query      = self::apply_model_context_static( $class, $config );
		$base       = $query ?: $class;

		$collection = $base::paginate(
			[
				'page'     => $pagination['page'],
				'per_page' => $pagination['limit'],
			]
		);

		if ( is_wp_error( $collection ) ) {
			return self::error( $collection->get_error_message(), [ 'code' => $collection->get_error_code() ] );
		}

		$items = [];
		$data  = $collection->data ?? [];
		if ( is_array( $data ) ) {
			foreach ( $data as $item ) {
				$items[] = self::model_to_array( $item );
			}
		}

		return self::respond(
			[
				'count' => (int) ( $collection->total() ?? count( $items ) ),
				$items_key => $items,
				'page'  => $pagination['page'],
				'limit' => $pagination['limit'],
			]
		);
	}

	protected static function apply_model_context_static( string $class, array $config ) {
		$query  = null;
		$expand = self::parse_list( $config['expand'] ?? [] );

		if ( ! empty( $expand ) ) {
			$query = $class::with( $expand );
		}

		return $query;
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

	protected static function build_billing_address( array $config ): array {
		$rows = $config['billing_address'] ?? [];
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$key_map = [
			'first_name'  => 'name', // combined below if last_name also present
			'last_name'   => 'name',
			'email'       => 'email',
			'company'     => 'company',
			'address'     => 'address',
			'address_2'   => 'address_2',
			'city'        => 'city',
			'state'       => 'state',
			'postal_code' => 'zip',
			'country'     => 'country',
			'phone'       => 'phone',
		];

		$first_name = '';
		$last_name  = '';
		$address    = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$field = (string) ( $row['field'] ?? '' );
			$value = $row['value'] ?? '';
			if ( '' === $field || '' === $value ) {
				continue;
			}

			if ( 'first_name' === $field ) {
				$first_name = (string) $value;
				continue;
			}
			if ( 'last_name' === $field ) {
				$last_name = (string) $value;
				continue;
			}

			if ( isset( $key_map[ $field ] ) ) {
				$address[ $key_map[ $field ] ] = $value;
			}
		}

		$name = trim( $first_name . ' ' . $last_name );
		if ( '' !== $name ) {
			$address['name'] = $name;
		}

		return $address;
	}

	protected static function build_product_data_from_manual( array $config ): array {
		$data = [];

		if ( isset( $config['name'] ) && '' !== $config['name'] ) {
			$data['name'] = $config['name'];
		}
		if ( isset( $config['description'] ) && '' !== $config['description'] ) {
			$data['description'] = $config['description'];
		}

		$status = self::normalize_product_status( $config['product_status'] ?? '' );
		if ( '' !== $status ) {
			$data['status'] = $status;
		}

		return $data;
	}

	protected static function build_price_data_from_manual( array $config ): array {
		$price = [];

		if ( isset( $config['price_amount'] ) && '' !== $config['price_amount'] ) {
			// SureCart amounts are in the smallest currency unit (cents), like Stripe.
			$price['amount'] = (int) round( ( (float) $config['price_amount'] ) * 100 );
		}
		if ( ! empty( $config['currency'] ) ) {
			$price['currency'] = strtolower( (string) $config['currency'] );
		}
		if ( ! empty( $config['recurring_interval'] ) ) {
			$price['recurring_interval']       = $config['recurring_interval'];
			$price['recurring_interval_count'] = max( 1, (int) ( $config['recurring_interval_count'] ?? 1 ) );
		}

		return $price;
	}

	protected static function normalize_product_status( $status ): string {
		$map = [
			'surecart_product_published' => 'published',
			'surecart_product_draft'     => 'draft',
			'surecart_product_archived'  => 'archived',
		];
		$status = (string) $status;
		return $map[ $status ] ?? $status;
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

	protected static function field_quantity(): array {
		return [
			[
				'key'         => 'quantity',
				'label'       => 'Quantity',
				'type'        => 'number',
				'required'    => true,
				'default'     => 1,
				'placeholder' => 'quantity',
				'min'         => 1,
			],
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

	protected static function field_price_id(): array {
		return [
			[
				'key' => 'price_id',
				'label' => 'Price ID',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'surecart',
					'query' => 'prices',
					'select' => [ 'id', 'label' ],
				],
				'required' => true,
			]
		];
	}

	protected static function field_order_status(): array {
		return [
			[
				'key'      => 'order_status',
				'label'    => 'Order Status',
				'type'     => 'select',
				'required' => true,
				'options'  => [
					[ 'label' => 'Pending', 'value' => 'pending' ],
					[ 'label' => 'Processing', 'value' => 'processing' ],
				],
			],
		];
	}

	protected static function field_subscription_status(): array {
		return [
			[
				'key'      => 'subscription_status',
				'label'    => 'Subscription Status',
				'type'     => 'select',
				'required' => false,
				'options'  => [
					[ 'label' => 'Active', 'value' => 'active' ],
					[ 'label' => 'Trialing', 'value' => 'trialing' ],
					[ 'label' => 'Paused', 'value' => 'paused' ],
					[ 'label' => 'Cancelled', 'value' => 'cancelled' ],
				],
			],
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

	protected static function field_billing_address(): array {
		return [
			[
				'key'      => 'billing_address',
				'label'    => 'Map Billing Address',
				'type'     => 'repeater',
				'required' => false,
				'fields'   => [
					[
						'key'     => 'field',
						'label'   => 'Field',
						'type'    => 'select',
						'options' => [
							[ 'label' => 'First Name', 'value' => 'first_name' ],
							[ 'label' => 'Last Name', 'value' => 'last_name' ],
							[ 'label' => 'Email', 'value' => 'email' ],
							[ 'label' => 'Company', 'value' => 'company' ],
							[ 'label' => 'Address', 'value' => 'address' ],
							[ 'label' => 'Address Line 2', 'value' => 'address_2' ],
							[ 'label' => 'City', 'value' => 'city' ],
							[ 'label' => 'State', 'value' => 'state' ],
							[ 'label' => 'Postal Code', 'value' => 'postal_code' ],
							[ 'label' => 'Country', 'value' => 'country' ],
							[ 'label' => 'Phone', 'value' => 'phone' ],
						],
					],
					[
						'key'         => 'value',
						'label'       => 'Value',
						'type'        => 'text',
						'placeholder' => 'Enter value or map a field',
					],
				],
			],
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
		return self::query_surecart_models( \SureCart\Models\Promotion::class, $q, 'coupon' );
	}

	protected static function merge_expand( array $config, array $additional ): array {
		$existing         = self::parse_list( $config['expand'] ?? [] );
		$config['expand'] = array_values( array_unique( array_merge( $existing, $additional ) ) );
		return $config;
	}

	public static function query_subscriptions( $q ): array {
		return self::query_surecart_models( \SureCart\Models\Subscription::class, $q, 'subscription' );
	}

	public static function query_prices( $q ): array {
		return self::query_surecart_models( \SureCart\Models\Price::class, $q, 'price' );
	}

	protected static function query_surecart_models( string $class, $q, string $type ): array {
		$error = self::ensure_surecart();
		if ( null !== $error ) {
			return [];
		}
		if ( ! class_exists( $class ) ) {
			return [];
		}

		$q      = is_array( $q ) ? $q : [];
		$limit  = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		if ( 'coupon' === $type ) {
			$base = $class::with( [ 'coupon' ] );
		} elseif ( 'price' === $type ) {
			$base = $class::with( [ 'product' ] );
		} else {
			$base = $class;
		}

		$collection = $base::paginate(
			[
				'page'     => 1,
				'per_page' => $limit,
			]
		);

		$data  = $collection->data ?? [];
		$items = [];

		if ( is_array( $data ) ) {
			foreach ( $data as $item ) {
				$values = self::model_to_array( $item );
				$id     = $values['id'] ?? '';
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
				} elseif ( 'price' === $type ) {
					$label = self::format_price_label( $values );
				}

				if ( '' === $label ) {
					$label = ucfirst( $type ) . ' #' . $id;
				}

				if ( in_array( $type, [ 'customer', 'price', 'product', 'order', 'subscription', 'coupon' ], true ) ) {
					$is_live = array_key_exists( 'live_mode', $values ) ? (bool) $values['live_mode'] : true;
					$label   = ( $is_live ? '[Live] ' : '[Test] ' ) . $label;
				}

				if ( ! self::matches_dynamic_search( $search, $label ) ) {
					continue;
				}

				$item_row = [
					'id'    => (string) $id,
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

	protected static function format_price_label( array $values ): string {
		$product_name = '';
		$product      = $values['product'] ?? '';
		if ( is_array( $product ) ) {
			$product_name = $product['name'] ?? '';
		}

		$amount_part = '';
		if ( isset( $values['amount'] ) ) {
			$amount_part = number_format( ( (int) $values['amount'] ) / 100, 2 );
			if ( ! empty( $values['currency'] ) ) {
				$amount_part .= ' ' . strtoupper( (string) $values['currency'] );
			}
		}
		if ( ! empty( $values['recurring_interval'] ) ) {
			$amount_part .= '/' . $values['recurring_interval'];
		}

		$label = trim( $product_name . ( '' !== $amount_part ? ' — ' . $amount_part : '' ) );
		if ( '' === $label ) {
			$label = $values['name'] ?? '';
		}
		return $label;
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
