<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class SwitchBlog extends BaseTrigger {

    public static function get_label(): string {
        return 'Blog Switch';
    }

    public static function get_hook(): string {
        return 'switch_blog';
    }

    public static function get_output_schema(): array {
        return [
            'blog_id'   => 'integer',
            'blog_url'  => 'string',
            'blog_name' => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $blog = $hook_args[0] ?? 0;

        if ( ! $blog ) {
            return false;
        }

        return [
            'blog_id'   => $blog,
            'blog_url'  => get_home_url( $blog ),
            'blog_name' => get_blog_option( $blog, 'blogname' ),
        ];
    }
}
