<?php

namespace Groundhogg;

class Contact {
    private $id;
    public function __construct( $id ) { $this->id = $id; }
    public function get_id() { return $this->id; }
    public function get_first_name() { return 'John'; }
    public function get_last_name() { return 'Doe'; }
    public function get_email() { return 'john@example.com'; }
    public function get_optin_status() { return 'subscribed'; }
    public function get_date_created() { return date('Y-m-d H:i:s'); }
    public function get_owner_id() { return 1; }
}

class Tag {
    private $id;
    public function __construct( $id ) { $this->id = $id; }
    public function exists() { return true; }
    public function get_id() { return $this->id; }
    public function get_name() { return 'Test Tag'; }
    public function get_slug() { return 'test-tag'; }
}