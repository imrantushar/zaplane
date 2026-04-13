<?php

if ( ! class_exists( 'FrmForm' ) ) {
    class FrmForm {
        public $id;
        public $name;

        public function __construct( $id = 1, $name = 'Test Form' ) {
            $this->id   = $id;
            $this->name = $name;
        }

        public static function getAll() {
            return [
                new self( 1, 'Contact Form' ),
                new self( 2, 'Survey Form' ),
            ];
        }
    }
}

if ( ! class_exists( 'FrmField' ) ) {
    class FrmField {
        public $id;
        public $field_key;
        public $name;
        public $type;
        public $default_value = [];

        public function __construct( $id, $key, $name, $type, $default_value = [] ) {
            $this->id = $id;
            $this->field_key = $key;
            $this->name = $name;
            $this->type = $type;
            $this->default_value = $default_value;
        }

        public static function get_all_for_form( $form_id ) {
            return [
                new self( 1, 'name_1', 'Name', 'name' ),
                new self( 2, 'email_1', 'Email', 'text' ),
            ];
        }
    }
}

if ( ! class_exists( 'FrmFieldsHelper' ) ) {
    class FrmFieldsHelper {
        public static function get_form_fields( $form_id ) {
            return FrmField::get_all_for_form( $form_id );
        }
    }
}

if ( ! class_exists( 'FrmEntryValues' )) {
    class FrmEntryValues {
        private $entry_id;

        public function __construct( $entry_id ) {
            $this->entry_id = $entry_id;
        }

        public function get_field_values() {
            return [
                1 => new class {
                    public function get_saved_value() {
                        return ['first'=>'John','middle'=>'M','last'=>'Doe'];
                    }
                },
                2 => new class {
                    public function get_saved_value() {
                        return 'john@example.com';
                    }
                },
            ];
        }
    }
}