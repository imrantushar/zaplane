<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Traits\ActionResponseTrait;
use Zaplane\Integrations\Wpmapblock\ActionsTrait;
use Zaplane\Integrations\Wpmapblock\TriggerTrait;
use Zaplane\Integrations\Wpmapblock\QueryTrait;
use Zaplane\Integrations\Wpmapblock\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wpmapblock extends IntegrationBase {
	use ActionResponseTrait;
	use ActionsTrait;
	use TriggerTrait;
	use Helper;
	use QueryTrait;

	private const MAP_POST_TYPE = 'wpmb_map';
	private const CONFIG_META = '_wpmb_config';
	private const LAT_META = '_wpmb_lat';
	private const LNG_META = '_wpmb_lng';
	private const REST_NS = 'wpmb/v1';
	private static array $baseline = [];
	/** Post types WP Map Block plots, split into every type and store-locator types. */
	private static ?array $source_index = null;
	/** The coordinate pair a write is heading for, published by our own location actions. */
	private static array $location_writes = [];
	/** The pair as it stood before a save started — what tells a real edit from a no-op. */
	private static array $location_prev = [];
	/** The last pair reported per post, so the second half of a save is not reported twice. */
	private static array $location_seen = [];
	/** Marker count before a REST write, so an import can be told from an ordinary save. */
	private static array $import_baseline = [];

	public static function get_slug(): string {
		return 'wpmapblock';
	}

	public static function get_name(): string {
		return 'WP Map Block';
	}

	public static function get_icon(): string {
		return 'wpmapblock.svg';
	}

	public static function get_triggers(): array {
		// Marker events need the pre-write hook as well as the post-write one:
		// without the outgoing document there is no diff to report.
		$marker_hooks = [ 'update_post_meta', 'updated_post_meta' ];

		// A location is a post with both `_wpmb_lat` and `_wpmb_lng`. The
		// pre-write hook is the no-op guard; the post-write hook is the report.
		$location_save_hooks = [ 'update_post_meta', 'updated_post_meta' ];

		return [
			'map_created'            => [
				'label' => 'Map Created',
				'hook' => 'added_post_meta',
			],
			'map_updated'            => [
				'label' => 'Map Updated',
				'hook' => 'updated_post_meta',
			],
			'map_status_changed'     => [
				'label' => 'Map Activated or Deactivated',
				'hook' => 'transition_post_status',
			],
			'map_deleted'            => [
				'label' => 'Map Deleted',
				'hook' => 'before_delete_post',
			],

			'marker_added'           => [
				'label' => 'Marker Added',
				'hook' => $marker_hooks,
			],
			'marker_updated'         => [
				'label' => 'Marker Updated',
				'hook' => $marker_hooks,
			],
			'marker_moved'           => [
				'label' => 'Marker Moved',
				'hook' => $marker_hooks,
			],
			'marker_removed'         => [
				'label' => 'Marker Removed',
				'hook' => $marker_hooks,
			],
			'map_viewed'             => [
				'label' => 'Map Viewed on the Front End',
				'hook' => 'wpmb/render/config',
			],
			'map_searched'           => [
				'label' => 'Map Searched by a Visitor',
				'hook' => 'rest_request_before_callbacks',
			],
			// WP Map Block re-resolves dynamic sources through this one route and
			// reports after it, so a request that failed `can_edit()` is visible
			// as a WP_Error rather than a refresh that never happened.
			'map_refreshed'          => [
				'label' => 'Map Data Refreshed',
				'hook' => 'rest_request_after_callbacks',
			],
			// The count before the write has to be taken before the permission
			// check runs, and the result read after the callback.
			'map_data_imported'      => [
				'label' => 'Map Data Imported',
				'hook' => [ 'rest_request_before_callbacks', 'rest_request_after_callbacks' ],
			],
			'rest_map_updated'       => [
				'label' => 'Map Data Updated via REST',
				'hook' => 'rest_request_after_callbacks',
			],

			'location_created'       => [
				'label' => 'Location Created',
				'hook' => 'added_post_meta',
			],
			'location_updated'       => [
				'label' => 'Location Updated',
				'hook' => $location_save_hooks,
			],
			'location_deleted'       => [
				'label' => 'Location Deleted',
				'hook' => 'before_delete_post',
			],
			// Published means "visitors can now see it", so it covers a status
			// transition into publish and coordinates arriving on a post that is
			// already published.
			'location_published'     => [
				'label' => 'Location Published',
				'hook' => [ 'transition_post_status', 'added_post_meta' ],
			],
			'location_status_changed' => [
				'label' => 'Location Status Changed',
				'hook' => 'transition_post_status',
			],

			'store_created'          => [
				'label' => 'Store Created',
				'hook' => 'added_post_meta',
			],
			'store_updated'          => [
				'label' => 'Store Updated',
				'hook' => $location_save_hooks,
			],
			'store_deleted'          => [
				'label' => 'Store Deleted',
				'hook' => 'before_delete_post',
			],
		];
	}

	public static function get_actions(): array {
		$defs = [
			'create_map'        => [ 'Create Map', 'Create a map in the WP Map Block library, optionally from a starter preset.' ],
			'update_map'        => [ 'Update Map Settings', 'Change a map\'s title, provider, centre, zoom, size or feature toggles.' ],
			'duplicate_map'     => [ 'Duplicate Map', 'Copy a map\'s configuration and markers into a brand new map.' ],
			'set_map_status'    => [ 'Activate or Deactivate Map', 'Publish or unpublish a map so it does, or no longer does, show on the front end.' ],
			'delete_map'        => [ 'Delete Map', 'Permanently delete a map from the library.' ],
			'get_map'           => [ 'Get Map', 'Read a map\'s configuration and marker list.' ],
			'search_markers'    => [ 'Search Map Markers', 'Search a map\'s markers, including resolved dynamic sources, by keyword or category.' ],
			'export_map'        => [ 'Export Map as JSON', 'Return a map\'s configuration as pretty-printed JSON for backup or migration.' ],
			'add_marker'        => [ 'Add Marker to Map', 'Append a marker to a map\'s static marker list.' ],
			'update_marker'     => [ 'Update Marker', 'Edit an existing marker on a map.' ],
			'move_marker'       => [ 'Move Marker', 'Change only an existing marker\'s latitude and longitude.' ],
			'delete_marker'     => [ 'Delete Marker', 'Remove a marker from a map.' ],
			'import_markers'    => [ 'Import Markers', 'Append or replace markers from a JSON array or a CSV export.' ],
			'add_data_source'   => [ 'Add Dynamic Data Source', 'Attach a post type, listing, GeoJSON or REST source to a map.' ],
			'set_post_location' => [ 'Set Post Map Location', 'Write the `_wpmb_lat` / `_wpmb_lng` coordinates WP Map Block lists posts from.' ],
			'geocode_address'   => [ 'Get Coordinates for an Address', 'Geocode a street address to latitude/longitude using OpenStreetMap Nominatim.' ],

			'create_location'       => [ 'Create Location', 'Create a post and give it the coordinates WP Map Block plots it from.' ],
			'update_location'       => [ 'Update Location', 'Change a location\'s title, content, status or coordinates.' ],
			'delete_location'       => [ 'Delete Location', 'Permanently delete a location\'s post.' ],
			'publish_location'      => [ 'Publish Location', 'Move a location into the `publish` status so it appears on maps.' ],
			'unpublish_location'    => [ 'Unpublish Location', 'Move a location back to `draft`, taking it off front-end maps.' ],
			'set_location_status'   => [ 'Change Location Status', 'Set a location to any WordPress post status WordPress will accept.' ],
			'get_location'          => [ 'Get Location', 'Read one location\'s status, permalink and coordinates.' ],
			'search_locations'      => [ 'Search Locations', 'Keyword search across locations, matching WP Map Block\'s own `?q=` search.' ],
			'filter_locations'      => [ 'Filter Locations', 'Filter locations by post type, status, taxonomy or distance from a centre.' ],
			'create_store'          => [ 'Create Store', 'Create a location in a post type that a store-locator map actually lists.' ],
			'update_store'          => [ 'Update Store', 'Change a store\'s title, status or coordinates.' ],
			'delete_store'          => [ 'Delete Store', 'Permanently delete a store\'s post.' ],
			'refresh_map_data'      => [ 'Refresh Map Data', 'Re-resolve a map\'s dynamic sources and report what it now renders.' ],
			'sync_dynamic_data'     => [ 'Sync Dynamic Map Data', 'Add a post type source to a map, and optionally drop sources whose data has gone away.' ],
			'update_map_settings'   => [ 'Update Map Settings', 'Change a map\'s nested settings: clustering, heatmap, store locator, directory, directions and popups.' ],
			'update_marker_settings' => [ 'Update Marker Settings', 'Change the map-wide marker animation and draggable defaults every marker inherits.' ],
			'set_map_center'        => [ 'Set Map Center', 'Set the map\'s centre coordinates and, optionally, its zoom level.' ],
			'set_map_provider'      => [ 'Set Map Provider', 'Switch the map\'s provider, optionally storing an API key for it.' ],
			'get_rest_map_data'     => [ 'Get REST Map Data', 'Read the map document WP Map Block\'s own REST endpoint returns, with the provider key redacted.' ],
		];

		$out = [];
		foreach ( $defs as $key => $def ) {
			$out[ $key ] = [
				'label' => $def[0],
				'description' => $def[1]
			];
		}
		return $out;
	}

	private static function node_event( array $node ): string {
		return (string) ( $node['event'] ?? $node['data']['event'] ?? $node['flow_details']['event'] ?? '' );
	}

	private static function config( array $node ): array {
		foreach ( [ $node['data']['config'] ?? null, $node['config'] ?? null, $node['flow_details']['config'] ?? null ] as $config ) {
			if ( is_array( $config ) ) {
				return $config;
			}
		}
		return [];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( ! isset( self::get_triggers()[ $trigger ] ) ) {
			return [];
		}

		// A brand new map has no id to match on yet.
		if ( 'map_created' === $trigger ) {
			return [];
		}

		// Location and store triggers watch posts, not maps, so their only
		// filter is which post type to accept.
		if ( 0 === strpos( $trigger, 'location_' ) || 0 === strpos( $trigger, 'store_' ) ) {
			return [
				self::post_type_select( 'post_type', 'Only post type', false, 'Leave empty to match every post type.' ),
			];
		}

		$help = '';
		if ( 'map_refreshed' === $trigger ) {
			$help = 'The refresh endpoint carries no map id, so this matches maps whose stored data sources are exactly the ones being previewed. Leave empty to match every refresh.';
		} elseif ( 'map_data_imported' === $trigger ) {
			$help = 'Fires when a REST write changes a map\'s marker list — the path the editor\'s Import button takes. Leave empty to match every map.';
		}

		$fields = [ self::map_select( 'map_id', false, 'Map', $help ) ];

		if ( 'map_searched' === $trigger ) {
			$fields[] = self::field( 'query', 'Only searches containing', 'text', false, [
				'help' => 'Case-insensitive substring of the visitor\'s search term. Leave empty to match every search.',
			] );
		}

		return $fields;
	}

	public static function resolve_trigger( array $node, array $args ) {
		$event = self::node_event( $node );
		if ( ! isset( self::get_triggers()[ $event ] ) ) {
			return false;
		}

		$filter = self::config( $node );

		switch ( $event ) {
			case 'map_created':
			case 'map_updated':
				return self::trigger_document_written( $filter, $args );

			case 'map_status_changed':
				return self::trigger_status_changed( $filter, $args );

			case 'map_deleted':
				return self::trigger_map_deleted( $filter, $args );

			case 'marker_added':
			case 'marker_updated':
			case 'marker_moved':
			case 'marker_removed':
				return self::trigger_marker_change( $event, $filter, $args );

			case 'map_viewed':
				return self::trigger_map_viewed( $filter, $args );

			case 'map_searched':
				return self::trigger_map_searched( $filter, $args );

			case 'map_refreshed':
				return self::trigger_map_refreshed( $filter, $args );

			case 'map_data_imported':
				return self::trigger_map_data_imported( $filter, $args );

			case 'rest_map_updated':
				return self::trigger_rest_map_updated( $filter, $args );

			case 'location_created':
			case 'location_updated':
			case 'location_deleted':
			case 'location_published':
			case 'location_status_changed':
				return self::trigger_location_event( $event, $filter, $args );

			case 'store_created':
			case 'store_updated':
			case 'store_deleted':
				return self::trigger_location_event( 'location_' . substr( $event, 6 ), $filter, $args, 'store' );
		}//end switch

		return false;
	}

	private static function map_payload( $post, bool $with_config = false ): array {
		$map_id   = (int) $post->ID;
		$document = self::read_config( $map_id );
		$view     = isset( $document['view'] ) && is_array( $document['view'] ) ? $document['view'] : [];
		$center   = isset( $view['center'] ) && is_array( $view['center'] ) ? $view['center'] : [];

		$payload = [
			'map_id'          => $map_id,
			'map_title'       => (string) $post->post_title,
			'status'          => (string) $post->post_status,
			'active'          => 'publish' === $post->post_status,
			'provider'        => (string) ( $document['provider'] ?? 'openstreetmap' ),
			'center_lat'      => isset( $center['lat'] ) ? (float) $center['lat'] : 0.0,
			'center_lng'      => isset( $center['lng'] ) ? (float) $center['lng'] : 0.0,
			'zoom'            => isset( $view['zoom'] ) ? (int) $view['zoom'] : 10,
			'markers_count'   => count( self::markers_of( $document ) ),
			'categories_count' => count( self::list_of( $document, 'categories' ) ),
			'shortcode'       => '[wp_map id="' . $map_id . '"]',
			'has_api_key'     => '' !== (string) ( $document['providerOptions']['apiKey'] ?? '' ),
			'modified'        => (string) ( $post->post_modified_gmt ?? '' ),
		];

		if ( $with_config ) {
			$payload['config'] = self::redacted_config( $document );
		}

		return $payload;
	}

	private static function redacted_config( array $document ): array {
		if ( isset( $document['providerOptions'] ) && is_array( $document['providerOptions'] ) ) {
			$document['providerOptions']['apiKey'] = '';
		}
		return $document;
	}

	private static function meta_target( array $args ): int {
		$object_id = $args[1] ?? 0;
		if ( is_array( $object_id ) || is_object( $object_id ) || ! is_numeric( $object_id ) ) {
			return 0;
		}
		return max( 0, (int) $object_id );
	}

	private static function meta_key( array $args ): string {
		return is_string( $args[2] ?? null ) ? $args[2] : '';
	}

	private static function map_matches( array $filter, int $map_id ): bool {
		$wanted = $filter['map_id'] ?? '';
		if ( is_array( $wanted ) ) {
			$wanted = reset( $wanted );
		}
		if ( ! is_scalar( $wanted ) || '' === trim( (string) $wanted ) ) {
			return true;
		}
		if ( ! is_numeric( $wanted ) ) {
			return false;
		}
		return (int) $wanted === $map_id;
	}

	private static function find_map( $map_id ) {
		$map_id = (int) $map_id;
		if ( $map_id <= 0 ) {
			return null;
		}
		$post = get_post( $map_id );
		if ( ! $post instanceof \WP_Post || self::MAP_POST_TYPE !== $post->post_type ) {
			return null;
		}
		return $post;
	}

	private static function require_map( $map_id ) {
		$post = self::find_map( $map_id );
		if ( ! $post ) {
			throw new \InvalidArgumentException( 'A valid WP Map Block map ID is required.' );
		}
		return $post;
	}

	private static function read_config( int $map_id ): array {
		$raw     = get_post_meta( $map_id, self::CONFIG_META, true );
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		return is_array( $decoded ) ? $decoded : [];
	}

	private static function require_plugin(): void {
		if ( ! class_exists( '\WPMapBlock\Config' ) ) {
			throw new \RuntimeException( 'WP Map Block is not active.' );
		}
	}

	private static function save_config( int $map_id, array $document ): array {
		self::require_plugin();
		$clean = \WPMapBlock\Config::sanitize( $document );
		self::write_config_json( $map_id, (string) wp_json_encode( $clean ) );
		return $clean;
	}

	private static function write_config_json( int $map_id, string $json ): void {
		// add_metadata()/update_metadata() run wp_unslash() on whatever they are
		// handed, so a JSON document has to arrive slashed or every escape
		// inside marker content is eaten on the way to the database.
		$slashed = function_exists( 'wp_slash' ) ? wp_slash( $json ) : $json;
		update_post_meta( $map_id, self::CONFIG_META, $slashed );

		// A map's config is where the location post types and the store
		// locator live, so the cached index is only safe to reuse until now.
		self::flush_source_index();
	}

	/** @return array<int,array<string,mixed>> */
	private static function markers_of( array $document ): array {
		return self::list_of( $document, 'markers' );
	}

	private static function list_of( array $document, string $key ): array {
		$value = $document[ $key ] ?? [];
		if ( ! is_array( $value ) ) {
			return [];
		}
		return array_values( array_filter( $value, 'is_array' ) );
	}

	private static function diff_markers( array $before, array $after ): array {
		$old = self::index_markers( $before );
		$new = self::index_markers( $after );

		$diff = [
			'marker_added'   => [],
			'marker_updated' => [],
			'marker_moved'   => [],
			'marker_removed' => [],
		];

		foreach ( $new as $id => $marker ) {
			if ( ! isset( $old[ $id ] ) ) {
				$diff['marker_added'][] = $marker;
				continue;
			}

			$previous = $old[ $id ];
			if ( self::position_of( $previous ) !== self::position_of( $marker ) ) {
				$diff['marker_moved'][] = array_merge( $marker, [
					'previous_lat' => (float) ( $previous['lat'] ?? 0 ),
					'previous_lng' => (float) ( $previous['lng'] ?? 0 ),
				] );
				continue;
			}

			if ( self::marker_signature( $previous ) !== self::marker_signature( $marker ) ) {
				$diff['marker_updated'][] = array_merge( $marker, [ 'previous' => $previous ] );
			}
		}

		foreach ( $old as $id => $marker ) {
			if ( ! isset( $new[ $id ] ) ) {
				$diff['marker_removed'][] = $marker;
			}
		}

		return $diff;
	}

	private static function index_markers( array $markers ): array {
		$out = [];
		foreach ( $markers as $marker ) {
			$id = isset( $marker['id'] ) ? (string) $marker['id'] : '';
			if ( '' === $id ) {
				continue;
			}
			$out[ $id ] = $marker;
		}
		return $out;
	}

	private static function position_of( array $marker ): array {
		return [
			round( (float) ( $marker['lat'] ?? 0 ), 6 ),
			round( (float) ( $marker['lng'] ?? 0 ), 6 ),
		];
	}

	private static function marker_signature( array $marker ): string {
		$icon = isset( $marker['icon'] ) && is_array( $marker['icon'] ) ? $marker['icon'] : [];

		return (string) wp_json_encode( [
			'title'    => (string) ( $marker['title'] ?? '' ),
			'content'  => (string) ( $marker['content'] ?? '' ),
			'image'    => (string) ( $marker['image'] ?? '' ),
			'link'     => (string) ( $marker['link'] ?? '' ),
			'category' => (string) ( $marker['category'] ?? '' ),
			'icon'     => [
				'type'   => (string) ( $icon['type'] ?? '' ),
				'preset' => (string) ( $icon['preset'] ?? '' ),
				'url'    => (string) ( $icon['url'] ?? '' ),
				'color'  => (string) ( $icon['color'] ?? '' ),
			],
		] );
	}

	public static function get_dynamic_queries(): array {
		return [
			'maps'            => [ self::class, 'query_maps' ],
			'markers'         => [ self::class, 'query_markers' ],
			'map_categories'  => [ self::class, 'query_map_categories' ],
			'geo_post_types'  => [ self::class, 'query_geo_post_types' ],
			'geo_posts'       => [ self::class, 'query_geo_posts' ],
			'listing_sources' => [ self::class, 'query_listing_sources' ],
		];
	}

	private static function field( string $key, string $label, string $type, bool $required, array $extra = [] ): array {
		return array_merge(
			[
				'key' => $key,
				'label' => $label,
				'type' => $type,
				'required' => $required
			],
			$extra
		);
	}

	private static function map_select( string $key, bool $required, string $label = 'Map', string $help = '' ): array {
		$extra = [
			'dynamic' => [
				'integration' => 'wpmapblock',
				'query'       => 'maps',
				'select'      => [ 'value', 'label' ],
			],
		];

		if ( '' !== $help ) {
			$extra['help'] = $help;
		} elseif ( ! $required ) {
			$extra['help'] = 'Leave empty to match any map.';
		}

		return self::field( $key, $label, 'select', $required, $extra );
	}

	private static function post_type_select( string $key, string $label = 'Post Type', bool $required = true, string $help = '' ): array {
		return self::with_help( self::field( $key, $label, 'select', $required, [
			'dynamic' => [
				'integration' => 'wpmapblock',
				'query'       => 'geo_post_types',
				'select'      => [ 'value', 'label' ],
			],
		] ), $help );
	}

	private static function post_select( string $key, string $label = 'Post', string $help = '' ): array {
		return self::with_help( self::field( $key, $label, 'select', true, [
			'dynamic' => [
				'integration' => 'wpmapblock',
				'query'       => 'geo_posts',
				'select'      => [ 'value', 'label' ],
			],
		] ), $help );
	}

	private static function marker_select( string $key, string $label ): array {
		return self::field( $key, $label, 'select', true, [
			'dynamic' => [
				'integration' => 'wpmapblock',
				'query'       => 'markers',
				'select'      => [ 'value', 'label' ],
				'depends_on'  => [ 'map_id' ],
			],
		] );
	}

	private static function with_help( array $field, string $help ): array {
		$help = trim( $help );
		if ( '' !== $help ) {
			$field['help'] = $help;
		}
		return $field;
	}

	private static function toggle( string $key, string $label, string $help = '' ): array {
		return self::with_help( self::field( $key, $label, 'select', false, [
			'options' => [
				[
					'value' => 'yes',
					'label' => 'Yes'
				],
				[
					'value' => 'no',
					'label' => 'No'
				],
			],
		] ), $help );
	}

	private static function select_field( string $key, string $label, array $options, bool $required = false, string $help = '' ): array {
		return self::with_help( self::field( $key, $label, 'select', $required, [ 'options' => $options ] ), $help );
	}

	private static function text_field( string $key, string $label, bool $required = false, string $help = '' ): array {
		return self::with_help( self::field( $key, $label, 'text', $required ), $help );
	}

	private static function number_field( string $key, string $label, bool $required = false, string $help = '' ): array {
		return self::with_help( self::field( $key, $label, 'number', $required ), $help );
	}

	public static function get_action_config_schema( string $action ): array {
		switch ( $action ) {
			case 'create_map':
				return [
					self::text_field( 'title', 'Map Title', true ),
					self::select_field( 'preset', 'Start From', [
						[
							'value' => '',
							'label' => 'Blank map'
						],
						[
							'value' => 'store_locator',
							'label' => 'Store Locator'
						],
						[
							'value' => 'real_estate',
							'label' => 'Real Estate'
						],
						[
							'value' => 'food_guide',
							'label' => 'Food Guide'
						],
						[
							'value' => 'travel_guide',
							'label' => 'Travel Guide'
						],
						[
							'value' => 'heatmap',
							'label' => 'Heatmap'
						],
						[
							'value' => 'global_vector',
							'label' => 'Global Vector'
						],
					] ),
					self::select_field( 'provider', 'Map Provider', self::provider_options() ),
					self::text_field( 'center_lat', 'Centre Latitude', false, 'Decimal degrees. Leave empty to keep the preset\'s centre.' ),
					self::text_field( 'center_lng', 'Centre Longitude', false, 'Decimal degrees. Leave empty to keep the preset\'s centre.' ),
					self::field( 'zoom', 'Zoom Level', 'number', false ),
				];

			case 'update_map':
				return [
					self::map_select( 'map_id', true ),
					self::text_field( 'title', 'New Title', false ),
					self::select_field( 'provider', 'Map Provider', self::provider_options() ),
					self::text_field( 'center_lat', 'Centre Latitude', false ),
					self::text_field( 'center_lng', 'Centre Longitude', false ),
					self::field( 'zoom', 'Zoom Level', 'number', false ),
					self::text_field( 'width', 'Map Width', false, 'Any CSS width, e.g. 100% or 640px.' ),
					self::field( 'height', 'Map Height', 'number', false ),
					self::select_field( 'map_type', 'Map Type', [
						[
							'value' => 'roadmap',
							'label' => 'Roadmap'
						],
						[
							'value' => 'satellite',
							'label' => 'Satellite'
						],
						[
							'value' => 'hybrid',
							'label' => 'Hybrid'
						],
						[
							'value' => 'terrain',
							'label' => 'Terrain'
						],
					] ),
					self::select_field( 'theme', 'Theme', [
						[
							'value' => 'light',
							'label' => 'Light'
						],
						[
							'value' => 'dark',
							'label' => 'Dark'
						],
						[
							'value' => 'auto',
							'label' => 'Auto (follow the visitor)'
						],
					] ),
					self::toggle( 'clustering', 'Marker Clustering' ),
					self::toggle( 'store_locator', 'Store Locator', 'Only rendered where the store locator capability is unlocked.' ),
					self::toggle( 'directory', 'Side Directory Panel' ),
					self::toggle( 'heatmap', 'Heatmap', 'Only rendered where the heatmap capability is unlocked.' ),
					self::toggle( 'directions', 'Directions Button', 'Only rendered where the directions capability is unlocked.' ),
					self::with_help( self::field( 'api_key', 'Provider API Key', 'password', false ), 'Leave empty to keep the key already saved on the map.' ),
				];

			case 'duplicate_map':
				return [ self::map_select( 'map_id', true ) ];

			case 'set_map_status':
				return [
					self::map_select( 'map_id', true ),
					self::select_field( 'status', 'Status', [
						[
							'value' => 'active',
							'label' => 'Active (publish)'
						],
						[
							'value' => 'inactive',
							'label' => 'Inactive (draft)'
						],
					], true ),
				];

			case 'delete_map':
			case 'get_map':
			case 'export_map':
				return [ self::map_select( 'map_id', true ) ];

			case 'search_markers':
				return [
					self::map_select( 'map_id', true ),
					self::text_field( 'query', 'Contains', false, 'Case-insensitive match against marker title and popup content.' ),
					self::field( 'category', 'Only Category', 'select', false, [
						'dynamic' => [
							'integration' => 'wpmapblock',
							'query'       => 'map_categories',
							'select'      => [ 'value', 'label' ],
							'depends_on'  => [ 'map_id' ],
						],
					] ),
					self::number_field( 'limit', 'Maximum Results', false, 'Defaults to 50.' ),
				];

			case 'add_marker':
				return array_merge(
					[ self::map_select( 'map_id', true ) ],
					self::marker_body_schema()
				);

			case 'move_marker':
				return [
					self::map_select( 'map_id', true ),
					self::marker_select( 'marker_id', 'Marker' ),
					self::text_field( 'lat', 'Latitude', true, 'Decimal degrees, e.g. 23.780573.' ),
					self::text_field( 'lng', 'Longitude', true, 'Decimal degrees, e.g. 90.407067.' ),
				];

			case 'update_marker':
				return array_merge(
					[ self::map_select( 'map_id', true ), self::marker_select( 'marker_id', 'Marker' ) ],
					self::marker_body_schema()
				);

			case 'delete_marker':
				return [
					self::map_select( 'map_id', true ),
					self::marker_select( 'marker_id', 'Marker' ),
				];

			case 'import_markers':
				return [
					self::map_select( 'map_id', true ),
					self::select_field( 'mode', 'Import Mode', [
						[
							'value' => 'append',
							'label' => 'Append to existing markers'
						],
						[
							'value' => 'replace',
							'label' => 'Replace all markers'
						],
					], false, 'Defaults to append.' ),
					self::field( 'data', 'Markers (JSON array or CSV)', 'textarea', true, [
						'help' => 'A JSON array of markers, or CSV with a header row: lat,lng,title,content,link,image,category.',
					] ),
				];

			case 'add_data_source':
				return [
					self::map_select( 'map_id', true ),
					self::select_field( 'type', 'Source Type', [
						[
							'value' => 'cpt',
							'label' => 'Posts / custom post type'
						],
						[
							'value' => 'listing',
							'label' => 'Registered listing source'
						],
						[
							'value' => 'geojson',
							'label' => 'GeoJSON file'
						],
						[
							'value' => 'rest',
							'label' => 'REST endpoint'
						],
					], true ),
					self::text_field( 'label', 'Label' ),
					self::field( 'post_type', 'Post Type', 'select', false, [
						'dynamic' => [
							'integration' => 'wpmapblock',
							'query'       => 'geo_post_types',
							'select'      => [ 'value', 'label' ],
						],
					] ),
					self::text_field( 'lat_meta', 'Latitude Meta Key', false, 'Defaults to _wpmb_lat.' ),
					self::text_field( 'lng_meta', 'Longitude Meta Key', false, 'Defaults to _wpmb_lng.' ),
					self::field( 'source', 'Listing Source', 'select', false, [
						'dynamic' => [
							'integration' => 'wpmapblock',
							'query'       => 'listing_sources',
							'select'      => [ 'value', 'label' ],
						],
					] ),
					self::text_field( 'url', 'URL', false, 'GeoJSON file or REST endpoint. Required for those source types.' ),
					self::number_field( 'limit', 'Maximum Markers', false, 'Defaults to 100.' ),
				];

			case 'set_post_location':
				return [
					self::field( 'post_id', 'Post', 'select', true, [
						'dynamic' => [
							'integration' => 'wpmapblock',
							'query'       => 'geo_posts',
							'select'      => [ 'value', 'label' ],
						],
					] ),
					self::text_field( 'lat', 'Latitude', true, 'Decimal degrees.' ),
					self::text_field( 'lng', 'Longitude', true, 'Decimal degrees.' ),
				];

			case 'geocode_address':
				return [
					self::field( 'address', 'Address', 'text', true, [
						'help' => 'Geocoded with OpenStreetMap Nominatim, the same service WP Map Block uses in the editor.',
					] ),
					self::number_field( 'limit', 'Maximum Results', false, 'Defaults to 1.' ),
				];

			case 'create_location':
			case 'create_store':
				return [
					self::post_type_select( 'post_type', 'Post Type', true, 'Store actions only accept a type a store-locator map actually lists.' ),
					self::text_field( 'title', 'Title', true ),
					self::field( 'content', 'Content', 'textarea', false, [
						'help' => 'HTML is allowed.',
					] ),
					self::select_field( 'status', 'Status', [
						[
							'value' => 'publish',
							'label' => 'Published'
						],
						[
							'value' => 'draft',
							'label' => 'Draft'
						],
						[
							'value' => 'pending',
							'label' => 'Pending review'
						],
						[
							'value' => 'private',
							'label' => 'Private'
						],
					], false, 'Defaults to published.' ),
					self::text_field( 'lat', 'Latitude', true, 'Decimal degrees, e.g. 23.780573.' ),
					self::text_field( 'lng', 'Longitude', true, 'Decimal degrees, e.g. 90.407067.' ),
				];

			case 'update_location':
			case 'update_store':
				return [
					self::post_select( 'post_id', 'Location' ),
					self::text_field( 'title', 'New Title', false ),
					self::field( 'content', 'New Content', 'textarea', false ),
					self::select_field( 'status', 'Status', [
						[
							'value' => 'publish',
							'label' => 'Published'
						],
						[
							'value' => 'draft',
							'label' => 'Draft'
						],
						[
							'value' => 'pending',
							'label' => 'Pending review'
						],
						[
							'value' => 'private',
							'label' => 'Private'
						],
					] ),
					self::text_field( 'lat', 'Latitude', false, 'Send either half on its own to move only that coordinate.' ),
					self::text_field( 'lng', 'Longitude', false, 'Send either half on its own to move only that coordinate.' ),
				];

			case 'delete_location':
			case 'delete_store':
			case 'get_location':
			case 'publish_location':
			case 'unpublish_location':
				return [ self::post_select( 'post_id', 'Location' ) ];

			case 'set_location_status':
				return [
					self::post_select( 'post_id', 'Location' ),
					self::select_field( 'status', 'Status', [
						[
							'value' => 'publish',
							'label' => 'Published'
						],
						[
							'value' => 'draft',
							'label' => 'Draft'
						],
						[
							'value' => 'pending',
							'label' => 'Pending review'
						],
						[
							'value' => 'private',
							'label' => 'Private'
						],
						[
							'value' => 'future',
							'label' => 'Scheduled'
						],
					], true ),
				];

			case 'search_locations':
				return [
					self::post_type_select( 'post_type', 'Only this post type', false, 'Leave empty to search every post type WP Map Block can plot.' ),
					self::text_field( 'query', 'Contains', false, 'Case-insensitive match against title and content, the same way WP Map Block\'s own ?q= search works.' ),
					self::number_field( 'limit', 'Maximum Results', false, 'Defaults to 50.' ),
				];

			case 'filter_locations':
				return [
					self::post_type_select( 'post_type', 'Only this post type', false, 'Leave empty to include every post type WP Map Block can plot.' ),
					self::select_field( 'status', 'Status', [
						[
							'value' => 'publish',
							'label' => 'Published'
						],
						[
							'value' => 'draft',
							'label' => 'Draft'
						],
						[
							'value' => 'pending',
							'label' => 'Pending review'
						],
						[
							'value' => 'private',
							'label' => 'Private'
						],
						[
							'value' => 'any',
							'label' => 'Any status'
						],
					], false, 'Defaults to published.' ),
					self::text_field( 'taxonomy', 'Taxonomy', false, 'e.g. category or product_cat. Needs a term slug too.' ),
					self::text_field( 'term', 'Term Slug', false, 'e.g. dhaka. Needs a taxonomy too.' ),
					self::text_field( 'near_lat', 'Centre Latitude', false, 'Nearest first, and the basis for the radius. Needs a centre longitude too.' ),
					self::text_field( 'near_lng', 'Centre Longitude', false, 'Nearest first, and the basis for the radius. Needs a centre latitude too.' ),
					self::number_field( 'radius', 'Radius (km)', false, 'Only with a centre. Leave empty for every distance, sorted nearest first.' ),
					self::number_field( 'limit', 'Maximum Results', false, 'Defaults to 50.' ),
				];

			case 'refresh_map_data':
				return [
					self::map_select( 'map_id', true ),
					self::number_field( 'limit', 'Maximum Markers', false, 'Caps the marker list returned. Defaults to 200.' ),
				];

			case 'sync_dynamic_data':
				return [
					self::map_select( 'map_id', true ),
					self::post_type_select( 'post_type', 'Post Type Source to Add', false, 'Leave empty to only reconcile what the map already lists.' ),
					self::toggle( 'prune', 'Drop Sources Whose Data Is Gone', 'Removes post type and listing sources that no longer resolve. Off by default so an automation cannot quietly delete one.' ),
				];

			case 'update_map_settings':
				return [
					self::map_select( 'map_id', true ),
					self::field( 'clustering_radius', 'Clustering Radius', 'number', false ),
					self::field( 'clustering_max_zoom', 'Clustering Max Zoom', 'number', false ),
					self::field( 'heatmap_radius', 'Heatmap Radius', 'number', false ),
					self::field( 'heatmap_intensity', 'Heatmap Intensity', 'number', false ),
					self::field( 'store_locator_radius', 'Store Locator Radius', 'number', false ),
					self::select_field( 'store_locator_unit', 'Store Locator Unit', [
						[
							'value' => 'km',
							'label' => 'Kilometres'
						],
						[
							'value' => 'mi',
							'label' => 'Miles'
						],
					] ),
					self::text_field( 'store_locator_placeholder', 'Store Locator Placeholder' ),
					self::field( 'directory_width', 'Directory Width', 'number', false ),
					self::select_field( 'directory_layout', 'Directory Layout', [
						[
							'value' => 'side',
							'label' => 'Side panel'
						],
						[
							'value' => 'bottom',
							'label' => 'Bottom sheet'
						],
					] ),
					self::select_field( 'directory_position', 'Directory Position', [
						[
							'value' => 'left',
							'label' => 'Left'
						],
						[
							'value' => 'right',
							'label' => 'Right'
						],
					] ),
					self::select_field( 'directions_service', 'Directions Service', [
						[
							'value' => 'google',
							'label' => 'Google Directions'
						],
						[
							'value' => 'osm',
							'label' => 'OpenStreetMap Nominatim'
						],
					] ),
					self::field( 'popup_max_width', 'Popup Max Width', 'number', false ),
					self::select_field( 'popup_theme', 'Popup Theme', [
						[
							'value' => 'light',
							'label' => 'Light'
						],
						[
							'value' => 'dark',
							'label' => 'Dark'
						],
					] ),
					self::select_field( 'popup_preset', 'Popup Preset', [
						[
							'value' => 'card',
							'label' => 'Card'
						],
						[
							'value' => 'overlay',
							'label' => 'Overlay'
						],
						[
							'value' => 'compact',
							'label' => 'Compact'
						],
						[
							'value' => 'minimal',
							'label' => 'Minimal'
						],
						[
							'value' => 'classic',
							'label' => 'Classic'
						],
					] ),
					self::text_field( 'popup_cta_label', 'Popup Call to Action Label' ),
				];

			case 'update_marker_settings':
				return [
					self::map_select( 'map_id', true ),
					self::select_field( 'animation', 'Marker Animation', [
						[
							'value' => 'none',
							'label' => 'None'
						],
						[
							'value' => 'bounce',
							'label' => 'Bounce'
						],
						[
							'value' => 'drop',
							'label' => 'Drop'
						],
						[
							'value' => 'pulse',
							'label' => 'Pulse'
						],
					] ),
					self::toggle( 'draggable', 'Markers Are Draggable' ),
				];

			case 'set_map_center':
				return [
					self::map_select( 'map_id', true ),
					self::text_field( 'lat', 'Latitude', true, 'Decimal degrees, e.g. 23.8103.' ),
					self::text_field( 'lng', 'Longitude', true, 'Decimal degrees, e.g. 90.4125.' ),
					self::number_field( 'zoom', 'Zoom Level', false, '0 to 24. Leave empty to keep the current zoom.' ),
				];

			case 'set_map_provider':
				return [
					self::map_select( 'map_id', true ),
					self::select_field( 'provider', 'Map Provider', self::provider_options(), true ),
					self::with_help( self::field( 'api_key', 'Provider API Key', 'password', false ), 'Leave empty to keep the key already saved on the map.' ),
				];

			case 'get_rest_map_data':
				return [ self::map_select( 'map_id', true ) ];
		}//end switch

		return [];
	}

	private static function marker_body_schema(): array {
		return [
			self::text_field( 'lat', 'Latitude', true, 'Decimal degrees, e.g. 23.780573.' ),
			self::text_field( 'lng', 'Longitude', true, 'Decimal degrees, e.g. 90.407067.' ),
			self::text_field( 'title', 'Marker Title' ),
			self::field( 'content', 'Popup Content', 'textarea', false, [
				'help' => 'HTML is allowed — WP Map Block stores popup content with wp_kses_post().',
			] ),
			self::text_field( 'link', 'Popup Link URL' ),
			self::text_field( 'image', 'Popup Image URL' ),
			self::field( 'category', 'Category', 'select', false, [
				'help'    => 'Created on the map if the id does not exist yet.',
				'dynamic' => [
					'integration' => 'wpmapblock',
					'query'       => 'map_categories',
					'select'      => [ 'value', 'label' ],
					'depends_on'  => [ 'map_id' ],
				],
			] ),
			self::select_field( 'icon_type', 'Icon', [
				[
					'value' => 'default',
					'label' => 'Default pin'
				],
				[
					'value' => 'color',
					'label' => 'Coloured pin'
				],
				[
					'value' => 'image',
					'label' => 'Custom image'
				],
				[
					'value' => 'preset',
					'label' => 'Preset icon'
				],
			] ),
			self::text_field( 'icon_color', 'Icon Colour', false, 'Hex, e.g. #006bff. Used by the coloured pin.' ),
			self::text_field( 'icon_url', 'Icon Image URL' ),
			self::text_field( 'icon_preset', 'Icon Preset', false, 'Preset key, e.g. flagship.' ),
		];
	}

	private static function provider_options(): array {
		$providers = [
			[ 'openstreetmap', 'OpenStreetMap' ],
			[ 'google', 'Google Maps (API key)' ],
			[ 'mapbox', 'Mapbox (Pro)' ],
			[ 'maptiler', 'MapTiler (Pro)' ],
			[ 'openfreemap', 'OpenFreeMap (Pro)' ],
			[ 'stadia', 'Stadia Maps (Pro)' ],
			[ 'esri', 'Esri / ArcGIS (Pro)' ],
			[ 'carto', 'CARTO (Pro)' ],
			[ 'azure', 'Azure Maps (Pro)' ],
		];

		$out = [];
		foreach ( $providers as $provider ) {
			$out[] = [
				'value' => $provider[0],
				'label' => $provider[1]
			];
		}
		return $out;
	}

	public static function execute_node( array $node, array $input ): array {
		$event  = self::node_event( $node );
		$config = self::config( $node );

		if ( '' === $event || ! isset( self::get_actions()[ $event ] ) ) {
			throw new \InvalidArgumentException( 'Unknown WP Map Block action.' );
		}

		$method = 'action_' . $event;
		if ( ! method_exists( static::class, $method ) ) {
			throw new \InvalidArgumentException( sprintf( 'WP Map Block action "%s" is not implemented.', $event ) );
		}

		return static::$method( $config, $input );
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		$map = [
			'map_id'           => 128,
			'map_title'        => 'Store Locator',
			'status'           => 'publish',
			'active'           => true,
			'provider'         => 'openstreetmap',
			'center_lat'       => 23.8103,
			'center_lng'       => 90.4125,
			'zoom'             => 11,
			'markers_count'    => 4,
			'categories_count' => 2,
			'shortcode'        => '[wp_map id="128"]',
			'has_api_key'      => false,
			'modified'         => '2026-09-27 10:15:00',
		];

		$marker = [
			'id'       => 'mk_8f31c2a4',
			'lat'      => 23.780573,
			'lng'      => 90.407067,
			'title'    => 'Flagship Store',
			'content'  => '<p>Open 10:00–22:00</p>',
			'image'    => '',
			'link'     => 'https://example.com/stores/flagship',
			'category' => 'flagship',
			'icon'     => [
				'type' => 'color',
				'preset' => '',
				'url' => '',
				'color' => '#006bff'
			],
		];

		$location = [
			'post_id'    => 42,
			'post_title' => 'Flagship Store',
			'post_type'  => 'product',
			'status'     => 'publish',
			'permalink'  => 'https://example.com/product/flagship-store/',
			'lat'        => 23.780573,
			'lng'        => 90.407067,
		];

		switch ( $trigger ) {
			case 'map_created':
			case 'map_updated':
				return array_merge( $map, [ 'config' => self::sample_config() ] );

			case 'map_status_changed':
				return array_merge( $map, [
					'previous_status' => 'draft',
					'new_status' => 'publish'
				] );

			case 'map_deleted':
				return array_merge( $map, [ 'deleted' => true ] );

			case 'marker_added':
			case 'marker_updated':
			case 'marker_removed':
				return array_merge( $map, [
					'change' => $trigger,
					'marker' => $marker,
					'markers' => [ $marker ]
				] );

			case 'marker_moved':
				return array_merge( $map, [
					'change'  => $trigger,
					'marker'  => array_merge( $marker, [
						'previous_lat' => 23.746100,
						'previous_lng' => 90.391200
					] ),
					'markers' => [
						array_merge( $marker, [
							'previous_lat' => 23.746100,
							'previous_lng' => 90.391200
						] )
					],
				] );

			case 'map_viewed':
				return $map;

			case 'map_searched':
				return array_merge( $map, [
					'query'  => 'flagship',
					'limit'  => 200,
					'source' => 'store_locator',
				] );

			case 'location_created':
			case 'store_created':
				return $location;

			case 'location_updated':
			case 'store_updated':
				return array_merge( $location, [ 'changed_field' => '_wpmb_lat' ] );

			case 'location_deleted':
			case 'store_deleted':
				return array_merge( $location, [ 'deleted' => true ] );

			case 'location_published':
				return array_merge( $location, [
					'previous_status' => 'draft',
					'new_status' => 'publish'
				] );

			case 'location_status_changed':
				return array_merge( $location, [
					'previous_status' => 'publish',
					'new_status' => 'draft'
				] );

			case 'map_refreshed':
				return [
					'route'              => '/wpmb/v1/dynamic-preview',
					'map_ids'            => [ 128 ],
					'data_sources_count' => 1,
					'data_sources'       => [
						[
							'id' => 'ds_4b9c1f02',
							'type' => 'cpt',
							'label' => 'Stores',
							'postType' => 'product'
						],
					],
					'post_types'         => [ 'product' ],
					'refreshed_at'       => '2026-09-27T10:15:00+00:00',
				];

			case 'map_data_imported':
				return array_merge( $map, [
					'imported' => 4,
					'incoming_count' => 8,
					'origin' => 'rest'
				] );

			case 'rest_map_updated':
				return array_merge( $map, [
					'origin'  => 'rest',
					'route'   => '/wpmb/v1/maps/128',
					'changed' => [ 'provider', 'view' ],
				] );
		}//end switch

		return [];
	}

	public static function get_action_sample_output( string $action ): array {
		$map = [
			'map_id'           => 128,
			'map_title'        => 'Store Locator',
			'status'           => 'publish',
			'active'           => true,
			'provider'         => 'openstreetmap',
			'center_lat'       => 23.8103,
			'center_lng'       => 90.4125,
			'zoom'             => 11,
			'markers_count'    => 4,
			'categories_count' => 2,
			'shortcode'        => '[wp_map id="128"]',
			'has_api_key'      => false,
			'modified'         => '2026-09-27 10:15:00',
		];

		$marker = [
			'id'       => 'mk_8f31c2a4',
			'lat'      => 23.780573,
			'lng'      => 90.407067,
			'title'    => 'Flagship Store',
			'content'  => '<p>Open 10:00–22:00</p>',
			'image'    => '',
			'link'     => 'https://example.com/stores/flagship',
			'category' => 'flagship',
			'icon'     => [
				'type' => 'color',
				'preset' => '',
				'url' => '',
				'color' => '#006bff'
			],
		];

		$location = [
			'post_id'    => 42,
			'post_title' => 'Flagship Store',
			'post_type'  => 'product',
			'status'     => 'publish',
			'permalink'  => 'https://example.com/product/flagship-store/',
			'lat'        => 23.780573,
			'lng'        => 90.407067,
		];

		switch ( $action ) {
			case 'create_map':
			case 'update_map':
				return array_merge( [
					'success' => true,
					'changed' => [ 'provider' ]
				], $map, [ 'config' => self::sample_config() ] );

			case 'duplicate_map':
				return array_merge( [
					'success' => true,
					'source_map_id' => 127
				], $map, [ 'config' => self::sample_config() ] );

			case 'set_map_status':
				return array_merge( [
					'success' => true,
					'changed' => true,
					'previous_status' => 'draft',
					'new_status' => 'publish'
				], $map );

			case 'delete_map':
				return array_merge( [
					'success' => true,
					'deleted' => true
				], $map );

			case 'get_map':
				return array_merge( [ 'success' => true ], $map, [ 'config' => self::sample_config() ] );

			case 'search_markers':
				return [
					'success'        => true,
					'map_id'         => 128,
					'query'          => 'flagship',
					'category'       => '',
					'total'          => 1,
					'items'          => [ $marker ],
					'markers_count'  => 1,
				];

			case 'export_map':
				return [
					'success'       => true,
					'map_id'        => 128,
					'title'         => 'Store Locator',
					'shortcode'     => '[wp_map id="128"]',
					'markers_count' => 4,
					'config'        => self::sample_config(),
					'json'          => '{"provider":"openstreetmap","markers":[]}',
				];

			case 'add_marker':
				return [
					'success' => true,
					'map_id' => 128,
					'marker' => $marker,
					'markers_count' => 5
				];

			case 'update_marker':
			case 'move_marker':
				return [
					'success' => true,
					'map_id' => 128,
					'marker' => $marker,
					'markers_count' => 4
				];

			case 'delete_marker':
				return [
					'success' => true,
					'deleted' => true,
					'map_id' => 128,
					'marker' => $marker,
					'markers_count' => 3
				];

			case 'import_markers':
				return [
					'success'       => true,
					'map_id'        => 128,
					'imported'      => 2,
					'mode'          => 'append',
					'markers'       => [ $marker ],
					'markers_count' => 6,
				];

			case 'add_data_source':
				return [
					'success'            => true,
					'map_id'             => 128,
					'data_source'        => [
						'id'       => 'ds_4b9c1f02',
						'type'     => 'cpt',
						'label'    => 'Stores',
						'url'      => '',
						'source'   => '',
						'postType' => 'product',
						'latMeta'  => '_wpmb_lat',
						'lngMeta'  => '_wpmb_lng',
						'limit'    => 100,
					],
					'data_sources_count' => 1,
				];

			case 'set_post_location':
				return [
					'success'          => true,
					'post_id'          => 42,
					'post_title'       => 'Flagship Store',
					'post_type'        => 'product',
					'permalink'        => 'https://example.com/product/flagship-store/',
					'lat'              => '23.780573',
					'lng'              => '90.407067',
					'included_on_maps' => true,
				];

			case 'geocode_address':
				return [
					'success'      => true,
					'found'        => true,
					'address'      => 'Gulshan Avenue, Dhaka',
					'lat'          => '23.7925000',
					'lng'          => '90.4077000',
					'display_name' => 'Gulshan Avenue, Dhaka, Bangladesh',
					'items'        => [
						[
							'lat'           => '23.7925000',
							'lon'           => '90.4077000',
							'display_name'  => 'Gulshan Avenue, Dhaka, Bangladesh',
						],
					],
				];

			case 'create_location':
			case 'create_store':
				return array_merge( [ 'success' => true ], $location );

			case 'update_location':
			case 'update_store':
				return array_merge( [
					'success' => true,
					'changed' => [ 'title', 'coordinates' ]
				], $location );

			case 'delete_location':
			case 'delete_store':
				return array_merge( [
					'success' => true,
					'deleted' => true
				], $location );

			case 'get_location':
				return array_merge( [ 'success' => true ], $location );

			case 'publish_location':
				return array_merge( [
					'success' => true,
					'changed' => true,
					'previous_status' => 'draft',
					'new_status' => 'publish'
				], $location );

			case 'unpublish_location':
				return array_merge( [
					'success' => true,
					'changed' => true,
					'previous_status' => 'publish',
					'new_status' => 'draft'
				], $location );

			case 'set_location_status':
				return array_merge( [
					'success' => true,
					'changed' => true,
					'previous_status' => 'draft',
					'new_status' => 'publish'
				], $location );

			case 'search_locations':
				return [
					'success'   => true,
					'query'     => 'flagship',
					'post_type' => 'product',
					'total'     => 1,
					'count'     => 1,
					'items'     => [ $location ],
				];

			case 'filter_locations':
				return [
					'success'   => true,
					'post_type' => 'product',
					'status'    => 'publish',
					'taxonomy'  => 'product_cat',
					'term'      => 'dhaka',
					'near_lat'  => 23.8103,
					'near_lng'  => 90.4125,
					'radius_km' => 25.0,
					'total'     => 1,
					'count'     => 1,
					'items'     => [ array_merge( $location, [ 'distance_km' => 4.612 ] ) ],
				];

			case 'refresh_map_data':
				return [
					'success'               => true,
					'map_id'                => 128,
					'data_sources_count'    => 1,
					'data_sources'          => [
						[
							'id' => 'ds_4b9c1f02',
							'type' => 'cpt',
							'label' => 'Stores',
							'postType' => 'product'
						],
					],
					'post_types'            => [ 'product' ],
					'markers_count'         => 6,
					'static_markers_count'  => 2,
					'dynamic_markers_count' => 4,
					'categories_count'      => 2,
					'markers'               => [ $marker ],
					'refreshed_at'          => '2026-09-27T10:15:00+00:00',
				];

			case 'sync_dynamic_data':
				$source = [
					'id'       => 'ds_9a1e77c4',
					'type'     => 'cpt',
					'label'    => 'Products',
					'url'      => '',
					'source'   => '',
					'postType' => 'product',
					'latMeta'  => '_wpmb_lat',
					'lngMeta'  => '_wpmb_lng',
					'limit'    => 100,
				];

				return [
					'success'            => true,
					'map_id'             => 128,
					'pruned'             => false,
					'changed'            => true,
					'added'              => [ $source ],
					'removed'            => [],
					'data_sources'       => [ $source ],
					'data_sources_count' => 1,
					'markers_count'      => 6,
				];

			case 'update_map_settings':
				return array_merge(
					[
						'success' => true,
						'changed' => [ 'clustering_radius', 'popup_theme' ]
					],
					$map,
					[ 'config' => self::sample_config() ]
				);

			case 'update_marker_settings':
				return [
					'success'        => true,
					'map_id'         => 128,
					'changed'        => [ 'animation' ],
					'marker_options' => [
						'animation' => 'drop',
						'draggable' => false
					],
					'markers_count'  => 4,
				];

			case 'set_map_center':
				return array_merge( [
					'success' => true,
					'changed' => [ 'center_lat', 'center_lng' ]
				], $map );

			case 'set_map_provider':
				return array_merge( [
					'success' => true,
					'changed' => [ 'provider', 'api_key' ]
				], $map );

			case 'get_rest_map_data':
				return [
					'success'      => true,
					'id'           => 128,
					'map_id'       => 128,
					'title'        => 'Store Locator',
					'status'       => 'active',
					'provider'     => 'openstreetmap',
					'shortcode'    => '[wp_map id="128"]',
					'modified'     => '2026-09-27T10:15:00+00:00',
					'markersCount' => 4,
					'config'       => self::sample_config(),
				];
		}//end switch

		return [];
	}

	private static function sample_config(): array {
		return [
			'version'    => 3,
			'provider'   => 'openstreetmap',
			'view'       => [
				'center' => [
					'lat' => 23.8103,
					'lng' => 90.4125
				],
				'zoom'   => 11,
			],
			'size'       => [
				'width' => '100%',
				'height' => 480
			],
			'mapType'    => 'roadmap',
			'clustering' => [
				'enabled' => true,
				'radius' => 50,
				'maxZoom' => 14
			],
			'markers'    => [],
			'categories' => [],
			'shapes'     => [],
			'dataSources' => [],
		];
	}
}
