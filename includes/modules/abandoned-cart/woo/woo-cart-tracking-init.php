<?php

namespace Zaplane\Modules\AbandonedCart\Woo;

use Zaplane\Modules\AbandonedCart\AbandonedCartModel;
use Zaplane\Modules\AbandonedCart\AbandonedCartHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WooCartTrackingInit {

	const CART_COOKIE  = 'zaplane_ab_cart_token';
	const SKIP_COOKIE  = 'zaplane_ab_cart_skip';
	const AJAX_UPDATE  = 'zaplane_ab_cart_update';
	const AJAX_OPTOUT  = 'zaplane_ab_cart_optout';

	public function register(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'woocommerce_after_checkout_form', [ $this, 'enqueue_tracking_script' ] );
		add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_after', [ $this, 'enqueue_tracking_script' ] );

		add_action( 'wc_ajax_' . self::AJAX_UPDATE, [ $this, 'handle_ajax_update_cart' ] );
		add_action( 'wc_ajax_' . self::AJAX_OPTOUT, [ $this, 'handle_ajax_optout' ] );

		add_action( 'woocommerce_checkout_order_processed', [ $this, 'link_order_to_cart' ], 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'link_order_to_cart_object' ], 10, 1 );
		add_action( 'woocommerce_order_status_changed', [ $this, 'handle_order_status_changed' ], 10, 4 );

		add_action( 'template_redirect', [ $this, 'handle_recovery_link' ] );
	}

	public function enqueue_tracking_script(): void {
		if ( ! AbandonedCartHelper::is_enabled() ) {
			return;
		}
		if ( ! is_checkout() ) {
			return;
		}
		if ( isset( $_COOKIE[ self::SKIP_COOKIE ] ) ) {
			return;
		}

		wp_enqueue_script(
			'zaplane-ab-cart-woo',
			ZAPLANE_ASSETS_URI . 'js/abandoned-cart-woo.js',
			[ 'jquery' ],
			ZAPLANE_VERSION,
			true
		);

		$settings = AbandonedCartHelper::get_settings();

		wp_localize_script( 'zaplane-ab-cart-woo', 'ZaplaneAbCart', [
			'wc_ajaxurl'       => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'nonce'            => wp_create_nonce( 'zaplane_ab_cart_nonce' ),
			'gdpr_message'     => $settings['gdpr_msg'],
			'is_gdpr_enabled'  => $settings['gdpr_consent_in_woo_checkout_page'] ? '1' : '0',
			'update_action'    => self::AJAX_UPDATE,
			'optout_action'    => self::AJAX_OPTOUT,
		] );
	}

	public function handle_ajax_update_cart(): void {
		check_ajax_referer( 'zaplane_ab_cart_nonce', 'nonce' );

		if ( ! AbandonedCartHelper::is_enabled() ) {
			wp_send_json_success( [] );
		}

		$billing_email = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! is_email( $billing_email ) ) {
			wp_send_json_error( [ 'message' => 'Invalid email' ] );
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$billing_address  = isset( $_POST['billingAddress'] ) ? (array) json_decode( sanitize_text_field( wp_unslash( $_POST['billingAddress'] ) ), true ) : [];
		$shipping_address = isset( $_POST['shippingAddress'] ) ? (array) json_decode( sanitize_text_field( wp_unslash( $_POST['shippingAddress'] ) ), true ) : [];
		$order_note       = isset( $_POST['order_comments'] ) ? sanitize_textarea_field( wp_unslash( $_POST['order_comments'] ) ) : '';
		// phpcs:enable

		$first_name = sanitize_text_field( $billing_address['first_name'] ?? '' );
		$last_name  = sanitize_text_field( $billing_address['last_name'] ?? '' );
		$full_name  = trim( $first_name . ' ' . $last_name );

		$cart_data  = $this->build_cart_data( $billing_address, $shipping_address );
		$cart_total = WC()->cart->get_total( 'edit' );
		$currency   = get_woocommerce_currency();

		$checkout_key = $this->get_or_create_checkout_key();

		$existing = AbandonedCartModel::where( 'checkout_key', $checkout_key )->first();

		$record = [
			'checkout_key'    => $checkout_key,
			'cart_hash'       => WC()->cart->get_cart_hash(),
			'full_name'       => $full_name,
			'email'           => $billing_email,
			'user_id'         => get_current_user_id() ?: null,
			'subtotal'        => WC()->cart->get_subtotal(),
			'shipping'        => WC()->cart->get_shipping_total(),
			'tax'             => WC()->cart->get_total_tax(),
			'discounts'       => WC()->cart->get_discount_total(),
			'fees'            => WC()->cart->get_fee_total(),
			'total'           => $cart_total,
			'currency'        => $currency,
			'cart'            => wp_json_encode( $cart_data ),
			'note'            => $order_note,
			'status'          => 'draft',
			'updated_at'      => current_time( 'mysql' ),
		];

		if ( $existing ) {
			if ( in_array( $existing->status, [ 'recovered', 'lost', 'opt_out' ], true ) ) {
				wp_send_json_success( [ 'status' => $existing->status ] );
			}
			AbandonedCartModel::where( 'id', $existing->id )->update( $record );
		} else {
			$record['created_at'] = current_time( 'mysql' );
			$record['provider']   = 'woo';
			AbandonedCartModel::create( $record );
		}

		wp_send_json_success( [ 'checkout_key' => $checkout_key ] );
	}

	public function handle_ajax_optout(): void {
		check_ajax_referer( 'zaplane_ab_cart_nonce', 'nonce' );

		$checkout_key = isset( $_COOKIE[ self::CART_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::CART_COOKIE ] ) ) : '';

		if ( $checkout_key ) {
			AbandonedCartModel::where( 'checkout_key', $checkout_key )
				->update( [
					'is_optout'  => 1,
					'status'     => 'opt_out',
					'updated_at' => current_time( 'mysql' ),
				] );
		}

		setcookie( self::SKIP_COOKIE, '1', time() + ( 7 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN );

		wp_send_json_success( [] );
	}

	public function link_order_to_cart( int $order_id ): void {
		$checkout_key = isset( $_COOKIE[ self::CART_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::CART_COOKIE ] ) ) : '';
		if ( ! $checkout_key ) {
			return;
		}
		AbandonedCartModel::where( 'checkout_key', $checkout_key )
			->where( 'status', 'draft' )
			->update( [
				'order_id'   => $order_id,
				'updated_at' => current_time( 'mysql' ),
			] );

		setcookie( self::CART_COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
	}

	public function link_order_to_cart_object( $order ): void {
		if ( $order && method_exists( $order, 'get_id' ) ) {
			$this->link_order_to_cart( $order->get_id() );
		}
	}

	public function handle_order_status_changed( int $order_id, string $old_status, string $new_status, $order ): void {
		$settings        = AbandonedCartHelper::get_settings();
		$recover_statuses = (array) ( $settings['mark_as_recovered_when_order_status_changed_to'] ?? [ 'processing', 'completed' ] );

		$cart = AbandonedCartModel::where( 'order_id', $order_id )->first();
		if ( ! $cart ) {
			return;
		}

		if ( in_array( $new_status, $recover_statuses, true ) ) {
			AbandonedCartModel::where( 'id', $cart->id )->update( [
				'status'       => 'recovered',
				'recovered_at' => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			] );

			if ( $cart->contact_id ) {
				AbandonedCartHelper::remove_gemcrm_abandoned_tags_lists( (int) $cart->contact_id, $settings );
			}

			$cart->status       = 'recovered';
			$cart->recovered_at = current_time( 'mysql' );

			do_action( 'zaplane/abandoned_cart/recovered', $cart, $order );
			return;
		}

		if ( in_array( $new_status, [ 'cancelled', 'refunded', 'failed' ], true ) ) {
			AbandonedCartModel::where( 'id', $cart->id )->update( [
				'status'     => 'lost',
				'updated_at' => current_time( 'mysql' ),
			] );

			if ( $cart->contact_id ) {
				AbandonedCartHelper::apply_gemcrm_tags( (int) $cart->contact_id, $settings['lost_tags'] );
				AbandonedCartHelper::apply_gemcrm_lists( (int) $cart->contact_id, $settings['lost_list'] );
			}

			$cart->status = 'lost';
			do_action( 'zaplane/abandoned_cart/lost', $cart );
		}
	}

	public function handle_recovery_link(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['zaplane'] ) || empty( $_GET['route'] ) || empty( $_GET['checkout_key'] ) ) {
			return;
		}
		if ( $_GET['zaplane'] !== '1' || $_GET['route'] !== 'abandoned-cart' ) {
			return;
		}
		// phpcs:enable

		$checkout_key = sanitize_text_field( wp_unslash( $_GET['checkout_key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$cart_record = AbandonedCartModel::where( 'checkout_key', $checkout_key )
			->where( 'status', 'processing' )
			->first();

		if ( ! $cart_record ) {
			wp_redirect( wc_get_checkout_url() );
			exit;
		}

		$cart_data = $cart_record->get_cart_contents();

		WC()->cart->empty_cart();

		if ( ! empty( $cart_data['cart_contents'] ) ) {
			foreach ( $cart_data['cart_contents'] as $item ) {
				$product_id   = absint( $item['product_id'] ?? 0 );
				$quantity     = absint( $item['quantity'] ?? 1 );
				$variation_id = absint( $item['variation_id'] ?? 0 );
				$variation    = (array) ( $item['variation'] ?? [] );

				if ( $product_id ) {
					WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
				}
			}
		}

		if ( ! empty( $cart_data['coupons'] ) ) {
			foreach ( (array) $cart_data['coupons'] as $coupon ) {
				WC()->cart->apply_coupon( sanitize_text_field( $coupon ) );
			}
		}

		if ( isset( $cart_data['customer_data'] ) ) {
			$customer_data = $cart_data['customer_data'];
			foreach ( $customer_data as $key => $value ) {
				WC()->customer->{"set_$key"}( sanitize_text_field( $value ) );
			}
		}

		AbandonedCartModel::where( 'id', $cart_record->id )->update( [
			'click_counts' => (int) $cart_record->click_counts + 1,
			'updated_at'   => current_time( 'mysql' ),
		] );

		setcookie( self::CART_COOKIE, $checkout_key, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN );

		wp_redirect( wc_get_checkout_url() );
		exit;
	}

	protected function get_or_create_checkout_key(): string {
		if ( isset( $_COOKIE[ self::CART_COOKIE ] ) && ! empty( $_COOKIE[ self::CART_COOKIE ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ self::CART_COOKIE ] ) );
		}
		$key = wp_generate_uuid4();
		setcookie( self::CART_COOKIE, $key, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN );
		return $key;
	}

	protected function build_cart_data( array $billing, array $shipping ): array {
		$cart_contents = [];
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];
			$cart_contents[] = [
				'product_id'   => $cart_item['product_id'],
				'variation_id' => $cart_item['variation_id'],
				'quantity'     => $cart_item['quantity'],
				'variation'    => $cart_item['variation'] ?? [],
				'name'         => $product ? $product->get_name() : '',
				'price'        => $product ? $product->get_price() : 0,
				'image'        => $product ? wp_get_attachment_url( $product->get_image_id() ) : '',
				'sku'          => $product ? $product->get_sku() : '',
			];
		}

		$coupons = array_keys( WC()->cart->get_applied_coupons() );

		return [
			'cart_contents' => $cart_contents,
			'coupons'       => $coupons,
			'customer_data' => [
				'billing_first_name'  => sanitize_text_field( $billing['first_name'] ?? '' ),
				'billing_last_name'   => sanitize_text_field( $billing['last_name'] ?? '' ),
				'billing_email'       => sanitize_email( $billing['email'] ?? '' ),
				'billing_phone'       => sanitize_text_field( $billing['phone'] ?? '' ),
				'billing_address_1'   => sanitize_text_field( $billing['address_1'] ?? '' ),
				'billing_address_2'   => sanitize_text_field( $billing['address_2'] ?? '' ),
				'billing_city'        => sanitize_text_field( $billing['city'] ?? '' ),
				'billing_state'       => sanitize_text_field( $billing['state'] ?? '' ),
				'billing_postcode'    => sanitize_text_field( $billing['postcode'] ?? '' ),
				'billing_country'     => sanitize_text_field( $billing['country'] ?? '' ),
				'shipping_first_name' => sanitize_text_field( $shipping['first_name'] ?? '' ),
				'shipping_last_name'  => sanitize_text_field( $shipping['last_name'] ?? '' ),
				'shipping_address_1'  => sanitize_text_field( $shipping['address_1'] ?? '' ),
				'shipping_address_2'  => sanitize_text_field( $shipping['address_2'] ?? '' ),
				'shipping_city'       => sanitize_text_field( $shipping['city'] ?? '' ),
				'shipping_state'      => sanitize_text_field( $shipping['state'] ?? '' ),
				'shipping_postcode'   => sanitize_text_field( $shipping['postcode'] ?? '' ),
				'shipping_country'    => sanitize_text_field( $shipping['country'] ?? '' ),
			],
		];
	}
}
