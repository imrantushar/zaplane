<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Eventscalendar extends IntegrationBase {

	public static function get_slug(): string {
		return 'eventscalendar'; }

	public static function get_name(): string {
		return 'The Events Calendar';
	}

	public static function get_icon(): string {
		return 'events-calendar.svg';
	}

	public static function get_triggers(): array {
		return [
			'attendEvent'          => [
				'label' => 'User attended an event',
				'hook'  => 'event_tickets_checkin'
			],
			'attendeeRegistered'  => [
				'label' => 'Attendee registered for an event',
				'hook' => 'event_tickets_rsvp_attendee_created'
			],
			'newAttendee'         => [
				'label' => 'New attendee registered',
				'hook' => 'event_tickets_rsvp_tickets_generated_for_product'
			],
			'attendeeRegisteredWc' => [
				'label' => 'Attendee registered via WooCommerce',
				'hook' => 'tribe_tickets_attendee_repository_create_attendee_for_ticket_after_create'
			],
		];
	}

	public static function get_trigger_sample_output( string $event ): array {
		$event_data = [
			'event_id'    => 410,
			'event_title' => 'Annual Tech Conference 2026',
			'event_url'   => 'https://example.com/events/annual-tech-conference-2026/',
		];

		$attendee = [
			'attendee_id'   => 720,
			'attendee_name' => 'Sarah Johnson',
			'user_id'       => 15,
			'user_email'    => 'sarah.johnson@example.com',
			'display_name'  => 'Sarah Johnson',
		];

		$venue = [
			'venue_id'   => 88,
			'venue_name' => 'Downtown Convention Center',
			'address'    => '123 Main Street, Springfield',
		];

		$samples = [
			'attendEvent'          => [
				'attendee_id'   => $attendee['attendee_id'],
				'attendee_name' => $attendee['attendee_name'],
				'event_id'      => $event_data['event_id'],
				'event_title'   => $event_data['event_title'],
				'event_url'     => $event_data['event_url'],
				'user_id'       => $attendee['user_id'],
				'user_email'    => $attendee['user_email'],
				'display_name'  => $attendee['display_name'],
				'checked_in_at' => '2026-07-09 09:15:00',
				'via_qr'        => true,
			],
			'attendeeRegistered'   => [
				'attendee_id' => $attendee['attendee_id'],
				'post_id'     => $event_data['event_id'],
				'order_id'    => 9001,
				'product_id'  => 512,
				'status'      => 'yes',
			],
			'newAttendee'          => [
				'product_id' => 512,
				'order_id'   => 9001,
				'attendees'  => [
					[
						'attendee_id' => $attendee['attendee_id'],
						'full_name'   => $attendee['attendee_name'],
						'email'       => $attendee['user_email'],
					],
				],
			],
			'attendeeRegisteredWc' => [
				'attendee_id'   => $attendee['attendee_id'],
				'ticket'        => [
					'ticket_id' => 512,
					'name'      => 'General Admission',
					'price'     => 49.00,
				],
				'order'         => [
					'order_id' => 9001,
					'total'    => 49.00,
					'status'   => 'completed',
				],
				'attendee_data' => [
					'full_name' => $attendee['attendee_name'],
					'email'     => $attendee['user_email'],
					'venue'     => $venue,
				],
			],
		];

		if ( isset( $samples[ $event ] ) ) {
			return $samples[ $event ];
		}

		if ( 0 === strpos( $event, 'new' ) ) {
			return $samples['newAttendee'];
		}
		if ( 0 === strpos( $event, 'attend' ) ) {
			return $samples['attendEvent'];
		}

		return $samples['attendEvent'];
	}

	public static function resolve_trigger( array $node, array $args ) {
		if ( ! self::is_event_tickets_available() ) {
			return false;
		}

		$event = $node['event'] ?? ( $node['data']['event'] ?? '' );

		switch ( $event ) {

			// event_tickets_checkin( $attendee_id, $qr )
			// $args[1] is a QR flag, NOT the event id — reading it as one meant a
			// QR check-in (the main way this fires) resolved the event to the flag
			// and returned an empty title/url.
			case 'attendEvent':
				$attendee_id = self::to_id( $args[0] ?? 0 );
				if ( $attendee_id <= 0 ) {
					return false;
				}
				$attendee = get_post( $attendee_id );
				if ( ! $attendee ) {
					return false;
				}
				$user_id  = self::resolve_attendee_user_id( $attendee_id );
				$event_id = self::resolve_attendee_event_id( $attendee_id );
				$user     = $user_id ? get_user_by( 'id', $user_id ) : null;
				$post     = $event_id ? get_post( $event_id ) : null;
				return [
					'attendee_id'   => $attendee_id,
					'attendee_name' => $attendee->post_title,
					'event_id'      => $event_id ?: '',
					'event_title'   => $post ? $post->post_title : '',
					'event_url'     => $post ? get_permalink( $post->ID ) : '',
					'user_id'       => $user ? (int) $user->ID : '',
					'user_email'    => $user ? $user->user_email : '',
					'display_name'  => $user ? $user->display_name : '',
					'checked_in_at' => current_time( 'mysql' ),
					'via_qr'        => ! empty( $args[1] ),
				];

			// event_tickets_rsvp_attendee_created( $attendee_id, $post_id, $order, $product_id, $status )
			// $args[2] is the order OBJECT for RSVP, so it has to be narrowed to an
			// id rather than passed through as one.
			case 'attendeeRegistered':
				$attendee_id = self::to_id( $args[0] ?? 0 );
				if ( $attendee_id <= 0 ) {
					return false;
				}
				return [
					'attendee_id' => $attendee_id,
					'post_id'     => self::to_id( $args[1] ?? 0 ),
					'order_id'    => self::to_id( $args[2] ?? 0 ),
					'product_id'  => self::to_id( $args[3] ?? 0 ),
					'status'      => (string) ( $args[4] ?? '' ),
				];

			// event_tickets_rsvp_tickets_generated_for_product( $product_id, $order_id )
			// Only two args are passed; the attendee list has to be looked up
			// rather than read from a third argument that never arrives.
			case 'newAttendee':
				$product_id = self::to_id( $args[0] ?? 0 );
				$order_id   = self::to_id( $args[1] ?? 0 );
				if ( $product_id <= 0 && $order_id <= 0 ) {
					return false;
				}
				return [
					'product_id' => $product_id,
					'order_id'   => $order_id,
					'attendees'  => self::fetch_attendees( $order_id ),
				];

			// tribe_tickets_attendee_repository_create_attendee_for_ticket_after_create(
			//     $attendee, $attendee_data, $ticket, $repository )
			// The previous mapping read these as ( attendee_id, ticket, order,
			// attendee_data ) — every field was shifted by one position and the
			// wrong type.
			case 'attendeeRegisteredWc':
				$attendee      = $args[0] ?? null;
				$attendee_data = is_array( $args[1] ?? null ) ? $args[1] : [];
				$ticket        = $args[2] ?? null;
				$attendee_id   = self::to_id( $attendee );
				if ( $attendee_id <= 0 ) {
					return false;
				}
				$order_id = self::to_id( $attendee_data['order_id'] ?? 0 );
				return [
					'attendee_id'   => $attendee_id,
					'ticket'        => self::build_ticket_payload( $ticket ),
					'order'         => self::build_order_payload( $order_id ),
					'attendee_data' => $attendee_data,
				];
		}//end switch

		return false;
	}

	/**
	 * The trigger hooks all live in Event Tickets, so nothing can fire without
	 * it — this keeps resolve_trigger from touching its helpers when only The
	 * Events Calendar is active.
	 */
	private static function is_event_tickets_available(): bool {
		return class_exists( 'Tribe__Tickets__Main' );
	}

	/**
	 * Narrow whatever a hook passed — an id, a WP_Post, a ticket object, a
	 * WC_Order — down to a positive integer id, or 0.
	 */
	private static function to_id( $value ): int {
		if ( is_numeric( $value ) ) {
			return max( 0, (int) $value );
		}

		if ( is_object( $value ) ) {
			foreach ( [ 'ID', 'id' ] as $prop ) {
				if ( isset( $value->{$prop} ) && is_numeric( $value->{$prop} ) ) {
					return max( 0, (int) $value->{$prop} );
				}
			}
			if ( method_exists( $value, 'get_id' ) ) {
				return max( 0, (int) $value->get_id() );
			}
		}

		if ( is_array( $value ) ) {
			foreach ( [ 'ID', 'id', 'order_id' ] as $key ) {
				if ( isset( $value[ $key ] ) && is_numeric( $value[ $key ] ) ) {
					return max( 0, (int) $value[ $key ] );
				}
			}
		}

		return 0;
	}

	/**
	 * Event Tickets stores the attendee's event under a different meta key per
	 * ticket provider (RSVP, WooCommerce, Tickets Commerce, EDD), so all of them
	 * have to be tried.
	 */
	private static function resolve_attendee_event_id( int $attendee_id ): int {
		$keys = [
			'_tribe_rsvp_event',
			'_tribe_wooticket_event',
			'_tribe_tpp_event',
			'_tec_tickets_commerce_event',
			'_tribe_eddticket_event',
			'_tribe_tickets_checkin_event_id',
		];

		foreach ( $keys as $key ) {
			$value = get_post_meta( $attendee_id, $key, true );
			if ( $value ) {
				return (int) $value;
			}
		}

		return 0;
	}

	private static function resolve_attendee_user_id( int $attendee_id ): int {
		$keys = [
			'_tribe_tickets_meta_user_id',
			'_tribe_rsvp_user_id',
			'_tribe_wooticket_user_id',
			'_tribe_tpp_user_id',
		];

		foreach ( $keys as $key ) {
			$value = get_post_meta( $attendee_id, $key, true );
			if ( $value ) {
				return (int) $value;
			}
		}

		return 0;
	}

	private static function fetch_attendees( int $order_id ): array {
		if ( $order_id <= 0 || ! function_exists( 'tribe_tickets_get_attendees' ) ) {
			return [];
		}

		$attendees  = tribe_tickets_get_attendees( $order_id );
		$normalized = [];

		foreach ( (array) $attendees as $attendee ) {
			if ( ! is_array( $attendee ) ) {
				continue;
			}
			$normalized[] = [
				'attendee_id' => self::to_id( $attendee['attendee_id'] ?? 0 ),
				'full_name'   => (string) ( $attendee['holder_name'] ?? ( $attendee['purchaser_name'] ?? '' ) ),
				'email'       => (string) ( $attendee['holder_email'] ?? ( $attendee['purchaser_email'] ?? '' ) ),
			];
		}

		return $normalized;
	}

	private static function build_ticket_payload( $ticket ): array {
		$ticket_id = self::to_id( $ticket );

		return [
			'ticket_id' => $ticket_id,
			'name'      => (string) ( is_object( $ticket ) && isset( $ticket->name ) ? $ticket->name : get_the_title( $ticket_id ) ),
			'price'     => is_object( $ticket ) && isset( $ticket->price ) ? $ticket->price : '',
		];
	}

	private static function build_order_payload( int $order_id ): array {
		$payload = [
			'order_id' => $order_id,
			'total'    => '',
			'status'   => '',
		];

		if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$payload['total']  = $order->get_total();
				$payload['status'] = $order->get_status();
			}
		}

		return $payload;
	}
}
