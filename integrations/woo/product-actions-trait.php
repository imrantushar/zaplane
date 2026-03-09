<?php
namespace Zaplane\Integrations\Woo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ProductActionsTrait {

	private static function action_create_product( array $config, array $input ): array {
		$name = $config['name'] ?? '';
		if ( $name === '' ) {
			return self::error( 'Product name is required' );
		}

		$product = new \WC_Product_Simple();
		$product->set_name( $name );

		if ( ! empty( $config['status'] ) ) {
			$product->set_status( $config['status'] );
		}
		if ( ! empty( $config['sku'] ) ) {
			$product->set_sku( $config['sku'] );
		}
		if ( isset( $config['regular_price'] ) ) {
			$product->set_regular_price( $config['regular_price'] );
		}
		if ( isset( $config['sale_price'] ) ) {
			$product->set_sale_price( $config['sale_price'] );
		}
		if ( isset( $config['price'] ) ) {
			$product->set_price( $config['price'] );
		}
		if ( ! empty( $config['description'] ) ) {
			$product->set_description( $config['description'] );
		}
		if ( ! empty( $config['short_description'] ) ) {
			$product->set_short_description( $config['short_description'] );
		}
		if ( isset( $config['stock_quantity'] ) ) {
			$product->set_stock_quantity( $config['stock_quantity'] );
		}
		if ( isset( $config['manage_stock'] ) ) {
			$product->set_manage_stock( self::parse_bool( $config['manage_stock'] ) );
		}
		if ( ! empty( $config['stock_status'] ) ) {
			$product->set_stock_status( $config['stock_status'] );
		}

		$product_id = $product->save();
		if ( ! $product_id ) {
			return self::error( 'Failed to create product' );
		}

		$category_ids = self::parse_list( $config['category_ids'] ?? [] );
		if ( ! empty( $category_ids ) ) {
			wp_set_object_terms( $product_id, $category_ids, 'product_cat' );
		}

		$tag_ids = self::parse_list( $config['tag_ids'] ?? [] );
		if ( ! empty( $tag_ids ) ) {
			wp_set_object_terms( $product_id, $tag_ids, 'product_tag' );
		}

		$created = wc_get_product( $product_id );

		return self::respond([
			'product' => $created ? self::build_product_payload( $created ) : [ 'product_id' => $product_id ],
		]);
	}

	private static function action_create_product_variation( array $config, array $input ): array {
		$parent_id = $config['parent_id'] ?? 0;
		if ( ! $parent_id ) {
			return self::error( 'Parent product ID is required' );
		}
		$attributes = self::parse_json_array( $config['attributes'] ?? [] );
		if ( empty( $attributes ) ) {
			return self::error( 'Attributes are required' );
		}

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent_id );
		$variation->set_attributes( $attributes );

		if ( ! empty( $config['sku'] ) ) {
			$variation->set_sku( $config['sku'] );
		}
		if ( isset( $config['regular_price'] ) ) {
			$variation->set_regular_price( $config['regular_price'] );
		}
		if ( isset( $config['sale_price'] ) ) {
			$variation->set_sale_price( $config['sale_price'] );
		}
		if ( isset( $config['stock_quantity'] ) ) {
			$variation->set_stock_quantity( $config['stock_quantity'] );
		}
		if ( isset( $config['manage_stock'] ) ) {
			$variation->set_manage_stock( self::parse_bool( $config['manage_stock'] ) );
		}
		if ( ! empty( $config['status'] ) ) {
			$variation->set_status( $config['status'] );
		}

		$variation_id = $variation->save();
		if ( ! $variation_id ) {
			return self::error( 'Failed to create variation' );
		}

