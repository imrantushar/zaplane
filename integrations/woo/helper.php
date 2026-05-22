<?php
namespace Zaplane\Integrations\Woo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Helper {

	protected static $order_status_events = [
		'order_status_pending',
		'order_status_failed',
		'order_status_on_hold',
		'order_status_processing',
		'order_status_completed',
		'order_status_refunded',
		'order_status_cancelled',
	];

	protected static function build_order_payload( \WC_Order $order, array $extra = [] ): array {
		return array_merge([
			'order_id' => $order->get_id(),
			'order_number' => $order->get_order_number(),
			'status' => $order->get_status(),
			'total' => $order->get_total(),
			'currency' => $order->get_currency(),
			'customer_id' => $order->get_customer_id(),
		], $extra);
	}

	protected static function build_product_payload( \WC_Product $product, array $extra = [] ): array {
		return array_merge([
			'product_id' => $product->get_id(),
			'name' => $product->get_name(),
			'status' => $product->get_status(),
			'sku' => $product->get_sku(),
			'price' => $product->get_price(),
			'type' => $product->get_type(),
		], $extra);
	}

	protected static function build_coupon_payload( \WC_Coupon $coupon, array $extra = [] ): array {
		return array_merge([
			'coupon_id' => $coupon->get_id(),
			'code' => $coupon->get_code(),
			'amount' => $coupon->get_amount(),
			'discount_type' => $coupon->get_discount_type(),
		], $extra);
	}

	protected static function build_customer_payload( \WC_Customer $customer, array $extra = [] ): array {
		return array_merge([
			'customer_id' => $customer->get_id(),
			'email' => $customer->get_email(),
			'username' => $customer->get_username(),
		], $extra);
	}

	protected static function get_order_from_args( array $args, int $id_index = 0, int $object_index = 1 ): ?\WC_Order {
		$order = $args[ $object_index ] ?? null;
		if ( $order instanceof \WC_Order ) {
			return $order;
		}

		$order_id = $args[ $id_index ] ?? 0;
		return $order_id ? wc_get_order( $order_id ) : null;
	}

	protected static function order_payload_from_args( array $args, array $extra = [], int $id_index = 0, int $object_index = 1 ): ?array {
		$order = self::get_order_from_args( $args, $id_index, $object_index );
		return $order ? self::build_order_payload( $order, $extra ) : null;
	}

	protected static function order_status_payload_from_args( array $args ): ?array {
		$order = self::get_order_from_args( $args );
		if ( ! $order ) {
			return null;
		}
		$transition = $args[2] ?? [];
		$old_status = is_array( $transition ) ? ( $transition['from'] ?? '' ) : '';
		$new_status = is_array( $transition ) ? ( $transition['to'] ?? $order->get_status() ) : $order->get_status();
		return self::build_order_payload($order, [
			'old_status' => $old_status,
			'new_status' => $new_status,
		]);
	}

	protected static function get_product_from_args( array $args, int $id_index = 0, int $object_index = 1 ): ?\WC_Product {
		$product = $args[ $object_index ] ?? null;
		if ( $product instanceof \WC_Product ) {
			return $product;
		}

		$product_id = $args[ $id_index ] ?? 0;
		return $product_id ? wc_get_product( $product_id ) : null;
	}

	protected static function product_payload_from_args( array $args, array $extra = [], int $id_index = 0, int $object_index = 1 ): ?array {
		$product = self::get_product_from_args( $args, $id_index, $object_index );
		return $product ? self::build_product_payload( $product, $extra ) : null;
	}

	protected static function get_product_from_post( $post_ref ): ?\WC_Product {
		$post = is_numeric( $post_ref ) ? get_post( (int) $post_ref ) : $post_ref;
		if ( ! $post || ( $post->post_type ?? '' ) !== 'product' ) {
			return null;
		}

		$product = wc_get_product( $post->ID );
		return $product ? $product : null;
	}

	protected static function product_payload_from_post( $post_ref, array $extra = [] ): ?array {
		$product = self::get_product_from_post( $post_ref );
		return $product ? self::build_product_payload( $product, $extra ) : null;
	}

