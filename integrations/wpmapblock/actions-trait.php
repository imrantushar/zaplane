<?php

namespace Zaplane\Integrations\Wpmapblock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ActionsTrait {

	private static function action_create_map( array $config, array $input ): array {
		self::require_plugin();

		$title = trim( (string) ( $config['title'] ?? '' ) );
		if ( '' === $title ) {
			throw new \InvalidArgumentException( 'A map title is required.' );
		}

		$document = self::preset_document( trim( (string) ( $config['preset'] ?? '' ) ) );
		$document = self::apply_settings( $document, $config );

		$map_id = wp_insert_post(
			[
				'post_type'   => self::MAP_POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'publish',
			],
			true
		);

		if ( is_wp_error( $map_id ) ) {
			throw new \RuntimeException( (string) $map_id->get_error_message() );
		}
		if ( ! $map_id ) {
			throw new \RuntimeException( 'WP Map Block could not create the map.' );
		}

		self::save_config( (int) $map_id, $document );

		return self::success( array_merge( [ 'success' => true ], self::map_payload( self::require_map( $map_id ), true ) ) );
	}

	private static function action_update_map( array $config, array $input ): array {
		self::require_plugin();

		$post    = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id  = (int) $post->ID;
		$changed = [];

		if ( self::provided( $config, 'title' ) ) {
			$changed[] = 'title';
			wp_update_post( [
				'ID'         => $map_id,
				'post_title' => trim( (string) $config['title'] ),
			] );
		}

		$document = self::apply_settings( self::read_config( $map_id ), $config, $changed );
		$document = self::save_config( $map_id, $document );

		return self::success( array_merge(
			[ 'success' => true, 'changed' => $changed ],
			self::map_payload( self::require_map( $map_id ), true )
		) );
	}

	private static function action_duplicate_map( array $config, array $input ): array {
		$source = self::require_map( self::id_from( $config, 'map_id' ) );

		$new_id = wp_insert_post( [
			'post_type'   => self::MAP_POST_TYPE,
			'post_title'  => sprintf( '%s (copy)', $source->post_title ),
			'post_status' => 'publish',
		] );

		if ( is_wp_error( $new_id ) ) {
			throw new \RuntimeException( (string) $new_id->get_error_message() );
		}
		if ( ! $new_id ) {
			throw new \RuntimeException( 'WP Map Block could not duplicate the map.' );
		}

		$raw          = get_post_meta( (int) $source->ID, self::CONFIG_META, true );
		$source_json  = is_string( $raw ) ? $raw : (string) wp_json_encode( is_array( $raw ) ? $raw : [] );
		self::write_config_json( (int) $new_id, $source_json );

		return self::success( array_merge(
			[ 'success' => true, 'source_map_id' => (int) $source->ID ],
			self::map_payload( self::require_map( $new_id ), true )
		) );
	}

	private static function action_set_map_status( array $config, array $input ): array {
		$post    = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id  = (int) $post->ID;
		$wanted  = 'inactive' === (string) ( $config['status'] ?? '' ) ? 'draft' : 'publish';
		$before  = (string) $post->post_status;

		if ( $before !== $wanted ) {
			wp_update_post( [
				'ID'          => $map_id,
				'post_status' => $wanted,
			] );
		}

		return self::success( array_merge(
			[
				'success'         => true,
				'changed'         => $before !== $wanted,
				'previous_status' => $before,
				'new_status'      => $wanted,
			],
			self::map_payload( self::require_map( $map_id ) )
		) );
	}

	private static function action_delete_map( array $config, array $input ): array {
		$post   = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id = (int) $post->ID;
		$payload = self::map_payload( $post );

		if ( ! wp_delete_post( $map_id, true ) ) {
			throw new \RuntimeException( 'WP Map Block could not delete the map.' );
		}

		return self::success( array_merge( [ 'success' => true, 'deleted' => true ], $payload ) );
	}

	private static function action_get_map( array $config, array $input ): array {
		return self::success( array_merge(
			[ 'success' => true ],
			self::map_payload( self::require_map( self::id_from( $config, 'map_id' ) ), true )
		) );
	}

