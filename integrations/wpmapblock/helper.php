<?php

namespace Zaplane\Integrations\Wpmapblock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Helper {

	private static function edit_marker( array $config, array $writable ): array {
		self::require_plugin();

		$post     = self::require_map( self::id_from( $config, 'map_id' ) );
		$map_id   = (int) $post->ID;
		$marker_id = trim( (string) ( $config['marker_id'] ?? '' ) );
		if ( '' === $marker_id ) {
			throw new \InvalidArgumentException( 'A marker is required.' );
		}

		$document = self::read_config( $map_id );
		$markers  = self::markers_of( $document );
		$index    = self::marker_index( $markers, $marker_id );
		if ( null === $index ) {
			throw new \InvalidArgumentException( sprintf( 'Marker "%s" is not on this map.', $marker_id ) );
		}

		$marker = $markers[ $index ];
		foreach ( $writable as $key ) {
			if ( self::provided( $config, $key ) ) {
				$marker[ $key ] = $config[ $key ];
			}
		}

		$marker['id'] = $marker_id;
		$marker       = \WPMapBlock\Config::sanitize_marker( $marker );
		if ( ! is_array( $marker ) ) {
			throw new \InvalidArgumentException( 'The marker needs a latitude and a longitude.' );
		}

		$markers[ $index ] = $marker;

		$document                = self::read_config( $map_id );
		$document['markers']     = $markers;
		$document                = self::ensure_category( $document, (string) $marker['category'] );
		$document                = self::save_config( $map_id, $document );

		return self::success( [
			'success'       => true,
			'map_id'        => $map_id,
			'marker'        => $marker,
			'markers_count' => count( self::markers_of( $document ) ),
		] );
	}

	private static function build_marker( array $config ): array {
		return [
			'id'       => 'mk_' . substr( md5( uniqid( (string) ( $config['map_id'] ?? '' ), true ) ), 0, 8 ),
			'lat'      => trim( (string) ( $config['lat'] ?? '' ) ),
			'lng'      => trim( (string) ( $config['lng'] ?? '' ) ),
			'title'    => trim( (string) ( $config['title'] ?? '' ) ),
			'content'  => (string) ( $config['content'] ?? '' ),
			'link'     => trim( (string) ( $config['link'] ?? '' ) ),
			'image'    => trim( (string) ( $config['image'] ?? '' ) ),
			'category' => trim( (string) ( $config['category'] ?? '' ) ),
			'icon'     => [
				'type'   => trim( (string) ( $config['icon_type'] ?? '' ) ),
				'preset' => trim( (string) ( $config['icon_preset'] ?? '' ) ),
				'url'    => trim( (string) ( $config['icon_url'] ?? '' ) ),
				'color'  => trim( (string) ( $config['icon_color'] ?? '' ) ),
			],
		];
	}

	private static function marker_body_keys(): array {
		return [ 'lat', 'lng', 'title', 'content', 'link', 'image', 'category', 'icon_type', 'icon_preset', 'icon_url', 'icon_color' ];
	}

	private static function marker_index( array $markers, string $marker_id ): ?int {
		foreach ( $markers as $index => $marker ) {
			if ( (string) ( $marker['id'] ?? '' ) === $marker_id ) {
				return (int) $index;
			}
		}
		return null;
	}

	private static function last_marker( array $document ): array {
		$markers = self::markers_of( $document );
		return empty( $markers ) ? [] : end( $markers );
	}

	private static function ensure_category( array $document, string $category_id ): array {
		if ( '' === $category_id ) {
			return $document;
		}

		$categories = self::list_of( $document, 'categories' );
		foreach ( $categories as $category ) {
			if ( (string) ( $category['id'] ?? '' ) === $category_id ) {
				return $document;
			}
		}

		$categories[] = [
			'id'    => $category_id,
			'name'  => ucwords( str_replace( [ '_', '-' ], ' ', $category_id ) ),
			'color' => '',
			'icon'  => '',
		];
		$document['categories'] = $categories;

		return $document;
	}

	private static function resolved_markers( array $document ): array {
		if ( class_exists( '\WPMapBlock\Frontend\DataSources' ) ) {
			$resolved = \WPMapBlock\Frontend\DataSources::resolve( $document );
			if ( is_array( $resolved ) ) {
				return $resolved;
			}
		}
		return $document;
	}

