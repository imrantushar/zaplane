<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class DeactivatePlugin extends BaseTrigger {

    public static function get_label(): string {
        return 'Deactivate Plugin';
    }

    public static function get_hook(): string {
        return 'deactivate_plugin';
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
