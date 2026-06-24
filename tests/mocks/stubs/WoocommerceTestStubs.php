<?php

namespace {
	if ( ! function_exists( 'sanitize_user' ) ) {
		function sanitize_user( $username, $strict = false ) {
			unset( $strict );
			return preg_replace( '/[^a-z0-9_\-@.]/i', '', (string) $username );
		}
	}

	if ( ! class_exists( 'WooTestStore' ) ) {
		class WooTestStore {
			private static int $next_order_id = 600;
			private static int $next_product_id = 950;
			private static int $next_customer_id = 750;
			private static int $next_coupon_id = 350;
			private static int $next_attribute_id = 10;
			private static array $orders = [];
			private static array $products = [];
			private static array $customers = [];
			private static array $coupons = [];
			private static array $attributes = [];
			private static array $applied_coupons = [];
			private static ?WC_Cart $cart = null;

			public static function reset(): void {
				self::$next_order_id = 600;
				self::$next_product_id = 950;
				self::$next_customer_id = 750;
				self::$next_coupon_id = 350;
				self::$next_attribute_id = 10;
				self::$orders = [
					501 => [
						'id'                  => 501,
						'status'              => 'processing',
						'total'               => 49.99,
						'currency'            => 'USD',
						'customer_id'         => 701,
						'billing_email'       => 'customer701@example.com',
						'billing_first_name'  => 'John',
						'billing_last_name'   => 'Doe',
						'notes'               => [],
						'meta'                => [],
						'billing'             => [],
						'shipping'            => [],
					],
					502 => [
						'id'                  => 502,
						'status'              => 'completed',
						'total'               => 19.99,
						'currency'            => 'USD',
						'customer_id'         => 701,
						'billing_email'       => 'customer701@example.com',
						'billing_first_name'  => 'John',
						'billing_last_name'   => 'Doe',
						'notes'               => [],
						'meta'                => [],
						'billing'             => [],
						'shipping'            => [],
					],
				];
				self::$products = [
					901 => [ 'id' => 901, 'name' => 'Simple Product', 'status' => 'publish', 'sku' => 'SKU-901', 'price' => '29.99', 'type' => 'simple', 'total_sales' => 12, 'attributes' => [] ],
					902 => [ 'id' => 902, 'name' => 'Variable Product', 'status' => 'publish', 'sku' => 'SKU-902', 'price' => '39.99', 'type' => 'variable', 'total_sales' => 8, 'attributes' => [] ],
					903 => [ 'id' => 903, 'name' => 'Grouped Product', 'status' => 'publish', 'sku' => 'SKU-903', 'price' => '49.99', 'type' => 'grouped', 'total_sales' => 5, 'attributes' => [] ],
					904 => [ 'id' => 904, 'name' => 'External Product', 'status' => 'publish', 'sku' => 'SKU-904', 'price' => '59.99', 'type' => 'external', 'total_sales' => 3, 'attributes' => [] ],
					905 => [ 'id' => 905, 'name' => 'Variation Product', 'status' => 'publish', 'sku' => 'SKU-905', 'price' => '24.99', 'type' => 'variation', 'parent_id' => 902, 'total_sales' => 2, 'attributes' => [] ],
					906 => [ 'id' => 906, 'name' => 'Subscription Product', 'status' => 'publish', 'sku' => 'SKU-906', 'price' => '15.99', 'type' => 'subscription', 'total_sales' => 7, 'attributes' => [] ],
				];
				self::$customers = [
					701 => [ 'id' => 701, 'email' => 'customer701@example.com', 'username' => 'customer701', 'first_name' => 'Casey', 'last_name' => 'Customer', 'billing' => [], 'shipping' => [] ],
				];
				self::$coupons = [
					301 => [ 'id' => 301, 'code' => 'SAVE10', 'amount' => '10', 'discount_type' => 'percent', 'email_restrictions' => [], 'product_ids' => [], 'excluded_product_ids' => [] ],
					302 => [ 'id' => 302, 'code' => 'WELCOME', 'amount' => '5', 'discount_type' => 'fixed_cart', 'email_restrictions' => [], 'product_ids' => [], 'excluded_product_ids' => [] ],
				];
				self::$attributes = [
					1 => [ 'id' => 1, 'name' => 'Color', 'slug' => 'pa_color', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ],
				];
				self::$applied_coupons = [ 'SAVE10' ];
				self::$cart = new WC_Cart();
				self::$cart->seedItem( 'cart-item-1', [
					'product_id'    => 901,
					'variation_id'  => 0,
					'quantity'      => 2,
					'line_subtotal' => 59.98,
					'line_total'    => 49.98,
				] );
			}

			public static function getCart(): WC_Cart {
				if ( null === self::$cart ) {
					self::reset();
				}
				return self::$cart;
			}

			public static function getAppliedCoupons(): array {
				return self::$applied_coupons;
			}

			public static function setAppliedCoupons( array $codes ): void {
				self::$applied_coupons = array_values( array_unique( $codes ) );
			}

			public static function allOrders(): array {
				return array_values( self::$orders );
			}

			public static function getOrder( int $id ): ?array {
				return self::$orders[ $id ] ?? null;
			}

			public static function saveOrder( array $data ): int {
				$id = (int) ( $data['id'] ?? 0 );
				if ( $id <= 0 ) {
					$id = ++self::$next_order_id;
				}
				$data['id'] = $id;
				self::$orders[ $id ] = array_merge( self::$orders[ $id ] ?? [], $data );
				return $id;
			}

			public static function allProducts(): array {
				return array_values( self::$products );
			}

			public static function getProduct( int $id ): ?array {
				return self::$products[ $id ] ?? null;
			}

			public static function findProductBySku( string $sku ): ?array {
				foreach ( self::$products as $product ) {
					if ( ( $product['sku'] ?? '' ) === $sku ) {
						return $product;
					}
				}
				return null;
			}

			public static function saveProduct( array $data ): int {
				$id = (int) ( $data['id'] ?? 0 );
				if ( $id <= 0 ) {
					$id = ++self::$next_product_id;
				}
				$data['id'] = $id;
				self::$products[ $id ] = array_merge( self::$products[ $id ] ?? [ 'attributes' => [] ], $data );
				return $id;
			}

			public static function deleteProduct( int $id ): bool {
				if ( ! isset( self::$products[ $id ] ) ) {
					return false;
				}
				unset( self::$products[ $id ] );
				return true;
			}

			public static function allCustomers(): array {
				return array_values( self::$customers );
			}

			public static function getCustomer( int $id ): ?array {
				return self::$customers[ $id ] ?? null;
			}

			public static function findCustomerByEmail( string $email ): ?array {
				foreach ( self::$customers as $customer ) {
					if ( ( $customer['email'] ?? '' ) === $email ) {
						return $customer;
					}
				}
				return null;
			}

			public static function saveCustomer( array $data ): int {
				$id = (int) ( $data['id'] ?? 0 );
				if ( $id <= 0 ) {
					$id = ++self::$next_customer_id;
				}
				$data['id'] = $id;
				self::$customers[ $id ] = array_merge( self::$customers[ $id ] ?? [], $data );
				return $id;
			}

			public static function allCoupons(): array {
				return array_values( self::$coupons );
			}

			public static function getCoupon( int $id ): ?array {
				return self::$coupons[ $id ] ?? null;
			}

			public static function findCouponByCode( string $code ): ?array {
				foreach ( self::$coupons as $coupon ) {
					if ( ( $coupon['code'] ?? '' ) === $code ) {
						return $coupon;
					}
				}
				return null;
			}

			public static function saveCoupon( array $data ): int {
				$id = (int) ( $data['id'] ?? 0 );
				if ( $id <= 0 ) {
					$id = ++self::$next_coupon_id;
				}
				$data['id'] = $id;
				self::$coupons[ $id ] = array_merge( self::$coupons[ $id ] ?? [], $data );
				return $id;
			}

			public static function allAttributes(): array {
				return array_values( self::$attributes );
			}

			public static function getAttribute( int $id ): ?array {
				return self::$attributes[ $id ] ?? null;
			}

			public static function getAttributeIdBySlug( string $slug ): int {
				foreach ( self::$attributes as $attribute ) {
					if ( ( $attribute['slug'] ?? '' ) === $slug ) {
						return (int) $attribute['id'];
					}
				}
				return 0;
			}

			public static function saveAttribute( array $data ): int {
				$id = (int) ( $data['id'] ?? 0 );
				if ( $id <= 0 ) {
					$id = ++self::$next_attribute_id;
				}
				$data['id'] = $id;
				self::$attributes[ $id ] = array_merge( self::$attributes[ $id ] ?? [], $data );
				return $id;
			}

			public static function deleteAttribute( int $id ): bool {
				if ( ! isset( self::$attributes[ $id ] ) ) {
					return false;
				}
				unset( self::$attributes[ $id ] );
				return true;
			}
		}
	}

