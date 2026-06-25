<?php
namespace Zaplane\Integrations\Woo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CouponActionsTrait {

	private static function action_create_coupon( array $config, array $input ): array {
		$is_birthday = ! empty( $input['_zaplane_birthday'] );
		$contact_id  = (int) ( $input['contact_id'] ?? 0 );
		$code        = $config['code'] ?? '';

		if ( '' === $code ) {
			if ( $is_birthday && $contact_id > 0 ) {
				$code = 'BDAY-' . $contact_id . '-' . wp_generate_password( 4, false );
			} else {
				return self::error( 'Coupon code is required' );
			}
		}

		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		if ( ! empty( $config['discount_type'] ) ) {
			$coupon->set_discount_type( $config['discount_type'] );
		}
		if ( isset( $config['amount'] ) ) {
			$coupon->set_amount( (string) $config['amount'] );
		}
		if ( isset( $config['usage_limit'] ) ) {
			$coupon->set_usage_limit( (int) $config['usage_limit'] );
		}
		if ( ! empty( $config['expiry_date'] ) ) {
			$coupon->set_date_expires( $config['expiry_date'] );
		}
		$emails = self::parse_list( $config['email_restrictions'] ?? [] );
		if ( ! empty( $emails ) ) {
			$coupon->set_email_restrictions( $emails );
		}
		$product_ids = self::parse_list( $config['product_ids'] ?? [] );
		if ( ! empty( $product_ids ) ) {
			$coupon->set_product_ids( array_map( 'intval', $product_ids ) );
		}
		$exclude_product_ids = self::parse_list( $config['exclude_product_ids'] ?? [] );
		if ( ! empty( $exclude_product_ids ) ) {
			$coupon->set_excluded_product_ids( array_map( 'intval', $exclude_product_ids ) );
		}

		$coupon_id = $coupon->save();
		if ( ! $coupon_id ) {
			return self::error( 'Failed to create coupon' );
		}

		if ( $is_birthday && $contact_id > 0 ) {
			update_post_meta( $coupon_id, '_zaplane_birthday_contact_id', $contact_id );
		}

		return self::respond([
			'coupon' => self::build_coupon_payload( $coupon ),
		]);
	}

	private static function action_update_coupon_data( array $config, array $input ): array {
		$coupon = self::get_coupon_from_config( $config );
		if ( ! $coupon ) {
			return self::error( 'Coupon not found' );
		}

		$data = self::parse_json_array( $config['data'] ?? [] );
		if ( ! empty( $data ) && method_exists( $coupon, 'set_props' ) ) {
			$coupon->set_props( $data );
		}

		if ( ! empty( $config['discount_type'] ) ) {
			$coupon->set_discount_type( $config['discount_type'] );
		}
		if ( isset( $config['amount'] ) ) {
			$coupon->set_amount( (string) $config['amount'] );
		}
		if ( isset( $config['usage_limit'] ) ) {
			$coupon->set_usage_limit( (int) $config['usage_limit'] );
		}
		if ( ! empty( $config['expiry_date'] ) ) {
			$coupon->set_date_expires( $config['expiry_date'] );
		}
		$emails = self::parse_list( $config['email_restrictions'] ?? [] );
		if ( ! empty( $emails ) ) {
			$coupon->set_email_restrictions( $emails );
		}
		$product_ids = self::parse_list( $config['product_ids'] ?? [] );
		if ( ! empty( $product_ids ) ) {
			$coupon->set_product_ids( array_map( 'intval', $product_ids ) );
		}
		$exclude_product_ids = self::parse_list( $config['exclude_product_ids'] ?? [] );
		if ( ! empty( $exclude_product_ids ) ) {
			$coupon->set_excluded_product_ids( array_map( 'intval', $exclude_product_ids ) );
		}

		$coupon->save();

		return self::respond([
			'coupon' => self::build_coupon_payload( $coupon ),
		]);
	}

	private static function action_update_coupon_code( array $config, array $input ): array {
		$coupon = self::get_coupon_from_config( $config );
		if ( ! $coupon ) {
			return self::error( 'Coupon not found' );
		}
		$new_code = $config['new_code'] ?? '';
		if ( '' === $new_code ) {
			return self::error( 'New coupon code is required' );
		}
		$coupon->set_code( $new_code );
		$coupon->save();

		return self::respond([
			'coupon' => self::build_coupon_payload( $coupon ),
		]);
	}

