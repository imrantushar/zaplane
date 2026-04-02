<?php

if ( ! class_exists('WPCF7_ContactForm')) {
    class WPCF7_ContactForm {

        public $id;
        private $properties = [
            'status' => 'publish'
        ];

        public function __construct( $id = 0 ) {
            $this->id = $id;
        }

        public function id() {
            return $this->id;
        }

        public function title() {
            return "Test Form";
        }

        public function prop( $key ) {
            return $this->properties[ $key ] ?? null;
        }

        public function locale() {
            return 'en_US';
        }
    }
}

if ( ! class_exists('WPCF7_Submission' )) {
    class WPCF7_Submission {

        private static $instance;
        private $posted_data = [];

        public function __construct($form = null, $args = []) {
            $this->posted_data = $args['posted_data'] ?? [];
            self::$instance = $this;
        }

        public static function get_instance() {
            return self::$instance;
        }

        public function get_posted_data() {
            return $this->posted_data;
        }

        public function uploaded_files() {
            return [];
        }

        public function get_meta($key) {
            return 0;
        }
    }
}