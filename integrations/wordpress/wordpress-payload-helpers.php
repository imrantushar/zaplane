<?php

namespace Zaplane\Integrations\Wordpress;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WordPress Payload Helpers
 *
 * Shared helper methods for resolving trigger payloads.
 */
class WordpressPayloadHelpers {

    /**
     * Resolve post data from post ID.
     *
     * @param int $post_id
     * @return array|false
     */
    public static function resolve_post( int $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return false;
        }

        return [
            'post_id'    => $post->ID,
            'post_title' => $post->post_title,
            'post_type'  => $post->post_type,
            'status'     => $post->post_status,
        ];
    }

    /**
     * Resolve media/attachment data from attachment ID.
     *
     * @param int $attachment_id
     * @return array|false
     */
    public static function resolve_media( int $attachment_id ) {
        if ( ! $attachment_id ) {
            return false;
        }

        $attachment = get_post( $attachment_id );
        if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
            return false;
        }

        return [
            'attachment_id' => $attachment_id,
            'post_title'    => $attachment->post_title,
            'mime_type'     => get_post_mime_type( $attachment_id ),
            'url'           => wp_get_attachment_url( $attachment_id ),
            'user_id'       => get_current_user_id(),
            'time'          => current_time( 'mysql' ),
        ];
    }

    /**
     * Resolve comment data from comment ID.
     *
     * @param int $comment_id
     * @return array|false
     */
    public static function resolve_comment( int $comment_id ) {
        $comment = get_comment( $comment_id );
        if ( ! $comment ) {
            return false;
        }

        return [
            'comment_id' => $comment->comment_ID,
            'post_id'    => $comment->comment_post_ID,
            'content'    => $comment->comment_content,
            'status'     => $comment->comment_approved,
            'author'     => $comment->comment_author,
        ];
    }

    /**
     * Resolve user data from user ID.
     *
     * @param int $user_id
     * @return array|false
     */
    public static function resolve_user( int $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        return [
            'user_id'      => $user->ID,
            'user_login'   => $user->user_login,
            'user_email'   => $user->user_email,
            'display_name' => $user->display_name,
            'roles'        => $user->roles,
        ];
    }

    /**
     * Resolve term data from term ID.
     *
     * @param int    $term_id
     * @param string $taxonomy
     * @param int    $tt_id
     * @return array|false
     */
    public static function resolve_term( int $term_id, string $taxonomy = '', int $tt_id = 0 ) {
        $term = get_term( $term_id, $taxonomy );
        if ( ! $term || is_wp_error( $term ) ) {
            return false;
        }

        return [
            'term_id'          => $term->term_id,
            'name'             => $term->name,
            'slug'             => $term->slug,
            'taxonomy'         => $term->taxonomy,
            'term_taxonomy_id' => $tt_id ?: $term->term_taxonomy_id,
        ];
    }
}