	if ( ! class_exists( 'WP_Term' ) ) {
		#[\AllowDynamicProperties]
		class WP_Term {
			public function __construct( array $data = [] ) {
				foreach ( $data as $key => $value ) {
					$this->$key = $value;
				}
			}
		}
	}

	if ( ! class_exists( 'WP_Comment_Query' ) ) {
		class WP_Comment_Query {
			public array $comments = [];
			public int $found_comments = 0;

			public function __construct( array $args = [] ) {
				unset( $args );
				$comment = get_comment( 1 );
				$this->comments = $comment ? [ $comment ] : [];
				$this->found_comments = count( $this->comments );
			}
		}
	}

	if ( ! function_exists( 'get_term_by' ) ) {
		function get_term_by( $field, $value, $taxonomy ) {
			$terms = get_terms( [ 'taxonomy' => $taxonomy ] );
			foreach ( $terms as $term ) {
				if ( isset( $term->$field ) && (string) $term->$field === (string) $value ) {
					return $term;
				}
			}
			return false;
		}
	}

	if ( ! function_exists( 'wp_count_posts' ) ) {
		function wp_count_posts( $type = 'post', $perm = '' ) {
			unset( $perm );
			if ( 'product' === $type || 'product_variation' === $type ) {
				$counts = [ 'publish' => 0, 'draft' => 0, 'trash' => 0 ];
				foreach ( WooTestStore::allProducts() as $product ) {
					if ( 'product_variation' === $type && ( $product['type'] ?? '' ) !== 'variation' ) {
						continue;
					}
					if ( 'product' === $type && ( $product['type'] ?? '' ) === 'variation' ) {
						continue;
					}
					$status = $product['status'] ?? 'publish';
					$counts[ $status ] = ( $counts[ $status ] ?? 0 ) + 1;
				}
				return (object) $counts;
			}
			return (object) [ 'publish' => 0 ];
		}
	}

