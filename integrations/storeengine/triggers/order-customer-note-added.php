<?php

namespace Zaplane\Integrations\Storeengine\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Integrations\Storeengine\StoreengineHelpers;

class OrderCustomerNoteAdded extends BaseTrigger {

    public static function get_label(): string {
        return 'Customer Note Added to Order';
    }

    public static function get_hook(): string {
        return 'storeengine/order/new_customer_note';
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
            'note'           => 'string',
            'note_author'    => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $order_id = $hook_args[0] ?? 0;
        $payload  = StoreengineHelpers::resolve_order( $order_id );

        if ( ! $payload ) {
            return false;
        }

        $payload['note']        = $hook_args[1] ?? '';
        $payload['note_author'] = $hook_args[2] ?? '';

        return $payload;
    }
}
