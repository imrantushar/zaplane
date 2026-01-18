<?php
namespace Zaplane\Integrations\Wordpress;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Traits\ActionResponseTrait;

trait CommentActionsTrait
{
    use ActionResponseTrait; // include success/error helpers

    protected static function action_create_comment(array $config): array
    {
        $comment_id = wp_insert_comment([
            'comment_post_ID'      => $config['post_id'],
            'comment_author'       => $config['author_name'],
            'comment_author_email' => $config['author_email'],
            'comment_content'      => $config['content'],
            'comment_approved'     => 1,
        ]);

        if (is_wp_error($comment_id)) {
            return static::error($comment_id->get_error_message());
        }

        return static::success(['comment_id' => $comment_id]);
    }

    protected static function action_reply_comment(array $config): array
    {
        $parent = get_comment($config['parent_id']);

        if (!$parent) {
            return static::error("Parent comment ID {$config['parent_id']} not found");
        }

        $comment_id = wp_insert_comment([
            'comment_post_ID'      => $parent->comment_post_ID,
            'comment_parent'       => $config['parent_id'],
            'comment_author'       => $config['author_name'],
            'comment_author_email' => $config['author_email'],
            'comment_content'      => $config['content'],
            'comment_approved'     => 1,
        ]);

        if (is_wp_error($comment_id)) {
            return static::error($comment_id->get_error_message());
        }

        return static::success([
            'comment_id' => $comment_id,
            'parent_id'  => $config['parent_id']
        ]);
    }

    protected static function action_delete_comment(array $config): array
    {
        $result = wp_delete_comment($config['comment_id'], true);

        if (!$result) {
            return static::error("Failed to delete comment ID {$config['comment_id']}");
        }

        return static::success(['comment_id' => $config['comment_id']]);
    }

    protected static function action_untrash_comment(array $config): array
    {
        $result = wp_untrash_comment($config['comment_id']);

        if (!$result) {
            return static::error("Failed to untrash comment ID {$config['comment_id']}");
        }

        return static::success(['comment_id' => $config['comment_id']]);
    }
}
