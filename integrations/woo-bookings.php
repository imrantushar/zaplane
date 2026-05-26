<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WooBookings extends IntegrationBase {

	public static function get_slug(): string {
		return 'woobookings';
	}

	public static function get_name(): string {
		return 'WooCommerce Bookings';
	}

	public static function get_icon(): string {
		return 'woo.svg';
	}

	public static function get_triggers(): array {
		return [
			'booking_created' => [
				'label' => 'Booking Created',
				'hook' => 'woocommerce_new_booking',
			],
			'booking_confirmed' => [
				'label' => 'Booking Confirmed',
				'hook' => 'woocommerce_booking_confirmed',
			],
			'booking_paid' => [
				'label' => 'Booking Paid',
				'hook' => 'woocommerce_booking_paid',
			],
			'booking_cancelled' => [
				'label' => 'Booking Cancelled',
				'hook' => 'woocommerce_booking_cancelled',
			],
			'booking_unpaid' => [
				'label' => 'Booking Unpaid',
				'hook' => 'woocommerce_booking_unpaid',
			],
			'booking_status_changed' => [
				'label' => 'Booking Status Updated',
				'hook' => 'woocommerce_booking_status_changed',
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		if ( ! self::is_bookings_available() ) {
			return false;
		}

		$event = (string) ( $node['event'] ?? ( $node['data']['event'] ?? ( $node['config']['trigger'] ?? '' ) ) );
		if ( '' === $event ) {
			return false;
		}

		switch ( $event ) {
			case 'booking_created':
			case 'booking_confirmed':
			case 'booking_paid':
			case 'booking_cancelled':
			case 'booking_unpaid':
				$booking = self::resolve_booking_from_args( $args );
				if ( ! $booking ) {
					return false;
				}

				$order = self::resolve_order_from_args( $args );
				return self::build_booking_payload(
					$booking,
					[
						'event' => $event,
						'order_id' => $order ? (int) $order->get_id() : (int) self::resolve_booking_order_id( $booking ),
					]
				);

			case 'booking_status_changed':
				$booking = self::resolve_booking_from_args( $args );
				if ( ! $booking ) {
					return false;
				}

				$new_status = (string) ( $args[1] ?? '' );
				$old_status = (string) ( $args[2] ?? '' );

				return self::build_booking_payload(
					$booking,
					[
						'new_status' => self::normalize_booking_status( $new_status ),
						'old_status' => self::normalize_booking_status( $old_status ),
					]
				);
		}//end switch

		return false;
	}

	public static function get_actions(): array {
		return [
			'get_bookings_all' => [ 'label' => 'Get Bookings (All)' ],
			'get_booking_single' => [ 'label' => 'Get Booking (Single)' ],
			'update_booking_status' => [ 'label' => 'Update Booking Status' ],
			'cancel_booking' => [ 'label' => 'Cancel Booking' ],
			'confirm_booking' => [ 'label' => 'Confirm Booking' ],
			'add_booking_note' => [ 'label' => 'Add Booking Note' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$status_options = self::get_booking_status_options();

		$schemas = [
			'get_bookings_all' => [
				[
					'key' => 'limit',
					'label' => 'Limit',
					'type' => 'number',
					'default' => 20,
				],
				[
					'key' => 'page',
					'label' => 'Page',
					'type' => 'number',
					'default' => 1,
				],
				[
					'key' => 'booking_status',
					'label' => 'Booking Status',
					'type' => 'select',
					'options' => $status_options,
				],
			],
			'get_booking_single' => [
				[
					'key' => 'booking_id',
					'label' => 'Booking ID',
					'type' => 'number',
					'required' => true,
				],
			],
			'update_booking_status' => [
				[
					'key' => 'booking_id',
					'label' => 'Booking ID',
					'type' => 'number',
					'required' => true,
				],
				[
					'key' => 'booking_status',
					'label' => 'Booking Status',
					'type' => 'select',
					'options' => $status_options,
					'required' => true,
				],
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'text',
				],
			],
			'cancel_booking' => [
				[
					'key' => 'booking_id',
					'label' => 'Booking ID',
					'type' => 'number',
					'required' => true,
				],
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'text',
				],
			],
			'confirm_booking' => [
				[
					'key' => 'booking_id',
					'label' => 'Booking ID',
					'type' => 'number',
					'required' => true,
				],
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'text',
				],
			],
			'add_booking_note' => [
				[
					'key' => 'booking_id',
					'label' => 'Booking ID',
					'type' => 'number',
					'required' => true,
				],
				[
					'key' => 'note',
					'label' => 'Note',
					'type' => 'text',
					'required' => true,
				],
				[
					'key' => 'is_customer_note',
					'label' => 'Customer Note',
					'type' => 'boolean',
				],
			],
		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		if ( ! self::is_bookings_available() ) {
			return self::error_response( 'WooCommerce Bookings is not available', $input );
		}

		$event = (string) ( $node['data']['event'] ?? ( $node['config']['action'] ?? '' ) );
		$config = $node['data']['config'] ?? ( $node['config']['data'] ?? [] );
		if ( ! is_array( $config ) ) {
			$config = [];
		}

		switch ( $event ) {
			case 'get_bookings_all':
				return self::action_get_bookings_all( $config, $input );
			case 'get_booking_single':
				return self::action_get_booking_single( $config, $input );
			case 'update_booking_status':
				return self::action_update_booking_status( $config, $input );
			case 'cancel_booking':
				return self::action_update_booking_status( array_merge( $config, [ 'booking_status' => 'wc-booking-cancelled' ] ), $input );
			case 'confirm_booking':
				return self::action_update_booking_status( array_merge( $config, [ 'booking_status' => 'wc-booking-confirmed' ] ), $input );
			case 'add_booking_note':
				return self::action_add_booking_note( $config, $input );
		}//end switch

		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	private static function is_bookings_available(): bool {
		return function_exists( 'get_wc_booking' ) || class_exists( 'WC_Booking' );
	}

	private static function action_get_bookings_all( array $config, array $input ): array {
		$limit = max( 1, (int) ( $config['limit'] ?? 20 ) );
		$page = max( 1, (int) ( $config['page'] ?? 1 ) );
		$status = self::normalize_booking_status( (string) ( $config['booking_status'] ?? '' ) );

		$bookings = [];
		$query = [
			'limit' => $limit,
			'page' => $page,
		];
		if ( '' !== $status ) {
			$query['status'] = $status;
		}

		if ( function_exists( 'wc_get_bookings' ) ) {
			$bookings = wc_get_bookings( $query );
		} elseif ( function_exists( 'get_wc_bookings' ) ) {
			$bookings = get_wc_bookings( $query );
		}

		$items = [];
		if ( is_array( $bookings ) ) {
			foreach ( $bookings as $booking ) {
				$resolved = self::resolve_booking( $booking );
				if ( ! $resolved ) {
					continue;
				}
				$items[] = self::build_booking_payload( $resolved );
			}
		}

		return self::main_response( array_merge( $input, [
			'items' => $items,
			'total' => count( $items ),
			'page' => $page,
			'limit' => $limit,
		] ) );
	}

	private static function action_get_booking_single( array $config, array $input ): array {
		$booking_id = (int) ( $config['booking_id'] ?? 0 );
		if ( $booking_id <= 0 ) {
			return self::error_response( 'Booking ID is required', $input );
		}

		$booking = self::resolve_booking( $booking_id );
		if ( ! $booking ) {
			return self::error_response( 'Booking not found', $input );
		}

		return self::main_response( array_merge( $input, [
			'booking' => self::build_booking_payload( $booking ),
		] ) );
	}

	private static function action_update_booking_status( array $config, array $input ): array {
		$booking_id = (int) ( $config['booking_id'] ?? 0 );
		if ( $booking_id <= 0 ) {
			return self::error_response( 'Booking ID is required', $input );
		}

		$target_status = self::normalize_booking_status( (string) ( $config['booking_status'] ?? ( $config['status'] ?? '' ) ) );
		if ( '' === $target_status ) {
			return self::error_response( 'Booking status is required', $input );
		}

		$booking = self::resolve_booking( $booking_id );
		if ( ! $booking ) {
			return self::error_response( 'Booking not found', $input );
		}

		$old_status = method_exists( $booking, 'get_status' ) ? (string) $booking->get_status() : '';
		if ( '' !== $old_status && $old_status === $target_status ) {
			return self::main_response( array_merge( $input, [
				'booking' => self::build_booking_payload( $booking, [
					'old_status' => $old_status,
					'new_status' => $target_status,
				] ),
			] ) );
		}

		$note = (string) ( $config['note'] ?? '' );
		try {
			if ( method_exists( $booking, 'update_status' ) ) {
				$booking->update_status( $target_status, $note );
			} elseif ( method_exists( $booking, 'set_status' ) ) {
				$booking->set_status( $target_status );
				if ( method_exists( $booking, 'save' ) ) {
					$booking->save();
				}
			} else {
				return self::error_response( 'Booking status update API is not available', $input );
			}
		} catch ( \Throwable $e ) {
			return self::error_response( $e->getMessage(), $input );
		}

		return self::main_response( array_merge( $input, [
			'booking' => self::build_booking_payload( $booking, [
				'old_status' => $old_status,
				'new_status' => $target_status,
			] ),
		] ) );
	}

	private static function action_add_booking_note( array $config, array $input ): array {
		$booking_id = (int) ( $config['booking_id'] ?? 0 );
		if ( $booking_id <= 0 ) {
			return self::error_response( 'Booking ID is required', $input );
		}

		$note = trim( (string) ( $config['note'] ?? '' ) );
		if ( '' === $note ) {
			return self::error_response( 'Note is required', $input );
		}

		$booking = self::resolve_booking( $booking_id );
		if ( ! $booking ) {
			return self::error_response( 'Booking not found', $input );
		}

		$is_customer_note = in_array( $config['is_customer_note'] ?? false, [ true, 1, '1', 'true', 'yes', 'on' ], true );

		if ( method_exists( $booking, 'add_order_note' ) ) {
			$booking->add_order_note( $note, $is_customer_note );
		} elseif ( method_exists( $booking, 'add_note' ) ) {
			$booking->add_note( $note );
		} else {
			return self::error_response( 'Booking note API is not available', $input );
		}

		return self::main_response( array_merge( $input, [
			'booking' => self::build_booking_payload( $booking ),
			'note' => $note,
			'is_customer_note' => $is_customer_note,
		] ) );
	}

	private static function resolve_booking( $value ) {
		if ( self::is_booking_object( $value ) ) {
			return $value;
		}

		if ( ! is_numeric( $value ) ) {
			return null;
		}

		$booking_id = (int) $value;
		if ( $booking_id <= 0 ) {
			return null;
		}

		if ( function_exists( 'get_wc_booking' ) ) {
			$booking = get_wc_booking( $booking_id );
			if ( self::is_booking_object( $booking ) ) {
				return $booking;
			}
		}

		if ( class_exists( 'WC_Booking' ) ) {
			try {
				$booking = new \WC_Booking( $booking_id );
				if ( self::is_booking_object( $booking ) && (int) $booking->get_id() > 0 ) {
					return $booking;
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		return null;
	}

	private static function resolve_booking_from_args( array $args ) {
		foreach ( $args as $value ) {
			$booking = self::resolve_booking( $value );
			if ( $booking ) {
				return $booking;
			}
		}

		return null;
	}

	private static function resolve_order( $value ) {
		if ( is_object( $value ) && method_exists( $value, 'get_id' ) && ! self::is_booking_object( $value ) ) {
			return $value;
		}

		if ( ! is_numeric( $value ) || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order = wc_get_order( (int) $value );
		if ( is_object( $order ) && method_exists( $order, 'get_id' ) && ! self::is_booking_object( $order ) ) {
			return $order;
		}

		return null;
	}

	private static function resolve_order_from_args( array $args ) {
		foreach ( $args as $value ) {
			$order = self::resolve_order( $value );
			if ( $order ) {
				return $order;
			}
		}

		return null;
	}

	private static function resolve_booking_order_id( $booking ): int {
		if ( method_exists( $booking, 'get_order_id' ) ) {
			return (int) $booking->get_order_id();
		}

		if ( method_exists( $booking, 'get_order' ) ) {
			$order = $booking->get_order();
			if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
				return (int) $order->get_id();
			}
		}

		return 0;
	}

	private static function is_booking_object( $value ): bool {
		return is_object( $value )
			&& method_exists( $value, 'get_id' )
			&& method_exists( $value, 'get_status' )
			&& (
				method_exists( $value, 'get_start' )
				|| method_exists( $value, 'get_start_date' )
				|| method_exists( $value, 'get_product_id' )
			);
	}

	private static function build_booking_payload( $booking, array $extra = [] ): array {
		return array_merge(
			[
				'booking_id' => (int) $booking->get_id(),
				'status' => (string) $booking->get_status(),
				'order_id' => self::resolve_booking_order_id( $booking ),
				'product_id' => method_exists( $booking, 'get_product_id' ) ? (int) $booking->get_product_id() : 0,
				'customer_id' => method_exists( $booking, 'get_customer_id' ) ? (int) $booking->get_customer_id() : 0,
				'start_date' => self::resolve_booking_date( $booking, 'start' ),
				'end_date' => self::resolve_booking_date( $booking, 'end' ),
				'all_day' => method_exists( $booking, 'get_all_day' ) ? (bool) $booking->get_all_day() : false,
			],
			$extra
		);
	}

	private static function resolve_booking_date( $booking, string $date_type ): string {
		if ( 'start' === $date_type ) {
			if ( method_exists( $booking, 'get_start_date' ) ) {
				return self::format_date_value( $booking->get_start_date() );
			}
			if ( method_exists( $booking, 'get_start' ) ) {
				return self::format_date_value( $booking->get_start() );
			}
			return '';
		}

		if ( method_exists( $booking, 'get_end_date' ) ) {
			return self::format_date_value( $booking->get_end_date() );
		}
		if ( method_exists( $booking, 'get_end' ) ) {
			return self::format_date_value( $booking->get_end() );
		}

		return '';
	}

	private static function format_date_value( $value ): string {
		if ( is_string( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return gmdate( 'Y-m-d H:i:s', (int) $value );
		}

		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'date' ) ) {
				return (string) $value->date( 'Y-m-d H:i:s' );
			}
			if ( method_exists( $value, 'getTimestamp' ) ) {
				return gmdate( 'Y-m-d H:i:s', (int) $value->getTimestamp() );
			}
		}

		return '';
	}

	private static function normalize_booking_status( string $status ): string {
		$status = sanitize_key( $status );
		if ( 0 === strpos( $status, 'wc-booking-' ) ) {
			return substr( $status, strlen( 'wc-booking-' ) );
		}
		if ( 0 === strpos( $status, 'booking-' ) ) {
			return substr( $status, strlen( 'booking-' ) );
		}

		return $status;
	}

	private static function get_booking_status_options(): array {
		return [
			[
				'label' => 'Pending Confirmation',
				'value' => 'wc-booking-pending-confirmation',
			],
			[
				'label' => 'Confirmed',
				'value' => 'wc-booking-confirmed',
			],
			[
				'label' => 'Paid',
				'value' => 'wc-booking-paid',
			],
			[
				'label' => 'Unpaid',
				'value' => 'wc-booking-unpaid',
			],
			[
				'label' => 'Cancelled',
				'value' => 'wc-booking-cancelled',
			],
			[
				'label' => 'Complete',
				'value' => 'wc-booking-complete',
			],
			[
				'label' => 'In Cart',
				'value' => 'wc-booking-in-cart',
			],
		];
	}

	private static function main_response( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	private static function error_response( string $message, array $input = [] ): array {
		return [
			'port' => 'error',
			'data' => array_merge( $input, [ 'error' => $message ] ),
		];
	}
}
