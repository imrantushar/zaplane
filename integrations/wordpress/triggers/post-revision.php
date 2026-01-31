<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Integrations\Wordpress\WordpressPayloadHelpers;

class PostRevision extends BaseTrigger {

    public static function get_label(): string {
        return 'Revision Creation';
    }

    public static function get_hook(): string {
        return '_wp_put_post_revision';
    }

    public static function get_output_schema(): array {
        return [
            'post_id'    => 'integer',
            'post_title' => 'string',
            'post_type'  => 'string',
            'status'     => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        return WordpressPayloadHelpers::resolve_post( $hook_args[0] ?? 0 );
    }
}
