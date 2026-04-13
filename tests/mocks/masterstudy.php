<?php

if ( ! function_exists('get_user_by') ) {
    function get_user_by( $field, $id ) {
        return (object) [
            'ID' => $id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'user_login' => 'johndoe',
            'user_email' => 'john@example.com',
            'nickname' => 'Johnny',
            'display_name' => 'John Doe',
            'roles' => ['subscriber'],
        ];
    }
}

if ( ! function_exists('get_post') ) {
    function get_post( $id ) {
        return (object) [
            'ID' => $id,
            'post_title' => "Test Post $id",
            'post_content' => 'Lorem ipsum content',
        ];
    }
}

if ( ! function_exists('get_permalink') ) {
    function get_permalink( $id ) {
        return "https://example.com/post/$id";
    }
}

if ( ! function_exists('get_avatar_url')) {
    function get_avatar_url( $id ) {
        return "https://example.com/avatar/$id.png";
    }
}

if ( ! function_exists('current_time') ) {
    function current_time( $type ) {
        return date('Y-m-d H:i:s');
    }
}