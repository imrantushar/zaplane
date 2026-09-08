<?php
namespace Zaplane\Integrations\Surecart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ProductActionsTrait {

	protected static function action_create_product_manual( array $config, array $input ): array {
		$error = self::ensure_surecart();
		if ( null !== $error ) {
			return $error;
		}

		$product_data = self::build_product_data_from_manual( $config );
		if ( empty( $product_data['name'] ) ) {
			return self::error( 'Product name is required', [ 'field' => 'name' ] );
		}

		$price_data = self::build_price_data_from_manual( $config );
		if ( ! array_key_exists( 'amount', $price_data ) || empty( $price_data['currency'] ) ) {
			return self::error( 'Price amount and currency are required', [ 'field' => 'price_amount' ] );
		}

		$product = \SureCart\Models\Product::create( $product_data );

		if ( is_wp_error( $product ) ) {
			return self::error( $product->get_error_message(), [ 'code' => $product->get_error_code() ] );
		}
		if ( false === $product || empty( $product->id ) ) {
			return self::error( 'Failed to create product' );
		}

		$price_data['product'] = $product->id;

		$price = \SureCart\Models\Price::create( $price_data );

		if ( is_wp_error( $price ) ) {
			return self::error(
				'Product created, but price failed: ' . $price->get_error_message(),
				[
					'code'    => $price->get_error_code(),
					'details' => $price->get_error_data(),
					'product' => self::model_to_array( $product ),
				]
			);
		}
		if ( false === $price || empty( $price->id ) ) {
			return self::error( 'Product created, but price creation failed', [ 'product' => self::model_to_array( $product ) ] );
		}

		return self::respond( [
			'product' => self::model_to_array( $product ),
			'price'   => self::model_to_array( $price ),
		] );
	}

	protected static function action_update_product( array $config, array $input ): array {
		$data = [];

		foreach ( [ 'name', 'description', 'status' ] as $field ) {
			if ( isset( $config[ $field ] ) && '' !== $config[ $field ] ) {
				$data[ $field ] = $config[ $field ];
			}
		}

		$extra = self::parse_json_array( $config['data'] ?? [] );
		if ( ! empty( $extra ) ) {
			$data = array_merge( $data, $extra );
		}

		return self::update_model( \SureCart\Models\Product::class, $config, 'product_id', $data, 'product' );
	}

	protected static function action_delete_product( array $config, array $input ): array {
		return self::delete_model( \SureCart\Models\Product::class, $config, 'product_id' );
	}

	protected static function action_get_products_all( array $config, array $input ): array {
		return self::list_models( \SureCart\Models\Product::class, $config, 'products' );
	}

	protected static function action_get_product_single( array $config, array $input ): array {
		return self::get_model_single( \SureCart\Models\Product::class, $config, 'product_id', 'product' );
	}
}
