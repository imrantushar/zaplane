<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetCaps extends BaseAction {

    public static function get_label(): string {
        return 'Get All Capabilities';
    }

    public static function get_config_schema(): array {
        return [];
    }

    public static function get_output_schema(): array {
        return [
            'capabilities' => 'array',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        global $wp_roles;

        $all_caps = [];
        foreach ( $wp_roles->roles as $role ) {
            foreach ( array_keys( $role['capabilities'] ) as $cap ) {
                $all_caps[ $cap ] = true;
            }
        }

        return static::success( $input, [
            'capabilities' => array_keys( $all_caps ),
        ] );
    }
}
