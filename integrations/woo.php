<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Woo\Helper;

if (!defined('ABSPATH')) exit;

class Woo extends IntegrationBase {
    use Helper;

    public static function get_slug(): string { return 'woocommerce'; }

    /* =====================================================
     * TRIGGERS
     * ===================================================== */
    public static function get_triggers(): array {
        return [
            'new_order' => ['label'=>'New Order','hook'=>'woocommerce_new_order'],
            'restore_order' => ['label'=>'Restore Order','hook'=>'woocommerce_untrash_order'],
            'order_status_pending' => ['label'=>'Order Status Set to Pending','hook'=>'woocommerce_order_status_pending'],
            'order_status_failed' => ['label'=>'Order Status Set to Failed','hook'=>'woocommerce_order_status_failed'],
            'order_status_on_hold' => ['label'=>'Order Status Set to On-hold','hook'=>'woocommerce_order_status_on-hold'],
            'order_status_processing' => ['label'=>'Order Status Set to Processing','hook'=>'woocommerce_order_status_processing'],
            'order_status_completed' => ['label'=>'Order Status Set to Completed','hook'=>'woocommerce_order_status_completed'],
            'order_status_refunded' => ['label'=>'Order Status Set to Refunded','hook'=>'woocommerce_order_status_refunded'],
            'order_status_cancelled' => ['label'=>'Order Status Set to Cancelled','hook'=>'woocommerce_order_status_cancelled'],
            'order_status_changed' => ['label'=>'Order Status Changed','hook'=>'woocommerce_order_status_changed'],
            'new_coupon' => ['label'=>'New Coupon Created','hook'=>'woocommerce_new_coupon'],
            'create_customer' => ['label'=>'Create Customer','hook'=>'woocommerce_created_customer'],
            'update_customer' => ['label'=>'Update Customer','hook'=>'woocommerce_update_customer'],
            'delete_customer' => ['label'=>'Delete Customer','hook'=>'woocommerce_delete_customer'],
            'create_product' => ['label'=>'Create Product','hook'=>'woocommerce_new_product'],
            'update_product' => ['label'=>'Update Product','hook'=>'woocommerce_update_product'],
            'delete_product' => ['label'=>'Delete Product','hook'=>'before_delete_post'],
            'restore_product' => ['label'=>'Restore Product','hook'=>'untrashed_post'],
            'product_status_updated' => ['label'=>'Product Status Updated','hook'=>'woocommerce_product_set_stock_status'],
            'product_status_changed' => ['label'=>'Product Status Changed','hook'=>'transition_post_status'],
            'product_added_to_cart' => ['label'=>'Product Added to Cart','hook'=>'woocommerce_add_to_cart'],
            'product_removed_from_cart' => ['label'=>'Product Removed from Cart','hook'=>'woocommerce_cart_item_removed'],
        ];
    }

    /* =====================================================
     * TRIGGERS
     * ===================================================== */
    public static function resolve_trigger(array $node, array $args) {
        $event = $node['event'] ?? '';
        if ($event === '') {
            return false;
        }

        if (in_array($event, self::ORDER_STATUS_EVENTS, true)) {
            return self::order_status_payload_from_args($args) ?: false;
        }

        switch ($event) {
            case 'new_order':
                return self::order_payload_from_args($args) ?: false;
            case 'restore_order':
                return self::order_payload_from_args($args, [
                    'previous_status' => $args[1] ?? '',
                ]) ?: false;
            case 'order_status_changed':
                return self::order_payload_from_args($args, [
                    'old_status' => $args[1] ?? '',
                    'new_status' => $args[2] ?? '',
                ], 0, 3) ?: false;
            case 'new_coupon':
                return self::coupon_payload_from_args($args) ?: false;
            case 'create_customer':
                return self::customer_payload_from_args($args, [
                    'password_generated' => (bool) ($args[2] ?? false),
                ]) ?: false;
            case 'update_customer':
                return self::customer_payload_from_args($args) ?: false;
            case 'delete_customer':
                $customer_id = $args[0] ?? 0;
                return $customer_id ? ['customer_id' => $customer_id] : false;
            case 'create_product':
            case 'update_product':
                return self::product_payload_from_args($args) ?: false;
            case 'delete_product':
            case 'restore_product':
                return self::product_payload_from_post($args[0] ?? 0) ?: false;
            case 'product_status_updated':
                return self::product_payload_from_args($args, [
                    'stock_status' => $args[1] ?? '',
                ], 0, 2) ?: false;
            case 'product_status_changed':
                return self::product_payload_from_post($args[2] ?? null, [
                    'old_status' => $args[1] ?? '',
                    'new_status' => $args[0] ?? '',
                ]) ?: false;
            case 'product_added_to_cart':
                return self::build_cart_add_payload($args);
            case 'product_removed_from_cart':
                return self::build_cart_item_payload($args);
        }

        return false;
    }
    /* =====================================================
     * ACTIONS
     * ===================================================== */
    public static function get_actions(): array {
        return [
            'create_order' => ['label'=>'Create Order'],
            'update_order' => ['label'=>'Update Order'],
        ];
    }


    public static function execute_node(array $node, array $input): array {
        switch($node['config']['action'] ?? '') {
            case 'create_order':
                $order = wc_create_order();
                return ['port'=>'main','data'=>['order_id'=>$order->get_id()]];
            case 'update_order':
                $order = wc_get_order($node['config']['data']['order_id'] ?? 0);
                if($order) $order->update_meta_data('custom', $node['config']['data']['meta'] ?? '');
                return ['port'=>'main','data'=>$input];
            default:
                return ['port'=>'main','data'=>$input];
        }
    }
}