		$created = wc_get_product( $variation_id );
		return self::respond([
			'product' => $created ? self::build_product_payload( $created ) : [ 'product_id' => $variation_id ],
		]);
	}

	private static function action_update_product( array $config, array $input ): array {
		$product_id = $config['product_id'] ?? 0;
		if ( ! $product_id ) {
			return self::error( 'Product ID is required' );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return self::error( 'Product not found', [ 'product_id' => $product_id ] );
		}

		$data = self::parse_json_array( $config['data'] ?? [] );
		if ( ! empty( $data ) && method_exists( $product, 'set_props' ) ) {
			$product->set_props( $data );
		}

		if ( ! empty( $config['name'] ) ) {
			$product->set_name( $config['name'] );
		}
		if ( ! empty( $config['status'] ) ) {
			$product->set_status( $config['status'] );
		}
		if ( ! empty( $config['sku'] ) ) {
			$product->set_sku( $config['sku'] );
		}
		if ( isset( $config['regular_price'] ) ) {
			$product->set_regular_price( $config['regular_price'] );
		}
		if ( isset( $config['sale_price'] ) ) {
			$product->set_sale_price( $config['sale_price'] );
		}
		if ( isset( $config['price'] ) ) {
			$product->set_price( $config['price'] );
		}
		if ( isset( $config['stock_quantity'] ) ) {
			$product->set_stock_quantity( $config['stock_quantity'] );
		}
		if ( isset( $config['manage_stock'] ) ) {
			$product->set_manage_stock( self::parse_bool( $config['manage_stock'] ) );
		}
		if ( ! empty( $config['stock_status'] ) ) {
			$product->set_stock_status( $config['stock_status'] );
		}
		if ( ! empty( $config['description'] ) ) {
			$product->set_description( $config['description'] );
		}
		if ( ! empty( $config['short_description'] ) ) {
			$product->set_short_description( $config['short_description'] );
		}

		$product->save();

		$category_ids = self::parse_list( $config['category_ids'] ?? [] );
		if ( ! empty( $category_ids ) ) {
			wp_set_object_terms( $product_id, $category_ids, 'product_cat' );
		}
		$tag_ids = self::parse_list( $config['tag_ids'] ?? [] );
		if ( ! empty( $tag_ids ) ) {
			wp_set_object_terms( $product_id, $tag_ids, 'product_tag' );
		}

		return self::respond([
			'product' => self::build_product_payload( $product ),
		]);
	}

	private static function action_get_products_all( array $config, array $input ): array {
		$pagination = self::get_pagination_args( $config );
		$result = self::query_products([
			'limit' => $pagination['limit'],
			'page' => $pagination['page'],
			'paginate' => true,
		]);

		$items = array_map(function ( $product ) {
			return self::build_product_payload( $product );
		}, $result['items']);
		return self::respond( [
			'count' => $result['total'],
			'items' => $items
		] );
	}

	private static function action_get_products_by_category( array $config, array $input ): array {
		$pagination = self::get_pagination_args( $config );
		$category_slug = $config['category_slug'] ?? '';
		if ( ! $category_slug && ! empty( $config['category_id'] ) ) {
			$term = get_term( $config['category_id'], 'product_cat' );
			$category_slug = $term instanceof \WP_Term ? $term->slug : '';
		}
		if ( ! $category_slug ) {
			return self::error( 'Category ID or slug is required' );
		}

		$result = self::query_products([
			'limit' => $pagination['limit'],
			'page' => $pagination['page'],
			'category' => [ $category_slug ],
			'paginate' => true,
		]);
		$items = array_map(function ( $product ) {
			return self::build_product_payload( $product );
		}, $result['items']);
		return self::respond( [
			'count' => $result['total'],
			'items' => $items
		] );
	}

	private static function action_get_products_simple( array $config, array $input ): array {
		return self::get_products_by_type( 'simple', $config );
	}

	private static function action_get_products_variable( array $config, array $input ): array {
		return self::get_products_by_type( 'variable', $config );
	}

	private static function action_get_products_grouped( array $config, array $input ): array {
		return self::get_products_by_type( 'grouped', $config );
	}

	private static function action_get_products_external( array $config, array $input ): array {
		return self::get_products_by_type( 'external', $config );
	}

	private static function action_get_products_variation( array $config, array $input ): array {
		return self::get_products_by_type( 'variation', $config );
	}

	private static function action_get_products_subscription( array $config, array $input ): array {
		return self::get_products_by_type( 'subscription', $config );
	}

	private static function action_get_product_by_id( array $config, array $input ): array {
		$product_id = $config['product_id'] ?? 0;
		if ( ! $product_id ) {
			return self::error( 'Product ID is required' );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return self::error( 'Product not found', [ 'product_id' => $product_id ] );
		}
		return self::respond( [ 'product' => self::build_product_payload( $product ) ] );
	}

	private static function action_get_product_by_sku( array $config, array $input ): array {
		$sku = $config['sku'] ?? '';
		if ( $sku === '' ) {
			return self::error( 'SKU is required' );
		}
		$product_id = wc_get_product_id_by_sku( $sku );
		if ( ! $product_id ) {
			return self::error( 'Product not found', [ 'sku' => $sku ] );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return self::error( 'Product not found', [ 'sku' => $sku ] );
		}
		return self::respond( [ 'product' => self::build_product_payload( $product ) ] );
	}

	private static function action_update_product_stock( array $config, array $input ): array {
		$product_id = $config['product_id'] ?? 0;
		if ( ! $product_id ) {
			return self::error( 'Product ID is required' );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return self::error( 'Product not found', [ 'product_id' => $product_id ] );
		}
		if ( isset( $config['manage_stock'] ) ) {
			$product->set_manage_stock( self::parse_bool( $config['manage_stock'] ) );
		}
		if ( isset( $config['stock_quantity'] ) ) {
			$product->set_stock_quantity( $config['stock_quantity'] );
		}
		if ( ! empty( $config['stock_status'] ) ) {
			$product->set_stock_status( $config['stock_status'] );
		}
		$product->save();

		return self::respond( [ 'product' => self::build_product_payload( $product ) ] );
	}

	private static function action_delete_product_permanently( array $config, array $input ): array {
		$product_id = $config['product_id'] ?? 0;
		if ( ! $product_id ) {
			return self::error( 'Product ID is required' );
		}
		$result = wp_delete_post( $product_id, true );
		if ( ! $result ) {
			return self::error( 'Failed to delete product', [ 'product_id' => $product_id ] );
		}
		return self::respond( [ 'product_id' => $product_id ] );
	}

	private static function action_delete_product_soft( array $config, array $input ): array {
		$product_id = $config['product_id'] ?? 0;
		if ( ! $product_id ) {
			return self::error( 'Product ID is required' );
		}
		$result = wp_trash_post( $product_id );
		if ( ! $result ) {
			return self::error( 'Failed to trash product', [ 'product_id' => $product_id ] );
		}
		return self::respond( [ 'product_id' => $product_id ] );
	}

	private static function action_get_products_totals( array $config, array $input ): array {
		$product_counts = wp_count_posts( 'product' );
		$variation_counts = wp_count_posts( 'product_variation' );
		return self::respond([
			'products' => (array) $product_counts,
			'variations' => (array) $variation_counts,
		]);
	}

	private static function action_get_product_sales_count_by_id( array $config, array $input ): array {
		$product_id = $config['product_id'] ?? 0;
		if ( ! $product_id ) {
			return self::error( 'Product ID is required' );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return self::error( 'Product not found', [ 'product_id' => $product_id ] );
		}
		return self::respond([
			'product_id' => $product_id,
			'total_sales' => $product->get_total_sales(),
		]);
	}

	private static function action_update_product_status( array $config, array $input ): array {
		$product_id = $config['product_id'] ?? 0;
		$status = $config['status'] ?? '';
		if ( ! $product_id || $status === '' ) {
			return self::error( 'Product ID and status are required' );
		}
		$result = wp_update_post([
			'ID' => $product_id,
			'post_status' => $status,
		], true);
		if ( is_wp_error( $result ) ) {
			return self::error( $result->get_error_message() );
		}
		$product = wc_get_product( $product_id );
		return self::respond([
			'product' => $product ? self::build_product_payload( $product ) : [ 'product_id' => $product_id ],
		]);
	}

	private static function get_products_by_type( string $type, array $config ): array {
		$pagination = self::get_pagination_args( $config );
		$result = self::query_products([
			'limit' => $pagination['limit'],
			'page' => $pagination['page'],
			'type' => $type,
			'paginate' => true,
		]);
		$items = array_map(function ( $product ) {
			return self::build_product_payload( $product );
		}, $result['items']);
		return self::respond( [
			'count' => $result['total'],
			'items' => $items,
			'type' => $type
		] );
	}
}
