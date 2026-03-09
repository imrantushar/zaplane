<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Surecart\Helper;
use Zaplane\Integrations\Surecart\OrderActionsTrait;
use Zaplane\Integrations\Surecart\CustomerActionsTrait;
use Zaplane\Integrations\Surecart\ProductActionsTrait;
use Zaplane\Integrations\Surecart\CouponActionsTrait;
use Zaplane\Integrations\Surecart\SubscriptionActionsTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Surecart extends IntegrationBase {

	use Helper;
	use OrderActionsTrait;
	use CustomerActionsTrait;
	use ProductActionsTrait;
	use CouponActionsTrait;
	use SubscriptionActionsTrait;

	public static function get_slug(): string {
		return 'surecart';
	}



	public static function get_triggers(): array {
		return [
			'purchase_created' => [
				'label' => 'Purchase Created',
				'hook' => 'surecart/purchase_created'
			],
			'purchase_invoked' => [
				'label' => 'Purchase Invoked',
				'hook' => 'surecart/purchase_invoked'
			],
			'purchase_revoked' => [
				'label' => 'Purchase Revoked',
				'hook' => 'surecart/purchase_revoked'
			],
			'checkout_confirmed' => [
				'label' => 'Checkout Confirmed',
				'hook' => 'surecart/checkout_confirmed'
			],
			'product_sync_created' => [
				'label' => 'Product Synced (Created)',
				'hook' => 'surecart/product/sync/created'
			],
			'product_sync_updated' => [
				'label' => 'Product Synced (Updated)',
				'hook' => 'surecart/product/sync/updated'
			],
			'integrations_created' => [
				'label' => 'Integrations Created',
				'hook' => 'surecart/integrations/create'
			],
			'integrations_deleted' => [
				'label' => 'Integrations Deleted',
				'hook' => 'surecart/integrations/delete'
			],
			'help_widget_loaded' => [
				'label' => 'Help Widget Loaded',
				'hook' => 'surecart/help_widget/loaded'
			],
			'admin_coupons_edit' => [
				'label' => 'Admin Coupons Edit',
				'hook' => 'surecart/admin/coupons/edit'
			],
			'post_created' => [
				'label' => 'Post Created (PageService)',
				'hook' => 'surecart/post_created'
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$event = $node['event'] ?? '';
		if ( $event === '' ) {
			return false;
		}

		switch ( $event ) {
			case 'purchase_created':
			case 'purchase_invoked':
			case 'purchase_revoked':
				return self::payload_from_model( $args[0] ?? null, 'purchase' );
			case 'checkout_confirmed':
				return self::payload_checkout_confirmed( $args );
			case 'product_sync_created':
			case 'product_sync_updated':
				return [
					'post' => self::normalize_post( $args[0] ?? null ),
					'product' => self::model_to_array( $args[1] ?? null ),
				];
			case 'integrations_created':
			case 'integrations_deleted':
				return [
					'params' => is_array( $args[0] ?? null ) ? $args[0] : [],
				];
			case 'help_widget_loaded':
			case 'admin_coupons_edit':
				return [];
			case 'post_created':
				return [
					'post' => self::normalize_post( $args[0] ?? null ),
					'data' => is_array( $args[1] ?? null ) ? $args[1] : [],
				];
		}//end switch

		return false;
	}



	public static function get_actions(): array {
		return [
			'create_order' => [ 'label' => 'Create Order' ],
			'update_order' => [ 'label' => 'Update Order' ],
			'get_orders_all' => [ 'label' => 'Get Orders (All)' ],
			'get_order_single' => [ 'label' => 'Get Order (Single)' ],
			'create_customer' => [ 'label' => 'Create Customer' ],
			'update_customer' => [ 'label' => 'Update Customer' ],
			'get_customers_all' => [ 'label' => 'Get Customers (All)' ],
			'get_customer_single' => [ 'label' => 'Get Customer (Single)' ],
			'create_product_manual' => [ 'label' => 'Create Product (Manual)' ],
			'create_product' => [ 'label' => 'Create Product (JSON)' ],
			'update_product' => [ 'label' => 'Update Product' ],
			'delete_product' => [ 'label' => 'Delete Product' ],
			'get_products_all' => [ 'label' => 'Get Products (All)' ],
			'get_product_single' => [ 'label' => 'Get Product (Single)' ],
			'create_coupon' => [ 'label' => 'Create Coupon' ],
			'update_coupon' => [ 'label' => 'Update Coupon' ],
			'delete_coupon' => [ 'label' => 'Delete Coupon' ],
			'get_coupons_all' => [ 'label' => 'Get Coupons (All)' ],
			'get_coupon_single' => [ 'label' => 'Get Coupon (Single)' ],
			'create_subscription' => [ 'label' => 'Create Subscription' ],
			'update_subscription' => [ 'label' => 'Update Subscription' ],
			'get_subscriptions_all' => [ 'label' => 'Get Subscriptions (All)' ],
			'get_subscription_single' => [ 'label' => 'Get Subscription (Single)' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [
			'create_order' => [
				...self::field_data( 'Order Data (JSON)' ),
				...self::field_mode(),
				...self::field_expand(),
			],
			'update_order' => [
				...self::field_order_id(),
				...self::field_data( 'Order Data (JSON)' ),
				...self::field_mode(),
				...self::field_expand(),
			],
			'get_orders_all' => [
				...self::field_limit_page(),
				...self::field_query(),
				...self::field_mode(),
				...self::field_expand(),
			],
			'get_order_single' => [
				...self::field_order_id(),
				...self::field_mode(),
				...self::field_expand(),
			],

			'create_customer' => [
				...self::field_data( 'Customer Data (JSON)' ),
				...self::field_mode(),
				...self::field_expand(),
			],
			'update_customer' => [
				...self::field_customer_id(),
				...self::field_data( 'Customer Data (JSON)' ),
				...self::field_mode(),
				...self::field_expand(),
			],
			'get_customers_all' => [
				...self::field_limit_page(),
				...self::field_query(),
				...self::field_mode(),
				...self::field_expand(),
			],
			'get_customer_single' => [
				...self::field_customer_id(),
				...self::field_mode(),
				...self::field_expand(),
			],

			'create_product_manual' => [
				[
					'key' => 'name',
					'label' => 'Product Name',
					'type' => 'text',
					'required' => true
				],
				[
					'key' => 'description',
					'label' => 'Description',
					'type' => 'textarea'
				],
				[
					'key' => 'status',
					'label' => 'Status',
					'type' => 'select',
					'options' => [
						[
							'label' => 'Published',
							'value' => 'published'
						],
						[
							'label' => 'Draft',
							'value' => 'draft'
						],
						[
							'label' => 'Archived',
							'value' => 'archived'
						],
					]
				],
				[
					'key' => 'price_amount',
					'label' => 'Price Amount (minor unit)',
					'type' => 'number',
					'required' => true
				],
				[
					'key' => 'currency',
					'label' => 'Currency (ISO)',
					'type' => 'text',
					'required' => true
				],
				[
					'key' => 'recurring_interval',
					'label' => 'Recurring Interval',
					'type' => 'select',
					'options' => [
						[
							'label' => 'Day',
							'value' => 'day'
						],
						[
							'label' => 'Week',
							'value' => 'week'
						],
						[
							'label' => 'Month',
							'value' => 'month'
						],
						[
							'label' => 'Year',
							'value' => 'year'
						],
					]
				],
				[
					'key' => 'recurring_interval_count',
					'label' => 'Recurring Interval Count',
					'type' => 'number',
					'default' => 1
				],
				...self::field_mode(),
				...self::field_expand(),
			],
			'create_product' => [
				...self::field_data( 'Product Data (JSON)' ),
				...self::field_mode(),
				...self::field_expand(),
			],
			'update_product' => [
				...self::field_product_id(),
				...self::field_data( 'Product Data (JSON)' ),
				...self::field_mode(),
				...self::field_expand(),
			],
			'delete_product' => [
				...self::field_product_id(),
				...self::field_mode(),
			],
			'get_products_all' => [
				...self::field_limit_page(),
				...self::field_query(),
				...self::field_mode(),
				...self::field_expand(),
			],
			'get_product_single' => [
				...self::field_product_id(),
				...self::field_mode(),
				...self::field_expand(),
			],

			'create_coupon' => [
				...self::field_data( 'Coupon Data (JSON)' ),
				...self::field_mode(),
				...self::field_expand(),
			],
			'update_coupon' => [
				...self::field_coupon_id(),
				...self::field_data( 'Coupon Data (JSON)' ),
				...self::field_mode(),
				...self::field_expand(),
			],
			'delete_coupon' => [
				...self::field_coupon_id(),
				...self::field_mode(),
			],
			'get_coupons_all' => [
				...self::field_limit_page(),
				...self::field_query(),
				...self::field_mode(),
				...self::field_expand(),
			],
			'get_coupon_single' => [
				...self::field_coupon_id(),
				...self::field_mode(),
				...self::field_expand(),
			],

			'create_subscription' => [
				...self::field_data( 'Subscription Data (JSON)' ),
				...self::field_mode(),
				...self::field_expand(),
			],
			'update_subscription' => [
				...self::field_subscription_id(),
				...self::field_data( 'Subscription Data (JSON)' ),
				...self::field_mode(),
				...self::field_expand(),
			],
			'get_subscriptions_all' => [
				...self::field_limit_page(),
				...self::field_query(),
				...self::field_mode(),
				...self::field_expand(),
			],
			'get_subscription_single' => [
				...self::field_subscription_id(),
				...self::field_mode(),
				...self::field_expand(),
			],
		];

		return $schemas[ $action ] ?? [];
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
