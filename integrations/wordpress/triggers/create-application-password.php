<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class CreateApplicationPassword extends BaseTrigger {

    public static function get_label(): string {
        return 'Create Application Password';
    }

    public static function get_hook(): string {
        return 'wp_create_application_password';
    }

    public static function get_output_schema(): array {
        return [
            'user_id'      => 'integer',
            'user_login'   => 'string',
            'new_password' => 'string',
            'time'         => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $user_id      = $hook_args[0] ?? 0;
        $new_password = $hook_args[1] ?? '';

        if ( ! $user_id || empty( $new_password ) ) {
            return false;
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        return [
            'user_id'      => $user_id,
            'user_login'   => $user->user_login,
            'new_password' => $new_password,
            'time'         => current_time( 'mysql' ),
        ];
    }
}
