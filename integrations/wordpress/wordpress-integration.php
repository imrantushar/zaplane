<?php

namespace Zaplane\Integrations\Wordpress;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\WordPressPluginIntegration;

/**
 * WordPress Integration
 *
 * Identity and dynamic queries only.
 * Triggers and actions are in separate modular files.
 */
class WordpressIntegration extends WordPressPluginIntegration {

    use QueryTrait;

    public static function get_slug(): string {
        return 'wordpress';
    }

    public static function get_name(): string {
        return 'WordPress';
    }

    public static function get_icon(): string {
        return 'wordpress';
    }

    /**
     * Dynamic data queries for field options in the UI.
     */
    public static function get_dynamic_queries(): array {
        return [
            'post_types'       => [ self::class, 'query_post_types' ],
            'posts'            => [ self::class, 'query_posts' ],
            'terms'            => [ self::class, 'query_terms' ],
            'users'            => [ self::class, 'query_users' ],
            'taxonomies'       => [ self::class, 'query_taxonomies' ],
            'categories'       => [ self::class, 'query_categories' ],
            'roles'            => [ self::class, 'query_roles' ],
            'caps'             => [ self::class, 'query_caps' ],
            'active_plugins'   => [ self::class, 'query_active_plugins' ],
            'inactive_plugins' => [ self::class, 'query_deactivate_plugins' ],
            'deactivate_theme' => [ self::class, 'query_deactivate_theme' ],
        ];
    }
}