	private static function settings_map(): array {
		return [
			'provider'        => [ 'provider' ],
			'center_lat'      => [ 'view', 'center', 'lat' ],
			'center_lng'      => [ 'view', 'center', 'lng' ],
			'zoom'            => [ 'view', 'zoom' ],
			'width'           => [ 'size', 'width' ],
			'height'          => [ 'size', 'height' ],
			'map_type'        => [ 'mapType' ],
			'theme'           => [ 'style', 'theme' ],
			'clustering'      => [ 'clustering', 'enabled' ],
			'store_locator'   => [ 'storeLocator', 'enabled' ],
			'directory'       => [ 'directory', 'enabled' ],
			'heatmap'         => [ 'heatmap', 'enabled' ],
			'directions'      => [ 'directions', 'enabled' ],
			'api_key'         => [ 'providerOptions', 'apiKey' ],
		];
	}

	private static function apply_settings( array $document, array $config, array &$changed = [] ): array {
		foreach ( self::settings_map() as $key => $path ) {
			if ( ! self::provided( $config, $key ) ) {
				continue;
			}

			$value = $config[ $key ];
			if ( in_array( $key, [ 'clustering', 'store_locator', 'directory', 'heatmap', 'directions' ], true ) ) {
				$value = 'yes' === (string) $value;
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

	private static function preset_document( string $preset ): array {
		if ( '' !== $preset && class_exists( '\WPMapBlock\Presets' ) ) {
			foreach ( (array) \WPMapBlock\Presets::all() as $preset_entry ) {
				if ( is_array( $preset_entry ) && ( $preset_entry['id'] ?? '' ) === $preset ) {
					return (array) ( $preset_entry['config'] ?? [] );
				}
			}
		}
		return class_exists( '\WPMapBlock\Config' ) ? \WPMapBlock\Config::defaults() : [];
	}

	private static function parse_markers( string $raw ): array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			throw new \InvalidArgumentException( 'No marker data was provided.' );
		}

		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			if ( isset( $decoded['lat'], $decoded['lng'] ) ) {
				$decoded = [ $decoded ];
			}
			$markers = [];
			foreach ( $decoded as $row ) {
				if ( is_array( $row ) && isset( $row['lat'], $row['lng'] ) ) {
					$markers[] = $row;
				}
			}
			if ( empty( $markers ) ) {
				throw new \InvalidArgumentException( 'The JSON did not contain any markers with a latitude and longitude.' );
			}
			return $markers;
		}

		return self::parse_csv_markers( $raw );
	}

	private static function parse_csv_markers( string $raw ): array {
		$columns = [ 'lat', 'lng', 'title', 'content', 'link', 'image', 'category' ];
		$lines   = preg_split( '/\r\n|\r|\n/', $raw );
		$lines   = is_array( $lines ) ? $lines : [];

		$offset = 0;
		$keys   = $columns;
		$first  = isset( $lines[0] ) ? array_map( 'strtolower', str_getcsv( (string) $lines[0] ) ) : [];

		foreach ( $first as $index => $header ) {
			$header = trim( (string) $header );
			if ( in_array( $header, $columns, true ) ) {
				$keys[ (int) $index ] = $header;
			}
		}
		if ( in_array( 'lat', $first, true ) && in_array( 'lng', $first, true ) ) {
			$offset = 1;
		}

		$markers = [];
		foreach ( array_slice( $lines, $offset ) as $line ) {
			if ( '' === trim( (string) $line ) ) {
				continue;
			}
			$cells = str_getcsv( (string) $line );
			$row   = [];
			foreach ( $keys as $index => $key ) {
				$row[ $key ] = isset( $cells[ $index ] ) ? trim( (string) $cells[ $index ] ) : '';
			}
			if ( '' === $row['lat'] || '' === $row['lng'] ) {
				continue;
			}
			$row['content'] = trim( str_replace( [ "\n", "\r" ], ' ', (string) $row['content'] ) );
			$markers[]      = $row;
		}

		if ( empty( $markers ) ) {
			throw new \InvalidArgumentException( 'The CSV did not contain any rows with a latitude and longitude.' );
		}

		return $markers;
	}

	private static function location_sources(): array {
		$sources = null;

		if ( function_exists( 'wpmb_get_listing_sources' ) ) {
			$sources = wpmb_get_listing_sources();
		} elseif ( class_exists( '\WPMapBlock\Integrations\Manager' ) ) {
			$sources = \WPMapBlock\Integrations\Manager::all();
		}

		return is_array( $sources ) ? $sources : [];
	}

