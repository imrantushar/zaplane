<?php

namespace Zaplane\Integrations\Storeengine\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Integrations\Storeengine\StoreengineHelpers;

class ProductPurchased extends BaseTrigger {

    public static function get_label(): string {
        return 'Product Purchased';
    }

    public static function get_hook(): string {
        return 'storeengine/checkout/after_place_order';
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
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $order_id = $hook_args[0] ?? 0;
        return StoreengineHelpers::resolve_order( $order_id );
    }
}
