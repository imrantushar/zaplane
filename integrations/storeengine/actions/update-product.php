<?php

namespace Zaplane\Integrations\Storeengine\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class UpdateProduct extends BaseAction {

    public static function get_label(): string {
        return 'Update Product';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'product_id',
                'label'    => 'Product ID',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'   => 'product_name',
                'label' => 'Product Name',
                'type'  => 'text',
            ],
            [
                'key'   => 'price',
                'label' => 'Price',
                'type'  => 'number',
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
            'product_id' => 'integer',
            'updated'    => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $product_id = $config['product_id'] ?? 0;

        if ( ! $product_id ) {
            throw new \Exception( 'Product ID is required' );
        }

        $update_data = [];

        if ( ! empty( $config['product_name'] ) ) {
            $update_data['name'] = $config['product_name'];
        }
        if ( ! empty( $config['price'] ) ) {
            $update_data['price'] = $config['price'];
        }
        if ( ! empty( $config['description'] ) ) {
            $update_data['description'] = $config['description'];
        }
        if ( ! empty( $config['status'] ) ) {
            $update_data['status'] = $config['status'];
        }

        storeengine_update_product( $product_id, $update_data );

        return static::success( $input, [
            'product_id' => $product_id,
            'updated'    => true,
        ] );
    }
}
