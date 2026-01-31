<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetRoles extends BaseAction {

    public static function get_label(): string {
        return 'Get All Roles';
    }

    public static function get_config_schema(): array {
        return [];
    }

    public static function get_output_schema(): array {
        return [
            'roles' => 'array',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        global $wp_roles;

        $roles = [];
        foreach ( $wp_roles->roles as $slug => $role ) {
            $roles[] = [
                'slug'         => $slug,
                'name'         => $role['name'],
                'capabilities' => array_keys( array_filter( $role['capabilities'] ) ),
            ];
        }

        return static::success( $input, [
            'roles' => $roles,
        ] );
    }
}
