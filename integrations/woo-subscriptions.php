<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WooSubscriptions extends IntegrationBase {

	public static function get_slug(): string {
		return 'woosubscriptions';
	}

	public static function get_name(): string {
		return 'WooCommerce Subscriptions';
	}

	public static function get_icon(): string {
		return 'woo.svg';
	}

	public static function get_triggers(): array {
		return [
			'subscription_created' => [
				'label' => 'Subscription Created',
				'hook' => 'woocommerce_new_subscription'
			],
			'subscription_status_updated' => [
				'label' => 'Subscription Status Updated',
				'hook' => 'woocommerce_subscription_status_updated'
			],
			'subscription_payment_complete' => [
				'label' => 'Subscription Payment Complete',
				'hook' => 'woocommerce_subscription_payment_complete'
			],
			'subscription_payment_failed' => [
				'label' => 'Subscription Payment Failed',
				'hook' => 'woocommerce_subscription_payment_failed'
			],
			'renewal_payment_complete' => [
				'label' => 'Renewal Payment Complete',
				'hook' => 'woocommerce_subscription_renewal_payment_complete'
			],
			'renewal_payment_failed' => [
				'label' => 'Renewal Payment Failed',
				'hook' => 'woocommerce_subscription_renewal_payment_failed'
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		if ( ! self::is_subscriptions_available() ) {
			return false;
		}

		$event = (string) ( $node['event'] ?? ( $node['data']['event'] ?? ( $node['config']['trigger'] ?? '' ) ) );
		if ( '' === $event ) {
			return false;
		}

		switch ( $event ) {
			case 'subscription_created':
				$subscription = self::resolve_subscription_from_args( $args );
				if ( ! $subscription ) {
					return false;
				}
				$order = self::resolve_order_from_args( $args );
				return self::build_subscription_payload($subscription, [
					'order_id' => $order ? (int) $order->get_id() : 0,
				]);

			case 'subscription_status_updated':
				$subscription = self::resolve_subscription_from_args( $args );
				if ( ! $subscription ) {
					return false;
				}
				return self::build_subscription_payload($subscription, [
					'new_status' => (string) ( $args[1] ?? '' ),
					'old_status' => (string) ( $args[2] ?? '' ),
				]);

			case 'subscription_payment_complete':
			case 'subscription_payment_failed':
				$subscription = self::resolve_subscription_from_args( $args );
				if ( ! $subscription ) {
					return false;
				}
				return self::build_subscription_payload( $subscription );

			case 'renewal_payment_complete':
			case 'renewal_payment_failed':
				$subscription = self::resolve_subscription_from_args( $args );
				if ( ! $subscription ) {
					return false;
				}
				$renewal_order = self::resolve_order_from_args( $args );
				return self::build_subscription_payload($subscription, [
					'renewal_order_id' => $renewal_order ? (int) $renewal_order->get_id() : 0,
				]);
		}//end switch

		return false;
	}

	public static function get_actions(): array {
		return [
			'create_subscription' => [ 'label' => 'Create Subscription' ],
			'get_subscriptions_all' => [ 'label' => 'Get Subscriptions (All)' ],
			'get_subscription_single' => [ 'label' => 'Get Subscription (Single)' ],
			'update_subscription_status' => [ 'label' => 'Update Subscription Status' ],
			'cancel_subscription' => [ 'label' => 'Cancel Subscription' ],
			'suspend_subscription' => [ 'label' => 'Suspend Subscription' ],
			'reactivate_subscription' => [ 'label' => 'Reactivate Subscription' ],
			'add_subscription_note' => [ 'label' => 'Add Subscription Note' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$status_options = self::get_subscription_status_options();

		$schemas = [
			'create_subscription' => [
				[
					'key' => 'customer_id',
					'label' => 'Customer ID',
					'type' => 'number',
				],
				[
					'key' => 'parent_order_id',
					'label' => 'Parent Order ID',
					'type' => 'number',
				],
				[
					'key' => 'subscription_status',
					'label' => 'Subscription Status',
					'type' => 'select',
					'options' => $status_options,
				],
				[
					'key' => 'billing_period',
					'label' => 'Billing Period',
					'type' => 'select',
					'options' => [
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
					'key' => 'billing_interval',
					'label' => 'Billing Interval',
					'type' => 'number',
				],
				[
					'key' => 'total',
					'label' => 'Total',
					'type' => 'expression',
				],
				[
					'key' => 'currency',
					'label' => 'Currency',
					'type' => 'text',
				],
				[
					'key' => 'start_date',
					'label' => 'Start Date (Y-m-d H:i:s)',
					'type' => 'expression',
				],
				[
					'key' => 'trial_end_date',
					'label' => 'Trial End Date (Y-m-d H:i:s)',
					'type' => 'expression',
				],
				[
					'key' => 'next_payment_date',
					'label' => 'Next Payment Date (Y-m-d H:i:s)',
					'type' => 'expression',
				],
				[
					'key' => 'end_date',
					'label' => 'End Date (Y-m-d H:i:s)',
					'type' => 'expression',
				],
			],
			'get_subscriptions_all' => [
				[
					'key' => 'limit',
					'label' => 'Limit',
					'type' => 'number',
					'default' => 20,
				],
				[
					'key' => 'page',
					'label' => 'Page',
					'type' => 'number',
					'default' => 1,
				],
				[
					'key' => 'subscription_status',
					'label' => 'Subscription Status',
					'type' => 'select',
					'options' => $status_options,
				],
			],
			'get_subscription_single' => [
				[
					'key' => 'subscription_id',
					'label' => 'Subscription ID',
					'type' => 'number',
					'required' => true,
				],
			],
			'update_subscription_status' => [
				[
					'key' => 'subscription_id',
					'label' => 'Subscription ID',
					'type' => 'number',
					'required' => true,
				],
				[
					'key' => 'subscription_status',
					'label' => 'Subscription Status',
					'type' => 'select',
					'options' => $status_options,
					'required' => true,
				],
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'text',
				],
			],
			'cancel_subscription' => [
				[
					'key' => 'subscription_id',
					'label' => 'Subscription ID',
					'type' => 'number',
					'required' => true,
				],
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'text',
				],
			],
			'suspend_subscription' => [
				[
					'key' => 'subscription_id',
					'label' => 'Subscription ID',
					'type' => 'number',
					'required' => true,
				],
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'text',
				],
			],
			'reactivate_subscription' => [
				[
					'key' => 'subscription_id',
					'label' => 'Subscription ID',
					'type' => 'number',
					'required' => true,
				],
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'text',
				],
			],
			'add_subscription_note' => [
				[
					'key' => 'subscription_id',
					'label' => 'Subscription ID',
					'type' => 'number',
					'required' => true,
				],
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'text',
					'required' => true,
				],
				[
					'key' => 'is_customer_note',
					'label' => 'Customer Note',
					'type' => 'boolean',
				],
			],
		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		if ( ! self::is_subscriptions_available() ) {
			return self::error_response( 'WooCommerce Subscriptions is not available', $input );
		}

		$event = (string) ( $node['data']['event'] ?? ( $node['config']['action'] ?? '' ) );
		$config = $node['data']['config'] ?? ( $node['config']['data'] ?? [] );
		if ( ! is_array( $config ) ) {
			$config = [];
		}

		switch ( $event ) {
			case 'create_subscription':
				return self::action_create_subscription( $config, $input );
			case 'get_subscriptions_all':
				return self::action_get_subscriptions_all( $config, $input );
			case 'get_subscription_single':
				return self::action_get_subscription_single( $config, $input );
			case 'update_subscription_status':
				return self::action_update_subscription_status( $config, $input );
			case 'cancel_subscription':
				return self::action_update_subscription_status( array_merge( $config, [ 'subscription_status' => 'wc_subscription_cancelled' ] ), $input );
			case 'suspend_subscription':
				return self::action_update_subscription_status( array_merge( $config, [ 'subscription_status' => 'wc_subscription_on-hold' ] ), $input );
			case 'reactivate_subscription':
				return self::action_update_subscription_status( array_merge( $config, [ 'subscription_status' => 'wc_subscription_active' ] ), $input );
			case 'add_subscription_note':
				return self::action_add_subscription_note( $config, $input );
		}//end switch

		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	private static function is_subscriptions_available(): bool {
		return function_exists( 'wcs_get_subscription' ) || class_exists( 'WC_Subscription' );
	}

	private static function action_create_subscription( array $config, array $input ): array {
		if ( ! function_exists( 'wcs_create_subscription' ) ) {
			return self::error_response( 'Subscription create API is not available', $input );
		}

		$create_args = [
			'status' => self::normalize_subscription_status( (string) ( $config['subscription_status'] ?? ( $config['status'] ?? 'active' ) ) ),
			'billing_period' => (string) ( $config['billing_period'] ?? 'month' ),
			'billing_interval' => max( 1, (int) ( $config['billing_interval'] ?? 1 ) ),
		];

		$customer_id = (int) ( $config['customer_id'] ?? 0 );
		$parent_order_id = (int) ( $config['parent_order_id'] ?? 0 );
		if ( $customer_id > 0 ) {
			$create_args['customer_id'] = $customer_id;
		}
		if ( $parent_order_id > 0 ) {
			$create_args['order_id'] = $parent_order_id;
		}

		$date_map = [
			'start_date' => 'start_date',
			'trial_end_date' => 'trial_end',
			'next_payment_date' => 'next_payment',
			'end_date' => 'end',
		];

		foreach ( $date_map as $config_key => $arg_key ) {
			$value = trim( (string) ( $config[ $config_key ] ?? '' ) );
			if ( '' !== $value ) {
				$create_args[ $arg_key ] = $value;
			}
		}

		try {
			$subscription = wcs_create_subscription( $create_args );
		} catch ( \Throwable $e ) {
			return self::error_response( $e->getMessage(), $input );
		}

		if ( ! $subscription || ! method_exists( $subscription, 'get_id' ) ) {
			return self::error_response( 'Failed to create subscription', $input );
		}

		if ( isset( $config['total'] ) && '' !== (string) $config['total'] && method_exists( $subscription, 'set_total' ) ) {
			$subscription->set_total( $config['total'] );
		}
		if ( isset( $config['currency'] ) && '' !== (string) $config['currency'] && method_exists( $subscription, 'set_currency' ) ) {
			$subscription->set_currency( (string) $config['currency'] );
		}
		if ( method_exists( $subscription, 'save' ) ) {
			$subscription->save();
		}

		return self::main_response( array_merge( $input, [
			'subscription' => self::build_subscription_payload( $subscription ),
		] ) );
	}

	private static function action_get_subscriptions_all( array $config, array $input ): array {
		$limit = max( 1, (int) ( $config['limit'] ?? 20 ) );
		$page = max( 1, (int) ( $config['page'] ?? 1 ) );
		$status = self::normalize_subscription_status( (string) ( $config['subscription_status'] ?? '' ) );

		$subscriptions = [];
		if ( function_exists( 'wcs_get_subscriptions' ) ) {
			$query = [
				'subscriptions_per_page' => $limit,
				'paged' => $page,
			];
			if ( '' !== $status ) {
				$query['subscription_status'] = $status;
			}
			$subscriptions = wcs_get_subscriptions( $query );
		}

		$items = [];
		if ( is_array( $subscriptions ) ) {
			foreach ( $subscriptions as $subscription ) {
				$resolved = self::resolve_subscription( $subscription );
				if ( ! $resolved ) {
					continue;
				}
				$items[] = self::build_subscription_payload( $resolved );
			}
		}

		return self::main_response( array_merge( $input, [
			'items' => $items,
			'total' => count( $items ),
			'page' => $page,
			'limit' => $limit,
		] ) );
	}

	private static function action_get_subscription_single( array $config, array $input ): array {
		$subscription_id = (int) ( $config['subscription_id'] ?? 0 );
		if ( $subscription_id <= 0 ) {
			return self::error_response( 'Subscription ID is required', $input );
		}

		$subscription = self::resolve_subscription( $subscription_id );
		if ( ! $subscription ) {
			return self::error_response( 'Subscription not found', $input );
		}

		return self::main_response( array_merge( $input, [
			'subscription' => self::build_subscription_payload( $subscription ),
		] ) );
	}

	private static function action_update_subscription_status( array $config, array $input ): array {
		$subscription_id = (int) ( $config['subscription_id'] ?? 0 );
		if ( $subscription_id <= 0 ) {
			return self::error_response( 'Subscription ID is required', $input );
		}

		$target_status = self::normalize_subscription_status( (string) ( $config['subscription_status'] ?? ( $config['status'] ?? '' ) ) );
		if ( '' === $target_status ) {
			return self::error_response( 'Subscription status is required', $input );
		}

		$subscription = self::resolve_subscription( $subscription_id );
		if ( ! $subscription ) {
			return self::error_response( 'Subscription not found', $input );
		}

		$old_status = method_exists( $subscription, 'get_status' ) ? (string) $subscription->get_status() : '';
		if ( '' !== $old_status && $old_status === $target_status ) {
			return self::main_response( array_merge( $input, [
				'subscription' => self::build_subscription_payload( $subscription, [
					'old_status' => $old_status,
					'new_status' => $target_status,
				] ),
			] ) );
		}

		if (
			method_exists( $subscription, 'can_be_updated_to' )
			&& ! $subscription->can_be_updated_to( $target_status )
		) {
			return self::error_response(
				sprintf(
					'Cannot change subscription status from "%s" to "%s"',
					$old_status,
					$target_status
				),
				$input
			);
		}

		$note = (string) ( $config['note'] ?? '' );

		try {
			if ( method_exists( $subscription, 'update_status' ) ) {
				$subscription->update_status( $target_status, $note );
			} elseif ( method_exists( $subscription, 'set_status' ) ) {
				$subscription->set_status( $target_status );
				if ( method_exists( $subscription, 'save' ) ) {
					$subscription->save();
				}
			} else {
				return self::error_response( 'Subscription status update API is not available', $input );
			}
		} catch ( \Throwable $e ) {
			return self::error_response( $e->getMessage(), $input );
		}

		return self::main_response( array_merge( $input, [
			'subscription' => self::build_subscription_payload( $subscription, [
				'old_status' => $old_status,
				'new_status' => $target_status,
			] ),
		] ) );
	}

	private static function action_add_subscription_note( array $config, array $input ): array {
		$subscription_id = (int) ( $config['subscription_id'] ?? 0 );
		if ( $subscription_id <= 0 ) {
			return self::error_response( 'Subscription ID is required', $input );
		}

		$note = trim( (string) ( $config['note'] ?? '' ) );
		if ( '' === $note ) {
			return self::error_response( 'Note is required', $input );
		}

		$subscription = self::resolve_subscription( $subscription_id );
		if ( ! $subscription ) {
			return self::error_response( 'Subscription not found', $input );
		}

		if ( ! method_exists( $subscription, 'add_order_note' ) ) {
			return self::error_response( 'Subscription note API is not available', $input );
		}

		$is_customer_note = in_array( $config['is_customer_note'] ?? false, [ true, 1, '1', 'true', 'yes', 'on' ], true );
		$subscription->add_order_note( $note, $is_customer_note );

		return self::main_response( array_merge( $input, [
			'subscription' => self::build_subscription_payload( $subscription ),
			'note' => $note,
			'is_customer_note' => $is_customer_note,
		] ) );
	}

	private static function resolve_subscription( $value ) {
		if (
			is_object( $value )
			&& method_exists( $value, 'get_id' )
			&& method_exists( $value, 'get_status' )
			&& method_exists( $value, 'get_billing_period' )
		) {
			return $value;
		}

		if ( ! is_numeric( $value ) ) {
			return null;
		}

		$subscription_id = (int) $value;
		if ( $subscription_id <= 0 ) {
			return null;
		}

		if ( function_exists( 'wcs_get_subscription' ) ) {
			$subscription = wcs_get_subscription( $subscription_id );
			if ( $subscription ) {
				return $subscription;
			}
		}

		if ( function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $subscription_id );
			if ( is_object( $order ) && method_exists( $order, 'get_billing_period' ) ) {
				return $order;
			}
		}

		return null;
	}

	private static function resolve_order( $value ) {
		if (
			is_object( $value )
			&& method_exists( $value, 'get_id' )
			&& ! method_exists( $value, 'get_billing_period' )
		) {
			return $value;
		}

		if ( ! is_numeric( $value ) || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order_id = (int) $value;
		if ( $order_id <= 0 ) {
			return null;
		}

		$order = wc_get_order( $order_id );
		if ( is_object( $order ) && ! method_exists( $order, 'get_billing_period' ) ) {
			return $order;
		}

		return null;
	}

	private static function resolve_subscription_from_args( array $args ) {
		foreach ( $args as $value ) {
			$subscription = self::resolve_subscription( $value );
			if ( $subscription ) {
				return $subscription;
			}
		}

		return null;
	}

	private static function resolve_order_from_args( array $args ) {
		foreach ( $args as $value ) {
			$order = self::resolve_order( $value );
			if ( $order ) {
				return $order;
			}
		}

		return null;
	}

	private static function build_subscription_payload( $subscription, array $extra = [] ): array {
		return array_merge([
			'subscription_id' => (int) $subscription->get_id(),
			'status' => (string) $subscription->get_status(),
			'customer_id' => method_exists( $subscription, 'get_customer_id' ) ? (int) $subscription->get_customer_id() : 0,
			'parent_order_id' => method_exists( $subscription, 'get_parent_id' ) ? (int) $subscription->get_parent_id() : 0,
			'total' => method_exists( $subscription, 'get_total' ) ? $subscription->get_total() : '',
			'currency' => method_exists( $subscription, 'get_currency' ) ? (string) $subscription->get_currency() : '',
			'billing_period' => method_exists( $subscription, 'get_billing_period' ) ? (string) $subscription->get_billing_period() : '',
			'billing_interval' => method_exists( $subscription, 'get_billing_interval' ) ? (int) $subscription->get_billing_interval() : 0,
			'start_date' => self::resolve_subscription_date( $subscription, 'start' ),
			'trial_end_date' => self::resolve_subscription_date( $subscription, 'trial_end' ),
			'next_payment_date' => self::resolve_subscription_date( $subscription, 'next_payment' ),
			'end_date' => self::resolve_subscription_date( $subscription, 'end' ),
		], $extra);
	}

	private static function normalize_subscription_status( string $status ): string {
		$status = sanitize_key( $status );
		if ( 0 === strpos( $status, 'wc_subscription_' ) ) {
			return substr( $status, strlen( 'wc_subscription_' ) );
		}

		return $status;
	}

	private static function get_subscription_status_options(): array {
		return [
			[
				'label' => 'Active',
				'value' => 'wc_subscription_active',
			],
			[
				'label' => 'Pending',
				'value' => 'wc_subscription_pending',
			],
			[
				'label' => 'On Hold',
				'value' => 'wc_subscription_on-hold',
			],
			[
				'label' => 'Cancelled',
				'value' => 'wc_subscription_cancelled',
			],
			[
				'label' => 'Pending Cancel',
				'value' => 'wc_subscription_pending-cancel',
			],
			[
				'label' => 'Expired',
				'value' => 'wc_subscription_expired',
			],
		];
	}

	private static function main_response( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	private static function error_response( string $message, array $input = [] ): array {
		return [
			'port' => 'error',
			'data' => array_merge( $input, [ 'error' => $message ] ),
		];
	}

	private static function resolve_subscription_date( $subscription, string $date_type ): string {
		if ( ! method_exists( $subscription, 'get_date' ) ) {
			return '';
		}

		$date = $subscription->get_date( $date_type );
		if ( is_string( $date ) ) {
			return $date;
		}

		if ( is_object( $date ) && method_exists( $date, 'date' ) ) {
			return (string) $date->date( 'Y-m-d H:i:s' );
		}

		return '';
	}
}