	private static function source_index(): array {
		if ( null !== self::$source_index ) {
			return self::$source_index;
		}

		$index   = [
			'all' => [],
			'store' => []
		];
		$listing = self::location_sources();

		foreach ( $listing as $source ) {
			$post_type = is_array( $source ) ? trim( (string) ( $source['post_type'] ?? '' ) ) : '';
			if ( '' !== $post_type ) {
				$index['all'][ $post_type ] = true;
			}
		}

		$maps = function_exists( 'get_posts' )
			? get_posts( [
				'post_type'        => self::MAP_POST_TYPE,
				'post_status'      => 'any',
				'numberposts'      => -1,
				'suppress_filters' => false,
			] )
			: [];

		foreach ( (array) $maps as $entry ) {
			$map_id = $entry instanceof \WP_Post ? (int) $entry->ID : (int) $entry;
			if ( $map_id <= 0 ) {
				continue;
			}

			$document = self::read_config( $map_id );
			$is_store = ! empty( $document['storeLocator']['enabled'] );

			foreach ( self::list_of( $document, 'dataSources' ) as $data_source ) {
				$type      = (string) ( $data_source['type'] ?? '' );
				$post_type = '';

				if ( 'cpt' === $type ) {
					$post_type = trim( (string) ( $data_source['postType'] ?? '' ) );
				} elseif ( 'listing' === $type ) {
					$source_id = (string) ( $data_source['source'] ?? '' );
					if ( isset( $listing[ $source_id ] ) && is_array( $listing[ $source_id ] ) ) {
						$post_type = trim( (string) ( $listing[ $source_id ]['post_type'] ?? '' ) );
					}
				}

				if ( '' === $post_type ) {
					continue;
				}

				$index['all'][ $post_type ] = true;
				if ( $is_store ) {
					$index['store'][ $post_type ] = true;
				}
			}//end foreach
		}//end foreach

		self::$source_index = $index;

		return $index;
	}

	private static function is_location_enabled( string $post_type ): bool {
		return '' !== $post_type && isset( self::source_index()['all'][ $post_type ] );
	}

	private static function store_post_types(): array {
		return array_keys( self::source_index()['store'] );
	}

	private static function flush_source_index(): void {
		self::$source_index = null;
	}

	private static function has_coordinates( int $post_id ): bool {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return false;
		}
		$lat = (float) get_post_meta( $post_id, self::LAT_META, true );
		$lng = (float) get_post_meta( $post_id, self::LNG_META, true );

