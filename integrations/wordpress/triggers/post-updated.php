<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Integrations\Wordpress\WordpressPayloadHelpers;

class PostUpdated extends BaseTrigger {

    public static function get_label(): string {
        return 'Post Updated';
    }

    public static function get_hook(): string {
        return 'post_updated';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'     => 'post_type',
                'label'   => 'Post Type',
                'type'    => 'select',
                'dynamic' => [
                    'integration' => 'wordpress',
                    'query'       => 'post_types',
                    'select'      => [ 'name', 'label' ],
                ],
                'required' => true,
            ],
            [
                'key'     => 'post_status',
                'label'   => 'Post Status',
                'type'    => 'select',
                'options' => [
                    [ 'label' => 'Publish', 'value' => 'publish' ],
                    [ 'label' => 'Draft',   'value' => 'draft' ],
                ],
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'post_id'    => 'integer',
            'post_title' => 'string',
            'post_type'  => 'string',
            'status'     => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        return WordpressPayloadHelpers::resolve_post( $hook_args[0] ?? 0 );
    }
}
