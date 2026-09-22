<?php

namespace Zaplane\Modules\Inbox\Commerce;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StoreengineStore implements StoreInterface {

	public static function slug(): string {
		return 'storeengine';
	}

	public static function label(): string {
		return 'StoreEngine';
	}

	public static function available(): bool {
		return class_exists( '\StoreEngine\Classes\ProductFactory' ) && class_exists( '\StoreEngine\Classes\Order' );
	}

	private static function post_type(): string {
		return class_exists( '\StoreEngine\Utils\Helper' ) ? \StoreEngine\Utils\Helper::PRODUCT_POST_TYPE : 'storeengine_product';
	}

	public static function search( string $query, int $limit = 6 ): array {
		$args = [
			'post_type'      => self::post_type(),
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 20, $limit ) ),
			'no_found_rows'  => true,
			'fields'         => 'ids',
		];
		if ( '' !== trim( $query ) ) {
			$args['s'] = $query;
		}

		$out = [];
		foreach ( get_posts( $args ) as $id ) {
			$product = self::product( (int) $id );
			if ( $product ) {
				$out[] = $product;
			}
		}
		return $out;
	}

	public static function product( int $id, int $option_id = 0 ): ?array {
		if ( $id <= 0 || get_post_type( $id ) !== self::post_type() || 'publish' !== get_post_status( $id ) ) {
			return null;
		}

		$product = \StoreEngine\Classes\ProductFactory::getProduct( $id );
		if ( ! $product ) {
			return null;
		}

		$in_stock = (bool) $product->is_in_stock();
		$options  = [];
		$chosen   = null;
		foreach ( (array) $product->get_prices() as $price ) {
			if ( ! is_object( $price ) || ( method_exists( $price, 'get_is_hidden' ) && $price->get_is_hidden() ) ) {
				continue;
			}
			$option    = self::option( $price, $in_stock );
			$options[] = $option;
			if ( $option['id'] === $option_id ) {
				$chosen = $option;
			}
		}
		$chosen = $chosen ?? ( $options[0] ?? null );

		return [
			'id'           => $id,
			'price_id'     => $chosen ? (int) $chosen['id'] : 0,
			'option_id'    => $chosen ? (int) $chosen['id'] : 0,
			// Named only when there is a choice to tell apart.
			'option_label' => $chosen && count( $options ) > 1 ? (string) $chosen['label'] : '',
			'name'         => wp_specialchars_decode( get_the_title( $id ), ENT_QUOTES ),
			'price'        => $chosen ? (float) $chosen['price'] : 0.0,
			'price_text'   => $chosen ? (string) $chosen['price_text'] : self::money( 0 ),
			'compare_text' => $chosen ? (string) $chosen['compare_text'] : '',
			'in_stock'     => $in_stock,
			'url'          => (string) get_permalink( $id ),
			'image'        => (string) get_the_post_thumbnail_url( $id, 'medium' ),
			'options'      => count( $options ) > 1 ? $options : [],
		];
	}

	/**
	 * One of a product's prices as a choosable option: "Large", "Monthly
	 * plan"… with its price (and the "was" price when it's on sale).
	 *
	 * @param object $price \StoreEngine\Classes\Price
	 * @return array{id:int,label:string,price:float,price_text:string,compare_text:string,in_stock:bool}
	 */
	private static function option( $price, bool $in_stock ): array {
		$amount  = (float) $price->get_price();
		$compare = method_exists( $price, 'get_compare_price' ) ? $price->get_compare_price() : null;
		$label   = method_exists( $price, 'get_price_name' ) ? trim( (string) $price->get_price_name() ) : '';
		$text    = self::money( $amount );

		if ( method_exists( $price, 'get_price_type' ) && 'subscription' === $price->get_price_type() && method_exists( $price, 'get_period' ) ) {
			$every = method_exists( $price, 'get_payment_duration' ) ? max( 1, (int) $price->get_payment_duration() ) : 1;
			$text  = 1 === $every
				/* translators: 1: price, 2: billing period: day, week, month or year. */
				? sprintf( __( '%1$s / %2$s', 'zaplane' ), $text, $price->get_period() )
				/* translators: 1: price, 2: how many, 3: billing period: day, week, month or year. */
				: sprintf( __( '%1$s every %2$d %3$ss', 'zaplane' ), $text, $every, $price->get_period() );
		}

		return [
			'id'           => (int) $price->get_id(),
			'label'        => '' !== $label ? $label : $text,
			'price'        => $amount,
			'price_text'   => $text,
			'compare_text' => null !== $compare && (float) $compare > $amount ? self::money( (float) $compare ) : '',
			'in_stock'     => $in_stock,
		];
	}

	private static function money( float $amount ): string {
		if ( class_exists( '\StoreEngine\Utils\Formatting' ) ) {
			return html_entity_decode( wp_strip_all_tags( \StoreEngine\Utils\Formatting::price( $amount ) ), ENT_QUOTES, 'UTF-8' );
		}
		return number_format_i18n( $amount, 2 );
	}

	public static function create_order( array $items, array $customer, array $context ) {
		try {
			$order = new \StoreEngine\Classes\Order();
			$order->set_created_via( 'zaplane-inbox' );
			$order->set_payment_method( 'cod' );
			$order->set_payment_method_title( __( 'Cash on delivery', 'zaplane' ) );

			if ( ! empty( $context['wp_user_id'] ) ) {
				$order->set_customer_id( (int) $context['wp_user_id'] );
			}

			$address = Commerce::address_fields( $customer );
			$order->set_billing_address( $address );
			$order->set_shipping_address( $address );

			$added = 0;
			foreach ( $items as $item ) {
				$product = self::product( (int) $item['product_id'], (int) ( $item['option_id'] ?? 0 ) );
				if ( ! $product || ! $product['price_id'] ) {
					continue;
				}
				$order->add_product( (int) $product['price_id'], max( 1, (int) $item['qty'] ) );
				$added++;
			}
			if ( 0 === $added ) {
				return new WP_Error( 'zaplane_inbox_order_empty', __( 'None of those products can be ordered.', 'zaplane' ) );
			}

			$order->calculate_totals();
			$order->save();

			$order->add_order_note( Commerce::order_note( $customer, $context ) );
			$order->set_paid_status( 'unpaid' );
			if ( class_exists( '\StoreEngine\Classes\OrderContext' ) ) {
				( new \StoreEngine\Classes\OrderContext( $order->get_status() ) )->proceed_to_next_status( 'processing', $order );
			}
			$order->save();

			$id = (int) $order->get_id();

			return [
				'id'         => $id,
				'number'     => (string) ( method_exists( $order, 'get_order_number' ) ? $order->get_order_number() : $id ),
				'total_text' => self::money( (float) $order->get_total() ),
				'admin_url'  => admin_url( 'admin.php?page=storeengine-orders&action=edit&id=' . $id ),
			];
		} catch ( \Throwable $e ) {
			return new WP_Error( 'zaplane_inbox_order_failed', $e->getMessage() );
		}
	}
}
