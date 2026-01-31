<?php

namespace Zaplane\Integrations\Storeengine\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Integrations\Storeengine\StoreengineHelpers;

class OrderRestored extends BaseTrigger {

    public static function get_label(): string {
        return 'Order Restored';
    }

    public static function get_hook(): string {
        return 'storeengine/order/status_changed';
    }

    public static function get_output_schema(): array {
        return [
            'order_id'        => 'integer',
            'order_number'    => 'string',
            'order_status'    => 'string',
            'total'           => 'string',
            'currency'        => 'string',
            'payment_method'  => 'string',
            'customer_email'  => 'string',
            'customer_name'   => 'string',
            'items'           => 'array',
            'old_status'      => 'string',
            'restored_status' => 'string',
        ];
    }

    /**
     * Only match when order is being restored from trash.
     */
    public static function matches( array $node, array $hook_args ): bool {
        $old_status = $hook_args[1] ?? '';
        return $old_status === 'trash';
    }

    public static function resolve( array $node, array $hook_args ) {
        $order_id   = $hook_args[0] ?? 0;
        $old_status = $hook_args[1] ?? '';

        // Only fire when coming from trash
        if ( $old_status !== 'trash' ) {
            return false;
        }

        $payload = StoreengineHelpers::resolve_order( $order_id );

        if ( ! $payload ) {
            return false;
        }

        $payload['old_status']      = $old_status;
        $payload['restored_status'] = $hook_args[2] ?? '';

        return $payload;
    }
}
