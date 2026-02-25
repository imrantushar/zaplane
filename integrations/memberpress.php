<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Memberpress\ActionsTrait;
use Zaplane\Integrations\Memberpress\HelperTrait;

if (!defined('ABSPATH')) exit;

class Memberpress extends IntegrationBase {
    use ActionsTrait;
    use HelperTrait;

    public static function get_slug(): string { return 'memberpress'; }

    public static function get_triggers(): array {
        return [
            'member_added' => ['label' => 'Member Added', 'hook' => 'mepr_event_member-added'],
            'member_signup_completed' => ['label' => 'Member Signup Completed', 'hook' => 'mepr_event_member-signup-completed'],
            'member_account_updated' => ['label' => 'Member Account Updated', 'hook' => 'mepr_event_member-account-updated'],
            'member_deleted' => ['label' => 'Member Deleted', 'hook' => 'mepr_event_member-deleted'],
            'login' => ['label' => 'Member Logged In', 'hook' => 'mepr_event_login'],
            'subscription_created' => ['label' => 'Subscription Created', 'hook' => 'mepr_event_subscription-created'],
            'subscription_paused' => ['label' => 'Subscription Paused', 'hook' => 'mepr_event_subscription-paused'],
            'subscription_resumed' => ['label' => 'Subscription Resumed', 'hook' => 'mepr_event_subscription-resumed'],
            'subscription_stopped' => ['label' => 'Subscription Stopped', 'hook' => 'mepr_event_subscription-stopped'],
            'subscription_upgraded' => ['label' => 'Subscription Upgraded', 'hook' => 'mepr_event_subscription-upgraded'],
            'subscription_downgraded' => ['label' => 'Subscription Downgraded', 'hook' => 'mepr_event_subscription-downgraded'],
            'subscription_upgraded_to_one_time' => ['label' => 'Subscription Upgraded To One-Time', 'hook' => 'mepr_event_subscription-upgraded-to-one-time'],
            'subscription_upgraded_to_recurring' => ['label' => 'Subscription Upgraded To Recurring', 'hook' => 'mepr_event_subscription-upgraded-to-recurring'],
            'subscription_downgraded_to_one_time' => ['label' => 'Subscription Downgraded To One-Time', 'hook' => 'mepr_event_subscription-downgraded-to-one-time'],
            'subscription_downgraded_to_recurring' => ['label' => 'Subscription Downgraded To Recurring', 'hook' => 'mepr_event_subscription-downgraded-to-recurring'],
            'subscription_expired' => ['label' => 'Subscription Expired', 'hook' => 'mepr_event_subscription-expired'],
            'subscription_changed' => ['label' => 'Subscription Changed', 'hook' => 'mepr_event_subscription-changed'],
            'transaction_completed' => ['label' => 'Transaction Completed', 'hook' => 'mepr_event_transaction-completed'],
            'transaction_refunded' => ['label' => 'Transaction Refunded', 'hook' => 'mepr_event_transaction-refunded'],
            'transaction_failed' => ['label' => 'Transaction Failed', 'hook' => 'mepr_event_transaction-failed'],
            'transaction_expired' => ['label' => 'Transaction Expired', 'hook' => 'mepr_event_transaction-expired'],
            'offline_payment_pending' => ['label' => 'Offline Payment Pending', 'hook' => 'mepr_event_offline-payment-pending'],
            'offline_payment_complete' => ['label' => 'Offline Payment Complete', 'hook' => 'mepr_event_offline-payment-complete'],
            'offline_payment_refunded' => ['label' => 'Offline Payment Refunded', 'hook' => 'mepr_event_offline-payment-refunded'],
            'recurring_transaction_completed' => ['label' => 'Recurring Transaction Completed', 'hook' => 'mepr_event_recurring-transaction-completed'],
            'renewal_transaction_completed' => ['label' => 'Renewal Transaction Completed', 'hook' => 'mepr_event_renewal-transaction-completed'],
            'recurring_transaction_failed' => ['label' => 'Recurring Transaction Failed', 'hook' => 'mepr_event_recurring-transaction-failed'],
            'recurring_transaction_expired' => ['label' => 'Recurring Transaction Expired', 'hook' => 'mepr_event_recurring-transaction-expired'],
            'recurring_transaction_refunded' => ['label' => 'Recurring Transaction Refunded', 'hook' => 'mepr_event_recurring-transaction-refunded'],
            'non_recurring_transaction_completed' => ['label' => 'Non-Recurring Transaction Completed', 'hook' => 'mepr_event_non-recurring-transaction-completed'],
            'non_recurring_transaction_expired' => ['label' => 'Non-Recurring Transaction Expired', 'hook' => 'mepr_event_non-recurring-transaction-expired'],
            'account_is_active' => ['label' => 'Account Is Active', 'hook' => 'mepr_event_account-is-active'],
            'account_is_inactive' => ['label' => 'Account Is Inactive', 'hook' => 'mepr_event_account-is-inactive'],
        ];
    }

