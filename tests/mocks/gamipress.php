<?php

if (!function_exists('get_post')) {
    function get_post($id) {
        global $zaplane_wp_posts;

        return $zaplane_wp_posts[$id] ?? null;
    }
}

if (!class_exists('WP_Post')) {
    class WP_Post {
        public $ID;
        public $post_type;
        public $post_name;
        public $post_parent;
        public $post_author;
        public $post_content;
        public $post_title;

        public function __construct($data = []) {
            foreach ($data as $k => $v) {
                $this->$k = $v;
            }
        }
    }
}