	private static function action_search_markers( array $config, array $input ): array {
		$post     = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id   = (int) $post->ID;
		$document = self::resolved_markers( self::read_config( $map_id ) );
		$markers  = self::markers_of( $document );

		$needle   = trim( (string) ( $config['query'] ?? '' ) );
		$category = trim( (string) ( $config['category'] ?? '' ) );
		$limit    = self::provided( $config, 'limit' ) ? max( 1, (int) $config['limit'] ) : 50;

		$matches = array_values( array_filter( $markers, function ( $marker ) use ( $needle, $category ) {
			if ( '' !== $category && (string) ( $marker['category'] ?? '' ) !== $category ) {
				return false;
			}
			if ( '' === $needle ) {
				return true;
			}
			$haystack = strtolower( (string) ( $marker['title'] ?? '' ) . ' ' . wp_strip_all_tags( (string) ( $marker['content'] ?? '' ) ) );
			return false !== strpos( $haystack, strtolower( $needle ) );
		} ) );

		return self::success( [
			'success'      => true,
			'map_id'       => $map_id,
			'query'        => $needle,
			'category'     => $category,
			'total'        => count( $matches ),
			'items'        => array_slice( $matches, 0, $limit ),
			'markers_count'=> count( $matches ),
		] );
	}

	private static function action_export_map( array $config, array $input ): array {
		$post     = self::require_map( self::id_from( $config, 'map_id' ) );
		$document = self::read_config( (int) $post->ID );
		$json     = (string) wp_json_encode( $document, JSON_PRETTY_PRINT );

		return self::success( [
			'success'        => true,
			'map_id'         => (int) $post->ID,
			'title'          => (string) $post->post_title,
			'shortcode'      => '[wp_map id="' . (int) $post->ID . '"]',
			'markers_count'  => count( self::markers_of( $document ) ),
			'config'         => self::redacted_config( $document ),
			'json'           => $json,
		] );
	}

	private static function action_add_marker( array $config, array $input ): array {
		$post     = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id   = (int) $post->ID;
		$document = self::read_config( $map_id );

		$marker            = self::build_marker( $config );
		$document['markers'] = array_merge( self::markers_of( $document ), [ $marker ] );
		$document          = self::ensure_category( $document, (string) $marker['category'] );
		$document          = self::save_config( $map_id, $document );

		return self::success( [
			'success'       => true,
			'map_id'        => $map_id,
			'marker'        => self::last_marker( $document ),
			'markers_count' => count( self::markers_of( $document ) ),
		] );
	}

	private static function action_update_marker( array $config, array $input ): array {
		return self::edit_marker( $config, self::marker_body_keys() );
	}

	private static function action_move_marker( array $config, array $input ): array {
		return self::edit_marker( $config, [ 'lat', 'lng' ] );
	}

	private static function action_delete_marker( array $config, array $input ): array {
		$post     = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id   = (int) $post->ID;
		$marker_id = trim( (string) ( $config['marker_id'] ?? '' ) );
		if ( '' === $marker_id ) {
			throw new \InvalidArgumentException( 'A marker is required.' );
		}

		$markers = self::markers_of( self::read_config( $map_id ) );
		$index   = self::marker_index( $markers, $marker_id );
		if ( null === $index ) {
			throw new \InvalidArgumentException( sprintf( 'Marker "%s" is not on this map.', $marker_id ) );
		}

		$removed = $markers[ $index ];
		unset( $markers[ $index ] );

		$document            = self::read_config( $map_id );
		$document['markers'] = array_values( $markers );
		$document            = self::save_config( $map_id, $document );

		return self::success( [
			'success'       => true,
			'deleted'       => true,
			'map_id'        => $map_id,
			'marker'        => $removed,
			'markers_count' => count( self::markers_of( $document ) ),
		] );
	}

	private static function action_import_markers( array $config, array $input ): array {
		self::require_plugin();

		$post     = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id   = (int) $post->ID;
		$incoming = self::parse_markers( (string) ( $config['data'] ?? '' ) );

		$document   = self::read_config( $map_id );
		$existing   = 'replace' === (string) ( $config['mode'] ?? 'append' ) ? [] : self::markers_of( $document );
		$imported   = [];

		foreach ( $incoming as $raw ) {
			$marker = \WPMapBlock\Config::sanitize_marker( $raw );
			if ( ! is_array( $marker ) ) {
				continue;
			}
			$document = self::ensure_category( $document, (string) $marker['category'] );
			$existing[] = $marker;
			$imported[] = $marker;
		}

		$document['markers'] = array_values( $existing );
		$document            = self::save_config( $map_id, $document );

		return self::success( [
			'success'        => true,
			'map_id'         => $map_id,
			'imported'       => count( $imported ),
			'mode'           => 'replace' === (string) ( $config['mode'] ?? 'append' ) ? 'replace' : 'append',
			'markers'        => $imported,
			'markers_count'  => count( self::markers_of( $document ) ),
		] );
	}

