<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class RestApiInit extends BaseTrigger {

    public static function get_label(): string {
        return 'REST API Init';
    }

    public static function get_hook(): string {
        return 'rest_api_init';
    }

    public static function get_output_schema(): array {
        return [
            'time' => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        return [
            'time' => current_time( 'mysql' ),
        ];
    }
}
