<?php

namespace Zaplane\Integrations\Storeengine\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Integrations\Storeengine\StoreengineHelpers;

class OrderStatusUpdate extends BaseTrigger {

    public static function get_label(): string {
        return 'Order Status Updated';
    }

    public static function get_hook(): string {
        return 'storeengine/order/status_changed';
    }

    public static function get_output_schema(): array {
        return [
            'order_id'       => 'integer',
            'order_number'   => 'string',
            'order_status'   => 'string',
            'total'          => 'string',
            'currency'       => 'string',
            'payment_method' => 'string',
            'customer_email' => 'string',
            'customer_name'  => 'string',
            'items'          => 'array',
            'old_status'     => 'string',
            'new_status'     => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $order_id = $hook_args[0] ?? 0;
        $payload  = StoreengineHelpers::resolve_order( $order_id );

        if ( ! $payload ) {
            return false;
        }

        $payload['old_status'] = $hook_args[1] ?? '';
        $payload['new_status'] = $hook_args[2] ?? '';

        return $payload;
    }
}
