<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

class Woo extends IntegrationBase {

    public static function get_slug(): string { return 'woocommerce'; }

    public static function get_triggers(): array {
        return [
            'new_order' => ['label'=>'New Order','hook'=>'woocommerce_new_order'],
            'order_status_changed' => ['label'=>'Order Status Changed','hook'=>'woocommerce_order_status_changed'],
        ];
    }

    public static function get_actions(): array {
        return [
            'create_order' => ['label'=>'Create Order'],
            'update_order' => ['label'=>'Update Order'],
        ];
    }

    public static function resolve_trigger(array $node, array $args) {
        $order_id = $args[0] ?? 0;
        $order = wc_get_order($order_id);
        if (!$order) return false;
        return ['order_id'=>$order_id,'total'=>$order->get_total()];
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
