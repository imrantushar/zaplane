<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class GenerateRewriteRules extends BaseTrigger {

    public static function get_label(): string {
        return 'Rewrite Rules Generated';
    }

    public static function get_hook(): string {
        return 'generate_rewrite_rules';
    }

    public static function get_output_schema(): array {
        return [
            'event' => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        return [
            'event' => 'generate_rewrite_rules',
        ];
    }
}
