<?php
namespace Zaplane\Integrations\Wordpress;

use Zaplane\Traits\ActionResponseTrait;

trait TaxonomyActionsTrait {

	private static function get_taxonomy_from_config( array $config ): string {
		return (string) ( $config['taxonomy'] ?? '' );
	}

	private static function get_term_id_from_config( array $config ): int {
		return (int) ( $config['term_id'] ?? 0 );
	}

	private static function get_category_id_from_config( array $config ): int {
		return (int) ( $config['category_id'] ?? 0 );
	}

	private static function normalize_term_write_args( array $config ): array {
		return [
			'slug' => $config['slug'] ?? '',
			'parent' => $config['parent'] ?? 0,
			'description' => $config['description'] ?? '',
		];
	}

	private static function normalize_category_write_args( array $config, int $category_id = 0 ): array {
		$args = [
			'cat_name' => (string) ( $config['name'] ?? '' ),
			'category_nicename' => (string) ( $config['slug'] ?? '' ),
			'category_parent' => (int) ( $config['parent'] ?? 0 ),
			'category_description' => (string) ( $config['description'] ?? '' ),
		];

		if ( $category_id > 0 ) {
			$args['cat_ID'] = $category_id;
		}

		return $args;
	}

	private static function normalize_term_payload( $term ): array {
		if ( ! ( $term instanceof \WP_Term ) ) {
			return [];
		}

		return self::get_term_payload( $term->term_id, $term->taxonomy, $term->term_taxonomy_id, [], $term );
	}

	private static function normalize_taxonomy_payload( $taxonomy ): array {
		if ( ! $taxonomy || ! is_object( $taxonomy ) ) {
			return [];
		}

		return [
			'name' => $taxonomy->name ?? '',
			'label' => $taxonomy->label ?? '',
			'object_type' => $taxonomy->object_type ?? [],
			'hierarchical' => (bool) ( $taxonomy->hierarchical ?? false ),
			'public' => (bool) ( $taxonomy->public ?? false ),
			'show_ui' => (bool) ( $taxonomy->show_ui ?? false ),
			'show_in_rest' => (bool) ( $taxonomy->show_in_rest ?? false ),
		];
	}

	protected static function action_get_term( array $config ): array {
		$term = get_term( self::get_term_id_from_config( $config ), self::get_taxonomy_from_config( $config ) );
		if ( ! $term || is_wp_error( $term ) ) {
			return static::error( 'Term not found' );
		}

		return static::success( [ 'term' => self::normalize_term_payload( $term ) ] );
	}

	protected static function action_get_terms_by_taxonomy( array $config ): array {
		$terms = get_terms([
			'taxonomy' => self::get_taxonomy_from_config( $config ),
			'hide_empty' => ! empty( $config['hide_empty'] ),
			'search' => (string) ( $config['search'] ?? '' ),
			'number' => ! empty( $config['limit'] ) ? (int) $config['limit'] : 20,
		]);
		if ( is_wp_error( $terms ) ) {
			return static::error( $terms->get_error_message() );
		}

		return static::success( [
			'count' => count( $terms ),
			'items' => array_map( [ self::class, 'normalize_term_payload' ], $terms ),
		] );
	}

	protected static function action_get_term_by_field( array $config ): array {
		$term = get_term_by(
			$config['field'] ?? '',
			$config['value'] ?? '',
			self::get_taxonomy_from_config( $config )
		);
		if ( ! $term || is_wp_error( $term ) ) {
			return static::error( 'Term not found' );
		}

		return static::success( [ 'term' => self::normalize_term_payload( $term ) ] );
	}

	protected static function action_create_term( array $config ): array {
		$result = wp_insert_term(
			$config['name'] ?? '',
			self::get_taxonomy_from_config( $config ),
			self::normalize_term_write_args( $config )
		);
		if ( is_wp_error( $result ) ) {
			return static::error( $result->get_error_message() );
		}
		$term_id = (int) ( $result['term_id'] ?? 0 );
		$taxonomy = self::get_taxonomy_from_config( $config );
		$term = get_term( $term_id, $taxonomy );

		return static::success( [
			'term_id' => $term_id,
			'term_taxonomy_id' => (int) ( $result['term_taxonomy_id'] ?? 0 ),
			'term' => self::normalize_term_payload( $term ),
		] );
	}

	protected static function action_update_term( array $config ): array {
		$result = wp_update_term(
			self::get_term_id_from_config( $config ),
			self::get_taxonomy_from_config( $config ),
			array_merge(
				[
					'name' => $config['name'] ?? '',
				],
				self::normalize_term_write_args( $config )
			)
		);
		if ( is_wp_error( $result ) ) {
			return static::error( $result->get_error_message() );
		}
		$term_id = (int) ( $result['term_id'] ?? self::get_term_id_from_config( $config ) );
		$taxonomy = self::get_taxonomy_from_config( $config );
		$term = get_term( $term_id, $taxonomy );

		return static::success( [
			'term_id' => $term_id,
			'term_taxonomy_id' => (int) ( $result['term_taxonomy_id'] ?? 0 ),
			'term' => self::normalize_term_payload( $term ),
		] );
	}

