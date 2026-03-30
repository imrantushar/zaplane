<?php

if (!class_exists('WP_Post')) {
    class WP_Post {
        public $ID;
        public $post_author;
        public $post_type;
        public $post_title;
        public $post_content;
        public $post_status;
        public $post_date;
        public $post_date_gmt;
        public $post_modified;
        public $post_modified_gmt;
        public $post_excerpt;
        public $comment_status;
        public $ping_status;
        public $post_password;
        public $post_name;
        public $post_parent;
        public $guid;
        public $menu_order;
        public $post_mime_type;
        public $comment_count;

        public function __construct($id, $author = 1, $type = 'wpuf_post') {
            $this->ID = $id;
            $this->post_author = $author;
            $this->post_type = $type;
            $this->post_title = "Test Post $id";
            $this->post_content = "Content $id";
            $this->post_status = 'publish';
            $this->post_date = $this->post_date_gmt = '2026-01-01 00:00:00';
            $this->post_modified = $this->post_modified_gmt = '2026-01-01 00:00:00';
            $this->post_excerpt = '';
            $this->comment_status = 'closed';
            $this->ping_status = 'closed';
            $this->post_password = '';
            $this->post_name = "test-post-$id";
            $this->post_parent = 0;
            $this->guid = "http://example.com/test-post-$id";
            $this->menu_order = 0;
            $this->post_mime_type = '';
            $this->comment_count = 0;
        }
    }
}

if (!function_exists('get_post')) {
    function get_post($id) {
        if ($id === 999) return null; // Non-existent post
        return new WP_Post($id, 1, $id === 200 ? 'wpuf_coupon' : 'wpuf_post');
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id) {
        return [
            'meta_key_1' => ['meta_value_1'],
            'meta_key_2' => ['meta_value_2'],
        ];
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        return (object)[
            'ID' => $user_id,
            'user_login' => 'johndoe',
            'user_email' => 'john@example.com',
            'display_name' => 'John Doe',
            'nickname' => 'johnny',
            'roles' => ['subscriber'],
            'user_url' => '',
            'user_registered' => '2026-01-01 00:00:00',
        ];
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key) {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];
        return $data[$key] ?? '';
    }
}

if (!function_exists('get_avatar_url')) {
    function get_avatar_url($user_id) {
        return "http://example.com/avatar/{$user_id}.png";
    }
}