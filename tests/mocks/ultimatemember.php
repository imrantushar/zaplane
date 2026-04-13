<?php
// Mock WordPress core functions

if ( ! function_exists( 'get_userdata' ) ) {
    function get_userdata( $user_id ) {
        return (object) [
            'ID'           => $user_id,
            'user_login'   => 'testuser',
            'user_email'   => 'test@example.com',
            'nickname'     => 'Tester',
            'display_name' => 'Test User',
            'roles'        => ['subscriber'], // default WP role
        ];
    }
}

if ( ! function_exists( 'get_users' ) ) {
    function get_users() {
        return [
            (object)[ 'ID'=>1, 'display_name'=>'Test User', 'user_email'=>'test@example.com' ],
        ];
    }
}

if ( ! function_exists( 'get_avatar_url' ) ) {
    function get_avatar_url( $user_id ) {
        return 'https://example.com/avatar.png';
    }
}

if ( ! class_exists( 'WP_User' ) ) {
    class WP_User {
        public $ID;
        public $role;
        public function __construct( $id ) { $this->ID = $id; }
        public function set_role( $role ) { $this->role = $role; }
    }
}

if ( ! function_exists( 'wp_roles' ) ) {
    function wp_roles() {
        return (object)[
            'roles' => [
                'administrator' => ['name'=>'Administrator'],
                'editor'        => ['name'=>'Editor'],
                'author'        => ['name'=>'Author'],
                'contributor'   => ['name'=>'Contributor'],
                'subscriber'    => ['name'=>'Subscriber'],
            ]
        ];
    }
}