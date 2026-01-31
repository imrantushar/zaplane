<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class WpInsertPost extends BaseTrigger {

    public static function get_label(): string {
        return 'Post Inserted';
    }

    public static function get_hook(): string {
        return 'wp_insert_post';
    }

    public static function get_output_schema(): array {
        return [
            'revision_id'    => 'integer',
            'parent_post_id' => 'integer',
            'parent_title'   => 'string',
            'post_type'      => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $post = get_post( $hook_args[0] ?? 0 );
        if ( ! $post || $post->post_type !== 'revision' ) {
            return false;
        }

        $parent = get_post( $post->post_parent );

        return [
            'revision_id'    => $post->ID,
            'parent_post_id' => $post->post_parent,
            'parent_title'   => $parent->post_title ?? '',
            'post_type'      => $parent->post_type ?? '',
        ];
    }
}