	if ( ! function_exists( 'wp_create_user' ) ) {
		function wp_create_user( $username, $password, $email ) {
			unset( $password );
			return WooTestStore::saveCustomer( [
				'username' => (string) $username,
				'email'    => (string) $email,
			] );
		}
	}

	if ( ! class_exists( 'WC_Order' ) ) {
		class WC_Order {
			private array $data = [];

			public function __construct( int $id = 0 ) {
				$this->data = $id > 0 ? ( WooTestStore::getOrder( $id ) ?? [] ) : [];
			}

			public function get_id(): int { return (int) ( $this->data['id'] ?? 0 ); }
			public function get_order_number(): string { return (string) $this->get_id(); }
			public function get_status(): string { return (string) ( $this->data['status'] ?? '' ); }
			public function get_total(): float { return (float) ( $this->data['total'] ?? 0 ); }
			public function get_currency(): string { return (string) ( $this->data['currency'] ?? 'USD' ); }
			public function get_customer_id(): int { return (int) ( $this->data['customer_id'] ?? 0 ); }
			public function get_billing_email(): string { return (string) ( $this->data['billing_email'] ?? '' ); }
			public function get_billing_first_name(): string { return (string) ( $this->data['billing_first_name'] ?? '' ); }
			public function get_billing_last_name(): string { return (string) ( $this->data['billing_last_name'] ?? '' ); }
			public function get_order_key(): string { return (string) ( $this->data['order_key'] ?? 'wc_order_' . $this->get_id() ); }
			public function set_currency( string $currency ): void { $this->data['currency'] = $currency; }
			public function set_status( string $status ): void { $this->data['status'] = $status; }
			public function set_customer_id( int $customer_id ): void { $this->data['customer_id'] = $customer_id; }
			public function set_total( float $total ): void { $this->data['total'] = $total; }
			public function set_address( array $address, string $type = 'billing' ): void {
				$this->data[ $type ] = $address;
				if ( 'billing' === $type ) {
					if ( isset( $address['email'] ) ) {
						$this->data['billing_email'] = $address['email'];
					}
					if ( isset( $address['first_name'] ) ) {
						$this->data['billing_first_name'] = $address['first_name'];
					}
					if ( isset( $address['last_name'] ) ) {
						$this->data['billing_last_name'] = $address['last_name'];
					}
				}
			}
			public function update_meta_data( string $key, $value ): void { $this->data['meta'][ $key ] = $value; }
			public function set_props( array $data ): void { $this->data = array_merge( $this->data, $data ); }
			public function add_order_note( string $note, bool $is_customer_note = false ) {
				$this->data['notes'][] = [ 'note' => $note, 'customer' => $is_customer_note ];
				$this->save();
				return count( $this->data['notes'] );
			}
			public function save(): int {
				$this->data['id'] = WooTestStore::saveOrder( $this->data );
				return $this->get_id();
			}
		}
	}