	protected static function get_coupon_from_args( array $args ): ?\WC_Coupon {
		$coupon = $args[1] ?? null;
		if ( $coupon instanceof \WC_Coupon ) {
			return $coupon;
		}

		$coupon_id = $args[0] ?? 0;
		if ( ! $coupon_id ) {
			return null;
		}

		$coupon = new \WC_Coupon( $coupon_id );
		return $coupon->get_id() ? $coupon : null;
	}

	protected static function coupon_payload_from_args( array $args, array $extra = [] ): ?array {
		$coupon = self::get_coupon_from_args( $args );
		return $coupon ? self::build_coupon_payload( $coupon, $extra ) : null;
	}

	protected static function get_customer_from_args( array $args, int $id_index = 0, int $object_index = 1 ): ?\WC_Customer {
		$customer = $args[ $object_index ] ?? null;
		if ( $customer instanceof \WC_Customer ) {
			return $customer;
		}

		$customer_id = $args[ $id_index ] ?? 0;
		if ( ! $customer_id ) {
			return null;
		}

		$customer = new \WC_Customer( $customer_id );
		return $customer->get_id() ? $customer : null;
	}

	protected static function customer_payload_from_args( array $args, array $extra = [], int $id_index = 0, int $object_index = 1 ): ?array {
		$customer = self::get_customer_from_args( $args, $id_index, $object_index );
		return $customer ? self::build_customer_payload( $customer, $extra ) : null;
	}

	protected static function build_cart_add_payload( array $args ): array {
		return [
			'cart_item_key' => $args[0] ?? '',
			'product_id' => $args[1] ?? 0,
			'quantity' => $args[2] ?? 0,
			'variation_id' => $args[3] ?? 0,
		];
	}

	protected static function build_cart_item_payload( array $args ): array {
		$cart_item_key = $args[0] ?? '';
		$cart = $args[1] ?? null;
		$cart_item = $cart instanceof \WC_Cart
			? ( $cart->removed_cart_contents[ $cart_item_key ] ?? $cart->cart_contents[ $cart_item_key ] ?? null )
			: null;
		if ( ! $cart_item ) {
			return [ 'cart_item_key' => $cart_item_key ];
		}

		return [
			'cart_item_key' => $cart_item_key,
			'product_id' => $cart_item['product_id'] ?? 0,
			'quantity' => $cart_item['quantity'] ?? 0,
			'variation_id' => $cart_item['variation_id'] ?? 0,
		];
	}

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

	protected static function get_customer_id_by_email( string $email ): int {
		if ( '' === $email ) {
			return 0;
		}
		if ( function_exists( 'wc_get_customer_id_by_email' ) ) {
			return (int) wc_get_customer_id_by_email( $email );
		}
		$user = get_user_by( 'email', $email );
		return $user ? (int) $user->ID : 0;
	}

