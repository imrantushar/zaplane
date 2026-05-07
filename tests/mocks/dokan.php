<?php

if ( ! class_exists( 'DokanTestStore' ) ) {
	class DokanTestStore {
		private static array $vendors   = [];
		private static array $products  = [];
		private static array $orders    = [];
		private static array $withdraws = [];

		public static function reset(): void {
			self::$vendors = [
				801 => [
					'id' => 801,
					'user_login' => 'vendor_one',
					'user_email' => 'vendor-one@example.com',
					'display_name' => 'Vendor One',
					'store_info' => [
						'store_name' => 'Vendor One Store',
						'phone' => '+8801700000001',
						'dokan_enable_selling' => 'yes',
					],
				],
				802 => [
					'id' => 802,
					'user_login' => 'vendor_two',
					'user_email' => 'vendor-two@example.com',
					'display_name' => 'Vendor Two',
					'store_info' => [
						'store_name' => 'Vendor Two Store',
						'phone' => '+8801700000002',
						'dokan_enable_selling' => 'no',
					],
				],
			];

			self::$products = [
				9101 => [
					'id' => 9101,
					'vendor_id' => 801,
					'title' => 'Dokan Product One',
				],
				9102 => [
					'id' => 9102,
					'vendor_id' => 802,
					'title' => 'Dokan Product Two',
				],
			];

			self::$orders = [
				9201 => [
					'order_id' => 9201,
					'vendor_id' => 801,
				],
				9202 => [
					'order_id' => 9202,
					'vendor_id' => 802,
				],
			];

			self::$withdraws = [
				5001 => [
					'id' => 5001,
					'user_id' => 801,
					'amount' => 120.50,
					'method' => 'paypal',
					'status' => 0,
					'date' => '2026-04-10 10:00:00',
					'note' => 'Pending request',
					'details' => [ 'email' => 'vendor-one@example.com' ],
				],
				5002 => [
					'id' => 5002,
					'user_id' => 801,
					'amount' => 75.00,
					'method' => 'bank',
					'status' => 1,
					'date' => '2026-04-11 11:00:00',
					'note' => 'Approved request',
					'details' => [ 'account' => '123456' ],
				],
				5003 => [
					'id' => 5003,
					'user_id' => 802,
					'amount' => 45.00,
					'method' => 'paypal',
					'status' => 2,
					'date' => '2026-04-12 12:00:00',
					'note' => 'Cancelled request',
					'details' => [ 'reason' => 'Invalid method' ],
				],
			];
		}

		public static function getVendor( int $vendor_id ): ?array {
			return self::$vendors[ $vendor_id ] ?? null;
		}

		public static function allVendors( array $args = [] ): array {
			$items = array_values( self::$vendors );
			$search = trim( (string) ( $args['search'] ?? '' ) );
			if ( '' !== $search ) {
				$search = strtolower( str_replace( '*', '', $search ) );
				$items = array_values( array_filter(
					$items,
					static function ( $vendor ) use ( $search ) {
						$haystack = strtolower(
							(string) ( $vendor['display_name'] ?? '' ) . ' ' .
							(string) ( $vendor['user_login'] ?? '' ) . ' ' .
							(string) ( $vendor['user_email'] ?? '' ) . ' ' .
							(string) ( $vendor['store_info']['store_name'] ?? '' )
						);

						return false !== strpos( $haystack, $search );
					}
				) );
			}

			$limit  = max( 1, (int) ( $args['number'] ?? 20 ) );
			$paged  = max( 1, (int) ( $args['paged'] ?? 1 ) );
			$offset = ( $paged - 1 ) * $limit;

			return array_slice( $items, $offset, $limit );
		}

		public static function countVendors( array $args = [] ): int {
			$search = trim( (string) ( $args['search'] ?? '' ) );
			if ( '' === $search ) {
				return count( self::$vendors );
			}

			return count( self::allVendors( [ 'search' => $search, 'number' => PHP_INT_MAX ] ) );
		}

		public static function setVendorEnabled( int $vendor_id, bool $enabled ): void {
			if ( ! isset( self::$vendors[ $vendor_id ] ) ) {
				return;
			}

			self::$vendors[ $vendor_id ]['store_info']['dokan_enable_selling'] = $enabled ? 'yes' : 'no';
		}

		public static function isVendorEnabled( int $vendor_id ): bool {
			$vendor = self::getVendor( $vendor_id );
			if ( ! $vendor ) {
				return false;
			}

			return 'yes' === ( $vendor['store_info']['dokan_enable_selling'] ?? '' );
		}

		public static function getProduct( int $product_id ): ?array {
			return self::$products[ $product_id ] ?? null;
		}

		public static function getVendorIdByProduct( int $product_id ): int {
			return (int) ( self::$products[ $product_id ]['vendor_id'] ?? 0 );
		}

		public static function getOrder( int $order_id ): ?array {
			return self::$orders[ $order_id ] ?? null;
		}

		public static function getWithdraw( int $withdraw_id ): ?array {
			return self::$withdraws[ $withdraw_id ] ?? null;
		}

		public static function allWithdraws( array $args = [] ): array {
			$items = array_values( self::$withdraws );
			$user_id = isset( $args['user_id'] ) ? (int) $args['user_id'] : 0;
			$status = isset( $args['status'] ) ? (int) $args['status'] : null;

			if ( $user_id > 0 ) {
				$items = array_values( array_filter(
					$items,
					static fn( $row ) => (int) ( $row['user_id'] ?? 0 ) === $user_id
				) );
			}

			if ( null !== $status ) {
				$items = array_values( array_filter(
					$items,
					static fn( $row ) => (int) ( $row['status'] ?? 0 ) === $status
				) );
			}

			$limit  = max( 1, (int) ( $args['limit'] ?? 20 ) );
			$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );

			return array_slice( $items, $offset, $limit );
		}
	}

	DokanTestStore::reset();
}

