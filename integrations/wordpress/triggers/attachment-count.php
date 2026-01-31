<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class AttachmentCount extends BaseTrigger {

    public static function get_label(): string {
        return 'Attachment Count';
    }

    public static function get_hook(): string {
        return 'wp_count_attachments';
    }

    public static function get_output_schema(): array {
        return [
            'post_type' => 'string',
            'counts'    => 'array',
            'time'      => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $post_type = $hook_args[0] ?? 0;
        $count     = wp_count_attachments( $post_type );

        return [
            'post_type' => $post_type,
            'counts'    => (array) $count,
            'time'      => current_time( 'mysql' ),
        ];
    }
}
