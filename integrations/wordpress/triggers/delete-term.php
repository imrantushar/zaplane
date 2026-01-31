<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;
use Zaplane\Integrations\Wordpress\WordpressPayloadHelpers;

class DeleteTerm extends BaseTrigger {

    public static function get_label(): string {
        return 'Delete Term';
    }

    public static function get_hook(): string {
        return 'delete_term';
    }

    public static function get_output_schema(): array {
        return [
            'term_id'          => 'integer',
            'name'             => 'string',
            'slug'             => 'string',
            'taxonomy'         => 'string',
            'term_taxonomy_id' => 'integer',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        return WordpressPayloadHelpers::resolve_term(
            $hook_args[0] ?? 0,
            $hook_args[2] ?? '',
            $hook_args[1] ?? 0
        );
    }
}
