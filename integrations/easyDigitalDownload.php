<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

class Easydigitaldownload extends IntegrationBase {

    public static function get_slug(): string { return 'easydigitaldownload'; }

    public static function get_triggers(): array {
        return  [
            'purchase_product' => ['label' => 'Product Purchased', 'hook' => 'edd_complete_purchase'],
            'product_purchased_discount' => ['label' => 'Product purchased with a discount code', 'hook' => 'edd_complete_purchase'],
            'order_refunded' => ['label' => 'User order refunded', 'hook' => 'edds_payment_refunded'],
            'subscription_renewed' => ['label' => 'User Subscription Renewed', 'hook' => 'edd_subscription_post_renew'],
            'license_key_created' => ['label' => 'User License Key Create', 'hook' => 'edd_sl_store_license'],
            'license_key_activated' => ['label' => 'User License Key Activated', 'hook' => 'edd_sl_activate_license'],
            'license_key_deactivated' => ['label' => 'User License Key Deactivated', 'hook' => 'edd_sl_deactivate_license'],
            'license_status_active' => ['label' => 'User License Key Status Change To Active', 'hook' => 'edd_sl_post_set_status'],
            'license_status_inactive' => ['label' => 'User License Key Status Change To Inactive', 'hook' => 'edd_sl_post_set_status'],
            'license_status_disabled' => ['label' => 'User License Key Status Change To Disabled', 'hook' => 'edd_sl_post_set_status'],
            'license_status_expired' => ['label' => 'User License Key Status Change To Expired', 'hook' => 'edd_sl_post_set_status'],
        ];
    }


       public static function resolve_trigger(array $node, array $args) {
        
        switch ($node['event']) {
  case 'purchase_product':
                $payment_id = $args[0] ?? 0;
                if (!$payment_id) return false;
                
                return ['payment_id' => $payment_id];

            case 'product_purchased_discount':
                $payment_id = $args[0] ?? 0;
                if (!$payment_id) return false;
                
                $payment = edd_get_payment($payment_id);
                if (!$payment || empty($payment->discounts)) return false;

                return [
                    'payment_id' => $payment_id,
                    'discount_code' => $payment->discounts,
                ];

            case 'order_refunded':
                $payment_id = $args[0] ?? 0;
                if (!$payment_id) return false;

                return ['payment_id' => $payment_id];

            case 'subscription_renewed':
                $subscription_id = $args[0] ?? 0;
                if (!$subscription_id) return false;

                return ['subscription_id' => $subscription_id];

            case 'license_key_created':
                $license_id = $args[0] ?? 0;
                if (!$license_id) return false;

                return ['license_id' => $license_id];

            case 'license_key_activated':
            case 'license_key_deactivated':
                $license_id = $args[0] ?? 0;
                if (!$license_id) return false;

                return ['license_id' => $license_id];

            case 'license_status_active':
            case 'license_status_inactive':
            case 'license_status_disabled':
            case 'license_status_expired':
                $license_id = $args[0] ?? 0;
                $status = $args[1] ?? '';
                if (!$license_id) return false;

                return ['license_id' => $license_id, 'status' => $status];
        }

        return false;
    }

    public static function execute_node(array $node, array $input): array {
        return ['port' => 'main', 'data' => $input];
    }
}