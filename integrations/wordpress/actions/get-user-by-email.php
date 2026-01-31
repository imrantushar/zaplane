<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetUserByEmail extends BaseAction {

    public static function get_label(): string {
        return 'Get User by Email';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'email',
                'label'    => 'Email',
                'type'     => 'expression',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'user' => 'object',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $user = get_user_by( 'email', $config['email'] ?? '' );

        if ( ! $user ) {
            throw new \Exception( 'User not found' );
        }

        return static::success( $input, [
            'user' => [
                'ID'           => $user->ID,
                'user_login'   => $user->user_login,
                'user_email'   => $user->user_email,
                'display_name' => $user->display_name,
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'roles'        => $user->roles,
            ],
        ] );
    }
}
