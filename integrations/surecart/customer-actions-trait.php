<?php
namespace Zaplane\Integrations\Surecart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CustomerActionsTrait {

	protected static function action_create_customer( array $config, array $input ): array {
		$data = [];
		foreach ( [ 'email', 'first_name', 'last_name', 'phone' ] as $field ) {
			if ( isset( $config[ $field ] ) && '' !== $config[ $field ] ) {
				$data[ $field ] = $config[ $field ];
			}
		}
		if ( empty( $data['email'] ) ) {
			return self::error( 'Email is required', [ 'field' => 'email' ] );
		}

		return self::create_model( \SureCart\Models\Customer::class, $config, $data, 'customer' );
	}

	protected static function action_update_customer( array $config, array $input ): array {
		$data = [];

		foreach ( [ 'email', 'first_name', 'last_name', 'phone' ] as $field ) {
			if ( isset( $config[ $field ] ) && '' !== $config[ $field ] ) {
				$data[ $field ] = $config[ $field ];
			}
		}

		return self::update_model( \SureCart\Models\Customer::class, $config, 'customer_id', $data, 'customer' );
	}

	protected static function action_get_customers_all( array $config, array $input ): array {
		return self::list_models( \SureCart\Models\Customer::class, $config, 'customers' );
	}

	protected static function action_get_customer_single( array $config, array $input ): array {
		return self::get_model_single( \SureCart\Models\Customer::class, $config, 'customer_id', 'customer' );
	}
}
