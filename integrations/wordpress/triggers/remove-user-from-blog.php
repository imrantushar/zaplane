<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Integrations\Wordpress\WordpressPayloadHelpers;

class RemoveUserFromBlog extends BaseTrigger {

    public static function get_label(): string {
        return 'Remove Blog User';
    }

    public static function get_hook(): string {
        return 'remove_user_from_blog';
    }

    public static function get_output_schema(): array {
        return [
            'user_id'      => 'integer',
            'user_login'   => 'string',
            'user_email'   => 'string',
            'display_name' => 'string',
            'roles'        => 'array',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        return WordpressPayloadHelpers::resolve_user( $hook_args[0] ?? 0 );
    }
}
