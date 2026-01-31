<?php

namespace Zaplane\Integrations\Storeengine\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class CreateProduct extends BaseAction {

    public static function get_label(): string {
        return 'Create Product';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'product_name',
                'label'    => 'Product Name',
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'      => 'price',
                'label'    => 'Price',
                'type'     => 'number',
                'required' => true,
            ],
            [
                'key'   => 'description',
                'label' => 'Description',
                'type'  => 'textarea',
            ],
            [
                'key'     => 'status',
                'label'   => 'Status',
                'type'    => 'select',
                'options' => [
                    [ 'label' => 'Draft',   'value' => 'draft' ],
                    [ 'label' => 'Publish', 'value' => 'publish' ],
                ],
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'product_id'   => 'integer',
            'product_name' => 'string',
            'price'        => 'string',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $name  = $config['product_name'] ?? '';
        $price = $config['price'] ?? '';

        if ( ! $name || $price === '' ) {
            throw new \Exception( 'Product name and price are required' );
        }

        $product_id = storeengine_create_product( [
            'name'        => $name,
            'price'       => $price,
            'description' => $config['description'] ?? '',
            'status'      => $config['status'] ?? 'publish',
        ] );

        if ( ! $product_id ) {
            throw new \Exception( 'Failed to create product' );
        }

        return static::success( $input, [
            'product_id'   => $product_id,
            'product_name' => $name,
            'price'        => $price,
        ] );
    }
}