	if ( ! class_exists( 'WC_Product' ) ) {
		class WC_Product {
			protected array $data = [];

			public function __construct( int $id = 0, string $default_type = 'simple' ) {
				$this->data = $id > 0 ? ( WooTestStore::getProduct( $id ) ?? [] ) : [ 'type' => $default_type, 'attributes' => [] ];
			}

			public function get_id(): int { return (int) ( $this->data['id'] ?? 0 ); }
			public function get_name(): string { return (string) ( $this->data['name'] ?? '' ); }
			public function get_status(): string { return (string) ( $this->data['status'] ?? 'publish' ); }
			public function get_sku(): string { return (string) ( $this->data['sku'] ?? '' ); }
			public function get_price(): string { return (string) ( $this->data['price'] ?? '' ); }
			public function get_type(): string { return (string) ( $this->data['type'] ?? 'simple' ); }
			public function get_total_sales(): int { return (int) ( $this->data['total_sales'] ?? 0 ); }
			public function get_attributes(): array { return $this->data['attributes'] ?? []; }
			public function set_name( string $name ): void { $this->data['name'] = $name; }
			public function set_status( string $status ): void { $this->data['status'] = $status; }
			public function set_sku( string $sku ): void { $this->data['sku'] = $sku; }
			public function set_regular_price( $price ): void { $this->data['regular_price'] = (string) $price; }
			public function set_sale_price( $price ): void { $this->data['sale_price'] = (string) $price; }
			public function set_price( $price ): void { $this->data['price'] = (string) $price; }
			public function set_description( string $value ): void { $this->data['description'] = $value; }
			public function set_short_description( string $value ): void { $this->data['short_description'] = $value; }
			public function set_stock_quantity( $value ): void { $this->data['stock_quantity'] = (int) $value; }
			public function set_manage_stock( bool $value ): void { $this->data['manage_stock'] = $value; }
			public function set_stock_status( string $value ): void { $this->data['stock_status'] = $value; }
			public function set_parent_id( int $parent_id ): void { $this->data['parent_id'] = $parent_id; }
			public function set_attributes( array $attributes ): void { $this->data['attributes'] = $attributes; }
			public function set_props( array $data ): void { $this->data = array_merge( $this->data, $data ); }
			public function save(): int {
				$this->data['id'] = WooTestStore::saveProduct( $this->data );
				return $this->get_id();
			}
		}
	}

	if ( ! class_exists( 'WC_Product_Simple' ) ) {
		class WC_Product_Simple extends WC_Product {
			public function __construct( int $id = 0 ) { parent::__construct( $id, 'simple' ); }
		}
	}

	if ( ! class_exists( 'WC_Product_Variation' ) ) {
		class WC_Product_Variation extends WC_Product {
			public function __construct( int $id = 0 ) { parent::__construct( $id, 'variation' ); }
		}
	}

