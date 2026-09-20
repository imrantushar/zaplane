<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Fluentcart\CreateProductHelperTrait;
use Zaplane\Integrations\Fluentcart\DynamicQueriesTrait;
use Zaplane\Integrations\Fluentcart\OrderActionsTrait;
use Zaplane\Integrations\Fluentcart\CustomerActionsTrait;
use Zaplane\Integrations\Fluentcart\SubscriptionActionsTrait;
use Zaplane\Integrations\Fluentcart\ProductActionsTrait;
use Zaplane\Integrations\Fluentcart\HookActionsTrait;
use Zaplane\Integrations\Fluentcart\TriggerResolverTrait;
use Zaplane\Integrations\Fluentcart\ModelHelperTrait;
use Zaplane\Integrations\Fluentcart\NodeHelperTrait;
use Zaplane\Integrations\Fluentcart\CouponActionsTrait;
use Zaplane\Integrations\Fluentcart\LicenseActionsTrait;
use Zaplane\Integrations\Fluentcart\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FluentCart extends IntegrationBase {
	use CreateProductHelperTrait;
	use DynamicQueriesTrait;
	use OrderActionsTrait;
	use CustomerActionsTrait;
	use SubscriptionActionsTrait;
	use ProductActionsTrait;
	use CouponActionsTrait;
	use LicenseActionsTrait;
	use HookActionsTrait;
	use TriggerResolverTrait;
	use ModelHelperTrait;
	use NodeHelperTrait;
	use Helper;

	private const INTRODUCTION = 'Track FluentCart order, payment, subscription, and stock events and run hook-based actions without webhooks.';

	private const ORDER_EVENTS = [
		'order_deleted',
		'renewal_order_deleted',
		'order_refunded',
		'order_status_changed',
		'payment_status_changed',
		'shipping_status_changed',
	];

	private const PRODUCT_EVENTS = [
		'product_updated',
		'product_duplicated',
		'product_stock_changed',
	];

	public static function get_slug(): string {
		return 'fluentcart';
	}

	public static function get_name(): string {
		return 'FluentCart';
	}

	public static function get_icon(): string {
		return 'fluent-cart.svg';
	}

	public static function get_introduction(): string {
		return self::INTRODUCTION;
	}

	public static function get_output_ports(): array {
		return [ 'main', 'error' ];
	}

	public static function get_triggers(): array {
		return [
			'product_purchased' => [
				'label' => 'Product Purchased',
				'hook'  => 'fluent_cart/checkout/prepare_other_data',
			],
			'product_updated' => [
				'label' => 'Product Updated',
				'hook'  => 'fluent_cart/product_updated',
			],
			'product_stock_updated' => [
				'label' => 'Product Stock Updated',
				'hook'  => 'fluent_cart/product_stock_changed',
			],
			'product_created' => [
				'label' => 'Product Created',
				'hook'  => 'save_post_fluent-products',
			],
			'product_duplicated' => [
				'label' => 'Product Duplicated',
				'hook'  => 'fluent_cart/product_duplicated',
			],
			'product_stock_changed' => [
				'label' => 'Product Stock Changed',
				'hook'  => 'fluent_cart/product_stock_changed',
			],
			'coupon_created' => [
				'label' => 'Coupon Created',
				'hook'  => 'fluent_cart/coupon_created',
			],
			'coupon_updated' => [
				'label' => 'Coupon Updated',
				'hook'  => 'fluent_cart/coupon_updated',
			],
			'cart_item_added' => [
				'label' => 'Cart Item Added',
				'hook'  => 'fluent_cart/cart/item_added',
			],
			'cart_item_removed' => [
				'label' => 'Cart Item Removed',
				'hook'  => 'fluent_cart/cart/item_removed',
			],
			'cart_items_updated' => [
				'label' => 'Cart Items Updated',
				'hook'  => 'fluent_cart/cart/cart_data_items_updated',
			],
			'cart_amount_updated' => [
				'label' => 'Cart Amount Updated',
				'hook'  => 'fluent_cart/checkout/cart_amount_updated',
			],
			'cart_completed' => [
				'label' => 'Cart Completed',
				'hook'  => 'fluent_cart/cart_completed',
			],
			'order_receipt_rendered' => [
				'label' => 'Order Receipt Rendered',
				'hook'  => 'fluent_cart/after_receipt',
			],
			'order_created' => [
				'label' => 'Order Created',
				'hook'  => 'fluent_cart/order_created',
			],
			'order_updated' => [
				'label' => 'Order Updated',
				'hook'  => 'fluent_cart/order_updated',
			],
			'order_deleted' => [
				'label' => 'Order Deleted',
				'hook'  => 'fluent_cart/order_deleted',
			],
			'order_refunded' => [
				'label' => 'Order Refunded (Full or Partial)',
				'hook'  => 'fluent_cart/order_refunded',
			],
			'order_partially_refunded' => [
				'label' => 'Order Refunded (Partial)',
				'hook'  => 'fluent_cart/order_partially_refunded',
			],
			'order_fully_refunded' => [
				'label' => 'Order Refunded (Full)',
				'hook'  => 'fluent_cart/order_fully_refunded',
			],
			'order_canceled' => [
				'label' => 'Order Cancelled',
				'hook'  => 'fluent_cart/order_status_changed_to_canceled',
			],
			'order_status_changed' => [
				'label' => 'Order Status Changed',
				'hook'  => 'fluent_cart/order_status_changed',
			],
			'order_status_processing' => [
				'label' => 'Order Status Updated To Processing',
				'hook'  => 'fluent_cart/order_status_changed_to_processing',
			],
			'order_status_completed' => [
				'label' => 'Order Status Updated To Completed',
				'hook'  => 'fluent_cart/order_status_changed_to_completed',
			],
			'order_status_on_hold' => [
				'label' => 'Order Status Updated To On-Hold',
				'hook'  => 'fluent_cart/order_status_changed_to_on-hold',
			],
			'order_shipping_status_changed' => [
				'label' => 'Order Shipping Status Changed',
				'hook'  => 'fluent_cart/shipping_status_changed',
			],
			'order_shipped' => [
				'label' => 'Order Shipped',
				'hook'  => 'fluent_cart/shipping_status_changed_to_shipped',
			],
			'order_unshipped' => [
				'label' => 'Order Unshipped',
				'hook'  => 'fluent_cart/shipping_status_changed_to_unshipped',
			],
			'order_unshippable' => [
				'label' => 'Order Unshippable',
				'hook'  => 'fluent_cart/shipping_status_changed_to_unshippable',
			],
			'order_delivered' => [
				'label' => 'Order Delivered',
				'hook'  => 'fluent_cart/shipping_status_changed_to_delivered',
			],
			'order_marked_as_paid' => [
				'label' => 'Order Marked As Paid',
				'hook'  => 'fluent_cart/order_paid_done',
			],
			'order_customer_changed' => [
				'label' => 'Order Customer Changed',
				'hook'  => 'fluent_cart/order_customer_changed',
			],
			'customer_created' => [
				'label' => 'Customer Created',
				'hook'  => 'fluent_cart/user/after_register',
			],
			'order_paid' => [
				'label' => 'Order Paid',
				'hook'  => 'fluent_cart/order_paid',
			],
			'order_payment_failed' => [
				'label' => 'Order Payment Failed',
				'hook'  => 'fluent_cart/order_payment_failed',
			],
			'renewal_order_deleted' => [
				'label' => 'Renewal Order Deleted',
				'hook'  => 'fluent_cart/renewal_order_deleted',
			],
			'payment_status_changed' => [
				'label' => 'Payment Status Changed',
				'hook'  => 'fluent_cart/payment_status_changed',
			],
			'shipping_status_changed' => [
				'label' => 'Shipping Status Changed',
				'hook'  => 'fluent_cart/shipping_status_changed',
			],
			'subscription_activated' => [
				'label' => 'Subscription Activated',
				'hook'  => 'fluent_cart/subscription_activated',
			],
			'subscription_canceled' => [
				'label' => 'Subscription Canceled',
				'hook'  => 'fluent_cart/subscription_canceled',
			],
			'subscription_renewed' => [
				'label' => 'Subscription Renewed',
				'hook'  => 'fluent_cart/subscription_renewed',
			],
			'subscription_eot' => [
				'label' => 'Subscription End Of Term',
				'hook'  => 'fluent_cart/subscription_eot',
			],
			'subscription_expired_validity' => [
				'label' => 'Subscription Validity Expired',
				'hook'  => 'fluent_cart/subscription_expired_validity',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( in_array( $trigger, self::ORDER_EVENTS, true ) ) {
			return [
				...self::field_order_id(),
			];
		}//end if

		if ( in_array( $trigger, self::PRODUCT_EVENTS, true ) ) {
			return [
				...self::field_product_id(),
			];
		}

		return [];
	}

	public static function get_actions(): array {
		return [
			'get_orders_all' => [
				'label' => 'Get Orders (All)',
			],
			'get_order_single' => [
				'label' => 'Get Order (Single)',
			],
			'create_order' => [
				'label' => 'Create Order',
			],
			'update_order' => [
				'label' => 'Update Order',
			],
			'delete_order' => [
				'label' => 'Delete Order',
			],
			'get_order_transactions' => [
				'label' => 'Get Order Transactions',
			],
			'get_order_subscriptions' => [
				'label' => 'Get Order Subscriptions',
			],
			'get_order_items' => [
				'label' => 'Get Order Items',
			],
			'get_order_customer' => [
				'label' => 'Get Order Customer',
			],
			'get_order_metadata_all' => [
				'label' => 'Get Order Metadata (All)',
			],
			'get_order_metadata_single' => [
				'label' => 'Get Order Metadata (Single)',
			],
			'update_order_metadata' => [
				'label' => 'Update Order Metadata',
			],
			'delete_order_metadata' => [
				'label' => 'Delete Order Metadata',
			],
			'get_order_coupons' => [
				'label' => 'Get Order Coupons',
			],
			'get_order_shipping_address' => [
				'label' => 'Get Order Shipping Address',
			],
			'get_order_billing_address' => [
				'label' => 'Get Order Billing Address',
			],
			'get_order_addresses' => [
				'label' => 'Get Order Addresses',
			],
			'get_order_licenses' => [
				'label' => 'Get Order Licenses',
			],
			'get_order_labels' => [
				'label' => 'Get Order Labels',
			],
			'get_order_renewals' => [
				'label' => 'Get Order Renewals',
			],
			'get_order_tax_rates' => [
				'label' => 'Get Order Tax Rates',
			],
			'update_order_status' => [
				'label' => 'Update Order Status',
			],
			'get_customers_all' => [
				'label' => 'Get Customers (All)',
			],
			'get_customer_single' => [
				'label' => 'Get Customer (Single)',
			],
			'create_customer' => [
				'label' => 'Create New Customer',
			],
			'update_customer' => [
				'label' => 'Update Customer',
			],
			'delete_customer' => [
				'label' => 'Delete Customer',
			],
			'get_customer_orders' => [
				'label' => 'Get Customer Orders',
			],
			'get_customer_subscriptions' => [
				'label' => 'Get Customer Subscriptions',
			],
			'get_customer_shipping_address' => [
				'label' => 'Get Customer Shipping Address',
			],
			'get_customer_billing_address' => [
				'label' => 'Get Customer Billing Address',
			],
			'get_customer_primary_shipping_address' => [
				'label' => 'Get Customer Primary Shipping Address',
			],
			'get_customer_primary_billing_address' => [
				'label' => 'Get Customer Primary Billing Address',
			],
			'get_customer_metadata' => [
				'label' => 'Get Customer Metadata',
			],
			'get_customer_labels' => [
				'label' => 'Get Customer Labels',
			],
			'get_subscriptions_all' => [
				'label' => 'Get Subscriptions (All)',
			],
			'get_subscription_single' => [
				'label' => 'Get Subscription (Single)',
			],
			'get_current_subscription' => [
				'label' => 'Get Current Subscription',
			],
			'get_subscription_transactions' => [
				'label' => 'Get Subscription Transactions',
			],
			'get_products_all' => [
				'label' => 'Get Products (All)',
			],
			'get_product_single' => [
				'label' => 'Get Product (Single)',
			],
			'create_product' => [
				'label' => 'Create Product',
			],
			'update_product' => [
				'label' => 'Update Product',
			],
			'delete_product' => [
				'label' => 'Delete Product',
			],
			'get_product_variants' => [
				'label' => 'Get Product Variants',
			],
			'get_total_paid_amount' => [
				'label' => 'Get Total Paid Amount',
			],
			'get_total_refund_amount' => [
				'label' => 'Get Total Refund Amount',
			],
			'generate_receipt_number' => [
				'label' => 'Generate Receipt Number',
			],
			'get_receipt_url' => [
				'label' => 'Get Receipt URL',
			],
			'update_payment_status' => [
				'label' => 'Update Payment Status',
			],
			'update_shipping_status' => [
				'label' => 'Update Shipping Status',
			],
			'get_transactions_all' => [
				'label' => 'Get Transactions (All)',
			],
			'get_transaction_single' => [
				'label' => 'Get Transaction (Single)',
			],
			'get_refund_transactions' => [
				'label' => 'Get Refund Transactions',
			],
			'get_latest_transaction' => [
				'label' => 'Get Latest Transaction',
			],
			'get_coupons_all' => [
				'label' => 'Get Coupons (All)',
			],
			'get_coupon_single' => [
				'label' => 'Get Coupon (Single)',
			],
			'create_coupon' => [
				'label' => 'Create Coupon',
			],
			'update_coupon' => [
				'label' => 'Update Coupon',
			],
			'delete_coupon' => [
				'label' => 'Delete Coupon',
			],
			'get_licenses_all' => [
				'label' => 'Get Licenses (All)',
			],
			'get_license_single' => [
				'label' => 'Get License (Single)',
			],
			'add_action' => [
				'label' => 'Add Action Hook',
			],
			'do_action' => [
				'label' => 'Do Action Hook',
			],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [
		'get_orders_all' => [
			...self::field_limit_page(),
			...self::field_search(),
		],
		'get_order_single' => [
			...self::field_order_id(),
		],
		'create_order' => [
			...self::field_products(),
			...self::field_customer_id(),
			...self::field_order_status(),
			...self::field_shipping_status(),
			...self::field_fulfillment_type(),
			...self::field_order_type(),
			...self::field_order_mode(),
			...self::field_payment_method(),
			...self::field_payment_method_title(),
			...self::field_payment_status(),
			...self::field_currency_code(),
			...self::field_subtotal(),
			...self::field_discount_tax(),
			...self::field_manual_discount_total(),
			...self::field_coupon_discount_total(),
			...self::field_shipping_tax(),
			...self::field_shipping_total(),
			...self::field_tax_total(),
			...self::field_total_amount(),
			...self::field_exchange_rate(),
			...self::field_tax_behavior(),
			...self::field_order_note(),
		],
		'update_order' => [
			...self::field_order_id(),
			...self::field_customer_id(),
			...self::field_order_status(),
			...self::field_shipping_status(),
			...self::field_fulfillment_type(),
			...self::field_order_type(),
			...self::field_order_mode(),
			...self::field_payment_method(),
			...self::field_payment_method_title(),
			...self::field_payment_status(),
			...self::field_currency_code(),
			...self::field_subtotal(),
			...self::field_discount_tax(),
			...self::field_manual_discount_total(),
			...self::field_coupon_discount_total(),
			...self::field_shipping_tax(),
			...self::field_shipping_total(),
			...self::field_tax_total(),
			...self::field_total_amount(),
			...self::field_exchange_rate(),
			...self::field_tax_behavior(),
			...self::field_order_note(),
		],
		'delete_order' => [
			...self::field_order_id(),
		],
		'get_order_transactions' => [
			...self::field_order_id(),
		],
		'get_order_subscriptions' => [
			...self::field_order_id(),
		],
		'get_order_items' => [
			...self::field_order_id(),
		],
		'get_order_customer' => [
			...self::field_order_id(),
		],
		'get_order_metadata_all' => [
			...self::field_order_id(),
		],
		'get_order_metadata_single' => [
			...self::field_order_id(),
			...self::field_metadata_key(),
		],
		'update_order_metadata' => [
			...self::field_order_id(),
			...self::field_metadata_key(),
			...self::field_metadata_value(),
		],
		'delete_order_metadata' => [
			...self::field_order_id(),
			...self::field_metadata_key(),
		],
		'get_order_coupons' => [
			...self::field_order_id(),
		],
		'get_order_shipping_address' => [
			...self::field_order_id(),
		],
		'get_order_billing_address' => [
			...self::field_order_id(),
		],
		'get_order_addresses' => [
			...self::field_order_id(),
		],
		'get_order_licenses' => [
			...self::field_order_id(),
		],
		'get_order_labels' => [
			...self::field_order_id(),
		],
		'get_order_renewals' => [
			...self::field_order_id(),
		],
		'get_order_tax_rates' => [
			...self::field_order_id(),
		],
		'update_order_status' => [
			...self::field_order_id(),
			...self::field_order_status(),
		],
		'get_total_paid_amount' => [
			...self::field_order_id(),
		],
		'get_total_refund_amount' => [
			...self::field_order_id(),
		],
		'generate_receipt_number' => [
			...self::field_order_id(),
		],
		'get_receipt_url' => [
			...self::field_order_id(),
		],
		'update_payment_status' => [
			...self::field_order_id(),
			...self::field_payment_status(),
		],
		'update_shipping_status' => [
			...self::field_order_id(),
			...self::field_shipping_status(),
		],
		'get_transactions_all' => [
			...self::field_limit_page(),
			...self::field_search(),
		],
		'get_transaction_single' => [
			...self::field_transaction_id(),
		],
		'get_refund_transactions' => [
			...self::field_order_id(),
		],
		'get_latest_transaction' => [
			...self::field_order_id(),
		],
		'get_customers_all' => [
			...self::field_limit_page(),
			...self::field_search(),
		],
		'get_customer_single' => [
			...self::field_customer_id(),
		],
		'create_customer' => [
			...self::field_customer_fields_mapping(),
		],
		'update_customer' => [
			...self::field_customer_id(),
			...self::field_customer_fields_mapping(),
		],
		'delete_customer' => [
			...self::field_customer_id(),
		],
		'get_customer_orders' => [
			...self::field_customer_id(),
		],
		'get_customer_subscriptions' => [
			...self::field_customer_id(),
		],
		'get_customer_shipping_address' => [
			...self::field_customer_id(),
		],
		'get_customer_billing_address' => [
			...self::field_customer_id(),
		],
		'get_customer_primary_shipping_address' => [
			...self::field_customer_id(),
		],
		'get_customer_primary_billing_address' => [
			...self::field_customer_id(),
		],
		'get_customer_metadata' => [
			...self::field_customer_id(),
		],
		'get_customer_labels' => [
			...self::field_customer_id(),
		],
		'get_subscriptions_all' => [
			...self::field_limit_page(),
			...self::field_search(),
		],
		'get_subscription_single' => [
			...self::field_subscription_id(),
		],
		'get_current_subscription' => [
			...self::field_order_id(),
		],
		'get_subscription_transactions' => [
			...self::field_subscription_id(),
		],
		'get_products_all' => [
			...self::field_limit_page(),
			...self::field_search(),
		],
		'get_product_single' => [
			...self::field_product_id(),
		],
		'create_product' => [
			...self::create_product_schema_fields(),
		],
		'update_product' => [
			...self::field_product_id(),
			...self::create_product_schema_fields(),
		],
		'delete_product' => [
			...self::field_product_id(),
		],
		'get_product_variants' => [
			...self::field_product_id(),
		],
		'get_coupons_all' => [
			...self::field_limit_page(),
			...self::field_search(),
		],
		'get_coupon_single' => [
			...self::field_coupon_id(),
		],
		'create_coupon' => [
			...self::field_coupon_fields(),
		],
		'update_coupon' => [
			...self::field_coupon_id(),
			...self::field_coupon_fields(),
		],
		'delete_coupon' => [
			...self::field_coupon_id(),
		],
		'get_licenses_all' => [
			...self::field_limit_page(),
			...self::field_search(),
		],
		'get_license_single' => [
			...self::field_license_id(),
		],
		'add_action' => [
			[
				'key'      => 'hook_name',
				'label'    => 'Hook Name',
				'type'     => 'text',
				'required' => true,
			],
			[
				'key'   => 'accepted_args',
				'label' => 'Accepted Args',
				'type'  => 'number',
			],
		],
		'do_action' => [
			[
				'key'      => 'hook_name',
				'label'    => 'Hook Name',
				'type'     => 'text',
				'required' => true,
			],
			[
				'key'   => 'arg_1',
				'label' => 'Argument 1',
				'type'  => 'expression',
			],
			[
				'key'   => 'arg_2',
				'label' => 'Argument 2',
				'type'  => 'expression',
			],
		],
		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		$event  = self::resolve_node_event( $node, 'action' );
		$config = self::resolve_node_config( $node );

		if ( ! self::is_fluentcart_available() && ! in_array( $event, [ 'add_action', 'do_action' ], true ) ) {
			return self::error_response( 'FluentCart is not available', $input );
		}

		switch ( $event ) {
			case 'get_orders_all':
				return self::action_get_orders_all( $config, $input );
			case 'get_order_single':
				return self::action_get_order_single( $config, $input );
			case 'create_order':
				return self::action_create_order( $config, $input );
			case 'update_order':
				return self::action_update_order( $config, $input );
			case 'delete_order':
				return self::action_delete_order( $config, $input );
			case 'get_order_transactions':
				return self::action_get_order_transactions( $config, $input );
			case 'get_order_subscriptions':
				return self::action_get_order_subscriptions( $config, $input );
			case 'get_order_items':
				return self::action_get_order_items( $config, $input );
			case 'get_order_customer':
				return self::action_get_order_customer( $config, $input );
			case 'get_order_metadata_all':
				return self::action_get_order_metadata_all( $config, $input );
			case 'get_order_metadata_single':
				return self::action_get_order_metadata_single( $config, $input );
			case 'update_order_metadata':
				return self::action_update_order_metadata( $config, $input );
			case 'delete_order_metadata':
				return self::action_delete_order_metadata( $config, $input );
			case 'get_order_coupons':
				return self::action_get_order_coupons( $config, $input );
			case 'get_order_shipping_address':
				return self::action_get_order_shipping_address( $config, $input );
			case 'get_order_billing_address':
				return self::action_get_order_billing_address( $config, $input );
			case 'get_order_addresses':
				return self::action_get_order_addresses( $config, $input );
			case 'get_order_licenses':
				return self::action_get_order_licenses( $config, $input );
			case 'get_order_labels':
				return self::action_get_order_labels( $config, $input );
			case 'get_order_renewals':
				return self::action_get_order_renewals( $config, $input );
			case 'get_order_tax_rates':
				return self::action_get_order_tax_rates( $config, $input );
			case 'update_order_status':
				return self::action_update_order_status( $config, $input );
			case 'get_total_paid_amount':
				return self::action_get_total_paid_amount( $config, $input );
			case 'get_total_refund_amount':
				return self::action_get_total_refund_amount( $config, $input );
			case 'generate_receipt_number':
				return self::action_generate_receipt_number( $config, $input );
			case 'get_receipt_url':
				return self::action_get_receipt_url( $config, $input );
			case 'update_payment_status':
				return self::action_update_payment_status( $config, $input );
			case 'update_shipping_status':
				return self::action_update_shipping_status( $config, $input );
			case 'get_transactions_all':
				return self::action_get_transactions_all( $config, $input );
			case 'get_transaction_single':
				return self::action_get_transaction_single( $config, $input );
			case 'get_refund_transactions':
				return self::action_get_refund_transactions( $config, $input );
			case 'get_latest_transaction':
				return self::action_get_latest_transaction( $config, $input );
			case 'get_customer_single':
				return self::action_get_customer_single( $config, $input );
			case 'get_customers_all':
				return self::action_get_customers_all( $config, $input );
			case 'create_customer':
				return self::action_create_customer( $config, $input );
			case 'update_customer':
				return self::action_update_customer( $config, $input );
			case 'delete_customer':
				return self::action_delete_customer( $config, $input );
			case 'get_customer_orders':
				return self::action_get_customer_orders( $config, $input );
			case 'get_customer_subscriptions':
				return self::action_get_customer_subscriptions( $config, $input );
			case 'get_customer_shipping_address':
				return self::action_get_customer_shipping_address( $config, $input );
			case 'get_customer_billing_address':
				return self::action_get_customer_billing_address( $config, $input );
			case 'get_customer_primary_shipping_address':
				return self::action_get_customer_primary_shipping_address( $config, $input );
			case 'get_customer_primary_billing_address':
				return self::action_get_customer_primary_billing_address( $config, $input );
			case 'get_customer_metadata':
				return self::action_get_customer_metadata( $config, $input );
			case 'get_customer_labels':
				return self::action_get_customer_labels( $config, $input );
			case 'get_subscription_single':
				return self::action_get_subscription_single( $config, $input );
			case 'get_subscriptions_all':
				return self::action_get_subscriptions_all( $config, $input );
			case 'get_current_subscription':
				return self::action_get_current_subscription( $config, $input );
			case 'get_subscription_transactions':
				return self::action_get_subscription_transactions( $config, $input );
			case 'get_product_single':
				return self::action_get_product_single( $config, $input );
			case 'get_products_all':
				return self::action_get_products_all( $config, $input );
			case 'create_product':
				return self::action_create_product( $config, $input );
			case 'update_product':
				return self::action_update_product( $config, $input );
			case 'delete_product':
				return self::action_delete_product( $config, $input );
			case 'get_product_variants':
				return self::action_get_product_variants( $config, $input );
			case 'get_coupons_all':
				return self::action_get_coupons_all( $config, $input );
			case 'get_coupon_single':
				return self::action_get_coupon_single( $config, $input );
			case 'create_coupon':
				return self::action_create_coupon( $config, $input );
			case 'update_coupon':
				return self::action_update_coupon( $config, $input );
			case 'delete_coupon':
				return self::action_delete_coupon( $config, $input );
			case 'get_licenses_all':
				return self::action_get_licenses_all( $config, $input );
			case 'get_license_single':
				return self::action_get_license_single( $config, $input );
			case 'add_action':
				return self::action_add_action( $config, $input );
			case 'do_action':
				return self::action_do_action( $config, $input );
		}//end switch

		return self::main_response( $input );
	}

	/**
	 * Sample trigger output for the "@" variable picker.
	 *
	 * Keys mirror what resolve_trigger() emits for each event. Every trigger
	 * returns a non-empty array: explicit samples where the shape is specific,
	 * plus category fallbacks keyed by the event-name prefix so no trigger is
	 * ever empty in the picker before a real capture.
	 */
	public static function get_trigger_sample_output( string $trigger ): array {
		$customer_sample = [
			'id'         => 5,
			'customer_id' => 5,
			'email'      => 'john@example.com',
			'first_name' => 'John',
			'last_name'  => 'Doe',
		];

		$order_sample = [
			'id'             => 101,
			'order_id'       => 101,
			'status'         => 'paid',
			'total'          => '49.00',
			'currency'       => 'USD',
			'customer_id'    => 5,
			'customer_email' => 'john@example.com',
			'items'          => [
				[
					'product_id' => 12,
					'name'       => 'Pro Plan',
					'quantity'   => 1,
					'total'      => '49.00',
				],
			],
		];

		$subscription_sample = [
			'id'          => 9,
			'status'      => 'active',
			'total'       => '49.00',
			'currency'    => 'USD',
			'customer_id' => 5,
			'order_id'    => 101,
		];

		$product_sample = [
			'id'    => 12,
			'ID'    => 12,
			'name'  => 'Pro Plan',
			'price' => '49.00',
		];

		// Base shape emitted by resolve_order_event_trigger().
		$order_base = [
			'event'               => $trigger,
			'event_time'          => '2026-01-01 12:00:00',
			'args'                => [],
			'order_id'            => 101,
			'customer_id'         => 5,
			'order'               => $order_sample,
			'customer'            => $customer_sample,
			'transaction'         => [],
			'old_status'          => '',
			'new_status'          => '',
			'reason'              => '',
			'type'                => '',
			'subscription'        => [],
			'connected_order_ids' => [],
			'refunded_items'      => [],
			'refunded_amount'     => 0.0,
		];

		// Base shape emitted by resolve_subscription_event_trigger().
		$subscription_base = [
			'event'           => $trigger,
			'event_time'      => '2026-01-01 12:00:00',
			'args'            => [],
			'subscription_id' => 9,
			'customer_id'     => 5,
			'order_id'        => 101,
			'subscription'    => $subscription_sample,
			'order'           => $order_sample,
			'customer'        => $customer_sample,
			'reason'          => '',
			'meta'            => [],
		];

		$samples = [
			'order_created'            => $order_base,
			'order_paid'              => array_merge( $order_base, [
				'transaction' => [
					'id' => 501,
					'total' => '49.00',
					'status' => 'paid'
				]
			] ),
			'order_payment_failed'    => array_merge( $order_base, [ 'reason' => 'card_declined' ] ),
			'order_updated'           => $order_base,
			'order_canceled'          => array_merge( $order_base, [ 'reason' => 'customer_request' ] ),
			'order_deleted'           => $order_base,
			'renewal_order_deleted'   => array_merge( $order_base, [ 'type' => 'renewal' ] ),
			'order_refunded'          => array_merge( $order_base, [
				'refunded_amount' => 49.0,
				'refunded_items' => [
					[
						'product_id' => 12,
						'total' => '49.00'
					]
				]
			] ),
			'order_fully_refunded'    => array_merge( $order_base, [ 'refunded_amount' => 49.0 ] ),
			'order_partially_refunded' => array_merge( $order_base, [ 'refunded_amount' => 20.0 ] ),
			'order_status_changed'    => array_merge( $order_base, [
				'old_status' => 'processing',
				'new_status' => 'paid'
			] ),
			'payment_status_changed'  => array_merge( $order_base, [
				'old_status' => 'pending',
				'new_status' => 'paid'
			] ),
			'shipping_status_changed' => array_merge( $order_base, [
				'old_status' => 'unshipped',
				'new_status' => 'shipped'
			] ),

			'subscription_activated'        => array_merge( $subscription_base, [ 'reason' => 'payment_received' ] ),
			'subscription_canceled'         => array_merge( $subscription_base, [ 'reason' => 'customer_request' ] ),
			'subscription_renewed'          => array_merge( $subscription_base, [ 'reason' => 'renewal_payment' ] ),
			'subscription_eot'              => array_merge( $subscription_base, [ 'reason' => 'end_of_term' ] ),
			'subscription_expired_validity' => array_merge( $subscription_base, [ 'reason' => 'validity_expired' ] ),

			'product_created' => [
				'event'      => $trigger,
				'event_time' => '2026-01-01 12:00:00',
				'args'       => [],
				'product_id' => 12,
				'product'    => $product_sample,
			],
			'product_updated' => [
				'event'      => $trigger,
				'event_time' => '2026-01-01 12:00:00',
				'args'       => [],
				'product_id' => 12,
				'data'       => [ 'price' => '49.00' ],
				'product'    => $product_sample,
			],
			'product_duplicated' => [
				'event'               => $trigger,
				'event_time'          => '2026-01-01 12:00:00',
				'args'                => [],
				'original_product_id' => 12,
				'new_product_id'      => 13,
				'options'             => [],
				'product'             => array_merge( $product_sample, [
					'id' => 13,
					'ID' => 13
				] ),
			],
			'product_stock_changed' => [
				'event'       => $trigger,
				'event_time'  => '2026-01-01 12:00:00',
				'args'        => [],
				'product_ids' => [ 12 ],
				'other_info'  => [],
			],
		];

		if ( isset( $samples[ $trigger ] ) ) {
			return $samples[ $trigger ];
		}

		// Category fallbacks by event-name prefix so every trigger exposes fields
		// in the "@" picker even without an explicit sample above.
		if ( 0 === strpos( $trigger, 'order_' ) || 0 === strpos( $trigger, 'payment_' ) || 0 === strpos( $trigger, 'shipping_' ) ) {
			return [
				'order_id'       => 101,
				'total'          => '49.00',
				'currency'       => 'USD',
				'status'         => 'paid',
				'customer_email' => 'john@example.com',
			];
		}
		if ( 0 === strpos( $trigger, 'subscription_' ) ) {
			return [
				'subscription_id' => 9,
				'status'          => 'active',
				'total'           => '49.00',
			];
		}
		if ( 0 === strpos( $trigger, 'product_' ) ) {
			return [
				'product_id' => 12,
				'name'       => 'Pro Plan',
				'price'      => '49.00',
			];
		}
		if ( 0 === strpos( $trigger, 'customer_' ) ) {
			return [
				'customer_id' => 5,
				'email'       => 'john@example.com',
				'first_name'  => 'John',
				'last_name'   => 'Doe',
			];
		}

		return $order_base;
	}
}
