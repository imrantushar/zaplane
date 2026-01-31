<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class UpdateBlogStatus extends BaseTrigger {

    public static function get_label(): string {
        return 'Update Blog Status';
    }

    public static function get_hook(): string {
        return 'update_blog_status';
    }

    public static function get_output_schema(): array {
        return [
            'blog_id'    => 'integer',
            'new_status' => 'string',
            'old_status' => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $blog_id    = $hook_args[0] ?? 0;
        $new_status = $hook_args[1] ?? 0;
        $old_status = $hook_args[2] ?? 0;

        if ( ! $blog_id ) {
            return false;
        }

        return [
            'blog_id'    => $blog_id,
            'new_status' => $new_status,
            'old_status' => $old_status,
        ];
    }
}
