<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class WpLogout extends BaseTrigger {

    public static function get_label(): string {
        return 'User Logged Out';
    }

    public static function get_hook(): string {
        return 'wp_logout';
    }

    public static function get_output_schema(): array {
        return [
            'user_id'  => 'integer',
            'username' => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $user = wp_get_current_user();

        if ( ! $user || ! $user->ID ) {
            return false;
        }

        return [
            'user_id'  => $user->ID,
            'username' => $user->user_login,
        ];
    }
}
