<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Integrations\Wordpress\WordpressPayloadHelpers;

class EditComment extends BaseTrigger {

    public static function get_label(): string {
        return 'Edit Comment';
    }

    public static function get_hook(): string {
        return 'edit_comment';
    }

    public static function get_output_schema(): array {
        return [
            'comment_id' => 'integer',
            'post_id'    => 'integer',
            'content'    => 'string',
            'status'     => 'string',
            'author'     => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        return WordpressPayloadHelpers::resolve_comment( $hook_args[0] ?? 0 );
    }
}
