<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class MediaUploadTabs extends BaseTrigger {

    public static function get_label(): string {
        return 'Media Tabs';
    }

    public static function get_hook(): string {
        return 'media_upload_tabs';
    }

    public static function get_output_schema(): array {
        return [
            'tabs'         => 'array',
            'tabs_keys'    => 'array',
            'count'        => 'integer',
            'triggered_at' => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $tabs = $hook_args[0] ?? [];

        if ( empty( $tabs ) || ! is_array( $tabs ) ) {
            return false;
        }

        return [
            'tabs'         => $tabs,
            'tabs_keys'    => array_keys( $tabs ),
            'count'        => count( $tabs ),
            'triggered_at' => current_time( 'mysql' ),
        ];
    }
}
