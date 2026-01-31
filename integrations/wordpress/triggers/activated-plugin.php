<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class ActivatedPlugin extends BaseTrigger {

    public static function get_label(): string {
        return 'Activate Plugin';
    }

    public static function get_hook(): string {
        return 'activated_plugin';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'     => 'plugin',
                'label'   => 'Inactive Plugin',
                'type'    => 'select',
                'dynamic' => [
                    'integration' => 'wordpress',
                    'query'       => 'inactive_plugins',
                    'select'      => [ 'file', 'name' ],
                ],
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'plugin' => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $plugin = $hook_args[0] ?? 0;

        if ( ! $plugin ) {
            return false;
        }

        return [
            'plugin' => $plugin,
        ];
    }
}
