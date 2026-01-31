<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class UpgraderProcessComplete extends BaseTrigger {

    public static function get_label(): string {
        return 'Upgrader Complete';
    }

    public static function get_hook(): string {
        return 'upgrader_process_complete';
    }

    public static function get_output_schema(): array {
        return [
            'action' => 'string',
            'type'   => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        return [
            'action' => $hook_args[1]['action'] ?? '',
            'type'   => $hook_args[1]['type'] ?? '',
        ];
    }
}