	if ( ! class_exists( 'WC_Customer' ) ) {
		class WC_Customer {
			private array $data = [];

			public function __construct( int $id = 0 ) {
				$this->data = $id > 0 ? ( WooTestStore::getCustomer( $id ) ?? [] ) : [];
			}

			public function get_id(): int { return (int) ( $this->data['id'] ?? 0 ); }
			public function get_email(): string { return (string) ( $this->data['email'] ?? '' ); }
			public function get_username(): string { return (string) ( $this->data['username'] ?? '' ); }
			public function set_first_name( string $value ): void { $this->data['first_name'] = $value; }
			public function set_last_name( string $value ): void { $this->data['last_name'] = $value; }
			public function set_billing( array $value ): void { $this->data['billing'] = $value; }
			public function set_shipping( array $value ): void { $this->data['shipping'] = $value; }
			public function save(): int {
				$this->data['id'] = WooTestStore::saveCustomer( $this->data );
				return $this->get_id();
			}
		}
	}

	if ( ! class_exists( 'WC_Coupon' ) ) {
		class WC_Coupon {
			private array $data = [];

			public function __construct( $id_or_code = 0 ) {
				if ( is_numeric( $id_or_code ) && (int) $id_or_code > 0 ) {
					$this->data = WooTestStore::getCoupon( (int) $id_or_code ) ?? [];
				} elseif ( is_string( $id_or_code ) && '' !== $id_or_code ) {
					$this->data = WooTestStore::findCouponByCode( $id_or_code ) ?? [];
				}
			}

			public function get_id(): int { return (int) ( $this->data['id'] ?? 0 ); }
			public function get_code(): string { return (string) ( $this->data['code'] ?? '' ); }
			public function get_amount(): string { return (string) ( $this->data['amount'] ?? '' ); }
			public function get_discount_type(): string { return (string) ( $this->data['discount_type'] ?? '' ); }
			public function get_email_restrictions(): array { return $this->data['email_restrictions'] ?? []; }
			public function set_code( string $value ): void { $this->data['code'] = $value; }
			public function set_discount_type( string $value ): void { $this->data['discount_type'] = $value; }
			public function set_amount( string $value ): void { $this->data['amount'] = $value; }
			public function set_usage_limit( int $value ): void { $this->data['usage_limit'] = $value; }
			public function set_date_expires( string $value ): void { $this->data['expiry_date'] = $value; }
			public function set_email_restrictions( array $value ): void { $this->data['email_restrictions'] = $value; }
			public function set_product_ids( array $value ): void { $this->data['product_ids'] = $value; }
			public function set_excluded_product_ids( array $value ): void { $this->data['excluded_product_ids'] = $value; }
			public function set_props( array $data ): void { $this->data = array_merge( $this->data, $data ); }
			public function save(): int {
				$this->data['id'] = WooTestStore::saveCoupon( $this->data );
				return $this->get_id();
			}
		}
	}

	if ( ! class_exists( 'WC_Product_Attribute' ) ) {
		class WC_Product_Attribute {
			private int $id = 0;
			private string $name = '';
			private array $options = [];
			private bool $visible = true;
			private bool $variation = false;
			private int $position = 0;

			public function set_id( int $id ): void { $this->id = $id; }
			public function set_name( string $name ): void { $this->name = $name; }
			public function set_options( array $options ): void { $this->options = $options; }
			public function set_visible( bool $visible ): void { $this->visible = $visible; }
			public function set_variation( bool $variation ): void { $this->variation = $variation; }
			public function set_position( int $position ): void { $this->position = $position; }
			public function get_name(): string { return $this->name; }
			public function get_options(): array { return $this->options; }
			public function get_visible(): bool { return $this->visible; }
			public function get_variation(): bool { return $this->variation; }
		}
	}

