<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class CustomizeRegister extends BaseTrigger {

    public static function get_label(): string {
        return 'Customizer Registration';
    }

    public static function get_hook(): string {
        return 'customize_register';
    }

    public static function get_output_schema(): array {
        return [
            'message' => 'string',
            'time'    => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $customize = $hook_args[0] ?? null;

        if ( ! $customize ) {
            return false;
        }

        return [
            'message' => 'Customize Registration',
            'time'    => current_time( 'mysql' ),
        ];
    }
}
