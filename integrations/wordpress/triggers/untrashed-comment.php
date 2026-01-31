<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Integrations\Wordpress\WordpressPayloadHelpers;

class UntrashedComment extends BaseTrigger {

    public static function get_label(): string {
        return 'Comment Untrashed';
    }

    public static function get_hook(): string {
        return 'untrashed_comment';
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
                'required' => false,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'comment_id' => 'integer',
            'post_id'    => 'integer',
            'content'    => 'string',
            'status'     => 'string',
            'author'     => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        return WordpressPayloadHelpers::resolve_comment( $hook_args[0] ?? 0 );
    }
}
