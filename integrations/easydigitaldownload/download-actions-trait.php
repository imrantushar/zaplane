<?php
namespace Zaplane\Integrations\Easydigitaldownload;

trait DownloadActionsTrait {

	protected static function action_create_download( array $config, array $input ): array {
		if ( ! class_exists( 'EDD_Download' ) ) {
			return self::action_error( 'Easy Digital Downloads is not available', $input );
		}

		$name = trim( $config['name'] ?? '' );
		if ( $name === '' ) {
			return self::action_error( 'Product name is required', $input );
		}

		$data = [
			'post_title' => $name,
			'post_content' => $config['description'] ?? '',
			'post_status' => $config['status'] ?? 'draft',
			'post_type' => 'download',
		];

		$download = new \EDD_Download( 0 );
		$created = $download->create( $data );

		if ( is_wp_error( $created ) ) {
			return self::action_error( $created->get_error_message(), $input );
		}

		$download_id = (int) ( $download->ID ?? 0 );
		if ( ! $download_id ) {
			return self::action_error( 'Failed to create product', $input );
		}

		if ( isset( $config['price'] ) && $config['price'] !== '' ) {
			update_post_meta( $download_id, 'edd_price', $config['price'] );
		}

		return self::action_success(array_merge($input, [
			'download_id' => $download_id,
			'download_name' => $name,
		]));
	}

	protected static function action_update_download( array $config, array $input ): array {
		if ( ! function_exists( 'edd_get_download' ) ) {
			return self::action_error( 'Easy Digital Downloads is not available', $input );
		}

		$download_id = (int) ( $config['download_id'] ?? 0 );
		if ( ! $download_id ) {
			return self::action_error( 'Product ID is required', $input );
		}

		$download = edd_get_download( $download_id );
		if ( ! $download ) {
			return self::action_error( 'Product not found', $input );
		}

		$update = [ 'ID' => $download_id ];
		if ( ! empty( $config['name'] ) ) {
			$update['post_title'] = $config['name'];
		}
		if ( array_key_exists( 'description', $config ) ) {
			$update['post_content'] = $config['description'];
		}
		if ( ! empty( $config['status'] ) ) {
			$update['post_status'] = $config['status'];
		}

		if ( count( $update ) > 1 ) {
			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				return self::action_error( $result->get_error_message(), $input );
			}
		}

		if ( isset( $config['price'] ) && $config['price'] !== '' ) {
			update_post_meta( $download_id, 'edd_price', $config['price'] );
		}

		return self::action_success(array_merge($input, [
			'download_id' => $download_id,
		]));
	}

	protected static function action_delete_download( array $config, array $input ): array {
		$download_id = (int) ( $config['download_id'] ?? 0 );
		if ( ! $download_id ) {
			return self::action_error( 'Product ID is required', $input );
		}

		$post = get_post( $download_id );
		if ( ! $post || $post->post_type !== 'download' ) {
			return self::action_error( 'Product not found', $input );
		}

		$force = ! empty( $config['force_delete'] ) && $config['force_delete'] !== '0';
		$deleted = wp_delete_post( $download_id, $force );

		if ( ! $deleted ) {
			return self::action_error( 'Failed to delete product', $input );
		}

		return self::action_success(array_merge($input, [
			'download_id' => $download_id,
			'deleted' => true,
		]));
	}
}
