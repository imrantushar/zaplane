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
	use HookActionsTrait;
	use TriggerResolverTrait;
	use ModelHelperTrait;
	use NodeHelperTrait;

	private const INTRODUCTION = 'Track FluentCart order, payment, subscription, and stock events and run hook-based actions without webhooks.';

	private const ORDER_EVENTS = [
		'order_created',
		'order_paid',
		'order_paid_done',
		'order_payment_failed',
		'order_updated',
		'order_canceled',
		'order_deleted',
		'renewal_order_deleted',
		'order_refunded',
		'order_fully_refunded',
		'order_partially_refunded',
		'order_status_changed',
		'payment_status_changed',
		'shipping_status_changed',
	];

	private const SUBSCRIPTION_EVENTS = [
		'subscription_activated',
		'subscription_canceled',
		'subscription_renewed',
		'subscription_eot',
		'subscription_expired_validity',
	];

	private const PRODUCT_EVENTS = [
		'product_created',
		'product_updated',
		'product_duplicated',
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
			'order_created' => [
				'label' => 'Order Created',
				'hook'  => 'fluent_cart/order_created',
			],
			'order_paid' => [
				'label' => 'Order Paid',
				'hook'  => 'fluent_cart/order_paid',
			],
			'order_paid_done' => [
				'label' => 'Order Paid Done',
				'hook'  => 'fluent_cart/order_paid_done',
			],
			'order_payment_failed' => [
				'label' => 'Order Payment Failed',
				'hook'  => 'fluent_cart/order_payment_failed',
			],
			'order_updated' => [
				'label' => 'Order Updated',
				'hook'  => 'fluent_cart/order_updated',
			],
			'order_canceled' => [
				'label' => 'Order Canceled',
				'hook'  => 'fluent_cart/order_canceled',
			],
			'order_deleted' => [
				'label' => 'Order Deleted',
				'hook'  => 'fluent_cart/order_deleted',
			],
			'renewal_order_deleted' => [
				'label' => 'Renewal Order Deleted',
				'hook'  => 'fluent_cart/renewal_order_deleted',
			],
			'order_refunded' => [
				'label' => 'Order Refunded',
				'hook'  => 'fluent_cart/order_refunded',
			],
			'order_fully_refunded' => [
				'label' => 'Order Fully Refunded',
				'hook'  => 'fluent_cart/order_fully_refunded',
			],
			'order_partially_refunded' => [
				'label' => 'Order Partially Refunded',
				'hook'  => 'fluent_cart/order_partially_refunded',
			],
			'order_status_changed' => [
				'label' => 'Order Status Changed',
				'hook'  => 'fluent_cart/order_status_changed',
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
			'product_created' => [
				'label' => 'Product Created',
				'hook'  => 'save_post_fluent-products',
			],
			'product_updated' => [
				'label' => 'Product Updated',
				'hook'  => 'fluent_cart/product_updated',
			],
			'product_duplicated' => [
				'label' => 'Product Duplicated',
				'hook'  => 'fluent_cart/product_duplicated',
			],
			'product_stock_changed' => [
				'label' => 'Product Stock Changed',
				'hook'  => 'fluent_cart/product_stock_changed',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( in_array( $trigger, self::ORDER_EVENTS, true ) ) {
			return [
				[
					'key'      => 'order_id',
					'label'    => 'Order',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'orders',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
				[
					'key'      => 'customer_id',
					'label'    => 'Customer',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'customers',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}//end if

		if ( in_array( $trigger, self::SUBSCRIPTION_EVENTS, true ) ) {
			return [
				[
					'key'      => 'subscription_id',
					'label'    => 'Subscription',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'subscriptions',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
				[
					'key'      => 'order_id',
					'label'    => 'Order',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'orders',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
				[
					'key'      => 'customer_id',
					'label'    => 'Customer',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'customers',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}//end if

		if ( in_array( $trigger, self::PRODUCT_EVENTS, true ) ) {
			return [
				[
					'key'      => 'product_id',
					'label'    => 'Product',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'products',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}

		if ( 'product_stock_changed' === $trigger ) {
			return [
				[
					'key'      => 'product_id',
					'label'    => 'Product',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'products',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}

		return [];
	}

	public static function get_actions(): array {
		return [
			'get_order_single' => [
				'label' => 'Get Order (Single)',
			],
			'get_orders_all' => [
				'label' => 'Get Orders (All)',
			],
			'get_customer_single' => [
				'label' => 'Get Customer (Single)',
			],
			'get_customers_all' => [
				'label' => 'Get Customers (All)',
			],
			'get_subscription_single' => [
				'label' => 'Get Subscription (Single)',
			],
			'get_subscriptions_all' => [
				'label' => 'Get Subscriptions (All)',
			],
			'get_product_single' => [
				'label' => 'Get Product (Single)',
			],
			'get_products_all' => [
				'label' => 'Get Products (All)',
			],
			'create_product' => [
				'label' => 'Create Product',
			],
			'update_product' => [
				'label' => 'Update Product',
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
			'get_order_single' => [
				[
					'key'      => 'order_id',
					'label'    => 'Order',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'orders',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			],
			'get_orders_all' => [
				[
					'key'     => 'limit',
					'label'   => 'Limit',
					'type'    => 'number',
					'required' => true,
				],
				[
					'key'     => 'page',
					'label'   => 'Page',
					'type'    => 'number',
					'required' => true,
				],
				[
					'key'   => 'search',
					'label' => 'Search',
					'type'  => 'text',
				],
				[
					'key'      => 'customer_id',
					'label'    => 'Customer',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'customers',
						'select'      => [ 'name', 'label' ],
					],
					'required' => false,
				],
				[
					'key'      => 'order_status',
					'label'    => 'Order Status',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'order_statuses',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
				[
					'key'      => 'payment_status',
					'label'    => 'Payment Status',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'payment_statuses',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			],
			'get_customer_single' => [
				[
					'key'      => 'customer_id',
					'label'    => 'Customer',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'customers',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			],
			'get_customers_all' => [
				[
					'key'     => 'limit',
					'label'   => 'Limit',
					'type'    => 'number',
					'required' => true,
				],
				[
					'key'     => 'page',
					'label'   => 'Page',
					'type'    => 'number',
					'required' => true,
				],
				[
					'key'   => 'search',
					'label' => 'Search',
					'type'  => 'text',
				],
				[
					'key'      => 'customer_status',
					'label'    => 'Customer Status',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'customer_statuses',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			],
			'get_subscription_single' => [
				[
					'key'      => 'subscription_id',
					'label'    => 'Subscription',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'subscriptions',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			],
			'get_subscriptions_all' => [
				[
					'key'     => 'limit',
					'label'   => 'Limit',
					'type'    => 'number',
					'required' => true,
				],
				[
					'key'     => 'page',
					'label'   => 'Page',
					'type'    => 'number',
					'required' => true,
				],
				[
					'key'   => 'search',
					'label' => 'Search',
					'type'  => 'text',
				],
				[
					'key'      => 'customer_id',
					'label'    => 'Customer',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'customers',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
				[
					'key'      => 'subscription_status',
					'label'    => 'Subscription Status',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'subscription_statuses',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			],
			'get_product_single' => [
				[
					'key'      => 'product_id',
					'label'    => 'Product',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'products',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			],
			'get_products_all' => [
				[
					'key'     => 'limit',
					'label'   => 'Limit',
					'type'    => 'number',
					'required' => true,
				],
				[
					'key'     => 'page',
					'label'   => 'Page',
					'type'    => 'number',
					'required' => true,
				],
				[
					'key'   => 'search',
					'label' => 'Search',
					'type'  => 'text',
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
					'required' => false,
				],
			],
			'create_product' => [
				...self::create_product_schema_fields(),
			],
			'update_product' => [
				[
					'key'      => 'product_id',
					'label'    => 'Product',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'fluentcart',
						'query'       => 'products',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
				[
					'key'   => 'post_title',
					'label' => 'Product Title',
					'type'  => 'text',
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
					'required' => true,
				],
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
			case 'get_order_single':
				return self::action_get_order_single( $config, $input );
			case 'get_orders_all':
				return self::action_get_orders_all( $config, $input );
			case 'get_customer_single':
				return self::action_get_customer_single( $config, $input );
			case 'get_customers_all':
				return self::action_get_customers_all( $config, $input );
			case 'get_subscription_single':
				return self::action_get_subscription_single( $config, $input );
			case 'get_subscriptions_all':
				return self::action_get_subscriptions_all( $config, $input );
			case 'get_product_single':
				return self::action_get_product_single( $config, $input );
			case 'get_products_all':
				return self::action_get_products_all( $config, $input );
			case 'create_product':
				return self::action_create_product( $config, $input );
			case 'update_product':
				return self::action_update_product( $config, $input );
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
			'order_paid_done'         => array_merge( $order_base, [
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
