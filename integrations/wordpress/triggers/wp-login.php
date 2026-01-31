<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class WpLogin extends BaseTrigger {

    public static function get_label(): string {
        return 'User Logged In';
    }

    public static function get_hook(): string {
        return 'wp_login';
    }

    public static function get_output_schema(): array {
        return [
            'user_id'  => 'integer',
            'username' => 'string',
            'email'    => 'string',
            'roles'    => 'array',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $user = $hook_args[1] ?? null;

        if ( ! $user instanceof \WP_User ) {
            return false;
        }

        return [
            'user_id'  => $user->ID,
            'username' => $user->user_login,
            'email'    => $user->user_email,
            'roles'    => $user->roles,
        ];
    }
}
