<?php
namespace Zaplane\Integrations\Woo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait AttributeActionsTrait {

	private static function action_add_or_update_product_attribute( array $config, array $input ): array {
		$product_id = (int) ( $config['product_id'] ?? 0 );
		$attribute_name = $config['attribute_name'] ?? '';
		if ( ! $product_id || $attribute_name === '' ) {
			return self::error( 'Product ID and attribute name are required' );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return self::error( 'Product not found', [ 'product_id' => $product_id ] );
		}

		$attributes = $product->get_attributes();
		$options = self::parse_list( $config['options'] ?? [] );
		$visible = self::parse_bool( $config['visible'] ?? true, true );
		$variation = self::parse_bool( $config['variation'] ?? false );
		$position = isset( $config['position'] ) ? (int) $config['position'] : count( $attributes );

		$is_taxonomy = self::parse_bool( $config['is_taxonomy'] ?? false );
		$taxonomy = $config['taxonomy'] ?? '';
		if ( ! $taxonomy && str_starts_with( $attribute_name, 'pa_' ) ) {
			$taxonomy = $attribute_name;
			$is_taxonomy = true;
		}

		$attribute = new \WC_Product_Attribute();

		if ( $is_taxonomy && $taxonomy ) {
			$taxonomy = wc_sanitize_taxonomy_name( $taxonomy );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				return self::error( 'Attribute taxonomy not found', [ 'taxonomy' => $taxonomy ] );
			}
			$term_ids = [];
			foreach ( $options as $option ) {
				if ( is_numeric( $option ) ) {
					$term_ids[] = (int) $option;
					continue;
				}
				$term = get_term_by( 'name', $option, $taxonomy );
				if ( ! $term ) {
					$term = get_term_by( 'slug', $option, $taxonomy );
				}
				if ( ! $term ) {
					$created = wp_insert_term( $option, $taxonomy );
					if ( is_wp_error( $created ) ) {
						continue;
					}
					$term_ids[] = (int) $created['term_id'];
				} else {
					$term_ids[] = (int) $term->term_id;
				}
			}
			$attribute->set_id( (int) wc_attribute_taxonomy_id_by_name( $taxonomy ) );
			$attribute->set_name( $taxonomy );
			$attribute->set_options( $term_ids );
			$key = $taxonomy;
		} else {
			$attribute->set_name( $attribute_name );
			$attribute->set_options( $options );
			$key = $attribute_name;
		}//end if

		$attribute->set_visible( $visible );
		$attribute->set_variation( $variation );
		$attribute->set_position( $position );

		$attributes[ $key ] = $attribute;
		$product->set_attributes( $attributes );
		$product->save();

		return self::respond([
			'product' => self::build_product_payload( $product ),
			'attribute' => [
				'name' => $attribute->get_name(),
				'options' => $attribute->get_options(),
				'visible' => $attribute->get_visible(),
				'variation' => $attribute->get_variation(),
			],
		]);
	}

	private static function action_remove_product_attribute( array $config, array $input ): array {
		$product_id = (int) ( $config['product_id'] ?? 0 );
		$attribute_name = $config['attribute_name'] ?? '';
		if ( ! $product_id || $attribute_name === '' ) {
			return self::error( 'Product ID and attribute name are required' );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return self::error( 'Product not found', [ 'product_id' => $product_id ] );
		}

		$attributes = $product->get_attributes();
		foreach ( $attributes as $key => $attribute ) {
			if ( $key === $attribute_name || $attribute->get_name() === $attribute_name ) {
				unset( $attributes[ $key ] );
			}
		}
		$product->set_attributes( $attributes );
		$product->save();

		return self::respond([
			'product' => self::build_product_payload( $product ),
			'removed' => $attribute_name,
		]);
	}

	private static function action_create_attribute( array $config, array $input ): array {
		if ( ! function_exists( 'wc_create_attribute' ) ) {
			return self::error( 'WooCommerce attribute API not available' );
		}
		$name = $config['name'] ?? '';
		if ( $name === '' ) {
			return self::error( 'Attribute name is required' );
		}
		$data = [
			'name' => $name,
			'slug' => $config['slug'] ?? '',
			'type' => $config['type'] ?? 'select',
			'order_by' => $config['order_by'] ?? 'menu_order',
			'has_archives' => self::parse_bool( $config['has_archives'] ?? false ),
		];
		$attribute_id = wc_create_attribute( $data );
		if ( is_wp_error( $attribute_id ) ) {
			return self::error( $attribute_id->get_error_message() );
		}
		return self::respond( [ 'attribute_id' => $attribute_id ] );
	}

	private static function action_update_attribute( array $config, array $input ): array {
		if ( ! function_exists( 'wc_update_attribute' ) ) {
			return self::error( 'WooCommerce attribute API not available' );
		}
		$attribute_id = (int) ( $config['attribute_id'] ?? 0 );
		if ( ! $attribute_id ) {
			return self::error( 'Attribute ID is required' );
		}
		$data = [
			'id' => $attribute_id,
			'name' => $config['name'] ?? '',
			'slug' => $config['slug'] ?? '',
			'type' => $config['type'] ?? '',
			'order_by' => $config['order_by'] ?? '',
			'has_archives' => isset( $config['has_archives'] ) ? self::parse_bool( $config['has_archives'] ) : null,
		];
		$result = wc_update_attribute($attribute_id, array_filter($data, function ( $value ) {
			return $value !== '' && $value !== null;
		}));
		if ( is_wp_error( $result ) ) {
			return self::error( $result->get_error_message() );
		}
		return self::respond( [ 'attribute_id' => $attribute_id ] );
	}

	private static function action_get_attribute( array $config, array $input ): array {
		if ( ! function_exists( 'wc_get_attribute' ) ) {
			return self::error( 'WooCommerce attribute API not available' );
		}
		$attribute_id = (int) ( $config['attribute_id'] ?? 0 );
		if ( ! $attribute_id ) {
			return self::error( 'Attribute ID is required' );
		}
		$attribute = wc_get_attribute( $attribute_id );
		if ( ! $attribute ) {
			return self::error( 'Attribute not found', [ 'attribute_id' => $attribute_id ] );
		}
		return self::respond([
			'attribute' => [
				'id' => $attribute->id ?? $attribute_id,
				'name' => $attribute->name ?? '',
				'slug' => $attribute->slug ?? '',
				'type' => $attribute->type ?? '',
				'order_by' => $attribute->order_by ?? '',
				'has_archives' => $attribute->has_archives ?? false,
			],
		]);
	}

	private static function action_delete_attribute( array $config, array $input ): array {
		if ( ! function_exists( 'wc_delete_attribute' ) ) {
			return self::error( 'WooCommerce attribute API not available' );
		}
		$attribute_id = (int) ( $config['attribute_id'] ?? 0 );
		if ( ! $attribute_id ) {
			return self::error( 'Attribute ID is required' );
		}
		$result = wc_delete_attribute( $attribute_id );
		if ( is_wp_error( $result ) ) {
			return self::error( $result->get_error_message() );
		}
		return self::respond( [ 'attribute_id' => $attribute_id ] );
	}
}
