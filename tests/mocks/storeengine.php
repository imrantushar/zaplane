<?php

if ( ! class_exists( 'Storeengine_Order' ) ) {
    class Storeengine_Order
    {
        public static $instances = [];
        private $id;
        private $data;

        public function __construct( $id, $data = [] )
        {
            $this->id   = $id;
            $this->data = $data;
            self::$instances[ $id ] = $this;
        }

        public function get_id() { return $this->id; }
        public function get_order_number() { return $this->id; }
        public function get_status() { return $this->data['status'] ?? 'pending'; }
        public function get_total() { return $this->data['total'] ?? 0; }
        public function get_currency() { return $this->data['currency'] ?? 'USD'; }
        public function get_payment_method() { return $this->data['payment_method'] ?? ''; }
        public function get_billing_email() { return $this->data['customer_email'] ?? ''; }
        public function get_billing_first_name() { return explode(' ', ($this->data['customer_name'] ?? ''))[0] ?? ''; }
        public function get_billing_last_name() { return explode(' ', ($this->data['customer_name'] ?? ''))[1] ?? ''; }
        public function get_items() { return $this->data['items'] ?? []; }
    }
}

if ( ! function_exists( 'storeengine_get_order' ) ) {
    function storeengine_get_order( $order_id ) {
        return \Storeengine_Order::$instances[ $order_id ] ?? null;
    }
}