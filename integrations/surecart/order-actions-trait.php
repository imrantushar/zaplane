<?php
namespace Zaplane\Integrations\Surecart;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait OrderActionsTrait {
    protected static function action_create_order(array $config, array $input): array {
        return self::create_model(\SureCart\Models\Order::class, $config, 'order');
    }

    protected static function action_update_order(array $config, array $input): array {
        return self::update_model(\SureCart\Models\Order::class, $config, 'order_id', 'order');
    }

    protected static function action_get_orders_all(array $config, array $input): array {
        return self::list_models(\SureCart\Models\Order::class, $config);
    }

    protected static function action_get_order_single(array $config, array $input): array {
        return self::get_model_single(\SureCart\Models\Order::class, $config, 'order_id', 'order');
    }
}
