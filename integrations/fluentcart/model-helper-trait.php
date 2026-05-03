<?php
namespace Zaplane\Integrations\Fluentcart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ModelHelperTrait {
	private static function list_orders( int $limit, int $page, string $search, int $customer_id, string $order_status, string $payment_status ): array {
		$filters = [];
		if ( $customer_id > 0 ) {
			$filters['customer_id'] = $customer_id;
		}
		if ( '' !== $order_status && 'any' !== $order_status ) {
			$filters['status'] = $order_status;
		}
		if ( '' !== $payment_status && 'any' !== $payment_status ) {
			$filters['payment_status'] = $payment_status;
		}

		return self::query_model_collection( self::order_model_class(), $filters, $limit, $page, $search );
	}

	private static function list_customers( int $limit, int $page, string $search, string $status ): array {
		$filters = [];
		if ( '' !== $status && 'any' !== $status ) {
			$filters['status'] = $status;
		}

		return self::query_model_collection( self::customer_model_class(), $filters, $limit, $page, $search );
	}

	private static function list_subscriptions( int $limit, int $page, string $search, int $customer_id, string $status ): array {
		$filters = [];
		if ( $customer_id > 0 ) {
			$filters['customer_id'] = $customer_id;
		}
		if ( '' !== $status && 'any' !== $status ) {
			$filters['status'] = $status;
		}

		return self::query_model_collection( self::subscription_model_class(), $filters, $limit, $page, $search );
	}

	private static function list_products( int $limit, int $page, string $search, string $post_status ): array {
		$filters = [];
		if ( '' !== $post_status && 'any' !== $post_status ) {
			$filters['post_status'] = $post_status;
		}

		return self::query_model_collection( self::product_model_class(), $filters, $limit, $page, $search );
	}

	private static function query_model_collection( string $model_class, array $filters, int $limit, int $page, string $search ): array {
		$query = self::new_model_query( $model_class );
		if ( ! $query ) {
			return [];
		}

		if ( '' !== $search && method_exists( $query, 'searchBy' ) ) {
			$query->searchBy( $search );
		}

		foreach ( $filters as $key => $value ) {
			if ( ! method_exists( $query, 'where' ) ) {
				continue;
			}

			$query->where( $key, $value );
		}

		if ( method_exists( $query, 'orderBy' ) ) {
			$query->orderBy( self::model_order_column( $model_class ), 'DESC' );
		}

		if ( method_exists( $query, 'limit' ) ) {
			$query->limit( $limit );
		}

		if ( method_exists( $query, 'offset' ) ) {
			$query->offset( max( 0, ( $page - 1 ) * $limit ) );
		}

		$rows = method_exists( $query, 'get' ) ? $query->get() : [];
		if ( is_object( $rows ) && method_exists( $rows, 'toArray' ) ) {
			$rows = $rows->toArray();
		}

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$items = [];
		foreach ( $rows as $row ) {
			$items[] = self::normalize_payload_value( $row );
		}

		return $items;
	}

	private static function find_model_by_id( string $model_class, int $id ) {
		if ( $id <= 0 ) {
			return null;
		}

		$query = self::new_model_query( $model_class );
		if ( ! $query || ! method_exists( $query, 'find' ) ) {
			return null;
		}

		return $query->find( $id );
	}

	private static function new_model_query( string $model_class ) {
		if ( '' === $model_class || ! class_exists( $model_class ) || ! method_exists( $model_class, 'query' ) ) {
			return null;
		}

		$query = $model_class::query();
		return is_object( $query ) ? $query : null;
	}

	private static function order_model_class(): string {
		return '\\FluentCart\\App\\Models\\Order';
	}

	private static function customer_model_class(): string {
		return '\\FluentCart\\App\\Models\\Customer';
	}

	private static function subscription_model_class(): string {
		return '\\FluentCart\\App\\Models\\Subscription';
	}

	private static function product_model_class(): string {
		return '\\FluentCart\\App\\Models\\Product';
	}

	private static function is_fluentcart_available(): bool {
		return function_exists( 'fluentCart' ) || class_exists( '\\FluentCart\\App\\App' ) || class_exists( self::order_model_class() );
	}

	private static function model_order_column( string $model_class ): string {
		return self::product_model_class() === $model_class ? 'ID' : 'id';
	}

	private static function load_product_payload( int $product_id ): array {
		if ( $product_id <= 0 ) {
			return [];
		}

		$product = self::find_model_by_id( self::product_model_class(), $product_id );
		if ( $product ) {
			return (array) self::normalize_payload_value( $product );
		}

		$post = get_post( $product_id );
		if ( ! $post ) {
			return [];
		}

		return [
			'ID'          => self::parse_positive_int( $post->ID ?? 0 ),
			'id'          => self::parse_positive_int( $post->ID ?? 0 ),
			'post_title'  => (string) ( $post->post_title ?? '' ),
			'post_status' => (string) ( $post->post_status ?? '' ),
			'post_type'   => (string) ( $post->post_type ?? '' ),
		];
	}

	private static function bootstrap_created_product_meta(
		int $product_id,
		string $post_title,
		string $fulfillment_type,
		string $stock_status,
		string $payment_type,
		int $total_stock
	): void {
		if ( $product_id <= 0 ) {
			return;
		}

		$stock_status = sanitize_key( $stock_status );
		if ( '' === $stock_status || 'any' === $stock_status ) {
			$stock_status = 'in-stock';
		}

		$payment_type = sanitize_key( $payment_type );
		if ( '' === $payment_type || 'any' === $payment_type ) {
			$payment_type = 'onetime';
		}

		$total_stock = max( 0, $total_stock );
		$available   = $total_stock > 0 ? 1 : 0;

		$product_detail_class = '\\FluentCart\\App\\Models\\ProductDetail';
		if ( class_exists( $product_detail_class ) && method_exists( $product_detail_class, 'query' ) ) {
			try {
				$product_detail_class::query()->create(
					[
						'post_id'          => $product_id,
						'fulfillment_type' => $fulfillment_type,
						'variation_type'   => 'simple',
						'manage_stock'     => $available,
						'other_info'       => [],
					]
				);
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		$product_variation_class = '\\FluentCart\\App\\Models\\ProductVariation';
		if ( class_exists( $product_variation_class ) && method_exists( $product_variation_class, 'query' ) ) {
			try {
				$product_variation_class::query()->create(
					[
						'post_id'          => $product_id,
						'serial_index'     => 1,
						'variation_title'  => $post_title,
						'stock_status'     => $stock_status,
						'payment_type'     => $payment_type,
						'total_stock'      => $total_stock,
						'available'        => $available,
						'fulfillment_type' => $fulfillment_type,
						'other_info'       => [
							'payment_type'      => $payment_type,
							'is_bundle_product' => 'no',
						],
					]
				);
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}
	}
}
