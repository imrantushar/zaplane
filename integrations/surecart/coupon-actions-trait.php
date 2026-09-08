<?php
namespace Zaplane\Integrations\Surecart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CouponActionsTrait {

	protected static function action_create_coupon( array $config, array $input ): array {
		if ( empty( $config['code'] ) ) {
			return self::error( 'Coupon code is required', [ 'field' => 'code' ] );
		}

		$coupon_data = [
			// Coupon requires a `name` (internal, shown in the dashboard) —
			// fall back to the code itself since we don't collect one separately.
			'name' => (string) $config['code'],
		];

		$discount_type   = $config['discount_type'] ?? '';
		$discount_amount = $config['discount_amount'] ?? '';
		if ( 'percentage' === $discount_type && '' !== $discount_amount ) {
			$coupon_data['percent_off'] = (float) $discount_amount;
		} elseif ( 'fixed' === $discount_type && '' !== $discount_amount ) {
			// amount_off is in the smallest currency unit (cents), like Stripe.
			$coupon_data['amount_off'] = (int) round( ( (float) $discount_amount ) * 100 );
			if ( ! empty( $config['currency'] ) ) {
				$coupon_data['currency'] = strtolower( (string) $config['currency'] );
			}
		} else {
			return self::error( 'Discount type and amount are required', [ 'field' => 'discount_amount' ] );
		}

		if ( ! empty( $config['duration'] ) ) {
			$coupon_data['duration'] = $config['duration'];
		}
		if ( ! empty( $config['duration_in_months'] ) ) {
			$coupon_data['duration_in_months'] = (int) $config['duration_in_months'];
		}

		$coupon_result = self::create_model( \SureCart\Models\Coupon::class, $config, $coupon_data, 'coupon' );
		if ( 'error' === ( $coupon_result['port'] ?? '' ) ) {
			return $coupon_result;
		}
		$coupon_id = $coupon_result['data']['coupon']['id'] ?? '';
		if ( '' === $coupon_id ) {
			return self::error( 'Coupon was created but no ID was returned by SureCart' );
		}

		$promotion_data = [
			'code'   => $config['code'],
			'coupon' => $coupon_id,
		];

		$promotion_result = self::create_model( \SureCart\Models\Promotion::class, $config, $promotion_data, 'coupon' );
		if ( 'error' === ( $promotion_result['port'] ?? '' ) ) {
			// The coupon itself did get created even though the promotion failed —
			// surface that so it's not a silent orphan.
			$promotion_result['data']['orphaned_coupon_id'] = $coupon_id;
			return $promotion_result;
		}

		// Merge the discount details into the response so the user sees one
		// combined object instead of having to expand it separately.
		$promotion_result['data']['coupon']['coupon_id']   = $coupon_id;
		$promotion_result['data']['coupon']['percent_off'] = $coupon_data['percent_off'] ?? null;
		$promotion_result['data']['coupon']['amount_off']  = $coupon_data['amount_off'] ?? null;

		return $promotion_result;
	}

	protected static function action_update_coupon( array $config, array $input ): array {
		$promotion_id = $config['coupon_id'] ?? '';
		if ( '' === $promotion_id ) {
			return self::error( 'ID is required', [ 'field' => 'coupon_id' ] );
		}

		// Anything that's actually a Promotion field.
		$promotion_data = [];
		if ( isset( $config['code'] ) && '' !== $config['code'] ) {
			$promotion_data['code'] = $config['code'];
		}
		if ( ! empty( $promotion_data ) ) {
			$promo_result = self::update_model( \SureCart\Models\Promotion::class, $config, 'coupon_id', $promotion_data, 'coupon' );
			if ( 'error' === ( $promo_result['port'] ?? '' ) ) {
				return $promo_result;
			}
		}

		// Anything that's actually a Coupon field (discount rules).
		$coupon_data     = [];
		$discount_type   = $config['discount_type'] ?? '';
		$discount_amount = $config['discount_amount'] ?? '';
		if ( 'percentage' === $discount_type && '' !== $discount_amount ) {
			$coupon_data['percent_off'] = (float) $discount_amount;
		} elseif ( 'fixed' === $discount_type && '' !== $discount_amount ) {
			$coupon_data['amount_off'] = (int) round( ( (float) $discount_amount ) * 100 );
		}
		$extra = self::parse_json_array( $config['data'] ?? [] );
		if ( ! empty( $extra ) ) {
			$coupon_data = array_merge( $coupon_data, $extra );
		}

		if ( ! empty( $coupon_data ) ) {
			$error = self::ensure_surecart();
			if ( null !== $error ) {
				return $error;
			}
			// Resolve which Coupon this Promotion points at, then update that.
			$promotion = \SureCart\Models\Promotion::find( $promotion_id );
			if ( is_wp_error( $promotion ) || null === $promotion ) {
				return self::error( 'Could not find the promotion to resolve its underlying coupon' );
			}
			$promo_array = self::model_to_array( $promotion );
			$coupon_ref  = $promo_array['coupon'] ?? '';
			$coupon_id   = is_array( $coupon_ref ) ? ( $coupon_ref['id'] ?? '' ) : (string) $coupon_ref;
			if ( '' === $coupon_id ) {
				return self::error( 'Could not resolve the underlying coupon for this promotion' );
			}

			$coupon_data['id'] = $coupon_id;
			$coupon_result     = \SureCart\Models\Coupon::update( $coupon_data );
			if ( is_wp_error( $coupon_result ) ) {
				return self::error( $coupon_result->get_error_message(), [ 'code' => $coupon_result->get_error_code() ] );
			}
		}

		return self::action_get_coupon_single( $config, $input );
	}

	protected static function action_delete_coupon( array $config, array $input ): array {
		return self::delete_model( \SureCart\Models\Promotion::class, $config, 'coupon_id' );
	}

	protected static function action_get_coupons_all( array $config, array $input ): array {
		$config = self::merge_expand( $config, [ 'coupon' ] );
		return self::list_models( \SureCart\Models\Promotion::class, $config, 'coupons' );
	}

	protected static function action_get_coupon_single( array $config, array $input ): array {
		$config = self::merge_expand( $config, [ 'coupon' ] );
		return self::get_model_single( \SureCart\Models\Promotion::class, $config, 'coupon_id', 'coupon' );
	}
}
