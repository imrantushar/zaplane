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
		}

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

	private static function is_location_enabled( string $post_type ): bool {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}
		$settings = get_option( 'wpmb_settings', [] );
		$enabled  = is_array( $settings ) && isset( $settings['locations'] ) ? (array) $settings['locations'] : [];

		return empty( $enabled ) ? false : in_array( $post_type, array_map( 'strval', $enabled ), true );
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
}
