<?php
namespace Zaplane\Integrations\Fluentcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ProductActionsTrait {

	private static function action_get_product_single( array $config, array $input ): array {
		$product_id = self::resolve_entity_id_for_action( $config, $input, 'product_id', [ 'product' ] );
		if ( $product_id <= 0 ) {
			return self::error_response( 'Product ID is required', $input );
		}

		$product = self::find_model_by_id( self::product_model_class(), $product_id );
		if ( ! $product ) {
			return self::error_response( 'Product not found', $input );
		}

		return self::main_response(
			array_merge(
				$input,
				[
					'product' => self::normalize_payload_value( $product ),
				]
			)
		);
	}

	private static function action_get_products_all( array $config, array $input ): array {
		$limit       = max( 1, (int) ( $config['limit'] ?? 20 ) );
		$page        = max( 1, (int) ( $config['page'] ?? 1 ) );
		$search      = trim( (string) ( $config['search'] ?? '' ) );
		$post_status = self::sanitize_status( $config['post_status'] ?? '' );
		$items       = self::list_products( $limit, $page, $search, $post_status );

		return self::main_response(
			array_merge(
				$input,
				[
					'items' => $items,
					'total' => count( $items ),
					'limit' => $limit,
					'page'  => $page,
				]
			)
		);
	}

	private static function action_create_product( array $config, array $input ): array {
		$post_title = trim( (string) ( $config['post_title'] ?? '' ) );
		if ( '' === $post_title ) {
			return self::error_response( 'Product title is required', $input );
		}

		$post_id = wp_insert_post(
			self::build_create_product_post_args( $config, $post_title ),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return self::error_response( $post_id->get_error_message(), $input );
		}

		$product_id = self::parse_positive_int( $post_id );
		if ( $product_id <= 0 ) {
			return self::error_response( 'Product creation failed', $input );
		}

		$meta_args = self::build_create_product_meta_args( $config );

		self::bootstrap_created_product_meta(
			$product_id,
			$post_title,
			$meta_args['fulfillment_type'],
			$meta_args['stock_status'],
			$meta_args['payment_type'],
			$meta_args['total_stock'],
			$meta_args['price'],
			$meta_args['compare_price']
		);

		self::apply_product_taxonomies_and_media( $product_id, $config );

		return self::main_response(
			array_merge(
				$input,
				[
					'product_id' => $product_id,
					'product'    => self::load_product_payload( $product_id ),
					'created'    => true,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function action_update_product( array $config, array $input ): array {
		$product_id = self::resolve_entity_id_for_action( $config, $input, 'product_id', [ 'product' ] );
		if ( $product_id <= 0 ) {
			return self::error_response( 'Product ID is required', $input );
		}

		$post = get_post( $product_id );
		if ( ! $post || 'fluent-products' !== (string) ( $post->post_type ?? '' ) ) {
			return self::error_response( 'Product not found', $input );
		}

		$update_data = [ 'ID' => $product_id ];
		$post_title  = trim( (string) ( $config['post_title'] ?? '' ) );
		$post_status = sanitize_key( (string) ( $config['post_status'] ?? '' ) );

		if ( '' !== $post_title ) {
			$update_data['post_title'] = $post_title;
			$update_data['post_name']  = sanitize_title( $post_title );
		}

		if ( '' !== $post_status && 'any' !== $post_status ) {
			$update_data['post_status'] = $post_status;
		}

		if ( 1 === count( $update_data ) ) {
			return self::error_response( 'At least one field is required to update', $input );
		}

		$updated = wp_update_post( $update_data, true );
		if ( is_wp_error( $updated ) ) {
			return self::error_response( $updated->get_error_message(), $input );
		}
		if ( self::parse_positive_int( $updated ) <= 0 ) {
			return self::error_response( 'Product update failed', $input );
		}

		self::apply_product_variation_updates( $product_id, $config );
		self::apply_product_taxonomies_and_media( $product_id, $config );

		return self::main_response(
			array_merge(
				$input,
				[
					'product_id' => $product_id,
					'product'    => self::load_product_payload( $product_id ),
					'updated'    => true,
					'event_time' => current_time( 'mysql' ),
				]
			)
		);
	}

	private static function apply_product_variation_updates( int $product_id, array $config ): void {
		$variation_class = '\\FluentCart\\App\\Models\\ProductVariation';
		if ( $product_id <= 0 || ! class_exists( $variation_class ) || ! method_exists( $variation_class, 'query' ) ) {
			return;
		}

		$data = [];

		if ( isset( $config['price'] ) && '' !== $config['price'] ) {
			$data['item_price'] = (float) $config['price'];
		}
		if ( isset( $config['compare_price'] ) && '' !== $config['compare_price'] ) {
			$data['compare_price'] = (float) $config['compare_price'];
		}
		if ( isset( $config['total_stock'] ) && '' !== $config['total_stock'] ) {
			$total_stock          = max( 0, (int) $config['total_stock'] );
			$data['total_stock']  = $total_stock;
			$data['available']    = $total_stock > 0 ? 1 : 0;
		}
		$stock_status = self::sanitize_select_config_value( $config, 'stock_status', '' );
		if ( '' !== $stock_status ) {
			$data['stock_status'] = $stock_status;
		}
		$payment_type = self::sanitize_select_config_value( $config, 'payment_type', '' );
		if ( '' !== $payment_type ) {
			$data['payment_type'] = $payment_type;
		}
		$fulfillment_type = self::sanitize_select_config_value( $config, 'fulfillment_type', '' );
		if ( '' !== $fulfillment_type ) {
			$data['fulfillment_type'] = $fulfillment_type;
		}

		if ( empty( $data ) ) {
			return;
		}

		try {
			$variation_class::query()->where( 'post_id', $product_id )->update( $data );
		} catch ( \Throwable $e ) {
			unset( $e );
		}
	}

	private static function action_delete_product( array $config, array $input ): array {
		$product_id = self::resolve_entity_id_for_action( $config, $input, 'product_id', [ 'product' ] );
		if ( $product_id <= 0 ) {
			return self::error_response( 'Product ID is required', $input );
		}

		if ( ! wp_delete_post( $product_id, true ) ) {
			return self::error_response( 'Product deletion failed', $input );
		}

		return self::main_response(
			array_merge( $input, [ 'product_id' => $product_id, 'deleted' => true, 'event_time' => current_time( 'mysql' ) ] )
		);
	}

	private static function action_get_product_variants( array $config, array $input ): array {
		return self::relation_list_response( $config, $input, 'product_id', [ 'product' ], self::product_model_class(), 'variations', 'items' );
	}
}
