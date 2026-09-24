<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Woo\Helper;
use Zaplane\Integrations\Woo\OrderActionsTrait;
use Zaplane\Integrations\Woo\CustomerActionsTrait;
use Zaplane\Integrations\Woo\ProductActionsTrait;
use Zaplane\Integrations\Woo\TaxonomyActionsTrait;
use Zaplane\Integrations\Woo\AttributeActionsTrait;
use Zaplane\Integrations\Woo\CartActionsTrait;
use Zaplane\Integrations\Woo\CouponActionsTrait;
use Zaplane\Integrations\Woo\ReviewActionsTrait;
use Zaplane\Integrations\Woo\AbandonedCartActionsTrait;
use Zaplane\Integrations\Woo\InactiveCustomerCronTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Woocommerce extends IntegrationBase {

	use Helper;
	use OrderActionsTrait;
	use CustomerActionsTrait;
	use ProductActionsTrait;
	use TaxonomyActionsTrait;
	use AttributeActionsTrait;
	use CartActionsTrait;
	use CouponActionsTrait;
	use ReviewActionsTrait;
	use AbandonedCartActionsTrait;
	use InactiveCustomerCronTrait;

	public static function get_slug(): string {
		return 'woocommerce';
	}

	public static function get_name(): string {
		return 'WooCommerce';
	}

	public static function get_icon(): string {
		return 'woo.svg';
	}

	/** @inheritDoc */
	public static function get_docs_url(): array {
		return [
			'trigger' => 'https://zaplane.app/docs/woocommerce/',
			'action'  => 'https://zaplane.app/docs/woocommerce/',
		];
	}

	/** Trigger events the recipe tester can self-seed with real WooCommerce data. */
	public static function get_seedable_triggers(): array {
		return [
			'new_order',
			'order_status_pending',
			'order_status_processing',
			'order_status_on_hold',
			'order_status_completed',
			'order_status_cancelled',
			'order_status_refunded',
			'order_status_failed',
			'order_status_changed',
			'create_product',
			'update_product',
		];
	}

	/** Action events the recipe tester can run with sample config. */
	public static function get_testable_actions(): array {
		return [ 'create_order', 'create_customer', 'create_product', 'create_coupon' ];
	}

	/** A valid config to execute a WooCommerce action with for testing. */
	public static function get_sample_action_config( string $event ): ?array {
		$rand = substr( md5( uniqid( 'r', true ) ), 0, 8 );
		switch ( $event ) {
			case 'create_order':
				return [ 'status' => 'processing' ];
			case 'create_customer':
				return [
					'email' => "customer_{$rand}@example.test",
					'first_name' => 'Recipe'
				];
			case 'create_product':
				return [
					'name' => "Recipe Product {$rand}",
					'regular_price' => '10'
				];
			case 'create_coupon':
				return [
					'code' => "recipe_{$rand}",
					'amount' => '10'
				];
		}
		return null;
	}

	/** Create real WooCommerce data and return the hook arguments for a trigger. */
	public static function seed_trigger_args( string $event ): ?array {
		if ( 'new_order' === $event || 0 === strpos( $event, 'order_status_' ) ) {
			if ( ! function_exists( 'wc_create_order' ) ) {
				return null;
			}
			$status = ( 0 === strpos( $event, 'order_status_' ) )
				? str_replace( '_', '-', substr( $event, strlen( 'order_status_' ) ) )
				: 'processing';

			$order = wc_create_order();
			$order->set_total( 50 );
			$order->save();

			if ( 'order_status_changed' === $event ) {
				$old = $order->get_status();
				$order->set_status( 'completed' );
				$order->save();
				// woocommerce_order_status_changed: ( $order_id, $from, $to, $order ).
				return [ $order->get_id(), $old, 'completed', $order ];
			}

			$order->set_status( $status ?: 'processing' );
			$order->save();
			// new_order + woocommerce_order_status_*: ( $order_id, $order ).
			return [ $order->get_id(), $order ];
		}//end if

		if ( 'create_product' === $event || 'update_product' === $event ) {
			if ( ! class_exists( '\WC_Product_Simple' ) ) {
				return null;
			}
			$product = new \WC_Product_Simple();
			$product->set_name( 'Zaplane Recipe Product' );
			$product->set_regular_price( '10' );
			$product->save();
			$post = get_post( $product->get_id() );
			// wp_after_insert_post: ( $post_id, $post, $update, $post_before ).
			return [ $product->get_id(), $post, ( 'update_product' === $event ), null ];
		}

		return null;
	}

	public static function get_triggers(): array {
		return [
			'new_order' => [
				'label' => 'New Order Created',
				'hook' => 'woocommerce_new_order'
			],
			'restore_order' => [
				'label' => 'Restore Order',
				'hook' => 'woocommerce_untrash_order'
			],
			'order_status_pending' => [
				'label' => 'Order Status Set to Pending',
				'hook' => 'woocommerce_order_status_pending'
			],
			'order_status_failed' => [
				'label' => 'Order Status Set to Failed',
				'hook' => 'woocommerce_order_status_failed'
			],
			'order_status_on_hold' => [
				'label' => 'Order Status Set to On-hold',
				'hook' => 'woocommerce_order_status_on-hold'
			],
			'order_status_processing' => [
				'label' => 'Order Status Set to Processing',
				'hook' => 'woocommerce_order_status_processing'
			],
			'order_status_completed' => [
				'label' => 'Order Status Set to Completed',
				'hook' => 'woocommerce_order_status_completed'
			],
			'order_status_refunded' => [
				'label' => 'Order Status Set to Refunded',
				'hook' => 'woocommerce_order_status_refunded'
			],
			'order_status_cancelled' => [
				'label' => 'Order Status Set to Cancelled',
				'hook' => 'woocommerce_order_status_cancelled'
			],
			'order_status_changed' => [
				'label' => 'Order Status Changed',
				'hook' => 'woocommerce_order_status_changed'
			],
			'new_coupon' => [
				'label' => 'New Coupon Created',
				'hook' => 'woocommerce_update_coupon'
			],
			'create_customer' => [
				'label' => 'Create Customer',
				'hook' => 'user_register'
			],
			'update_customer' => [
				'label' => 'Update Customer',
				'hook' => 'profile_update'
			],
			'delete_customer' => [
				'label' => 'Delete Customer',
				'hook' => 'delete_user'
			],
			'create_product' => [
				'label' => 'Create Product',
				'hook' => 'wp_after_insert_post'
			],
			'update_product' => [
				'label' => 'Update Product',
				'hook' => 'wp_after_insert_post'
			],
			'delete_product' => [
				'label' => 'Trash Product',
				'hook' => 'transition_post_status'
			],
			'restore_product' => [
				'label' => 'Restore Product',
				'hook' => 'transition_post_status'
			],
			'product_status_updated' => [
				'label' => 'Product Status Updated',
				'hook' => 'transition_post_status'
			],
			'product_status_changed' => [
				'label' => 'Product Status Changed',
				'hook' => 'transition_post_status'
			],
			'product_added_to_cart' => [
				'label' => 'Product Added to Cart',
				'hook' => 'woocommerce_add_to_cart'
			],
			'product_removed_from_cart' => [
				'label' => 'Product Removed from Cart',
				'hook' => 'woocommerce_cart_item_removed'
			],
			'cart_abandoned' => [
				'label' => 'Cart Abandoned',
				'hook'  => 'zaplane/abandoned_cart/started',
			],
			'cart_recovered' => [
				'label' => 'Cart Recovered',
				'hook'  => 'zaplane/abandoned_cart/recovered',
			],
			'cart_lost' => [
				'label' => 'Cart Lost',
				'hook'  => 'zaplane/abandoned_cart/lost',
			],
			'inactive_customer' => [
				'label' => 'Inactive Customer',
				'hook'  => 'zaplane_woo_inactive_customer',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'inactive_customer' !== $trigger ) {
			return [];
		}

		return [
			[
				'key'         => 'days',
				'label'       => 'Days Since Last Order',
				'type'        => 'expression',
				'required'    => true,
				'placeholder' => 'e.g. 30',
			],
			[
				'key'      => 'tag_ids',
				'label'    => 'Apply Tags to Contact (optional)',
				'type'     => 'multi-select',
				'required' => false,
				'dynamic'  => [
					'integration' => 'gemcrm',
					'query'       => 'gemcrm_tag_query',
					'select'      => [ 'value', 'label' ],
				],
			],
			[
				'key'      => 'list_ids',
				'label'    => 'Add Contact to Lists (optional)',
				'type'     => 'multi-select',
				'required' => false,
				'dynamic'  => [
					'integration' => 'gemcrm',
					'query'       => 'gemcrm_list_query',
					'select'      => [ 'value', 'label' ],
				],
			],
		];
	}

	public static function get_trigger_hooks( string $trigger, array $node = [] ): array {
		if ( in_array( $trigger, [ 'new_order' ], true ) ) {
			return [
				'woocommerce_new_order',
				'woocommerce_checkout_order_created',
			];
		}

		return parent::get_trigger_hooks( $trigger, $node );
	}



	public static function resolve_trigger( array $node, array $args ) {
		$event = $node['event'] ?? '';
		if ( '' === $event ) {
			return false;
		}

		if ( in_array( $event, self::$order_status_events, true ) ) {
			$payload = self::order_status_payload_from_args( $args );
			return $payload ? $payload : false;
		}

		switch ( $event ) {
			case 'new_order':
				$payload = self::order_payload_from_args( $args );
				return $payload ? $payload : false;
			case 'restore_order':
				$payload = self::order_payload_from_args($args, [
					'previous_status' => $args[1] ?? '',
				]);
				return $payload ? $payload : false;
			case 'order_status_changed':
				$payload = self::order_payload_from_args($args, [
					'old_status' => $args[1] ?? '',
					'new_status' => $args[2] ?? '',
				], 0, 3);
				return $payload ? $payload : false;
			case 'new_coupon':
				$payload = self::coupon_payload_from_args( $args );
				return $payload ? $payload : false;
			case 'create_customer':
				$payload = self::customer_payload_from_args($args, [
					'password_generated' => (bool) ( $args[2] ?? false ),
				]);
				return $payload ? $payload : false;
			case 'update_customer':
				$payload = self::customer_payload_from_args( $args );
				return $payload ? $payload : false;
			case 'delete_customer':
				$customer_id = $args[0] ?? 0;
				return $customer_id ? [ 'customer_id' => $customer_id ] : false;
			case 'create_product':
			case 'update_product':
				$payload = self::product_payload_from_args( $args );
				return $payload ? $payload : false;
			case 'delete_product':
				$new_status = $args[0] ?? '';
				$old_status = $args[1] ?? '';
				$post       = $args[2] ?? null;
				if (
					$post instanceof \WP_Post &&
					$post->post_type === 'product' &&
					'trash' === $new_status &&
					'trash' !== $old_status
				) {
					return self::product_payload_from_post( $post );
				}
				return false;
			case 'restore_product':
				$new_status = $args[0] ?? '';
				$old_status = $args[1] ?? '';
				$post       = $args[2] ?? null;
				if (
					$post instanceof \WP_Post &&
					$post->post_type === 'product' &&
					'trash' === $old_status &&
					'trash' !== $new_status
				) {
					return self::product_payload_from_post( $post );
				}
				return false;
			case 'product_status_updated':
				$payload = self::product_payload_from_args($args, [
					'stock_status' => $args[1] ?? '',
				], 0, 2);
				return $payload ? $payload : false;
			case 'product_status_changed':
				$payload = self::product_payload_from_post($args[2] ?? null, [
					'old_status' => $args[1] ?? '',
					'new_status' => $args[0] ?? '',
				]);
				return $payload ? $payload : false;
			case 'product_added_to_cart':
				return self::build_cart_add_payload( $args );
			case 'product_removed_from_cart':
				return self::build_cart_item_payload( $args );
			case 'cart_abandoned':
			case 'cart_recovered':
			case 'cart_lost':
				$payload = self::abandoned_cart_trigger_payload( $args );
				return $payload ? $payload : false;
			case 'inactive_customer':
				$data = $args[0] ?? [];
				return is_array( $data ) && ! empty( $data['email'] ) ? $data : false;
		}//end switch

		return false;
	}

	public static function get_actions(): array {
		return [
			'create_order'                    => [ 'label' => 'Create Order' ],
			'update_order'                    => [ 'label' => 'Update Order' ],
			'update_order_status'             => [ 'label' => 'Update Order Status' ],
			'add_or_update_order_meta'        => [ 'label' => 'Add or Update Order Custom Fields (Meta Data)' ],
			'get_total_orders_count'          => [ 'label' => 'Get Total Orders Count' ],
			'get_refunded_orders'             => [ 'label' => 'Get Refunded Orders' ],
			'get_orders_all'                  => [ 'label' => 'Get All Orders' ],
			'get_orders_by_status'            => [ 'label' => 'Get Orders by Status' ],
			'get_orders_by_billing_email'     => [ 'label' => 'Get Orders by Billing Email' ],
			'get_orders_by_customer_id'       => [ 'label' => 'Get Orders by Customer ID' ],
			'get_order_single'                => [ 'label' => 'Get Single Order' ],
			'add_order_note'                  => [ 'label' => 'Add Order Note' ],
			'get_customers_all'               => [ 'label' => 'Get All Customers' ],
			'get_customer_single'             => [ 'label' => 'Get Single Customer' ],
			'get_customer_by_email'           => [ 'label' => 'Get Customer by Email' ],
			'get_customer_total_spent'        => [ 'label' => 'Get Customer Total Spent' ],
			'get_customer_last_order'         => [ 'label' => 'Get Customer Last Order' ],
			'create_customer'                 => [ 'label' => 'Create Customer' ],
			'create_product'                  => [ 'label' => 'Create Product' ],
			'create_product_variation'        => [ 'label' => 'Create Product Variation' ],
			'update_product'                  => [ 'label' => 'Update Product' ],
			'update_product_stock'            => [ 'label' => 'Update Product Stock' ],
			'update_product_status'           => [ 'label' => 'Update Product Status' ],
			'delete_product_permanently'      => [ 'label' => 'Delete Product Permanently' ],
			'delete_product_soft'             => [ 'label' => 'Soft Delete Product' ],
			'get_products_all'                => [ 'label' => 'Get All Products' ],
			'get_products_by_category'        => [ 'label' => 'Get Products by Category' ],
			'get_products_simple'             => [ 'label' => 'Get Simple Products' ],
			'get_products_variable'           => [ 'label' => 'Get Variable Products' ],
			'get_products_grouped'            => [ 'label' => 'Get Grouped Products' ],
			'get_products_external'           => [ 'label' => 'Get External/Affiliate Products' ],
			'get_products_variation'          => [ 'label' => 'Get Variation Products' ],
			'get_products_subscription'       => [ 'label' => 'Get Subscription Products' ],
			'get_product_by_id'               => [ 'label' => 'Get Product by ID' ],
			'get_product_by_sku'              => [ 'label' => 'Get Product by SKU' ],
			'get_product'                     => [ 'label' => 'Get Product (search by name/SKU)' ],
			'get_products_totals'             => [ 'label' => 'Get Products Totals' ],
			'get_product_sales_count_by_id'   => [ 'label' => 'Get Product Sales Count by ID' ],
			'create_product_category'         => [ 'label' => 'Create Product Category' ],
			'update_product_category'         => [ 'label' => 'Update Product Category' ],
			'delete_product_category'         => [ 'label' => 'Delete Product Category' ],
			'get_product_category_all'        => [ 'label' => 'Get All Product Categories' ],
			'get_product_category_single'     => [ 'label' => 'Get Single Product Category' ],
			'create_product_tag'              => [ 'label' => 'Create Product Tag' ],
			'update_product_tag'              => [ 'label' => 'Update Product Tag' ],
			'delete_product_tag'              => [ 'label' => 'Delete Product Tag' ],
			'get_product_tag_all'             => [ 'label' => 'Get All Product Tags' ],
			'get_product_tag_single'          => [ 'label' => 'Get Single Product Tag' ],
			'create_product_type'             => [ 'label' => 'Create Product Type' ],
			'update_product_type'             => [ 'label' => 'Update Product Type' ],
			'delete_product_type'             => [ 'label' => 'Delete Product Type' ],
			'get_product_type_all'            => [ 'label' => 'Get All Product Types' ],
			'get_product_type_single'         => [ 'label' => 'Get Single Product Type' ],
			'create_product_brand'            => [ 'label' => 'Create Product Brand' ],
			'update_product_brand'            => [ 'label' => 'Update Product Brand' ],
			'delete_product_brand'            => [ 'label' => 'Delete Product Brand' ],
			'get_product_brand_all'           => [ 'label' => 'Get All Product Brands' ],
			'get_product_brand_single'        => [ 'label' => 'Get Single Product Brand' ],
			'create_product_shipping_class'   => [ 'label' => 'Create Shipping Class' ],
			'update_product_shipping_class'   => [ 'label' => 'Update Shipping Class' ],
			'delete_product_shipping_class'   => [ 'label' => 'Delete Shipping Class' ],
			'get_product_shipping_class_all'  => [ 'label' => 'Get All Shipping Classes' ],
			'get_product_shipping_class_single' => [ 'label' => 'Get Single Shipping Class' ],
			'add_or_update_product_attribute' => [ 'label' => 'Add or Update Product Attribute' ],
			'remove_product_attribute'        => [ 'label' => 'Remove Product Attribute' ],
			'create_attribute'                => [ 'label' => 'Create Attribute' ],
			'update_attribute'                => [ 'label' => 'Update Attribute' ],
			'get_attribute'                   => [ 'label' => 'Get Attribute' ],
			'delete_attribute'                => [ 'label' => 'Delete Attribute' ],
			'get_cart_items_all'              => [ 'label' => 'Get All Cart Items' ],
			'get_cart_totals'                 => [ 'label' => 'Get Cart Totals' ],
			'add_product_to_cart'             => [ 'label' => 'Add Product to Cart' ],
			'remove_product_from_cart'        => [ 'label' => 'Remove Product from Cart' ],
			'create_coupon'                   => [ 'label' => 'Create Coupon' ],
			'update_coupon_data'              => [ 'label' => 'Update Coupon Data' ],
			'update_coupon_code'              => [ 'label' => 'Update Coupon Code' ],
			'add_coupon_emails'               => [ 'label' => 'Add Emails to Coupon' ],
			'apply_coupon_to_cart'            => [ 'label' => 'Apply Coupon to Cart' ],
			'get_applied_coupons_from_cart'   => [ 'label' => 'Get Applied Cart Coupons' ],
			'remove_coupon_from_cart'         => [ 'label' => 'Remove Coupon from Cart' ],
			'delete_coupon'                   => [ 'label' => 'Delete Coupon' ],
			'get_coupons_all'                 => [ 'label' => 'Get All Coupons' ],
			'get_coupon_single'               => [ 'label' => 'Get Single Coupon' ],
			'get_coupon_totals_by_discount_type' => [ 'label' => 'Get Coupon Totals by Discount Type' ],
			'get_reviews_all'                 => [ 'label' => 'Get All Reviews' ],
			'top_selling_products_report'     => [ 'label' => 'Top Selling Products Report' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [

			'create_order' => [
				[
					'key' => 'customer_id',
					'label' => 'Customer ID',
					'type' => 'expression',
					'required' => true,
				],
				[
					'key' => 'order_status',
					'label' => 'Order Status',
					'type' => 'select',
					'required' => true,
					'options' => self::order_status_options()
				],
				[
					'key' => 'currency',
					'label' => 'Currency',
					'type' => 'text'
				],
				[
					'key' => 'note',
					'label' => 'Order Note',
					'type' => 'textarea'
				],
				[
					'key' => 'is_customer_note',
					'label' => 'Customer Note',
					'type' => 'boolean'
				],
			],
			'update_order' => [
				...self::field_order_id(),
				[
					'key' => 'order_status',
					'label' => 'Order Status',
					'type' => 'select',
					'required' => true,
					'options' => self::order_status_options()
				],
				[
					'key' => 'customer_id',
					'label' => 'Customer ID',
					'type' => 'expression'
				],
				[
					'key' => 'total',
					'label' => 'Order Total',
					'type' => 'expression'
				],
				[
					'key' => 'currency',
					'label' => 'Currency',
					'type' => 'text'
				],
				[
					'key' => 'billing',
					'label' => 'Billing Data (JSON)',
					'type' => 'textarea'
				],
				[
					'key' => 'shipping',
					'label' => 'Shipping Data (JSON)',
					'type' => 'textarea'
				],
				[
					'key' => 'meta',
					'label' => 'Meta (JSON)',
					'type' => 'textarea'
				],
				[
					'key' => 'data',
					'label' => 'Order Data (JSON)',
					'type' => 'textarea'
				],
			],
			'update_order_status' => [
				...self::field_order_id(),
				[
					'key' => 'order_status',
					'label' => 'Order Status',
					'type' => 'select',
					'options' => self::order_status_options(),
					'required' => true
				],
			],
			'add_or_update_order_meta' => [
				...self::field_order_id(),
				[
					'key' => 'meta_key',
					'label' => 'Meta Key',
					'type' => 'text',
					'required' => true
				],
				[
					'key' => 'meta_value',
					'label' => 'Meta Value',
					'type' => 'expression'
				],
			],
			'get_total_orders_count' => [
				[
					'key' => 'order_status',
					'label' => 'Order Status',
					'type' => 'select',
					'required' => true,
					'options' => self::order_status_options()
				],
			],
			'get_refunded_orders' => [
				...self::field_limit_page(),
			],
			'get_orders_all' => [
				...self::field_limit_page(),
			],
			'get_orders_by_status' => [
				[
					'key' => 'order_status',
					'label' => 'Order Status',
					'type' => 'select',
					'options' => self::order_status_options(),
					'required' => true
				],
				...self::field_limit_page(),
			],
			'get_orders_by_billing_email' => [
				[
					'key' => 'billing_email',
					'label' => 'Billing Email',
					'type' => 'email',
					'required' => true
				],
				...self::field_limit_page(),
			],
			'get_orders_by_customer_id' => [
				...self::field_customer_id(),
				...self::field_limit_page(),
			],
			'get_order_single' => self::field_order_id(),
			'get_customer_total_spent' => [
				...self::field_customer_id( false ),
				[
					'key' => 'email',
					'label' => 'Customer Email',
					'type' => 'text'
				],
			],
			'get_customer_last_order' => [
				...self::field_customer_id( false ),
				[
					'key' => 'email',
					'label' => 'Customer Email',
					'type' => 'email'
				],
			],
			'add_order_note' => [
				...self::field_order_id(),
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'textarea',
					'required' => true
				],
				[
					'key' => 'is_customer_note',
					'label' => 'Customer Note',
					'type' => 'boolean'
				],
			],

			'get_customers_all' => [
				...self::field_limit_page(),
			],
			'get_customer_single' => self::field_customer_id(),
			'get_customer_by_email' => [
				[
					'key' => 'email',
					'label' => 'Customer Email',
					'type' => 'email',
					'required' => true
				],
			],
			'create_customer' => [
				[
					'key' => 'email',
					'label' => 'Email',
					'type' => 'email',
					'required' => true
				],
				[
					'key' => 'username',
					'label' => 'Username',
					'type' => 'text'
				],
				[
					'key' => 'password',
					'label' => 'Password',
					'type' => 'text'
				],
				[
					'key' => 'first_name',
					'label' => 'First Name',
					'type' => 'text'
				],
				[
					'key' => 'last_name',
					'label' => 'Last Name',
					'type' => 'text'
				],
				[
					'key' => 'billing',
					'label' => 'Billing Data (JSON)',
					'type' => 'textarea'
				],
				[
					'key' => 'shipping',
					'label' => 'Shipping Data (JSON)',
					'type' => 'textarea'
				],
			],

			'create_product' => [
				[
					'key' => 'name',
					'label' => 'Product Name',
					'type' => 'text',
					'required' => true
				],
				[
					'key' => 'product_status',
					'label' => 'Status',
					'type' => 'select',
					'required' => true,
					'options' => self::product_status_options()
				],
				[
					'key' => 'sku',
					'label' => 'SKU',
					'type' => 'text'
				],
				[
					'key' => 'regular_price',
					'label' => 'Regular Price',
					'type' => 'expression'
				],
				[
					'key' => 'sale_price',
					'label' => 'Sale Price',
					'type' => 'expression'
				],
				[
					'key' => 'price',
					'label' => 'Price',
					'type' => 'expression'
				],
				[
					'key' => 'description',
					'label' => 'Description',
					'type' => 'textarea'
				],
				[
					'key' => 'short_description',
					'label' => 'Short Description',
					'type' => 'textarea'
				],
				[
					'key' => 'stock_quantity',
					'label' => 'Stock Quantity',
					'type' => 'expression'
				],
				[
					'key' => 'manage_stock',
					'label' => 'Manage Stock',
					'type' => 'boolean'
				],
				[
					'key' => 'stock_status',
					'label' => 'Stock Status',
					'type' => 'select',
					'options' => self::stock_status_options()
				],
				[
					'key' => 'category_ids',
					'label' => 'Category IDs (CSV/JSON)',
					'type' => 'textarea'
				],
				[
					'key' => 'tag_ids',
					'label' => 'Tag IDs (CSV/JSON)',
					'type' => 'textarea'
				],
			],
			'create_product_variation' => [
				[
					'key' => 'parent_id',
					'label' => 'Parent Product ID',
					'type' => 'select',
					'dynamic' => [
						'integration' => 'woocommerce',
						'query' => 'products',
						'select' => [ 'id', 'name' ],
					],
					'required' => true
				],
				[
					'key' => 'attributes',
					'label' => 'Attributes (JSON)',
					'type' => 'textarea',
					'required' => true
				],
				[
					'key' => 'sku',
					'label' => 'SKU',
					'type' => 'text'
				],
				[
					'key' => 'regular_price',
					'label' => 'Regular Price',
					'type' => 'expression'
				],
				[
					'key' => 'sale_price',
					'label' => 'Sale Price',
					'type' => 'expression'
				],
				[
					'key' => 'stock_quantity',
					'label' => 'Stock Quantity',
					'type' => 'expression'
				],
				[
					'key' => 'manage_stock',
					'label' => 'Manage Stock',
					'type' => 'boolean'
				],
				[
					'key' => 'product_status',
					'label' => 'Status',
					'type' => 'select',
					'required' => true,
					'options' => self::product_status_options()
				],
			],
			'update_product' => [
				...self::field_product_id(),
				[
					'key' => 'name',
					'label' => 'Product Name',
					'type' => 'text'
				],
				[
					'key' => 'product_status',
					'label' => 'Status',
					'type' => 'select',
					'required' => true,
					'options' => self::product_status_options()
				],
				[
					'key' => 'sku',
					'label' => 'SKU',
					'type' => 'text'
				],
				[
					'key' => 'regular_price',
					'label' => 'Regular Price',
					'type' => 'expression'
				],
				[
					'key' => 'sale_price',
					'label' => 'Sale Price',
					'type' => 'expression'
				],
				[
					'key' => 'price',
					'label' => 'Price',
					'type' => 'expression'
				],
				[
					'key' => 'description',
					'label' => 'Description',
					'type' => 'textarea'
				],
				[
					'key' => 'short_description',
					'label' => 'Short Description',
					'type' => 'textarea'
				],
				[
					'key' => 'stock_quantity',
					'label' => 'Stock Quantity',
					'type' => 'expression'
				],
				[
					'key' => 'manage_stock',
					'label' => 'Manage Stock',
					'type' => 'boolean'
				],
				[
					'key' => 'stock_status',
					'label' => 'Stock Status',
					'type' => 'select',
					'options' => self::stock_status_options()
				],
				[
					'key' => 'category_ids',
					'label' => 'Category IDs (CSV/JSON)',
					'type' => 'textarea'
				],
				[
					'key' => 'tag_ids',
					'label' => 'Tag IDs (CSV/JSON)',
					'type' => 'textarea'
				],
				[
					'key' => 'data',
					'label' => 'Product Data (JSON)',
					'type' => 'textarea'
				],
			],
			'get_products_all' => [
				...self::field_limit_page(),
			],
			'get_products_by_category' => [
				[
					'key' => 'category_id',
					'label' => 'Category ID',
					'type' => 'select',
					'dynamic' => [
						'integration' => 'woocommerce',
						'query' => 'terms',
						'select' => [ 'id', 'name' ],
						'where' => [
							'taxonomy' => 'product_cat',
						],
					],
				],
				[
					'key' => 'category_slug',
					'label' => 'Category Slug',
					'type' => 'text'
				],
				...self::field_limit_page(),
			],
			'get_products_simple' => [
				...self::field_limit_page(),
			],
			'get_products_variable' => [
				...self::field_limit_page(),
			],
			'get_products_grouped' => [
				...self::field_limit_page(),
			],
			'get_products_external' => [
				...self::field_limit_page(),
			],
			'get_products_variation' => [
				...self::field_limit_page(),
			],
			'get_products_subscription' => [
				...self::field_limit_page(),
			],
			'get_product_by_id' => self::field_product_id(),
			'get_product_by_sku' => [
				[
					'key' => 'sku',
					'label' => 'SKU',
					'type' => 'text',
					'required' => true
				],
			],
			'get_product' => [
				[
					'key'         => 'query',
					'label'       => 'Product Name or Keyword',
					'type'        => 'expression',
					'required'    => true,
					'placeholder' => '{{trigger.text}}',
					'help'        => 'Searched against the product title/SKU. Returns the closest live match(es) with current price and stock — wire this onto an AI Agent\'s Tools handle for exact, up-to-date pricing rather than a cached knowledge snippet.',
				],
				[
					'key'      => 'limit',
					'label'    => 'Max Matches',
					'type'     => 'number',
					'required' => false,
					'default'  => 1,
				],
			],
			'update_product_stock' => [
				...self::field_product_id(),
				[
					'key' => 'stock_quantity',
					'label' => 'Stock Quantity',
					'type' => 'expression'
				],
				[
					'key' => 'manage_stock',
					'label' => 'Manage Stock',
					'type' => 'boolean'
				],
				[
					'key' => 'stock_status',
					'label' => 'Stock Status',
					'type' => 'select',
					'options' => self::stock_status_options()
				],
			],
			'delete_product_permanently' => self::field_product_id(),
			'delete_product_soft' => self::field_product_id(),
			'get_products_totals' => [
				[
					'key' => 'include_variations',
					'label' => 'Include Variations',
					'type' => 'boolean',
				],
			],
			'get_product_sales_count_by_id' => self::field_product_id(),
			'update_product_status' => [
				...self::field_product_id(),
				[
					'key' => 'product_status',
					'label' => 'Status',
					'type' => 'select',
					'options' => self::product_status_options(),
					'required' => true
				],
			],

			'create_product_category' => self::field_term_create( true, 'product_cat' ),
			'update_product_category' => self::field_term_update( true, 'product_cat' ),
			'delete_product_category' => self::field_term_delete( 'product_cat' ),
			'get_product_category_all' => [
				...self::field_term_list_filters(),
			],
			'get_product_category_single' => self::field_term_id( 'product_cat' ),

			'create_product_tag' => self::field_term_create( false, 'product_tag' ),
			'update_product_tag' => self::field_term_update( false, 'product_tag' ),
			'delete_product_tag' => self::field_term_delete( 'product_tag' ),
			'get_product_tag_all' => [
				...self::field_term_list_filters(),
			],
			'get_product_tag_single' => self::field_term_id( 'product_tag' ),

			'create_product_type' => self::field_term_create( false, 'product_type' ),
			'update_product_type' => self::field_term_update( false, 'product_type' ),
			'delete_product_type' => self::field_term_delete( 'product_type' ),
			'get_product_type_all' => [
				...self::field_term_list_filters(),
			],
			'get_product_type_single' => self::field_term_id( 'product_type' ),

			'create_product_brand' => self::field_term_create( false, 'product_brand' ),
			'update_product_brand' => self::field_term_update( false, 'product_brand' ),
			'delete_product_brand' => self::field_term_delete( 'product_brand' ),
			'get_product_brand_all' => [
				...self::field_term_list_filters(),
			],
			'get_product_brand_single' => self::field_term_id( 'product_brand' ),

			'create_product_shipping_class' => self::field_term_create( false, 'product_shipping_class' ),
			'update_product_shipping_class' => self::field_term_update( false, 'product_shipping_class' ),
			'delete_product_shipping_class' => self::field_term_delete( 'product_shipping_class' ),
			'get_product_shipping_class_all' => [
				...self::field_term_list_filters(),
			],
			'get_product_shipping_class_single' => self::field_term_id( 'product_shipping_class' ),

			'add_or_update_product_attribute' => [
				...self::field_product_id(),
				[
					'key' => 'attribute_name',
					'label' => 'Attribute Name',
					'type' => 'text',
					'required' => true
				],
				[
					'key' => 'options',
					'label' => 'Options (CSV/JSON)',
					'type' => 'textarea'
				],
				[
					'key' => 'visible',
					'label' => 'Visible on Product Page',
					'type' => 'boolean'
				],
				[
					'key' => 'variation',
					'label' => 'Used for Variations',
					'type' => 'boolean'
				],
				[
					'key' => 'is_taxonomy',
					'label' => 'Taxonomy Attribute',
					'type' => 'boolean'
				],
				[
					'key' => 'taxonomy',
					'label' => 'Taxonomy (e.g. pa_color)',
					'type' => 'text'
				],
				[
					'key' => 'position',
					'label' => 'Position',
					'type' => 'number'
				],
			],
			'remove_product_attribute' => [
				...self::field_product_id(),
				[
					'key' => 'attribute_name',
					'label' => 'Attribute Name',
					'type' => 'text',
					'required' => true
				],
			],
			'create_attribute' => self::field_attribute_create(),
			'update_attribute' => self::field_attribute_update(),
			'get_attribute' => self::field_attribute_id(),
			'delete_attribute' => self::field_attribute_id(),

			'get_cart_items_all' => [
				[
					'key' => 'product_id',
					'label' => 'Filter by Product ID',
					'type' => 'select',
					'dynamic' => [
						'integration' => 'woocommerce',
						'query' => 'products',
						'select' => [ 'id', 'label' ],
					],
				],
			],
			'get_cart_totals' => [
				[
					'key' => 'with_items_count',
					'label' => 'Include Items Count',
					'type' => 'boolean',
				],
			],
			'add_product_to_cart' => [
				...self::field_product_id(),
				[
					'key' => 'quantity',
					'label' => 'Quantity',
					'type' => 'number'
				],
				[
					'key' => 'variation_id',
					'label' => 'Variation ID',
					'type' => 'select',
					'dynamic' => [
						'integration' => 'woocommerce',
						'query' => 'variations',
						'select' => [ 'id', 'label' ],
					],
				],
				[
					'key' => 'variations',
					'label' => 'Variations (JSON)',
					'type' => 'textarea'
				],
				[
					'key' => 'cart_item_data',
					'label' => 'Cart Item Data (JSON)',
					'type' => 'textarea'
				],
			],
			'remove_product_from_cart' => [
				[
					'key' => 'cart_item_key',
					'label' => 'Cart Item Key',
					'type' => 'text',
					'required' => true
				],
			],

			'create_coupon' => [
				[
					'key' => 'code',
					'label' => 'Coupon Code',
					'type' => 'text',
					'required' => true
				],
				[
					'key' => 'discount_type',
					'label' => 'Discount Type',
					'type' => 'select',
					'options' => self::coupon_type_options()
				],
				[
					'key' => 'amount',
					'label' => 'Amount',
					'type' => 'expression'
				],
				[
					'key' => 'usage_limit',
					'label' => 'Usage Limit',
					'type' => 'expression'
				],
				[
					'key' => 'expiry_date',
					'label' => 'Expiry Date',
					'type' => 'date'
				],
				[
					'key' => 'email_restrictions',
					'label' => 'Email Restrictions (CSV/JSON)',
					'type' => 'textarea'
				],
				[
					'key' => 'product_ids',
					'label' => 'Product IDs (CSV/JSON)',
					'type' => 'textarea'
				],
				[
					'key' => 'exclude_product_ids',
					'label' => 'Exclude Product IDs (CSV/JSON)',
					'type' => 'textarea'
				],
			],
			'update_coupon_data' => [
				...self::field_coupon_identifier(),
				[
					'key' => 'discount_type',
					'label' => 'Discount Type',
					'type' => 'select',
					'options' => self::coupon_type_options()
				],
				[
					'key' => 'amount',
					'label' => 'Amount',
					'type' => 'expression'
				],
				[
					'key' => 'usage_limit',
					'label' => 'Usage Limit',
					'type' => 'expression'
				],
				[
					'key' => 'expiry_date',
					'label' => 'Expiry Date',
					'type' => 'date'
				],
				[
					'key' => 'email_restrictions',
					'label' => 'Email Restrictions (CSV/JSON)',
					'type' => 'textarea'
				],
				[
					'key' => 'product_ids',
					'label' => 'Product IDs (CSV/JSON)',
					'type' => 'textarea'
				],
				[
					'key' => 'exclude_product_ids',
					'label' => 'Exclude Product IDs (CSV/JSON)',
					'type' => 'textarea'
				],
				[
					'key' => 'data',
					'label' => 'Coupon Data (JSON)',
					'type' => 'textarea'
				],
			],
			'update_coupon_code' => [
				...self::field_coupon_identifier(),
				[
					'key' => 'new_code',
					'label' => 'New Coupon Code',
					'type' => 'text',
					'required' => true
				],
			],
			'add_coupon_emails' => [
				...self::field_coupon_identifier(),
				[
					'key' => 'emails',
					'label' => 'Emails (CSV/JSON)',
					'type' => 'textarea',
					'required' => true
				],
			],
			'apply_coupon_to_cart' => [
				[
					'key' => 'code',
					'label' => 'Coupon Code',
					'type' => 'text',
					'required' => true
				],
			],
			'get_applied_coupons_from_cart' => [
				[
					'key' => 'include_totals',
					'label' => 'Include Coupon Totals',
					'type' => 'boolean',
				],
			],
			'remove_coupon_from_cart' => [
				[
					'key' => 'code',
					'label' => 'Coupon Code',
					'type' => 'text',
					'required' => true
				],
			],
			'delete_coupon' => self::field_coupon_identifier(),
			'get_coupons_all' => [
				...self::field_limit_page(),
			],
			'get_coupon_single' => self::field_coupon_identifier(),
			'get_coupon_totals_by_discount_type' => [
				[
					'key' => 'include_empty_types',
					'label' => 'Include Empty Types',
					'type' => 'boolean',
				],
			],

			'get_reviews_all' => [
				...self::field_limit_page(),
			],
			'top_selling_products_report' => [
				[
					'key' => 'limit',
					'label' => 'Limit',
					'type' => 'number',
					'required' => true
				],
			],
			'get_abandoned_cart' => [
				[
					'key'      => 'cart_id',
					'label'    => 'Cart ID',
					'type'     => 'text',
					'required' => true,
				],
			],
			'get_abandoned_cart_by_email' => [
				[
					'key'      => 'email',
					'label'    => 'Email Address',
					'type'     => 'email',
					'required' => true,
				],
			],
			'get_abandoned_carts' => [
				[
					'key'     => 'status',
					'label'   => 'Status Filter',
					'type'    => 'select',
					'options' => [
						[
							'label' => 'All',
							'value' => ''
						],
						[
							'label' => 'Draft',
							'value' => 'draft'
						],
						[
							'label' => 'Processing (Abandoned)',
							'value' => 'processing'
						],
						[
							'label' => 'Recovered',
							'value' => 'recovered'
						],
						[
							'label' => 'Lost',
							'value' => 'lost'
						],
						[
							'label' => 'Opt Out',
							'value' => 'opt_out'
						],
					],
				],
				[
					'key'     => 'limit',
					'label'   => 'Limit',
					'type'    => 'number',
					'default' => 20,
				],
			],
			'update_abandoned_cart_status' => [
				[
					'key'      => 'cart_id',
					'label'    => 'Cart ID',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'      => 'status',
					'label'    => 'New Status',
					'type'     => 'select',
					'required' => true,
					'options'  => [
						[
							'label' => 'Draft',
							'value' => 'draft'
						],
						[
							'label' => 'Processing',
							'value' => 'processing'
						],
						[
							'label' => 'Recovered',
							'value' => 'recovered'
						],
						[
							'label' => 'Lost',
							'value' => 'lost'
						],
						[
							'label' => 'Opt Out',
							'value' => 'opt_out'
						],
						[
							'label' => 'Skipped',
							'value' => 'skipped'
						],
					],
				],
			],
			'get_abandoned_cart_report' => [
				[
					'key'   => 'date_from',
					'label' => 'Date From (YYYY-MM-DD)',
					'type'  => 'text',
				],
				[
					'key'   => 'date_to',
					'label' => 'Date To (YYYY-MM-DD)',
					'type'  => 'text',
				],
			],
		];

		return $schemas[ $action ] ?? [];
	}

	public static function get_dynamic_queries(): array {
		return [
			'orders' => [ self::class, 'query_dynamic_orders' ],
			'customers' => [ self::class, 'query_dynamic_customers' ],
			'products' => [ self::class, 'query_dynamic_products' ],
			'variations' => [ self::class, 'query_dynamic_variations' ],
			'coupons' => [ self::class, 'query_dynamic_coupons' ],
			'terms' => [ self::class, 'query_dynamic_terms' ],
			'attributes' => [ self::class, 'query_dynamic_attributes' ],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$action = self::get_node_action( $node );
		$config = self::get_node_config( $node );

		$method = 'action_' . $action;
		if ( method_exists( static::class, $method ) ) {
			return static::$method( $config, $input );
		}

		return [
			'port' => 'main',
			'data' => $input
		];
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		if ( in_array( $trigger, [ 'cart_abandoned', 'cart_recovered', 'cart_lost' ], true ) ) {
			return \Zaplane\Integrations\AbandonedCart::get_trigger_sample_output( $trigger );
		}

		if ( 'inactive_customer' === $trigger ) {
			return [
				'user_id'         => 1,
				'email'           => 'john.doe@example.com',
				'first_name'      => 'John',
				'last_name'       => 'Doe',
				'phone'           => '+1234567890',
				'last_order_date' => '2024-01-01',
				'contact_id'      => 1,
				'days'            => 30,
				'tag_ids'         => [],
				'list_ids'        => [],
			];
		}

		// Shared base samples that mirror the build_*_payload() shapes in Woo\Helper.
		$order = [
			'order_id'          => 123,
			'order_number'      => '123',
			'order_key'         => 'wc_order_abc123',
			'status'            => 'completed',
			'total'             => 49.99,
			'currency'          => 'USD',
			'customer_id'       => 1,
			'email'             => 'customer@example.com',
			'first_name'        => 'Jane',
			'last_name'         => 'Smith',
			'feedback_page_url' => home_url( '/feedback/?order_id=123&key=wc_order_abc123' ),
		];

		$product = [
			'product_id' => 55,
			'name'       => 'Sample Product',
			'status'     => 'publish',
			'sku'        => 'SKU-055',
			'price'      => '19.99',
			'type'       => 'simple',
		];

		$coupon = [
			'coupon_id'     => 77,
			'code'          => 'save10',
			'amount'        => '10',
			'discount_type' => 'percent',
		];

		$customer = [
			'customer_id' => 1,
			'email'       => 'customer@example.com',
			'username'    => 'janesmith',
		];

		$cart_item = [
			'cart_item_key' => 'a1b2c3d4e5',
			'product_id'    => 55,
			'quantity'      => 2,
			'variation_id'  => 0,
		];

		if ( in_array( $trigger, self::$order_status_events, true ) ) {
			return array_merge( $order, [
				'old_status' => 'processing',
				'new_status' => 'completed',
			] );
		}

		// Explicit samples that match each resolve_trigger() branch.
		$explicit = [
			'new_order'                 => $order,
			'restore_order'             => array_merge( $order, [ 'previous_status' => 'trash' ] ),
			'order_status_changed'      => array_merge( $order, [
				'old_status' => 'processing',
				'new_status' => 'completed'
			] ),
			'new_coupon'                => $coupon,
			'create_customer'           => array_merge( $customer, [ 'password_generated' => true ] ),
			'update_customer'           => $customer,
			'delete_customer'           => [ 'customer_id' => 1 ],
			'create_product'            => $product,
			'update_product'            => $product,
			'delete_product'            => $product,
			'restore_product'           => $product,
			'product_status_updated'    => array_merge( $product, [ 'stock_status' => 'instock' ] ),
			'product_status_changed'    => array_merge( $product, [
				'old_status' => 'draft',
				'new_status' => 'publish'
			] ),
			'product_added_to_cart'     => $cart_item,
			'product_removed_from_cart' => $cart_item,
		];

		if ( isset( $explicit[ $trigger ] ) ) {
			return $explicit[ $trigger ];
		}

		// Category fallbacks by event-name prefix so any future trigger stays non-empty.
		if ( 0 === strpos( $trigger, 'order_' ) || 'new_order' === $trigger || 'restore_order' === $trigger ) {
			return $order;
		}
		if ( 0 === strpos( $trigger, 'product_' ) ) {
			return $product;
		}
		if ( 0 === strpos( $trigger, 'customer_' ) || false !== strpos( $trigger, '_customer' ) ) {
			return $customer;
		}
		if ( 0 === strpos( $trigger, 'coupon_' ) || false !== strpos( $trigger, 'coupon' ) ) {
			return $coupon;
		}
		if ( 0 === strpos( $trigger, 'cart_' ) ) {
			return $cart_item;
		}
		if ( 0 === strpos( $trigger, 'subscription_' ) ) {
			return [
				'subscription_id' => 900,
				'status'          => 'active',
				'total'           => 49.99,
			];
		}
		if ( 0 === strpos( $trigger, 'review_' ) ) {
			return [
				'review_id'    => 300,
				'product_id'   => 55,
				'reviewer'     => 'Jane Smith',
				'rating'       => 5,
				'review'       => 'Great product!',
				'approved'     => true,
			];
		}

		return $order;
	}
}
