<?php

namespace Zaplane\Integrations\Wpmapblock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait QueryTrait {

	public static function query_maps( array $context = [] ): array {
		$posts  = get_posts( [
			'post_type'    => self::MAP_POST_TYPE,
			'post_status'  => 'any',
			'numberposts'  => self::limit_of( $context, 50 ),
			'orderby'      => 'title',
			'order'        => 'ASC',
			'suppress_filters' => false,
		] );
		$search = self::search_of( $context );
		$out    = [];

		foreach ( (array) $posts as $post ) {
			if ( ! $post instanceof \WP_Post || self::MAP_POST_TYPE !== $post->post_type ) {
				continue;
			}
			$label = $post->post_title ? $post->post_title : 'Untitled map';
			$label = '#' . $post->ID . ' ' . $label;
			if ( 'publish' !== $post->post_status ) {
				$label .= ' (' . $post->post_status . ')';
			}
			if ( '' !== $search && false === stripos( $label, $search ) ) {
				continue;
			}
			$out[] = [ 'value' => (string) $post->ID, 'label' => $label ];
		}

		return $out;
	}

	public static function query_markers( array $context = [] ): array {
		$map_id = (int) ( $context['map_id'] ?? 0 );
		if ( $map_id <= 0 ) {
			return [];
		}

		$search = self::search_of( $context );
		$limit  = self::limit_of( $context, 200 );
		$out    = [];

		foreach ( self::markers_of( self::read_config( $map_id ) ) as $marker ) {
			$label = sprintf(
				'%s (%s, %s)',
				(string) ( $marker['title'] ?? '' ),
				(string) ( $marker['lat'] ?? 0 ),
				(string) ( $marker['lng'] ?? 0 )
			);
			if ( '' !== $search && false === stripos( $label, $search ) ) {
				continue;
			}
			$out[] = [ 'value' => (string) ( $marker['id'] ?? '' ), 'label' => $label ];
			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}

	public static function query_map_categories( array $context = [] ): array {
		$map_id = (int) ( $context['map_id'] ?? 0 );
		if ( $map_id <= 0 ) {
			return [];
		}

		$out = [];
		foreach ( self::list_of( self::read_config( $map_id ), 'categories' ) as $category ) {
			$name = (string) ( $category['name'] ?? '' );
			$out[] = [
				'value' => (string) ( $category['id'] ?? '' ),
				'label' => '' !== $name ? $name : (string) ( $category['id'] ?? '' ),
			];
		}
		return $out;
	}

	public static function query_geo_post_types( array $context = [] ): array {
		if ( ! function_exists( 'get_post_types' ) ) {
			return [];
		}

		$out = [];
		foreach ( (array) get_post_types( [ 'public' => true ], 'objects' ) as $type ) {
			$name = (string) ( $type->name ?? '' );
			if ( '' === $name || in_array( $name, [ 'attachment', 'revision' ], true ) ) {
				continue;
			}
			$label = (string) ( $type->label ?? $name );
			if ( isset( $type->labels->singular_name ) && $type->labels->singular_name ) {
				$label = (string) $type->labels->singular_name;
			}
			$out[] = [ 'value' => $name, 'label' => $label ];
		}
		return $out;
	}

	public static function query_geo_posts( array $context = [] ): array {
		$only_type = trim( (string) ( $context['post_type'] ?? '' ) );
		$args       = [
			'post_type'      => '' !== $only_type ? $only_type : 'any',
			'post_status'    => 'publish',
			'numberposts'    => self::limit_of( $context, 50 ),
			'meta_key'       => self::LAT_META,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		$search = self::search_of( $context );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$out = [];
		foreach ( (array) get_posts( $args ) as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$out[] = [
				'value' => (string) $post->ID,
				'label' => sprintf(
					'%s (%s, %s)',
					$post->post_title ? $post->post_title : '#' . $post->ID,
					(string) get_post_meta( $post->ID, self::LAT_META, true ),
					(string) get_post_meta( $post->ID, self::LNG_META, true )
				),
			];
		}
		return $out;
	}

	public static function query_listing_sources( array $context = [] ): array {
		$sources = null;

		if ( function_exists( 'wpmb_get_listing_sources' ) ) {
			$sources = wpmb_get_listing_sources();
		} elseif ( class_exists( '\WPMapBlock\Integrations\Manager' ) ) {
			$sources = \WPMapBlock\Integrations\Manager::all();
		}

		if ( ! is_array( $sources ) ) {
			return [];
		}

		$out = [];
		foreach ( $sources as $id => $source ) {
			$source = is_array( $source ) ? $source : [];
			$label  = (string) ( $source['label'] ?? $id );
			$group  = (string) ( $source['group'] ?? '' );
			$out[]  = [
				'value' => (string) $id,
				'label' => '' !== $group ? $label . ' — ' . $group : $label,
			];
		}
		return $out;
	}
}
