<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class UpdateUserRole extends BaseAction {

    public static function get_label(): string {
        return 'Update User Role';
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
                'key'      => 'role',
                'label'    => 'New Role',
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
            'user_id' => 'integer',
            'role'    => 'string',
            'updated' => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $user = get_user_by( 'id', $config['user_id'] ?? 0 );

        if ( ! $user ) {
            throw new \Exception( 'User not found' );
        }

        $user->set_role( $config['role'] ?? '' );

        return static::success( $input, [
            'user_id' => $config['user_id'],
            'role'    => $config['role'],
            'updated' => true,
        ] );
    }
}
