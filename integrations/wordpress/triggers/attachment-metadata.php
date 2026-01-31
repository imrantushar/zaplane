<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class AttachmentMetadata extends BaseTrigger {

    public static function get_label(): string {
        return 'Generate Attachment Metadata';
    }

    public static function get_hook(): string {
        return 'wp_generate_attachment_metadata';
    }

    public static function get_output_schema(): array {
        return [
            'attachment_id' => 'integer',
            'post_title'    => 'string',
            'mime_type'     => 'string',
            'url'           => 'string',
            'metadata'      => 'array',
            'user_id'       => 'integer',
            'time'          => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $metadata      = $hook_args[0] ?? [];
        $attachment_id = $hook_args[1] ?? 0;

        if ( ! $attachment_id || empty( $metadata ) ) {
            return false;
        }

        $attachment = get_post( $attachment_id );
        if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
            return false;
        }

        return [
            'attachment_id' => $attachment_id,
            'post_title'    => $attachment->post_title,
            'mime_type'     => get_post_mime_type( $attachment_id ),
            'url'           => wp_get_attachment_url( $attachment_id ),
            'metadata'      => $metadata,
            'user_id'       => get_current_user_id(),
            'time'          => current_time( 'mysql' ),
        ];
    }
}
