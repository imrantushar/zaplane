<?php
namespace Zaplane\Integrations\Memberpress;

trait ActionsTrait {

	private static function action_success_with_input( array $input, array $extra = [] ): array {
		return self::action_success( array_merge( $input, $extra ) );
	}

	private static function require_transaction( int $transaction_id, array $input ) {
		$transaction = new \MeprTransaction( $transaction_id );
		if ( empty( $transaction->id ) ) {
			return self::action_error( 'Transaction not found', $input );
		}

		return $transaction;
	}

	private static function require_subscription( int $subscription_id, array $input ) {
		$subscription = new \MeprSubscription( $subscription_id );
		if ( empty( $subscription->id ) ) {
			return self::action_error( 'Subscription not found', $input );
		}

		return $subscription;
	}

	protected static function action_create_member( array $config, array $input ): array {
		if ( ! class_exists( 'MeprUser' ) ) {
			return self::action_error( 'MemberPress is not available', $input );
		}

		$email = trim( $config['email'] ?? '' );
		if ( '' === $email ) {
			return self::action_error( 'Email is required', $input );
		}

		try {
			$user = new \MeprUser();
			$user->user_email = $email;

			$username = trim( $config['username'] ?? '' );
			if ( '' === $username ) {
				$username = $email;
			}
			$user->user_login = function_exists( 'sanitize_user' ) ? sanitize_user( $username, true ) : $username;

			if ( ! empty( $config['password'] ) ) {
				$user->user_pass = $config['password'];
			} elseif ( function_exists( 'wp_generate_password' ) ) {
				$user->user_pass = wp_generate_password( 20, true, true );
			}

			if ( ! empty( $config['first_name'] ) ) {
				$user->first_name = $config['first_name'];
			}

			if ( ! empty( $config['last_name'] ) ) {
				$user->last_name = $config['last_name'];
			}

			$user_id = $user->store();
		} catch ( \Throwable $e ) {
			return self::action_error( $e->getMessage(), $input );
		}//end try

		if ( empty( $user_id ) ) {
			return self::action_error( 'Failed to create member', $input );
		}

		return self::action_success_with_input( $input, [
			'user_id' => (int) $user_id,
			'email' => $email,
		] );
	}

	protected static function action_create_membership( array $config, array $input ): array {
		if ( ! class_exists( 'MeprProduct' ) ) {
			return self::action_error( 'MemberPress is not available', $input );
		}

		$name = trim( $config['name'] ?? '' );
		$price = $config['price'] ?? '';

		if ( '' === $name || '' === $price ) {
			return self::action_error( 'Membership name and price are required', $input );
		}

		try {
			$product = new \MeprProduct();
			$product->post_title = $name;
			if ( array_key_exists( 'description', $config ) ) {
				$product->post_content = $config['description'];
			}
			$membership_status = self::get_membership_status_config( $config );
			if ( '' !== $membership_status ) {
				$product->post_status = self::normalize_post_status( $membership_status );
			}

			$product->price = (float) $price;

			if ( isset( $config['period'] ) && '' !== $config['period'] ) {
				$product->period = (int) $config['period'];
			}
			if ( ! empty( $config['period_type'] ) ) {
				$product->period_type = $config['period_type'];
			}
			if ( array_key_exists( 'trial', $config ) ) {
				$product->trial = self::to_bool( $config['trial'] );
			}
			if ( isset( $config['trial_days'] ) && '' !== $config['trial_days'] ) {
				$product->trial_days = (int) $config['trial_days'];
			}
			if ( isset( $config['trial_amount'] ) && '' !== $config['trial_amount'] ) {
				$product->trial_amount = (float) $config['trial_amount'];
			}
			if ( ! empty( $config['expire_type'] ) ) {
				$product->expire_type = $config['expire_type'];
			}
			if ( isset( $config['expire_after'] ) && '' !== $config['expire_after'] ) {
				$product->expire_after = (int) $config['expire_after'];
			}
			if ( ! empty( $config['expire_unit'] ) ) {
				$product->expire_unit = $config['expire_unit'];
			}
			if ( ! empty( $config['expire_fixed'] ) ) {
				$product->expire_fixed = $config['expire_fixed'];
			}
			if ( array_key_exists( 'allow_renewal', $config ) ) {
				$product->allow_renewal = self::to_bool( $config['allow_renewal'] );
			}
			if ( ! empty( $config['pricing_display'] ) ) {
				$product->pricing_display = $config['pricing_display'];
			}
			if ( array_key_exists( 'pricing_title', $config ) ) {
				$product->pricing_title = $config['pricing_title'];
			}
			if ( array_key_exists( 'pricing_heading_text', $config ) ) {
				$product->pricing_heading_txt = $config['pricing_heading_text'];
			}
			if ( array_key_exists( 'pricing_footer_text', $config ) ) {
				$product->pricing_footer_txt = $config['pricing_footer_text'];
			}
			if ( array_key_exists( 'pricing_button_text', $config ) ) {
				$product->pricing_button_txt = $config['pricing_button_text'];
			}
			if ( array_key_exists( 'pricing_button_position', $config ) ) {
				$product->pricing_button_position = $config['pricing_button_position'];
			}

			$membership_id = $product->store();
		} catch ( \Throwable $e ) {
			return self::action_error( $e->getMessage(), $input );
		}//end try

		if ( empty( $membership_id ) ) {
			return self::action_error( 'Failed to create membership', $input );
		}

		return self::action_success_with_input( $input, [
			'membership_id' => (int) $membership_id,
			'membership_name' => $name,
		] );
	}

