<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class SaveAttachment extends BaseTrigger {

    public static function get_label(): string {
        return 'Attachment Save';
    }

    public static function get_hook(): string {
        return 'attachment_fields_to_save';
    }

    public static function get_output_schema(): array {
        return [
            'attachment_id' => 'integer',
            'post_title'    => 'string',
            'mime_type'     => 'string',
            'url'           => 'string',
            'user_id'       => 'integer',
            'time'          => 'string',
            'fields'        => 'array',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $post_id    = $hook_args[0] ?? 0;
        $attachment = $hook_args[1] ?? [];

        if ( ! $post_id ) {
            return false;
        }

        $attachment_post = get_post( $post_id );
        if ( ! $attachment_post || $attachment_post->post_type !== 'attachment' ) {
            return false;
        }

        return [
            'attachment_id' => $post_id,
            'post_title'    => $attachment_post->post_title,
            'mime_type'     => get_post_mime_type( $post_id ),
            'url'           => wp_get_attachment_url( $post_id ),
            'user_id'       => get_current_user_id(),
            'time'          => current_time( 'mysql' ),
            'fields'        => $attachment,
        ];
    }
}
