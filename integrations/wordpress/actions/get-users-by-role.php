<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetUsersByRole extends BaseAction {

    public static function get_label(): string {
        return 'Get All Users by Role';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'role',
                'label'    => 'Role',
                'type'     => 'select',
                'required' => true,
                'dynamic'  => [
                    'integration' => 'wordpress',
                    'query'       => 'roles',
                    'select'      => [ 'name', 'label' ],
                ],
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'users' => 'array',
            'count' => 'integer',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $users = get_users( [ 'role' => $config['role'] ?? '' ] );

        $result = [];
        foreach ( $users as $user ) {
            $result[] = [
                'ID'           => $user->ID,
                'user_login'   => $user->user_login,
                'user_email'   => $user->user_email,
                'display_name' => $user->display_name,
                'roles'        => $user->roles,
            ];
        }

        return static::success( $input, [
            'users' => $result,
            'count' => count( $result ),
        ] );
    }
}
