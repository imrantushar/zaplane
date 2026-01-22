<?php
namespace Zaplane\Integrations\Woo;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Helper {
    protected const ORDER_STATUS_EVENTS = [
        'order_status_pending',
        'order_status_failed',
        'order_status_on_hold',
        'order_status_processing',
        'order_status_completed',
        'order_status_refunded',
        'order_status_cancelled',
    ];
    protected static function build_order_payload(\WC_Order $order, array $extra = []): array {
        return array_merge([
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'customer_id' => $order->get_customer_id(),
        ], $extra);
    }

    protected static function build_product_payload(\WC_Product $product, array $extra = []): array {
        return array_merge([
            'product_id' => $product->get_id(),
            'name' => $product->get_name(),
            'status' => $product->get_status(),
            'sku' => $product->get_sku(),
            'price' => $product->get_price(),
            'type' => $product->get_type(),
        ], $extra);
    }

    protected static function build_coupon_payload(\WC_Coupon $coupon, array $extra = []): array {
        return array_merge([
            'coupon_id' => $coupon->get_id(),
            'code' => $coupon->get_code(),
            'amount' => $coupon->get_amount(),
            'discount_type' => $coupon->get_discount_type(),
        ], $extra);
    }

    protected static function build_customer_payload(\WC_Customer $customer, array $extra = []): array {
        return array_merge([
            'customer_id' => $customer->get_id(),
            'email' => $customer->get_email(),
            'username' => $customer->get_username(),
        ], $extra);
    }

    protected static function get_order_from_args(array $args, int $id_index = 0, int $object_index = 1): ?\WC_Order {
        $order = $args[$object_index] ?? null;
        if ($order instanceof \WC_Order) return $order;

        $order_id = $args[$id_index] ?? 0;
        return $order_id ? wc_get_order($order_id) : null;
    }

    protected static function order_payload_from_args(array $args, array $extra = [], int $id_index = 0, int $object_index = 1): ?array {
        $order = self::get_order_from_args($args, $id_index, $object_index);
        return $order ? self::build_order_payload($order, $extra) : null;
    }

    protected static function order_status_payload_from_args(array $args): ?array {
        $order = self::get_order_from_args($args);
        if (!$order) return null;
        $transition = $args[2] ?? [];
        $old_status = is_array($transition) ? ($transition['from'] ?? '') : '';
        $new_status = is_array($transition) ? ($transition['to'] ?? $order->get_status()) : $order->get_status();
        return self::build_order_payload($order, [
            'old_status' => $old_status,
            'new_status' => $new_status,
        ]);
    }

    protected static function get_product_from_args(array $args, int $id_index = 0, int $object_index = 1): ?\WC_Product {
        $product = $args[$object_index] ?? null;
        if ($product instanceof \WC_Product) return $product;

        $product_id = $args[$id_index] ?? 0;
        return $product_id ? wc_get_product($product_id) : null;
    }

    protected static function product_payload_from_args(array $args, array $extra = [], int $id_index = 0, int $object_index = 1): ?array {
        $product = self::get_product_from_args($args, $id_index, $object_index);
        return $product ? self::build_product_payload($product, $extra) : null;
    }

    protected static function get_product_from_post($post_ref): ?\WC_Product {
        $post = is_numeric($post_ref) ? get_post((int) $post_ref) : $post_ref;
        if (!$post || ($post->post_type ?? '') !== 'product') return null;

        return wc_get_product($post->ID) ?: null;
    }

    protected static function product_payload_from_post($post_ref, array $extra = []): ?array {
        $product = self::get_product_from_post($post_ref);
        return $product ? self::build_product_payload($product, $extra) : null;
    }

    protected static function get_coupon_from_args(array $args): ?\WC_Coupon {
        $coupon = $args[1] ?? null;
        if ($coupon instanceof \WC_Coupon) return $coupon;

        $coupon_id = $args[0] ?? 0;
        if (!$coupon_id) return null;

        $coupon = new \WC_Coupon($coupon_id);
        return $coupon->get_id() ? $coupon : null;
    }

    protected static function coupon_payload_from_args(array $args, array $extra = []): ?array {
        $coupon = self::get_coupon_from_args($args);
        return $coupon ? self::build_coupon_payload($coupon, $extra) : null;
    }

    protected static function get_customer_from_args(array $args, int $id_index = 0, int $object_index = 1): ?\WC_Customer {
        $customer = $args[$object_index] ?? null;
        if ($customer instanceof \WC_Customer) return $customer;

        $customer_id = $args[$id_index] ?? 0;
        if (!$customer_id) return null;

        $customer = new \WC_Customer($customer_id);
        return $customer->get_id() ? $customer : null;
    }

    protected static function customer_payload_from_args(array $args, array $extra = [], int $id_index = 0, int $object_index = 1): ?array {
        $customer = self::get_customer_from_args($args, $id_index, $object_index);
        return $customer ? self::build_customer_payload($customer, $extra) : null;
    }

    protected static function build_cart_add_payload(array $args): array {
        return [
            'cart_item_key' => $args[0] ?? '',
            'product_id' => $args[1] ?? 0,
            'quantity' => $args[2] ?? 0,
            'variation_id' => $args[3] ?? 0,
        ];
    }

    protected static function build_cart_item_payload(array $args): array {
        $cart_item_key = $args[0] ?? '';
        $cart = $args[1] ?? null;
        $cart_item = $cart instanceof \WC_Cart
            ? ($cart->removed_cart_contents[$cart_item_key] ?? $cart->cart_contents[$cart_item_key] ?? null)
            : null;
        if (!$cart_item) return ['cart_item_key' => $cart_item_key];

        return [
            'cart_item_key' => $cart_item_key,
            'product_id' => $cart_item['product_id'] ?? 0,
            'quantity' => $cart_item['quantity'] ?? 0,
            'variation_id' => $cart_item['variation_id'] ?? 0,
        ];
    }
}
