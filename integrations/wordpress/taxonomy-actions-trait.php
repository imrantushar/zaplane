<?php
namespace Zaplane\Integrations\Wordpress;

use Zaplane\Traits\ActionResponseTrait;

trait TaxonomyActionsTrait
{
    protected static function action_get_term(array $config): array
    {
        return static::success(get_term($config['term_id'] ?? 0, $config['taxonomy'] ?? ''));
    }

    protected static function action_get_terms_by_taxonomy(array $config): array
    {
        return static::success(get_terms([
            'taxonomy' => $config['taxonomy'] ?? '',
            'hide_empty' => false
        ]));
    }

    protected static function action_get_term_by_field(array $config): array
    {
        return static::success(get_term_by(
            $config['field'] ?? '',
            $config['value'] ?? '',
            $config['taxonomy'] ?? ''
        ));
    }

    protected static function action_create_term(array $config): array
    {
        return static::success(wp_insert_term(
            $config['name'] ?? '',
            $config['taxonomy'] ?? '',
            [
                'slug' => $config['slug'] ?? '',
                'parent' => $config['parent'] ?? 0,
                'description' => $config['description'] ?? '',
            ]
        ));
    }

    protected static function action_update_term(array $config): array
    {
        return static::success(wp_update_term(
            $config['term_id'] ?? 0,
            $config['taxonomy'] ?? '',
            [
                'name' => $config['name'] ?? '',
                'slug' => $config['slug'] ?? '',
                'description' => $config['description'] ?? '',
                'parent' => $config['parent'] ?? 0,
            ]
        ));
    }

    protected static function action_delete_term(array $config): array
    {
        return static::success(wp_delete_term($config['term_id'] ?? 0, $config['taxonomy'] ?? ''));
    }

    protected static function action_register_taxonomy(array $config): array
    {
        return static::success(register_taxonomy(
            $config['taxonomy'] ?? '',
            WordpressHelpers::normalize_list($config['object_type'] ?? []),
            WordpressHelpers::normalize_taxonomy_args($config['args'] ?? [])
        ));
    }

    protected static function action_unregister_taxonomy(array $config): array
    {
        return static::success(unregister_taxonomy($config['taxonomy'] ?? ''));
    }

    protected static function action_get_taxonomies(): array
    {
        return static::success(get_taxonomies([], 'objects'));
    }

    protected static function action_get_taxonomy(array $config): array
    {
        return static::success(get_taxonomy($config['taxonomy'] ?? ''));
    }
}
