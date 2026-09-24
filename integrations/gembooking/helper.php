<?php

namespace Zaplane\Integrations\Gembooking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Helper
{

    private static function booking_id_field( bool $required = true ): array {
		return [
			[
				'key'      => 'booking_id',
				'label'    => 'Booking',
				'type'     => 'select',
				'required' => $required,
				'dynamic'  => [
					'integration' => 'gembooking',
					'query'       => 'gembooking_query',
					'select'      => [ 'value', 'label' ],
				],
			],
		];
	}

	/**
	 * @param bool $include_package Whether "Package" is a valid option — only
	 *                               true for flows that book a package, not
	 *                               for schemas like slot-availability or
	 *                               duplicate that only deal in service/event/
	 *                               resource posts.
	 */
	private static function booking_type_field( bool $include_package = true ): array {
		$options = [
			[ 'value' => 'service', 'label' => 'Service' ],
			[ 'value' => 'event', 'label' => 'Event' ],
			[ 'value' => 'resource', 'label' => 'Resource' ],
		];
		if ( $include_package ) {
			$options[] = [ 'value' => 'package', 'label' => 'Package' ];
		}

		return [
			[
				'key'      => 'booking_type',
				'label'    => 'Booking Type',
				'type'     => 'select',
				'required' => true,
				'options'  => $options,
			],
		];
	}

	private static function bookable_field(): array {
		return [
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
		];
	}

	private static function package_id_field(): array {
		return [
			[
				'key'      => 'package_id',
				'label'    => 'Package',
				'type'     => 'select',
				'required' => true,
				'dynamic'  => [
					'integration' => 'gembooking',
					'query'       => 'gembooking_package_query',
					'select'      => [ 'value', 'label' ],
				],
			],
		];
	}

	private static function purchase_id_field( bool $required = true ): array {
		return [
			[
				'key'      => 'purchase_id',
				'label'    => 'Package Purchase',
				'type'     => 'select',
				'required' => $required,
				'dynamic'  => [
					'integration' => 'gembooking',
					'query'       => 'gembooking_purchase_query',
					'select'      => [ 'value', 'label' ],
				],
			],
		];
	}

	/**
	 * @param bool $conditional Only show this field when booking_type is
	 *                          service/event/resource. Only pass true when
	 *                          the schema actually has a booking_type field
	 *                          with those values — otherwise the field would
	 *                          stay permanently hidden.
	 */
	private static function timezone_field( bool $conditional = true ): array {
		$field = [
			'key'      => 'timezone',
			'label'    => 'Timezone',
			'type'     => 'select',
			'required' => false,
			'dynamic'  => [
				'integration' => 'gembooking',
				'query'       => 'gembooking_timezone_query',
				'select'      => [ 'value', 'label' ],
			],
		];
		if ( $conditional ) {
			$field['show_if'] = [ 'booking_type' => [ 'service', 'event', 'resource' ] ];
		}
		return [ $field ];
	}

	private static function start_at_field( bool $conditional = true ): array {
		$field = [
			'key'      => 'start_at',
			'label'    => 'Start At',
			'type'     => 'datetime',
			'required' => true,
		];
		if ( $conditional ) {
			$field['show_if'] = [ 'booking_type' => [ 'service', 'event', 'resource' ] ];
		}
		return [ $field ];
	}

	private static function end_at_field( bool $conditional = true ): array {
		$field = [
			'key'      => 'end_at',
			'label'    => 'End At',
			'type'     => 'datetime',
			'required' => true,
		];
		if ( $conditional ) {
			$field['show_if'] = [ 'booking_type' => [ 'service', 'event', 'resource' ] ];
		}
		return [ $field ];
	}

	private static function first_name_field(): array {
		return [
			[
				'key' => 'first_name',
				'label' => 'First Name',
				'type' => 'text',
				'required' => true,
			],
		];
	}

	private static function last_name_field(): array {
		return [
			[
				'key' => 'last_name',
				'label' => 'Last Name',
				'type' => 'text',
				'required' => false,
			],
		];
	}

	private static function email_field(): array {
		return [
			[
				'key'      => 'email',
				'label'    => 'Email',
				'type'     => 'email',
				'required' => true,
			],
		];
	}

	private static function reason_field(): array {
		return [
			[
				'key'      => 'reason',
				'label'    => 'Reason',
				'type'     => 'textarea',
				'required' => false,
			],
		];
	}

	private static function message_field(): array {
		return [
			[
				'key' => 'message',
				'label' => 'Message',
				'type' => 'textarea',
				'required' => true,
			],
		];
	}

	private static function team_id_field( bool $required = true ): array {
		return [
			[
				'key' => 'team_id',
				'label' => 'Staff Member ID',
				'type' => 'number',
				'required' => $required,
			],
		];
	}

	private static function phone_field( bool $required = true ): array {
		return [
			[
				'key' => 'phone',
				'label' => 'Phone Number',
				'type' => 'text',
				'required' => $required,
			],
		];
	}

	private static function ticket_code_field(): array {
		return [
			[
				'key' => 'ticket_code',
				'label' => 'Ticket Code',
				'type' => 'text',
				'required' => true,
			],
		];
	}
}
