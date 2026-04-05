<?php
namespace Zaplane\Integrations\Surecart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SubscriptionActionsTrait {

	protected static function action_create_subscription( array $config, array $input ): array {
		return self::create_model( \SureCart\Models\Subscription::class, $config, 'subscription' );
	}

	protected static function action_update_subscription( array $config, array $input ): array {
		return self::update_model( \SureCart\Models\Subscription::class, $config, 'subscription_id', 'subscription' );
	}

	protected static function action_get_subscriptions_all( array $config, array $input ): array {
		return self::list_models( \SureCart\Models\Subscription::class, $config );
	}

	protected static function action_get_subscription_single( array $config, array $input ): array {
		return self::get_model_single( \SureCart\Models\Subscription::class, $config, 'subscription_id', 'subscription' );
	}
}
