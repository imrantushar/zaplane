<?php
namespace Zaplane\Integrations\Surecart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait OrderActionsTrait {

	protected static function action_create_order( array $config, array $input ): array {
		$error = self::ensure_surecart();
		if ( null !== $error ) {
			return $error;
		}

		$price_id = trim( (string) ( $config['price_id'] ?? '' ) );
		if ( '' === $price_id ) {
			return self::error( 'Price ID is required' );
		}

		$quantity = max( 1, (int) ( $config['quantity'] ?? 1 ) );

		$checkout_data = [
			'line_items' => [
				[
					'price'    => $price_id,
					'quantity' => $quantity,
				],
			],
		];

		// Email + Name
		$billing = self::build_billing_address( $config );
		if ( ! empty( $billing['email'] ) ) {
			$checkout_data['email'] = $billing['email'];
		}
		if ( ! empty( $billing['name'] ) ) {
			$checkout_data['name'] = $billing['name'];
		}

		// Mode
		$mode = self::normalize_mode( $config['mode'] ?? '' );
		if ( 'test' === $mode ) {
			$checkout_data['live_mode'] = false;
		} elseif ( 'live' === $mode ) {
			$checkout_data['live_mode'] = true;
		}

		$checkout = \SureCart\Models\Checkout::create( $checkout_data );

		if ( is_wp_error( $checkout ) ) {
			return self::error(
				$checkout->get_error_message(),
				[ 'code' => $checkout->get_error_code() ]
			);
		}

		if ( false === $checkout || empty( $checkout->id ) ) {
			return self::error( 'Failed to create checkout' );
		}

		return self::respond( [
			'checkout' => self::model_to_array( $checkout ),
			'status'   => $checkout->status ?? 'draft', // draft = pending
			'message'  => 'Order/Checkout created successfully with pending status.',
		] );
	}

	protected static function action_update_order( array $config, array $input ): array {
		$error = self::ensure_surecart();
		if ( null !== $error ) {
			return $error;
		}

		$order_id = trim( (string) ( $config['order_id'] ?? '' ) );
		if ( '' === $order_id ) {
			return self::error( 'Order ID is required', [ 'field' => 'order_id' ] );
		}

		$order = \SureCart\Models\Order::find( $order_id );

		if ( is_wp_error( $order ) ) {
			return self::error( $order->get_error_message(), [ 'code' => $order->get_error_code() ] );
		}
		if ( false === $order || empty( $order->id ) ) {
			return self::error( 'Order not found', [ 'order_id' => $order_id ] );
		}

		$order_status = trim( (string) ( $config['order_status'] ?? '' ) );

		// Get related checkout id
		$checkout_id = '';
		if ( ! empty( $order->checkout ) ) {
			$checkout_id = is_string( $order->checkout )
				? $order->checkout
				: ( is_object( $order->checkout ) ? ( $order->checkout->id ?? '' ) : '' );
		}

		if ( in_array( $order_status, [ 'paid', 'completed' ], true ) && '' !== $checkout_id ) {
			$checkout = \SureCart\Models\Checkout::find( $checkout_id );

			if ( ! is_wp_error( $checkout ) && $checkout && method_exists( $checkout, 'manually_pay' ) ) {
				$paid = $checkout->manually_pay();

				if ( is_wp_error( $paid ) ) {
					return self::error( $paid->get_error_message(), [ 'code' => $paid->get_error_code() ] );
				}

				$order = \SureCart\Models\Order::find( $order_id );

				return self::respond( [
					'order'   => self::model_to_array( $order ),
					'message' => 'Order marked as paid successfully.',
				] );
			}
		}

		if ( '' !== $order_status ) {
			return self::error(
				'Order status cannot be changed directly via API (except paid/completed).',
				[
					'current_status' => $order->status ?? null,
					'requested'      => $order_status,
					'order_id'       => $order_id,
				]
			);
		}

		$updated = [];

		if ( '' !== $checkout_id ) {
			$checkout_data = [ 'id' => $checkout_id ];

			// From billing_address repeater
			$billing = self::build_billing_address( $config );
			if ( ! empty( $billing['email'] ) ) {
				$checkout_data['email'] = $billing['email'];
			}
			if ( ! empty( $billing['name'] ) ) {
				$checkout_data['name'] = $billing['name'];
			}

			// Direct fields
			if ( ! empty( $config['email'] ) ) {
				$checkout_data['email'] = $config['email'];
			}
			if ( ! empty( $config['first_name'] ) || ! empty( $config['last_name'] ) ) {
				$name = trim( ( $config['first_name'] ?? '' ) . ' ' . ( $config['last_name'] ?? '' ) );
				if ( '' !== $name ) {
					$checkout_data['name'] = $name;
				}
			}

			// Only update if something to change
			if ( count( $checkout_data ) > 1 ) {
				$result = \SureCart\Models\Checkout::update( $checkout_data );

				if ( is_wp_error( $result ) ) {
					return self::error( $result->get_error_message(), [ 'code' => $result->get_error_code() ] );
				}
				if ( false === $result ) {
					return self::error( 'Checkout update failed' );
				}

				$updated['checkout'] = self::model_to_array( $result );
			}
		}

		// Also update Customer if customer_id given
		$customer_id = trim( (string) ( $config['customer_id'] ?? '' ) );
		if ( '' === $customer_id && ! empty( $order->customer ) ) {
			$customer_id = is_string( $order->customer )
				? $order->customer
				: ( is_object( $order->customer ) ? ( $order->customer->id ?? '' ) : '' );
		}

		if ( '' !== $customer_id ) {
			$customer_data = [ 'id' => $customer_id ];

			$billing = self::build_billing_address( $config );
			if ( ! empty( $billing['email'] ) ) {
				$customer_data['email'] = $billing['email'];
			}
			if ( ! empty( $billing['name'] ) ) {
				$customer_data['name'] = $billing['name'];
			}
			if ( ! empty( $config['email'] ) ) {
				$customer_data['email'] = $config['email'];
			}
			if ( ! empty( $config['first_name'] ) ) {
				$customer_data['first_name'] = $config['first_name'];
			}
			if ( ! empty( $config['last_name'] ) ) {
				$customer_data['last_name'] = $config['last_name'];
			}

			if ( count( $customer_data ) > 1 ) {
				$cust_result = \SureCart\Models\Customer::update( $customer_data );

				if ( ! is_wp_error( $cust_result ) && false !== $cust_result ) {
					$updated['customer'] = self::model_to_array( $cust_result );
				}
			}
		}

		if ( empty( $updated ) ) {
			return self::respond( [
				'order'   => self::model_to_array( $order ),
				'message' => 'Nothing to update.',
			] );
		}

		return self::respond( [
			'order'   => self::model_to_array( $order ),
			'updated' => $updated,
			'message' => 'Order related data updated successfully.',
		] );
	}

	protected static function action_get_orders_all( array $config, array $input ): array {
		return self::list_models( \SureCart\Models\Order::class, $config, 'orders' );
	}

	protected static function action_get_order_single( array $config, array $input ): array {
		return self::get_model_single( \SureCart\Models\Order::class, $config, 'order_id', 'order' );
	}
}
