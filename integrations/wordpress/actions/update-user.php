<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class UpdateUser extends BaseAction {

    public static function get_label(): string {
        return 'Update User';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'user_id',
                'label'    => 'User ID',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'   => 'user_email',
                'label' => 'Email',
                'type'  => 'text',
            ],
            [
                'key'   => 'user_pass',
                'label' => 'Password',
                'type'  => 'text',
            ],
            [
                'key'   => 'display_name',
                'label' => 'Display Name',
                'type'  => 'text',
            ],
            [
                'key'   => 'first_name',
                'label' => 'First Name',
                'type'  => 'text',
            ],
            [
                'key'   => 'last_name',
                'label' => 'Last Name',
                'type'  => 'text',
            ],
            [
                'key'   => 'role',
                'label' => 'Role',
                'type'  => 'text',
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'user_id' => 'integer',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $user_data = [ 'ID' => $config['user_id'] ];

        if ( ! empty( $config['user_email'] ) ) {
            $user_data['user_email'] = $config['user_email'];
        }
        if ( ! empty( $config['user_pass'] ) ) {
            $user_data['user_pass'] = $config['user_pass'];
        }
        if ( ! empty( $config['display_name'] ) ) {
            $user_data['display_name'] = $config['display_name'];
        }
        if ( ! empty( $config['first_name'] ) ) {
            $user_data['first_name'] = $config['first_name'];
        }
        if ( ! empty( $config['last_name'] ) ) {
            $user_data['last_name'] = $config['last_name'];
        }
        if ( ! empty( $config['role'] ) ) {
            $user_data['role'] = $config['role'];
        }

        $user_id = wp_update_user( $user_data );

        if ( is_wp_error( $user_id ) ) {
            throw new \Exception( $user_id->get_error_message() );
        }

        return static::success( $input, [ 'user_id' => $user_id ] );
    }
}