	if ( ! class_exists( 'WC_Cart' ) ) {
		class WC_Cart {
			public array $removed_cart_contents = [];
			public array $cart_contents = [];

			public function seedItem( string $key, array $item ): void {
				$this->cart_contents[ $key ] = $item;
			}

			public function get_cart(): array { return $this->cart_contents; }
			public function get_totals(): array {
				$total = 0;
				foreach ( $this->cart_contents as $item ) {
					$total += (float) ( $item['line_total'] ?? 0 );
				}
				return [ 'total' => $total, 'subtotal' => $total + 10 ];
			}
			public function get_cart_contents_count(): int {
				return array_sum( array_map( static fn( $item ) => (int) ( $item['quantity'] ?? 0 ), $this->cart_contents ) );
			}
			public function add_to_cart( int $product_id, int $quantity = 1, int $variation_id = 0, array $variations = [], array $cart_item_data = [] ) {
				unset( $variations, $cart_item_data );
				$key = 'cart-item-' . ( count( $this->cart_contents ) + 1 );
				$this->cart_contents[ $key ] = [
					'product_id'    => $product_id,
					'variation_id'  => $variation_id,
					'quantity'      => $quantity,
					'line_subtotal' => 10 * $quantity,
					'line_total'    => 10 * $quantity,
				];
				return $key;
			}
			public function remove_cart_item( string $key ): bool {
				if ( ! isset( $this->cart_contents[ $key ] ) ) {
					return false;
				}
				$this->removed_cart_contents[ $key ] = $this->cart_contents[ $key ];
				unset( $this->cart_contents[ $key ] );
				return true;
			}
			public function apply_coupon( string $code ): bool {
				$codes = WooTestStore::getAppliedCoupons();
				$codes[] = $code;
				WooTestStore::setAppliedCoupons( $codes );
				return true;
			}
			public function get_applied_coupons(): array {
				return WooTestStore::getAppliedCoupons();
			}
			public function get_coupon_discount_totals(): array {
				return array_fill_keys( $this->get_applied_coupons(), 5 );
			}
			public function remove_coupon( string $code ): void {
				WooTestStore::setAppliedCoupons( array_values( array_filter(
					WooTestStore::getAppliedCoupons(),
					static fn( $item ) => $item !== $code
				) ) );
			}
		}
	}

	if ( ! class_exists( 'WC_Global' ) ) {
		class WC_Global {
			public WC_Cart $cart;

			public function __construct() {
				$this->cart = WooTestStore::getCart();
			}
		}
	}

	if ( ! function_exists( 'WC' ) ) {
		function WC() {
			static $instance = null;
			if ( null === $instance ) {
				$instance = new WC_Global();
			}
			$instance->cart = WooTestStore::getCart();
			return $instance;
		}
	}

	if ( ! function_exists( 'wc_get_order' ) ) {
		function wc_get_order( $order_id ) {
			$order_id = (int) $order_id;
			return $order_id > 0 && WooTestStore::getOrder( $order_id ) ? new WC_Order( $order_id ) : null;
		}
	}

	if ( ! function_exists( 'wc_create_order' ) ) {
		function wc_create_order( $args = [] ) {
			$order = new WC_Order();
			if ( ! empty( $args['customer_id'] ) ) {
				$order->set_customer_id( (int) $args['customer_id'] );
			}
			$order->save();
			return $order;
		}
	}

	if ( ! function_exists( 'wc_get_orders' ) ) {
		function wc_get_orders( $args = [] ) {
			$orders = array_map( static fn( $row ) => new WC_Order( (int) $row['id'] ), WooTestStore::allOrders() );
			if ( ! empty( $args['status'] ) && 'any' !== $args['status'] ) {
				$orders = array_values( array_filter( $orders, static fn( $order ) => $order->get_status() === $args['status'] ) );
			}
			if ( ! empty( $args['customer_id'] ) ) {
				$orders = array_values( array_filter( $orders, static fn( $order ) => $order->get_customer_id() === (int) $args['customer_id'] ) );
			}
			if ( ! empty( $args['billing_email'] ) ) {
				$orders = array_values( array_filter( $orders, static fn( $order ) => $order->get_billing_email() === $args['billing_email'] ) );
			}
			$limit = (int) ( $args['limit'] ?? count( $orders ) );
			if ( $limit > 0 ) {
				$orders = array_slice( $orders, 0, $limit );
			}
			if ( ! empty( $args['paginate'] ) ) {
				return (object) [ 'orders' => $orders, 'total' => count( $orders ) ];
			}
			return $orders;
		}
	}

	if ( ! function_exists( 'wc_get_product' ) ) {
		function wc_get_product( $product_id ) {
			$product_id = (int) $product_id;
			$product = WooTestStore::getProduct( $product_id );
			if ( ! $product ) {
				return null;
			}
			return 'variation' === ( $product['type'] ?? '' ) ? new WC_Product_Variation( $product_id ) : new WC_Product( $product_id );
		}
	}

