<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class AddRoleCaps extends BaseAction {

    public static function get_label(): string {
        return 'Add Role Capabilities';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'role_slug',
                'label'    => 'Role',
                'type'     => 'select',
                'required' => true,
                'dynamic'  => [
                    'integration' => 'wordpress',
                    'query'       => 'roles',
                    'select'      => [ 'slug', 'name' ],
                ],
            ],
            [
                'key'      => 'capabilities',
                'label'    => 'Capabilities (comma-separated)',
                'type'     => 'text',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'role_slug' => 'string',
            'added'     => 'array',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $role_slug   = $config['role_slug'] ?? '';
        $caps_string = $config['capabilities'] ?? '';

        $role  = get_role( $role_slug );
        $added = [];

        if ( $role && ! empty( $caps_string ) ) {
            $caps = array_map( 'trim', explode( ',', $caps_string ) );
            foreach ( $caps as $cap ) {
                if ( ! empty( $cap ) ) {
                    $role->add_cap( $cap );
                    $added[] = $cap;
                }
            }
        }

        return static::success( $input, [
            'role_slug' => $role_slug,
            'added'     => $added,
        ] );
    }
}
