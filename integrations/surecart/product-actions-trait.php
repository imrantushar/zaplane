<?php
namespace Zaplane\Integrations\Surecart;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ProductActionsTrait {
    protected static function action_create_product_manual(array $config, array $input): array {
        $data = self::build_product_data_from_manual($config);
        if (empty($data['name'])) {
            return self::error('Product name is required', ['field' => 'name']);
        }
        $price = $data['prices'][0] ?? [];
        if (!array_key_exists('amount', $price) || empty($price['currency'])) {
            return self::error('Price amount and currency are required', ['field' => 'price_amount']);
        }
        $config['data'] = $data;
        return self::create_model(\SureCart\Models\Product::class, $config, 'product');
    }

    protected static function action_create_product(array $config, array $input): array {
        return self::create_model(\SureCart\Models\Product::class, $config, 'product');
    }

    protected static function action_update_product(array $config, array $input): array {
        return self::update_model(\SureCart\Models\Product::class, $config, 'product_id', 'product');
    }

    protected static function action_delete_product(array $config, array $input): array {
        return self::delete_model(\SureCart\Models\Product::class, $config, 'product_id');
    }

    protected static function action_get_products_all(array $config, array $input): array {
        return self::list_models(\SureCart\Models\Product::class, $config);
    }

    protected static function action_get_product_single(array $config, array $input): array {
        return self::get_model_single(\SureCart\Models\Product::class, $config, 'product_id', 'product');
    }
}
