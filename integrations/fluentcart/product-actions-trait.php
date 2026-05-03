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
			$meta_args['total_stock']
		);

		return self::main_response(
			array_merge(
				$input,
				[
					'product_id'  => $product_id,
					'product'     => self::load_product_payload( $product_id ),
					'created'     => true,
					'event_time'  => current_time( 'mysql' ),
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
}