	protected static function action_delete_term( array $config ): array {
		$term_id = self::get_term_id_from_config( $config );
		$taxonomy = self::get_taxonomy_from_config( $config );
		$result = wp_delete_term( $term_id, $taxonomy );
		if ( is_wp_error( $result ) ) {
			return static::error( $result->get_error_message() );
		}
		if ( ! $result ) {
			return static::error( 'Failed to delete term' );
		}
		return static::success( [
			'deleted' => true,
			'term_id' => $term_id
		] );
	}

	protected static function action_register_taxonomy( array $config ): array {
		$taxonomy = register_taxonomy(
			self::get_taxonomy_from_config( $config ),
			self::normalize_list( $config['object_type'] ?? [] ),
			self::normalize_taxonomy_args( $config['args'] ?? [] )
		);
		if ( is_wp_error( $taxonomy ) ) {
			return static::error( $taxonomy->get_error_message() );
		}
		return static::success( [ 'taxonomy' => self::normalize_taxonomy_payload( $taxonomy ) ] );
	}

	protected static function action_unregister_taxonomy( array $config ): array {
		$removed = unregister_taxonomy( self::get_taxonomy_from_config( $config ) );
		return static::success( [ 'removed' => (bool) $removed ] );
	}

	protected static function action_get_taxonomies( array $config ): array {
		$items = get_taxonomies( [], 'objects' );
		$search = strtolower( trim( (string) ( $config['search'] ?? '' ) ) );
		if ( '' === $search ) {
			return static::success( $items );
		}

		$filtered = [];
		foreach ( $items as $key => $value ) {
			$name = strtolower( (string) ( $value->name ?? $key ) );
			$label = strtolower( (string) ( $value->label ?? '' ) );
			if ( false !== strpos( $name, $search ) || false !== strpos( $label, $search ) ) {
				$filtered[ $key ] = $value;
			}
		}
		return static::success( $filtered );
	}

	protected static function action_get_taxonomy( array $config ): array {
		$taxonomy = get_taxonomy( self::get_taxonomy_from_config( $config ) );
		if ( ! $taxonomy ) {
			return static::error( 'Taxonomy not found' );
		}
		return static::success( [ 'taxonomy' => self::normalize_taxonomy_payload( $taxonomy ) ] );
	}

	protected static function action_create_category( array $config ): array {
		$name = (string) ( $config['name'] ?? '' );
		if ( '' === $name ) {
			return static::error( 'Category name is required' );
		}

		$result = wp_insert_category(
			self::normalize_category_write_args( $config ),
			true
		);

		if ( is_wp_error( $result ) ) {
			return static::error( $result->get_error_message() );
		}

		return static::success( [
			'category_id' => (int) $result,
			'category' => self::normalize_term_payload( get_category( (int) $result ) ),
		] );
	}

	protected static function action_update_category( array $config ): array {
		$category_id = self::get_category_id_from_config( $config );
		if ( ! $category_id ) {
			return static::error( 'Category ID is required' );
		}

		$result = wp_update_category( self::normalize_category_write_args( $config, $category_id ) );

		if ( is_wp_error( $result ) ) {
			return static::error( $result->get_error_message() );
		}

		return static::success( [
			'category_id' => (int) $category_id,
			'category' => self::normalize_term_payload( get_category( $category_id ) ),
		] );
	}

	protected static function action_delete_category( array $config ): array {
		$category_id = self::get_category_id_from_config( $config );
		if ( ! $category_id ) {
			return static::error( 'Category ID is required' );
		}

		$deleted = wp_delete_category( $category_id );
		if ( ! $deleted ) {
			return static::error( 'Failed to delete category' );
		}

		return static::success( [
			'category_id' => $category_id,
			'deleted' => true
		] );
	}

	protected static function action_get_categories( array $config ): array {
		$args = [
			'hide_empty' => ! empty( $config['hide_empty'] ),
		];
		if ( ! empty( $config['search'] ) ) {
			$args['search'] = (string) $config['search'];
		}
		if ( ! empty( $config['limit'] ) ) {
			$args['number'] = (int) $config['limit'];
		}

		$items = get_categories( $args );
		return static::success( [
			'count' => is_array( $items ) ? count( $items ) : 0,
			'items' => is_array( $items ) ? array_map( [ self::class, 'normalize_term_payload' ], $items ) : [],
		] );
	}

	protected static function action_get_category( array $config ): array {
		$category_id = self::get_category_id_from_config( $config );
		if ( ! $category_id ) {
			return static::error( 'Category ID is required' );
		}

		$category = get_category( $category_id );
		if ( ! $category || is_wp_error( $category ) ) {
			return static::error( 'Category not found' );
		}

		return static::success( [ 'category' => self::normalize_term_payload( $category ) ] );
	}
}
