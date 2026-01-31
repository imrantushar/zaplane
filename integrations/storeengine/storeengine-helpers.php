<?php

namespace Zaplane\Integrations\Storeengine;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * StoreEngine Helpers
 *
 * Shared helper methods for resolving order and product data.
 */
class StoreengineHelpers {

    /**
     * Resolve order data from order ID.
     *
     * @param int $order_id
     * @return array|false
     */
    public static function resolve_order( int $order_id ) {
        if ( ! $order_id || ! function_exists( 'storeengine_get_order' ) ) {
            return false;
        }

        $order = storeengine_get_order( $order_id );
        if ( ! $order ) {
            return false;
        }

        return [
            'order_id'       => $order_id,
            'order_number'   => $order->get_order_number(),
            'order_status'   => $order->get_status(),
            'total'          => $order->get_total(),
            'currency'       => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'customer_email' => $order->get_customer_email(),
            'customer_name'  => $order->get_customer_name(),
            'items'          => $order->get_items(),
        ];
    }

    /**
     * Resolve product data from product ID.
     *
     * @param int $product_id
     * @return array|false
     */
    public static function resolve_product( int $product_id ) {
        if ( ! $product_id || ! function_exists( 'storeengine_get_product' ) ) {
            return false;
        }

        $product = storeengine_get_product( $product_id );
        if ( ! $product ) {
            return false;
        }

        return [
            'product_id'   => $product_id,
            'product_name' => $product->get_name(),
            'price'        => $product->get_price(),
            'description'  => $product->get_description(),
            'status'       => $product->get_status(),
        ];
    }
}
