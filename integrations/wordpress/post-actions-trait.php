<?php
namespace Zaplane\Integrations\Wordpress;

use Zaplane\Traits\ActionResponseTrait;

trait PostActionsTrait
{
    use ActionResponseTrait;

    protected static function action_create_post(array $config): array
    {
        $id = wp_insert_post([
            'post_title'   => $config['post_title'] ?? '',
            'post_content' => $config['post_content'] ?? '',
            'post_status'  => $config['post_status'] ?? 'draft',
            'post_type'    => $config['post_type'] ?? 'post',
        ]);

        if (is_wp_error($id)) return static::error($id->get_error_message());
        return static::success(['post_id' => $id]);
    }

    protected static function action_untrash_post(array $config): array
    {
        $result = wp_untrash_post($config['post_id'] ?? 0);
        if (!$result) return static::error("Failed to untrash post");
        return static::success(['post_id' => $config['post_id']]);
    }

    protected static function action_set_featured_image(array $config): array
    {
        $result = set_post_thumbnail($config['post_id'] ?? 0, $config['attachment_id'] ?? 0);
        if (!$result) return static::error("Failed to set featured image");
        return static::success([
            'post_id' => $config['post_id'],
            'attachment_id' => $config['attachment_id']
        ]);
    }

    protected static function action_add_taxonomy_to_post(array $config): array
    {
        $added = wp_set_object_terms(
            $config['post_id'] ?? 0,
            Helper::normalize_list($config['terms'] ?? []),
            $config['taxonomy'] ?? '',
            $config['append'] ?? false
        );
        return static::success(['added' => $added]);
    }

    protected static function action_remove_taxonomy_from_post(array $config): array
    {
        $removed = wp_remove_object_terms(
            $config['post_id'] ?? 0,
            Helper::normalize_list($config['terms'] ?? []),
            $config['taxonomy'] ?? ''
        );
        return static::success(['removed' => $removed]);
    }

    protected static function action_bulk_assign_terms_to_posts(array $config): array
    {
        $results = [];
        $post_ids = Helper::normalize_list($config['post_ids'] ?? []);
        $terms = Helper::normalize_list($config['terms'] ?? []);
        $taxonomy = $config['taxonomy'] ?? '';
        $append = $config['append'] ?? false;

        foreach ($post_ids as $post_id) {
            $results[$post_id] = wp_set_object_terms($post_id, $terms, $taxonomy, $append);
        }

        return static::success($results);
    }

    protected static function action_bulk_remove_terms_from_posts(array $config): array
    {
        $results = [];
        $post_ids = Helper::normalize_list($config['post_ids'] ?? []);
        $terms = Helper::normalize_list($config['terms'] ?? []);
        $taxonomy = $config['taxonomy'] ?? '';

        foreach ($post_ids as $post_id) {
            $results[$post_id] = wp_remove_object_terms($post_id, $terms, $taxonomy);
        }

        return static::success($results);
    }

    protected static function action_add_category_to_post(array $config): array
    {
        $added = wp_set_post_categories(
            $config['post_id'] ?? 0,
            Helper::normalize_list($config['categories'] ?? []),
            $config['append'] ?? false
        );
        return static::success(['added' => $added]);
    }

    protected static function action_add_tags_to_post(array $config): array
    {
        $added = wp_set_post_tags(
            $config['post_id'] ?? 0,
            Helper::normalize_list($config['tags'] ?? []),
            $config['append'] ?? false
        );
        return static::success(['added' => $added]);
    }

    protected static function action_remove_tags_from_post(array $config): array
    {
        $removed = wp_remove_object_terms(
            $config['post_id'] ?? 0,
            Helper::normalize_list($config['tags'] ?? []),
            'post_tag'
        );
        return static::success(['removed' => $removed]);
    }
}
