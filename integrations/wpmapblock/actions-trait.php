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
			[
				'success' => true,
				'changed' => $changed
			],
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
			[
				'success' => true,
				'source_map_id' => (int) $source->ID
			],
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
		self::flush_source_index();

		return self::success( array_merge( [
			'success' => true,
			'deleted' => true
		], $payload ) );
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
			'markers_count' => count( $matches ),
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
			'data_sources_count' => count( self::list_of( $document, 'dataSources' ) ),
		] );
	}

	private static function action_set_post_location( array $config, array $input ): array {
		$post = self::require_location( self::id_from( $config, 'post_id' ) );

		if ( ! self::provided( $config, 'lat' ) || ! self::provided( $config, 'lng' ) ) {
			throw new \InvalidArgumentException( 'Latitude and longitude are required.' );
		}

		$lat = trim( (string) $config['lat'] );
		$lng = trim( (string) $config['lng'] );
		self::require_coordinate_pair( $lat, $lng );

		self::write_coordinates( (int) $post->ID, $lat, $lng );

		return self::success( [
			'success'         => true,
			'post_id'         => (int) $post->ID,
			'post_title'      => (string) $post->post_title,
			'post_type'       => (string) $post->post_type,
			'permalink'       => (string) get_permalink( $post->ID ),
			'lat'             => $lat,
			'lng'             => $lng,
			'included_on_maps' => self::is_location_enabled( (string) $post->post_type ),
		] );
	}

	private static function require_coordinate_pair( $lat, $lng ): void {
		if ( '' === (string) $lat || '' === (string) $lng ) {
			throw new \InvalidArgumentException( 'Latitude and longitude are required.' );
		}
		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			throw new \InvalidArgumentException( 'Latitude and longitude must be decimal degrees, e.g. 23.780573.' );
		}
	}

	private static function location_status( array $config, string $default ): string {
		$status = trim( (string) ( $config['status'] ?? '' ) );
		if ( '' === $status ) {
			return $default;
		}

		$allowed = [ 'publish', 'draft', 'pending', 'private', 'future' ];
		if ( ! in_array( $status, $allowed, true ) ) {
			throw new \InvalidArgumentException( sprintf( 'Status must be one of: %s.', implode( ', ', $allowed ) ) );
		}

		return $status;
	}

	private static function action_create_location( array $config, array $input ): array {
		$post_type = trim( (string) ( $config['post_type'] ?? '' ) );
		$title     = trim( (string) ( $config['title'] ?? '' ) );

		if ( '' === $post_type ) {
			throw new \InvalidArgumentException( 'A post type is required.' );
		}
		if ( '' === $title ) {
			throw new \InvalidArgumentException( 'A title is required.' );
		}

		$lat = trim( (string) ( $config['lat'] ?? '' ) );
		$lng = trim( (string) ( $config['lng'] ?? '' ) );
		self::require_coordinate_pair( $lat, $lng );

		$post_id = wp_insert_post(
			[
				'post_type'    => $post_type,
				'post_title'   => $title,
				'post_status'  => self::location_status( $config, 'publish' ),
				'post_content' => (string) ( $config['content'] ?? '' ),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( (string) $post_id->get_error_message() );
		}
		if ( ! $post_id ) {
			throw new \RuntimeException( 'The location could not be created.' );
		}

		self::write_coordinates( (int) $post_id, $lat, $lng );

		return self::success( array_merge(
			[ 'success' => true ],
			self::location_payload( self::require_location( $post_id ) )
		) );
	}

	private static function action_update_location( array $config, array $input ): array {
		$post    = self::require_location( self::id_from( $config, 'post_id' ) );
		$post_id = (int) $post->ID;
		$changed = [];
		$updates = [];

		if ( self::provided( $config, 'title' ) ) {
			$updates['post_title'] = trim( (string) $config['title'] );
			$changed[]             = 'title';
		}
		if ( self::provided( $config, 'content' ) ) {
			$updates['post_content'] = (string) $config['content'];
			$changed[]               = 'content';
		}
		if ( self::provided( $config, 'status' ) ) {
			$updates['post_status'] = self::location_status( $config, (string) $post->post_status );
			$changed[]              = 'status';
		}

		if ( ! empty( $updates ) ) {
			$updates['ID'] = $post_id;
			$result        = wp_update_post( $updates, true );
			if ( is_wp_error( $result ) ) {
				throw new \RuntimeException( (string) $result->get_error_message() );
			}
		}

		$lat = self::provided( $config, 'lat' ) ? trim( (string) $config['lat'] ) : null;
		$lng = self::provided( $config, 'lng' ) ? trim( (string) $config['lng'] ) : null;

		if ( null !== $lat && ! is_numeric( $lat ) ) {
			throw new \InvalidArgumentException( 'Latitude must be decimal degrees, e.g. 23.780573.' );
		}
		if ( null !== $lng && ! is_numeric( $lng ) ) {
			throw new \InvalidArgumentException( 'Longitude must be decimal degrees, e.g. 90.407067.' );
		}

		if ( null !== $lat || null !== $lng ) {
			self::write_coordinates( $post_id, $lat, $lng );
			$changed[] = null === $lat ? 'lng' : ( null === $lng ? 'lat' : 'coordinates' );
		}

		if ( empty( $changed ) ) {
			throw new \InvalidArgumentException( 'Nothing to change: provide a title, content, status, latitude or longitude.' );
		}

		return self::success( array_merge(
			[
				'success' => true,
				'changed' => $changed
			],
			self::location_payload( self::require_location( $post_id ) )
		) );
	}

	private static function action_delete_location( array $config, array $input ): array {
		$post    = self::require_location( self::id_from( $config, 'post_id' ) );
		$post_id = (int) $post->ID;
		$payload = self::location_payload( $post );

		if ( ! wp_delete_post( $post_id, true ) ) {
			throw new \RuntimeException( 'The location could not be deleted.' );
		}

		return self::success( array_merge( [
			'success' => true,
			'deleted' => true
		], $payload ) );
	}

	private static function action_set_location_status( array $config, array $input ): array {
		$post    = self::require_location( self::id_from( $config, 'post_id' ) );
		$post_id = (int) $post->ID;
		$before  = (string) $post->post_status;
		$wanted  = self::location_status( $config, $before );
		$changed = $before !== $wanted;

		if ( $changed ) {
			$result = wp_update_post( [
				'ID' => $post_id,
				'post_status' => $wanted
			], true );
			if ( is_wp_error( $result ) ) {
				throw new \RuntimeException( (string) $result->get_error_message() );
			}
		}

		return self::success( array_merge(
			[
				'success'         => true,
				'changed'         => $changed,
				'previous_status' => $before,
				'new_status'      => $wanted,
			],
			self::location_payload( self::require_location( $post_id ) )
		) );
	}

	private static function action_publish_location( array $config, array $input ): array {
		return self::action_set_location_status( array_merge( $config, [ 'status' => 'publish' ] ), $input );
	}

	private static function action_unpublish_location( array $config, array $input ): array {
		return self::action_set_location_status( array_merge( $config, [ 'status' => 'draft' ] ), $input );
	}

	private static function action_get_location( array $config, array $input ): array {
		return self::success( array_merge(
			[ 'success' => true ],
			self::location_payload( self::require_location( self::id_from( $config, 'post_id' ) ) )
		) );
	}

	private static function action_search_locations( array $config, array $input ): array {
		$only_type = trim( (string) ( $config['post_type'] ?? '' ) );
		$needle    = trim( (string) ( $config['query'] ?? '' ) );
		$limit     = self::provided( $config, 'limit' ) ? max( 1, min( 500, (int) $config['limit'] ) ) : 50;

		$args = [
			'post_type'   => '' !== $only_type ? $only_type : 'any',
			'post_status' => 'publish',
			'numberposts' => $limit,
			'meta_key'    => self::LAT_META,
			'orderby'     => 'title',
			'order'       => 'ASC',
		];
		if ( '' !== $needle ) {
			$args['s'] = $needle;
		}

		$items = [];
		foreach ( (array) get_posts( $args ) as $post ) {
			if ( ! self::is_location_post( $post ) ) {
				continue;
			}
			$items[] = self::location_payload( $post );
		}

		return self::success( [
			'success'   => true,
			'query'     => $needle,
			'post_type' => $only_type,
			'total'     => count( $items ),
			'count'     => count( $items ),
			'items'     => $items,
		] );
	}

	private static function action_filter_locations( array $config, array $input ): array {
		$only_type = trim( (string) ( $config['post_type'] ?? '' ) );
		$taxonomy  = trim( (string) ( $config['taxonomy'] ?? '' ) );
		$term      = trim( (string) ( $config['term'] ?? '' ) );
		$status    = trim( (string) ( $config['status'] ?? '' ) );
		$status    = '' !== $status ? $status : 'publish';
		$limit     = self::provided( $config, 'limit' ) ? max( 1, min( 500, (int) $config['limit'] ) ) : 50;

		if ( ( '' === $taxonomy ) !== ( '' === $term ) ) {
			throw new \InvalidArgumentException( 'A taxonomy filter needs both a taxonomy and a term slug.' );
		}

		$near_lat = self::provided( $config, 'near_lat' ) ? (float) $config['near_lat'] : null;
		$near_lng = self::provided( $config, 'near_lng' ) ? (float) $config['near_lng'] : null;
		if ( ( null === $near_lat ) !== ( null === $near_lng ) ) {
			throw new \InvalidArgumentException( 'A radius filter needs both a centre latitude and a centre longitude.' );
		}
		if ( null !== $near_lat && ( ! is_finite( $near_lat ) || ! is_finite( $near_lng ) ) ) {
			throw new \InvalidArgumentException( 'The centre must be decimal degrees, e.g. 23.8103.' );
		}

		$radius = self::provided( $config, 'radius' ) ? max( 0, (float) $config['radius'] ) : 0;
		$args   = [
			'post_type'   => '' !== $only_type ? $only_type : 'any',
			'post_status' => $status,
			'numberposts' => $limit,
			'meta_key'    => self::LAT_META,
			'orderby'     => 'title',
			'order'       => 'ASC',
		];
		if ( '' !== $taxonomy ) {
			$args['tax_query'] = [
				[
					'taxonomy' => $taxonomy,
					'field' => 'slug',
					'terms' => [ $term ]
				]
			];
		}

		$items = [];
		foreach ( (array) get_posts( $args ) as $post ) {
			if ( ! self::is_location_post( $post ) ) {
				continue;
			}

			$payload = self::location_payload( $post );

			if ( null !== $near_lat ) {
				$distance = self::haversine_km( $near_lat, $near_lng, $payload['lat'], $payload['lng'] );
				if ( $radius > 0 && $distance > $radius ) {
					continue;
				}
				$payload['distance_km'] = round( $distance, 3 );
			}

			$items[] = $payload;
		}

		if ( null !== $near_lat ) {
			usort( $items, function ( $left, $right ) {
				return $left['distance_km'] <=> $right['distance_km'];
			} );
		}

		return self::success( [
			'success'    => true,
			'post_type'  => $only_type,
			'status'     => $status,
			'taxonomy'   => $taxonomy,
			'term'       => $term,
			'near_lat'   => $near_lat,
			'near_lng'   => $near_lng,
			'radius_km'  => $radius,
			'total'      => count( $items ),
			'count'      => count( $items ),
			'items'      => array_slice( $items, 0, $limit ),
		] );
	}

	private static function action_create_store( array $config, array $input ): array {
		$config['post_type'] = self::require_store_post_type( trim( (string) ( $config['post_type'] ?? '' ) ) );

		return self::action_create_location( $config, $input );
	}

	private static function action_update_store( array $config, array $input ): array {
		self::require_store_location( self::id_from( $config, 'post_id' ) );

		return self::action_update_location( $config, $input );
	}

	private static function action_delete_store( array $config, array $input ): array {
		self::require_store_location( self::id_from( $config, 'post_id' ) );

		return self::action_delete_location( $config, $input );
	}

	private static function action_refresh_map_data( array $config, array $input ): array {
		$post     = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id   = (int) $post->ID;
		$document = self::read_config( $map_id );
		$sources  = self::list_of( $document, 'dataSources' );
		$limit    = self::provided( $config, 'limit' ) ? max( 1, min( 2000, (int) $config['limit'] ) ) : 200;

		$markers   = self::markers_of( self::resolved_markers( $document ) );
		$static    = count( self::markers_of( $document ) );
		$post_types = [];

		foreach ( $sources as $source ) {
			$post_type = trim( (string) ( $source['postType'] ?? '' ) );
			if ( '' !== $post_type ) {
				$post_types[] = $post_type;
			}
		}

		return self::success( [
			'success'               => true,
			'map_id'                => $map_id,
			'data_sources_count'    => count( $sources ),
			'data_sources'          => $sources,
			'post_types'            => array_values( array_unique( $post_types ) ),
			'markers_count'         => count( $markers ),
			'static_markers_count'  => $static,
			'dynamic_markers_count' => max( 0, count( $markers ) - $static ),
			'categories_count'      => count( self::list_of( $document, 'categories' ) ),
			'markers'               => array_slice( $markers, 0, $limit ),
			'refreshed_at'          => gmdate( 'c' ),
		] );
	}

	private static function action_sync_dynamic_data( array $config, array $input ): array {
		$post     = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id   = (int) $post->ID;
		$want     = trim( (string) ( $config['post_type'] ?? '' ) );
		$prune    = 'yes' === (string) ( $config['prune'] ?? 'no' );

		$document = self::read_config( $map_id );
		$listing  = self::location_sources();
		$added    = [];
		$removed  = [];
		$kept     = [];

		foreach ( self::list_of( $document, 'dataSources' ) as $source ) {
			$type = (string) ( $source['type'] ?? '' );
			$dead = false;

			if ( $prune && 'cpt' === $type ) {
				$post_type = (string) ( $source['postType'] ?? '' );
				$dead      = '' === $post_type
					|| ( function_exists( 'post_type_exists' ) && ! post_type_exists( $post_type ) );
			} elseif ( $prune && 'listing' === $type ) {
				$source_id = (string) ( $source['source'] ?? '' );
				$dead      = '' === $source_id || ! isset( $listing[ $source_id ] );
			}

			if ( $dead ) {
				$removed[] = $source;
				continue;
			}

			$kept[] = $source;
		}

		if ( '' !== $want ) {
			$exists = false;
			foreach ( $kept as $source ) {
				if ( 'cpt' === (string) ( $source['type'] ?? '' )
					&& $want === trim( (string) ( $source['postType'] ?? '' ) ) ) {
					$exists = true;
					break;
				}
			}

			if ( ! $exists ) {
				$label = $want;
				if ( function_exists( 'get_post_type_object' ) ) {
					$object = get_post_type_object( $want );
					if ( $object && ! empty( $object->labels->name ) ) {
						$label = (string) $object->labels->name;
					}
				}

				$source = [
					'id'       => 'ds_' . substr( md5( uniqid( (string) $map_id, true ) ), 0, 8 ),
					'type'     => 'cpt',
					'label'    => $label,
					'url'      => '',
					'source'   => '',
					'postType' => $want,
					'latMeta'  => self::LAT_META,
					'lngMeta'  => self::LNG_META,
					'limit'    => 100,
				];

				$kept[]  = $source;
				$added[] = $source;
			}//end if
		}//end if

		$changed = ! empty( $added ) || ! empty( $removed );
		if ( $changed ) {
			$document['dataSources'] = $kept;
			$document                = self::save_config( $map_id, $document );
		}

		$resolved = self::resolved_markers( $document );

		return self::success( [
			'success'            => true,
			'map_id'             => $map_id,
			'pruned'             => $prune,
			'changed'            => $changed,
			'added'              => $added,
			'removed'            => $removed,
			'data_sources'       => self::list_of( $document, 'dataSources' ),
			'data_sources_count' => count( self::list_of( $document, 'dataSources' ) ),
			'markers_count'      => count( self::markers_of( $resolved ) ),
		] );
	}

	private static function deep_settings_map(): array {
		return [
			'clustering_radius'         => [ 'clustering', 'radius' ],
			'clustering_max_zoom'       => [ 'clustering', 'maxZoom' ],
			'heatmap_radius'            => [ 'heatmap', 'radius' ],
			'heatmap_intensity'         => [ 'heatmap', 'intensity' ],
			'store_locator_radius'      => [ 'storeLocator', 'radius' ],
			'store_locator_unit'        => [ 'storeLocator', 'unit' ],
			'store_locator_placeholder' => [ 'storeLocator', 'placeholder' ],
			'directory_width'           => [ 'directory', 'width' ],
			'directory_layout'          => [ 'directory', 'layout' ],
			'directory_position'        => [ 'directory', 'position' ],
			'directions_service'        => [ 'directions', 'service' ],
			'popup_max_width'           => [ 'style', 'popup', 'maxWidth' ],
			'popup_theme'               => [ 'style', 'popup', 'theme' ],
			'popup_preset'              => [ 'style', 'popup', 'preset' ],
			'popup_cta_label'           => [ 'style', 'popup', 'ctaLabel' ],
		];
	}

	private static function apply_deep_settings( array $document, array $config, array &$changed = [] ): array {
		$enums = [
			'store_locator_unit' => [ 'km', 'mi' ],
			'directory_layout'   => [ 'side', 'bottom' ],
			'directory_position' => [ 'left', 'right' ],
			'directions_service' => [ 'google', 'osm' ],
			'popup_theme'        => [ 'light', 'dark' ],
			'popup_preset'       => [ 'card', 'overlay', 'compact', 'minimal', 'classic' ],
		];

		foreach ( self::deep_settings_map() as $key => $path ) {
			if ( ! self::provided( $config, $key ) ) {
				continue;
			}

			$value = $config[ $key ];

			if ( isset( $enums[ $key ] ) ) {
				$value = strtolower( trim( (string) $value ) );
				if ( ! in_array( $value, $enums[ $key ], true ) ) {
					throw new \InvalidArgumentException(
						sprintf( '%s must be one of: %s.', $key, implode( ', ', $enums[ $key ] ) )
					);
				}
			} elseif ( 'heatmap_intensity' === $key ) {
				$value = (float) $value;
			} elseif ( is_numeric( $value ) ) {
				$value = (int) $value;
			} else {
				$value = trim( (string) $value );
			}

			$target = &$document;
			foreach ( $path as $segment ) {
				if ( ! isset( $target[ $segment ] ) || ! is_array( $target[ $segment ] ) ) {
					$target[ $segment ] = [];
				}
				$target = &$target[ $segment ];
			}
			$target = $value;
			unset( $target );

			$changed[] = $key;
		}//end foreach

		return $document;
	}

	private static function action_update_map_settings( array $config, array $input ): array {
		self::require_plugin();

		$post     = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id   = (int) $post->ID;
		$changed  = [];
		$document = self::apply_deep_settings( self::read_config( $map_id ), $config, $changed );

		if ( empty( $changed ) ) {
			throw new \InvalidArgumentException( 'Nothing to change: provide at least one setting.' );
		}

		$document = self::save_config( $map_id, $document );

		return self::success( array_merge(
			[
				'success' => true,
				'changed' => $changed
			],
			self::map_payload( self::require_map( $map_id ), true )
		) );
	}

	private static function action_update_marker_settings( array $config, array $input ): array {
		self::require_plugin();

		$post    = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id  = (int) $post->ID;
		$changed = [];

		$document = self::read_config( $map_id );
		$options  = isset( $document['markerOptions'] ) && is_array( $document['markerOptions'] )
			? $document['markerOptions']
			: [];

		if ( self::provided( $config, 'animation' ) ) {
			$animation = strtolower( trim( (string) $config['animation'] ) );
			if ( ! in_array( $animation, [ 'none', 'bounce', 'drop', 'pulse' ], true ) ) {
				throw new \InvalidArgumentException( 'Animation must be one of: none, bounce, drop, pulse.' );
			}
			$options['animation'] = $animation;
			$changed[]            = 'animation';
		}
		if ( self::provided( $config, 'draggable' ) ) {
			$options['draggable'] = 'yes' === (string) $config['draggable'];
			$changed[]            = 'draggable';
		}

		if ( empty( $changed ) ) {
			throw new \InvalidArgumentException( 'Nothing to change: provide an animation or a draggable setting.' );
		}

		$document['markerOptions'] = $options;
		$document                  = self::save_config( $map_id, $document );

		return self::success( [
			'success'        => true,
			'map_id'         => $map_id,
			'changed'        => $changed,
			'marker_options' => $document['markerOptions'],
			'markers_count'  => count( self::markers_of( $document ) ),
		] );
	}

	private static function action_set_map_center( array $config, array $input ): array {
		self::require_plugin();

		$post   = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id = (int) $post->ID;
		$lat    = trim( (string) ( $config['lat'] ?? '' ) );
		$lng    = trim( (string) ( $config['lng'] ?? '' ) );
		self::require_coordinate_pair( $lat, $lng );

		$document = self::read_config( $map_id );
		$view     = isset( $document['view'] ) && is_array( $document['view'] ) ? $document['view'] : [];
		$center   = isset( $view['center'] ) && is_array( $view['center'] ) ? $view['center'] : [];

		$view['center'] = array_merge( $center, [
			'lat' => (float) $lat,
			'lng' => (float) $lng
		] );
		if ( self::provided( $config, 'zoom' ) ) {
			$view['zoom'] = max( 0, min( 24, (int) $config['zoom'] ) );
		}

		$document['view'] = $view;
		$document         = self::save_config( $map_id, $document );

		return self::success( array_merge(
			[
				'success' => true,
				'changed' => [ 'center_lat', 'center_lng' ]
			],
			self::map_payload( self::require_map( $map_id ) )
		) );
	}

	private static function action_set_map_provider( array $config, array $input ): array {
		self::require_plugin();

		$post     = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id   = (int) $post->ID;
		$provider = strtolower( trim( (string) ( $config['provider'] ?? '' ) ) );

		if ( '' === $provider ) {
			throw new \InvalidArgumentException( 'A map provider is required.' );
		}

		$allowed = [];
		foreach ( self::provider_options() as $option ) {
			$allowed[] = (string) $option['value'];
		}
		if ( ! in_array( $provider, $allowed, true ) ) {
			throw new \InvalidArgumentException( sprintf( 'Unknown map provider "%s".', $provider ) );
		}

		$document              = self::read_config( $map_id );
		$document['provider']  = $provider;
		$changed               = [ 'provider' ];

		if ( self::provided( $config, 'api_key' ) ) {
			$options = isset( $document['providerOptions'] ) && is_array( $document['providerOptions'] )
				? $document['providerOptions']
				: [];
			$options['apiKey']          = (string) $config['api_key'];
			$document['providerOptions'] = $options;
			$changed[]                   = 'api_key';
		}

		$document = self::save_config( $map_id, $document );

		return self::success( array_merge(
			[
				'success' => true,
				'changed' => $changed
			],
			self::map_payload( self::require_map( $map_id ) )
		) );
	}

	private static function action_get_rest_map_data( array $config, array $input ): array {
		$post     = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id   = (int) $post->ID;
		$document = self::read_config( $map_id );
		$modified = function_exists( 'get_post_modified_time' )
			? (string) get_post_modified_time( 'c', true, $post )
			: (string) ( $post->post_modified_gmt ?? '' );

		return self::success( [
			'success'      => true,
			'id'           => $map_id,
			'map_id'       => $map_id,
			'title'        => (string) $post->post_title,
			'status'       => 'publish' === $post->post_status ? 'active' : 'inactive',
			'provider'     => (string) ( $document['provider'] ?? 'openstreetmap' ),
			'shortcode'    => '[wp_map id="' . $map_id . '"]',
			'modified'     => $modified,
			'markersCount' => count( self::markers_of( $document ) ),
			'config'       => self::redacted_config( $document ),
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
