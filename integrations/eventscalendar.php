<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Eventscalendar extends IntegrationBase {
	private static array $new_events = [];
	private static array $previous_relations = [];

	public function __construct() {
		add_action( 'wp_after_insert_post', [ self::class, 'remember_new_event' ], 1, 3 );
		add_action( 'wp_after_insert_post', [ self::class, 'forget_event_if_complete' ], 999, 2 );
		add_action( 'tribe_events_update_meta', [ self::class, 'forget_new_event' ], 999, 1 );
		add_filter( 'update_post_metadata', [ self::class, 'remember_previous_relation' ], 10, 5 );
	}

	public static function remember_new_event( $id, $post, $update ): void {
		if ( $post instanceof \WP_Post && 'tribe_events' === $post->post_type && ! $update ) {
			self::$new_events[ (int) $id ] = true;
		}
	}

	public static function forget_new_event( $id ): void {
		unset( self::$new_events[ (int) $id ] );
	}

	public static function forget_event_if_complete( $id, $post ): void {
		if ( $post instanceof \WP_Post && 'tribe_events' === $post->post_type &&
			get_post_meta( (int) $id, '_EventStartDate', true ) &&
			get_post_meta( (int) $id, '_EventEndDate', true ) ) {
			self::forget_new_event( $id );
		}
	}

	public static function remember_previous_relation( $check, $post_id, $meta_key, $meta_value, $prev_value ) {
		if ( in_array( $meta_key, [ '_EventVenueID', '_EventOrganizerID' ], true ) ) {
			$old = (int) get_post_meta( (int) $post_id, $meta_key, true );
			$new = self::to_id( $meta_value );
			unset( self::$previous_relations[ (int) $post_id ][ $meta_key ] );
			if ( $old > 0 && $old !== $new ) {
				self::$previous_relations[ (int) $post_id ][ $meta_key ] = $old;
			}
		}
		return $check;
	}

	public static function get_slug(): string { return 'eventscalendar'; }
	public static function get_name(): string { return 'The Events Calendar'; }
	public static function get_icon(): string { return 'events-calendar.svg'; }

	public static function get_triggers(): array {
		return [
			'event_created' => [ 'label' => 'Event Created', 'hook' => [ 'wp_after_insert_post', 'tribe_events_update_meta' ] ],
			'event_published' => [ 'label' => 'Event Published', 'hook' => 'transition_post_status' ],
			'event_updated' => [ 'label' => 'Event Updated', 'hook' => 'wp_after_insert_post' ],
			'event_deleted' => [ 'label' => 'Event Deleted', 'hook' => 'before_delete_post' ],
			'event_status_changed' => [ 'label' => 'Event Status Changed', 'hook' => 'transition_post_status' ],
			'venue_created' => [ 'label' => 'Venue Created', 'hook' => 'wp_after_insert_post' ],
			'venue_updated' => [ 'label' => 'Venue Updated', 'hook' => 'wp_after_insert_post' ],
			'venue_deleted' => [ 'label' => 'Venue Deleted', 'hook' => 'before_delete_post' ],
			'organizer_created' => [ 'label' => 'Organizer Created', 'hook' => 'wp_after_insert_post' ],
			'organizer_updated' => [ 'label' => 'Organizer Updated', 'hook' => 'wp_after_insert_post' ],
			'organizer_deleted' => [ 'label' => 'Organizer Deleted', 'hook' => 'before_delete_post' ],
			'event_linked_related_post' => [ 'label' => 'Event Linked to Related Post', 'hook' => [ 'added_post_meta', 'updated_post_meta' ] ],
			'event_unlinked_related_post' => [ 'label' => 'Event Unlinked from Related Post', 'hook' => [ 'deleted_post_meta', 'updated_post_meta' ] ],
		];
	}

	public static function get_actions(): array {
		$labels = [
			'create_event' => 'Create Event', 'update_event' => 'Update Event', 'delete_event' => 'Delete Event',
			'get_event_details' => 'Get Event Details', 'search_events' => 'Search Events',
			'create_venue' => 'Create Venue', 'update_venue' => 'Update Venue', 'delete_venue' => 'Delete Venue',
			'get_venue_details' => 'Get Venue Details', 'search_venues' => 'Search Venues',
			'create_organizer' => 'Create Organizer', 'update_organizer' => 'Update Organizer',
			'delete_organizer' => 'Delete Organizer', 'get_organizer_details' => 'Get Organizer Details',
			'search_organizers' => 'Search Organizers', 'add_venue_to_event' => 'Add Venue to Event',
			'remove_venue_from_event' => 'Remove Venue from Event', 'add_organizer_to_event' => 'Add Organizer to Event',
			'remove_organizer_from_event' => 'Remove Organizer from Event',
		];
		$out = [];
		foreach ( $labels as $key => $label ) { $out[ $key ] = [ 'label' => $label ]; }
		return $out;
	}

	private static function node_event( array $node ): string {
		return (string) ( $node['event'] ?? $node['data']['event'] ?? $node['flow_details']['event'] ?? '' );
	}

	private static function config( array $node ): array {
		foreach ( [ $node['data']['config'] ?? null, $node['config'] ?? null, $node['flow_details']['config'] ?? null ] as $config ) {
			if ( is_array( $config ) ) { return $config; }
		}
		return $node;
	}

	private static function filter_payload( array $node, array $payload ) {
		$config = self::config( $node );
		foreach ( [ 'event_id', 'venue_id', 'organizer_id', 'related_post_id', 'event_status', 'venue_status', 'organizer_status', 'new_status', 'related_type' ] as $key ) {
			if ( ! array_key_exists( $key, $config ) || ! is_scalar( $config[ $key ] ) ) { continue; }
			$value = trim( (string) $config[ $key ] );
			if ( '' === $value ) { continue; }
			if ( ! array_key_exists( $key, $payload ) ) { return false; }
			if ( false !== strpos( $key, '_id' ) ) {
				if ( self::to_id( $value ) !== self::to_id( $payload[ $key ] ) ) { return false; }
			} elseif ( $value !== (string) $payload[ $key ] ) {
				return false;
			}
		}
		return $payload;
	}

	private static function to_id( $value ): int {
		if ( is_array( $value ) ) { $value = $value['id'] ?? $value['value'] ?? $value['name'] ?? 0; }
		if ( is_object( $value ) ) { $value = $value->ID ?? $value->id ?? 0; }
		return is_numeric( $value ) ? max( 0, (int) $value ) : 0;
	}

	private static function require_tec(): void {
		if ( ! class_exists( 'Tribe__Events__Main' ) ) { throw new \RuntimeException( 'The Events Calendar plugin is not active.' ); }
	}

	private static function require_post_type( int $id, string $type, string $label ): \WP_Post {
		$post = $id ? get_post( $id ) : null;
		if ( ! $post instanceof \WP_Post || $post->post_type !== $type ) {
			throw new \InvalidArgumentException( 'A valid ' . esc_html( $label ) . ' ID is required.' );
		}
		return $post;
	}

	private static function related_ids( int $event_id, string $key ): array {
		return array_values( array_unique( array_filter( array_map( 'intval', (array) get_post_meta( $event_id, $key, false ) ) ) ) );
	}

	private static function event_payload( \WP_Post $post ): array {
		$id = (int) $post->ID;
		return [
			'event_id' => $id, 'event_title' => (string) $post->post_title,
			'event_status' => (string) $post->post_status, 'event_url' => (string) get_permalink( $id ),
			'event_start' => (string) get_post_meta( $id, '_EventStartDate', true ),
			'event_end' => (string) get_post_meta( $id, '_EventEndDate', true ),
			'venue_ids' => self::related_ids( $id, '_EventVenueID' ),
			'organizer_ids' => self::related_ids( $id, '_EventOrganizerID' ),
		];
	}

	private static function venue_payload( \WP_Post $post ): array {
		$id = (int) $post->ID;
		return [
			'venue_id' => $id, 'venue_name' => (string) $post->post_title,
			'venue_status' => (string) $post->post_status,
			'address' => (string) get_post_meta( $id, '_VenueAddress', true ),
			'city' => (string) get_post_meta( $id, '_VenueCity', true ),
			'country' => (string) get_post_meta( $id, '_VenueCountry', true ),
			'zip' => (string) get_post_meta( $id, '_VenueZip', true ),
			'phone' => (string) get_post_meta( $id, '_VenuePhone', true ),
		];
	}

	private static function organizer_payload( \WP_Post $post ): array {
		$id = (int) $post->ID;
		return [
			'organizer_id' => $id, 'organizer_name' => (string) $post->post_title,
			'organizer_status' => (string) $post->post_status,
			'email' => (string) get_post_meta( $id, '_OrganizerEmail', true ),
			'phone' => (string) get_post_meta( $id, '_OrganizerPhone', true ),
			'website' => (string) get_post_meta( $id, '_OrganizerWebsite', true ),
		];
	}

	private static function linked_payload( int $event_id, string $meta_key, int $related_id ): array {
		$type = '_EventVenueID' === $meta_key ? 'venue' : 'organizer';
		$post = get_post( $event_id );
		if ( ! $post instanceof \WP_Post || 'tribe_events' !== $post->post_type || $related_id <= 0 ) { return []; }
		return array_merge( self::event_payload( $post ), [
			'related_type' => $type, 'related_post_id' => $related_id,
			'related_post_title' => (string) get_the_title( $related_id ),
		] );
	}

	public static function resolve_trigger( array $node, array $args ) {
		self::require_tec();
		$event = self::node_event( $node );
		if ( ! isset( self::get_triggers()[ $event ] ) ) { return false; }

		// TEC saves event dates after wp_after_insert_post. The later metadata
		// hook has all dates; only fire there for events created by TEC's API.
		if ( 'event_created' === $event && ( $args[2] ?? null ) instanceof \WP_Post ) {
			$id = (int) ( $args[0] ?? 0 );
			$post = $args[2];
			if ( ! isset( self::$new_events[ $id ] ) || 'tribe_events' !== $post->post_type ) { return false; }
			return self::filter_payload( $node, self::event_payload( $post ) );
		}

		if ( in_array( $event, [ 'event_created', 'event_updated', 'venue_created', 'venue_updated', 'organizer_created', 'organizer_updated' ], true ) ) {
			$post = $args[1] ?? null; $update = (bool) ( $args[2] ?? false );
			if ( ! $post instanceof \WP_Post ) { return false; }
			$map = [
				'event_created' => [ 'tribe_events', false ], 'event_updated' => [ 'tribe_events', true ],
				'venue_created' => [ 'tribe_venue', false ], 'venue_updated' => [ 'tribe_venue', true ],
				'organizer_created' => [ 'tribe_organizer', false ], 'organizer_updated' => [ 'tribe_organizer', true ],
			];
			if ( $post->post_type !== $map[ $event ][0] || $update !== $map[ $event ][1] || in_array( $post->post_status, [ 'auto-draft', 'inherit' ], true ) ) { return false; }
			if ( 'event_created' === $event ) {
				$payload = self::event_payload( $post );
				if ( '' === $payload['event_start'] || '' === $payload['event_end'] ) { return false; }
				return self::filter_payload( $node, $payload );
			}
			return self::filter_payload( $node, 'tribe_events' === $post->post_type ? self::event_payload( $post ) :
				( 'tribe_venue' === $post->post_type ? self::venue_payload( $post ) : self::organizer_payload( $post ) ) );
		}

		if ( in_array( $event, [ 'event_deleted', 'venue_deleted', 'organizer_deleted' ], true ) ) {
			$post = $args[1] ?? null;
			if ( ! $post instanceof \WP_Post ) { return false; }
			$expected = [ 'event_deleted' => 'tribe_events', 'venue_deleted' => 'tribe_venue', 'organizer_deleted' => 'tribe_organizer' ][ $event ];
			if ( $post->post_type !== $expected ) { return false; }
			return self::filter_payload( $node, 'tribe_events' === $expected ? self::event_payload( $post ) :
				( 'tribe_venue' === $expected ? self::venue_payload( $post ) : self::organizer_payload( $post ) ) );
		}

		if ( in_array( $event, [ 'event_published', 'event_status_changed' ], true ) ) {
			$new = (string) ( $args[0] ?? '' ); $old = (string) ( $args[1] ?? '' ); $post = $args[2] ?? null;
			if ( ! $post instanceof \WP_Post || 'tribe_events' !== $post->post_type || $new === $old ) { return false; }
			if ( 'event_published' === $event && 'publish' !== $new ) { return false; }
			return self::filter_payload( $node, array_merge( self::event_payload( $post ), [ 'previous_status' => $old, 'new_status' => $new ] ) );
		}

		$meta_key = (string) ( $args[2] ?? '' );
		if ( ! in_array( $meta_key, [ '_EventVenueID', '_EventOrganizerID' ], true ) ) { return false; }
		$post_id = (int) ( $args[1] ?? 0 );
		$related_id = self::to_id( $args[3] ?? 0 );
		if ( 'event_unlinked_related_post' === $event && ! is_array( $args[0] ?? null ) ) {
			// Updating an existing venue also unlinks its previous venue.
			$previous = self::$previous_relations[ $post_id ][ $meta_key ] ?? 0;
			if ( ! $previous || $previous === $related_id ) { return false; }
			$related_id = (int) $previous;
		}
		$payload = self::linked_payload( $post_id, $meta_key, $related_id );
		return $payload ? self::filter_payload( $node, $payload ) : false;
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( ! isset( self::get_triggers()[ $trigger ] ) ) { return []; }
		if ( 0 === strpos( $trigger, 'venue_' ) ) {
			return [ self::text_field( 'venue_id', 'Only venue ID (optional)' ),
				self::text_field( 'venue_status', 'Only venue status (optional)' ) ];
		}
		if ( 0 === strpos( $trigger, 'organizer_' ) ) {
			return [ self::text_field( 'organizer_id', 'Only organizer ID (optional)' ),
				self::text_field( 'organizer_status', 'Only organizer status (optional)' ) ];
		}
		$fields = [ self::text_field( 'event_id', 'Only event ID (optional)' ),
			self::text_field( 'event_status', 'Only event status (optional)' ) ];
		if ( 'event_status_changed' === $trigger || 'event_published' === $trigger ) {
			$fields[] = self::text_field( 'new_status', 'Only new status (optional)' );
		}
		if ( in_array( $trigger, [ 'event_linked_related_post', 'event_unlinked_related_post' ], true ) ) {
			$fields[] = self::text_field( 'related_type', 'Only venue or organizer (optional)' );
			$fields[] = self::text_field( 'related_post_id', 'Only related post ID (optional)' );
		}
		return $fields;
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		if ( ! isset( self::get_triggers()[ $trigger ] ) ) { return []; }
		if ( 0 === strpos( $trigger, 'venue_' ) ) {
			return [ 'venue_id' => 12, 'venue_name' => 'Sample Venue', 'venue_status' => 'publish', 'address' => '1 Main St', 'city' => 'Dhaka', 'country' => 'Bangladesh', 'zip' => '1200', 'phone' => '0123456789' ];
		}
		if ( 0 === strpos( $trigger, 'organizer_' ) ) {
			return [ 'organizer_id' => 15, 'organizer_name' => 'Sample Organizer', 'organizer_status' => 'publish', 'email' => 'organizer@example.com', 'phone' => '0123456789', 'website' => 'https://example.com' ];
		}
		$sample = [ 'event_id' => 42, 'event_title' => 'Sample Event', 'event_status' => 'publish', 'event_url' => 'https://example.com/event/sample/', 'event_start' => '2026-10-03 10:00:00', 'event_end' => '2026-10-03 11:00:00', 'venue_ids' => [ 12 ], 'organizer_ids' => [ 15 ] ];
		if ( 'event_published' === $trigger || 'event_status_changed' === $trigger ) { $sample += [ 'previous_status' => 'draft', 'new_status' => 'publish' ]; }
		if ( in_array( $trigger, [ 'event_linked_related_post', 'event_unlinked_related_post' ], true ) ) { $sample += [ 'related_type' => 'venue', 'related_post_id' => 12, 'related_post_title' => 'Sample Venue' ]; }
		return $sample;
	}

	private static function text_field( string $key, string $label, bool $required = false ): array {
		return [ 'key' => $key, 'label' => $label, 'type' => 'text', 'required' => $required ];
	}

	public static function get_action_config_schema( string $action ): array {
		if ( ! isset( self::get_actions()[ $action ] ) ) { return []; }
		if ( 'create_event' === $action ) {
			return [ self::text_field( 'title', 'Event Title', true ), self::text_field( 'start', 'Start (YYYY-MM-DD HH:MM:SS)', true ), self::text_field( 'end', 'End (YYYY-MM-DD HH:MM:SS)', true ), self::text_field( 'content', 'Description' ), self::text_field( 'status', 'Status: draft or publish' ) ];
		}
		if ( 'update_event' === $action ) {
			return [ self::text_field( 'event_id', 'Event ID', true ), self::text_field( 'title', 'Event Title' ), self::text_field( 'start', 'Start (YYYY-MM-DD HH:MM:SS)' ), self::text_field( 'end', 'End (YYYY-MM-DD HH:MM:SS)' ), self::text_field( 'content', 'Description' ), self::text_field( 'status', 'Status: draft, publish, pending or private' ) ];
		}
		if ( in_array( $action, [ 'delete_event', 'get_event_details' ], true ) ) { return [ self::text_field( 'event_id', 'Event ID', true ) ]; }
		if ( 'search_events' === $action ) { return [ self::text_field( 'search', 'Search text' ) ]; }
		if ( 'create_venue' === $action ) { return [ self::text_field( 'name', 'Venue Name', true ), self::text_field( 'address', 'Address' ), self::text_field( 'city', 'City' ), self::text_field( 'country', 'Country' ), self::text_field( 'zip', 'Zip' ), self::text_field( 'phone', 'Phone' ) ]; }
		if ( 'update_venue' === $action ) { return array_merge( [ self::text_field( 'venue_id', 'Venue ID', true ) ], self::get_action_config_schema( 'create_venue' ) ); }
		if ( in_array( $action, [ 'delete_venue', 'get_venue_details' ], true ) ) { return [ self::text_field( 'venue_id', 'Venue ID', true ) ]; }
		if ( 'search_venues' === $action ) { return [ self::text_field( 'search', 'Search text' ) ]; }
		if ( 'create_organizer' === $action ) { return [ self::text_field( 'name', 'Organizer Name', true ), self::text_field( 'email', 'Email' ), self::text_field( 'phone', 'Phone' ), self::text_field( 'website', 'Website' ) ]; }
		if ( 'update_organizer' === $action ) { return array_merge( [ self::text_field( 'organizer_id', 'Organizer ID', true ) ], self::get_action_config_schema( 'create_organizer' ) ); }
		if ( in_array( $action, [ 'delete_organizer', 'get_organizer_details' ], true ) ) { return [ self::text_field( 'organizer_id', 'Organizer ID', true ) ]; }
		if ( 'search_organizers' === $action ) { return [ self::text_field( 'search', 'Search text' ) ]; }
		if ( in_array( $action, [ 'add_venue_to_event', 'remove_venue_from_event' ], true ) ) { return [ self::text_field( 'event_id', 'Event ID', true ), self::text_field( 'venue_id', 'Venue ID', true ) ]; }
		return [ self::text_field( 'event_id', 'Event ID', true ), self::text_field( 'organizer_id', 'Organizer ID', true ) ];
	}

	private static function parse_datetime( string $value ): array {
		$dt = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', trim( $value ) );
		if ( ! $dt || $dt->format( 'Y-m-d H:i:s' ) !== trim( $value ) ) { throw new \InvalidArgumentException( 'Date/time must use YYYY-MM-DD HH:MM:SS.' ); }
		$format = class_exists( '\\Tribe__Date_Utils' ) && function_exists( 'tribe_get_option' ) ? \Tribe__Date_Utils::datepicker_formats( tribe_get_option( 'datepickerFormat' ) ) : 'n/j/Y';
		return [ $dt->format( $format ), $dt->format( 'H:i:s' ), $dt ];
	}

	private static function build_event_args( array $config, bool $creating ): array {
		$args = [];
		if ( $creating || array_key_exists( 'title', $config ) ) {
			$title = trim( (string) ( $config['title'] ?? '' ) );
			if ( $creating && '' === $title ) { throw new \InvalidArgumentException( 'Event title is required.' ); }
			$args['post_title'] = $title;
		}
		if ( array_key_exists( 'content', $config ) ) { $args['post_content'] = wp_kses_post( (string) $config['content'] ); }
		if ( $creating ) {
			$status = (string) ( $config['status'] ?? 'draft' );
			if ( ! in_array( $status, [ 'draft', 'publish' ], true ) ) { throw new \InvalidArgumentException( 'Status must be draft or publish.' ); }
			$args['post_status'] = $status;
		} elseif ( array_key_exists( 'status', $config ) ) {
			$status = (string) $config['status'];
			if ( ! in_array( $status, [ 'draft', 'publish', 'pending', 'private' ], true ) ) { throw new \InvalidArgumentException( 'Invalid event status.' ); }
			$args['post_status'] = $status;
		}
		foreach ( [ 'start' => [ 'EventStartDate', 'EventStartTime' ], 'end' => [ 'EventEndDate', 'EventEndTime' ] ] as $key => $names ) {
			if ( array_key_exists( $key, $config ) && '' !== trim( (string) $config[ $key ] ) ) {
				[ $date, $time ] = self::parse_datetime( (string) $config[ $key ] );
				$args[ $names[0] ] = $date; $args[ $names[1] ] = $time;
			}
		}
		return $args;
	}

	private static function search_posts( string $type, string $search, callable $payload ): array {
		$search = sanitize_text_field( $search );
		$args   = [
			'post_type'      => $type,
			// Everything but trash and auto-drafts, as the event lists show.
			'post_status'    => [ 'publish', 'future', 'draft', 'pending', 'private' ],
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		$posts = get_posts( $args );
		return array_values( array_map( $payload, $posts ) );
	}

	public static function execute_node( array $node, array $input ): array {
		self::require_tec();
		$action = self::node_event( $node ); $config = self::config( $node );
		if ( ! isset( self::get_actions()[ $action ] ) ) { throw new \InvalidArgumentException( 'Unknown The Events Calendar action.' ); }

		if ( 'create_event' === $action || 'update_event' === $action ) {
			$id = self::to_id( $config['event_id'] ?? 0 );
			if ( 'update_event' === $action ) { self::require_post_type( $id, 'tribe_events', 'event' ); }
			$args = self::build_event_args( $config, 'create_event' === $action );
			if ( 'create_event' === $action && ( empty( $config['start'] ) || empty( $config['end'] ) ) ) { throw new \InvalidArgumentException( 'Start and end are required.' ); }
			if ( ! empty( $config['start'] ) && ! empty( $config['end'] ) ) {
				[ , , $start ] = self::parse_datetime( (string) $config['start'] ); [ , , $end ] = self::parse_datetime( (string) $config['end'] );
				if ( $end <= $start ) { throw new \InvalidArgumentException( 'Event end must be after start.' ); }
			}
			$fn = 'create_event' === $action ? 'tribe_create_event' : 'tribe_update_event';
			if ( ! function_exists( $fn ) ) { throw new \RuntimeException( 'The Events Calendar event API is unavailable.' ); }
			$result = 'create_event' === $action ? $fn( $args ) : $fn( $id, $args );
			if ( ! $result || is_wp_error( $result ) ) { throw new \RuntimeException( 'Event save failed.' ); }
			$post = self::require_post_type( (int) $result, 'tribe_events', 'event' );
			return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true ], self::event_payload( $post ) ) ];
		}

		if ( in_array( $action, [ 'delete_event', 'get_event_details' ], true ) ) {
			$id = self::to_id( $config['event_id'] ?? $input['event_id'] ?? 0 ); $post = self::require_post_type( $id, 'tribe_events', 'event' );
			if ( 'delete_event' === $action ) {
				$payload = self::event_payload( $post ); $deleted = wp_delete_post( $id, true );
				if ( ! $deleted ) { throw new \RuntimeException( 'Event delete failed.' ); }
				return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true, 'deleted' => true ], $payload ) ];
			}
			return [ 'port' => 'main', 'data' => self::event_payload( $post ) ];
		}
		if ( 'search_events' === $action ) { return [ 'port' => 'main', 'data' => [ 'items' => self::search_posts( 'tribe_events', (string) ( $config['search'] ?? '' ), [ self::class, 'event_payload' ] ) ] ]; }

		if ( in_array( $action, [ 'create_venue', 'update_venue' ], true ) ) {
			$id = self::to_id( $config['venue_id'] ?? 0 ); $updating = 'update_venue' === $action;
			if ( $updating ) { self::require_post_type( $id, 'tribe_venue', 'venue' ); }
			$name = trim( (string) ( $config['name'] ?? '' ) ); if ( ! $updating && '' === $name ) { throw new \InvalidArgumentException( 'Venue name is required.' ); }
			$args = [];
			foreach ( [ 'name' => 'Venue', 'address' => 'Address', 'city' => 'City', 'country' => 'Country', 'zip' => 'Zip', 'phone' => 'Phone' ] as $key => $tec ) {
				if ( array_key_exists( $key, $config ) && '' !== trim( (string) $config[ $key ] ) ) { $args[ $tec ] = trim( (string) $config[ $key ] ); }
			}
			if ( isset( $args['Venue'] ) ) { $args['post_title'] = $args['Venue']; } $args['post_status'] = 'publish';
			$fn = $updating ? 'tribe_update_venue' : 'tribe_create_venue';
			if ( ! function_exists( $fn ) ) { throw new \RuntimeException( 'The Events Calendar venue API is unavailable.' ); }
			$result = $updating ? $fn( $id, $args ) : $fn( $args );
			if ( ! $result || is_wp_error( $result ) ) { throw new \RuntimeException( 'Venue save failed.' ); }
			return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true ], self::venue_payload( self::require_post_type( (int) $result, 'tribe_venue', 'venue' ) ) ) ];
		}
		if ( in_array( $action, [ 'delete_venue', 'get_venue_details' ], true ) ) {
			$id = self::to_id( $config['venue_id'] ?? 0 ); $post = self::require_post_type( $id, 'tribe_venue', 'venue' );
			if ( 'delete_venue' === $action ) {
				$payload = self::venue_payload( $post );
				if ( ! wp_delete_post( $id, true ) ) { throw new \RuntimeException( 'Venue delete failed.' ); }
				return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true, 'deleted' => true ], $payload ) ];
			}
			return [ 'port' => 'main', 'data' => self::venue_payload( $post ) ];
		}
		if ( 'search_venues' === $action ) {
			return [ 'port' => 'main', 'data' => [ 'items' => self::search_posts( 'tribe_venue', (string) ( $config['search'] ?? '' ), [ self::class, 'venue_payload' ] ) ] ];
		}
		if ( in_array( $action, [ 'create_organizer', 'update_organizer' ], true ) ) {
			$id = self::to_id( $config['organizer_id'] ?? 0 ); $updating = 'update_organizer' === $action;
			if ( $updating ) { self::require_post_type( $id, 'tribe_organizer', 'organizer' ); }
			$name = trim( (string) ( $config['name'] ?? '' ) );
			if ( ! $updating && '' === $name ) { throw new \InvalidArgumentException( 'Organizer name is required.' ); }
			$args = [];
			foreach ( [ 'name' => 'Organizer', 'email' => 'Email', 'phone' => 'Phone', 'website' => 'Website' ] as $key => $tec ) {
				if ( array_key_exists( $key, $config ) && '' !== trim( (string) $config[ $key ] ) ) { $args[ $tec ] = trim( (string) $config[ $key ] ); }
			}
			if ( isset( $args['Organizer'] ) ) { $args['post_title'] = $args['Organizer']; } $args['post_status'] = 'publish';
			$fn = $updating ? 'tribe_update_organizer' : 'tribe_create_organizer';
			if ( ! function_exists( $fn ) ) { throw new \RuntimeException( 'The Events Calendar organizer API is unavailable.' ); }
			$result = $updating ? $fn( $id, $args ) : $fn( $args );
			if ( ! $result || is_wp_error( $result ) ) { throw new \RuntimeException( 'Organizer save failed.' ); }
			return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true ], self::organizer_payload( self::require_post_type( (int) $result, 'tribe_organizer', 'organizer' ) ) ) ];
		}
		if ( in_array( $action, [ 'delete_organizer', 'get_organizer_details' ], true ) ) {
			$id = self::to_id( $config['organizer_id'] ?? 0 ); $post = self::require_post_type( $id, 'tribe_organizer', 'organizer' );
			if ( 'delete_organizer' === $action ) {
				$payload = self::organizer_payload( $post );
				if ( ! wp_delete_post( $id, true ) ) { throw new \RuntimeException( 'Organizer delete failed.' ); }
				return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true, 'deleted' => true ], $payload ) ];
			}
			return [ 'port' => 'main', 'data' => self::organizer_payload( $post ) ];
		}
		if ( 'search_organizers' === $action ) {
			return [ 'port' => 'main', 'data' => [ 'items' => self::search_posts( 'tribe_organizer', (string) ( $config['search'] ?? '' ), [ self::class, 'organizer_payload' ] ) ] ];
		}

		$event_id = self::to_id( $config['event_id'] ?? 0 );
		$event_post = self::require_post_type( $event_id, 'tribe_events', 'event' );
		$is_venue = false !== strpos( $action, 'venue' );
		$related_id = self::to_id( $config[ $is_venue ? 'venue_id' : 'organizer_id' ] ?? 0 );
		self::require_post_type( $related_id, $is_venue ? 'tribe_venue' : 'tribe_organizer', $is_venue ? 'venue' : 'organizer' );
		$meta = $is_venue ? '_EventVenueID' : '_EventOrganizerID';
		$adding = 0 === strpos( $action, 'add_' );
		$changed = false;

		if ( $is_venue ) {
			$current = (int) get_post_meta( $event_id, $meta, true );
			if ( $adding && $current !== $related_id ) {
				update_post_meta( $event_id, $meta, $related_id ); $changed = true;
			} elseif ( ! $adding && $current === $related_id ) {
				delete_post_meta( $event_id, $meta, $related_id ); $changed = true;
			}
		} else {
			$current = self::related_ids( $event_id, $meta );
			if ( $adding && ! in_array( $related_id, $current, true ) ) {
				add_post_meta( $event_id, $meta, $related_id, false ); $changed = true;
			} elseif ( ! $adding && in_array( $related_id, $current, true ) ) {
				delete_post_meta( $event_id, $meta, $related_id ); $changed = true;
			}
		}
		return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true, 'changed' => $changed ], self::event_payload( $event_post ) ) ];
	}

	public static function get_action_sample_output( string $action ): array {
		if ( ! isset( self::get_actions()[ $action ] ) ) { return []; }
		if ( 0 === strpos( $action, 'search_' ) ) { return [ 'items' => [] ]; }
		if ( false !== strpos( $action, 'venue' ) && false === strpos( $action, 'event' ) ) {
			return array_merge( [ 'success' => true ], self::get_trigger_sample_output( 'venue_created' ) );
		}
		if ( false !== strpos( $action, 'organizer' ) && false === strpos( $action, 'event' ) ) {
			return array_merge( [ 'success' => true ], self::get_trigger_sample_output( 'organizer_created' ) );
		}
		return array_merge( [ 'success' => true ], self::get_trigger_sample_output( 'event_created' ) );
	}
}
