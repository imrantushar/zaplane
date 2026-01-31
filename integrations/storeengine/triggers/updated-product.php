<?php

namespace Zaplane\Integrations\Storeengine\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Integrations\Storeengine\StoreengineHelpers;

class UpdatedProduct extends BaseTrigger {

    public static function get_label(): string {
        return 'Updated Product';
    }

    public static function get_hook(): string {
        return 'storeengine/product/updated';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'product_id',
                'label'    => 'ID',
                'type'     => 'expression',
                'required' => true,
            ],
        ];
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
