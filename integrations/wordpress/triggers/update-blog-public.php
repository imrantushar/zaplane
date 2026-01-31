<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class UpdateBlogPublic extends BaseTrigger {

    public static function get_label(): string {
        return 'Update Blog Public';
    }

    public static function get_hook(): string {
        return 'update_blog_public';
    }

    public static function get_output_schema(): array {
        return [
            'blog_id'   => 'integer',
            'is_public' => 'integer',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $blog_id = $hook_args[0] ?? 0;
        $public  = $hook_args[1] ?? 0;

        if ( ! $blog_id ) {
            return false;
        }

        return [
            'blog_id'   => $blog_id,
            'is_public' => $public,
        ];
    }
}
