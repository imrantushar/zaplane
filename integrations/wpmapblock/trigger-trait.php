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

	private static function trigger_location_saved( array $filter, array $args ) {
		$meta_key = self::meta_key( $args );
		if ( ! in_array( $meta_key, [ self::LAT_META, self::LNG_META ], true ) ) {
			return false;
		}

		$post_id = self::meta_target( $args );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post instanceof \WP_Post || in_array( $post->post_type, [ 'revision', 'attachment' ], true ) ) {
			return false;
		}

		$only_type = trim( (string) ( $filter['post_type'] ?? '' ) );
		if ( '' !== $only_type && $post->post_type !== $only_type ) {
			return false;
		}

		return [
			'post_id'       => (int) $post->ID,
			'post_title'    => (string) $post->post_title,
			'post_type'     => (string) $post->post_type,
			'status'        => (string) $post->post_status,
			'permalink'     => (string) get_permalink( $post->ID ),
			'lat'           => (float) get_post_meta( $post->ID, self::LAT_META, true ),
			'lng'           => (float) get_post_meta( $post->ID, self::LNG_META, true ),
			'changed_field' => $meta_key,
		];
	}
}
