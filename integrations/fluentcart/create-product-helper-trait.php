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
				'required' => false,
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
				'required' => false,
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
				'required' => false,
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
		];
	}
}