	private static function action_add_coupon_emails( array $config, array $input ): array {
		$coupon = self::get_coupon_from_config( $config );
		if ( ! $coupon ) {
			return self::error( 'Coupon not found' );
		}
		$emails = self::parse_list( $config['emails'] ?? [] );
		if ( empty( $emails ) ) {
			return self::error( 'Emails are required' );
		}
		$existing = $coupon->get_email_restrictions();
		$coupon->set_email_restrictions( array_values( array_unique( array_merge( $existing, $emails ) ) ) );
		$coupon->save();

		return self::respond([
			'coupon' => self::build_coupon_payload( $coupon ),
		]);
	}

	private static function action_apply_coupon_to_cart( array $config, array $input ): array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return self::error( 'Cart is not available' );
		}
		$code = $config['code'] ?? '';
		if ( '' === $code ) {
			return self::error( 'Coupon code is required' );
		}
		$applied = WC()->cart->apply_coupon( $code );
		if ( ! $applied ) {
			return self::error( 'Failed to apply coupon' );
		}
		return self::respond( [ 'code' => $code ] );
	}

	private static function action_get_applied_coupons_from_cart( array $config, array $input ): array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return self::error( 'Cart is not available' );
		}
		$response = [ 'coupons' => WC()->cart->get_applied_coupons() ];
		if ( isset( $config['include_totals'] ) && self::parse_bool( $config['include_totals'] ) ) {
			$response['coupon_totals'] = WC()->cart->get_coupon_discount_totals();
		}
		return self::respond( $response );
	}

	private static function action_remove_coupon_from_cart( array $config, array $input ): array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return self::error( 'Cart is not available' );
		}
		$code = $config['code'] ?? '';
		if ( '' === $code ) {
			return self::error( 'Coupon code is required' );
		}
		WC()->cart->remove_coupon( $code );
		return self::respond( [ 'code' => $code ] );
	}

	private static function action_delete_coupon( array $config, array $input ): array {
		$coupon = self::get_coupon_from_config( $config );
		if ( ! $coupon ) {
			return self::error( 'Coupon not found' );
		}
		$coupon_id = $coupon->get_id();
		$result = wp_delete_post( $coupon_id, true );
		if ( ! $result ) {
			return self::error( 'Failed to delete coupon', [ 'coupon_id' => $coupon_id ] );
		}
		return self::respond( [ 'coupon_id' => $coupon_id ] );
	}

	private static function action_get_coupons_all( array $config, array $input ): array {
		$pagination = self::get_pagination_args( $config );
		$result = self::query_coupons([
			'limit' => $pagination['limit'],
			'page' => $pagination['page'],
		]);
		$items = array_map(function ( $coupon ) {
			return self::build_coupon_payload( $coupon );
		}, $result['items']);
		return self::respond( [
			'count' => $result['total'],
			'items' => $items
		] );
	}

	private static function action_get_coupon_single( array $config, array $input ): array {
		$coupon = self::get_coupon_from_config( $config );
		if ( ! $coupon ) {
			return self::error( 'Coupon not found' );
		}
		return self::respond( [ 'coupon' => self::build_coupon_payload( $coupon ) ] );
	}

	private static function action_get_coupon_totals_by_discount_type( array $config, array $input ): array {
		$result = self::query_coupons( [ 'limit' => -1 ] );
		$totals = [];
		foreach ( $result['items'] as $coupon ) {
			$type = $coupon->get_discount_type();
			if ( '' === $type ) {
				$type = 'unknown';
			}
			if ( ! isset( $totals[ $type ] ) ) {
				$totals[ $type ] = 0;
			}
			$totals[ $type ]++;
		}
		if ( isset( $config['include_empty_types'] ) && self::parse_bool( $config['include_empty_types'] ) ) {
			foreach ( [ 'percent', 'fixed_cart', 'fixed_product' ] as $known_type ) {
				if ( ! isset( $totals[ $known_type ] ) ) {
					$totals[ $known_type ] = 0;
				}
			}
		}
		return self::respond( [ 'totals' => $totals ] );
	}

	private static function get_coupon_from_config( array $config ): ?\WC_Coupon {
		$coupon_id = (int) ( $config['coupon_id'] ?? 0 );
		$code = $config['code'] ?? '';
		if ( $coupon_id ) {
			$coupon = new \WC_Coupon( $coupon_id );
			return $coupon->get_id() ? $coupon : null;
		}
		if ( '' !== $code ) {
			$coupon = new \WC_Coupon( $code );
			return $coupon->get_id() ? $coupon : null;
		}
		return null;
	}
}
