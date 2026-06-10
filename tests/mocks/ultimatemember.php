<?php
// Mock WordPress core functions

if ( ! function_exists( 'get_userdata' ) ) {
    function get_userdata( $user_id ) {
        return new WP_User( (int) $user_id );
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
    #[\AllowDynamicProperties]
    class WP_User {
        public $ID = 0;
        public $role = '';
        public $roles = [];
        public $caps = [];
        public $allcaps = [];
        public $user_login = 'testuser';
        public $user_email = 'test@example.com';
        public $nickname = 'Tester';
        public $display_name = 'Test User';
        public $first_name = 'Test';
        public $last_name = 'User';

        public function __construct( $id = 0 ) {
            $this->ID = (int) $id;
            if ( empty( $this->roles ) ) {
                $this->roles = [ 'subscriber' ];
            }
        }

        public function set_role( $role ) {
            $this->role = (string) $role;
            $this->roles = '' === $this->role ? [] : [ $this->role ];
        }

        public function add_role( $role ) {
            $role = (string) $role;
            if ( '' !== $role && ! in_array( $role, $this->roles, true ) ) {
                $this->roles[] = $role;
            }
        }

        public function remove_role( $role ) {
            $this->roles = array_values(
                array_filter(
                    $this->roles,
                    function ( $item ) use ( $role ) {
                        return (string) $item !== (string) $role;
                    }
                )
            );
        }

        public function add_cap( $cap, $grant = true ) {
            $cap = (string) $cap;
            if ( '' === $cap ) {
                return;
            }

            $this->caps[ $cap ] = (bool) $grant;
            $this->allcaps[ $cap ] = (bool) $grant;
        }

        public function remove_cap( $cap ) {
            $cap = (string) $cap;
            unset( $this->caps[ $cap ], $this->allcaps[ $cap ] );
        }
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
            ],
            'role_names' => [
                'administrator' => 'Administrator',
                'editor'        => 'Editor',
                'author'        => 'Author',
                'contributor'   => 'Contributor',
                'subscriber'    => 'Subscriber',
            ],
            'role_key' => 'wp_user_roles',
        ];
    }
}
