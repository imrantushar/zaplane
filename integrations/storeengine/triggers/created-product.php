<?php

namespace Zaplane\Integrations\Storeengine\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Integrations\Storeengine\StoreengineHelpers;

class CreatedProduct extends BaseTrigger {

    public static function get_label(): string {
        return 'Created Product';
    }

    public static function get_hook(): string {
        return 'storeengine/product/created';
    }

    public static function get_output_schema(): array {
        return [
            'product_id'   => 'integer',
            'product_name' => 'string',
            'price'        => 'string',
            'description'  => 'string',
            'status'       => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $product_id = $hook_args[0] ?? 0;
        return StoreengineHelpers::resolve_product( $product_id );
    }
}
