<?php

namespace Zaplane\Integrations\Storeengine\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class CreateOrder extends BaseAction {

    public static function get_label(): string {
        return 'Create Order';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'customer_email',
                'label'    => 'Customer Email',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'     => 'status',
                'label'   => 'Order Status',
                'type'    => 'select',
                'options' => [
                    [ 'label' => 'Pending',    'value' => 'pending' ],
                    [ 'label' => 'Processing', 'value' => 'processing' ],
                ],
                'default' => 'pending',
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'order_id' => 'integer',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $order_id = storeengine_create_order( [
            'customer_email' => $config['customer_email'] ?? '',
            'status'         => $config['status'] ?? 'pending',
        ] );

        if ( ! $order_id ) {
            throw new \Exception( 'Failed to create order' );
        }

        return static::success( $input, [
            'order_id' => $order_id,
        ] );
    }
}