	private static function action_add_data_source( array $config, array $input ): array {
		self::require_plugin();

		$post   = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id = (int) $post->ID;
		$type   = (string) ( $config['type'] ?? 'cpt' );

		if ( in_array( $type, [ 'geojson', 'rest' ], true ) && '' === trim( (string) ( $config['url'] ?? '' ) ) ) {
			throw new \InvalidArgumentException( 'A URL is required for GeoJSON and REST sources.' );
		}
		if ( 'listing' === $type && '' === trim( (string) ( $config['source'] ?? '' ) ) ) {
			throw new \InvalidArgumentException( 'A listing source is required.' );
		}
		if ( 'cpt' === $type && '' === trim( (string) ( $config['post_type'] ?? '' ) ) ) {
			throw new \InvalidArgumentException( 'A post type is required.' );
		}

		$document = self::read_config( $map_id );
		$sources  = self::list_of( $document, 'dataSources' );

		$source = [
			'id'       => 'ds_' . substr( md5( uniqid( (string) $map_id, true ) ), 0, 8 ),
			'type'     => $type,
			'label'    => trim( (string) ( $config['label'] ?? '' ) ),
			'url'      => trim( (string) ( $config['url'] ?? '' ) ),
			'source'   => trim( (string) ( $config['source'] ?? '' ) ),
			'postType' => trim( (string) ( $config['post_type'] ?? '' ) ),
			'latMeta'  => trim( (string) ( $config['lat_meta'] ?? '' ) ),
			'lngMeta'  => trim( (string) ( $config['lng_meta'] ?? '' ) ),
			'limit'    => self::provided( $config, 'limit' ) ? (int) $config['limit'] : 100,
		];

		$sources[] = $source;

		$document['dataSources'] = $sources;
		$document                = self::save_config( $map_id, $document );

		return self::success( [
			'success'           => true,
			'map_id'            => $map_id,
			'data_source'       => $source,
			'data_sources_count'=> count( self::list_of( $document, 'dataSources' ) ),
		] );
	}

	private static function action_set_post_location( array $config, array $input ): array {
		$post_id = self::id_from( $config, 'post_id' );
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post instanceof \WP_Post || in_array( $post->post_type, [ 'revision', 'attachment' ], true ) ) {
			throw new \InvalidArgumentException( 'A valid post ID is required.' );
		}

		if ( ! self::provided( $config, 'lat' ) || ! self::provided( $config, 'lng' ) ) {
			throw new \InvalidArgumentException( 'Latitude and longitude are required.' );
		}

		update_post_meta( (int) $post->ID, self::LAT_META, trim( (string) $config['lat'] ) );
		update_post_meta( (int) $post->ID, self::LNG_META, trim( (string) $config['lng'] ) );

		return self::success( [
			'success'         => true,
			'post_id'         => (int) $post->ID,
			'post_title'      => (string) $post->post_title,
			'post_type'       => (string) $post->post_type,
			'permalink'       => (string) get_permalink( $post->ID ),
			'lat'             => trim( (string) $config['lat'] ),
			'lng'             => trim( (string) $config['lng'] ),
			'included_on_maps'=> self::is_location_enabled( (string) $post->post_type ),
		] );
	}

	private static function action_geocode_address( array $config, array $input ): array {
		$address = trim( (string) ( $config['address'] ?? '' ) );
		if ( '' === $address ) {
			throw new \InvalidArgumentException( 'An address is required.' );
		}

		$limit = self::provided( $config, 'limit' ) ? max( 1, min( 10, (int) $config['limit'] ) ) : 1;
		$url   = 'https://nominatim.openstreetmap.org/search?format=json&addressdetails=0&limit='
			. rawurlencode( (string) $limit )
			. '&q=' . rawurlencode( $address );

		$response = wp_remote_get( $url, [
			'timeout'    => 15,
			'user-agent' => 'Zaplane WP Map Block geocoder',
		] );

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Geocoding failed: ' . $response->get_error_message() );
		}

		$results = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $results ) || empty( $results ) ) {
			return self::success( [
				'success' => true,
				'found'   => false,
				'address' => $address,
				'lat'     => '',
				'lng'     => '',
				'items'   => [],
			] );
		}

		$first = (array) $results[0];

		return self::success( [
			'success'        => true,
			'found'          => true,
			'address'        => $address,
			'lat'            => (string) ( $first['lat'] ?? '' ),
			'lng'            => (string) ( $first['lon'] ?? $first['lng'] ?? '' ),
			'display_name'   => (string) ( $first['display_name'] ?? '' ),
			'items'          => $results,
		] );
	}
}
