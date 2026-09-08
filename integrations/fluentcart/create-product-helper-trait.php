<?php
namespace Zaplane\Integrations\Fluentcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CreateProductHelperTrait {
	private static function create_product_schema_fields(): array {
		return [
			[
				'key'      => 'post_title',
				'label'    => 'Product Title',
				'type'     => 'text',
				'required' => true,
			],
			[
				'key'   => 'post_name',
				'label' => 'Product Slug',
				'type'  => 'text',
			],
			[
				'key'   => 'post_content',
				'label' => 'Product Description',
				'type'  => 'textarea',
			],
			[
				'key'   => 'post_excerpt',
				'label' => 'Short Description',
				'type'  => 'textarea',
			],
			[
				'key'      => 'post_status',
				'label'    => 'Post Status',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'fluentcart',
					'query'       => 'post_statuses',
					'select'      => [ 'name', 'label' ],
				],
				'default'  => 'draft',
				'required' => true,
			],
			[
				'key'   => 'price',
				'label' => 'Price',
				'type'  => 'number',
				'step'  => '0.01',
				'required' => true,
			],
			[
				'key'   => 'compare_price',
				'label' => 'Compare Price',
				'type'  => 'number',
				'step'  => '0.01',
			],
			[
				'key'     => 'product_categories',
				'label'   => 'Product Categories',
				'type'    => 'select',
				'dynamic' => [
					'integration' => 'fluentcart',
					'query'       => 'categories',
					'select'      => [ 'id', 'label' ],
				],
			],
			[
				'key'     => 'product_brands',
				'label'   => 'Product Brands',
				'type'    => 'select',
				'dynamic' => [
					'integration' => 'fluentcart',
					'query'       => 'brands',
					'select'      => [ 'id', 'label' ],
				],
			],
			[
				'key'      => 'fulfillment_type',
				'label'    => 'Fulfillment Type',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'fluentcart',
					'query'       => 'fulfillment_types',
					'select'      => [ 'name', 'label' ],
				],
				'default'  => 'physical',
				'required' => true,
			],
			[
				'key'      => 'stock_status',
				'label'    => 'Stock Status',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'fluentcart',
					'query'       => 'stock_statuses',
					'select'      => [ 'name', 'label' ],
				],
				'default'  => 'in-stock',
				'required' => true,
			],
			[
				'key'      => 'payment_type',
				'label'    => 'Payment Type',
				'type'     => 'select',
				'dynamic'  => [
					'integration' => 'fluentcart',
					'query'       => 'payment_types',
					'select'      => [ 'name', 'label' ],
				],
				'default'  => 'onetime',
				'required' => false,
			],
			[
				'key'     => 'total_stock',
				'label'   => 'Total Stock',
				'type'    => 'number',
				'default' => 1,
			],
			[
				'key'     => 'shipping_class',
				'label'   => 'Shipping Class',
				'type'    => 'select',
				'dynamic' => [
					'integration' => 'fluentcart',
					'query'       => 'shipping_classes',
					'select'      => [ 'id', 'label' ],
				],
			],
			[
				'key'         => 'gallery_image_ids',
				'label'       => 'Gallery Image IDs',
				'type'        => 'text',
				'placeholder' => '1, 2, 3',
			],
			[
				'key'   => 'featured_media_id',
				'label' => 'Featured Media ID',
				'type'  => 'number',
			],
		];
	}

	private static function sanitize_select_config_value( array $config, string $key, string $default ): string {
		$value = sanitize_key( (string) ( $config[ $key ] ?? $default ) );
		if ( '' === $value || 'any' === $value ) {
			return $default;
		}

		return $value;
	}

	private static function build_create_product_post_args( array $config, string $post_title ): array {
		$post_name = sanitize_title( (string) ( $config['post_name'] ?? '' ) );
		if ( '' === $post_name ) {
			$post_name = sanitize_title( $post_title );
		}

		return [
			'post_title'   => $post_title,
			'post_content' => trim( (string) ( $config['post_content'] ?? '' ) ),
			'post_excerpt' => trim( (string) ( $config['post_excerpt'] ?? '' ) ),
			'post_status'  => self::sanitize_select_config_value( $config, 'post_status', 'draft' ),
			'post_type'    => 'fluent-products',
			'post_name'    => $post_name,
		];
	}

	private static function build_create_product_meta_args( array $config ): array {
		return [
			'fulfillment_type' => self::sanitize_select_config_value( $config, 'fulfillment_type', 'physical' ),
			'stock_status'     => self::sanitize_select_config_value( $config, 'stock_status', 'in-stock' ),
			'payment_type'     => self::sanitize_select_config_value( $config, 'payment_type', 'onetime' ),
			'total_stock'      => max( 0, (int) ( $config['total_stock'] ?? 1 ) ),
			'price'            => isset( $config['price'] ) && '' !== $config['price'] ? (float) $config['price'] : null,
			'compare_price'    => isset( $config['compare_price'] ) && '' !== $config['compare_price'] ? (float) $config['compare_price'] : null,
			'shipping_class'   => self::parse_positive_int( $config['shipping_class'] ?? 0 ),
		];
	}

	private static function build_create_product_taxonomy_args( array $config ): array {
		return [
			'categories' => self::parse_term_id_list( $config['product_categories'] ?? null ),
			'brands'     => self::parse_term_id_list( $config['product_brands'] ?? null ),
		];
	}

	private static function build_create_product_media_args( array $config ): array {
		return [
			'gallery_image_ids' => self::parse_term_id_list( $config['gallery_image_ids'] ?? null ),
			'featured_media_id' => self::parse_positive_int( $config['featured_media_id'] ?? 0 ),
		];
	}

	private static function parse_term_id_list( $value ): array {
		if ( null === $value || '' === $value ) {
			return [];
		}

		if ( is_string( $value ) ) {
			$value = array_map( 'trim', explode( ',', $value ) );
		}

		if ( ! is_array( $value ) ) {
			$value = [ $value ];
		}

		$ids = array_map( [ self::class, 'parse_positive_int' ], $value );

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	private static function apply_product_taxonomies_and_media( int $product_id, array $config ): void {
		if ( $product_id <= 0 ) {
			return;
		}

		$taxonomy_args = self::build_create_product_taxonomy_args( $config );
		$media_args    = self::build_create_product_media_args( $config );

		if ( ! empty( $taxonomy_args['categories'] ) && taxonomy_exists( self::PRODUCT_CATEGORY_TAXONOMY ) ) {
			wp_set_object_terms( $product_id, $taxonomy_args['categories'], self::PRODUCT_CATEGORY_TAXONOMY );
		}

		if ( ! empty( $taxonomy_args['brands'] ) && taxonomy_exists( self::PRODUCT_BRAND_TAXONOMY ) ) {
			wp_set_object_terms( $product_id, $taxonomy_args['brands'], self::PRODUCT_BRAND_TAXONOMY );
		}

		$shipping_class = self::parse_positive_int( $config['shipping_class'] ?? 0 );
		if ( $shipping_class > 0 && taxonomy_exists( self::PRODUCT_SHIPPING_CLASS_TAXONOMY ) ) {
			wp_set_object_terms( $product_id, [ $shipping_class ], self::PRODUCT_SHIPPING_CLASS_TAXONOMY );
		}

		if ( ! empty( $media_args['gallery_image_ids'] ) ) {
			update_post_meta( $product_id, self::PRODUCT_GALLERY_META_KEY, $media_args['gallery_image_ids'] );
		}

		if ( $media_args['featured_media_id'] > 0 ) {
			set_post_thumbnail( $product_id, $media_args['featured_media_id'] );
		}
	}
}
