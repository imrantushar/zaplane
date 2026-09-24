<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Gembooking\ActionsTrait;
use Zaplane\Integrations\Gembooking\QueryTrait;
use Zaplane\Integrations\Gembooking\Helper;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gembooking extends IntegrationBase {

	use ActionsTrait;
	use QueryTrait;
	use Helper;

	public static function get_slug(): string {
		return 'gembooking';
	}

	public static function get_name(): string {
		return 'GemBooking';
	}

	public static function get_icon(): string {
		return 'gembooking.svg';
	}

	public static function get_required_plugins(): array {
		return [ 'gembooking/gembooking.php' ];
	}

	/**
	 * Whether a GemBooking add-on is loaded.
	 *
	 * @param string $addon Add-on identifier.
	 */
	private static function addon_active( string $addon ): bool {
		$classes = [
			'package' => '\\GemBookingPackage\\API\\GrantController',
			'email'   => '\\GemBookingEmail\\Email\\Mail',
			'ticket'  => '\\GemBookingTicket\\API\\ScanController',
			'sms'     => '\\GemBookingSms\\Sms\\Client',
		];

		$active = isset( $classes[ $addon ] ) && class_exists( $classes[ $addon ] );

		return (bool) apply_filters( 'zaplane/gembooking_addon_active', $active, $addon );
	}

	private static function gate( array $items, string $addon, string $label ): array {
		$active = self::addon_active( $addon );

		foreach ( $items as &$item ) {
			$item['requires_addon'] = $addon;

			if ( ! $active ) {
				$item['disabled']        = true;
				$item['disabled_reason'] = sprintf(
					/* translators: %s: GemBooking add-on name. */
					__( 'Requires the GemBooking %s add-on to be active.', 'zaplane' ),
					$label
				);
			}
		}
		unset( $item );

		return $items;
	}

	/**
	 * Resolve the real GemBooking post type for a bookable kind.
	 *
	 * GemBooking defines its own post type via a constant instead of a
	 * fixed 'gembk_*' slug, so the save_post_{type} hook name has to be
	 * built from that constant — a hardcoded 'gembk_resource' etc. will
	 * silently never fire if GemBooking registers a different slug.
	 *
	 * @param string $kind    'service' | 'event' | 'resource' | 'package'.
	 * @param string $default Fallback slug if the constant isn't defined.
	 */
	private static function bookable_post_type( string $kind, string $default ): string {
		$constants = [
			'service'  => 'GEMBOOKING_SERVICE_POST_TYPE',
			'event'    => 'GEMBOOKING_EVENT_POST_TYPE',
			'resource' => 'GEMBOOKING_RESOURCE_POST_TYPE',
			'package'  => 'GEMBOOKING_PACKAGE_POST_TYPE',
		];
		$constant = $constants[ $kind ] ?? '';

		return ( $constant && defined( $constant ) ) ? (string) constant( $constant ) : $default;
	}

	public static function get_triggers(): array {
		$service_post_type  = self::bookable_post_type( 'service', 'gembk_service' );
		$event_post_type    = self::bookable_post_type( 'event', 'gembk_event' );
		$resource_post_type = self::bookable_post_type( 'resource', 'gembk_resource' );

		$triggers = [
			'booking_created'             => [
				'label' => 'Booking Created',
				'hook'  => 'gembooking/after_booking_persisted'
			],
			'booking_confirmed'           => [
				'label' => 'Booking Status Confirmed',
				'hook'  => 'gembooking/booking/confirmed'
			],
			'booking_pending'             => [
				'label' => 'Booking Status Pending',
				'hook'  => 'gembooking/booking/pending'
			],
			'booking_rescheduled'         => [
				'label' => 'Booking Status Rescheduled',
				'hook'  => 'gembooking/booking/rescheduled'
			],
			'booking_cancelled'           => [
				'label' => 'Booking Status Cancelled',
				'hook'  => 'gembooking/booking/cancelled'
			],
			'booking_abandoned'           => [
				'label' => 'Booking Status Abandoned',
				'hook'  => 'gembooking/booking/abandoned'
			],
			'booking_status_changed_by_admin' => [
				'label' => 'Booking Status Changed by Admin',
				'hook'  => 'gembooking/after_status_update_booked'
			],
			'booking_deleted'             => [
				'label' => 'Booking Deleted',
				'hook'  => 'gembooking/after_delete_booked'
			],
			'booking_form_abandoned'      => [
				'label' => 'Booking Form Abandoned',
				'hook'  => 'gembooking/form/lead_abandoned'
			],
			'staff_member_added'          => [
				'label' => 'Staff Member Added',
				'hook'  => 'gembooking/after_create_team'
			],
			'staff_member_updated'        => [
				'label' => 'Staff Member Updated',
				'hook'  => 'gembooking/after_update_team'
			],
			'staff_member_removed'        => [
				'label' => 'Staff Member Removed',
				'hook'  => 'gembooking/after_delete_team'
			],
			'bookable_duplicated'         => [
				'label' => 'Service, Event or Resource Duplicated',
				'hook'  => 'gembooking/duplicated'
			],
			'woocommerce_product_linked'   => [
				'label' => 'Product Linked (WooCommerce)',
				'hook'  => 'gembooking/woocommerce/product_linked'
			],
			'woocommerce_product_unlinked' => [
				'label' => 'Product Unlinked (WooCommerce)',
				'hook'  => 'gembooking/woocommerce/product_unlinked'
			],
			'storeengine_product_linked'   => [
				'label' => 'Product Linked (StoreEngine)',
				'hook'  => 'gembooking/storeengine/product_linked'
			],
			'storeengine_product_unlinked' => [
				'label' => 'Product Unlinked (StoreEngine)',
				'hook'  => 'gembooking/storeengine/product_unlinked'
			],
			'service_created'    => [
				'label' => 'Service Created',
				'hook'  => 'save_post_' . $service_post_type
			],
			'event_created'      => [
				'label' => 'Event Created',
				'hook'  => 'save_post_' . $event_post_type
			],
			'resource_created'   => [
				'label' => 'Resource Created',
				'hook'  => 'save_post_' . $resource_post_type
			],
			'integration_unresolved' => [
				'label' => 'Meeting Link or Calendar Sync Failed',
				'hook'  => 'gembooking/integrations/unresolved'
			],
			'external_calendar_cancelled' => [
				'label' => 'Booking Cancelled from External Calendar',
				'hook'  => 'gembooking/booking/external_cancel'
			],
			'external_calendar_rescheduled' => [
				'label' => 'Booking Moved from External Calendar',
				'hook'  => 'gembooking/booking/external_reschedule'
			],
		];
		$triggers += self::gate(
			[
				'package_purchased'       => [
					'label' => 'Package Purchased',
					'hook'  => 'gembooking/package/purchased'
				],
				'package_granted'         => [
					'label' => 'Package Granted Manually',
					'hook'  => 'gembooking/package/granted'
				],
				'package_status_changed'  => [
					'label' => 'Package Status Changed',
					'hook'  => 'gembooking/package/status_changed'
				],
				'package_purchases_deleted' => [
					'label' => 'Package Purchases Deleted',
					'hook'  => 'gembooking/package/purchases_deleted'
				],
			],
			'package',
			'Package'
		);

		return $triggers;
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		$booking = [
			'booking_id' => 123,
			'status' => 'confirmed',
			'object_id' => 45,
			'host_id' => 12,
			'start_at' => '2026-09-22 10:00',
			'end_at' => '2026-09-22 11:00'
		];
		if ( 'booking_created' === $trigger ) {
			return [
				'event' => $trigger,
				'booking_id' => 123,
				'static_data' => $booking,
				'dynamic_data' => []
			];
		}
		if ( in_array( $trigger, [ 'booking_confirmed', 'booking_pending', 'booking_rescheduled', 'booking_cancelled', 'booking_deleted' ], true ) ) {
			return [
				'event' => $trigger,
				'booking_id' => 123
			];
		}
		if ( 'booking_abandoned' === $trigger ) {
			return [
				'event' => $trigger,
				'booking_id' => 123,
				'action' => 'cleanup',
				'row' => []
			];
		}
		if ( 'booking_status_changed_by_admin' === $trigger ) {
			return [
				'event' => $trigger,
				'booking' => $booking
			];
		}
		if ( 'booking_form_abandoned' === $trigger ) {
			return [
				'event' => $trigger,
				'lead' => [
					'email' => 'customer@example.com',
					'fields' => []
				]
			];
		}
		if ( in_array( $trigger, [ 'staff_member_added', 'staff_member_updated', 'staff_member_removed' ], true ) ) {
			return [
				'event' => $trigger,
				'user_id' => 12
			];
		}
		if ( 'bookable_duplicated' === $trigger ) {
			return [
				'event' => $trigger,
				'new_id' => 46,
				'source_id' => 45,
				'post_type' => 'gembk_service'
			];
		}
		if ( in_array( $trigger, [ 'woocommerce_product_linked', 'storeengine_product_linked' ], true ) ) {
			return [
				'event' => $trigger,
				'object_id' => 45,
				'product_id' => 88,
				'previous' => 0
			];
		}
		if ( in_array( $trigger, [ 'woocommerce_product_unlinked', 'storeengine_product_unlinked' ], true ) ) {
			return [
				'event' => $trigger,
				'object_id' => 45,
				'product_id' => 88
			];
		}
		if ( in_array( $trigger, [ 'service_created', 'event_created', 'resource_created' ], true ) ) {
			$post_type_map = [
				'service_created'  => self::bookable_post_type( 'service', 'gembk_service' ),
				'event_created'    => self::bookable_post_type( 'event', 'gembk_event' ),
				'resource_created' => self::bookable_post_type( 'resource', 'gembk_resource' ),
			];
			return [
				'event' => $trigger,
				'post_id' => 45,
				'post' => [
					'ID' => 45,
					'post_type' => $post_type_map[ $trigger ],
					'post_title' => 'Sample ' . ucfirst( str_replace( '_created', '', $trigger ) ),
					'post_content' => 'Sample description',
					'post_status' => 'publish',
				],
				'post_type' => $post_type_map[ $trigger ],
				'title' => 'Sample ' . ucfirst( str_replace( '_created', '', $trigger ) ),
				'description' => 'Sample description',
				'status' => 'publish',
				'meta' => [
					'price' => 100,
					'duration' => 3600,
				],
				'update' => false
			];
		}
		if ( 'package_purchased' === $trigger ) {
			return [
				'event' => $trigger,
				'purchase_id' => 101,
				'package_id' => 22,
				'context' => []
			];
		}
		if ( 'package_granted' === $trigger ) {
			return [
				'event' => $trigger,
				'purchase_id' => 101,
				'package_id' => 22,
				'user_id' => 12,
				'email' => 'customer@example.com'
			];
		}
		if ( 'package_status_changed' === $trigger ) {
			return [
				'event' => $trigger,
				'purchase_id' => 101,
				'status' => 'active',
				'purchase' => [
					'id' => 101,
					'package_id' => 22,
					'user_id' => 12,
					'status' => 'active'
				]
			];
		}
		if ( 'package_purchases_deleted' === $trigger ) {
			return [
				'event' => $trigger,
				'ids' => [ 101, 102 ]
			];
		}
		if ( 'integration_unresolved' === $trigger ) {
			return [
				'event' => $trigger,
				'provider_slug' => 'google',
				'resolution' => 'failed',
				'booking_id' => 123
			];
		}
		if ( 'external_calendar_cancelled' === $trigger ) {
			return [
				'event' => $trigger,
				'booking_id' => 123,
				'reason' => 'External calendar event deleted',
				'data' => []
			];
		}
		return [
			'event' => $trigger,
			'booking_id' => 123,
			'start_at' => '2026-09-22 10:00',
			'end_at' => '2026-09-22 11:00',
			'data' => []
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$event = (string) ( $node['event'] ?? ( $node['data']['event'] ?? '' ) );

		if ( '' === $event || empty( $args ) ) {
			return false;
		}

		switch ( $event ) {
			case 'booking_created':
				$id = absint( $args[0] ?? 0 );
				return $id ? [
					'event' => $event,
					'booking_id' => $id,
					'static_data' => is_array( $args[1] ?? null ) ? $args[1] : [],
					'dynamic_data' => is_array( $args[2] ?? null ) ? $args[2] : []
				] : false;
			case 'booking_confirmed':
			case 'booking_pending':
			case 'booking_rescheduled':
			case 'booking_cancelled':
			case 'booking_deleted':
				$id = absint( $args[0] ?? 0 );
				return $id ? [
					'event' => $event,
					'booking_id' => $id
				] : false;
			case 'booking_abandoned':
				return [
					'event' => $event,
					'booking_id' => absint( $args[0] ?? 0 ),
					'action' => $args[1] ?? '',
					'row' => self::normalize( $args[2] ?? [] )
				];
			case 'booking_status_changed_by_admin':
				return [
					'event' => $event,
					'booking' => self::normalize( $args[0] ?? [] )
				];
			case 'booking_form_abandoned':
				return [
					'event' => $event,
					'lead' => self::normalize( $args[0] ?? [] )
				];
			case 'staff_member_added':
			case 'staff_member_updated':
			case 'staff_member_removed':
				return [
					'event' => $event,
					'user_id' => absint( $args[0] ?? 0 )
				];
			case 'bookable_duplicated':
				return [
					'event' => $event,
					'new_id' => absint( $args[0] ?? 0 ),
					'source_id' => absint( $args[1] ?? 0 ),
					'post_type' => (string) ( $args[2] ?? '' )
				];
			case 'woocommerce_product_linked':
			case 'storeengine_product_linked':
				return [
					'event' => $event,
					'object_id' => absint( $args[0] ?? 0 ),
					'product_id' => absint( $args[1] ?? 0 ),
					'previous' => self::normalize( $args[2] ?? null )
				];
			case 'woocommerce_product_unlinked':
			case 'storeengine_product_unlinked':
				return [
					'event' => $event,
					'object_id' => absint( $args[0] ?? 0 ),
					'product_id' => absint( $args[1] ?? 0 )
				];
			case 'service_created':
			case 'event_created':
			case 'resource_created':
				$post_id = absint( $args[0] ?? 0 );
				if (
					! $post_id ||
					( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) ) ||
					( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) )
				) {
					return false;
				}

				$post = self::normalize( $args[1] ?? [] );
				$post = is_array( $post ) ? $post : [];

				return [
					'event' => $event,
					'post_id' => $post_id,
					'post' => self::normalize( $args[1] ?? [] ),
					'post_type' => (string) ( $post['post_type'] ?? '' ),
					'title' => (string) ( $post['post_title'] ?? '' ),
					'description' => (string) ( $post['post_content'] ?? '' ),
					'status' => (string) ( $post['post_status'] ?? '' ),
					// GemBooking stores its own service/event/resource fields
					// (price, duration, capacity, images, etc.) as post meta,
					// not on the $post object itself — pull all of it here
					// instead of dumping the raw WP_Post.
					'meta' => self::flatten_meta( function_exists( 'get_post_meta' ) ? get_post_meta( $post_id ) : [] ),
					'update' => (bool) ( $args[2] ?? false )
				];
			case 'package_purchased':
				return [
					'event' => $event,
					'purchase_id' => absint( $args[0] ?? 0 ),
					'package_id' => absint( $args[1] ?? 0 ),
					'context' => self::normalize( $args[2] ?? [] )
				];
			case 'package_granted':
				return [
					'event' => $event,
					'purchase_id' => absint( $args[0] ?? 0 ),
					'package_id' => absint( $args[1] ?? 0 ),
					'user_id' => absint( $args[2] ?? 0 ),
					'email' => (string) ( $args[3] ?? '' )
				];
			case 'package_status_changed':
				return [
					'event' => $event,
					'purchase_id' => absint( $args[0] ?? 0 ),
					'status' => (string) ( $args[1] ?? '' ),
					'purchase' => self::normalize( $args[2] ?? [] )
				];
			case 'package_purchases_deleted':
				return [
					'event' => $event,
					'ids' => (array) ( $args[0] ?? [] )
				];
			case 'integration_unresolved':
				return [
					'event' => $event,
					'provider_slug' => (string) ( $args[0] ?? '' ),
					'resolution' => (string) ( $args[1] ?? '' ),
					'booking_id' => absint( $args[2] ?? 0 )
				];
			case 'external_calendar_cancelled':
				return [
					'event' => $event,
					'booking_id' => absint( $args[0] ?? 0 ),
					'reason' => (string) ( $args[1] ?? '' ),
					'data' => self::normalize( $args[2] ?? [] )
				];
			case 'external_calendar_rescheduled':
				return [
					'event' => $event,
					'booking_id' => absint( $args[0] ?? 0 ),
					'start_at' => $args[1] ?? '',
					'end_at' => $args[2] ?? '',
					'data' => self::normalize( $args[3] ?? [] )
				];

			default:
				return false;
		}//end switch
	}

	public static function get_actions(): array {
		$actions = [
			'create_booking'          => [ 'label' => 'Create Booking' ],
			'change_booking_status'   => [ 'label' => 'Change Booking Status' ],
			'reschedule_booking'      => [ 'label' => 'Reschedule Booking' ],
			'cancel_booking'          => [ 'label' => 'Cancel Booking' ],
			'delete_booking'          => [ 'label' => 'Delete Booking' ],
			'add_booking_note'        => [ 'label' => 'Add Internal Note' ],
			'reply_customer'          => [ 'label' => 'Reply to Customer' ],
			'update_booking_field'    => [ 'label' => 'Update Booking Custom Field' ],
			'find_booking'            => [ 'label' => 'Find Booking' ],
			'check_slot_availability' => [ 'label' => 'Check Slot Availability' ],
			'add_staff_time_off'      => [ 'label' => 'Add Staff Time Off' ],
			'update_customer_profile' => [ 'label' => 'Update Customer Profile' ],
			'duplicate_bookable'      => [ 'label' => 'Duplicate Service, Event or Resource' ],
			'create_staff'            => [ 'label' => 'Create Staff Member' ],
		];
		$actions += self::gate(
			[
				'send_template_email' => [ 'label' => 'Send Email in GemBooking Template' ],
			],
			'email',
			'Email'
		);

		$actions += self::gate(
			[
				'grant_package'          => [ 'label' => 'Grant Package to Customer' ],
				'change_package_status'  => [ 'label' => 'Change Package Status' ],
				'delete_package_purchase' => [ 'label' => 'Delete Package Purchase' ],
			],
			'package',
			'Package'
		);
		$actions += self::gate(
			[
				'check_in_ticket'      => [ 'label' => 'Check In Ticket' ],
				'undo_ticket_checkin'  => [ 'label' => 'Undo Ticket Check-In' ],
				'check_in_all_tickets' => [ 'label' => 'Check In All Tickets on a Booking' ],
			],
			'ticket',
			'Ticket'
		);
		$actions += self::gate(
			[
				'send_sms_whatsapp' => [ 'label' => 'Send SMS or WhatsApp' ],
			],
			'sms',
			'SMS'
		);

		return $actions;
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [
			'create_booking' => [
				...self::booking_type_field(),
				...self::bookable_field(),
				[
					'key'      => 'customer_id',
					'label'    => 'Existing Customer (search by name or email)',
					'type'     => 'select',
					'required' => false,
					'show_if'  => [ 'booking_type' => 'package' ],
					'dynamic'  => [
						'integration' => 'gembooking',
						'query'       => 'gembooking_customer_query',
						'select'      => [ 'value', 'label' ],
					],
				],
				[
					'key'      => 'customer_email',
					'label'    => 'Or Customer Email (no account yet)',
					'type'     => 'email',
					'required' => false,
					'show_if'  => [ 'booking_type' => 'package' ],
				],
				...self::start_at_field(),
				...self::end_at_field(),
				...self::timezone_field(),
				[
					'key'      => 'first_name',
					'label'    => 'Customer Name',
					'type'     => 'text',
					'required' => true,
					'show_if'  => [ 'booking_type' => [ 'service', 'event', 'resource' ] ],
				],
				[
					'key'      => 'email',
					'label'    => 'Customer Email',
					'type'     => 'email',
					'required' => true,
					'show_if'  => [ 'booking_type' => [ 'service', 'event', 'resource' ] ],
				],
				[
					'key'      => 'phone',
					'label'    => 'Phone',
					'type'     => 'text',
					'required' => false,
					'show_if'  => [ 'booking_type' => [ 'service', 'event', 'resource' ] ],
				],
				[
					'key'      => 'note',
					'label'    => 'Comment / Notes',
					'type'     => 'textarea',
					'required' => false,
					'show_if'  => [ 'booking_type' => [ 'service', 'event', 'resource' ] ],
				],
				[
					'key'      => 'host_id',
					'label'    => 'Host ID (optional)',
					'type'     => 'number',
					'required' => false,
					'show_if'  => [ 'booking_type' => [ 'service', 'event', 'resource' ] ],
				],
				[
					'key'      => 'team_member_id',
					'label'    => 'Staff/Team Member ID (optional)',
					'type'     => 'number',
					'required' => false,
					'show_if'  => [ 'booking_type' => [ 'service', 'event', 'resource' ] ],
				],
				[
					'key'      => 'attendee_count',
					'label'    => 'Number of Attendees (optional)',
					'type'     => 'number',
					'required' => false,
					'show_if'  => [ 'booking_type' => [ 'service', 'event', 'resource' ] ],
				],
			],
			'change_booking_status' => [
				...self::booking_id_field(),
				[
					'key'      => 'booking_status',
					'label'    => 'Booking Status',
					'type'     => 'select',
					'required' => true,
					'options'  => [
						[ 'value' => 'pending', 'label' => 'Pending' ],
						[ 'value' => 'confirmed', 'label' => 'Confirmed' ],
						[ 'value' => 'cancelled', 'label' => 'Cancelled' ],
						[ 'value' => 'rescheduled', 'label' => 'Rescheduled' ],
					],
				],
			],
			'reschedule_booking' => [
				...self::booking_id_field(),
				...self::start_at_field( false ),
				...self::end_at_field( false ),
			],
			'cancel_booking' => [
				...self::booking_id_field(),
				...self::reason_field(),
			],
			'delete_booking' => [
				...self::booking_id_field(),
			],
			'add_booking_note' => [
				...self::booking_id_field(),
				[
					'key'      => 'note',
					'label'    => 'Note',
					'type'     => 'textarea',
					'required' => true,
				],
			],
			'reply_customer' => [
				...self::booking_id_field(),
				...self::message_field(),
			],
			'update_booking_field' => [
				...self::booking_id_field(),
				[
					'key' => 'field_key',
					'label' => 'Custom Field Key',
					'type' => 'text',
					'required' => true,
				],
				[
					'key' => 'value',
					'label' => 'Value',
					'type' => 'text',
					'required' => true,
				],
			],
			'find_booking' => [
				...self::booking_id_field( false ),
				[
					'key' => 'search',
					'label' => 'Search Term (name or email)',
					'type' => 'text',
					'required' => false,
				],
				[
					'key' => 'filter_status',
					'label' => 'Status Filter',
					'type' => 'text',
					'required' => false,
				],
				[
					'key' => 'limit',
					'label' => 'Limit',
					'type' => 'number',
					'required' => false,
				],
			],
			'check_slot_availability' => [
				...self::booking_type_field( false ),
				...self::bookable_field(),
				...self::start_at_field(),
				...self::end_at_field(),
				...self::team_id_field( false ),
			],
			'add_staff_time_off' => [
				...self::team_id_field(),
				...self::start_at_field( false ),
				...self::end_at_field( false ),
				...self::reason_field(),
			],
			'update_customer_profile' => [
				[
					'key' => 'user_id',
					'label' => 'Customer User ID',
					'type' => 'number',
					'required' => true,
				],
				...self::first_name_field(),
				...self::last_name_field(),
				[
					'key' => 'display_name',
					'label' => 'Display Name',
					'type' => 'text',
					'required' => true,
				],
				...self::email_field(),
				...self::phone_field(),
				...self::timezone_field( false ),
			],
			'duplicate_bookable' => [
				...self::booking_type_field( false ),
				...self::bookable_field(),
			],
			'create_staff' => [
				...self::first_name_field(),
				...self::last_name_field(),
				...self::email_field(),
				...self::phone_field(),
			],
			'send_template_email' => [
				[
					'key' => 'to',
					'label' => 'To',
					'type' => 'email',
					'required' => true,
				],
				[
					'key' => 'subject',
					'label' => 'Subject',
					'type' => 'text',
					'required' => true,
				],
				[
					'key' => 'body',
					'label' => 'Body',
					'type' => 'textarea',
					'required' => true,
				],
			],
			'grant_package' => [
				...self::package_id_field(),
				[
					'key'      => 'user_id',
					'label'    => 'Customer User ID (or use Email below)',
					'type'     => 'number',
					'required' => false,
				],
				[
					'key'      => 'email',
					'label'    => 'Customer Email (used if no User ID)',
					'type'     => 'email',
					'required' => false,
				],
			],
			'change_package_status' => [
				...self::purchase_id_field(),
				[
					'key'      => 'package_status',
					'label'    => 'Package Status',
					'type'     => 'select',
					'required' => true,
					'options'  => [
						[ 'value' => 'active', 'label' => 'Active' ],
						[ 'value' => 'cancelled', 'label' => 'Cancelled' ],
						[ 'value' => 'expired', 'label' => 'Expired' ],
					],
				],
			],
			'delete_package_purchase' => [
				...self::purchase_id_field( false ),
				[
					'key' => 'ids',
					'label' => 'Purchase IDs (comma-separated, for bulk delete)',
					'type' => 'text',
					'required' => false,
				],
			],
			'check_in_ticket' => [
				...self::ticket_code_field(),
			],
			'undo_ticket_checkin' => [
				...self::ticket_code_field(),
			],
			'check_in_all_tickets' => [
				...self::ticket_code_field(),
			],
			'send_sms_whatsapp' => [
				...self::phone_field(),
				...self::message_field(),
				[
					'key'      => 'channel',
					'label'    => 'Channel',
					'type'     => 'select',
					'required' => false,
					'default'  => 'sms',
					'options'  => [
						[ 'value' => 'sms', 'label' => 'SMS' ],
						[ 'value' => 'whatsapp', 'label' => 'WhatsApp' ],
					],
				],
			],
		];
		return $schemas[ $action ] ?? [
			...self::booking_id_field( false ),
			[
				'key' => 'data',
				'label' => 'Data (JSON)',
				'type' => 'textarea',
				'required' => false,
			],
		];
	}

	public static function get_action_sample_output( string $action ): array {
		if ( 'find_booking' === $action ) {
			return [
				'success' => true,
				'data' => [
					'total' => 1,
					'rows' => []
				],
				'action' => $action
			];
		}
		if ( 'check_slot_availability' === $action ) {
			return [
				'success' => true,
				'data' => [ 'available' => true ],
				'action' => $action
			];
		}
		return [
			'success' => true,
			'data' => [
				'booking_id' => 123,
				'status' => 'confirmed'
			],
			'action' => $action
		];
	}

	/**
	 * Entry point used by the workflow engine to run this node.
	 *
	 * No REST dispatch here — every action calls the GemBooking model or
	 * controller classes directly, the same way update_booking_field,
	 * find_booking and check_slot_availability already did.
	 */
	public static function execute_node( array $node, array $input ): array {
		$event  = (string) ( $node['data']['event'] ?? '' );
		$config = is_array( $node['data']['config'] ?? null ) ? $node['data']['config'] : [];

		if ( '' === $event ) {
			return self::action_error( 'No action event was provided to this node.' );
		}

		$method = 'action_' . $event;

		if ( ! method_exists( static::class, $method ) ) {
			return self::action_error( sprintf( 'GemBooking action "%s" is not available.', $event ) );
		}

		return static::$method( $config, $input );
	}

	/**
	 * Calls a BookingSolver-style method, which expects a WP_REST_Request.
	 * We build that request in-process rather than dispatching it through
	 * rest_do_request(), so no REST permission/route layer is involved.
	 */
	private static function call_solver( string $class, string $method, array $params ): array {
		return self::call_controller( $class, $method, $params );
	}

	/**
	 * Calls any GemBooking controller/solver method that takes a
	 * WP_REST_Request, builds that request from $params, and normalizes
	 * whatever comes back (WP_Error / WP_REST_Response / array) into our
	 * action_success / action_error shape.
	 */
	private static function call_controller( string $class, string $method, array $params ): array {
		if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
			return self::action_error( sprintf( 'GemBooking method %s::%s() is not available. Confirm the required GemBooking version/add-on is active.', $class, $method ) );
		}

		$request = new \WP_REST_Request();
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		try {
			$result = self::invoke( $class, $method, [ $request ] );
		} catch ( \TypeError $e ) {
			return self::action_error( sprintf( '%s::%s() type error: %s', $class, $method, $e->getMessage() ) );
		} catch ( \ArgumentCountError $e ) {
			return self::action_error( sprintf( '%s::%s() argument error: %s', $class, $method, $e->getMessage() ) );
		} catch ( \Throwable $e ) {
			return self::action_error( sprintf( '%s::%s() failed: %s', $class, $method, $e->getMessage() ) );
		}

		if ( $result instanceof \WP_Error ) {
			return self::action_error( $result->get_error_message() );
		}

		if ( $result instanceof \WP_REST_Response ) {
			$data = $result->get_data();
			if ( $result->get_status() >= 400 ) {
				$message = is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : sprintf( 'GemBooking returned HTTP %d.', $result->get_status() );
				return self::action_error( $message );
			}
			return self::action_success( is_array( $data ) ? $data : [ 'data' => $data ] );
		}

		if ( is_array( $result ) && isset( $result['status'], $result['body'] ) ) {
			if ( $result['status'] >= 400 ) {
				$message = is_array( $result['body'] ) && isset( $result['body']['message'] ) ? (string) $result['body']['message'] : sprintf( 'GemBooking returned HTTP %d.', $result['status'] );
				return self::action_error( $message );
			}
			return self::action_success( is_array( $result['body'] ) ? $result['body'] : [ 'data' => $result['body'] ] );
		}

		return self::action_success( is_array( $result ) ? $result : [ 'data' => $result ] );
	}

	/**
	 * Calls a static or instance method with the given argument list,
	 * without assuming which one it is.
	 */
	private static function invoke( string $class, string $method, array $args ) {
		$reflection = new \ReflectionMethod( $class, $method );

		if ( $reflection->isStatic() ) {
			return call_user_func_array( [ $class, $method ], $args );
		}

		$instance = new $class();
		return call_user_func_array( [ $instance, $method ], $args );
	}

	private static function action_success( array $data ): array {
		return [
			'port' => 'main',
			'data' => [
				'success' => true,
				'data' => $data
			]
		];
	}

	private static function action_error( string $message ): array {
		return [
			'port' => 'main',
			'data' => [
				'success' => false,
				'error' => $message
			]
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'gembooking_bookable_query' => [ self::class, 'query_bookables' ],
			'gembooking_customer_query' => [ self::class, 'query_customers' ],
			'gembooking_timezone_query' => [ self::class, 'query_timezones' ],
			'gembooking_query'          => [ self::class, 'query_booking' ],
			'gembooking_package_query'  => [ self::class, 'query_packages' ],
			'gembooking_purchase_query' => [ self::class, 'query_purchases' ],
		];
	}

	private static function decode_data( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		$decoded = json_decode( (string) $value, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return [];
		}
		return is_array( $decoded ) ? $decoded : [];
	}

	private static function normalize( $value ) {
		return is_object( $value ) ? get_object_vars( $value ) : $value;
	}

	/**
	 * Flatten a get_post_meta( $id ) result ( [key => [values]] ) into
	 * plain [key => value] — single-value meta keys are unwrapped and
	 * PHP-serialized values are restored.
	 */
	private static function flatten_meta( array $meta ): array {
		$flat = [];
		foreach ( $meta as $key => $values ) {
			if ( is_array( $values ) && 1 === count( $values ) ) {
				$flat[ $key ] = function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $values[0] ) : $values[0];
			} elseif ( is_array( $values ) ) {
				$flat[ $key ] = array_map(
					static fn( $v ) => function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $v ) : $v,
					$values
				);
			} else {
				$flat[ $key ] = $values;
			}
		}
		return $flat;
	}
}
