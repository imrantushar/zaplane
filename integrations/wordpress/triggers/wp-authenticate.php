<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class WpAuthenticate extends BaseTrigger {

    public static function get_label(): string {
        return 'WP Authenticate';
    }

    public static function get_hook(): string {
        return 'wp_authenticate';
    }

    public static function get_output_schema(): array {
        return [
            'username' => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        return [
            'username' => $hook_args[0] ?? '',
        ];
    }
}
