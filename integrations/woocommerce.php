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

	public static function get_slug(): string {
		return 'woocommerce';
	}

	public static function get_icon(): string {
		return 'woo.svg';
	}



	public static function get_triggers(): array {
		return [
			'new_order' => [
				'label' => 'New Order',
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
				'hook' => 'woocommerce_new_coupon'
			],
			'create_customer' => [
				'label' => 'Create Customer',
				'hook' => 'woocommerce_created_customer'
			],
			'update_customer' => [
				'label' => 'Update Customer',
				'hook' => 'woocommerce_update_customer'
			],
			'delete_customer' => [
				'label' => 'Delete Customer',
				'hook' => 'woocommerce_delete_customer'
			],
			'create_product' => [
				'label' => 'Create Product',
				'hook' => 'woocommerce_new_product'
			],
			'update_product' => [
				'label' => 'Update Product',
				'hook' => 'woocommerce_update_product'
			],
			'delete_product' => [
				'label' => 'Delete Product',
				'hook' => 'before_delete_post'
			],
			'restore_product' => [
				'label' => 'Restore Product',
				'hook' => 'untrashed_post'
			],
			'product_status_updated' => [
				'label' => 'Product Status Updated',
				'hook' => 'woocommerce_product_set_stock_status'
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
		];
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
			case 'restore_product':
				$payload = self::product_payload_from_post( $args[0] ?? 0 );
				return $payload ? $payload : false;
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
		}//end switch

		return false;
	}


	public static function get_actions(): array {
		return [
			'create_order' => [ 'label' => 'Create Order' ],
			'update_order' => [ 'label' => 'Update Order' ],
			'update_order_status' => [ 'label' => 'Update Order Status' ],
			'add_or_update_order_meta' => [ 'label' => 'Add or Update Order Custom Fields (Meta Data)' ],
			'get_total_orders_count' => [ 'label' => 'Get Total Orders Count' ],
			'get_refunded_orders' => [ 'label' => 'Get a List of Refunded Orders' ],
			'get_orders_all' => [ 'label' => 'Get Order (All)' ],
			'get_orders_by_status' => [ 'label' => 'Get Order (By Status)' ],
			'get_orders_by_billing_email' => [ 'label' => 'Get Order (By Billing Email Address)' ],
			'get_orders_by_customer_id' => [ 'label' => 'Get Order (By Customer Id)' ],
			'get_order_single' => [ 'label' => 'Get Order (Single)' ],
			'get_customer_total_spent' => [ 'label' => 'Get Customer Total Spent' ],
			'get_customer_last_order' => [ 'label' => 'Get Customer Last Order' ],
			'add_order_note' => [ 'label' => 'Add Order Note' ],
			'get_customers_all' => [ 'label' => 'Get Customer (All)' ],
			'get_customer_single' => [ 'label' => 'Get Customer (Single)' ],
			'get_customer_by_email' => [ 'label' => 'Get Customer (By Email Address)' ],
			'create_customer' => [ 'label' => 'Create New Customer' ],
			'create_product' => [ 'label' => 'Create Product' ],
			'create_product_variation' => [ 'label' => 'Create Product Variation' ],
			'update_product' => [ 'label' => 'Update Product' ],
			'get_products_all' => [ 'label' => 'Get All Products' ],
			'get_products_by_category' => [ 'label' => 'Get All Products (By Category)' ],
			'get_products_simple' => [ 'label' => 'Get All Simple Products' ],
			'get_products_variable' => [ 'label' => 'Get All Variable Products' ],
			'get_products_grouped' => [ 'label' => 'Get All Grouped Products' ],
			'get_products_external' => [ 'label' => 'Get All External or Affiliate Products' ],
			'get_products_variation' => [ 'label' => 'Get All Variation Products' ],
			'get_products_subscription' => [ 'label' => 'Get All Subscription Products' ],
			'get_product_by_id' => [ 'label' => 'Get Product by ID' ],
			'get_product_by_sku' => [ 'label' => 'Get Product by SKU' ],
			'update_product_stock' => [ 'label' => 'Update Product Stock' ],
			'delete_product_permanently' => [ 'label' => 'Delete Product (Permanently)' ],
			'delete_product_soft' => [ 'label' => 'Delete Product (Soft Delete)' ],
			'get_products_totals' => [ 'label' => 'Get Products Totals' ],
			'get_product_sales_count_by_id' => [ 'label' => 'Get Product Sales Count by ID' ],
			'update_product_status' => [ 'label' => 'Update Product Status' ],
			'create_product_category' => [ 'label' => 'Create Product Category' ],
			'update_product_category' => [ 'label' => 'Update Product Category' ],
			'delete_product_category' => [ 'label' => 'Delete Product Category' ],
			'get_product_category_all' => [ 'label' => 'Get Product Category (All)' ],
			'get_product_category_single' => [ 'label' => 'Get Product Category (Single)' ],
			'create_product_tag' => [ 'label' => 'Create Product Tag' ],
			'update_product_tag' => [ 'label' => 'Update Product Tag' ],
			'delete_product_tag' => [ 'label' => 'Delete Product Tag' ],
			'get_product_tag_all' => [ 'label' => 'Get Product Tag (All)' ],
			'get_product_tag_single' => [ 'label' => 'Get Product Tag (Single)' ],
			'create_product_type' => [ 'label' => 'Create Product Type' ],
			'update_product_type' => [ 'label' => 'Update Product Type' ],
			'delete_product_type' => [ 'label' => 'Delete Product Type' ],
			'get_product_type_all' => [ 'label' => 'Get Product Type (All)' ],
			'get_product_type_single' => [ 'label' => 'Get Product Type (Single)' ],
			'create_product_brand' => [ 'label' => 'Create Product Brand' ],
			'update_product_brand' => [ 'label' => 'Update Product Brand' ],
			'delete_product_brand' => [ 'label' => 'Delete Product Brand' ],
			'get_product_brand_all' => [ 'label' => 'Get Product Brand (All)' ],
			'get_product_brand_single' => [ 'label' => 'Get Product Brand (Single)' ],
			'create_product_shipping_class' => [ 'label' => 'Create Product Shipping Class' ],
			'update_product_shipping_class' => [ 'label' => 'Update Product Shipping Class' ],
			'delete_product_shipping_class' => [ 'label' => 'Delete Product Shipping Class' ],
			'get_product_shipping_class_all' => [ 'label' => 'Get Product Shipping Class (All)' ],
			'get_product_shipping_class_single' => [ 'label' => 'Get Product Shipping Class (Single)' ],
			'add_or_update_product_attribute' => [ 'label' => 'Add or Update Product Attribute' ],
			'remove_product_attribute' => [ 'label' => 'Remove Product Attribute' ],
			'create_attribute' => [ 'label' => 'Create Attribute' ],
			'update_attribute' => [ 'label' => 'Update Attribute' ],
			'get_attribute' => [ 'label' => 'Get Attribute' ],
			'delete_attribute' => [ 'label' => 'Delete Attribute' ],
			'get_cart_items_all' => [ 'label' => 'Get All Cart Items' ],
			'get_cart_totals' => [ 'label' => 'Get Cart Totals' ],
			'add_product_to_cart' => [ 'label' => 'Add Product to Cart' ],
			'remove_product_from_cart' => [ 'label' => 'Remove Product from Cart' ],
			'create_coupon' => [ 'label' => 'Create Coupon' ],
			'update_coupon_data' => [ 'label' => 'Update Coupon Data' ],
			'update_coupon_code' => [ 'label' => 'Update Coupon Code' ],
			'add_coupon_emails' => [ 'label' => 'Add Emails to Coupon' ],
			'apply_coupon_to_cart' => [ 'label' => 'Apply Coupon to Cart' ],
			'get_applied_coupons_from_cart' => [ 'label' => 'Get Applied Coupons from Cart' ],
			'remove_coupon_from_cart' => [ 'label' => 'Remove Coupon from Cart' ],
			'delete_coupon' => [ 'label' => 'Delete Coupon' ],
			'get_coupons_all' => [ 'label' => 'Get Coupon (All)' ],
			'get_coupon_single' => [ 'label' => 'Get Coupon (Single)' ],
			'get_coupon_totals_by_discount_type' => [ 'label' => 'Get Coupon Totals By Discount Type' ],
			'get_reviews_all' => [ 'label' => 'Get All Reviews' ],
			'top_selling_products_report' => [ 'label' => 'Top Selling Products Report' ],
			'get_abandoned_cart' => [ 'label' => 'Get Abandoned Cart by ID' ],
			'get_abandoned_cart_by_email' => [ 'label' => 'Get Abandoned Cart by Email' ],
			'get_abandoned_carts' => [ 'label' => 'Get Abandoned Carts List' ],
			'update_abandoned_cart_status' => [ 'label' => 'Update Abandoned Cart Status' ],
			'get_abandoned_cart_report' => [ 'label' => 'Get Abandoned Cart Report' ],
		];
	}



	public static function get_action_config_schema( string $action ): array {
		$schemas = [

			'create_order' => [
				[
					'key' => 'customer_id',
					'label' => 'Customer ID',
					'type' => 'expression'
				],
				[
					'key' => 'order_status',
					'label' => 'Order Status',
					'type' => 'select',
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
					'type' => 'text',
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
					'type' => 'text'
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
					'type' => 'text',
					'required' => true
				],
			],
			'create_customer' => [
				[
					'key' => 'email',
					'label' => 'Email',
					'type' => 'text',
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
					'default' => 10
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
					'type'     => 'text',
					'required' => true,
				],
			],
			'get_abandoned_carts' => [
				[
					'key'     => 'status',
					'label'   => 'Status Filter',
					'type'    => 'select',
					'options' => [
						[ 'label' => 'All', 'value' => '' ],
						[ 'label' => 'Draft', 'value' => 'draft' ],
						[ 'label' => 'Processing (Abandoned)', 'value' => 'processing' ],
						[ 'label' => 'Recovered', 'value' => 'recovered' ],
						[ 'label' => 'Lost', 'value' => 'lost' ],
						[ 'label' => 'Opt Out', 'value' => 'opt_out' ],
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
						[ 'label' => 'Draft', 'value' => 'draft' ],
						[ 'label' => 'Processing', 'value' => 'processing' ],
						[ 'label' => 'Recovered', 'value' => 'recovered' ],
						[ 'label' => 'Lost', 'value' => 'lost' ],
						[ 'label' => 'Opt Out', 'value' => 'opt_out' ],
						[ 'label' => 'Skipped', 'value' => 'skipped' ],
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

	/**
	 * =====================================================
	 * DYNAMIC DATA QUERIES (API)
	 * =====================================================
	 */
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
}
