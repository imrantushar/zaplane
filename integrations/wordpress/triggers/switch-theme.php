<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class SwitchTheme extends BaseTrigger {

    public static function get_label(): string {
        return 'Theme Switch';
    }

    public static function get_hook(): string {
        return 'switch_theme';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'     => 'theme',
                'label'   => 'Theme Switch',
                'type'    => 'select',
                'dynamic' => [
                    'integration' => 'wordpress',
                    'query'       => 'deactivate_theme',
                    'select'      => [ 'file', 'name' ],
                ],
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'theme' => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $theme = $hook_args[0] ?? 0;

        if ( ! $theme ) {
            return false;
        }

        return [
            'theme' => $theme,
        ];
    }
}
