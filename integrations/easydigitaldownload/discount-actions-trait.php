<?php
namespace Zaplane\Integrations\Easydigitaldownload;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait DiscountActionsTrait {

	protected static function action_create_discount( array $config, array $input ): array {
		if ( ! function_exists( 'edd_add_discount' ) ) {
			return self::action_error( 'Easy Digital Downloads is not available', $input );
		}

		$name = trim( $config['name'] ?? '' );
		$code = trim( $config['code'] ?? '' );
		$amount = $config['amount'] ?? '';
		$type = $config['type'] ?? 'percent';

		if ( '' === $name || '' === $code || '' === $amount ) {
			return self::action_error( 'Discount name, code, and amount are required', $input );
		}

		$data = [
			'name' => $name,
			'code' => $code,
			'amount' => $amount,
			'type' => $type,
		];

		$discount_status = self::get_discount_status_config( $config );
		if ( '' !== $discount_status ) {
			$data['status'] = self::normalize_discount_status( $discount_status );
		}

		if ( ! empty( $config['start_date'] ) ) {
			$data['start_date'] = $config['start_date'];
		}

		if ( ! empty( $config['end_date'] ) ) {
			$data['end_date'] = $config['end_date'];
		}

		$discount_id = edd_add_discount( $data );

		if ( empty( $discount_id ) ) {
			return self::action_error( 'Failed to create discount', $input );
		}

		return self::action_success(array_merge($input, [
			'discount_id' => $discount_id,
			'discount_code' => $code,
			'discount_name' => $name,
		]));
	}
}