if ( ! class_exists( 'Dokan_Test_Vendor' ) ) {
	class Dokan_Test_Vendor {
		private int $vendor_id = 0;

		public function __construct( int $vendor_id ) {
			$this->vendor_id = $vendor_id;
		}

		public function get_id(): int {
			return $this->vendor_id;
		}

		public function get_shop_info(): array {
			$vendor = DokanTestStore::getVendor( $this->vendor_id );
			return (array) ( $vendor['store_info'] ?? [] );
		}

		public function make_active(): array {
			DokanTestStore::setVendorEnabled( $this->vendor_id, true );
			return $this->to_array();
		}

		public function make_inactive(): array {
			DokanTestStore::setVendorEnabled( $this->vendor_id, false );
			return $this->to_array();
		}

		public function to_array(): array {
			$vendor = DokanTestStore::getVendor( $this->vendor_id );
			if ( ! $vendor ) {
				return [];
			}

			return [
				'id' => (int) $vendor['id'],
				'display_name' => (string) ( $vendor['display_name'] ?? '' ),
				'user_email' => (string) ( $vendor['user_email'] ?? '' ),
				'shop_info' => (array) ( $vendor['store_info'] ?? [] ),
			];
		}
	}
}

if ( ! class_exists( 'Dokan_Test_Vendor_Manager' ) ) {
	class Dokan_Test_Vendor_Manager {
		public function get( $vendor_id ) {
			$vendor_id = (int) $vendor_id;
			return DokanTestStore::getVendor( $vendor_id ) ? new Dokan_Test_Vendor( $vendor_id ) : null;
		}

		public function get_vendors( array $args = [] ): array {
			$rows = DokanTestStore::allVendors( $args );

			return array_map(
				static fn( $row ) => new Dokan_Test_Vendor( (int) ( $row['id'] ?? 0 ) ),
				$rows
			);
		}

		public function get_total(): int {
			return DokanTestStore::countVendors();
		}
	}
}

