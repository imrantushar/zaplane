<?php
namespace Zaplane\Integrations\Easydigitaldownload;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CustomerActionsTrait {

	protected static function action_create_customer( array $config, array $input ): array {
		if ( ! function_exists( 'edd_add_customer' ) ) {
			return self::action_error( 'Easy Digital Downloads is not available', $input );
		}

		$email = trim( $config['email'] ?? '' );
		if ( '' === $email ) {
			return self::action_error( 'Customer email is required', $input );
		}

		$data = [
			'email' => $email,
		];

		if ( ! empty( $config['name'] ) ) {
			$data['name'] = $config['name'];
		}

		if ( ! empty( $config['user_id'] ) ) {
			$data['user_id'] = (int) $config['user_id'];
		}

		$customer_status = self::get_customer_status_config( $config );
		if ( '' !== $customer_status ) {
			$data['status'] = self::normalize_customer_status( $customer_status );
		}

		$customer_id = edd_add_customer( $data );

		if ( empty( $customer_id ) ) {
			return self::action_error( 'Failed to create customer', $input );
		}

		return self::action_success(array_merge($input, [
			'customer_id' => $customer_id,
			'customer_email' => $email,
			'customer_name' => $data['name'] ?? '',
		]));
	}
}
