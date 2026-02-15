
<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

class EasyDigitalDownload extends IntegrationBase {

    public static function get_slug(): string {
        return 'EasyDigitalDownload';
    }

    public static function get_name(): string {
        return 'Easy Digital Download';
    }

    public static function get_triggers(): array {
        return [
            'product_purchased_discount' => ['label' => 'Product purchased with a discount code', 'hook' => 'edd_complete_purchase'],
            'order_refunded_stripe' => ['label' => 'User order refunded via Stripe gateway', 'hook' => 'edd_stripe_refund_order'],
            'subscription_renewed' => ['label' => 'User Subscription Renewed', 'hook' => 'edd_subscription_post_renew'],
            'license_key_created' => ['label' => 'User License Key Create', 'hook' => 'edd_sl_post_insert_license'],
            'license_key_activated' => ['label' => 'User License Key Activated', 'hook' => 'edd_sl_post_activate_license'],
            'license_key_deactivated' => ['label' => 'User License Key Deactivated', 'hook' => 'edd_sl_post_deactivate_license'],
            'license_status_active' => ['label' => 'User License Key Status Change To Active', 'hook' => 'edd_sl_license_status_active'],
            'license_status_inactive' => ['label' => 'User License Key Status Change To Inactive', 'hook' => 'edd_sl_license_status_inactive'],
            'license_status_disabled' => ['label' => 'User License Key Status Change To Disabled', 'hook' => 'edd_sl_license_status_disabled'],
            'license_status_expired' => ['label' => 'User License Key Status Change To Expired', 'hook' => 'edd_sl_license_status_expired'],
        ];
    }

    public static function get_actions(): array {
        return [];
    }

    public static function resolve_trigger(array $node, array $args) {
        switch ($node['event']) {
            case 'product_purchased_discount':
                $payment_id = $args[0] ?? 0;
                if (!$payment_id) return false;
                
                $payment = edd_get_payment($payment_id);
                if (!$payment || empty($payment->discounts)) return false;

                return [
                    'payment_id' => $payment_id,
                    'discount_code' => $payment->discounts,
                    'customer_email' => $payment->email,
                    'total' => $payment->total,
                ];

            case 'order_refunded_stripe':
                $payment_id = $args[0] ?? 0;
                if (!$payment_id) return false;

                return [
                    'payment_id' => $payment_id,
                    'refund_id' => $args[1] ?? '',
                ];

            case 'subscription_renewed':
                $subscription_id = $args[0] ?? 0;
                if (!$subscription_id) return false;

                return [
                    'subscription_id' => $subscription_id,
                ];

            case 'license_key_created':
                $license_id = $args[0] ?? 0;
                if (!$license_id) return false;

                return [
                    'license_id' => $license_id,
                    'license_key' => $args[1] ?? '',
                ];

            case 'license_key_activated':
            case 'license_key_deactivated':
                $license_id = $args[0] ?? 0;
                if (!$license_id) return false;

                return [
                    'license_id' => $license_id,
                ];

            case 'license_status_active':
            case 'license_status_inactive':
            case 'license_status_disabled':
            case 'license_status_expired':
                $license_id = $args[0] ?? 0;
                if (!$license_id) return false;

                return [
                    'license_id' => $license_id,
                    'status' => $args[1] ?? '',
                ];
        }

        return false;
    }

    public static function execute_node(array $node, array $input): array {
        return ['port' => 'main', 'data' => $input];
    }
}