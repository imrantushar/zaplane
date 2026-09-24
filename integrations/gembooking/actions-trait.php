<?php

namespace Zaplane\Integrations\Gembooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ActionsTrait
{

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
			$email       = sanitize_email( (string) ( $config['email'] ?? '' ) );

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

		return self::call_solver( '\\GemBooking\\Classes\\BookingSolver', 'add_staff_reply', [
			'id' => $id,
			'message' => $message,
		] );
	}

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
			sanitize_key( (string) ( $config['filter_status'] ?? '' ) )
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

		$object_id = absint( $data['object_id'] ?? $config['bookable'] ?? $config['object_id'] ?? 0 );
		$start_at  = (string) ( $data['start_at'] ?? $config['start_at'] ?? '' );
		$end_at    = (string) ( $data['end_at'] ?? $config['end_at'] ?? '' );

		if ( ! $object_id || '' === $start_at || '' === $end_at ) {
			return self::action_error( 'object_id, start_at and end_at are required.' );
		}

		$team_id   = isset( $data['team_id'] ) ? absint( $data['team_id'] ) : ( isset( $config['team_id'] ) ? absint( $config['team_id'] ) : null );
		$available = $model->is_slot_available( $object_id, $start_at, $end_at, $team_id );

		return self::action_success( [ 'available' => (bool) $available ] );
	}

	private static function action_add_staff_time_off( array $config, array $input ): array {
		$team_id = absint( $config['team_id'] ?? 0 );

		if ( ! $team_id || '' === (string) ( $config['start_at'] ?? '' ) ) {
			return self::action_error( 'A valid team_id and start_at are required.' );
		}

		return self::call_controller( '\\GemBooking\\API\\ExceptionController', 'store', [
			'team_id' => $team_id,
			'start_at' => (string) $config['start_at'],
			'end_at' => (string) ( $config['end_at'] ?? $config['start_at'] ),
			'reason' => (string) ( $config['reason'] ?? '' ),
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

		$user = [ 'ID' => $id ];

		foreach ( [ 'first_name', 'last_name', 'display_name' ] as $field ) {
			if ( isset( $config[ $field ] ) && '' !== (string) $config[ $field ] ) {
				$user[ $field ] = sanitize_text_field( (string) $config[ $field ] );
			}
		}

		if ( isset( $config['email'] ) && '' !== (string) $config['email'] ) {
			$user['user_email'] = sanitize_email( (string) $config['email'] );
		}

		$result = wp_update_user( $user );

		if ( is_wp_error( $result ) ) {
			return self::action_error( $result->get_error_message() );
		}

		foreach ( [ 'phone', 'timezone' ] as $field ) {
			if ( isset( $config[ $field ] ) && '' !== (string) $config[ $field ] ) {
				update_user_meta( $id, $field, sanitize_text_field( (string) $config[ $field ] ) );
			}
		}

		return self::action_success( [ 'user_id' => $id ] );
	}

	private static function action_duplicate_bookable( array $config, array $input ): array {
		$source_id = absint( $config['bookable'] ?? $config['source_id'] ?? $config['id'] ?? 0 );

		if ( ! $source_id ) {
			return self::action_error( 'A valid source_id is required.' );
		}

		return self::call_controller( '\\GemBooking\\API\\DuplicateController', 'duplicate', [
			'source_id' => $source_id,
			'id' => $source_id,
		] );
	}

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
		$status = sanitize_key( (string) ( $config['package_status'] ?? '' ) );

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
		$to      = sanitize_text_field( (string) ( $config['phone'] ?? $config['to'] ?? '' ) );
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
	 */
	private static function action_create_staff( array $config, array $input ): array {
		$result = apply_filters( 'zaplane/gembooking_action', null, 'create_staff', $config, $input );

		return null === $result
			? self::action_error( 'Create Staff Member needs a "zaplane/gembooking_action" filter implementation — GemBooking only exposes this via AJAX.' )
			: self::action_success( is_array( $result ) ? $result : [ 'data' => $result ] );
	}

	private static function action_create_service( array $config, array $input ): array {
		return self::bookable_create( 'service', $config, $input );
	}

	private static function action_service_status_change( array $config, array $input ): array {
		return self::bookable_change_status( 'service', $config );
	}

	private static function action_service_trash( array $config, array $input ): array {
		return self::bookable_remove( 'service', $config );
	}

	private static function action_create_event( array $config, array $input ): array {
		return self::bookable_create( 'event', $config, $input );
	}

	private static function action_event_status_change( array $config, array $input ): array {
		return self::bookable_change_status( 'event', $config );
	}

	private static function action_event_delete( array $config, array $input ): array {
		return self::bookable_remove( 'event', $config );
	}

	private static function action_create_resource( array $config, array $input ): array {
		return self::bookable_create( 'resource', $config, $input );
	}

	private static function action_resource_status_change( array $config, array $input ): array {
		return self::bookable_change_status( 'resource', $config );
	}

	private static function action_resource_trash( array $config, array $input ): array {
		return self::bookable_remove( 'resource', $config );
	}

	/**
	 * Create a GemBooking service / event / resource.
	 *
	 * Meta is written through wp_insert_post()'s `meta_input`, so it already
	 * exists when save_post_{type} fires — the "Service/Event/Resource
	 * Created" triggers therefore see the full meta.
	 */
	private static function bookable_create( string $kind, array $config, array $input ): array {
		$title = sanitize_text_field( (string) ( $config['title'] ?? '' ) );

		if ( '' === $title ) {
			return self::action_error( sprintf( 'A %s title is required.', $kind ) );
		}

		$status = self::bookable_status( $config['bookable_status'] ?? $config['status'] ?? 'publish' );

		if ( null === $status ) {
			return self::action_error( 'Invalid status. Use publish, draft, pending or private.' );
		}

		// Events need a host (the admin form marks Host as required).
		$host_id = 0;
		if ( 'event' === $kind ) {
			$host_id = absint( $config['host_id'] ?? 0 );

			if ( ! $host_id || ! get_user_by( 'id', $host_id ) ) {
				return self::action_error( 'A valid host (staff user ID) is required to create an event.' );
			}
		}

		// Custom meta: JSON object or already-decoded array.
		$raw_extra = $config['custom_meta'] ?? '';
		if ( is_array( $raw_extra ) ) {
			$extra = $raw_extra;
		} elseif ( '' === trim( (string) $raw_extra ) ) {
			$extra = [];
		} else {
			$extra = json_decode( (string) $raw_extra, true );
			if ( ! is_array( $extra ) ) {
				return self::action_error( 'Custom Meta must be a valid JSON object, e.g. {"my_key":"value"}.' );
			}
		}

		$map  = self::bookable_meta_map( $kind );
		$meta = [];

		// Price.
		if ( isset( $config['price'] ) && '' !== trim( (string) $config['price'] ) ) {
			if ( ! is_numeric( $config['price'] ) || (float) $config['price'] < 0 ) {
				return self::action_error( 'Price must be a number, 0 or higher.' );
			}
			$meta[ $map['price'] ] = (float) $config['price'];
		}

		// Duration (entered in minutes, stored in seconds).
		if ( isset( $config['duration'] ) && '' !== trim( (string) $config['duration'] ) ) {
			if ( ! is_numeric( $config['duration'] ) || (float) $config['duration'] <= 0 ) {
				return self::action_error( 'Duration must be a positive number of minutes.' );
			}
			$meta[ $map['duration'] ] = (int) round( (float) $config['duration'] * MINUTE_IN_SECONDS );
		}

		// Capacity / parallel bookings.
		if ( isset( $config['capacity'] ) && '' !== trim( (string) $config['capacity'] ) ) {
			$meta[ $map['capacity'] ] = max( 1, absint( $config['capacity'] ) );
		}

		if ( 'event' === $kind ) {
			$meta[ $map['host_id'] ] = $host_id;

			if ( '' !== trim( (string) ( $config['location'] ?? '' ) ) ) {
				$meta[ $map['location'] ] = sanitize_text_field( (string) $config['location'] );
			}
		}

		if ( 'service' === $kind && isset( $config['advance_reservation'] ) && '' !== (string) $config['advance_reservation'] ) {
			$meta[ $map['advance_reservation'] ] = 'yes' === sanitize_key( (string) $config['advance_reservation'] ) ? 1 : 0;
		}

		// Images: first = featured image, the rest = gallery (services only).
		$warnings  = [];
		$raw_imgs  = (string) ( $config['images'] ?? $config['image'] ?? '' );
		$images    = preg_split( '/[\s,]+/', trim( $raw_imgs ), -1, PREG_SPLIT_NO_EMPTY );
		$image_ids = [];

		foreach ( $images as $image ) {
			$attachment_id = self::bookable_resolve_image( $image );

			if ( $attachment_id ) {
				$image_ids[] = $attachment_id;
			} else {
				$warnings[] = sprintf( 'Image "%s" could not be used (not an attachment ID or a downloadable URL).', $image );
			}
		}

		if ( ! empty( $image_ids ) ) {
			$meta['_thumbnail_id'] = $image_ids[0];

			if ( 'service' === $kind && count( $image_ids ) > 1 ) {
				$meta[ $map['gallery'] ] = array_slice( $image_ids, 1 );
			}
		}

		/**
		 * Filter the final meta array before it is saved.
		 *
		 * @param array  $meta   Meta to be stored (key => value).
		 * @param string $kind   service | event | resource.
		 * @param array  $config Raw node config.
		 */
		$meta = (array) apply_filters( 'zaplane/gembooking_bookable_meta', $meta, $kind, $config );

		// Custom meta always wins, so a site can override any key exactly.
		foreach ( $extra as $key => $value ) {
			$key = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $key );
			if ( '' !== $key ) {
				$meta[ $key ] = $value;
			}
		}

		$author = get_current_user_id();
		if ( ! $author ) {
			$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
			$author = (int) ( $admins[0] ?? 0 );
		}

		$post_id = wp_insert_post(
			wp_slash( [
				'post_type'    => self::bookable_type_for( $kind ),
				'post_title'   => $title,
				'post_content' => wp_kses_post( (string) ( $config['description'] ?? '' ) ),
				'post_status'  => $status,
				'post_author'  => $author,
				'meta_input'   => $meta,
			] ),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return self::action_error( $post_id->get_error_message() );
		}

		if ( ! $post_id ) {
			return self::action_error( sprintf( 'GemBooking could not create the %s.', $kind ) );
		}

		/**
		 * Fires after a service/event/resource is created by Zaplane.
		 * Hook here for site-specific extras (link a WooCommerce/StoreEngine
		 * product, attach a booking form, assign team members, ...).
		 */
		do_action( 'zaplane/gembooking_bookable_saved', $post_id, $kind, $config, $input );

		$result = self::bookable_summary( get_post( $post_id ), $kind );

		if ( ! empty( $warnings ) ) {
			$result['warnings'] = $warnings;
		}

		return self::action_success( $result );
	}

	private static function bookable_change_status( string $kind, array $config ): array {
		$post = self::bookable_load( $kind, $config );

		if ( is_array( $post ) ) {
			return $post;
		}

		$status = self::bookable_status( $config['bookable_status'] ?? $config['status'] ?? '' );

		if ( null === $status ) {
			return self::action_error( 'Invalid status. Use publish, draft, pending or private.' );
		}

		$previous = $post->post_status;

		if ( $previous === $status ) {
			$result                    = self::bookable_summary( $post, $kind );
			$result['previous_status'] = $previous;
			$result['changed']         = false;

			return self::action_success( $result );
		}

		// Bring it back from the trash first, then apply the target status.
		if ( 'trash' === $previous ) {
			if ( ! wp_untrash_post( $post->ID ) ) {
				return self::action_error( sprintf( 'Could not restore the %s from the trash.', $kind ) );
			}
			$post = get_post( $post->ID );
		}

		if ( $post && $post->post_status !== $status ) {
			$updated = wp_update_post(
				wp_slash( [
					'ID'          => $post->ID,
					'post_status' => $status,
				] ),
				true
			);

			if ( is_wp_error( $updated ) ) {
				return self::action_error( $updated->get_error_message() );
			}

			if ( ! $updated ) {
				return self::action_error( sprintf( 'Could not change the %s status.', $kind ) );
			}
		}

		$result                    = self::bookable_summary( get_post( $post->ID ), $kind );
		$result['previous_status'] = $previous;
		$result['changed']         = true;

		return self::action_success( $result );
	}

	private static function bookable_remove( string $kind, array $config ): array {
		$post = self::bookable_load( $kind, $config );

		if ( is_array( $post ) ) {
			return $post;
		}

		$mode = 'permanent' === sanitize_key( (string) ( $config['delete_mode'] ?? 'trash' ) ) ? 'permanent' : 'trash';

		/**
		 * Let a site veto removal (for example when the item still has
		 * active bookings). Return true to allow, or false / WP_Error to block.
		 *
		 * @param bool|\WP_Error $allowed
		 * @param \WP_Post       $post
		 * @param string         $kind    service | event | resource.
		 * @param string         $mode    trash | permanent.
		 */
		$allowed = apply_filters( 'zaplane/gembooking_can_remove_bookable', true, $post, $kind, $mode );

		if ( true !== $allowed ) {
			return self::action_error( is_wp_error( $allowed ) ? $allowed->get_error_message() : 'Removal was blocked by a filter.' );
		}

		if ( 'trash' === $mode && 'trash' === $post->post_status ) {
			$result            = self::bookable_summary( $post, $kind );
			$result['mode']    = $mode;
			$result['changed'] = false;

			return self::action_success( $result );
		}

		$done = 'permanent' === $mode ? wp_delete_post( $post->ID, true ) : wp_trash_post( $post->ID );

		if ( ! $done ) {
			return self::action_error( sprintf( 'GemBooking could not remove the %s.', $kind ) );
		}

		// wp_trash_post() deletes outright when EMPTY_TRASH_DAYS is 0.
		$now = get_post_status( $post->ID );

		return self::action_success( [
			'id'      => $post->ID,
			'kind'    => $kind,
			'title'   => $post->post_title,
			'mode'    => $mode,
			'status'  => false === $now ? 'deleted' : $now,
			'changed' => true,
		] );
	}

	/**
	 * Load the target post and make sure it really is that kind of
	 * GemBooking item. Returns a WP_Post, or an action_error() array.
	 *
	 * @return \WP_Post|array
	 */
	private static function bookable_load( string $kind, array $config ) {
		$id = absint( $config['bookable'] ?? $config['post_id'] ?? $config['id'] ?? 0 );

		if ( ! $id ) {
			return self::action_error( sprintf( 'A valid %s is required.', $kind ) );
		}

		$post = get_post( $id );

		if ( ! $post || self::bookable_type_for( $kind ) !== $post->post_type ) {
			return self::action_error( sprintf( 'The selected item is not a GemBooking %s.', $kind ) );
		}

		return $post;
	}
}
