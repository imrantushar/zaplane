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

    protected static function action_approve_comment(array $config): array
    {
       $comment_id = $config['comment_id'] ?? 0;
        $result = wp_set_comment_status( $comment_id, 'approve' );
        if (is_wp_error( $result ) || !$result) {
            return static::error("Failed to approve comment ID {$config['comment_id']}");
        }
        return static::success(['comment_id' => $comment_id,
        'status'     => 'approved',]);
    }

    protected static function action_unapproved_comment(array $config): array
    {
        $comment_id = $config['comment_id'] ?? 0;
        $result = wp_set_comment_status( $comment_id, 'hold' );
        if (is_wp_error( $result ) || !$result) {
            return static::error("Failed to unapproved comment ID {$config['comment_id']}");
        }
        return static::success([
            'comment_id' => $comment_id,
            'status'     => 'hold',
        ]);
    }

    protected static function action_mark_comment_spam(array $config): array
    {
        $comment_id = $config['comment_id'] ?? 0;
        $result = wp_spam_comment( $comment_id );
        if (is_wp_error( $result ) || !$result) {
            return static::error("Failed to mark comment ID {$comment_id}");
        }
        return static::success([
            'comment_id' => $comment_id,
            'status'     => 'spam',
        ]);
    }

    protected static function action_unmark_comment_spam(array $config): array
    {
        $comment_id = $config['comment_id'] ?? 0;
        $result = wp_unspam_comment( $comment_id );
        if (is_wp_error( $result ) || !$result) {
            return static::error("Failed to unmark comment ID {$comment_id}");
        }
        return static::success([
            'comment_id' => $comment_id,
            'status'     => 'unspam',
        ]);
    }

    protected static function action_delete_trash_comment(array $config): array
    {
        $result = wp_delete_comment($config['comment_id'], true);

        if (!$result) {
            return static::error("Failed to delete trash comment ID {$config['comment_id']}");
        }

        return static::success(['comment_id' => $config['comment_id']]);
    }

    protected static function action_delete_comment(array $config): array
    {
        $result = wp_delete_comment($config['comment_id'], true);

        if (!$result) {
            return static::error("Failed to delete comment ID {$config['comment_id']}");
        }

        return static::success(['comment_id' => $config['comment_id']]);
    }

    protected static function action_trash_comment(array $config): array
    {
        $result = wp_trash_comment($config['comment_id']);

        if (!$result) {
            return static::error("Failed to trash comment ID {$config['comment_id']}");
        }

        return static::success(['comment_id' => $config['comment_id']]);
    }

    protected static function action_restore_comment(array $config): array
    {
        $result = wp_untrash_comment($config['comment_id']);

        if (!$result) {
            return static::error("Failed to restore comment ID {$config['comment_id']}");
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
