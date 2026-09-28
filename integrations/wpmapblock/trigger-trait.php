<?php

namespace Zaplane\Integrations\Wpmapblock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait TriggerTrait {

	private static function trigger_document_written( array $filter, array $args ) {
		$map_id = self::meta_target( $args );
		if ( ! $map_id || self::CONFIG_META !== self::meta_key( $args ) ) {
			return false;
		}

		$post = self::find_map( $map_id );
		if ( ! $post || ! self::map_matches( $filter, $map_id ) ) {
			return false;
		}

		return self::map_payload( $post, true );
	}
	private static function trigger_status_changed( array $filter, array $args ) {
		$new = (string) ( $args[0] ?? '' );
		$old = (string) ( $args[1] ?? '' );
		$post = $args[2] ?? null;

		if ( ! $post instanceof \WP_Post || self::MAP_POST_TYPE !== $post->post_type ) {
			return false;
		}
		// Creation is its own event; `new` is WordPress' placeholder status.
		if ( '' === $new || $new === $old || in_array( $old, [ 'new', 'auto-draft' ], true ) ) {
			return false;
		}
		if ( ! self::map_matches( $filter, (int) $post->ID ) ) {
			return false;
		}

		return array_merge( self::map_payload( $post ), [
			'previous_status' => $old,
			'new_status'      => $new,
		] );
	}

	private static function trigger_map_deleted( array $filter, array $args ) {
		$post = $args[1] ?? null;
		if ( ! $post instanceof \WP_Post ) {
			$post = self::find_map( (int) ( $args[0] ?? 0 ) );
		}
		if ( ! $post instanceof \WP_Post || self::MAP_POST_TYPE !== $post->post_type ) {
			return false;
		}
		if ( ! self::map_matches( $filter, (int) $post->ID ) ) {
			return false;
		}

		return array_merge( self::map_payload( $post ), [ 'deleted' => true ] );
	}

	private static function trigger_marker_change( string $event, array $filter, array $args ) {
		$map_id = self::meta_target( $args );
		if ( ! $map_id || self::CONFIG_META !== self::meta_key( $args ) ) {
			return false;
		}
		if ( ! self::find_map( $map_id ) ) {
			return false;
		}

		if ( 'update_post_meta' === current_filter() ) {
			// The meta cache has not been cleared yet, so this is the document
			// that is about to be replaced.
			self::$baseline[ $map_id ] = self::markers_of( self::read_config( $map_id ) );
			return false;
		}

		if ( ! array_key_exists( $map_id, self::$baseline ) ) {
			return false;
		}

		$diff = self::diff_markers( self::$baseline[ $map_id ], self::markers_of( self::read_config( $map_id ) ) );
		if ( empty( $diff[ $event ] ) || ! self::map_matches( $filter, $map_id ) ) {
			return false;
		}

		$post = self::find_map( $map_id );

		return array_merge( self::map_payload( $post ), [
			'change'  => $event,
			'marker'  => $diff[ $event ][0],
			'markers' => $diff[ $event ],
		] );
	}

	private static function trigger_map_viewed( array $filter, array $args ) {
		if ( ! is_array( $args[0] ?? null ) ) {
			return false;
		}
		$dom_id = (string) ( $args[1] ?? '' );
		if ( ! preg_match( '/^wpmb-map-(\d+)$/', $dom_id, $matches ) ) {
			// The quick "simple map" block renders `wpmb-simple-*` and is not a
			// saved map, so there is nothing to report.
			return false;
		}
		// The block editor's AJAX preview renders through the same code path.
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return false;
		}

		$post = self::find_map( (int) $matches[1] );
		if ( ! $post || 'publish' !== $post->post_status || ! self::map_matches( $filter, (int) $post->ID ) ) {
			return false;
		}

