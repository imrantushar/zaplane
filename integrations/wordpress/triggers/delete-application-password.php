<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class DeleteApplicationPassword extends BaseTrigger {

    public static function get_label(): string {
        return 'Delete Application Password';
    }

    public static function get_hook(): string {
        return 'wp_delete_application_password';
    }

    public static function get_output_schema(): array {
        return [
            'user_id' => 'integer',
            'uuid'    => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $user_id = $hook_args[0] ?? 0;
        $uuid    = $hook_args[1] ?? '';

        if ( ! $user_id ) {
            return false;
        }

        return [
            'user_id' => $user_id,
            'uuid'    => $uuid,
        ];
    }
}
