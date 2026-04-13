<?php

if ( ! class_exists( 'CartflowsTestStore' ) ) {
	class CartflowsTestStore {
		private static array $flow_steps = [];
		private static array $step_data = [];
		private static array $order_data = [];

		public static function reset(): void {
			self::$flow_steps = [
				601 => [
					[ 'id' => 701, 'type' => 'landing' ],
					[ 'id' => 702, 'type' => 'checkout' ],
					[ 'id' => 703, 'type' => 'thankyou' ],
				],
			];

			self::$step_data = [
				701 => [
					'flow_id'      => 601,
					'step_type'    => 'landing',
					'next_step_id' => 702,
				],
				702 => [
					'flow_id'      => 601,
					'step_type'    => 'checkout',
					'next_step_id' => 703,
				],
				703 => [
					'flow_id'      => 601,
					'step_type'    => 'thankyou',
					'next_step_id' => 0,
				],
			];

			self::$order_data = [
				9001 => [
					'id'            => 9001,
					'status'        => 'processing',
					'total'         => 149.50,
					'currency'      => 'USD',
					'customer_id'   => 301,
					'billing_email' => 'buyer@example.com',
					'meta'          => [
						'_wcf_checkout_id' => 702,
						'_order_key'       => 'wc_order_test_9001',
					],
					'items'         => [
						[
							'product_id'   => 701,
							'variation_id' => 0,
							'product_name' => 'Landing Pack',
							'quantity'     => 1,
							'subtotal'     => 99.50,
							'total'        => 99.50,
							'subtotal_tax' => 0,
							'tax_class'    => '',
							'tax_status'   => 'taxable',
						],
						[
							'product_id'   => 702,
							'variation_id' => 0,
							'product_name' => 'Checkout Bonus',
							'quantity'     => 1,
							'subtotal'     => 50.00,
							'total'        => 50.00,
							'subtotal_tax' => 0,
							'tax_class'    => '',
							'tax_status'   => 'taxable',
						],
					],
				],
			];
		}

		public static function getFlowSteps( int $flow_id ) {
			return self::$flow_steps[ $flow_id ] ?? false;
		}

		public static function getStepData( int $step_id ): array {
			return self::$step_data[ $step_id ] ?? [];
		}

		public static function getNextStepId( int $step_id ): int {
			return (int) ( self::$step_data[ $step_id ]['next_step_id'] ?? 0 );
		}

		public static function getFlowIdFromStep( int $step_id ): int {
			return (int) ( self::$step_data[ $step_id ]['flow_id'] ?? 0 );
		}

		public static function getOrderData( int $order_id ): array {
			return self::$order_data[ $order_id ] ?? [];
		}
	}

	CartflowsTestStore::reset();
}

if ( ! class_exists( 'Cartflows_Test_Utils' ) ) {
	class Cartflows_Test_Utils {
		public function get_flow_steps( $flow_id ) {
			return CartflowsTestStore::getFlowSteps( (int) $flow_id );
		}

		public function get_next_step_id( $flow_id, $step_id ) {
			unset( $flow_id );
			return CartflowsTestStore::getNextStepId( (int) $step_id );
		}

		public function get_flow_id_from_step_id( $step_id ) {
			return CartflowsTestStore::getFlowIdFromStep( (int) $step_id );
		}
	}
}

if ( ! class_exists( 'Cartflows_Test_Step' ) ) {
	class Cartflows_Test_Step {
		private int $step_id;
		private array $data;

		public function __construct( int $step_id ) {
			$this->step_id = $step_id;
			$this->data = CartflowsTestStore::getStepData( $step_id );
		}

		public function get_flow_id() {
			return (int) ( $this->data['flow_id'] ?? 0 );
		}

		public function get_step_type() {
			return (string) ( $this->data['step_type'] ?? '' );
		}

		public function get_direct_next_step_id() {
			return (int) ( $this->data['next_step_id'] ?? 0 );
		}

		public function get_step_id() {
			return $this->step_id;
		}
	}
}