		return self::map_payload( $post );
	}

	private static function trigger_map_searched( array $filter, array $args ) {
		$request = $args[2] ?? null;
		if ( ! $request instanceof \WP_REST_Request ) {
			return false;
		}

		$namespace = defined( 'WPMAPBLOCK_REST_NAMESPACE' ) ? WPMAPBLOCK_REST_NAMESPACE : self::REST_NS;
		$route     = (string) $request->get_route();
		if ( ! preg_match( '#^/' . preg_quote( $namespace, '#' ) . '/maps/(\d+)/search$#', $route, $matches ) ) {
			return false;
		}

		$post = self::find_map( (int) $matches[1] );
		if ( ! $post || ! self::map_matches( $filter, (int) $post->ID ) ) {
			return false;
		}

		$search = trim( (string) $request->get_param( 'q' ) );
		$needle = trim( (string) ( $filter['query'] ?? '' ) );
		if ( '' !== $needle && false === stripos( $search, $needle ) ) {
			return false;
		}

		return array_merge( self::map_payload( $post ), [
			'query'  => $search,
			'limit'  => (int) $request->get_param( 'limit' ),
			'source' => 'store_locator',
		] );
	}

	private static function location_match( array $filter, $post, string $scope = 'location' ): bool {
		if ( 'store' === $scope ) {
			if ( ! self::is_store_location( $post ) ) {
				return false;
			}
		} elseif ( ! self::is_location_post( $post ) ) {
			return false;
		}

		$only_type = trim( (string) ( $filter['post_type'] ?? '' ) );
		if ( '' !== $only_type && ( ! $post instanceof \WP_Post || $post->post_type !== $only_type ) ) {
			return false;
		}

		return true;
	}

	private static function trigger_location_event( string $event, array $filter, array $args, string $scope = 'location' ) {
		switch ( $event ) {
			case 'location_created':
				return self::trigger_location_created( $filter, $args, $scope );

			case 'location_updated':
				return self::trigger_location_updated( $filter, $args, $scope );

			case 'location_deleted':
				return self::trigger_location_deleted( $filter, $args, $scope );

			case 'location_published':
				return self::trigger_location_transition( 'published', $filter, $args, $scope );

			case 'location_status_changed':
				return self::trigger_location_transition( 'status', $filter, $args, $scope );
		}

		return false;
	}

	private static function trigger_location_created( array $filter, array $args, string $scope = 'location' ) {
		if ( ! in_array( self::meta_key( $args ), [ self::LAT_META, self::LNG_META ], true ) ) {
			return false;
		}

		$post_id = self::meta_target( $args );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! self::location_match( $filter, $post, $scope ) ) {
			return false;
		}

		// Requiring both halves is what keeps creation to exactly one event:
		// `Location_Meta::save()` writes latitude first, at which point the pair
		// is still incomplete and `has_coordinates()` says no.
		return self::location_payload( $post );
	}

	private static function trigger_location_updated(
		array $filter,
		array $args,
		string $scope = 'location',
		?string $hook = null
	) {
		$meta_key = self::meta_key( $args );
		if ( ! in_array( $meta_key, [ self::LAT_META, self::LNG_META ], true ) ) {
			return false;
		}

		$post_id = self::meta_target( $args );
		if ( $post_id <= 0 ) {
			return false;
		}

		$hook = null !== $hook ? $hook : (string) current_filter();
		$state = self::location_state_key( $filter, $scope, $post_id );

		if ( 'update_post_meta' === $hook ) {
			// The row has not been touched yet, so this is the pair the save
			// started from.
			self::$location_prev[ $state ] = self::current_pair( $post_id );
			return false;
		}

		if ( 'updated_post_meta' !== $hook ) {
			return false;
		}

		$final = self::intended_pair( $post_id );
		if ( null === $final ) {
			if ( self::LNG_META !== $meta_key ) {
				return false;
			}
			$final = self::current_pair( $post_id );
		}

		$key = self::pair_key( $final );
		if ( isset( self::$location_seen[ $state ] ) && self::$location_seen[ $state ] === $key ) {
			// The other half of the same save, or a repeat of a finished pair.
			return false;
		}
		if ( isset( self::$location_prev[ $state ] ) && self::pair_key( self::$location_prev[ $state ] ) === $key ) {
			// Nothing actually moved — WordPress just ran the hook anyway.
			unset( self::$location_prev[ $state ] );
			return false;
		}

		$post = get_post( $post_id );
		if ( ! self::location_match( $filter, $post, $scope ) ) {
			return false;
		}

		self::$location_seen[ $state ] = $key;
		unset( self::$location_prev[ $state ] );

		return self::location_payload( $post, [
			'lat'           => (float) $final['lat'],
			'lng'           => (float) $final['lng'],
			'changed_field' => $meta_key,
		] );
	}

	private static function trigger_location_deleted( array $filter, array $args, string $scope = 'location' ) {
		$post = $args[1] ?? null;
		if ( ! $post instanceof \WP_Post ) {
			$post_id = (int) ( $args[0] ?? 0 );
			$post    = $post_id ? get_post( $post_id ) : null;
		}
		if ( ! self::location_match( $filter, $post, $scope ) ) {
			return false;
		}

		return self::location_payload( $post, [ 'deleted' => true ] );
	}

	private static function trigger_location_transition(
		string $mode,
		array $filter,
		array $args,
		string $scope = 'location',
		?string $hook = null
	) {
		$hook = null !== $hook ? $hook : (string) current_filter();

		if ( 'transition_post_status' === $hook ) {
			$new      = (string) ( $args[0] ?? '' );
			$previous = (string) ( $args[1] ?? '' );
			$post     = $args[2] ?? null;

			// Creation is its own event; `new` is WordPress' placeholder status.
			if ( '' === $new || $new === $previous ) {
				return false;
			}
			if ( in_array( $previous, [ 'new', 'auto-draft' ], true ) ) {
				return false;
			}
			if ( 'published' === $mode && 'publish' !== $new ) {
				return false;
			}
		} elseif ( 'added_post_meta' === $hook ) {
			if ( 'published' !== $mode ) {
				return false;
			}
			if ( ! in_array( self::meta_key( $args ), [ self::LAT_META, self::LNG_META ], true ) ) {
				return false;
			}

			$post_id = self::meta_target( $args );
			$post    = $post_id ? get_post( $post_id ) : null;
			// No status was crossed to get here, so there is nothing to report
			// as previous.
			$new      = 'publish';
			$previous = '';
		} else {
			return false;
		}//end if

		if ( ! self::location_match( $filter, $post, $scope ) ) {
			return false;
		}
		if ( 'published' === $mode && 'publish' !== (string) $post->post_status ) {
			return false;
		}

		return self::location_payload( $post, [
			'previous_status' => $previous,
			'new_status'      => $new,
		] );
	}

	private static function trigger_map_refreshed( array $filter, array $args ) {
		if ( ( $args[0] ?? null ) instanceof \WP_Error ) {
			return false;
		}

		$request = $args[2] ?? null;
		if ( ! $request instanceof \WP_REST_Request ) {
			return false;
		}

		$namespace = defined( 'WPMAPBLOCK_REST_NAMESPACE' ) ? WPMAPBLOCK_REST_NAMESPACE : self::REST_NS;
		$route     = (string) $request->get_route();
		if ( ! preg_match( '#^/' . preg_quote( $namespace, '#' ) . '/dynamic-preview$#', $route ) ) {
			return false;
		}
		if ( 'POST' !== strtoupper( (string) $request->get_method() ) ) {
			return false;
		}

		$body    = $request->get_json_params();
		$sources = isset( $body['dataSources'] ) && is_array( $body['dataSources'] )
			? array_values( array_filter( $body['dataSources'], 'is_array' ) )
			: [];

		$post_types = [];
		foreach ( $sources as $source ) {
			$post_type = trim( (string) ( $source['postType'] ?? '' ) );
			if ( '' !== $post_type ) {
				$post_types[] = $post_type;
			}
		}

		$map_ids = self::maps_with_data_sources( $sources );
		if ( ! self::map_id_list_matches( $filter, $map_ids ) ) {
			return false;
		}

		return [
			'route'               => $route,
			'map_ids'             => $map_ids,
			'data_sources_count'  => count( $sources ),
			'data_sources'        => $sources,
			'post_types'          => array_values( array_unique( $post_types ) ),
			'refreshed_at'        => gmdate( 'c' ),
		];
	}

	private static function trigger_map_data_imported( array $filter, array $args ) {
		$request = $args[2] ?? null;
		if ( ! $request instanceof \WP_REST_Request ) {
			return false;
		}

		$namespace = defined( 'WPMAPBLOCK_REST_NAMESPACE' ) ? WPMAPBLOCK_REST_NAMESPACE : self::REST_NS;
		$route     = (string) $request->get_route();
		if ( ! preg_match( '#^/' . preg_quote( $namespace, '#' ) . '/maps/(\d+)$#', $route, $matches ) ) {
			return false;
		}
		if ( ! in_array( strtoupper( (string) $request->get_method() ), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			return false;
		}

		$map_id = (int) $matches[1];
		if ( ! self::find_map( $map_id ) ) {
			return false;
		}

		if ( 'rest_request_before_callbacks' === current_filter() ) {
			self::$import_baseline[ $map_id ] = count( self::markers_of( self::read_config( $map_id ) ) );
			return false;
		}

		if ( ( $args[0] ?? null ) instanceof \WP_Error || ! isset( self::$import_baseline[ $map_id ] ) ) {
			return false;
		}

		$before = (int) self::$import_baseline[ $map_id ];
		unset( self::$import_baseline[ $map_id ] );

		$body    = $request->get_json_params();
		$config  = isset( $body['config'] ) && is_array( $body['config'] ) ? $body['config'] : $body;
		$markers = isset( $config['markers'] ) && is_array( $config['markers'] ) ? $config['markers'] : null;
		if ( null === $markers ) {
			return false;
		}

		$incoming = count( array_filter( $markers, 'is_array' ) );
		if ( $incoming === $before ) {
			return false;
		}

		$post = self::find_map( $map_id );
		if ( ! $post || ! self::map_matches( $filter, $map_id ) ) {
			return false;
		}

		return array_merge( self::map_payload( $post ), [
			'imported'       => $incoming - $before,
			'incoming_count' => $incoming,
			'origin'         => 'rest',
		] );
	}

	private static function trigger_rest_map_updated( array $filter, array $args ) {
		if ( ( $args[0] ?? null ) instanceof \WP_Error ) {
			return false;
		}

		$request = $args[2] ?? null;
		if ( ! $request instanceof \WP_REST_Request ) {
			return false;
		}

		$namespace = defined( 'WPMAPBLOCK_REST_NAMESPACE' ) ? WPMAPBLOCK_REST_NAMESPACE : self::REST_NS;
		$route     = (string) $request->get_route();
		if ( ! preg_match( '#^/' . preg_quote( $namespace, '#' ) . '/maps/(\d+)(?:/status)?$#', $route, $matches ) ) {
			return false;
		}
		if ( ! in_array( strtoupper( (string) $request->get_method() ), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			return false;
		}

		$map_id = (int) $matches[1];
		$post   = self::find_map( $map_id );
		if ( ! $post || ! self::map_matches( $filter, $map_id ) ) {
			return false;
		}

		$body    = $request->get_json_params();
		$changed = [];
		if ( is_array( $body ) ) {
			if ( isset( $body['title'] ) ) {
				$changed[] = 'title';
			}
			if ( isset( $body['active'] ) ) {
				$changed[] = 'status';
			}
			if ( isset( $body['config'] ) && is_array( $body['config'] ) ) {
				$changed = array_merge( $changed, array_keys( $body['config'] ) );
			}
		}

		return array_merge( self::map_payload( $post ), [
			'origin'  => 'rest',
			'route'   => $route,
			'changed' => array_values( array_unique( $changed ) ),
		] );
	}

	private static function maps_with_data_sources( array $sources ): array {
		$wanted = (string) wp_json_encode( $sources );
		$ids    = [];

		foreach ( self::all_map_ids() as $map_id ) {
			$stored = self::list_of( self::read_config( $map_id ), 'dataSources' );
			if ( (string) wp_json_encode( $stored ) === $wanted ) {
				$ids[] = $map_id;
			}
		}

		return $ids;
	}

	private static function all_map_ids(): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return [];
		}

		$posts = get_posts( [
			'post_type'        => self::MAP_POST_TYPE,
			'post_status'      => 'any',
			'numberposts'      => -1,
			'suppress_filters' => false,
		] );

		$ids = [];
		foreach ( (array) $posts as $entry ) {
			$map_id = $entry instanceof \WP_Post ? (int) $entry->ID : (int) $entry;
			if ( $map_id > 0 ) {
				$ids[] = $map_id;
			}
		}

		return $ids;
	}

	private static function map_id_list_matches( array $filter, array $map_ids ): bool {
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

		return in_array( (int) $wanted, array_map( 'intval', $map_ids ), true );
	}
}
