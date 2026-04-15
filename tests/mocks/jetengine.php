<?php

if ( ! function_exists('get_post') ) {
    function get_post( $post_id )
    {
        return (object) [
            'ID' => $post_id,
            'post_author' => 1,
            'post_date' => '2026-03-19 00:00:00',
            'post_date_gmt' => '2026-03-19 00:00:00',
            'post_content' => 'Test content',
            'post_title' => 'Test Post',
            'post_excerpt' => 'Excerpt',
            'post_status' => 'publish',
            'comment_status' => 'open',
            'ping_status' => 'open',
            'post_password' => '',
            'post_name' => 'test-post',
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => '2026-03-19 01:00:00',
            'post_modified_gmt' => '2026-03-19 01:00:00',
            'post_content_filtered' => '',
            'post_parent' => 0,
            'guid' => 'http://example.com/?p='.$post_id,
            'menu_order' => 0,
            'post_type' => 'post',
            'post_mime_type' => '',
            'comment_count' => 0,
        ];
    }
}

if ( ! function_exists('maybe_unserialize') ) {
    function maybe_unserialize( $data ) {
        return $data;
    }
}

if ( ! function_exists('wp_is_post_autosave') ) {
    function wp_is_post_autosave( $post_id ) {
        return false;
    }
}

if ( ! function_exists('wp_is_post_revision') ) {
    function wp_is_post_revision( $post_id ) {
        return false;
    }
}

if ( ! function_exists('current_time') ) {
    function current_time( $type ) {
        return '2026-03-19 12:00:00';
    }
}