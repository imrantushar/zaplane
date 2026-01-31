<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class ActivatePlugin extends BaseAction {

    public static function get_label(): string {
        return 'Activate Plugin';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'plugin',
                'label'    => 'Inactive Plugin',
                'type'     => 'select',
                'required' => true,
                'dynamic'  => [
                    'integration' => 'wordpress',
                    'query'       => 'inactive_plugins',
                    'select'      => [ 'file', 'name' ],
                ],
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'plugin'    => 'string',
            'activated' => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $plugin = $config['plugin'] ?? '';

        if ( empty( $plugin ) ) {
            throw new \Exception( 'Plugin is required' );
        }

        $result = activate_plugin( $plugin );

        if ( is_wp_error( $result ) ) {
            throw new \Exception( $result->get_error_message() );
        }

        return static::success( $input, [
            'plugin'    => $plugin,
            'activated' => true,
        ] );
    }
}