if ( ! class_exists( 'Dokan_Test_Withdraw' ) ) {
	class Dokan_Test_Withdraw {
		private array $data = [];

		public function __construct( $value ) {
			if ( is_array( $value ) ) {
				$this->data = $value;
				return;
			}

			$withdraw_id = (int) $value;
			$this->data = $withdraw_id > 0 ? ( DokanTestStore::getWithdraw( $withdraw_id ) ?? [] ) : [];
		}

		public function get_id(): int {
			return (int) ( $this->data['id'] ?? 0 );
		}

		public function get_user_id(): int {
			return (int) ( $this->data['user_id'] ?? 0 );
		}

		public function get_amount(): float {
			return (float) ( $this->data['amount'] ?? 0 );
		}

		public function get_method(): string {
			return (string) ( $this->data['method'] ?? '' );
		}

		public function get_status() {
			return $this->data['status'] ?? 0;
		}

		public function get_date(): string {
			return (string) ( $this->data['date'] ?? '' );
		}

		public function get_note(): string {
			return (string) ( $this->data['note'] ?? '' );
		}

		public function get_details() {
			return $this->data['details'] ?? [];
		}

		public function get_data(): array {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'Dokan_Test_Withdraw_Manager' ) ) {
	class Dokan_Test_Withdraw_Manager {
		public function get( $id ) {
			$withdraw_id = (int) $id;
			return DokanTestStore::getWithdraw( $withdraw_id ) ? new Dokan_Test_Withdraw( $withdraw_id ) : null;
		}

		public function all( array $args = [] ): array {
			$items = DokanTestStore::allWithdraws( $args );
			return array_map(
				static fn( $row ) => new Dokan_Test_Withdraw( $row ),
				$items
			);
		}

		public function get_status_name( $code ): string {
			switch ( (int) $code ) {
				case 0:
					return 'pending';
				case 1:
					return 'approved';
				case 2:
					return 'cancelled';
			}

			return '';
		}
	}
}

if ( ! class_exists( 'Dokan_Test_App' ) ) {
	class Dokan_Test_App {
		public Dokan_Test_Vendor_Manager $vendor;
		public Dokan_Test_Withdraw_Manager $withdraw;

		public function __construct() {
			$this->vendor   = new Dokan_Test_Vendor_Manager();
			$this->withdraw = new Dokan_Test_Withdraw_Manager();
		}
	}
}

if ( ! function_exists( 'dokan' ) ) {
	function dokan() {
		static $app = null;
		if ( null === $app ) {
			$app = new Dokan_Test_App();
		}
		return $app;
	}
}

if ( ! function_exists( 'dokan_get_store_info' ) ) {
	function dokan_get_store_info( $seller_id ) {
		$vendor = DokanTestStore::getVendor( (int) $seller_id );
		return $vendor ? (array) ( $vendor['store_info'] ?? [] ) : [];
	}
}

if ( ! function_exists( 'dokan_get_sellers' ) ) {
	function dokan_get_sellers( $args = [] ) {
		$vendors = DokanTestStore::allVendors( (array) $args );
		$users = array_map(
			static function ( $vendor ) {
				return (object) [
					'ID' => (int) ( $vendor['id'] ?? 0 ),
					'user_login' => (string) ( $vendor['user_login'] ?? '' ),
					'user_email' => (string) ( $vendor['user_email'] ?? '' ),
					'display_name' => (string) ( $vendor['display_name'] ?? '' ),
				];
			},
			$vendors
		);

		return [
			'users' => $users,
			'count' => DokanTestStore::countVendors( (array) $args ),
		];
	}
}

if ( ! function_exists( 'dokan_get_store_url' ) ) {
	function dokan_get_store_url( $user_id, $tab = '' ) {
		$user_id = (int) $user_id;
		$url = 'https://example.com/store/' . $user_id;
		if ( '' !== (string) $tab ) {
			$url .= '/' . trim( (string) $tab, '/' );
		}
		return $url;
	}
}

if ( ! function_exists( 'dokan_get_vendor_by_product' ) ) {
	function dokan_get_vendor_by_product( $product, $get_vendor_id = false ) {
		$product_id = 0;
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			$product_id = (int) $product->get_id();
		} else {
			$product_id = (int) $product;
		}

		$vendor_id = DokanTestStore::getVendorIdByProduct( $product_id );
		if ( $get_vendor_id ) {
			return $vendor_id;
		}

		return dokan()->vendor->get( $vendor_id );
	}
}

if ( ! function_exists( 'dokan_get_seller_id_by_order' ) ) {
	function dokan_get_seller_id_by_order( $order ) {
		$order_id = 0;
		if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
			$order_id = (int) $order->get_id();
		} else {
			$order_id = (int) $order;
		}

		$data = DokanTestStore::getOrder( $order_id );
		return (int) ( $data['vendor_id'] ?? 0 );
	}
}

if ( ! function_exists( 'dokan_is_seller_enabled' ) ) {
	function dokan_is_seller_enabled( $vendor_id ) {
		return DokanTestStore::isVendorEnabled( (int) $vendor_id );
	}
}
