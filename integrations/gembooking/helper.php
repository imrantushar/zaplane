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

	private static function bookable_kinds(): array {
		return [
			'service'  => [ 'label' => 'Service',  'default' => 'gembk_service' ],
			'event'    => [ 'label' => 'Event',    'default' => 'gembk_event' ],
			'resource' => [ 'label' => 'Resource', 'default' => 'gembk_resource' ],
		];
	}

	private static function bookable_type_for( string $kind ): string {
		$kinds = self::bookable_kinds();

		return self::bookable_post_type( $kind, $kinds[ $kind ]['default'] ?? '' );
	}

	private static function bookable_meta_map( string $kind ): array {
		$map = [
			'price'               => 'price',
			'duration'            => 'duration',   // seconds
			'capacity'            => 'capacity',
			'host_id'             => 'host_id',
			'location'            => 'location',
			'gallery'             => 'gallery',
			'advance_reservation' => 'advance_reservation',
		];

		return array_merge( $map, (array) apply_filters( 'zaplane/gembooking_bookable_meta_map', $map, $kind ) );
	}

	private static function bookable_status( $value ): ?string {
		$status = sanitize_key( (string) $value );

		return in_array( $status, [ 'publish', 'draft', 'pending', 'private' ], true ) ? $status : null;
	}

	private static function bookable_summary( $post, string $kind ): array {
		if ( ! $post ) {
			return [];
		}

		return [
			'id'        => (int) $post->ID,
			'kind'      => $kind,
			'post_type' => $post->post_type,
			'title'     => $post->post_title,
			'status'    => $post->post_status,
			'permalink' => get_permalink( $post->ID ) ?: '',
			'edit_url'  => function_exists( 'get_edit_post_link' ) ? (string) get_edit_post_link( $post->ID, 'raw' ) : '',
		];
	}

	private static function bookable_resolve_image( string $value ): int {
		$value = trim( $value );

		if ( '' === $value ) {
			return 0;
		}

		if ( ctype_digit( $value ) ) {
			return 'attachment' === get_post_type( (int) $value ) ? (int) $value : 0;
		}

		$url = esc_url_raw( $value );

		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return 0;
		}

		$existing = attachment_url_to_postid( $url );

		if ( $existing ) {
			return (int) $existing;
		}

		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$id = media_sideload_image( $url, 0, null, 'id' );

		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	private static function bookable_target_field( string $kind ): array {
		$kinds = self::bookable_kinds();

		return [
			[
				'key'      => 'bookable',
				'label'    => $kinds[ $kind ]['label'],
				'type'     => 'select',
				'required' => true,
				'dynamic'  => [
					'integration' => 'gembooking',
					'query'       => 'gembooking_' . $kind . '_query',
					'select'      => [ 'value', 'label' ],
				],
			],
		];
	}

	private static function bookable_status_field( bool $with_default ): array {
		$field = [
			'key'      => 'bookable_status',
			'label'    => 'Status',
			'type'     => 'select',
			'required' => true,
			'options'  => [
				[ 'value' => 'publish', 'label' => 'Published' ],
				[ 'value' => 'draft', 'label' => 'Draft' ],
				[ 'value' => 'pending', 'label' => 'Pending Review' ],
				[ 'value' => 'private', 'label' => 'Private' ],
			],
		];

		if ( $with_default ) {
			$field['default'] = 'publish';
		}

		return [ $field ];
	}

	private static function bookable_delete_mode_field(): array {
		return [
			[
				'key'      => 'delete_mode',
				'label'    => 'Remove Mode',
				'type'     => 'select',
				'required' => false,
				'default'  => 'trash',
				'options'  => [
					[ 'value' => 'trash', 'label' => 'Move to Trash (can be restored)' ],
					[ 'value' => 'permanent', 'label' => 'Delete Permanently' ],
				],
			],
		];
	}

	private static function bookable_create_fields( string $kind ): array {
		$kinds  = self::bookable_kinds();
		$label  = $kinds[ $kind ]['label'];
		$fields = [
			[
				'key'      => 'title',
				'label'    => $label . ' Title',
				'type'     => 'text',
				'required' => true,
			],
			[
				'key'      => 'description',
				'label'    => 'Description',
				'type'     => 'textarea',
				'required' => false,
			],
			...self::bookable_status_field( true ),
		];

		if ( 'event' === $kind ) {
			$fields[] = [
				'key'      => 'host_id',
				'label'    => 'Host (Staff User ID)',
				'type'     => 'number',
				'required' => true,
			];
			$fields[] = [
				'key'      => 'location',
				'label'    => 'Location',
				'type'     => 'text',
				'required' => false,
			];
		}

		$fields[] = [
			'key'      => 'price',
			'label'    => 'Base Price (0 = free)',
			'type'     => 'number',
			'required' => false,
		];
		$fields[] = [
			'key'      => 'duration',
			'label'    => 'Duration (minutes)',
			'type'     => 'number',
			'required' => false,
		];
		$fields[] = [
			'key'      => 'capacity',
			'label'    => 'resource' === $kind ? 'Parallel-booking Capacity' : 'Capacity',
			'type'     => 'number',
			'required' => false,
		];

		if ( 'service' === $kind ) {
			$fields[] = [
				'key'      => 'advance_reservation',
				'label'    => 'Enable Advance Reservation',
				'type'     => 'select',
				'required' => false,
				'default'  => 'no',
				'options'  => [
					[ 'value' => 'no', 'label' => 'No' ],
					[ 'value' => 'yes', 'label' => 'Yes' ],
				],
			];
			$fields[] = [
				'key'      => 'images',
				'label'    => 'Images (attachment IDs or URLs, comma-separated — first is featured)',
				'type'     => 'textarea',
				'required' => false,
			];
		} else {
			$fields[] = [
				'key'      => 'image',
				'label'    => 'Featured Image (attachment ID or URL)',
				'type'     => 'text',
				'required' => false,
			];
		}

		$fields[] = [
			'key'      => 'custom_meta',
			'label'    => 'Custom Meta (JSON, optional)',
			'type'     => 'textarea',
			'required' => false,
		];

		return $fields;
	}

	private static function bookable_action_sample( string $action ): ?array {
		$map = [
			'create_service'         => [ 'service', 'publish' ],
			'service_status_change'  => [ 'service', 'draft' ],
			'service_trash'          => [ 'service', 'trash' ],
			'create_event'           => [ 'event', 'publish' ],
			'event_status_change'    => [ 'event', 'draft' ],
			'event_delete'           => [ 'event', 'trash' ],
			'create_resource'        => [ 'resource', 'publish' ],
			'resource_status_change' => [ 'resource', 'draft' ],
			'resource_trash'         => [ 'resource', 'trash' ],
		];

		if ( ! isset( $map[ $action ] ) ) {
			return null;
		}

		[ $kind, $status ] = $map[ $action ];

		$data = [
			'id'        => 45,
			'kind'      => $kind,
			'post_type' => 'gembk_' . $kind,
			'title'     => 'Sample ' . ucfirst( $kind ),
			'status'    => $status,
			'permalink' => 'https://example.com/' . $kind . '/sample/',
			'edit_url'  => 'https://example.com/wp-admin/post.php?post=45&action=edit',
		];

		if ( false !== strpos( $action, 'status_change' ) ) {
			$data['previous_status'] = 'publish';
			$data['changed']         = true;
		}

		if ( in_array( $action, [ 'service_trash', 'event_delete', 'resource_trash' ], true ) ) {
			$data = [
				'id'      => 45,
				'kind'    => $kind,
				'title'   => 'Sample ' . ucfirst( $kind ),
				'mode'    => 'trash',
				'status'  => 'trash',
				'changed' => true,
			];
		}

		return [
			'success' => true,
			'data'    => $data,
			'action'  => $action,
		];
	}
}
