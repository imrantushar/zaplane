<?php

if ( ! class_exists( 'GFFormsModel' ) ) {
    class GFFormsModel {
        public static function get_forms( $public_only = true ) {
            return [
                (object)[ "id" => 10, "title" => "Test Form 1" ],
                (object)[ "id" => 11, "title" => "Test Form 2" ],
            ];
        }
    }
}