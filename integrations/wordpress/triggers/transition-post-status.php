<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class TransitionPostStatus extends BaseTrigger {

    public static function get_label(): string {
        return 'Post Status Updated';
    }

    public static function get_hook(): string {
        return 'transition_post_status';
    }

    public static function get_output_schema(): array {
        return [
            'post_id'    => 'integer',
            'post_title' => 'string',
            'post_type'  => 'string',
            'old_status' => 'string',
            'new_status' => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $post = get_post( $hook_args[2] ?? 0 );
        if ( ! $post || ( $hook_args[1] ?? '' ) === 'new' ) {
            return false;
        }

        return [
            'post_id'    => $post->ID,
            'post_title' => $post->post_title,
            'post_type'  => $post->post_type,
            'old_status' => $hook_args[1] ?? '',
            'new_status' => $hook_args[0] ?? '',
        ];
    }
}
