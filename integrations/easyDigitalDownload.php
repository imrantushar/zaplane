
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
            'edd_purchase_complete' => ['label' => 'Purchase Complete', 'hook' => 'edd_complete_purchase'],
            'edd_download_purchased' => ['label' => 'Download Purchased', 'hook' => 'edd_insert_payment'],
            'edd_customer_created' => ['label' => 'Customer Created', 'hook' => 'edd_customer_post_create'],
            'edd_subscription_created' => ['label' => 'Subscription Created', 'hook' => 'edd_subscription_post_create'],
            'edd_subscription_cancelled' => ['label' => 'Subscription Cancelled', 'hook' => 'edd_subscription_cancelled'],
            'edd_refund_created' => ['label' => 'Refund Created', 'hook' => 'edd_refund_order'],
        ];
    }

    public static function get_actions(): array {
        return [];
    }

    public static function resolve_trigger(array $node, array $args) {
        switch ($node['event']) {
            case 'edd_purchase_complete':
            case 'edd_download_purchased':
                $payment_id = $args[0] ?? 0;
                if (!$payment_id) return false;
                
                $payment = edd_get_payment($payment_id);
                if (!$payment) return false;

                return [
                    'payment_id' => $payment_id,
                    'customer_email' => $payment->email,
                    'total' => $payment->total,
                    'customer_id' => $payment->customer_id,
                ];

            case 'edd_customer_created':
                $customer_id = $args[0] ?? 0;
                if (!$customer_id) return false;
                
                $customer = edd_get_customer($customer_id);
                if (!$customer) return false;

                return [
                    'customer_id' => $customer_id,
                    'customer_email' => $customer->email,
                    'customer_name' => $customer->name,
                ];

            case 'edd_subscription_created':
            case 'edd_subscription_cancelled':
                $subscription_id = $args[0] ?? 0;
                if (!$subscription_id) return false;

                return [
                    'subscription_id' => $subscription_id,
                    'customer_id' => $args[1] ?? 0,
                ];

            case 'edd_refund_created':
                $order_id = $args[0] ?? 0;
                if (!$order_id) return false;

                return [
                    'order_id' => $order_id,
                    'refund_amount' => $args[1] ?? 0,
                ];
        }

        return false;
    }

    public static function execute_node(array $node, array $input): array {
        return ['port' => 'main', 'data' => $input];
    }
}