	protected static function action_update_membership( array $config, array $input ): array {
		if ( ! class_exists( 'MeprProduct' ) ) {
			return self::action_error( 'MemberPress is not available', $input );
		}

		$membership_id = (int) ( $config['membership_id'] ?? 0 );
		if ( ! $membership_id ) {
			return self::action_error( 'Membership ID is required', $input );
		}

		$product = new \MeprProduct( $membership_id );
		if ( empty( $product->ID ) ) {
			return self::action_error( 'Membership not found', $input );
		}

		if ( ! empty( $config['name'] ) ) {
			$product->post_title = $config['name'];
		}
		if ( array_key_exists( 'description', $config ) ) {
			$product->post_content = $config['description'];
		}
		$membership_status = self::get_membership_status_config( $config );
		if ( '' !== $membership_status ) {
			$product->post_status = self::normalize_post_status( $membership_status );
		}
		if ( isset( $config['price'] ) && '' !== $config['price'] ) {
			$product->price = (float) $config['price'];
		}
		if ( isset( $config['period'] ) && '' !== $config['period'] ) {
			$product->period = (int) $config['period'];
		}
		if ( ! empty( $config['period_type'] ) ) {
			$product->period_type = $config['period_type'];
		}
		if ( array_key_exists( 'trial', $config ) ) {
			$product->trial = self::to_bool( $config['trial'] );
		}
		if ( isset( $config['trial_days'] ) && '' !== $config['trial_days'] ) {
			$product->trial_days = (int) $config['trial_days'];
		}
		if ( isset( $config['trial_amount'] ) && '' !== $config['trial_amount'] ) {
			$product->trial_amount = (float) $config['trial_amount'];
		}
		if ( ! empty( $config['expire_type'] ) ) {
			$product->expire_type = $config['expire_type'];
		}
		if ( isset( $config['expire_after'] ) && '' !== $config['expire_after'] ) {
			$product->expire_after = (int) $config['expire_after'];
		}
		if ( ! empty( $config['expire_unit'] ) ) {
			$product->expire_unit = $config['expire_unit'];
		}
		if ( ! empty( $config['expire_fixed'] ) ) {
			$product->expire_fixed = $config['expire_fixed'];
		}
		if ( array_key_exists( 'allow_renewal', $config ) ) {
			$product->allow_renewal = self::to_bool( $config['allow_renewal'] );
		}
		if ( ! empty( $config['pricing_display'] ) ) {
			$product->pricing_display = $config['pricing_display'];
		}
		if ( array_key_exists( 'pricing_title', $config ) ) {
			$product->pricing_title = $config['pricing_title'];
		}
		if ( array_key_exists( 'pricing_heading_text', $config ) ) {
			$product->pricing_heading_txt = $config['pricing_heading_text'];
		}
		if ( array_key_exists( 'pricing_footer_text', $config ) ) {
			$product->pricing_footer_txt = $config['pricing_footer_text'];
		}
		if ( array_key_exists( 'pricing_button_text', $config ) ) {
			$product->pricing_button_txt = $config['pricing_button_text'];
		}
		if ( array_key_exists( 'pricing_button_position', $config ) ) {
			$product->pricing_button_position = $config['pricing_button_position'];
		}

		try {
			$product->store();
		} catch ( \Throwable $e ) {
			return self::action_error( $e->getMessage(), $input );
		}

		return self::action_success_with_input( $input, [
			'membership_id' => $membership_id,
		] );
	}

