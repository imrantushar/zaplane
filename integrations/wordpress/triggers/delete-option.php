<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class DeleteOption extends BaseTrigger {

    public static function get_label(): string {
        return 'Delete Option';
    }

    public static function get_hook(): string {
        return 'delete_option';
    }

    public static function get_output_schema(): array {
        return [
            'option_name' => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        return [
            'option_name' => $hook_args[0] ?? '',
        ];
    }
}