	if ( ! function_exists( 'wc_get_products' ) ) {
		function wc_get_products( $args = [] ) {
			$products = array_map( static fn( $row ) => wc_get_product( $row['id'] ), WooTestStore::allProducts() );
			if ( ! empty( $args['type'] ) ) {
				$products = array_values( array_filter( $products, static fn( $product ) => $product && $product->get_type() === $args['type'] ) );
			}
			if ( ! empty( $args['orderby'] ) && 'total_sales' === $args['orderby'] ) {
				usort( $products, static fn( $a, $b ) => $b->get_total_sales() <=> $a->get_total_sales() );
			}
			$limit = (int) ( $args['limit'] ?? count( $products ) );
			if ( $limit > 0 ) {
				$products = array_slice( $products, 0, $limit );
			}
			if ( ! empty( $args['paginate'] ) ) {
				return (object) [ 'products' => $products, 'total' => count( $products ) ];
			}
			return $products;
		}
	}

	if ( ! function_exists( 'wc_get_customer_total_spent' ) ) {
		function wc_get_customer_total_spent( $customer_id ) {
			$total = 0.0;
			foreach ( WooTestStore::allOrders() as $order ) {
				if ( (int) $order['customer_id'] === (int) $customer_id && ( $order['status'] ?? '' ) === 'completed' ) {
					$total += (float) $order['total'];
				}
			}
			return $total;
		}
	}

	if ( ! function_exists( 'wc_get_customers' ) ) {
		function wc_get_customers( $args = [] ) {
			unset( $args );
			return array_map( static fn( $row ) => new WC_Customer( (int) $row['id'] ), WooTestStore::allCustomers() );
		}
	}

	if ( ! function_exists( 'wc_create_new_customer' ) ) {
		function wc_create_new_customer( $email, $username = '', $password = '' ) {
			unset( $password );
			return WooTestStore::saveCustomer( [
				'email'    => (string) $email,
				'username' => '' !== $username ? $username : (string) $email,
			] );
		}
	}

	if ( ! function_exists( 'wc_get_customer_id_by_email' ) ) {
		function wc_get_customer_id_by_email( $email ) {
			$customer = WooTestStore::findCustomerByEmail( (string) $email );
			return $customer ? (int) $customer['id'] : 0;
		}
	}

	if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
		function wc_get_product_id_by_sku( $sku ) {
			$product = WooTestStore::findProductBySku( (string) $sku );
			return $product ? (int) $product['id'] : 0;
		}
	}

	if ( ! function_exists( 'wc_get_coupons' ) ) {
		function wc_get_coupons( $args = [] ) {
			unset( $args );
			return array_map( static fn( $row ) => new WC_Coupon( (int) $row['id'] ), WooTestStore::allCoupons() );
		}
	}

	if ( ! function_exists( 'wc_sanitize_taxonomy_name' ) ) {
		function wc_sanitize_taxonomy_name( $name ) {
			return sanitize_key( (string) $name );
		}
	}

	if ( ! function_exists( 'wc_attribute_taxonomy_id_by_name' ) ) {
		function wc_attribute_taxonomy_id_by_name( $name ) {
			return WooTestStore::getAttributeIdBySlug( (string) $name );
		}
	}

	if ( ! function_exists( 'wc_create_attribute' ) ) {
		function wc_create_attribute( $data ) {
			return WooTestStore::saveAttribute( $data );
		}
	}

	if ( ! function_exists( 'wc_update_attribute' ) ) {
		function wc_update_attribute( $attribute_id, $data ) {
			$data['id'] = (int) $attribute_id;
			WooTestStore::saveAttribute( $data );
			return $attribute_id;
		}
	}

	if ( ! function_exists( 'wc_get_attribute' ) ) {
		function wc_get_attribute( $attribute_id ) {
			$attribute = WooTestStore::getAttribute( (int) $attribute_id );
			return $attribute ? (object) $attribute : false;
		}
	}

	if ( ! function_exists( 'wc_delete_attribute' ) ) {
		function wc_delete_attribute( $attribute_id ) {
			return WooTestStore::deleteAttribute( (int) $attribute_id );
		}
	}
}