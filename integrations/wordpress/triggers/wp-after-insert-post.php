<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Integrations\Wordpress\WordpressPayloadHelpers;

class WpAfterInsertPost extends BaseTrigger {

    public static function get_label(): string {
        return 'WP After Insert Post';
    }

    public static function get_hook(): string {
        return 'wp_after_insert_post';
    }

    public static function get_output_schema(): array {
        return [
            'post_id'    => 'integer',
            'post_title' => 'string',
            'post_type'  => 'string',
            'status'     => 'string',
            'is_update'  => 'boolean',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $post = get_post( $hook_args[0] ?? 0 );
        if ( ! $post ) {
            return false;
        }

        $payload = WordpressPayloadHelpers::resolve_post( $post->ID );
        if ( ! $payload ) {
            return false;
        }

        $payload['is_update'] = $hook_args[1] ?? false;

        return $payload;
    }
}
