<?php
namespace Zaplane\Integrations\Fluentcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CouponActionsTrait {

	private static function action_get_coupons_all( array $config, array $input ): array {
		$limit = max( 1, (int) ( $config['limit'] ?? 20 ) );
		$page  = max( 1, (int) ( $config['page'] ?? 1 ) );
		$items = self::list_coupons( $limit, $page, trim( (string) ( $config['search'] ?? '' ) ) );

		return self::main_response(
			array_merge( $input, [ 'items' => $items, 'total' => count( $items ), 'limit' => $limit, 'page' => $page ] )
		);
	}

	private static function action_get_coupon_single( array $config, array $input ): array {
		$coupon_id = self::resolve_entity_id_for_action( $config, $input, 'coupon_id', [ 'coupon' ] );
		if ( $coupon_id <= 0 ) {
			return self::error_response( 'Coupon ID is required', $input );
		}

		$coupon = self::find_model_by_id( self::coupon_model_class(), $coupon_id );
		if ( ! $coupon ) {
			return self::error_response( 'Coupon not found', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'coupon' => self::normalize_payload_value( $coupon ) ] )
		);
	}

	private static function action_create_coupon( array $config, array $input ): array {
		$code = trim( (string) ( $config['code'] ?? '' ) );
		if ( '' === $code ) {
			return self::error_response( 'Coupon code is required', $input );
		}

		$coupon = self::create_model( self::coupon_model_class(), self::build_coupon_data_from_config( $config, $code ) );
		if ( ! $coupon ) {
			return self::error_response( 'Coupon creation failed', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'coupon' => self::normalize_payload_value( $coupon ), 'created' => true, 'event_time' => current_time( 'mysql' ) ] )
		);
	}

	private static function action_update_coupon( array $config, array $input ): array {
		$coupon_id = self::resolve_entity_id_for_action( $config, $input, 'coupon_id', [ 'coupon' ] );
		if ( $coupon_id <= 0 ) {
			return self::error_response( 'Coupon ID is required', $input );
		}

		$data = self::build_coupon_data_from_config( $config, trim( (string) ( $config['code'] ?? '' ) ), false );
		if ( empty( $data ) ) {
			return self::error_response( 'At least one field is required to update', $input );
		}

		if ( ! self::update_model_by_id( self::coupon_model_class(), $coupon_id, $data ) ) {
			return self::error_response( 'Coupon update failed', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'coupon_id'  => $coupon_id,
					'coupon'     => self::normalize_payload_value( self::find_model_by_id( self::coupon_model_class(), $coupon_id ) ),
					'updated'    => true,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_delete_coupon( array $config, array $input ): array {
		$coupon_id = self::resolve_entity_id_for_action( $config, $input, 'coupon_id', [ 'coupon' ] );
		if ( $coupon_id <= 0 ) {
			return self::error_response( 'Coupon ID is required', $input );
		}

		if ( ! self::delete_model_by_id( self::coupon_model_class(), $coupon_id ) ) {
			return self::error_response( 'Coupon deletion failed', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'coupon_id' => $coupon_id, 'deleted' => true ] )
		);
	}

	private static function build_coupon_data_from_config( array $config, string $code, bool $require_code = true ): array {
		$data = [];

		if ( '' !== $code ) {
			$data['code'] = $code;
		} elseif ( $require_code ) {
			return [];
		}

		foreach ( [ 'amount', 'type', 'status', 'expiry_date' ] as $key ) {
			if ( array_key_exists( $key, $config ) && '' !== $config[ $key ] ) {
				$data[ $key ] = $config[ $key ];
			}
		}

		return $data;
	}
}
