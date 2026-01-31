<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class CreateRole extends BaseAction {

    public static function get_label(): string {
        return 'Create Role';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'role_slug',
                'label'    => 'Role Slug',
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'      => 'display_name',
                'label'    => 'Display Name',
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'      => 'capabilities',
                'label'    => 'Capabilities (comma-separated)',
                'type'     => 'text',
                'required' => false,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'role_slug'    => 'string',
            'display_name' => 'string',
            'created'      => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $role_slug    = $config['role_slug'] ?? '';
        $display_name = $config['display_name'] ?? '';
        $caps_string  = $config['capabilities'] ?? '';

        $capabilities = [];
        if ( ! empty( $caps_string ) ) {
            $caps_array = array_map( 'trim', explode( ',', $caps_string ) );
            foreach ( $caps_array as $cap ) {
                if ( ! empty( $cap ) ) {
                    $capabilities[ $cap ] = true;
                }
            }
        }

        $result = add_role( $role_slug, $display_name, $capabilities );

        return static::success( $input, [
            'role_slug'    => $role_slug,
            'display_name' => $display_name,
            'created'      => $result !== null,
        ] );
    }
}
