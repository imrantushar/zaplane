<?php

namespace Zaplane\Integrations\Storeengine\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Integrations\Storeengine\StoreengineHelpers;

class OrderCustomerNoteDeleted extends BaseTrigger {

    public static function get_label(): string {
        return 'Customer Note Deleted From Order';
    }

    public static function get_hook(): string {
        return 'storeengine/order/note_deleted';
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
            'deleted_note'   => 'string',
            'deleted_by'     => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $order_id = $hook_args[0] ?? 0;
        $payload  = StoreengineHelpers::resolve_order( $order_id );

        if ( ! $payload ) {
            return false;
        }

        $payload['deleted_note'] = $hook_args[1] ?? '';
        $payload['deleted_by']   = $hook_args[2] ?? '';

        return $payload;
    }
}
