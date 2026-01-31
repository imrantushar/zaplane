<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class RemoveUserCaps extends BaseAction {

    public static function get_label(): string {
        return 'Remove User Capabilities';
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
                'key'      => 'capabilities',
                'label'    => 'Capabilities (comma-separated)',
                'type'     => 'text',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'user_id' => 'integer',
            'removed' => 'array',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $user_id     = $config['user_id'] ?? 0;
        $caps_string = $config['capabilities'] ?? '';

        $user    = get_userdata( $user_id );
        $removed = [];

        if ( $user && ! empty( $caps_string ) ) {
            $caps = array_map( 'trim', explode( ',', $caps_string ) );
            foreach ( $caps as $cap ) {
                if ( ! empty( $cap ) ) {
                    $user->remove_cap( $cap );
                    $removed[] = $cap;
                }
            }
        }

        return static::success( $input, [
            'user_id' => $user_id,
            'removed' => $removed,
        ] );
    }
}
