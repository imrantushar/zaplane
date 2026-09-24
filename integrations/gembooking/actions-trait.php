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
}
