<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class SendPasswordResetEmail extends BaseAction {

    public static function get_label(): string {
        return 'Send Password Reset Email';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'user_login',
                'label'    => 'Username or Email',
                'type'     => 'text',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'user_login' => 'string',
            'sent'       => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $user_login = $config['user_login'] ?? '';

        $user = get_user_by( 'email', $user_login );
        if ( ! $user ) {
            $user = get_user_by( 'login', $user_login );
        }

        $sent = false;
        if ( $user ) {
            $result = retrieve_password( $user->user_login );
            $sent   = ! is_wp_error( $result );
        }

        return static::success( $input, [
            'user_login' => $user_login,
            'sent'       => $sent,
        ] );
    }
}
