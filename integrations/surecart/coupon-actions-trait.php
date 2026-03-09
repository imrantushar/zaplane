<?php
namespace Zaplane\Integrations\Surecart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CouponActionsTrait {

	protected static function action_create_coupon( array $config, array $input ): array {
		return self::create_model( \SureCart\Models\Coupon::class, $config, 'coupon' );
	}

	protected static function action_update_coupon( array $config, array $input ): array {
		return self::update_model( \SureCart\Models\Coupon::class, $config, 'coupon_id', 'coupon' );
	}

	protected static function action_delete_coupon( array $config, array $input ): array {
		return self::delete_model( \SureCart\Models\Coupon::class, $config, 'coupon_id' );
	}

	protected static function action_get_coupons_all( array $config, array $input ): array {
		return self::list_models( \SureCart\Models\Coupon::class, $config );
	}

	protected static function action_get_coupon_single( array $config, array $input ): array {
		return self::get_model_single( \SureCart\Models\Coupon::class, $config, 'coupon_id', 'coupon' );
	}
}