    public static function resolve_trigger(array $node, array $args)
    {
        $event = $args[0] ?? null;
        if (!is_object($event) || !isset($event->event)) {
            return false;
        }

        $payload = [
            'event' => $event->event ?? '',
            'evt_id' => (int) ($event->evt_id ?? 0),
            'evt_id_type' => $event->evt_id_type ?? '',
            'created_at' => $event->created_at ?? null,
            'args' => self::decode_event_args($event->args ?? null),
        ];

        if ($payload['event'] === '' || $payload['evt_id'] <= 0) {
            return false;
        }

        switch ($payload['evt_id_type']) {
            case 'users':
                $payload['user_id'] = $payload['evt_id'];
                break;
            case 'transactions':
                $payload['transaction_id'] = $payload['evt_id'];
                break;
            case 'subscriptions':
                $payload['subscription_id'] = $payload['evt_id'];
                break;
            case 'drm':
                $payload['drm_id'] = $payload['evt_id'];
                break;
        }

        return array_merge($payload, self::get_event_payload_details($event));
    }

    private static function get_event_payload_details($event): array
    {
        if (!is_object($event) || !method_exists($event, 'get_data')) {
            return [];
        }

        $data = $event->get_data();
        if (!is_object($data) || (function_exists('is_wp_error') && is_wp_error($data))) {
            return [];
        }

        if (class_exists('MeprUser') && $data instanceof \MeprUser) {
            $user_id = (int) ($data->ID ?? 0);
            return [
                'user_id' => $user_id,
                'user' => [
                    'id' => $user_id,
                    'email' => $data->user_email ?? '',
                    'username' => $data->user_login ?? '',
                    'first_name' => $data->first_name ?? '',
                    'last_name' => $data->last_name ?? '',
                    'display_name' => $data->display_name ?? '',
                ],
            ];
        }

        if (class_exists('MeprTransaction') && $data instanceof \MeprTransaction) {
            $transaction_id = (int) ($data->id ?? 0);
            $user_id = (int) ($data->user_id ?? 0);
            $membership_id = (int) ($data->product_id ?? 0);
            $subscription_id = (int) ($data->subscription_id ?? 0);
            $amount = $data->amount ?? null;
            $total = $data->total ?? null;

            return [
                'transaction_id' => $transaction_id,
                'user_id' => $user_id,
                'membership_id' => $membership_id,
                'product_id' => $membership_id,
                'subscription_id' => $subscription_id,
                'transaction' => [
                    'id' => $transaction_id,
                    'user_id' => $user_id,
                    'product_id' => $membership_id,
                    'subscription_id' => $subscription_id,
                    'amount' => $amount !== null ? (float) $amount : null,
                    'total' => $total !== null ? (float) $total : null,
                    'status' => $data->status ?? '',
                    'gateway' => $data->gateway ?? '',
                    'created_at' => $data->created_at ?? null,
                    'expires_at' => $data->expires_at ?? null,
                ],
            ];
        }

        if (class_exists('MeprSubscription') && $data instanceof \MeprSubscription) {
            $subscription_id = (int) ($data->id ?? 0);
            $user_id = (int) ($data->user_id ?? 0);
            $membership_id = (int) ($data->product_id ?? 0);
            $price = $data->price ?? null;

            return [
                'subscription_id' => $subscription_id,
                'user_id' => $user_id,
                'membership_id' => $membership_id,
                'product_id' => $membership_id,
                'subscription' => [
                    'id' => $subscription_id,
                    'user_id' => $user_id,
                    'product_id' => $membership_id,
                    'price' => $price !== null ? (float) $price : null,
                    'period' => isset($data->period) ? (int) $data->period : null,
                    'period_type' => $data->period_type ?? '',
                    'status' => $data->status ?? '',
                    'gateway' => $data->gateway ?? '',
                    'created_at' => $data->created_at ?? null,
                ],
            ];
        }

        return [];
    }

