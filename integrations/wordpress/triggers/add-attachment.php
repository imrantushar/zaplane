<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Integrations\Wordpress\WordpressPayloadHelpers;

class AddAttachment extends BaseTrigger {

    public static function get_label(): string {
        return 'Add Attachment';
    }

    public static function get_hook(): string {
        return 'add_attachment';
    }

    public static function get_output_schema(): array {
        return [
            'attachment_id' => 'integer',
            'post_title'    => 'string',
            'mime_type'     => 'string',
            'url'           => 'string',
            'user_id'       => 'integer',
            'time'          => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        return WordpressPayloadHelpers::resolve_media( $hook_args[0] ?? 0 );
    }
}
