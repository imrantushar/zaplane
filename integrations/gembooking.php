<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gembooking extends IntegrationBase {

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

	/**
	 * Keep add-on nodes visible, but disable them until their add-on is active.
	 *
	 * @param array  $items Add-on-only triggers or actions.
	 * @param string $addon Add-on identifier.
	 * @param string $label Human-readable add-on name.
	 */
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
	 * @param string $kind    'service' | 'event' | 'resource'.
	 * @param string $default Fallback slug if the constant isn't defined.
	 */
	private static function bookable_post_type( string $kind, string $default ): string {
		$constants = [
			'service'  => 'GEMBOOKING_SERVICE_POST_TYPE',
			'event'    => 'GEMBOOKING_EVENT_POST_TYPE',
			'resource' => 'GEMBOOKING_RESOURCE_POST_TYPE',
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

	private static function booking_id_field(): array {
		return [
			[
				'key'      => 'booking_id',
				'label'    => 'Booking',
				'type'     => 'select',
				'required' => true,
				'dynamic'  => [
					'integration' => 'gembooking',
					'query'       => 'gembooking_query',
					'select'      => [ 'value', 'label' ],
				],
			],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [
			'create_booking' => [
				[
					'key'      => 'booking_type',
					'label'    => 'Booking Type',
					'type'     => 'select',
					'required' => true,
					'options'  => [
						[ 'value' => 'service', 'label' => 'Service' ],
						[ 'value' => 'event', 'label' => 'Event' ],
						[ 'value' => 'resource', 'label' => 'Resource' ],
						[ 'value' => 'package', 'label' => 'Package' ],
					],
				],
				[
					'key'      => 'bookable',
					'label'    => 'Bookable',
					'type'     => 'select',
					'required' => true,
					'dynamic'  => [
						'integration' => 'gembooking',
						'query'       => 'gembooking_bookable_query',
						'select'      => [ 'value', 'label' ],
						'depends_on'  => [ 'booking_type' ],
					],
				],

				// --- Package flow (booking_type = package) ---
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

				// --- Service / Event / Resource flow ---
				[
					'key'      => 'start_at',
					'label'    => 'Start At',
					'type'     => 'datetime',
					'required' => true,
					'show_if'  => [ 'booking_type' => [ 'service', 'event', 'resource' ] ],
				],
				[
					'key'      => 'end_at',
					'label'    => 'End At',
					'type'     => 'datetime',
					'required' => true,
					'show_if'  => [ 'booking_type' => [ 'service', 'event', 'resource' ] ],
				],
				[
					'key'      => 'timezone',
					'label'    => 'Timezone',
					'type'     => 'select',
					'required' => false,
					'show_if'  => [ 'booking_type' => [ 'service', 'event', 'resource' ] ],
					'dynamic'  => [
						'integration' => 'gembooking',
						'query'       => 'gembooking_timezone_query',
						'select'      => [ 'value', 'label' ],
					],
				],
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
				[
					'key'      => 'start_at',
					'label'    => 'New Start At',
					'type'     => 'datetime',
					'required' => true,
				],
				[
					'key'      => 'end_at',
					'label'    => 'New End At',
					'type'     => 'datetime',
					'required' => true,
				],
			],
			'cancel_booking' => [
				...self::booking_id_field(),
				[
					'key'      => 'reason',
					'label'    => 'Reason',
					'type'     => 'textarea',
					'required' => false,
				],
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
				[
					'key' => 'message',
					'label' => 'Message',
					'type' => 'textarea',
					'required' => true,
				],
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
				...self::booking_id_field(),
				[
					'key' => 'search', 'label' => 'Search Term (name or email)', 'type' => 'text', 'required' => false ],
				[ 'key' => 'status', 'label' => 'Status Filter', 'type' => 'text', 'required' => false ],
				[ 'key' => 'limit', 'label' => 'Limit', 'type' => 'number', 'required' => false ],
			],
			'check_slot_availability' => [
				[
					'key'      => 'booking_type',
					'label'    => 'Booking Type',
					'type'     => 'select',
					'required' => true,
					'options'  => [
						[ 'value' => 'service', 'label' => 'Service' ],
						[ 'value' => 'event', 'label' => 'Event' ],
						[ 'value' => 'resource', 'label' => 'Resource' ],
					],
				],
				[
					'key'      => 'bookable',
					'label'    => 'Bookable',
					'type'     => 'select',
					'required' => true,
					'dynamic'  => [
						'integration' => 'gembooking',
						'query'       => 'gembooking_bookable_query',
						'select'      => [ 'value', 'label' ],
						'depends_on'  => [ 'booking_type' ],
					],
				],
				[
					'key' => 'start_at',
					'label' => 'Start At',
					'type' => 'datetime',
					'required' => true,
				],
				[
					'key' => 'end_at',
					'label' => 'End At',
					'type' => 'datetime',
					'required' => true,
				],
				[
					'key' => 'team_id',
					'label' => 'Staff/Team ID',
					'type' => 'number',
					'required' => false,
				],
			],
			'add_staff_time_off' => [
				[
					'key' => 'team_id', 'label' => 'Staff Member ID', 'type' => 'number', 'required' => true ],
				[
					'key' => 'start_at',
					'label' => 'Start At',
					'type' => 'datetime',
					'required' => true,
				],
				[
					'key' => 'end_at',
					'label' => 'End At',
					'type' => 'datetime',
					'required' => true,
				],
				[ 'key' => 'reason', 'label' => 'Reason', 'type' => 'text', 'required' => false ],
			],
			'update_customer_profile' => [
				[ 'key' => 'user_id', 'label' => 'Customer User ID', 'type' => 'number', 'required' => true ],
				[ 'key' => 'data', 'label' => 'Profile data (JSON — first_name, last_name, display_name, user_email, phone, timezone)', 'type' => 'textarea', 'required' => true ],
			],
			'duplicate_bookable' => [
				[ 'key' => 'source_id', 'label' => 'Source Service/Event/Resource ID', 'type' => 'number', 'required' => true ],
			],
			'create_staff' => [
				[ 'key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true ],
				[ 'key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true ],
				[ 'key' => 'role', 'label' => 'Role', 'type' => 'text', 'required' => false ],
			],
			'send_template_email' => [
				[ 'key' => 'to', 'label' => 'To', 'type' => 'email', 'required' => true ],
				[ 'key' => 'subject', 'label' => 'Subject', 'type' => 'text', 'required' => true ],
				[ 'key' => 'body', 'label' => 'Body', 'type' => 'textarea', 'required' => true ],
			],
			'grant_package' => [
				[ 'key' => 'package_id', 'label' => 'Package ID', 'type' => 'number', 'required' => true ],
				[ 'key' => 'user_id', 'label' => 'Customer User ID (or use Email below)', 'type' => 'number', 'required' => false ],
				[ 'key' => 'email', 'label' => 'Customer Email (used if no User ID)', 'type' => 'email', 'required' => false ],
			],
			'change_package_status' => [
				[ 'key' => 'purchase_id', 'label' => 'Package Purchase ID', 'type' => 'number', 'required' => true ],
				[
					'key'      => 'status',
					'label'    => 'Status',
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
				[ 'key' => 'purchase_id', 'label' => 'Package Purchase ID (single)', 'type' => 'number', 'required' => false ],
				[ 'key' => 'ids', 'label' => 'Purchase IDs (comma-separated, for bulk delete)', 'type' => 'text', 'required' => false ],
			],
			'check_in_ticket' => [
				[ 'key' => 'ticket_code', 'label' => 'Ticket Code', 'type' => 'text', 'required' => true ],
			],
			'undo_ticket_checkin' => [
				[ 'key' => 'ticket_code', 'label' => 'Ticket Code', 'type' => 'text', 'required' => true ],
			],
			'check_in_all_tickets' => [
				[ 'key' => 'ticket_code', 'label' => 'Ticket Code (any ticket on the booking)', 'type' => 'text', 'required' => true ],
			],
			'send_sms_whatsapp' => [
				[ 'key' => 'to', 'label' => 'Phone Number', 'type' => 'text', 'required' => true ],
				[ 'key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true ],
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
			...self::booking_id_field(),
			[ 'key' => 'data', 'label' => 'Data (JSON)', 'type' => 'textarea', 'required' => false ],
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

	/* -----------------------------------------------------------------
	 * Core booking actions — GemBooking\Classes\BookingSolver
	 * --------------------------------------------------------------- */

	private static function action_create_booking( array $config, array $input ): array {
		$type        = sanitize_key( (string) ( $config['booking_type'] ?? '' ) );
		$bookable_id = absint( $config['bookable'] ?? 0 );

		if ( ! in_array( $type, [ 'service', 'event', 'resource', 'package' ], true ) || ! $bookable_id ) {
			return self::action_error( 'A valid booking_type and bookable are required.' );
		}

		// Verify the bookable item exists
		if ( 'package' !== $type ) {
			$post = get_post( $bookable_id );
			if ( ! $post || 'publish' !== $post->post_status ) {
				return self::action_error( 'The selected bookable item does not exist or is not published.' );
			}
		}

		if ( 'package' === $type ) {
			$customer_id = absint( $config['customer_id'] ?? 0 );
			$email       = sanitize_email( (string) ( $config['customer_email'] ?? '' ) );

			if ( ! $customer_id && ! is_email( $email ) ) {
				return self::action_error( 'Select an existing customer or provide a customer_email.' );
			}

			// A package is granted, not "booked" — route to the grant flow,
			// matching the "Grant package" button in the admin modal.
			return self::call_controller( '\\GemBookingPackage\\API\\GrantController', 'grant', array_filter( [
				'package_id' => $bookable_id,
				'user_id'    => $customer_id ?: null,
				'email'      => $email ?: null,
			] ) );
		}

		$data = [
			'object_id'   => $bookable_id,
			'object_type' => $type,
			'start_at'    => (string) ( $config['start_at'] ?? '' ),
			'end_at'      => (string) ( $config['end_at'] ?? '' ),
			'timezone'    => (string) ( $config['timezone'] ?? '' ),
			'fields'      => [
				'name'           => (string) ( $config['first_name'] ?? '' ),
				'email'          => sanitize_email( (string) ( $config['email'] ?? '' ) ),
				'phone'          => (string) ( $config['phone'] ?? '' ),
			],
		];

		// Add note if provided
		if ( ! empty( $config['note'] ) ) {
			$data['fields']['meeting_about'] = (string) $config['note'];
		}

		// Add optional parameters if provided
		if ( ! empty( $config['host_id'] ) ) {
			$data['host_id'] = absint( $config['host_id'] );
		}

		if ( ! empty( $config['team_member_id'] ) ) {
			$data['team_member_id'] = absint( $config['team_member_id'] );
		}

		if ( ! empty( $config['attendee_count'] ) ) {
			$data['attendee_count'] = max( 1, absint( $config['attendee_count'] ) );
		}

		if ( '' === $data['start_at'] || '' === $data['end_at'] || '' === $data['timezone'] || '' === $data['fields']['name'] || ! is_email( $data['fields']['email'] ) ) {
			return self::action_error( 'start_at, end_at, timezone, first_name and a valid email are required.' );
		}

		// Validate datetime format
		$start_time = strtotime( $data['start_at'] );
		$end_time = strtotime( $data['end_at'] );

		if ( false === $start_time || false === $end_time ) {
			return self::action_error( 'Invalid datetime format for start_at or end_at.' );
		}

		if ( $end_time <= $start_time ) {
			return self::action_error( 'end_at must be after start_at.' );
		}

		// Validate timezone
		if ( ! in_array( $data['timezone'], \DateTimeZone::listIdentifiers(), true ) ) {
			return self::action_error( 'Invalid timezone provided.' );
		}

		return self::call_solver( '\\GemBooking\\Classes\\BookingSolver', 'create_booked', $data );
	}

	private static function action_change_booking_status( array $config, array $input ): array {
		$id     = absint( $config['booking_id'] ?? 0 );
		$status = sanitize_key( (string) ( $config['booking_status'] ?? '' ) );

		if ( ! $id || '' === $status ) {
			return self::action_error( 'A valid booking_id and status are required.' );
		}

		// Validate status values
		$valid_statuses = [ 'pending', 'confirmed', 'cancelled', 'rescheduled' ];
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return self::action_error( 'Invalid status value. Must be one of: pending, confirmed, cancelled, rescheduled.' );
		}

		return self::call_solver( '\\GemBooking\\Classes\\BookingSolver', 'update_booked_status', [
			'id' => $id,
			'status' => $status,
		] );
	}

	private static function action_reschedule_booking( array $config, array $input ): array {
		$id       = absint( $config['booking_id'] ?? 0 );
		$start_at = (string) ( $config['start_at'] ?? '' );
		$end_at   = (string) ( $config['end_at'] ?? '' );

		if ( ! $id || '' === $start_at || '' === $end_at ) {
			return self::action_error( 'A valid booking_id, start_at and end_at are required.' );
		}

		// Validate datetime format
		$start_time = strtotime( $start_at );
		$end_time = strtotime( $end_at );

		if ( false === $start_time || false === $end_time ) {
			return self::action_error( 'Invalid datetime format for start_at or end_at.' );
		}

		if ( $end_time <= $start_time ) {
			return self::action_error( 'end_at must be after start_at.' );
		}

		// Build the proper data structure expected by GemBooking
		$data = [
			'id'       => $id,
			'start_at' => $start_at,
			'end_at'   => $end_at,
		];

		return self::call_solver( '\\GemBooking\\Classes\\BookingSolver', 'update_booking_status_with_reschedule', $data );
	}

	private static function action_cancel_booking( array $config, array $input ): array {
		$id = absint( $config['booking_id'] ?? 0 );

		if ( ! $id ) {
			return self::action_error( 'A valid booking_id is required.' );
		}

		// Use the simpler status update method to avoid permission issues
		return self::call_solver( '\\GemBooking\\Classes\\BookingSolver', 'update_booked_status', [
			'id' => $id,
			'status' => 'cancelled',
		] );
	}

	private static function action_delete_booking( array $config, array $input ): array {
		$id = absint( $config['booking_id'] ?? 0 );

		if ( ! $id ) {
			return self::action_error( 'A valid booking_id is required.' );
		}

		return self::call_solver( '\\GemBooking\\Classes\\BookingSolver', 'destroy_booked', [ 'id' => $id ] );
	}

	private static function action_add_booking_note( array $config, array $input ): array {
		$id   = absint( $config['booking_id'] ?? 0 );
		$note = (string) ( $config['note'] ?? '' );

		if ( ! $id || '' === trim( $note ) ) {
			return self::action_error( 'A valid booking_id and note are required.' );
		}

		return self::call_solver( '\\GemBooking\\Classes\\BookingSolver', 'update_booking_note', [
			'id' => $id,
			'note' => $note,
		] );
	}

	private static function action_reply_customer( array $config, array $input ): array {
		$id      = absint( $config['booking_id'] ?? 0 );
		$message = (string) ( $config['message'] ?? '' );

		if ( ! $id || '' === trim( $message ) ) {
			return self::action_error( 'A valid booking_id and message are required.' );
		}

		return self::call_solver( 'GemBooking\Classes\BookingSolver', 'add_staff_reply', [
			'id' => $id,
			'message' => $message,
		] );
	}

	/* -----------------------------------------------------------------
	 * Model-direct actions (no request object involved)
	 * --------------------------------------------------------------- */

	private static function action_update_booking_field( array $config, array $input ): array {
		$id  = absint( $config['booking_id'] ?? 0 );
		$key = sanitize_key( (string) ( $config['field_key'] ?? $config['key'] ?? '' ) );

		if ( ! $id || '' === $key || ! class_exists( '\\GemBooking\\Models\\BookingMetaModel' ) || ! method_exists( '\\GemBooking\\Models\\BookingMetaModel', 'update' ) ) {
			return self::action_error( 'A valid booking ID, custom field key, and GemBooking BookingMetaModel::update() are required.' );
		}

		$reflection = new \ReflectionMethod( '\\GemBooking\\Models\\BookingMetaModel', 'update' );

		if ( $reflection->isStatic() ) {
			$result = \GemBooking\Models\BookingMetaModel::update( $id, $key, $config['value'] ?? '' );
		} else {
			$model  = new \GemBooking\Models\BookingMetaModel();
			$result = $model->update( $id, $key, $config['value'] ?? '' );
		}

		return false === $result ? self::action_error( 'GemBooking could not update the booking custom field.' ) : self::action_success( [
			'booking_id' => $id,
			'key' => $key,
			'value' => $config['value'] ?? ''
		] );
	}

	private static function action_find_booking( array $config, array $input ): array {
		if ( ! class_exists( '\\GemBooking\\Models\\BookingModels' ) ) {
			return self::action_error( 'GemBooking BookingModels is not available.' );
		}
		$model = new \GemBooking\Models\BookingModels();
		$id    = absint( $config['booking_id'] ?? 0 );

		if ( $id ) {
			$booking = $model->find_by_id( $id );

			return self::action_success( is_array( $booking ) ? $booking : [] );
		}

		return self::action_success( $model->get_list(
			absint( $config['page'] ?? 1 ) ?: 1,
			absint( $config['limit'] ?? 20 ) ?: 20,
			sanitize_text_field( (string) ( $config['search'] ?? '' ) ),
			sanitize_key( (string) ( $config['status'] ?? '' ) )
		) );
	}

	private static function action_check_slot_availability( array $config, array $input ): array {
		if ( ! class_exists( '\\GemBooking\\Models\\BookingModels' ) ) {
			return self::action_error( 'GemBooking BookingModels is not available.' );
		}
		$data  = self::decode_data( $config['data'] ?? [] );
		$model = new \GemBooking\Models\BookingModels();

		if ( ! method_exists( $model, 'is_slot_available' ) ) {
			return self::action_error( 'GemBooking slot availability is not available in this plugin version.' );
		}

		$object_id = absint( $data['object_id'] ?? $config['object_id'] ?? 0 );
		$start_at  = (string) ( $data['start_at'] ?? $config['start_at'] ?? '' );
		$end_at    = (string) ( $data['end_at'] ?? $config['end_at'] ?? '' );

		if ( ! $object_id || '' === $start_at || '' === $end_at ) {
			return self::action_error( 'object_id, start_at and end_at are required.' );
		}

		$team_id   = isset( $data['team_id'] ) ? absint( $data['team_id'] ) : ( isset( $config['team_id'] ) ? absint( $config['team_id'] ) : null );
		$available = $model->is_slot_available( $object_id, $start_at, $end_at, $team_id );

		return self::action_success( [ 'available' => (bool) $available ] );
	}

	/* -----------------------------------------------------------------
	 * Controller-direct actions — no REST routing, we call the
	 * controller method straight with a manually built request.
	 * --------------------------------------------------------------- */

	private static function action_add_staff_time_off( array $config, array $input ): array {
		$team_id = absint( $config['team_id'] ?? 0 );

		// Support both start_at/start_date and end_at/end_date for compatibility
		$start_date = (string) ( $config['start_at'] ?? $config['start_date'] ?? '' );
		$end_date = (string) ( $config['end_at'] ?? $config['end_date'] ?? '' );

		if ( ! $team_id || '' === $start_date ) {
			return self::action_error( 'A valid team_id and start_at are required.' );
		}

		// Convert datetime to date format if needed
		$start_time = strtotime( $start_date );
		if ( false === $start_time ) {
			return self::action_error( 'Invalid start_at format.' );
		}

		// Extract just the date part (YYYY-MM-DD)
		$start_date = date( 'Y-m-d', $start_time );

		// Handle end_date
		if ( '' !== $end_date ) {
			$end_time = strtotime( $end_date );
			if ( false === $end_time ) {
				return self::action_error( 'Invalid end_at format.' );
			}
			$end_date = date( 'Y-m-d', $end_time );
		} else {
			// Default to single day if end_date not provided
			$end_date = $start_date;
		}

		// Build the proper exception format expected by GemBooking
		$exceptions = [];
		$current_date = $start_date;
		
		while ( strtotime( $current_date ) <= strtotime( $end_date ) ) {
			$exceptions[] = [
				'date' => $current_date,
				'type' => 'unavailable',
			];
			$current_date = date( 'Y-m-d', strtotime( $current_date . ' +1 day' ) );
		}

		return self::call_controller( 'GemBooking\API\ExceptionController', 'store', [
			'source' => 'team',
			'id' => $team_id,
			'exceptions' => $exceptions,
		] );
	}

	private static function action_update_customer_profile( array $config, array $input ): array {
		// GemBooking's own controller only works for the logged-in user, so
		// this updates the WordPress user record and GemBooking's profile
		// meta directly, which works for any user_id.
		$id = absint( $config['user_id'] ?? 0 );

		if ( ! $id || ! get_user_by( 'id', $id ) ) {
			return self::action_error( 'A valid customer user_id is required.' );
		}

		$data = self::decode_data( $config['data'] ?? [] );
		$user = [ 'ID' => $id ];

		foreach ( [ 'first_name', 'last_name', 'display_name', 'user_email' ] as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$user[ $field ] = 'user_email' === $field
					? sanitize_email( (string) $data[ $field ] )
					: sanitize_text_field( (string) $data[ $field ] );
			}
		}

		$result = wp_update_user( $user );

		if ( is_wp_error( $result ) ) {
			return self::action_error( $result->get_error_message() );
		}

		foreach ( [ 'phone', 'timezone' ] as $field ) {
			if ( isset( $data[ $field ] ) ) {
				update_user_meta( $id, $field, sanitize_text_field( (string) $data[ $field ] ) );
			}
		}

		return self::action_success( [ 'user_id' => $id, 'updated' => true ] );
	}

	private static function action_duplicate_bookable( array $config, array $input ): array {
		$source_id = absint( $config['source_id'] ?? 0 );

		if ( ! $source_id ) {
			return self::action_error( 'A valid source_id is required.' );
		}

		// DuplicateController expects 'id' parameter (the source item to duplicate)
		return self::call_controller( '\\GemBooking\\API\\DuplicateController', 'duplicate', [
			'id' => $source_id,
		] );
	}

	/* -----------------------------------------------------------------
	 * Add-on actions
	 * --------------------------------------------------------------- */

	private static function action_send_template_email( array $config, array $input ): array {
		$to      = sanitize_email( (string) ( $config['to'] ?? '' ) );
		$subject = (string) ( $config['subject'] ?? '' );
		$body    = (string) ( $config['body'] ?? '' );

		if ( ! is_email( $to ) || '' === $subject || '' === trim( $body ) ) {
			return self::action_error( 'A valid recipient email, subject and body are required.' );
		}

		if ( ! class_exists( '\\GemBookingEmail\\Email\\Mail' ) || ! method_exists( '\\GemBookingEmail\\Email\\Mail', 'send_mail' ) ) {
			return self::action_error( 'The GemBooking Email add-on is not active.' );
		}

		try {
			$result = self::invoke( '\\GemBookingEmail\\Email\\Mail', 'send_mail', [ $to, $subject, $body, [], [] ] );
		} catch ( \Throwable $e ) {
			return self::action_error( 'Failed to send email: ' . $e->getMessage() );
		}

		if ( $result instanceof \WP_Error ) {
			return self::action_error( $result->get_error_message() );
		}
		if ( false === $result ) {
			return self::action_error( 'GemBooking could not send the email.' );
		}

		return self::action_success( [ 'to' => $to, 'subject' => $subject ] );
	}

	private static function action_grant_package( array $config, array $input ): array {
		$package_id = absint( $config['package_id'] ?? 0 );
		$user_id    = absint( $config['user_id'] ?? 0 );
		$email      = sanitize_email( (string) ( $config['email'] ?? '' ) );

		if ( ! $package_id || ( ! $user_id && ! is_email( $email ) ) ) {
			return self::action_error( 'A valid package_id and either a user_id or email are required.' );
		}

		$params = [ 'package_id' => $package_id ];
		if ( $user_id ) {
			$params['user_id'] = $user_id;
		}
		if ( is_email( $email ) ) {
			$params['email'] = $email;
		}

		return self::call_controller( '\\GemBookingPackage\\API\\GrantController', 'grant', $params );
	}

	private static function action_change_package_status( array $config, array $input ): array {
		$id     = absint( $config['purchase_id'] ?? $config['id'] ?? 0 );
		$status = sanitize_key( (string) ( $config['status'] ?? '' ) );

		if ( ! $id || '' === $status ) {
			return self::action_error( 'A valid purchase_id and status are required.' );
		}

		return self::call_controller( '\\GemBookingPackage\\API\\PurchasesController', 'set_status', [
			'id' => $id,
			'status' => $status,
		] );
	}

	private static function action_delete_package_purchase( array $config, array $input ): array {
		$ids_raw = (string) ( $config['ids'] ?? '' );
		$ids     = array_filter( array_map( 'absint', array_map( 'trim', explode( ',', $ids_raw ) ) ) );

		if ( ! empty( $ids ) ) {
			return self::call_controller( '\\GemBookingPackage\\API\\PurchasesController', 'bulk_destroy', [
				'ids' => array_values( $ids ),
			] );
		}

		$id = absint( $config['purchase_id'] ?? $config['id'] ?? 0 );

		if ( ! $id ) {
			return self::action_error( 'A valid purchase_id (or comma-separated ids for bulk delete) is required.' );
		}

		return self::call_controller( '\\GemBookingPackage\\API\\PurchasesController', 'destroy', [ 'id' => $id ] );
	}

	private static function action_check_in_ticket( array $config, array $input ): array {
		$code = sanitize_text_field( (string) ( $config['ticket_code'] ?? $config['code'] ?? '' ) );

		if ( '' === $code ) {
			return self::action_error( 'A valid ticket_code is required.' );
		}

		return self::call_controller( '\\GemBookingTicket\\API\\ScanController', 'redeem', [ 'code' => $code ] );
	}

	private static function action_undo_ticket_checkin( array $config, array $input ): array {
		$code = sanitize_text_field( (string) ( $config['ticket_code'] ?? $config['code'] ?? '' ) );

		if ( '' === $code ) {
			return self::action_error( 'A valid ticket_code is required.' );
		}

		return self::call_controller( '\\GemBookingTicket\\API\\ScanController', 'unredeem', [ 'code' => $code ] );
	}

	private static function action_check_in_all_tickets( array $config, array $input ): array {
		$code = sanitize_text_field( (string) ( $config['ticket_code'] ?? $config['code'] ?? '' ) );

		if ( '' === $code ) {
			return self::action_error( 'A valid ticket_code is required.' );
		}

		return self::call_controller( '\\GemBookingTicket\\API\\ScanController', 'redeem_all', [ 'code' => $code ] );
	}

	private static function action_send_sms_whatsapp( array $config, array $input ): array {
		$to      = sanitize_text_field( (string) ( $config['to'] ?? '' ) );
		$message = (string) ( $config['message'] ?? '' );
		$channel = sanitize_key( (string) ( $config['channel'] ?? 'sms' ) );

		if ( '' === $to || '' === trim( $message ) ) {
			return self::action_error( 'A valid recipient phone number and message are required.' );
		}

		if ( ! class_exists( '\\GemBookingSms\\Sms\\Client' ) || ! method_exists( '\\GemBookingSms\\Sms\\Client', 'send' ) ) {
			return self::action_error( 'The GemBooking SMS add-on is not active.' );
		}

		// GemBooking's own settings array format for Client::send() isn't
		// documented; this filter lets it be supplied from GemBooking's
		// saved options (or wherever this site keeps them) without
		// guessing the shape here.
		$settings = apply_filters( 'zaplane/gembooking_sms_settings', [], $channel );

		try {
			$result = self::invoke( '\\GemBookingSms\\Sms\\Client', 'send', [ $to, $message, $settings, $channel ] );
		} catch ( \Throwable $e ) {
			return self::action_error( 'Failed to send SMS/WhatsApp: ' . $e->getMessage() );
		}

		if ( $result instanceof \WP_Error ) {
			return self::action_error( $result->get_error_message() );
		}
		if ( false === $result ) {
			return self::action_error( 'GemBooking could not send the message.' );
		}

		return self::action_success( [ 'to' => $to, 'channel' => $channel ] );
	}

	/**
	 * Create Staff Member has no clean callable: GemBooking only exposes it
	 * via AJAX (reads $_POST, ends with wp_send_json + die()). Until that
	 * logic is refactored into a plain method on GemBooking's side, this
	 * stays behind a filter so a site can hook it in safely.
	 *
	 * Schema expects: name (required), email (required), role (optional)
	 */
	private static function action_create_staff( array $config, array $input ): array {
		$name  = sanitize_text_field( (string) ( $config['name'] ?? '' ) );
		$email = sanitize_email( (string) ( $config['email'] ?? '' ) );
		$role  = sanitize_text_field( (string) ( $config['role'] ?? '' ) );

		if ( '' === $name || ! is_email( $email ) ) {
			return self::action_error( 'A valid name and email are required.' );
		}

		$result = apply_filters( 'zaplane/gembooking_action', null, 'create_staff', $config, $input );

		return null === $result
			? self::action_error( 'Create Staff Member needs a "zaplane/gembooking_action" filter implementation — GemBooking only exposes this via AJAX.' )
			: self::action_success( is_array( $result ) ? $result : [ 'data' => $result ] );
	}

	/* -----------------------------------------------------------------
	 * Call helpers
	 * --------------------------------------------------------------- */

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
		];
	}

	public static function query_booking( $q = null ): array {
		if ( ! class_exists( '\\GemBooking\\Models\\BookingModels' ) ) {
			return [];
		}

		$model = new \GemBooking\Models\BookingModels();

		$search = '';
		$booking_id = 0;

		if ( is_array( $q ) ) {
			$search     = sanitize_text_field( (string) ( $q['search'] ?? '' ) );
			$booking_id = absint( $q['booking_id'] ?? 0 );
		} elseif ( is_numeric( $q ) ) {
			$booking_id = absint( $q );
		} elseif ( is_string( $q ) ) {
			$search = sanitize_text_field( $q );
		}

		/*
		 * If a specific booking ID is requested,
		 * return only that booking.
		 */
		if ( $booking_id ) {
			$booking = $model->find_by_id( $booking_id );

			if ( ! is_array( $booking ) || empty( $booking ) ) {
				return [];
			}

			return [
				[
					'value' => $booking_id,
					'label' => self::booking_query_label( $booking, $booking_id ),
				],
			];
		}

		/*
		 * Load booking list.
		 */
		$result = $model->get_list(
			1,
			100,
			$search,
			''
		);

		if ( ! is_array( $result ) ) {
			return [];
		}

		/*
		 * GemBooking may return rows directly or inside "rows"/"data".
		 */
		$rows = $result;

		if ( isset( $result['rows'] ) && is_array( $result['rows'] ) ) {
			$rows = $result['rows'];
		} elseif ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
			$rows = $result['data'];

			if ( isset( $result['data']['rows'] ) && is_array( $result['data']['rows'] ) ) {
				$rows = $result['data']['rows'];
			}
		}

		$options = [];

		foreach ( $rows as $item ) {
			$row = is_object( $item ) ? get_object_vars( $item ) : ( is_array( $item ) ? $item : [] );
			if ( empty( $row ) ) {
				continue;
			}

			$id = absint(
				$row['id']
				?? $row['booking_id']
				?? $row['booked_id']
				?? 0
			);

			if ( ! $id ) {
				continue;
			}

			$options[] = [
				'value' => $id,
				'label' => self::booking_query_label( $row, $id ),
			];
		}

		return $options;
	}

	private static function booking_query_label( array $booking, $id ): string {
		$name = trim(
			(string) (
				$booking['name']
				?? $booking['customer_name']
				?? $booking['first_name']
				?? ''
			)
		);

		$email = trim(
			(string) (
				$booking['email']
				?? $booking['customer_email']
				?? ''
			)
		);

		$status = trim(
			(string) ( $booking['status'] ?? '' )
		);

		$label = '#' . absint( $id );

		if ( '' !== $name ) {
			$label .= ' - ' . $name;
		} elseif ( '' !== $email ) {
			$label .= ' - ' . $email;
		}

		if ( '' !== $email && '' !== $name ) {
			$label .= ' (' . $email . ')';
		}

		if ( '' !== $status ) {
			$label .= ' - ' . ucfirst( $status );
		}

		return $label;
	}

	public static function query_bookables( $q = null ): array {
		$type = is_array( $q ) ? sanitize_key( (string) ( $q['booking_type'] ?? '' ) ) : '';
		$post_type_map = [
			'service'  => self::bookable_post_type( 'service', 'gembk_service' ),
			'event'    => self::bookable_post_type( 'event', 'gembk_event' ),
			'resource' => self::bookable_post_type( 'resource', 'gembk_resource' ),
			'package'  => 'gembk_package',
		];
		$post_type = $post_type_map[ $type ] ?? $post_type_map['service'];

		$posts = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		return array_map( static fn( $p ) => [ 'value' => $p->ID, 'label' => $p->post_title ], $posts );
	}

	public static function query_customers( $q = null ): array {
		$search = is_array( $q ) ? sanitize_text_field( (string) ( $q['search'] ?? '' ) ) : '';

		$users = get_users( [
			'search'         => $search ? '*' . $search . '*' : '',
			'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
			'number'         => 20,
			'role__in'       => [ 'subscriber', 'customer' ],
		] );

		return array_map( static fn( $u ) => [
			'value' => $u->ID,
			'label' => $u->display_name . ' (' . $u->user_email . ')',
		], $users );
	}

	public static function query_timezones( $q = null ): array {
		$search = is_array( $q ) ? strtolower( sanitize_text_field( (string) ( $q['search'] ?? '' ) ) ) : '';

		// Use caching for timezone data since it doesn't change frequently
		$cache_key = 'zaplane_gembooking_timezones';
		$zones = function_exists( 'wp_cache_get' ) ? wp_cache_get( $cache_key ) : false;

		if ( false === $zones ) {
			$zones = [];

			// UTC first.
			$zones[] = [
				'value' => 'UTC',
				'label' => '(GMT+0:00) UTC',
				'offset' => 0,
			];

			foreach ( \DateTimeZone::listIdentifiers( \DateTimeZone::ALL ) as $identifier ) {
				if ( 'UTC' === $identifier ) {
					continue;
				}

				try {
					$tz     = new \DateTimeZone( $identifier );
					$offset = $tz->getOffset( new \DateTime( 'now', $tz ) );
				} catch ( \Throwable $e ) {
					continue;
				}

				$hours   = intdiv( abs( $offset ), 3600 );
				$minutes = intdiv( abs( $offset ) % 3600, 60 );
				$sign    = $offset < 0 ? '-' : '+';

				$zones[] = [
					'value'  => $identifier,
					'label'  => sprintf( '(GMT%s%d:%02d) %s', $sign, $hours, $minutes, $identifier ),
					'offset' => $offset,
				];
			}

			// Cache for 1 hour if WordPress cache functions are available
			if ( function_exists( 'wp_cache_set' ) && function_exists( 'HOUR_IN_SECONDS' ) ) {
				wp_cache_set( $cache_key, $zones, '', HOUR_IN_SECONDS );
			}
		}

		if ( '' !== $search ) {
			$zones = array_values( array_filter( $zones, static function ( $zone ) use ( $search ) {
				return false !== strpos( strtolower( $zone['label'] ), $search );
			} ) );
		}

		// Drop the internal sort key before returning.
		return array_map( static fn( $zone ) => [
			'value' => $zone['value'],
			'label' => $zone['label'],
		], $zones );
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
