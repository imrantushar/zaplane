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

	public static function get_name(): string {
		return 'SureCart';
	}

	public static function get_icon(): string {
		return 'surecart.svg';
	}

	public static function get_triggers(): array {
		return [
			'purchase_created' => [
				'label' => 'Purchase Created',
				'hook' => 'surecart/purchase_created'
			],
			'purchase_revoked' => [
				'label' => 'Purchase Revoked',
				'hook' => 'surecart/purchase_revoked'
			],
			'purchase_invoked' => [
				'label' => 'Purchase Invoked',
				'hook' => 'surecart/purchase_invoked'
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
		if ( '' === $event ) {
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



	/**
	 * Sample output for the "@" variable picker.
	 *
	 * Keys mirror exactly what resolve_trigger() returns for each event so users
	 * can pick trigger fields before a real test run exists. Values are realistic
	 * SureCart-shaped dummies. Every trigger resolves to a non-empty array.
	 */
	public static function get_trigger_sample_output( string $trigger ): array {
		$customer = [
			'id'         => 'cust_a1b2c3d4e5',
			'object'     => 'customer',
			'name'       => 'John Doe',
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'email'      => 'john@example.com',
			'phone'      => '+15551234567',
			'live_mode'  => true,
			'created_at' => 1767268800,
			'updated_at' => 1767268800,
		];

		$price = [
			'id'                       => 'price_p1r2i3c4e5',
			'object'                   => 'price',
			'name'                     => 'Monthly',
			'amount'                   => 4900,
			'currency'                 => 'usd',
			'recurring_interval'       => 'month',
			'recurring_interval_count' => 1,
			'ad_hoc'                   => false,
			'live_mode'                => true,
			'product'                  => 'prod_p1r2o3d4u5',
		];

		$product = [
			'id'          => 'prod_p1r2o3d4u5',
			'object'      => 'product',
			'name'        => 'Pro Plan',
			'description' => 'Full access to all pro features.',
			'status'      => 'published',
			'slug'        => 'pro-plan',
			'currency'    => 'usd',
			'live_mode'   => true,
			'prices'      => [ $price ],
			'created_at'  => 1767268800,
			'updated_at'  => 1767268800,
		];

		$subscription = [
			'id'                     => 'sub_s1u2b3s4c5',
			'object'                 => 'subscription',
			'status'                 => 'active',
			'customer'               => $customer['id'],
			'price'                  => $price['id'],
			'quantity'               => 1,
			'current_period_start_at' => 1767268800,
			'current_period_end_at'  => 1769947200,
			'canceled_at'            => null,
			'live_mode'              => true,
			'created_at'             => 1767268800,
			'updated_at'             => 1767268800,
		];

		$checkout = [
			'id'           => 'checkout_c1h2e3c4k5',
			'object'       => 'checkout',
			'status'       => 'paid',
			'email'        => 'john@example.com',
			'name'         => 'John Doe',
			'currency'     => 'usd',
			'total_amount' => 4900,
			'subtotal_amount' => 4900,
			'tax_amount'   => 0,
			'discount_amount' => 0,
			'customer'     => $customer,
			'live_mode'    => true,
			'line_items'   => [
				'object' => 'list',
				'data'   => [
					[
						'id'       => 'li_l1i2n3e4i5',
						'object'   => 'line_item',
						'quantity' => 1,
						'price'    => $price,
					],
				],
			],
			'created_at'   => 1767268800,
			'updated_at'   => 1767268800,
		];

		// Shape emitted by payload_from_model( $model, 'purchase' ).
		$purchase = [
			'id'           => 'purchase_p1u2r3c4h5',
			'object'       => 'purchase',
			'revoked'      => false,
			'quantity'     => 1,
			'customer'     => $customer,
			'product'      => $product,
			'price'        => $price,
			'subscription' => $subscription,
			'checkout'     => $checkout,
			'live_mode'    => true,
			'created_at'   => 1767268800,
			'updated_at'   => 1767268800,
		];

		// Shape emitted by normalize_post().
		$post = [
			'ID'            => 42,
			'post_title'    => 'Pro Plan',
			'post_name'     => 'pro-plan',
			'post_status'   => 'publish',
			'post_type'     => 'sc_product',
			'post_date'     => '2026-01-01 12:00:00',
			'post_modified' => '2026-01-01 12:00:00',
			'post_author'   => 1,
			'guid'          => 'https://example.com/?post_type=sc_product&p=42',
		];

		$samples = [
			// payload_from_model( $args[0], 'purchase' ) => [ 'purchase' => ... ]
			'purchase_created' => [ 'purchase' => $purchase ],
			'purchase_invoked' => [ 'purchase' => $purchase ],
			'purchase_revoked' => [ 'purchase' => array_merge( $purchase, [ 'revoked' => true ] ) ],

			// payload_checkout_confirmed() => [ 'checkout' => ..., 'request' => ... ]
			'checkout_confirmed' => [
				'checkout' => $checkout,
				'request'  => [
					'method' => 'POST',
					'route'  => '/surecart/v1/checkouts/checkout_c1h2e3c4k5/confirm',
					'params' => [ 'id' => 'checkout_c1h2e3c4k5' ],
				],
			],

			// [ 'post' => normalize_post(...), 'product' => model_to_array(...) ]
			'product_sync_created' => [
				'post' => $post,
				'product' => $product
			],
			'product_sync_updated' => [
				'post' => $post,
				'product' => $product
			],

			// [ 'params' => ... ]
			'integrations_created' => [
				'params' => [
					'provider'   => 'surecart',
					'model_id'   => 'prod_p1r2o3d4u5',
					'model_type' => 'product',
					'integration_type' => 'download',
				],
			],
			'integrations_deleted' => [
				'params' => [
					'provider'   => 'surecart',
					'model_id'   => 'prod_p1r2o3d4u5',
					'model_type' => 'product',
					'integration_type' => 'download',
				],
			],

			// resolve_trigger returns [] for these; provide a non-empty sample.
			'help_widget_loaded' => [
				'user_id'    => 1,
				'user_email' => 'john@example.com',
				'screen'     => 'sc-dashboard',
				'loaded_at'  => 1767268800,
			],
			'admin_coupons_edit' => [
				'coupon_id' => 'coupon_c1o2u3p4o5',
				'screen'    => 'surecart-coupons',
				'user_id'   => 1,
			],

			// [ 'post' => normalize_post(...), 'data' => ... ]
			'post_created' => [
				'post' => $post,
				'data' => [
					'source'  => 'surecart',
					'model'   => 'product',
					'post_id' => 42,
				],
			],
		];

		if ( isset( $samples[ $trigger ] ) ) {
			return $samples[ $trigger ];
		}

		// Category fallbacks by event-name prefix so any future trigger still
		// exposes fields in the "@" picker without an explicit sample above.
		if ( 0 === strpos( $trigger, 'purchase_' ) ) {
			return [ 'purchase' => $purchase ];
		}
		if ( 0 === strpos( $trigger, 'checkout_' ) ) {
			return [ 'checkout' => $checkout ];
		}
		if ( 0 === strpos( $trigger, 'order_' ) ) {
			return [ 'checkout' => $checkout ];
		}
		if ( 0 === strpos( $trigger, 'subscription_' ) ) {
			return [ 'subscription' => $subscription ];
		}
		if ( 0 === strpos( $trigger, 'product_' ) ) {
			return [
				'post' => $post,
				'product' => $product
			];
		}
		if ( 0 === strpos( $trigger, 'customer_' ) ) {
			return [ 'customer' => $customer ];
		}
		if ( 0 === strpos( $trigger, 'integrations_' ) ) {
			return [
				'params' => [
					'provider' => 'surecart',
					'model_type' => 'product'
				]
			];
		}
		if ( 0 === strpos( $trigger, 'post_' ) ) {
			return [
				'post' => $post,
				'data' => [ 'source' => 'surecart' ]
			];
		}

		// Final non-empty catch-all so NO trigger ever returns [].
		return [
			'purchase' => $purchase,
			'customer' => $customer,
			'product'  => $product,
		];
	}



	public static function get_actions(): array {
		return [
			'create_order' 	  		  => [ 'label' => 'Create Order' ],
			'update_order' 	  		  => [ 'label' => 'Update Order' ],
			'get_orders_all' 	  	  => [ 'label' => 'Get Orders (All)' ],
			'get_order_single' 	      => [ 'label' => 'Get Order (Single)' ],
			'create_customer' 	  	  => [ 'label' => 'Create Customer' ],
			'update_customer' 	  	  => [ 'label' => 'Update Customer' ],
			'get_customers_all' 	  => [ 'label' => 'Get Customers (All)' ],
			'get_customer_single' 	  => [ 'label' => 'Get Customer (Single)' ],
			'create_product_manual'   => [ 'label' => 'Create Product (Manual)' ],
			'create_product' 		  => [ 'label' => 'Create Product (JSON)' ],
			'update_product' 		  => [ 'label' => 'Update Product' ],
			'delete_product' 		  => [ 'label' => 'Delete Product' ],
			'get_products_all' 		  => [ 'label' => 'Get Products (All)' ],
			'get_product_single' 	  => [ 'label' => 'Get Product (Single)' ],
			'create_coupon' 		  => [ 'label' => 'Create Coupon' ],
			'update_coupon' 		  => [ 'label' => 'Update Coupon' ],
			'delete_coupon' 		  => [ 'label' => 'Delete Coupon' ],
			'get_coupons_all' 		  => [ 'label' => 'Get Coupons (All)' ],
			'get_coupon_single' 	  => [ 'label' => 'Get Coupon (Single)' ],
			'create_subscription' 	  => [ 'label' => 'Create Subscription' ],
			'update_subscription' 	  => [ 'label' => 'Update Subscription' ],
			'get_subscriptions_all'   => [ 'label' => 'Get Subscriptions (All)' ],
			'get_subscription_single' => [ 'label' => 'Get Subscription (Single)' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [
			'create_order' => [
				...self::field_price_id(),
				...self::field_quantity(),
				...self::field_order_status(),
				...self::field_billing_address(),
				...self::field_mode(),
				...self::field_expand(),
				...self::field_data( 'Advanced Order Data (JSON)' ),
			],
			'update_order' => [
				...self::field_order_id(),
				...self::field_billing_address(),
				...self::field_mode(),
				...self::field_expand(),
				...self::field_data( 'Advanced Order Data (JSON)' ),
			],
			'get_orders_all' => [
				...self::field_limit_page(),

			],
			'get_order_single' => [
				...self::field_order_id(),
				...self::field_mode(),
				...self::field_expand(),
			],
			'create_customer' => [
				[
					'key'         => 'email',
					'label'       => 'Email',
					'type'        => 'text',
					'required'    => true,
					'placeholder' => 'customer@example.com',
				],
				[
					'key'         => 'first_name',
					'label'       => 'First Name',
					'type'        => 'text',
					'required'    => false,
				],
				[
					'key'         => 'last_name',
					'label'       => 'Last Name',
					'type'        => 'text',
					'required'    => false,
				],
				[
					'key'         => 'phone',
					'label'       => 'Phone',
					'type'        => 'number',
					'required'    => false,
				],
				...self::field_mode(),
				...self::field_expand(),
			],
			'update_customer' => [
				...self::field_customer_id(),
				[
					'key'         => 'email',
					'label'       => 'Email',
					'type'        => 'text',
					'required'    => false,
				],
				[
					'key'         => 'first_name',
					'label'       => 'First Name',
					'type'        => 'text',
					'required'    => false,
				],
				[
					'key'         => 'last_name',
					'label'       => 'Last Name',
					'type'        => 'text',
					'required'    => false,
				],
				[
					'key'         => 'phone',
					'label'       => 'Phone',
					'type'        => 'number',
					'required'    => false,
				],
				...self::field_mode(),
				...self::field_expand(),
			],
			'get_customers_all' => [
				...self::field_limit_page(),
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
					'key'         => 'name',
					'label'       => 'Product Name',
					'type'        => 'text',
					'required'    => true,
					'placeholder' => 'Enter product name',
				],
				[
					'key'         => 'description',
					'label'       => 'Description',
					'type'        => 'textarea',
					'required'    => false,
				],
				[
					'key'      => 'product_status',
					'label'    => 'Product Status',
					'type'     => 'select',
					'required' => true,
					'options'  => [
						[
							'label' => 'Published',
							'value' => 'surecart_product_published',
						],
						[
							'label' => 'Draft',
							'value' => 'surecart_product_draft',
						],
						[
							'label' => 'Archived',
							'value' => 'surecart_product_archived'
						],
					],
				],
				[
					'key'         => 'price_amount',
					'label'       => 'Price Amount',
					'type'        => 'number',
					'required'    => true,
					'placeholder' => '0.00',
					'min'         => 0,
				],
				[
					'key'         => 'currency',
					'label'       => 'Currency',
					'type'        => 'text',
					'required'    => true,
					'default'     => 'USD',
					'placeholder' => 'USD',
				],
				[
					'key'      => 'recurring_interval',
					'label'    => 'Recurring Interval',
					'type'     => 'select',
					'required' => false,
					'options'  => [
						[
							'label' => 'One Time',
							'value' => '',
						],
						[
							'label' => 'Day',
							'value' => 'day',
						],
						[
							'label' => 'Week',
							'value' => 'week',
						],
						[
							'label' => 'Month',
							'value' => 'month',
						],
						[
							'label' => 'Year',
							'value' => 'year',
						],
					],
				],
				[
					'key'         => 'recurring_interval_count',
					'label'       => 'Recurring Interval Count',
					'type'        => 'number',
					'required'    => false,
					'default'     => 1,
					'min'         => 1,
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
				[
					'key'         => 'name',
					'label'       => 'Product Name',
					'type'        => 'text',
					'required'    => false,
				],
				[
					'key'         => 'description',
					'label'       => 'Description',
					'type'        => 'textarea',
					'required'    => false,
				],
				[
					'key'      => 'update_status',
					'label'    => 'Product Status',
					'type'     => 'select',
					'required' => false,
					'options'  => [
						[
							'label' => 'Published',
							'value' => 'published',
						],
						[
							'label' => 'Draft',
							'value' => 'draft',
						],
					],
				],
				[
					'key'         => 'data',
					'label'       => 'Additional Product Data (JSON)',
					'type'        => 'textarea',
					'required'    => false,
				],
				...self::field_mode(),
				...self::field_expand(),
			],
			'delete_product' => [
				...self::field_product_id(),
				...self::field_mode(),
			],
			'get_products_all' => [
				...self::field_limit_page(),
				...self::field_mode(),
				...self::field_expand(),
			],
			'get_product_single' => [
				...self::field_product_id(),
				...self::field_mode(),
				...self::field_expand(),
			],
			'create_coupon' => [
				[
					'key'         => 'code',
					'label'       => 'Coupon Code',
					'type'        => 'text',
					'required'    => true,
					'placeholder' => 'SUMMER20',
				],
				[
					'key'      => 'discount_type',
					'label'    => 'Discount Type',
					'type'     => 'select',
					'required' => true,
					'options'  => [
						[
							'label' => 'Percentage',
							'value' => 'percentage',
						],
						[
							'label' => 'Fixed Amount',
							'value' => 'fixed',
						],
					],
				],
				[
					'key'         => 'discount_amount',
					'label'       => 'Discount Amount',
					'type'        => 'number',
					'required'    => true,
					'placeholder' => '10',
					'min'         => 0,
				],
				[
					'key'         => 'currency',
					'label'       => 'Currency',
					'type'        => 'text',
					'required'    => false,
					'default'     => 'USD',
				],
				[
					'key'      => 'duration',
					'label'    => 'Duration',
					'type'     => 'select',
					'required' => false,
					'options'  => [
						[
							'label' => 'Once',
							'value' => 'once',
						],
						[
							'label' => 'Forever',
							'value' => 'forever',
						],
						[
							'label' => 'Repeating',
							'value' => 'repeating',
						],
					],
				],
				[
					'key'         => 'duration_in_months',
					'label'       => 'Duration in Months',
					'type'        => 'number',
					'required'    => false,
					'min'         => 1,
				],
				...self::field_mode(),
				...self::field_expand(),
			],
			'update_coupon' => [
				...self::field_coupon_id(),
				[
					'key'         => 'code',
					'label'       => 'Coupon Code',
					'type'        => 'text',
					'required'    => false,
				],
				[
					'key'      => 'discount_type',
					'label'    => 'Discount Type',
					'type'     => 'select',
					'required' => false,
					'options'  => [
						[
							'label' => 'Percentage',
							'value' => 'percentage',
						],
						[
							'label' => 'Fixed Amount',
							'value' => 'fixed',
						],
					],
				],
				[
					'key'         => 'discount_amount',
					'label'       => 'Discount Amount',
					'type'        => 'number',
					'required'    => false,
					'min'         => 0,
				],
				[
					'key'         => 'data',
					'label'       => 'Additional Coupon Data (JSON)',
					'type'        => 'textarea',
					'required'    => false,
				],
				...self::field_mode(),
				...self::field_expand(),
			],
			'delete_coupon' => [
				...self::field_coupon_id(),
				...self::field_mode(),
			],
			'get_coupons_all' => [
				...self::field_limit_page(),
				...self::field_mode(),
				...self::field_expand(),
			],
			'get_coupon_single' => [
				...self::field_coupon_id(),
				...self::field_mode(),
				...self::field_expand(),
			],
			'create_subscription' => [
				...self::field_customer_id(),
				...self::field_price_id(),
				...self::field_quantity(),
				...self::field_subscription_status(),
				...self::field_mode(),
				...self::field_expand(),
			],
			'update_subscription' => [
				...self::field_subscription_id(),
				...self::field_subscription_status(),
				...self::field_quantity(),
				...self::field_mode(),
				...self::field_expand(),
			],
			'get_subscriptions_all' => [
				...self::field_limit_page(),
				...self::field_mode(),
				...self::field_expand(),
			],
			'get_subscription_single' => [
				...self::field_subscription_id(),
				...self::field_mode(),
				...self::field_expand(),
			],
		];
		return isset($schemas[$action])
			? $schemas[$action]
			: [];
	}

	public static function get_dynamic_queries(): array {
		return [
			'orders' => [ self::class, 'query_orders' ],
			'customers' => [ self::class, 'query_customers' ],
			'products' => [ self::class, 'query_products' ],
			'coupons' => [ self::class, 'query_coupons' ],
			'subscriptions' => [ self::class, 'query_subscriptions' ],
			'prices' => [ self::class, 'query_prices' ],
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
