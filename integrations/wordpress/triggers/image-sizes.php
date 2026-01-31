<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class ImageSizes extends BaseTrigger {

    public static function get_label(): string {
        return 'Image Sizes';
    }

    public static function get_hook(): string {
        return 'image_size_names_choose';
    }

    public static function get_output_schema(): array {
        return [
            'sizes'      => 'array',
            'sizes_keys' => 'array',
            'count'      => 'integer',
            'time'       => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $sizes = $hook_args[0] ?? [];

        if ( empty( $sizes ) || ! is_array( $sizes ) ) {
            return false;
        }

        return [
            'sizes'      => $sizes,
            'sizes_keys' => array_keys( $sizes ),
            'count'      => count( $sizes ),
            'time'       => current_time( 'mysql' ),
        ];
    }
}
