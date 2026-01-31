<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class DeleteRole extends BaseAction {

    public static function get_label(): string {
        return 'Delete Role';
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
        ];
    }

    public static function get_output_schema(): array {
        return [
            'role_slug' => 'string',
            'deleted'   => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $role_slug = $config['role_slug'] ?? '';

        remove_role( $role_slug );

        return static::success( $input, [
            'role_slug' => $role_slug,
            'deleted'   => true,
        ] );
    }
}
