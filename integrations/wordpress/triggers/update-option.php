<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class UpdateOption extends BaseTrigger {

    public static function get_label(): string {
        return 'Update Option';
    }

    public static function get_hook(): string {
        return 'update_option';
    }

    public static function get_output_schema(): array {
        return [
            'option_name' => 'string',
            'value'       => 'mixed',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        return [
            'option_name' => $hook_args[0] ?? '',
            'value'       => $hook_args[2] ?? null,
        ];
    }
}