    public static function get_actions(): array
    {
        return [
            'create_member' => ['label' => 'Create Member'],
            'create_membership' => ['label' => 'Create Membership'],
            'update_membership' => ['label' => 'Update Membership'],
            'create_transaction' => ['label' => 'Create Transaction'],
            'update_transaction_status' => ['label' => 'Update Transaction Status'],
            'refund_transaction' => ['label' => 'Refund Transaction'],
            'create_subscription' => ['label' => 'Create Subscription'],
            'update_subscription_status' => ['label' => 'Update Subscription Status'],
            'cancel_subscription' => ['label' => 'Cancel Subscription'],
            'suspend_subscription' => ['label' => 'Suspend Subscription'],
            'resume_subscription' => ['label' => 'Resume Subscription'],
        ];
    }

    public static function get_action_config_schema(string $action): array
    {
        $schemas = [
            'create_member' => [
                ['key' => 'email', 'label' => 'Email', 'type' => 'text', 'required' => true],
                ['key' => 'username', 'label' => 'Username', 'type' => 'text'],
                ['key' => 'password', 'label' => 'Password', 'type' => 'text'],
                ['key' => 'first_name', 'label' => 'First Name', 'type' => 'text'],
                ['key' => 'last_name', 'label' => 'Last Name', 'type' => 'text'],
            ],
            'create_membership' => [
                ['key' => 'name', 'label' => 'Membership Name', 'type' => 'text', 'required' => true],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['key' => 'price', 'label' => 'Price', 'type' => 'number', 'required' => true],
                ['key' => 'period', 'label' => 'Period', 'type' => 'number'],
                ['key' => 'period_type', 'label' => 'Period Type', 'type' => 'select', 'options' => self::get_membership_period_type_options()],
                ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => self::get_post_status_options()],
                ['key' => 'trial', 'label' => 'Trial', 'type' => 'select', 'options' => self::get_yes_no_options()],
                ['key' => 'trial_days', 'label' => 'Trial Days', 'type' => 'number'],
                ['key' => 'trial_amount', 'label' => 'Trial Amount', 'type' => 'number'],
                ['key' => 'expire_type', 'label' => 'Expire Type', 'type' => 'select', 'options' => self::get_expire_type_options()],
                ['key' => 'expire_after', 'label' => 'Expire After', 'type' => 'number'],
                ['key' => 'expire_unit', 'label' => 'Expire Unit', 'type' => 'select', 'options' => self::get_expire_unit_options()],
                ['key' => 'expire_fixed', 'label' => 'Expire Fixed (YYYY-MM-DD)', 'type' => 'text'],
                ['key' => 'allow_renewal', 'label' => 'Allow Renewal', 'type' => 'select', 'options' => self::get_yes_no_options()],
                ['key' => 'pricing_display', 'label' => 'Pricing Display', 'type' => 'select', 'options' => self::get_pricing_display_options()],
                ['key' => 'pricing_title', 'label' => 'Pricing Title', 'type' => 'text'],
                ['key' => 'pricing_heading_text', 'label' => 'Pricing Heading Text', 'type' => 'text'],
                ['key' => 'pricing_footer_text', 'label' => 'Pricing Footer Text', 'type' => 'text'],
                ['key' => 'pricing_button_text', 'label' => 'Pricing Button Text', 'type' => 'text'],
                ['key' => 'pricing_button_position', 'label' => 'Pricing Button Position', 'type' => 'text'],
            ],
            'update_membership' => [
                ['key' => 'membership_id', 'label' => 'Membership ID', 'type' => 'number', 'required' => true],
                ['key' => 'name', 'label' => 'Membership Name', 'type' => 'text'],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['key' => 'price', 'label' => 'Price', 'type' => 'number'],
                ['key' => 'period', 'label' => 'Period', 'type' => 'number'],
                ['key' => 'period_type', 'label' => 'Period Type', 'type' => 'select', 'options' => self::get_membership_period_type_options()],
                ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => self::get_post_status_options()],
                ['key' => 'trial', 'label' => 'Trial', 'type' => 'select', 'options' => self::get_yes_no_options()],
                ['key' => 'trial_days', 'label' => 'Trial Days', 'type' => 'number'],
                ['key' => 'trial_amount', 'label' => 'Trial Amount', 'type' => 'number'],
                ['key' => 'expire_type', 'label' => 'Expire Type', 'type' => 'select', 'options' => self::get_expire_type_options()],
                ['key' => 'expire_after', 'label' => 'Expire After', 'type' => 'number'],
                ['key' => 'expire_unit', 'label' => 'Expire Unit', 'type' => 'select', 'options' => self::get_expire_unit_options()],
                ['key' => 'expire_fixed', 'label' => 'Expire Fixed (YYYY-MM-DD)', 'type' => 'text'],
                ['key' => 'allow_renewal', 'label' => 'Allow Renewal', 'type' => 'select', 'options' => self::get_yes_no_options()],
                ['key' => 'pricing_display', 'label' => 'Pricing Display', 'type' => 'select', 'options' => self::get_pricing_display_options()],
                ['key' => 'pricing_title', 'label' => 'Pricing Title', 'type' => 'text'],
                ['key' => 'pricing_heading_text', 'label' => 'Pricing Heading Text', 'type' => 'text'],
                ['key' => 'pricing_footer_text', 'label' => 'Pricing Footer Text', 'type' => 'text'],
                ['key' => 'pricing_button_text', 'label' => 'Pricing Button Text', 'type' => 'text'],
                ['key' => 'pricing_button_position', 'label' => 'Pricing Button Position', 'type' => 'text'],
            ],
            'create_transaction' => [
                ['key' => 'user_id', 'label' => 'User ID', 'type' => 'number', 'required' => true],
                ['key' => 'product_id', 'label' => 'Membership ID', 'type' => 'number', 'required' => true],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true],
                ['key' => 'total', 'label' => 'Total', 'type' => 'number'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => self::get_transaction_status_options()],
                ['key' => 'gateway', 'label' => 'Gateway', 'type' => 'text'],
                ['key' => 'subscription_id', 'label' => 'Subscription ID', 'type' => 'number'],
            ],
            'update_transaction_status' => [
                ['key' => 'transaction_id', 'label' => 'Transaction ID', 'type' => 'number', 'required' => true],
                ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => self::get_transaction_status_options(), 'required' => true],
            ],
            'refund_transaction' => [
                ['key' => 'transaction_id', 'label' => 'Transaction ID', 'type' => 'number', 'required' => true],
            ],
            'create_subscription' => [
                ['key' => 'user_id', 'label' => 'User ID', 'type' => 'number', 'required' => true],
                ['key' => 'product_id', 'label' => 'Membership ID', 'type' => 'number', 'required' => true],
                ['key' => 'price', 'label' => 'Price', 'type' => 'number', 'required' => true],
                ['key' => 'period', 'label' => 'Period', 'type' => 'number'],
                ['key' => 'period_type', 'label' => 'Period Type', 'type' => 'select', 'options' => self::get_period_type_options()],
                ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => self::get_subscription_status_options()],
                ['key' => 'gateway', 'label' => 'Gateway', 'type' => 'text'],
            ],
            'update_subscription_status' => [
                ['key' => 'subscription_id', 'label' => 'Subscription ID', 'type' => 'number', 'required' => true],
                ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => self::get_subscription_status_options(), 'required' => true],
            ],
            'cancel_subscription' => [
                ['key' => 'subscription_id', 'label' => 'Subscription ID', 'type' => 'number', 'required' => true],
            ],
            'suspend_subscription' => [
                ['key' => 'subscription_id', 'label' => 'Subscription ID', 'type' => 'number', 'required' => true],
            ],
            'resume_subscription' => [
                ['key' => 'subscription_id', 'label' => 'Subscription ID', 'type' => 'number', 'required' => true],
            ],
        ];

        return $schemas[$action] ?? [];
    }

    public static function execute_node(array $node, array $input): array
    {
        $event = $node['data']['event'] ?? ($node['config']['action'] ?? '');
        $config = $node['data']['config'] ?? ($node['config']['data'] ?? []);

        switch ($event) {
            case 'create_member':
                return static::action_create_member($config, $input);
            case 'create_membership':
                return static::action_create_membership($config, $input);
            case 'update_membership':
                return static::action_update_membership($config, $input);
            case 'create_transaction':
                return static::action_create_transaction($config, $input);
            case 'update_transaction_status':
                return static::action_update_transaction_status($config, $input);
            case 'refund_transaction':
                return static::action_refund_transaction($config, $input);
            case 'create_subscription':
                return static::action_create_subscription($config, $input);
            case 'update_subscription_status':
                return static::action_update_subscription_status($config, $input);
            case 'cancel_subscription':
                return static::action_cancel_subscription($config, $input);
            case 'suspend_subscription':
                return static::action_suspend_subscription($config, $input);
            case 'resume_subscription':
                return static::action_resume_subscription($config, $input);
            default:
                return ['port' => 'main', 'data' => $input];
        }
    }
}
