<?php

namespace Zaplane\Modules\AbandonedCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AbandonedCartRunner {

	public static function schedule_recurring(): void {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}
		if ( ! as_next_scheduled_action( 'zaplane_ab_cart_check_abandoned' ) ) {
			as_schedule_recurring_action( time(), 300, 'zaplane_ab_cart_check_abandoned', [], 'zaplane_abandoned_cart' );
		}
		if ( ! as_next_scheduled_action( 'zaplane_ab_cart_check_lost' ) ) {
			as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'zaplane_ab_cart_check_lost', [], 'zaplane_abandoned_cart' );
		}
	}

	public static function run_abandoned(): void {
		if ( ! AbandonedCartHelper::is_enabled() ) {
			return;
		}

		$settings       = AbandonedCartHelper::get_settings();
		$cart_off_time  = absint( $settings['cart_off_time'] );
		$threshold      = date( 'Y-m-d H:i:s', time() - ( $cart_off_time * MINUTE_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

		$carts = AbandonedCartModel::where( 'status', 'draft' )
			->where( 'updated_at', '<=', $threshold )
			->get();

		if ( ! $carts ) {
			return;
		}

		foreach ( $carts as $cart ) {
			self::process_abandoned_cart( $cart, $settings );
		}
	}

	protected static function process_abandoned_cart( AbandonedCartModel $cart, array $settings ): void {
		$user_id = (int) $cart->user_id;

		if ( $user_id ) {
			if ( AbandonedCartHelper::is_within_cool_off( $user_id ) ) {
				AbandonedCartModel::where( 'id', $cart->id )->update( [ 'status' => 'skipped' ] );
				return;
			}

			$user = get_userdata( $user_id );
			if ( $user ) {
				$roles    = (array) $user->roles;
				$primary  = ! empty( $roles ) ? $roles[0] : '';
				if ( $primary && ! AbandonedCartHelper::will_track_for_role( $primary ) ) {
					AbandonedCartModel::where( 'id', $cart->id )->update( [ 'status' => 'skipped' ] );
					return;
				}
			}
		}

		$email      = $cart->email;
		$first_name = '';
		$last_name  = '';

		if ( $cart->full_name ) {
			$name_parts = explode( ' ', $cart->full_name, 2 );
			$first_name = $name_parts[0];
			$last_name  = $name_parts[1] ?? '';
		}

		$contact_id = null;

		if ( $email ) {
			$contact_id = AbandonedCartHelper::create_or_update_gemcrm_contact(
				$email,
				$first_name,
				$last_name,
				$settings['status_of_new_contact']
			);

			if ( $contact_id ) {
				AbandonedCartHelper::apply_gemcrm_tags( $contact_id, $settings['abandoned_tags'] );
				AbandonedCartHelper::apply_gemcrm_lists( $contact_id, $settings['abandoned_list'] );
			}
		}

		$update = [
			'status'       => 'processing',
			'abandoned_at' => current_time( 'mysql' ),
		];
		if ( $contact_id ) {
			$update['contact_id'] = $contact_id;
		}

		AbandonedCartModel::where( 'id', $cart->id )->update( $update );

		$cart->status       = 'processing';
		$cart->abandoned_at = current_time( 'mysql' );
		$cart->contact_id   = $contact_id;

		do_action( 'zaplane/abandoned_cart/started', $cart );
	}

	public static function run_lost(): void {
		if ( ! AbandonedCartHelper::is_enabled() ) {
			return;
		}

		$settings  = AbandonedCartHelper::get_settings();
		$lost_mins = absint( $settings['mark_as_lost_after_minutes'] );
		$threshold = date( 'Y-m-d H:i:s', time() - ( $lost_mins * MINUTE_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

		$carts = AbandonedCartModel::where( 'status', 'processing' )
			->where( 'abandoned_at', '<=', $threshold )
			->get();

		if ( ! $carts ) {
			return;
		}

		foreach ( $carts as $cart ) {
			self::process_lost_cart( $cart, $settings );
		}
	}

	protected static function process_lost_cart( AbandonedCartModel $cart, array $settings ): void {
		$contact_id = (int) $cart->contact_id;

		if ( $contact_id ) {
			AbandonedCartHelper::apply_gemcrm_tags( $contact_id, $settings['lost_tags'] );
			AbandonedCartHelper::apply_gemcrm_lists( $contact_id, $settings['lost_list'] );
			AbandonedCartHelper::remove_gemcrm_abandoned_tags_lists( $contact_id, $settings );
		}

		AbandonedCartModel::where( 'id', $cart->id )->update( [ 'status' => 'lost' ] );
		$cart->status = 'lost';

		do_action( 'zaplane/abandoned_cart/lost', $cart );
	}
}
