<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class NewBlog extends BaseTrigger {

    public static function get_label(): string {
        return 'New Blog';
    }

    public static function get_hook(): string {
        return 'wpmu_new_blog';
    }

    public static function get_output_schema(): array {
        return [
            'blog_id' => 'integer',
            'user_id' => 'integer',
            'domain'  => 'string',
            'path'    => 'string',
            'site_id' => 'integer',
            'meta'    => 'array',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $blog_id = $hook_args[0] ?? 0;

        if ( ! $blog_id ) {
            return false;
        }

        return [
            'blog_id' => $blog_id,
            'user_id' => $hook_args[1] ?? 0,
            'domain'  => $hook_args[2] ?? '',
            'path'    => $hook_args[3] ?? '',
            'site_id' => $hook_args[4] ?? 0,
            'meta'    => $hook_args[5] ?? [],
        ];
    }
}
