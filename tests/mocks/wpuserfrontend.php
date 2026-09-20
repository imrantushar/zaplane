<?php

if ( ! class_exists('WP_Post') ) {
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

        public function __construct( $id = 0, $author = 1, $type = 'wpuf_post' ) {
            // Allow `new WP_Post( [ 'ID' => 10, 'post_type' => 'foo', ... ] )`
            // so tests that pass an associative array of fields work too.
            if ( is_array( $id ) ) {
                $data = $id;
                $id   = $data['ID'] ?? 0;
            } else {
                $data = [];
            }
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
            // Apply per-field overrides last so callers can set any of the
            // properties declared on this class.
            foreach ( $data as $key => $value ) {
                $this->$key = $value;
            }
        }
    }
}

if ( ! function_exists( 'get_post' ) ) {
    function get_post( $id ) {
        // Honour per-test fixtures seeded via $GLOBALS['zaplane_wp_posts'] so
        // integrations that need specific post titles/types can still work
        // even though this priority mock would otherwise win. Tests that opt
        // into the fixture should set $GLOBALS['zaplane_wp_posts_strict'] = true
        // to make missing ids return null instead of the generic WP_Post stub.
        global $zaplane_wp_posts;
        if ( isset( $zaplane_wp_posts[ $id ] ) ) {
            return $zaplane_wp_posts[ $id ];
        }
        if ( ! empty( $GLOBALS['zaplane_wp_posts_strict'] ) ) {
            return null;
        }
        if ( $id === 999 ) return null;
        return new WP_Post( $id, 1, $id === 200 ? 'wpuf_coupon' : 'wpuf_post' );
    }
}

if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $post_id, $key = '', $single = false ) {
        // Per-test override: $GLOBALS['zaplane_post_meta'] may be an array of
        // all-meta (keyed by meta key with value as array), or keyed by post_id
        // when more granularity is needed.
        if ( isset( $GLOBALS['zaplane_post_meta'][ $post_id ] ) ) {
            $all = $GLOBALS['zaplane_post_meta'][ $post_id ];
        } elseif ( isset( $GLOBALS['zaplane_post_meta'] ) && is_array( $GLOBALS['zaplane_post_meta'] ) && ! array_filter( array_keys( $GLOBALS['zaplane_post_meta'] ), 'is_int' ) ) {
            // String-keyed top-level → shared for any post id.
            $all = $GLOBALS['zaplane_post_meta'];
        } else {
            $all = [
                'meta_key_1' => ['meta_value_1'],
                'meta_key_2' => ['meta_value_2'],
            ];
        }

        if ( '' === (string) $key ) {
            return $all;
        }
        $value = $all[ $key ] ?? '';
        if ( $single ) {
            return is_array( $value ) ? ( $value[0] ?? '' ) : $value;
        }
        return is_array( $value ) ? $value : [ $value ];
    }
}

if ( ! function_exists( 'get_userdata' ) ) {
    function get_userdata( $user_id ) {
        // Return a WP_User-like object so callers can use either property
        // access (display_name) OR role methods (add_role/remove_role).
        // We can't return a real WP_User without colliding with the WPMocks
        // class, so wrap in stdClass but include role method shims when
        // role-actions trait checks via method_exists.
        return (object)[
            'ID' => $user_id,
            'user_login' => 'johndoe',
            'user_email' => 'john@example.com',
            'display_name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'nickname' => 'johnny',
            'roles' => ['subscriber'],
            'user_url' => 'https://example.com/johndoe',
            'user_registered' => '2026-01-01 00:00:00',
        ];
    }
}

if ( ! function_exists( 'get_user_meta' ) ) {
    function get_user_meta( $user_id, $key = '', $single = false ) {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        if ( '' === (string) $key ) {
            return $data;
        }

        if ( array_key_exists( $key, $data ) ) {
            return $single ? $data[ $key ] : [ $data[ $key ] ];
        }

        // This file is loaded as a priority mock, so it defines get_user_meta for
        // every test, not just this integration's. Keys it does not own fall
        // through to the shared store, otherwise anything that writes user meta
        // reads back an empty string.
        return \Zaplane\Tests\WPMocks::getUserMeta( (int) $user_id, (string) $key, (bool) $single );
    }
}

if ( ! function_exists( 'get_avatar_url' ) ) {
    function get_avatar_url( $user_id ) {
        return "http://example.com/avatar/{$user_id}.png";
    }
}
