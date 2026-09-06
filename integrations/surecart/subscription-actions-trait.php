<?php
namespace Zaplane\Integrations\Surecart;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait SubscriptionActionsTrait {

	protected static function action_create_subscription( array $config, array $input ): array {
		$error = self::ensure_surecart();
		if ( null !== $error ) {
			return $error;
		}

		$customer_id = trim( (string) ( $config['customer_id'] ?? '' ) );
		$price_id    = trim( (string) ( $config['price_id'] ?? '' ) );

		if ( '' === $customer_id ) {
			return self::error( 'Customer is required', [ 'field' => 'customer_id' ] );
		}
		if ( '' === $price_id ) {
			return self::error( 'Price is required', [ 'field' => 'price_id' ] );
		}

		$quantity = max( 1, (int) ( $config['quantity'] ?? 1 ) );

		// Look up the customer and pass email (like action_create_order does),
		// instead of passing the raw customer ID as 'customer' — that field may
		// not accept a bare ID on Checkout::create().
		$customer = \SureCart\Models\Customer::find( $customer_id );
		if ( is_wp_error( $customer ) || false === $customer || empty( $customer->email ) ) {
			return self::error( 'Could not find customer email for the selected customer', [ 'field' => 'customer_id' ] );
		}

		$checkout_data = [
			'email'      => $customer->email,
			'line_items' => [
				[
					'price'    => $price_id,
					'quantity' => $quantity,
				],
			],
		];

		if ( ! empty( $customer->name ) ) {
			$checkout_data['name'] = $customer->name;
		}

		$mode = self::normalize_mode( $config['mode'] ?? '' );
		if ( 'test' === $mode ) {
			$checkout_data['live_mode'] = false;
		} elseif ( 'live' === $mode ) {
			$checkout_data['live_mode'] = true;
		}

		$checkout = \SureCart\Models\Checkout::create( $checkout_data );

		if ( is_wp_error( $checkout ) ) {
			$message = $checkout->get_error_message();
			if ( 'test' === $mode ) {
				$message .= ' | Tip: Test mode requires Test-mode Price ID and Customer. Live resources will fail.';
			}

			return self::error( $message, [
				'code' => $checkout->get_error_code(),
			] );
		}
		if ( false === $checkout || empty( $checkout->id ) ) {
			return self::error( 'Failed to create checkout' );
		}

		return self::respond( [
			'checkout' => self::model_to_array( $checkout ),
			'message'  => 'Checkout created for recurring price. Subscription is created once checkout completes/is paid.',
		] );
	}

    protected static function action_update_subscription( array $config, array $input ): array {
        $data = [];

        if ( ! empty( $config['subscription_status'] ) ) {
            $data['status'] = $config['subscription_status'];
        }
        if ( isset( $config['quantity'] ) && '' !== $config['quantity'] ) {
            $data['quantity'] = max( 1, (int) $config['quantity'] );
        }

        return self::update_model( \SureCart\Models\Subscription::class, $config, 'subscription_id', $data, 'subscription' );
    }

    protected static function action_get_subscriptions_all( array $config, array $input ): array {
        return self::list_models( \SureCart\Models\Subscription::class, $config, 'subscriptions' );
    }

    protected static function action_get_subscription_single( array $config, array $input ): array {
        return self::get_model_single( \SureCart\Models\Subscription::class, $config, 'subscription_id', 'subscription' );
    }
}