	protected static function action_create_transaction( array $config, array $input ): array {
		if ( ! class_exists( 'MeprTransaction' ) ) {
			return self::action_error( 'MemberPress is not available', $input );
		}

		$user_id = (int) ( $config['user_id'] ?? 0 );
		$product_id = (int) ( $config['product_id'] ?? 0 );
		$amount = $config['amount'] ?? '';

		if ( ! $user_id || ! $product_id || '' === $amount ) {
			return self::action_error( 'User ID, membership ID and amount are required', $input );
		}

		try {
			$txn = new \MeprTransaction();
			$txn->user_id = $user_id;
			$txn->product_id = $product_id;
			$txn->amount = (float) $amount;
			$txn->total = isset( $config['total'] ) && '' !== $config['total'] ? (float) $config['total'] : (float) $amount;
			$transaction_status = self::get_transaction_status_config( $config );
			$txn->status = '' !== $transaction_status ? self::normalize_transaction_status( $transaction_status ) : \MeprTransaction::$complete_str;
			$txn->gateway = $config['gateway'] ?? \MeprTransaction::$manual_gateway_str;

			if ( ! empty( $config['subscription_id'] ) ) {
				$txn->subscription_id = (int) $config['subscription_id'];
			}

			$txn_id = $txn->store();
		} catch ( \Throwable $e ) {
			return self::action_error( $e->getMessage(), $input );
		}

		if ( empty( $txn_id ) ) {
			return self::action_error( 'Failed to create transaction', $input );
		}

		return self::action_success_with_input( $input, [
			'transaction_id' => (int) $txn_id,
			'user_id' => $user_id,
			'membership_id' => $product_id,
			'amount' => (float) $amount,
		] );
	}

	protected static function action_update_transaction_status( array $config, array $input ): array {
		if ( ! class_exists( 'MeprTransaction' ) ) {
			return self::action_error( 'MemberPress is not available', $input );
		}

		$txn_id = (int) ( $config['transaction_id'] ?? 0 );
		$transaction_status = self::get_transaction_status_config( $config );
		$status = '' !== $transaction_status ? self::normalize_transaction_status( $transaction_status ) : '';

		if ( ! $txn_id || '' === $status ) {
			return self::action_error( 'Transaction ID and status are required', $input );
		}

		$txn = self::require_transaction( $txn_id, $input );
		if ( is_array( $txn ) ) {
			return $txn;
		}

		$txn->status = $status;
		$txn->store();

		return self::action_success_with_input( $input, [
			'transaction_id' => $txn_id,
			'status' => $status,
		] );
	}

	protected static function action_refund_transaction( array $config, array $input ): array {
		if ( ! class_exists( 'MeprTransaction' ) ) {
			return self::action_error( 'MemberPress is not available', $input );
		}

		$txn_id = (int) ( $config['transaction_id'] ?? 0 );
		if ( ! $txn_id ) {
			return self::action_error( 'Transaction ID is required', $input );
		}

		$txn = self::require_transaction( $txn_id, $input );
		if ( is_array( $txn ) ) {
			return $txn;
		}

		$refunded = $txn->refund();
		if ( ! $refunded ) {
			return self::action_error( 'Failed to refund transaction', $input );
		}

		return self::action_success_with_input( $input, [
			'transaction_id' => $txn_id,
			'refunded' => true,
		] );
	}

