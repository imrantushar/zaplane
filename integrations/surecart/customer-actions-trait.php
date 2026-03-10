<?php
namespace Zaplane\Integrations\Surecart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CustomerActionsTrait {

	protected static function action_create_customer( array $config, array $input ): array {
		return self::create_model( \SureCart\Models\Customer::class, $config, 'customer' );
	}

	protected static function action_update_customer( array $config, array $input ): array {
		return self::update_model( \SureCart\Models\Customer::class, $config, 'customer_id', 'customer' );
	}

	protected static function action_get_customers_all( array $config, array $input ): array {
		return self::list_models( \SureCart\Models\Customer::class, $config );
	}

	protected static function action_get_customer_single( array $config, array $input ): array {
		return self::get_model_single( \SureCart\Models\Customer::class, $config, 'customer_id', 'customer' );
	}
}
