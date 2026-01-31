<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WordPressPluginIntegration
 *
 * Base class for WordPress-native integrations (core WordPress, WooCommerce,
 * ACF, Gravity Forms, etc.).
 *
 * Pre-sets:
 *  - `requires_connection()` → false (WP plugins run locally)
 *  - `get_category()` → 'wordpress'
 *
 * Provides helpers for common WordPress data resolution patterns so junior
 * developers don't need to re-implement post/user/term payload extraction.
 *
 * Example:
 * ```php
 * class GravityForms extends WordPressPluginIntegration {
 *     public static function get_slug(): string { return 'gravity-forms'; }
 *     public static function get_name(): string { return 'Gravity Forms'; }
 *
 *     public static function get_triggers(): array {
 *         return [
 *             'form_submitted' => [
 *                 'label' => 'Form Submitted',
 *                 'hook'  => 'gform_after_submission',
 *             ],
 *         ];
 *     }
 *
 *     public static function resolve_trigger(array $node, array $args) {
 *         $entry = $args[0] ?? null;
 *         $form  = $args[1] ?? null;
 *         if (!$entry || !$form) return false;
 *         return [
 *             'entry_id'  => $entry['id'],
 *             'form_id'   => $form['id'],
 *             'form_title'=> $form['title'],
 *             'fields'    => $entry,
 *         ];
 *     }
 * }
 * ```
 */
abstract class WordPressPluginIntegration extends IntegrationBase {

    /* ---------------------------------------------------------
     * Pre-set: WP plugins don't need external connections
     * --------------------------------------------------------- */

    public static function requires_connection(): bool {
        return false;
    }

    public static function get_category(): string {
        return 'wordpress';
    }

    /* ---------------------------------------------------------
     * Common WordPress Data Resolvers
     * Junior devs can call these from resolve_trigger()
     * --------------------------------------------------------- */

    /**
     * Resolve standard post payload from a post ID.
     *
     * @param int $post_id WordPress post ID.
     * @return array|false  Post payload or false if not found.
     */
    protected static function resolve_post( int $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return false;
        }

        return [
            'post_id'      => $post->ID,
            'post_title'   => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_type'    => $post->post_type,
            'post_status'  => $post->post_status,
            'post_author'  => $post->post_author,
            'post_date'    => $post->post_date,
            'permalink'    => get_permalink( $post->ID ),
        ];
    }

    /**
     * Resolve standard user payload from a user ID.
     *
     * @param int $user_id WordPress user ID.
     * @return array|false  User payload or false if not found.
     */
    protected static function resolve_user( int $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        return [
            'user_id'      => $user->ID,
            'user_login'   => $user->user_login,
            'user_email'   => $user->user_email,
            'display_name' => $user->display_name,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'roles'        => $user->roles,
            'registered'   => $user->user_registered,
        ];
    }

    /**
     * Resolve standard comment payload from a comment ID.
     *
     * @param int $comment_id WordPress comment ID.
     * @return array|false     Comment payload or false if not found.
     */
    protected static function resolve_comment( int $comment_id ) {
        $comment = get_comment( $comment_id );
        if ( ! $comment ) {
            return false;
        }

        return [
            'comment_id'      => $comment->comment_ID,
            'post_id'         => $comment->comment_post_ID,
            'comment_author'  => $comment->comment_author,
            'comment_email'   => $comment->comment_author_email,
            'comment_content' => $comment->comment_content,
            'comment_status'  => $comment->comment_approved,
            'comment_date'    => $comment->comment_date,
        ];
    }

    /**
     * Resolve standard term payload from a term ID.
     *
     * @param int    $term_id  WordPress term ID.
     * @param string $taxonomy Taxonomy slug.
     * @return array|false     Term payload or false if not found.
     */
    protected static function resolve_term( int $term_id, string $taxonomy = '' ) {
        $term = get_term( $term_id, $taxonomy );
        if ( ! $term || is_wp_error( $term ) ) {
            return false;
        }

        return [
            'term_id'   => $term->term_id,
            'name'      => $term->name,
            'slug'      => $term->slug,
            'taxonomy'  => $term->taxonomy,
            'parent'    => $term->parent,
            'count'     => $term->count,
        ];
    }

    /**
     * Resolve standard attachment/media payload from an attachment ID.
     *
     * @param int $attachment_id WordPress attachment ID.
     * @return array|false       Attachment payload or false if not found.
     */
    protected static function resolve_attachment( int $attachment_id ) {
        $attachment = get_post( $attachment_id );
        if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
            return false;
        }

        return [
            'attachment_id' => $attachment_id,
            'title'         => $attachment->post_title,
            'mime_type'     => get_post_mime_type( $attachment_id ),
            'url'           => wp_get_attachment_url( $attachment_id ),
            'alt_text'      => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
            'author'        => $attachment->post_author,
            'date'          => $attachment->post_date,
        ];
    }
}