	protected static function action_create_subscription( array $config, array $input ): array {
		if ( ! class_exists( 'MeprSubscription' ) ) {
			return self::action_error( 'MemberPress is not available', $input );
		}

		$user_id = (int) ( $config['user_id'] ?? 0 );
		$product_id = (int) ( $config['product_id'] ?? 0 );
		$price = $config['price'] ?? '';

		if ( ! $user_id || ! $product_id || '' === $price ) {
			return self::action_error( 'User ID, membership ID and price are required', $input );
		}

		$sub = new \MeprSubscription();
		$sub->user_id = $user_id;
		$sub->product_id = $product_id;
		$sub->price = (float) $price;
		$sub->period = (int) ( $config['period'] ?? 1 );
		$sub->period_type = $config['period_type'] ?? 'months';
		$subscription_status = self::get_subscription_status_config( $config );
		$sub->status = '' !== $subscription_status ? self::normalize_subscription_status( $subscription_status ) : \MeprSubscription::$active_str;
		$sub->gateway = $config['gateway'] ?? 'manual';

		$sub_id = $sub->store();

		if ( empty( $sub_id ) ) {
			return self::action_error( 'Failed to create subscription', $input );
		}

		return self::action_success_with_input( $input, [
			'subscription_id' => (int) $sub_id,
			'user_id' => $user_id,
			'membership_id' => $product_id,
		] );
	}

	protected static function action_update_subscription_status( array $config, array $input ): array {
		if ( ! class_exists( 'MeprSubscription' ) ) {
			return self::action_error( 'MemberPress is not available', $input );
		}

		$sub_id = (int) ( $config['subscription_id'] ?? 0 );
		$subscription_status = self::get_subscription_status_config( $config );
		$status = '' !== $subscription_status ? self::normalize_subscription_status( $subscription_status ) : '';

		if ( ! $sub_id || '' === $status ) {
			return self::action_error( 'Subscription ID and status are required', $input );
		}

		$sub = self::require_subscription( $sub_id, $input );
		if ( is_array( $sub ) ) {
			return $sub;
		}

		$sub->status = $status;
		$sub->store();

		return self::action_success_with_input( $input, [
			'subscription_id' => $sub_id,
			'status' => $status,
		] );
	}

	protected static function action_cancel_subscription( array $config, array $input ): array {
		if ( ! class_exists( 'MeprSubscription' ) ) {
			return self::action_error( 'MemberPress is not available', $input );
		}

		$sub_id = (int) ( $config['subscription_id'] ?? 0 );
		if ( ! $sub_id ) {
			return self::action_error( 'Subscription ID is required', $input );
		}

		$sub = self::require_subscription( $sub_id, $input );
		if ( is_array( $sub ) ) {
			return $sub;
		}

		$cancelled = $sub->cancel();
		if ( ! $cancelled ) {
			return self::action_error( 'Failed to cancel subscription', $input );
		}

		return self::action_success_with_input( $input, [
			'subscription_id' => $sub_id,
			'cancelled' => true,
		] );
	}

	protected static function action_suspend_subscription( array $config, array $input ): array {
		if ( ! class_exists( 'MeprSubscription' ) ) {
			return self::action_error( 'MemberPress is not available', $input );
		}

		$sub_id = (int) ( $config['subscription_id'] ?? 0 );
		if ( ! $sub_id ) {
			return self::action_error( 'Subscription ID is required', $input );
		}

		$sub = self::require_subscription( $sub_id, $input );
		if ( is_array( $sub ) ) {
			return $sub;
		}

		$suspended = $sub->suspend();
		if ( ! $suspended ) {
			return self::action_error( 'Failed to suspend subscription', $input );
		}

		return self::action_success_with_input( $input, [
			'subscription_id' => $sub_id,
			'suspended' => true,
		] );
	}

	protected static function action_resume_subscription( array $config, array $input ): array {
		if ( ! class_exists( 'MeprSubscription' ) ) {
			return self::action_error( 'MemberPress is not available', $input );
		}

		$sub_id = (int) ( $config['subscription_id'] ?? 0 );
		if ( ! $sub_id ) {
			return self::action_error( 'Subscription ID is required', $input );
		}

		$sub = self::require_subscription( $sub_id, $input );
		if ( is_array( $sub ) ) {
			return $sub;
		}

		$resumed = $sub->resume();
		if ( ! $resumed ) {
			return self::action_error( 'Failed to resume subscription', $input );
		}

		return self::action_success_with_input( $input, [
			'subscription_id' => $sub_id,
			'resumed' => true,
		] );
	}

	protected static function action_error( string $message, array $input = [] ): array {
		return [
			'port' => 'main',
			'data' => array_merge($input, [
				'error' => $message,
			]),
		];
	}

	protected static function action_success( array $data = [] ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}
}