	protected static function parse_bool( $value, bool $default = false ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( null === $value || '' === $value ) {
			return $default;
		}
		$result = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		if ( null === $result ) {
			return $default;
		}
		return $result;
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
			return $value;
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
			return $decoded;
		}
		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' ) );
	}

	protected static function normalize_order_status( string $status ): string {
		$status = self::normalize_prefixed_option_value( $status, 'wc_order_' );
		if ( strpos( $status, 'wc-' ) === 0 ) {
			return substr( $status, 3 );
		}
		return $status;
	}

	protected static function normalize_product_status( string $status ): string {
		return self::normalize_prefixed_option_value( $status, 'wc_product_' );
	}

	protected static function get_order_status_config( array $config ): string {
		return (string) ( $config['order_status'] ?? ( $config['status'] ?? '' ) );
	}

	protected static function get_product_status_config( array $config ): string {
		return (string) ( $config['product_status'] ?? ( $config['status'] ?? '' ) );
	}

	protected static function normalize_prefixed_option_value( string $value, string $prefix ): string {
		$value = sanitize_key( $value );
		if ( 0 === strpos( $value, $prefix ) ) {
			return substr( $value, strlen( $prefix ) );
		}

		return $value;
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

	protected static function query_orders( array $args ): array {
		$result = wc_get_orders( $args );
		if ( is_wp_error( $result ) ) {
			return [
				'items' => [],
				'total' => 0
			];
		}
		if ( is_object( $result ) && isset( $result->orders ) ) {
			return [
				'items' => $result->orders ?? [],
				'total' => (int) ( $result->total ?? count( $result->orders ?? [] ) ),
			];
		}
		if ( is_array( $result ) && isset( $result['orders'] ) ) {
			return [
				'items' => $result['orders'] ?? [],
				'total' => (int) ( $result['total'] ?? count( $result['orders'] ?? [] ) ),
			];
		}
		$items = is_array( $result ) ? $result : [];
		return [
			'items' => $items,
			'total' => count( $items ),
		];
	}

	protected static function query_products( array $args ): array {
		$result = wc_get_products( $args );
		if ( is_wp_error( $result ) ) {
			return [
				'items' => [],
				'total' => 0
			];
		}
		if ( is_object( $result ) && isset( $result->products ) ) {
			return [
				'items' => $result->products ?? [],
				'total' => (int) ( $result->total ?? count( $result->products ?? [] ) ),
			];
		}
		if ( is_array( $result ) && isset( $result['products'] ) ) {
			return [
				'items' => $result['products'] ?? [],
				'total' => (int) ( $result['total'] ?? count( $result['products'] ?? [] ) ),
			];
		}
		$items = is_array( $result ) ? $result : [];
		return [
			'items' => $items,
			'total' => count( $items ),
		];
	}

	protected static function query_customers( array $args ): array {
		if ( function_exists( 'wc_get_customers' ) ) {
			$result = wc_get_customers( $args );
			return [
				'items' => $result ? $result : [],
				'total' => is_array( $result ) ? count( $result ) : 0,
			];
		}
		return [
			'items' => [],
			'total' => 0
		];
	}

	protected static function query_coupons( array $args ): array {
		if ( function_exists( 'wc_get_coupons' ) ) {
			$result = wc_get_coupons( $args );
			return [
				'items' => $result ? $result : [],
				'total' => is_array( $result ) ? count( $result ) : 0,
			];
		}
		return [
			'items' => [],
			'total' => 0
		];
	}

	protected static function query_reviews( array $args ): array {
		$query = new \WP_Comment_Query( $args );
		$items = $query->comments ? $query->comments : [];
		$total = (int) ( $query->found_comments ?? count( $items ) );
		return [
			'items' => $items,
			'total' => $total
		];
	}

	protected static function build_term_payload( $term ): array {
		if ( $term instanceof \WP_Term ) {
			return [
				'term_id' => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
				'description' => $term->description,
				'parent' => $term->parent,
				'count' => $term->count,
				'taxonomy' => $term->taxonomy,
			];
		}
		return [];
	}

	protected static function product_status_options(): array {
		return [
			[
				'label' => 'Publish',
				'value' => 'wc_product_publish'
			],
			[
				'label' => 'Draft',
				'value' => 'wc_product_draft'
			],
			[
				'label' => 'Pending',
				'value' => 'wc_product_pending'
			],
			[
				'label' => 'Private',
				'value' => 'wc_product_private'
			],
		];
	}

	protected static function order_status_options(): array {
		return [
			[
				'label' => 'Pending',
				'value' => 'wc_order_pending'
			],
			[
				'label' => 'Processing',
				'value' => 'wc_order_processing'
			],
			[
				'label' => 'On-hold',
				'value' => 'wc_order_on-hold'
			],
			[
				'label' => 'Completed',
				'value' => 'wc_order_completed'
			],
			[
				'label' => 'Cancelled',
				'value' => 'wc_order_cancelled'
			],
			[
				'label' => 'Refunded',
				'value' => 'wc_order_refunded'
			],
			[
				'label' => 'Failed',
				'value' => 'wc_order_failed'
			],
		];
	}

	protected static function stock_status_options(): array {
		return [
			[
				'label' => 'In Stock',
				'value' => 'instock'
			],
			[
				'label' => 'Out of Stock',
				'value' => 'outofstock'
			],
			[
				'label' => 'On Backorder',
				'value' => 'onbackorder'
			],
		];
	}

	protected static function coupon_type_options(): array {
		return [
			[
				'label' => 'Percentage',
				'value' => 'percent'
			],
			[
				'label' => 'Fixed Cart',
				'value' => 'fixed_cart'
			],
			[
				'label' => 'Fixed Product',
				'value' => 'fixed_product'
			],
		];
	}

	protected static function field_order_id( bool $required = true ): array {
		return [
			[
				'key' => 'order_id',
				'label' => 'Order ID',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'woocommerce',
					'query' => 'orders',
					'select' => [ 'id', 'label' ],
				],
				'required' => $required,
			]
		];
	}

	protected static function field_customer_id( bool $required = true ): array {
		return [
			[
				'key' => 'customer_id',
				'label' => 'Customer ID',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'woocommerce',
					'query' => 'customers',
					'select' => [ 'id', 'label' ],
				],
				'required' => $required,
			]
		];
	}

	protected static function field_product_id( bool $required = true ): array {
		return [
			[
				'key' => 'product_id',
				'label' => 'Product ID',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'woocommerce',
					'query' => 'products',
					'select' => [ 'id', 'name' ],
				],
				'required' => $required,
			]
		];
	}

	protected static function field_coupon_identifier(): array {
		return [
			[
				'key' => 'coupon_id',
				'label' => 'Coupon ID',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'woocommerce',
					'query' => 'coupons',
					'select' => [ 'id', 'code' ],
				],
			],
			[
				'key' => 'code',
				'label' => 'Coupon Code',
				'type' => 'text'
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

	protected static function field_term_list_filters(): array {
		return [
			[
				'key' => 'hide_empty',
				'label' => 'Hide Empty Terms',
				'type' => 'boolean',
			],
			[
				'key' => 'search',
				'label' => 'Search',
				'type' => 'text',
			],
			...self::field_limit_page(),
		];
	}

	protected static function field_term_id( string $taxonomy = 'product_cat' ): array {
		return [
			[
				'key' => 'term_id',
				'label' => 'Term ID',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'woocommerce',
					'query' => 'terms',
					'select' => [ 'id', 'name' ],
					'where' => [
						'taxonomy' => $taxonomy,
					],
				],
				'required' => true,
			]
		];
	}

	protected static function field_term_create( bool $with_parent = true, string $taxonomy = 'product_cat' ): array {
		$fields = [
			[
				'key' => 'name',
				'label' => 'Name',
				'type' => 'text',
				'required' => true
			],
			[
				'key' => 'slug',
				'label' => 'Slug',
				'type' => 'text'
			],
			[
				'key' => 'description',
				'label' => 'Description',
				'type' => 'textarea'
			],
		];
		if ( $with_parent ) {
			$fields[] = [
				'key' => 'parent',
				'label' => 'Parent Term ID',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'woocommerce',
					'query' => 'terms',
					'select' => [ 'id', 'name' ],
					'where' => [
						'taxonomy' => $taxonomy,
					],
				],
			];
		}
		return $fields;
	}

	protected static function field_term_update( bool $with_parent = true, string $taxonomy = 'product_cat' ): array {
		$fields = array_merge(self::field_term_id( $taxonomy ), [
			[
				'key' => 'name',
				'label' => 'Name',
				'type' => 'text'
			],
			[
				'key' => 'slug',
				'label' => 'Slug',
				'type' => 'text'
			],
			[
				'key' => 'description',
				'label' => 'Description',
				'type' => 'textarea'
			],
		]);
		if ( $with_parent ) {
			$fields[] = [
				'key' => 'parent',
				'label' => 'Parent Term ID',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'woocommerce',
					'query' => 'terms',
					'select' => [ 'id', 'name' ],
					'where' => [
						'taxonomy' => $taxonomy,
					],
				],
			];
		}
		return $fields;
	}

	protected static function field_term_delete( string $taxonomy = 'product_cat' ): array {
		return self::field_term_id( $taxonomy );
	}

	protected static function field_attribute_id(): array {
		return [
			[
				'key' => 'attribute_id',
				'label' => 'Attribute ID',
				'type' => 'select',
				'dynamic' => [
					'integration' => 'woocommerce',
					'query' => 'attributes',
					'select' => [ 'id', 'label' ],
				],
				'required' => true,
			]
		];
	}

	protected static function field_attribute_create(): array {
		return [
			[
				'key' => 'name',
				'label' => 'Name',
				'type' => 'text',
				'required' => true
			],
			[
				'key' => 'slug',
				'label' => 'Slug',
				'type' => 'text'
			],
			[
				'key' => 'type',
				'label' => 'Type',
				'type' => 'select',
				'options' => [
					[
						'label' => 'Select',
						'value' => 'select'
					],
					[
						'label' => 'Text',
						'value' => 'text'
					],
				]
			],
			[
				'key' => 'order_by',
				'label' => 'Order By',
				'type' => 'select',
				'options' => [
					[
						'label' => 'Name',
						'value' => 'name'
					],
					[
						'label' => 'Name (numeric)',
						'value' => 'name_num'
					],
					[
						'label' => 'ID',
						'value' => 'id'
					],
					[
						'label' => 'Menu Order',
						'value' => 'menu_order'
					],
				]
			],
			[
				'key' => 'has_archives',
				'label' => 'Has Archives',
				'type' => 'boolean'
			],
		];
	}

	protected static function field_attribute_update(): array {
		return array_merge(self::field_attribute_id(), [
			[
				'key' => 'name',
				'label' => 'Name',
				'type' => 'text'
			],
			[
				'key' => 'slug',
				'label' => 'Slug',
				'type' => 'text'
			],
			[
				'key' => 'type',
				'label' => 'Type',
				'type' => 'select',
				'options' => [
					[
						'label' => 'Select',
						'value' => 'select'
					],
					[
						'label' => 'Text',
						'value' => 'text'
					],
				]
			],
			[
				'key' => 'order_by',
				'label' => 'Order By',
				'type' => 'select',
				'options' => [
					[
						'label' => 'Name',
						'value' => 'name'
					],
					[
						'label' => 'Name (numeric)',
						'value' => 'name_num'
					],
					[
						'label' => 'ID',
						'value' => 'id'
					],
					[
						'label' => 'Menu Order',
						'value' => 'menu_order'
					],
				]
			],
			[
				'key' => 'has_archives',
				'label' => 'Has Archives',
				'type' => 'boolean'
			],
		]);
	}

	public static function query_dynamic_orders( $q ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return [];
		}

		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		$args = [ 'limit' => $limit ];
		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$orders = wc_get_orders( $args );
		$items = [];

		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$id = $order->get_id();
			$order_number = $order->get_order_number();
			$email = $order->get_billing_email();
			$label = '#' . $order_number . ( $email ? ' - ' . $email : '' );
			if ( ! self::matches_dynamic_search( $search, $label ) ) {
				continue;
			}
			$items[] = [
				'id' => (string) $id,
				'label' => $label,
				'order_number' => (string) $order_number,
				'status' => $order->get_status(),
			];
		}

		return array_slice( $items, 0, $limit );
	}

	public static function query_dynamic_customers( $q ): array {
		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );
		$items = [];

		if ( class_exists( '\WC_Customer_Query' ) ) {
			$args = [
				'limit' => $limit,
			];
			if ( '' !== $search ) {
				$args['search'] = '*' . $search . '*';
				$args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
			}
			$query = new \WC_Customer_Query( $args );
			$customers = $query->get_customers();

			foreach ( $customers as $customer ) {
				if ( ! $customer instanceof \WC_Customer ) {
					continue;
				}
				$id = $customer->get_id();
				$email = $customer->get_email();
				$name = trim( $customer->get_first_name() . ' ' . $customer->get_last_name() );
				$label = '' !== $name ? $name : $email;
				if ( '' === $label ) {
					$label = 'Customer #' . $id;
				}
				if ( ! self::matches_dynamic_search( $search, $label ) ) {
					continue;
				}
				$items[] = [
					'id' => (string) $id,
					'label' => $label,
					'email' => $email,
				];
			}
		} elseif ( function_exists( 'get_users' ) ) {
			$args = [ 'number' => $limit ];
			if ( '' !== $search ) {
				$args['search'] = '*' . $search . '*';
				$args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
			}
			$users = get_users( $args );
			foreach ( $users as $user ) {
				$id = $user->ID ?? 0;
				if ( ! $id ) {
					continue;
				}
				$label = ! empty( $user->display_name ) ? $user->display_name : ( $user->user_email ?? '' );
				if ( '' === $label ) {
					$label = 'User #' . $id;
				}
				if ( ! self::matches_dynamic_search( $search, $label ) ) {
					continue;
				}
				$items[] = [
					'id' => (string) $id,
					'label' => $label,
					'email' => $user->user_email ?? '',
				];
			}
		}//end if

		return array_slice( $items, 0, $limit );
	}

	public static function query_dynamic_products( $q ): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return [];
		}

		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		$args = [
			'limit' => $limit,
			'status' => 'publish',
		];
		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$products = wc_get_products( $args );
		$items = [];

		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			$id = $product->get_id();
			$name = $product->get_name();
			if ( '' === $name ) {
				$name = 'Product #' . $id;
			}
			if ( ! self::matches_dynamic_search( $search, $name ) ) {
				continue;
			}
			$items[] = [
				'id' => (string) $id,
				'name' => $name,
				'sku' => $product->get_sku(),
			];
		}

		return array_slice( $items, 0, $limit );
	}

	public static function query_dynamic_variations( $q ): array {
		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		$args = [
			'post_type' => 'product_variation',
			'post_status' => [ 'publish', 'private' ],
			'posts_per_page' => $limit,
			's' => $search,
		];

		$posts = function_exists( 'get_posts' ) ? get_posts( $args ) : [];
		$items = [];

		foreach ( $posts as $post ) {
			$id = $post->ID ?? 0;
			if ( ! $id ) {
				continue;
			}

			$label = '#' . $id;
			$product_id = (int) ( $post->post_parent ?? 0 );
			if ( $product_id > 0 ) {
				$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
				if ( $product instanceof \WC_Product ) {
					$label .= ' - ' . $product->get_name();
				}
			}

			if ( ! self::matches_dynamic_search( $search, $label ) ) {
				continue;
			}

			$items[] = [
				'id' => (string) $id,
				'label' => $label,
				'parent_id' => (string) $product_id,
			];
		}//end foreach

		return array_slice( $items, 0, $limit );
	}

	public static function query_dynamic_coupons( $q ): array {
		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );
		$items = [];

		if ( function_exists( 'wc_get_coupons' ) ) {
			$args = [ 'limit' => $limit ];
			if ( '' !== $search ) {
				$args['search'] = $search;
			}
			$coupons = wc_get_coupons( $args );
			foreach ( $coupons as $coupon ) {
				if ( ! $coupon instanceof \WC_Coupon ) {
					continue;
				}
				$id = $coupon->get_id();
				$code = $coupon->get_code();
				$label = '' !== $code ? $code : 'Coupon #' . $id;
				if ( ! self::matches_dynamic_search( $search, $label ) ) {
					continue;
				}
				$items[] = [
					'id' => (string) $id,
					'code' => $code,
				];
			}
		} elseif ( function_exists( 'get_posts' ) ) {
			$args = [
				'post_type' => 'shop_coupon',
				'numberposts' => $limit,
				's' => $search,
			];
			$posts = get_posts( $args );
			foreach ( $posts as $post ) {
				$id = $post->ID ?? 0;
				if ( ! $id ) {
					continue;
				}
				$code = $post->post_title ?? '';
				if ( '' !== $search && ! self::matches_dynamic_search( $search, $code ) ) {
					continue;
				}
				$items[] = [
					'id' => (string) $id,
					'code' => $code,
				];
			}
		}//end if

		return array_slice( $items, 0, $limit );
	}

	public static function query_dynamic_terms( $q ): array {
		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );
		$taxonomy = 'product_cat';

		if ( isset( $q['where'] ) && is_array( $q['where'] ) && ! empty( $q['where']['taxonomy'] ) ) {
			$taxonomy = (string) $q['where']['taxonomy'];
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$args = [
			'taxonomy' => $taxonomy,
			'hide_empty' => false,
			'number' => $limit,
		];
		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$terms = get_terms( $args );
		$items = [];

		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$items[] = [
				'id' => (string) $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
				'taxonomy' => $term->taxonomy,
			];
		}

		return array_slice( $items, 0, $limit );
	}

	public static function query_dynamic_attributes( $q ): array {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return [];
		}

		$q = is_array( $q ) ? $q : [];
		$limit = self::normalize_dynamic_limit( $q );
		$search = self::normalize_dynamic_search( $q );

		$attrs = wc_get_attribute_taxonomies();
		$items = [];

		foreach ( $attrs as $attr ) {
			$id = $attr->attribute_id ?? 0;
			if ( ! $id ) {
				continue;
			}
			$label = $attr->attribute_label ?? $attr->attribute_name ?? '';
			if ( '' === $label ) {
				$label = 'Attribute #' . $id;
			}
			if ( ! self::matches_dynamic_search( $search, $label ) ) {
				continue;
			}
			$items[] = [
				'id' => (string) $id,
				'label' => $label,
				'name' => $attr->attribute_name ?? '',
			];
		}

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
