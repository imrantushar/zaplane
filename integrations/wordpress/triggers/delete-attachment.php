<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class DeleteAttachment extends BaseTrigger {

    public static function get_label(): string {
        return 'Media Deletion';
    }

    public static function get_hook(): string {
        return 'delete_attachment';
    }

    public static function get_output_schema(): array {
        return [
            'attachment_id' => 'integer',
            'user_id'       => 'integer',
            'time'          => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $attachment_id = $hook_args[0] ?? 0;

        if ( ! $attachment_id ) {
            return false;
        }

        return [
            'attachment_id' => $attachment_id,
            'user_id'       => get_current_user_id(),
            'time'          => current_time( 'mysql' ),
        ];
    }
}
