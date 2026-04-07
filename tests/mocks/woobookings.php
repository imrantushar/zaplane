<?php

namespace {
	if ( ! class_exists( 'WooBookingsTestStore' ) ) {
		class WooBookingsTestStore {
			private static array $bookings = [];
			private static int $next_id = 1200;

			public static function reset(): void {
				self::$next_id = 1200;
				self::$bookings = [
					1101 => [
						'id' => 1101,
						'status' => 'confirmed',
						'order_id' => 501,
						'product_id' => 901,
						'customer_id' => 701,
						'start_date' => '2026-04-10 10:00:00',
						'end_date' => '2026-04-10 12:00:00',
						'all_day' => false,
						'notes' => [],
					],
					1102 => [
						'id' => 1102,
						'status' => 'unpaid',
						'order_id' => 502,
						'product_id' => 902,
						'customer_id' => 702,
						'start_date' => '2026-04-12 08:00:00',
						'end_date' => '2026-04-12 09:00:00',
						'all_day' => false,
						'notes' => [],
					],
				];
			}

			public static function allBookings(): array {
				return array_values( self::$bookings );
			}

			public static function getBooking( int $id ): ?array {
				return self::$bookings[ $id ] ?? null;
			}

			public static function saveBooking( array $data ): int {
				$id = (int) ( $data['id'] ?? 0 );
				if ( $id <= 0 ) {
					$id = ++self::$next_id;
				}

				$data['id'] = $id;
				self::$bookings[ $id ] = array_merge( self::$bookings[ $id ] ?? [], $data );
				return $id;
			}
		}

		WooBookingsTestStore::reset();
	}

	if ( ! class_exists( 'WC_Booking' ) ) {
		class WC_Booking {
			private array $data = [];

			public function __construct( int $id = 0 ) {
				$this->data = $id > 0 ? ( WooBookingsTestStore::getBooking( $id ) ?? [] ) : [];
			}

			public function get_id(): int {
				return (int) ( $this->data['id'] ?? 0 );
			}

			public function get_status(): string {
				return (string) ( $this->data['status'] ?? '' );
			}

			public function get_order_id(): int {
				return (int) ( $this->data['order_id'] ?? 0 );
			}

			public function get_product_id(): int {
				return (int) ( $this->data['product_id'] ?? 0 );
			}

			public function get_customer_id(): int {
				return (int) ( $this->data['customer_id'] ?? 0 );
			}

			public function get_start() {
				return strtotime( (string) ( $this->data['start_date'] ?? '' ) ) ?: 0;
			}

			public function get_end() {
				return strtotime( (string) ( $this->data['end_date'] ?? '' ) ) ?: 0;
			}

			public function get_start_date() {
				return (string) ( $this->data['start_date'] ?? '' );
			}

			public function get_end_date() {
				return (string) ( $this->data['end_date'] ?? '' );
			}

			public function get_all_day(): bool {
				return (bool) ( $this->data['all_day'] ?? false );
			}

			public function set_status( string $status ): void {
				$this->data['status'] = $status;
			}

			public function update_status( string $status, string $note = '' ): void {
				$this->data['status'] = $status;
				if ( '' !== $note ) {
					$this->add_order_note( $note );
				}
				$this->save();
			}

			public function add_order_note( string $note, bool $is_customer_note = false ) {
				$this->data['notes'][] = [
					'note' => $note,
					'is_customer_note' => $is_customer_note,
				];
				$this->save();
				return count( $this->data['notes'] );
			}

			public function save(): int {
				$this->data['id'] = WooBookingsTestStore::saveBooking( $this->data );
				return (int) $this->data['id'];
			}
		}
	}

	if ( ! function_exists( 'get_wc_booking' ) ) {
		function get_wc_booking( $booking_id ) {
			$booking_id = (int) $booking_id;
			if ( $booking_id <= 0 ) {
				return null;
			}

			return WooBookingsTestStore::getBooking( $booking_id ) ? new WC_Booking( $booking_id ) : null;
		}
	}

	if ( ! function_exists( 'wc_get_bookings' ) ) {
		function wc_get_bookings( $args = [] ) {
			$items = array_map(
				static fn( $row ) => new WC_Booking( (int) ( $row['id'] ?? 0 ) ),
				WooBookingsTestStore::allBookings()
			);

			$status = sanitize_key( (string) ( $args['status'] ?? '' ) );
			$status = preg_replace( '/^(wc-booking-|booking-)/', '', $status );
			if ( '' !== $status ) {
				$items = array_values( array_filter(
					$items,
					static fn( $booking ) => $booking->get_status() === $status
				) );
			}

			$limit = (int) ( $args['limit'] ?? 20 );
			$page = max( 1, (int) ( $args['page'] ?? 1 ) );
			$offset = ( $page - 1 ) * $limit;
			if ( $limit > 0 ) {
				$items = array_slice( $items, $offset, $limit );
			}

			return $items;
		}
	}
}
