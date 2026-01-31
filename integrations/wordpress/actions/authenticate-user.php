<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;
use Zaplane\Integrations\Wordpress\WordpressPayloadHelpers;

class AuthenticateUser extends BaseAction {

    public static function get_label(): string {
        return 'Authenticate User';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'user_login',
                'label'    => 'Username or Email',
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'      => 'user_password',
                'label'    => 'Password',
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'      => 'remember',
                'label'    => 'Remember',
                'type'     => 'boolean',
                'required' => false,
                'default'  => false,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'authenticated' => 'boolean',
            'user'          => 'object',
            'error'         => 'string',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $user_login    = $config['user_login'] ?? '';
        $user_password = $config['user_password'] ?? '';
        $remember      = $config['remember'] ?? false;

        $user = wp_authenticate( $user_login, $user_password );

        if ( is_wp_error( $user ) ) {
            return static::success( $input, [
                'authenticated' => false,
                'user'          => null,
                'error'         => $user->get_error_message(),
            ] );
        }

        // Optionally log the user in
        if ( $remember ) {
            wp_set_auth_cookie( $user->ID, true );
        }

        return static::success( $input, [
            'authenticated' => true,
            'user'          => WordpressPayloadHelpers::resolve_user( $user->ID ),
            'error'         => null,
        ] );
    }
}
