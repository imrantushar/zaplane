<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class DeactivatePlugin extends BaseAction {

    public static function get_label(): string {
        return 'Deactivate Plugin';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'plugin',
                'label'    => 'Active Plugin',
                'type'     => 'select',
                'required' => true,
                'dynamic'  => [
                    'integration' => 'wordpress',
                    'query'       => 'active_plugins',
                    'select'      => [ 'file', 'name' ],
                ],
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'plugin'      => 'string',
            'deactivated' => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $plugin = $config['plugin'] ?? '';

        if ( empty( $plugin ) ) {
            throw new \Exception( 'Plugin is required' );
        }

        deactivate_plugins( $plugin );

        return static::success( $input, [
            'plugin'      => $plugin,
            'deactivated' => true,
        ] );
    }
}
