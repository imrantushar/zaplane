<?php

namespace Zaplane\Modules\Inbox\Commerce;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WoocommerceStore implements StoreInterface {

	public static function slug(): string {
		return 'woocommerce';
	}

	public static function label(): string {
		return 'WooCommerce';
	}

	public static function available(): bool {
		return function_exists( 'wc_get_product' ) && function_exists( 'wc_create_order' );
	}

	public static function search( string $query, int $limit = 6 ): array {
		$args = [
			'status' => 'publish',
			'limit'  => max( 1, min( 20, $limit ) ),
			'return' => 'ids',
		];
		if ( '' !== trim( $query ) ) {
			$args['s'] = $query;
		}

		$out = [];
		foreach ( wc_get_products( $args ) as $id ) {
			$product = self::product( (int) $id );
			if ( $product ) {
				$out[] = $product;
			}
		}
		return $out;
	}

	public static function product( int $id, int $option_id = 0 ): ?array {
		$product = $id > 0 ? wc_get_product( $id ) : null;
		if ( ! $product || 'publish' !== $product->get_status() ) {
			return null;
		}

		// A variable product's variations are its options: "Red / Large"…
		$options = [];
		$chosen  = null;
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$variation = wc_get_product( $child_id );
				if ( ! $variation || ! $variation->variation_is_visible() ) {
					continue;
				}
				$option    = self::option( $variation );
				$options[] = $option;
				if ( $option['id'] === $option_id ) {
					$chosen = $option;
				}
			}
		}
		$chosen = $chosen ?? ( $options[0] ?? null );
		$amount = $chosen ? (float) $chosen['price'] : (float) $product->get_price();
		$image  = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'medium' ) : '';
		if ( $chosen && $chosen['image'] ) {
			$image = $chosen['image'];
		}

		return [
			'id'           => $id,
			'option_id'    => $chosen ? (int) $chosen['id'] : 0,
			'option_label' => $chosen && count( $options ) > 1 ? (string) $chosen['label'] : '',
			'name'         => wp_specialchars_decode( $product->get_name(), ENT_QUOTES ),
			'price'        => $amount,
			'price_text'   => self::money( $amount ),
			'compare_text' => $chosen ? (string) $chosen['compare_text'] : self::compare( $product ),
			'in_stock'     => $chosen ? (bool) $chosen['in_stock'] : $product->is_in_stock(),
			'url'          => (string) $product->get_permalink(),
			'image'        => (string) $image,
			'options'      => count( $options ) > 1 ? array_map( fn( $o ) => array_diff_key( $o, [ 'image' => 1 ] ), $options ) : [],
		];
	}

	/**
	 * One variation as a choosable option.
	 *
	 * @param \WC_Product_Variation $variation
	 * @return array{id:int,label:string,price:float,price_text:string,compare_text:string,in_stock:bool,image:string}
	 */
	private static function option( $variation ): array {
		$amount = (float) $variation->get_price();
		$label  = wc_get_formatted_variation( $variation, true, false, true );

		return [
			'id'           => (int) $variation->get_id(),
			'label'        => '' !== trim( (string) $label ) ? wp_strip_all_tags( (string) $label ) : '#' . $variation->get_id(),
			'price'        => $amount,
			'price_text'   => self::money( $amount ),
			'compare_text' => self::compare( $variation ),
			'in_stock'     => $variation->is_in_stock(),
			'image'        => $variation->get_image_id() ? (string) wp_get_attachment_image_url( $variation->get_image_id(), 'medium' ) : '',
		];
	}

	/** The "was" price while on sale, else ''. */
	private static function compare( $product ): string {
		$regular = (float) $product->get_regular_price();
		return $product->is_on_sale() && $regular > (float) $product->get_price() ? self::money( $regular ) : '';
	}

	private static function money( float $amount ): string {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}

	public static function create_order( array $items, array $customer, array $context ) {
		try {
			$order = wc_create_order( [
				'customer_id' => (int) ( $context['wp_user_id'] ?? 0 ),
				'created_via' => 'zaplane-inbox',
			] );
			if ( is_wp_error( $order ) ) {
				return $order;
			}

			$added = 0;
			foreach ( $items as $item ) {
				$product = wc_get_product( (int) $item['product_id'] );
				if ( $product && $product->is_type( 'variable' ) ) {
					// The chosen variation, else the first one on sale.
					$picked  = self::product( (int) $item['product_id'], (int) ( $item['option_id'] ?? 0 ) );
					$product = $picked && $picked['option_id'] ? wc_get_product( $picked['option_id'] ) : null;
				}
				if ( ! $product || ! $product->is_purchasable() ) {
					continue;
				}
				$order->add_product( $product, max( 1, (int) $item['qty'] ) );
				$added++;
			}
			if ( 0 === $added ) {
				$order->delete( true );
				return new WP_Error( 'zaplane_inbox_order_empty', __( 'None of those products can be ordered.', 'zaplane' ) );
			}

			$address = Commerce::address_fields( $customer );
			$order->set_address( $address, 'billing' );
			$order->set_address( $address, 'shipping' );
			$order->set_payment_method( 'cod' );
			$order->set_payment_method_title( __( 'Cash on delivery', 'zaplane' ) );
			$order->calculate_totals();
			$order->add_order_note( Commerce::order_note( $customer, $context ) );
			$order->update_status( 'processing' );

			return [
				'id'         => (int) $order->get_id(),
				'number'     => (string) $order->get_order_number(),
				'total_text' => html_entity_decode( wp_strip_all_tags( wc_price( (float) $order->get_total() ) ), ENT_QUOTES, 'UTF-8' ),
				'admin_url'  => (string) $order->get_edit_order_url(),
			];
		} catch ( \Throwable $e ) {
			return new WP_Error( 'zaplane_inbox_order_failed', $e->getMessage() );
		}
	}
}
