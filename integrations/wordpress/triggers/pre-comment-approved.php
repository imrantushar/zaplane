<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class PreCommentApproved extends BaseTrigger {

    public static function get_label(): string {
        return 'Pre-Approve Comment';
    }

    public static function get_hook(): string {
        return 'pre_comment_approved';
    }

    public static function get_output_schema(): array {
        return [
            'approved'    => 'string',
            'commentdata' => 'array',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        return [
            'approved'    => $hook_args[0] ?? '',
            'commentdata' => $hook_args[1] ?? [],
        ];
    }
}