		return ! empty( $lat ) && ! empty( $lng );
	}

	private static function is_location_post( $post ): bool {
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		if ( in_array( $post->post_type, [ 'revision', 'attachment', self::MAP_POST_TYPE ], true ) ) {
			return false;
		}

		return self::has_coordinates( (int) $post->ID );
	}

	private static function is_store_location( $post ): bool {
		return self::is_location_post( $post )
			&& isset( self::source_index()['store'][ (string) $post->post_type ] );
	}

	private static function location_payload( $post, array $extra = [] ): array {
		$post_id = (int) $post->ID;

		return array_merge(
			[
				'post_id'    => $post_id,
				'post_title' => (string) $post->post_title,
				'post_type'  => (string) $post->post_type,
				'status'     => (string) $post->post_status,
				'permalink'  => (string) get_permalink( $post_id ),
				'lat'        => (float) get_post_meta( $post_id, self::LAT_META, true ),
				'lng'        => (float) get_post_meta( $post_id, self::LNG_META, true ),
			],
			$extra
		);
	}

	private static function require_location( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			throw new \InvalidArgumentException( 'A valid post ID is required.' );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			throw new \InvalidArgumentException( 'That post does not exist.' );
		}
		if ( in_array( $post->post_type, [ 'revision', 'attachment', self::MAP_POST_TYPE ], true ) ) {
			throw new \InvalidArgumentException( 'That post is not a location.' );
		}

		return $post;
	}

	private static function require_store_location( $post_id ) {
		$post = self::require_location( $post_id );

		if ( ! isset( self::source_index()['store'][ (string) $post->post_type ] ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'No store-locator map lists "%s". Turn the store locator on for a map with that data source, or use the location actions instead.',
					(string) $post->post_type
				)
			);
		}

		return $post;
	}

	private static function require_store_post_type( string $post_type ): string {
		if ( '' === $post_type ) {
			throw new \InvalidArgumentException( 'A post type is required.' );
		}
		if ( isset( self::source_index()['store'][ $post_type ] ) ) {
			return $post_type;
		}

		$available = self::store_post_types();
		throw new \InvalidArgumentException(
			$available
				? sprintf( 'No store-locator map lists "%s". Store post types are: %s.', $post_type, implode( ', ', $available ) )
				: sprintf( 'No map has the store locator switched on with a dynamic source yet, so "%s" is not a store post type.', $post_type )
		);
	}

	private static function write_coordinates( int $post_id, $lat, $lng ): void {
		self::$location_writes[ $post_id ] = [
			'lat' => null === $lat ? (float) get_post_meta( $post_id, self::LAT_META, true ) : (float) $lat,
			'lng' => null === $lng ? (float) get_post_meta( $post_id, self::LNG_META, true ) : (float) $lng,
		];

		try {
			if ( null !== $lat ) {
				update_post_meta( $post_id, self::LAT_META, trim( (string) $lat ) );
			}
			if ( null !== $lng ) {
				update_post_meta( $post_id, self::LNG_META, trim( (string) $lng ) );
			}
		} finally {
			unset( self::$location_writes[ $post_id ] );
		}
	}

	private static function current_pair( int $post_id ): array {
		return [
			'lat' => (float) get_post_meta( $post_id, self::LAT_META, true ),
			'lng' => (float) get_post_meta( $post_id, self::LNG_META, true ),
		];
	}

	private static function pair_key( array $pair ): string {
		return sprintf( '%.9F|%.9F', (float) ( $pair['lat'] ?? 0 ), (float) ( $pair['lng'] ?? 0 ) );
	}

	private static function location_state_key( array $filter, string $scope, int $post_id ): string {
		$encoded = is_string( wp_json_encode( $filter ) ) ? wp_json_encode( $filter ) : '';

		return $scope . '|' . $encoded . '|' . $post_id;
	}

	private static function intended_pair( int $post_id ): ?array {
		if ( isset( self::$location_writes[ $post_id ] ) && is_array( self::$location_writes[ $post_id ] ) ) {
			return self::$location_writes[ $post_id ];
		}

		return self::posted_pair( $post_id );
	}

	private static function posted_pair( int $post_id ): ?array {
		foreach ( [ 'wpmb_location_nonce', 'post_ID', 'wpmb_lat', 'wpmb_lng' ] as $key ) {
			if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
				return null;
			}
		}
		foreach ( [ 'sanitize_key', 'sanitize_text_field', 'wp_unslash', 'wp_verify_nonce' ] as $function ) {
			if ( ! function_exists( $function ) ) {
				return null;
			}
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified right here, exactly as Location_Meta::save() does.
		if ( (int) $_POST['post_ID'] !== $post_id ) {
			return null;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['wpmb_location_nonce'] ) ), 'wpmb_location' ) ) {
			return null;
		}

		$lat = (float) sanitize_text_field( wp_unslash( $_POST['wpmb_lat'] ) );
		$lng = (float) sanitize_text_field( wp_unslash( $_POST['wpmb_lng'] ) );
		// phpcs:enable

		return ( ! empty( $lat ) && ! empty( $lng ) ) ? [
			'lat' => $lat,
			'lng' => $lng
		] : null;
	}

	private static function id_from( array $config, string $key ): int {
		$value = $config[ $key ] ?? 0;
		if ( is_array( $value ) ) {
			$value = $value['value'] ?? $value['id'] ?? reset( $value );
		}
		if ( is_object( $value ) ) {
			$value = $value->value ?? $value->id ?? 0;
		}
		return is_numeric( $value ) ? max( 0, (int) $value ) : 0;
	}

	private static function provided( array $config, string $key ): bool {
		if ( ! array_key_exists( $key, $config ) ) {
			return false;
		}
		$value = $config[ $key ];
		if ( is_array( $value ) ) {
			return ! empty( $value );
		}
		if ( is_bool( $value ) ) {
			return true;
		}
		if ( is_object( $value ) ) {
			return true;
		}
		return '' !== trim( (string) $value );
	}

	private static function search_of( array $context ): string {
		return isset( $context['search'] ) ? trim( (string) $context['search'] ) : '';
	}

	private static function limit_of( array $context, int $default ): int {
		if ( ! isset( $context['limit'] ) || ! is_numeric( $context['limit'] ) ) {
			return $default;
		}
		return max( 1, min( 500, (int) $context['limit'] ) );
	}

	private static function haversine_km( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
		$earth  = 6371.0088;
		$to_rad = M_PI / 180;

		$d_lat = ( $lat2 - $lat1 ) * $to_rad;
		$d_lng = ( $lng2 - $lng1 ) * $to_rad;
		$a     = sin( $d_lat / 2 ) ** 2
			+ cos( $lat1 * $to_rad ) * cos( $lat2 * $to_rad ) * sin( $d_lng / 2 ) ** 2;

		return $earth * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
	}
}
