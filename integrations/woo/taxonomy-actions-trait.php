<?php
namespace Zaplane\Integrations\Woo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait TaxonomyActionsTrait {

	private static function action_create_product_category( array $config, array $input ): array {
		return self::create_term_action( 'product_cat', $config );
	}

	private static function action_update_product_category( array $config, array $input ): array {
		return self::update_term_action( 'product_cat', $config );
	}

	private static function action_delete_product_category( array $config, array $input ): array {
		return self::delete_term_action( 'product_cat', $config );
	}

	private static function action_get_product_category_all( array $config, array $input ): array {
		return self::get_terms_action( 'product_cat' );
	}

	private static function action_get_product_category_single( array $config, array $input ): array {
		return self::get_term_action( 'product_cat', $config );
	}

	private static function action_create_product_tag( array $config, array $input ): array {
		return self::create_term_action( 'product_tag', $config );
	}

	private static function action_update_product_tag( array $config, array $input ): array {
		return self::update_term_action( 'product_tag', $config );
	}

	private static function action_delete_product_tag( array $config, array $input ): array {
		return self::delete_term_action( 'product_tag', $config );
	}

	private static function action_get_product_tag_all( array $config, array $input ): array {
		return self::get_terms_action( 'product_tag' );
	}

	private static function action_get_product_tag_single( array $config, array $input ): array {
		return self::get_term_action( 'product_tag', $config );
	}

	private static function action_create_product_type( array $config, array $input ): array {
		return self::create_term_action( 'product_type', $config );
	}

	private static function action_update_product_type( array $config, array $input ): array {
		return self::update_term_action( 'product_type', $config );
	}

	private static function action_delete_product_type( array $config, array $input ): array {
		return self::delete_term_action( 'product_type', $config );
	}

	private static function action_get_product_type_all( array $config, array $input ): array {
		return self::get_terms_action( 'product_type' );
	}

	private static function action_get_product_type_single( array $config, array $input ): array {
		return self::get_term_action( 'product_type', $config );
	}

	private static function action_create_product_brand( array $config, array $input ): array {
		return self::create_term_action( 'product_brand', $config );
	}

	private static function action_update_product_brand( array $config, array $input ): array {
		return self::update_term_action( 'product_brand', $config );
	}

	private static function action_delete_product_brand( array $config, array $input ): array {
		return self::delete_term_action( 'product_brand', $config );
	}

	private static function action_get_product_brand_all( array $config, array $input ): array {
		return self::get_terms_action( 'product_brand' );
	}

	private static function action_get_product_brand_single( array $config, array $input ): array {
		return self::get_term_action( 'product_brand', $config );
	}

	private static function action_create_product_shipping_class( array $config, array $input ): array {
		return self::create_term_action( 'product_shipping_class', $config );
	}

	private static function action_update_product_shipping_class( array $config, array $input ): array {
		return self::update_term_action( 'product_shipping_class', $config );
	}

	private static function action_delete_product_shipping_class( array $config, array $input ): array {
		return self::delete_term_action( 'product_shipping_class', $config );
	}

	private static function action_get_product_shipping_class_all( array $config, array $input ): array {
		return self::get_terms_action( 'product_shipping_class' );
	}

	private static function action_get_product_shipping_class_single( array $config, array $input ): array {
		return self::get_term_action( 'product_shipping_class', $config );
	}

	private static function create_term_action( string $taxonomy, array $config ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return self::error( 'Taxonomy not found', [ 'taxonomy' => $taxonomy ] );
		}
		$name = $config['name'] ?? '';
		if ( $name === '' ) {
			return self::error( 'Name is required' );
		}
		$args = [];
		if ( ! empty( $config['slug'] ) ) {
			$args['slug'] = $config['slug'];
		}
		if ( ! empty( $config['description'] ) ) {
			$args['description'] = $config['description'];
		}
		if ( ! empty( $config['parent'] ) ) {
			$args['parent'] = (int) $config['parent'];
		}
		$result = wp_insert_term( $name, $taxonomy, $args );
		if ( is_wp_error( $result ) ) {
			return self::error( $result->get_error_message() );
		}
		$term = get_term( $result['term_id'], $taxonomy );
		return self::respond([
			'term' => self::build_term_payload( $term ),
		]);
	}

	private static function update_term_action( string $taxonomy, array $config ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return self::error( 'Taxonomy not found', [ 'taxonomy' => $taxonomy ] );
		}
		$term_id = (int) ( $config['term_id'] ?? 0 );
		if ( ! $term_id ) {
			return self::error( 'Term ID is required' );
		}
		$args = [];
		if ( ! empty( $config['name'] ) ) {
			$args['name'] = $config['name'];
		}
		if ( ! empty( $config['slug'] ) ) {
			$args['slug'] = $config['slug'];
		}
		if ( ! empty( $config['description'] ) ) {
			$args['description'] = $config['description'];
		}
		if ( ! empty( $config['parent'] ) ) {
			$args['parent'] = (int) $config['parent'];
		}
		$result = wp_update_term( $term_id, $taxonomy, $args );
		if ( is_wp_error( $result ) ) {
			return self::error( $result->get_error_message() );
		}
		$term = get_term( $term_id, $taxonomy );
		return self::respond([
			'term' => self::build_term_payload( $term ),
		]);
	}

	private static function delete_term_action( string $taxonomy, array $config ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return self::error( 'Taxonomy not found', [ 'taxonomy' => $taxonomy ] );
		}
		$term_id = (int) ( $config['term_id'] ?? 0 );
		if ( ! $term_id ) {
			return self::error( 'Term ID is required' );
		}
		$result = wp_delete_term( $term_id, $taxonomy );
		if ( is_wp_error( $result ) || ! $result ) {
			return self::error( 'Failed to delete term', [ 'term_id' => $term_id ] );
		}
		return self::respond( [ 'term_id' => $term_id ] );
	}

	private static function get_terms_action( string $taxonomy ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return self::error( 'Taxonomy not found', [ 'taxonomy' => $taxonomy ] );
		}
		$terms = get_terms( [
			'taxonomy' => $taxonomy,
			'hide_empty' => false
		] );
		if ( is_wp_error( $terms ) ) {
			return self::error( $terms->get_error_message() );
		}
		$items = array_map(function ( $term ) {
			return self::build_term_payload( $term );
		}, $terms);
		return self::respond( [
			'count' => count( $items ),
			'items' => $items
		] );
	}

	private static function get_term_action( string $taxonomy, array $config ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return self::error( 'Taxonomy not found', [ 'taxonomy' => $taxonomy ] );
		}
		$term_id = (int) ( $config['term_id'] ?? 0 );
		if ( ! $term_id ) {
			return self::error( 'Term ID is required' );
		}
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return self::error( 'Term not found', [ 'term_id' => $term_id ] );
		}
		return self::respond( [ 'term' => self::build_term_payload( $term ) ] );
	}
}
