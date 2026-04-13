<?php

if ( ! class_exists( 'LD_User' ) ) {
    class LD_User
    {
        public $ID;
        public $first_name   = 'Test';
        public $last_name    = 'User';
        public $user_login   = 'testuser';
        public $user_email   = 'test@example.com';
        public $nickname     = 'Tester';
        public $display_name = 'Test User';
        public $roles        = ['subscriber'];

        public function __construct( $id )
        {
            $this->ID = $id;
        }
    }
}

if ( ! class_exists( 'LD_Course' ) ) {
    class LD_Course
    {
        public $ID;
        public $post_title;

        public function __construct( $id )
        {
            $this->ID = $id;
            $this->post_title = "Course $id";
        }
    }
}

if ( ! class_exists( 'LD_Lesson' ) ) {
    class LD_Lesson
    {
        public $ID;
        public $post_title;

        public function __construct($id)
        {
            $this->ID = $id;
            $this->post_title = "Lesson $id";
        }
    }
}

if ( ! class_exists( 'LD_Topic' ) ) {
    class LD_Topic
    {
        public $ID;
        public $post_title;

        public function __construct( $id )
        {
            $this->ID = $id;
            $this->post_title = "Topic $id";
        }
    }
}

if ( ! function_exists( 'get_user_by' ) ) {
    function get_user_by( $type, $id )
    {
        return new \LD_User( $id );
    }
}

if ( ! function_exists( 'get_post' ) ) {
    function get_post( $id )
    {
        if ( $id < 100 ) {
            return new \LD_Course( $id );
        } elseif ( $id < 200 ) {
            return new \LD_Lesson( $id );
        } else {
            return new \LD_Topic( $id );
        }
    }
}

if ( ! function_exists( 'get_the_title' ) ) {
    function get_the_title( $id )
    {
        return "Post Title $id";
    }
}

if ( ! function_exists( 'get_permalink' ) ) {
    function get_permalink( $id )
    {
        return "https://example.com/post/$id";
    }
}

if (!function_exists( 'get_avatar_url' ) ) {
    function get_avatar_url( $id )
    {
        return "https://example.com/avatar/$id.png";
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type )
    {
        return date('Y-m-d H:i:s');
    }
}

if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id()
    {
        return 1;
    }
}