if ( ! class_exists( 'Cartflows_Test_Order_Item' ) ) {
	class Cartflows_Test_Order_Item {
		private array $data;

		public function __construct( array $data ) {
			$this->data = $data;
		}

		public function get_product_id() {
			return (int) ( $this->data['product_id'] ?? 0 );
		}

		public function get_variation_id() {
			return (int) ( $this->data['variation_id'] ?? 0 );
		}

		public function get_name() {
			return (string) ( $this->data['product_name'] ?? '' );
		}

		public function get_quantity() {
			return (int) ( $this->data['quantity'] ?? 0 );
		}

		public function get_subtotal() {
			return (float) ( $this->data['subtotal'] ?? 0 );
		}

		public function get_total() {
			return (float) ( $this->data['total'] ?? 0 );
		}

		public function get_subtotal_tax() {
			return (float) ( $this->data['subtotal_tax'] ?? 0 );
		}

		public function get_tax_class() {
			return (string) ( $this->data['tax_class'] ?? '' );
		}

		public function get_tax_status() {
			return (string) ( $this->data['tax_status'] ?? '' );
		}
	}
}

if ( ! class_exists( 'Cartflows_Test_Order_Meta' ) ) {
	class Cartflows_Test_Order_Meta {
		private string $key;
		private $value;

		public function __construct( string $key, $value ) {
			$this->key = $key;
			$this->value = $value;
		}

		public function get_data(): array {
			return [
				'key'   => $this->key,
				'value' => $this->value,
			];
		}
	}
}

if ( ! class_exists( 'Cartflows_Test_Order' ) ) {
	class Cartflows_Test_Order {
		private array $data;

		public function __construct( int $order_id ) {
			$this->data = CartflowsTestStore::getOrderData( $order_id );
		}

		public function get_id() {
			return (int) ( $this->data['id'] ?? 0 );
		}

		public function get_status() {
			return (string) ( $this->data['status'] ?? '' );
		}

		public function get_total() {
			return (float) ( $this->data['total'] ?? 0 );
		}

		public function get_currency() {
			return (string) ( $this->data['currency'] ?? 'USD' );
		}

		public function get_customer_id() {
			return (int) ( $this->data['customer_id'] ?? 0 );
		}

		public function get_billing_email() {
			return (string) ( $this->data['billing_email'] ?? '' );
		}

		public function get_items(): array {
			$items = $this->data['items'] ?? [];
			$result = [];
			foreach ( $items as $item ) {
				$result[] = new Cartflows_Test_Order_Item( is_array( $item ) ? $item : [] );
			}
			return $result;
		}

		public function get_meta( string $key, bool $single = true ) {
			unset( $single );
			return $this->data['meta'][ $key ] ?? null;
		}

		public function get_meta_data(): array {
			$rows = [];
			foreach ( (array) ( $this->data['meta'] ?? [] ) as $key => $value ) {
				$rows[] = new Cartflows_Test_Order_Meta( (string) $key, $value );
			}
			return $rows;
		}
	}
}

if ( ! class_exists( 'Cartflows_Loader' ) ) {
	class Cartflows_Loader {
		private static ?self $instance = null;
		public $utils;

		private function __construct() {
			$this->utils = new Cartflows_Test_Utils();
		}

		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}
	}
}

if ( ! function_exists( 'wcf' ) ) {
	function wcf() {
		return Cartflows_Loader::get_instance();
	}
}

if ( ! function_exists( 'wcf_get_step' ) ) {
	function wcf_get_step( $step_id ) {
		return new Cartflows_Test_Step( (int) $step_id );
	}
}

if ( ! function_exists( 'wcf_get_step_type' ) ) {
	function wcf_get_step_type( $step_id ) {
		$data = CartflowsTestStore::getStepData( (int) $step_id );
		return (string) ( $data['step_type'] ?? '' );
	}
}

if ( ! defined( 'CARTFLOWS_FLOW_POST_TYPE' ) ) {
	define( 'CARTFLOWS_FLOW_POST_TYPE', 'cartflows_flow' );
}

if ( ! defined( 'CARTFLOWS_STEP_POST_TYPE' ) ) {
	define( 'CARTFLOWS_STEP_POST_TYPE', 'cartflows_step' );
}
