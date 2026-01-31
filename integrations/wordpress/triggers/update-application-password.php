<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class UpdateApplicationPassword extends BaseTrigger {

    public static function get_label(): string {
        return 'Update Application Password';
    }

    public static function get_hook(): string {
        return 'wp_update_application_password';
    }

    public static function get_output_schema(): array {
        return [
            'user_id'   => 'integer',
            'item_name' => 'string',
            'item_id'   => 'string',
            'time'      => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $user_id = $hook_args[0] ?? 0;
        $item    = $hook_args[1] ?? null;

        if ( ! $user_id || empty( $item ) ) {
            return false;
        }

        return [
            'user_id'   => $user_id,
            'item_name' => $item['name'] ?? '',
            'item_id'   => $item['uuid'] ?? '',
            'time'      => current_time( 'mysql' ),
        ];
    }
}
