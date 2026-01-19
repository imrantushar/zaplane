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

    protected static function action_update_title(array $config): array
    {
        $id = wp_update_post([
            'ID'         => $config['post_id'] ,
            'post_title' => $config['post_title'],
        ]);

        if (is_wp_error($id)) return static::error($id->get_error_message());
        return static::success(['post_id' => $id]);
    }

    protected static function action_update_status(array $config): array
    {
        $id = wp_update_post([
            'ID'          => $config['post_id'] ,
            'post_status' => $config['post_status'],
        ]);

        if (is_wp_error($id)) return static::error($id->get_error_message());
        return static::success(['post_id' => $id]);
    }

    protected static function action_update_post(array $config): array
    {
        $id = wp_update_post([
            'ID'           => $config['post_id'],
            'post_title'   => $config['post_title'],
            'post_type'    => $config['post_type'],
            'post_content' => $config['post_content'],
            'post_status'  => $config['post_status'],
        ]);

        if (is_wp_error($id)) return static::error($id->get_error_message());
        return static::success(['post_id' => $id]);
    }

    protected static function action_duplicate_post(array $config): array
    {
        $post_id     = $config['post_id'] ?? 0;
        $new_title   = $config['new_title'] ?? '';
        $status      = $config['status'] ?? 'draft';
        $post        = get_post($post_id);
        $final_title = $new_title ?: $post->post_title . ' (copy)';
        $new_post_id = wp_insert_post([
            'post_type'    => $post->post_type,
            'post_title'   => $final_title,
            'post_content' => $post->post_content,
            'post_status'  => $status,
            'post_author'  => $post->post_author,
        ], true);

        if (is_wp_error($new_post_id))return static::error($new_post_id->get_error_message());
        WordpressHelpers::copy_taxonomies($post_id, $new_post_id);
        WordpressHelpers::copy_meta($post_id, $new_post_id);
        return static::success(['original_id' => $post_id,'new_id' => $new_post_id,'new_title' => $final_title,
        ]);
    }
    
    protected static function action_schedule_post(array $config): array
    {
        $post_id       = $config['post_id'] ?? 0;
        $schedule_date = $config['schedule_date'] ?? '';
        $status        = $config['status'] ?? 'future';
        $post_data     = wp_update_post([
            'ID'            => $post_id,
            'post_status'   => $status,
            'post_date'     => $schedule_date,
            'post_date_gmt' => get_gmt_from_date( $schedule_date ),
        ], true);

        if (is_wp_error($post_data))return static::error($post_data->get_error_message());
        return static::success(['post_id' => $post_id,]);
    }

    protected static function action_unschedule_post(array $config): array
    {
        $post_id       = $config['post_id'] ?? 0;
        $post_data     = wp_update_post([
            'ID'            => $post_id,
            'post_status'   => 'draft',
            'post_date'     => current_time('mysql'),
            'post_date_gmt' => current_time('mysql', 1),
            ], true);

        if (is_wp_error($post_data))return static::error($post_data->get_error_message());
        return static::success(['post_id' => $post_id,]);
    }

    protected static function action_update_post_feature_image(array $config): array
    {
        $post_id  = $config['post_id'] ?? 0;
        $image_id = $config['image_id'] ?? 0;
        if ( $post_id && $image_id ) {
            set_post_thumbnail( $post_id, $image_id );
        }
        return static::success(['post_id' => $post_id,]);
    }

    protected static function action_change_post_author(array $config): array
    {
        $post_id   = $config['post_id'] ?? 0;
        $author_id = $config['author_id'] ?? 0;
        $result    = wp_update_post([ 
                'ID'        => $post_id, 
                'post_author' => $author_id 
            ], true);
        
        if (is_wp_error($result))return static::error($result->get_error_message());
        return static::success(['post_id' => $post_id,]);
    }

    protected static function action_trash_post(array $config): array
    {
        $result = wp_trash_post($config['post_id'] ?? 0);
        if (!$result) return static::error("Failed to untrash post");
        return static::success(['post_id' => $config['post_id']]);
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
            WordpressHelpers::normalize_list($config['terms'] ?? []),
            $config['taxonomy'] ?? '',
            $config['append'] ?? false
        );
        return static::success(['added' => $added]);
    }

    protected static function action_remove_taxonomy_from_post(array $config): array
    {
        $removed = wp_remove_object_terms(
            $config['post_id'] ?? 0,
            WordpressHelpers::normalize_list($config['terms'] ?? []),
            $config['taxonomy'] ?? ''
        );
        return static::success(['removed' => $removed]);
    }

    protected static function action_bulk_assign_terms_to_posts(array $config): array
    {
        $results = [];
        $post_ids = WordpressHelpers::normalize_list($config['post_ids'] ?? []);
        $terms = WordpressHelpers::normalize_list($config['terms'] ?? []);
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
        $post_ids = WordpressHelpers::normalize_list($config['post_ids'] ?? []);
        $terms = WordpressHelpers::normalize_list($config['terms'] ?? []);
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
            WordpressHelpers::normalize_list($config['categories'] ?? []),
            $config['append'] ?? false
        );
        return static::success(['added' => $added]);
    }

    protected static function action_add_tags_to_post(array $config): array
    {
        $added = wp_set_post_tags(
            $config['post_id'] ?? 0,
            WordpressHelpers::normalize_list($config['tags'] ?? []),
            $config['append'] ?? false
        );
        return static::success(['added' => $added]);
    }

    protected static function action_remove_tags_from_post(array $config): array
    {
        $removed = wp_remove_object_terms(
            $config['post_id'] ?? 0,
            WordpressHelpers::normalize_list($config['tags'] ?? []),
            'post_tag'
        );
        return static::success(['removed' => $removed]);
    }
}
