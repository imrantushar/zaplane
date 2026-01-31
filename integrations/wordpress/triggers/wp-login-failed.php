<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class WpLoginFailed extends BaseTrigger {

    public static function get_label(): string {
        return 'Login Failed';
    }

    public static function get_hook(): string {
        return 'wp_login_failed';
    }

    public static function get_output_schema(): array {
        return [
            'username' => 'string',
            'failed'   => 'boolean',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        return [
            'username' => $hook_args[0] ?? '',
            'failed'   => true,
        ];
    }
}
