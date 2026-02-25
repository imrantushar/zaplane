<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

class Memberpress extends IntegrationBase
{
    public static function get_slug(): string
    {
        return 'memberpress';
    }

    public static function get_triggers(): array
    {
        $events = self::get_memberpress_events();
        $triggers = [];

        foreach ($events as $event => $label) {
            $key = str_replace('-', '_', $event);
            $triggers[$key] = [
                'label' => $label,
                'hook' => 'mepr_event_' . $event,
            ];
        }

        return $triggers;
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

        return $payload;
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
        $event = $node['data']['event'] ?? '';
        $config = $node['data']['config'] ?? [];
        $method = 'action_' . $event;

        if (method_exists(static::class, $method)) {
            return static::$method($config, $input);
        }

        return ['port' => 'main', 'data' => $input];
    }

    protected static function action_create_member(array $config, array $input): array
    {
        if (!class_exists('MeprUser')) {
            return self::action_error('MemberPress is not available', $input);
        }

        $email = trim($config['email'] ?? '');
        if ($email === '') {
            return self::action_error('Email is required', $input);
        }

        try {
            $user = new \MeprUser();
            $user->user_email = $email;

            if (!empty($config['username'])) {
                $user->user_login = $config['username'];
            }

            if (!empty($config['password'])) {
                $user->user_pass = $config['password'];
            }

            if (!empty($config['first_name'])) {
                $user->first_name = $config['first_name'];
            }

            if (!empty($config['last_name'])) {
                $user->last_name = $config['last_name'];
            }

            $user_id = $user->store();
        } catch (\Throwable $e) {
            return self::action_error($e->getMessage(), $input);
        }

        if (empty($user_id)) {
            return self::action_error('Failed to create member', $input);
        }

        return self::action_success(array_merge($input, [
            'user_id' => (int) $user_id,
            'email' => $email,
        ]));
    }

    protected static function action_create_membership(array $config, array $input): array
    {
        if (!class_exists('MeprProduct')) {
            return self::action_error('MemberPress is not available', $input);
        }

        $name = trim($config['name'] ?? '');
        $price = $config['price'] ?? '';

        if ($name === '' || $price === '') {
            return self::action_error('Membership name and price are required', $input);
        }

        try {
            $product = new \MeprProduct();
            $product->post_title = $name;
            if (array_key_exists('description', $config)) {
                $product->post_content = $config['description'];
            }
            if (!empty($config['status'])) {
                $product->post_status = $config['status'];
            }

            $product->price = (float) $price;

            if (isset($config['period']) && $config['period'] !== '') {
                $product->period = (int) $config['period'];
            }
            if (!empty($config['period_type'])) {
                $product->period_type = $config['period_type'];
            }
            if (array_key_exists('trial', $config)) {
                $product->trial = self::to_bool($config['trial']);
            }
            if (isset($config['trial_days']) && $config['trial_days'] !== '') {
                $product->trial_days = (int) $config['trial_days'];
            }
            if (isset($config['trial_amount']) && $config['trial_amount'] !== '') {
                $product->trial_amount = (float) $config['trial_amount'];
            }
            if (!empty($config['expire_type'])) {
                $product->expire_type = $config['expire_type'];
            }
            if (isset($config['expire_after']) && $config['expire_after'] !== '') {
                $product->expire_after = (int) $config['expire_after'];
            }
            if (!empty($config['expire_unit'])) {
                $product->expire_unit = $config['expire_unit'];
            }
            if (!empty($config['expire_fixed'])) {
                $product->expire_fixed = $config['expire_fixed'];
            }
            if (array_key_exists('allow_renewal', $config)) {
                $product->allow_renewal = self::to_bool($config['allow_renewal']);
            }

            $membership_id = $product->store();
        } catch (\Throwable $e) {
            return self::action_error($e->getMessage(), $input);
        }

        if (empty($membership_id)) {
            return self::action_error('Failed to create membership', $input);
        }

        return self::action_success(array_merge($input, [
            'membership_id' => (int) $membership_id,
            'membership_name' => $name,
        ]));
    }

    protected static function action_update_membership(array $config, array $input): array
    {
        if (!class_exists('MeprProduct')) {
            return self::action_error('MemberPress is not available', $input);
        }

        $membership_id = (int) ($config['membership_id'] ?? 0);
        if (!$membership_id) {
            return self::action_error('Membership ID is required', $input);
        }

        $product = new \MeprProduct($membership_id);
        if (empty($product->ID)) {
            return self::action_error('Membership not found', $input);
        }

        if (!empty($config['name'])) {
            $product->post_title = $config['name'];
        }
        if (array_key_exists('description', $config)) {
            $product->post_content = $config['description'];
        }
        if (!empty($config['status'])) {
            $product->post_status = $config['status'];
        }
        if (isset($config['price']) && $config['price'] !== '') {
            $product->price = (float) $config['price'];
        }
        if (isset($config['period']) && $config['period'] !== '') {
            $product->period = (int) $config['period'];
        }
        if (!empty($config['period_type'])) {
            $product->period_type = $config['period_type'];
        }
        if (array_key_exists('trial', $config)) {
            $product->trial = self::to_bool($config['trial']);
        }
        if (isset($config['trial_days']) && $config['trial_days'] !== '') {
            $product->trial_days = (int) $config['trial_days'];
        }
        if (isset($config['trial_amount']) && $config['trial_amount'] !== '') {
            $product->trial_amount = (float) $config['trial_amount'];
        }
        if (!empty($config['expire_type'])) {
            $product->expire_type = $config['expire_type'];
        }
        if (isset($config['expire_after']) && $config['expire_after'] !== '') {
            $product->expire_after = (int) $config['expire_after'];
        }
        if (!empty($config['expire_unit'])) {
            $product->expire_unit = $config['expire_unit'];
        }
        if (!empty($config['expire_fixed'])) {
            $product->expire_fixed = $config['expire_fixed'];
        }
        if (array_key_exists('allow_renewal', $config)) {
            $product->allow_renewal = self::to_bool($config['allow_renewal']);
        }

        try {
            $product->store();
        } catch (\Throwable $e) {
            return self::action_error($e->getMessage(), $input);
        }

        return self::action_success(array_merge($input, [
            'membership_id' => $membership_id,
        ]));
    }

    protected static function action_create_transaction(array $config, array $input): array
    {
        if (!class_exists('MeprTransaction')) {
            return self::action_error('MemberPress is not available', $input);
        }

        $user_id = (int) ($config['user_id'] ?? 0);
        $product_id = (int) ($config['product_id'] ?? 0);
        $amount = $config['amount'] ?? '';

        if (!$user_id || !$product_id || $amount === '') {
            return self::action_error('User ID, membership ID and amount are required', $input);
        }

        try {
            $txn = new \MeprTransaction();
            $txn->user_id = $user_id;
            $txn->product_id = $product_id;
            $txn->amount = (float) $amount;
            $txn->total = isset($config['total']) && $config['total'] !== '' ? (float) $config['total'] : (float) $amount;
            $txn->status = $config['status'] ?? \MeprTransaction::$complete_str;
            $txn->gateway = $config['gateway'] ?? \MeprTransaction::$manual_gateway_str;

            if (!empty($config['subscription_id'])) {
                $txn->subscription_id = (int) $config['subscription_id'];
            }

            $txn_id = $txn->store();
        } catch (\Throwable $e) {
            return self::action_error($e->getMessage(), $input);
        }

        if (empty($txn_id)) {
            return self::action_error('Failed to create transaction', $input);
        }

        return self::action_success(array_merge($input, [
            'transaction_id' => (int) $txn_id,
            'user_id' => $user_id,
            'membership_id' => $product_id,
            'amount' => (float) $amount,
        ]));
    }

    protected static function action_update_transaction_status(array $config, array $input): array
    {
        if (!class_exists('MeprTransaction')) {
            return self::action_error('MemberPress is not available', $input);
        }

        $txn_id = (int) ($config['transaction_id'] ?? 0);
        $status = $config['status'] ?? '';

        if (!$txn_id || $status === '') {
            return self::action_error('Transaction ID and status are required', $input);
        }

        $txn = new \MeprTransaction($txn_id);
        if (empty($txn->id)) {
            return self::action_error('Transaction not found', $input);
        }

        $txn->status = $status;
        $txn->store();

        return self::action_success(array_merge($input, [
            'transaction_id' => $txn_id,
            'status' => $status,
        ]));
    }

    protected static function action_refund_transaction(array $config, array $input): array
    {
        if (!class_exists('MeprTransaction')) {
            return self::action_error('MemberPress is not available', $input);
        }

        $txn_id = (int) ($config['transaction_id'] ?? 0);
        if (!$txn_id) {
            return self::action_error('Transaction ID is required', $input);
        }

        $txn = new \MeprTransaction($txn_id);
        if (empty($txn->id)) {
            return self::action_error('Transaction not found', $input);
        }

        $refunded = $txn->refund();
        if (!$refunded) {
            return self::action_error('Failed to refund transaction', $input);
        }

        return self::action_success(array_merge($input, [
            'transaction_id' => $txn_id,
            'refunded' => true,
        ]));
    }

    protected static function action_create_subscription(array $config, array $input): array
    {
        if (!class_exists('MeprSubscription')) {
            return self::action_error('MemberPress is not available', $input);
        }

        $user_id = (int) ($config['user_id'] ?? 0);
        $product_id = (int) ($config['product_id'] ?? 0);
        $price = $config['price'] ?? '';

        if (!$user_id || !$product_id || $price === '') {
            return self::action_error('User ID, membership ID and price are required', $input);
        }

        $sub = new \MeprSubscription();
        $sub->user_id = $user_id;
        $sub->product_id = $product_id;
        $sub->price = (float) $price;
        $sub->period = (int) ($config['period'] ?? 1);
        $sub->period_type = $config['period_type'] ?? 'months';
        $sub->status = $config['status'] ?? \MeprSubscription::$active_str;
        $sub->gateway = $config['gateway'] ?? 'manual';

        $sub_id = $sub->store();

        if (empty($sub_id)) {
            return self::action_error('Failed to create subscription', $input);
        }

        return self::action_success(array_merge($input, [
            'subscription_id' => (int) $sub_id,
            'user_id' => $user_id,
            'membership_id' => $product_id,
        ]));
    }

    protected static function action_update_subscription_status(array $config, array $input): array
    {
        if (!class_exists('MeprSubscription')) {
            return self::action_error('MemberPress is not available', $input);
        }

        $sub_id = (int) ($config['subscription_id'] ?? 0);
        $status = $config['status'] ?? '';

        if (!$sub_id || $status === '') {
            return self::action_error('Subscription ID and status are required', $input);
        }

        $sub = new \MeprSubscription($sub_id);
        if (empty($sub->id)) {
            return self::action_error('Subscription not found', $input);
        }

        $sub->status = $status;
        $sub->store();

        return self::action_success(array_merge($input, [
            'subscription_id' => $sub_id,
            'status' => $status,
        ]));
    }

    protected static function action_cancel_subscription(array $config, array $input): array
    {
        if (!class_exists('MeprSubscription')) {
            return self::action_error('MemberPress is not available', $input);
        }

        $sub_id = (int) ($config['subscription_id'] ?? 0);
        if (!$sub_id) {
            return self::action_error('Subscription ID is required', $input);
        }

        $sub = new \MeprSubscription($sub_id);
        if (empty($sub->id)) {
            return self::action_error('Subscription not found', $input);
        }

        $cancelled = $sub->cancel();
        if (!$cancelled) {
            return self::action_error('Failed to cancel subscription', $input);
        }

        return self::action_success(array_merge($input, [
            'subscription_id' => $sub_id,
            'cancelled' => true,
        ]));
    }

    protected static function action_suspend_subscription(array $config, array $input): array
    {
        if (!class_exists('MeprSubscription')) {
            return self::action_error('MemberPress is not available', $input);
        }

        $sub_id = (int) ($config['subscription_id'] ?? 0);
        if (!$sub_id) {
            return self::action_error('Subscription ID is required', $input);
        }

        $sub = new \MeprSubscription($sub_id);
        if (empty($sub->id)) {
            return self::action_error('Subscription not found', $input);
        }

        $suspended = $sub->suspend();
        if (!$suspended) {
            return self::action_error('Failed to suspend subscription', $input);
        }

        return self::action_success(array_merge($input, [
            'subscription_id' => $sub_id,
            'suspended' => true,
        ]));
    }

    protected static function action_resume_subscription(array $config, array $input): array
    {
        if (!class_exists('MeprSubscription')) {
            return self::action_error('MemberPress is not available', $input);
        }

        $sub_id = (int) ($config['subscription_id'] ?? 0);
        if (!$sub_id) {
            return self::action_error('Subscription ID is required', $input);
        }

        $sub = new \MeprSubscription($sub_id);
        if (empty($sub->id)) {
            return self::action_error('Subscription not found', $input);
        }

        $resumed = $sub->resume();
        if (!$resumed) {
            return self::action_error('Failed to resume subscription', $input);
        }

        return self::action_success(array_merge($input, [
            'subscription_id' => $sub_id,
            'resumed' => true,
        ]));
    }

    protected static function action_error(string $message, array $input = []): array
    {
        return [
            'port' => 'main',
            'data' => array_merge($input, [
                'error' => $message,
            ]),
        ];
    }

    protected static function action_success(array $data = []): array
    {
        return [
            'port' => 'main',
            'data' => $data,
        ];
    }

    protected static function to_bool($value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
    }

    protected static function decode_event_args($args)
    {
        if (is_string($args)) {
            $decoded = json_decode($args, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $args;
    }

    protected static function get_transaction_status_options(): array
    {
        return [
            ['label' => 'Pending', 'value' => 'pending'],
            ['label' => 'Complete', 'value' => 'complete'],
            ['label' => 'Confirmed', 'value' => 'confirmed'],
            ['label' => 'Failed', 'value' => 'failed'],
            ['label' => 'Refunded', 'value' => 'refunded'],
        ];
    }

    protected static function get_subscription_status_options(): array
    {
        return [
            ['label' => 'Active', 'value' => 'active'],
            ['label' => 'Pending', 'value' => 'pending'],
            ['label' => 'Suspended', 'value' => 'suspended'],
            ['label' => 'Cancelled', 'value' => 'cancelled'],
        ];
    }

    protected static function get_membership_period_type_options(): array
    {
        return [
            ['label' => 'Weeks', 'value' => 'weeks'],
            ['label' => 'Months', 'value' => 'months'],
            ['label' => 'Years', 'value' => 'years'],
            ['label' => 'Lifetime', 'value' => 'lifetime'],
        ];
    }

    protected static function get_post_status_options(): array
    {
        return [
            ['label' => 'Publish', 'value' => 'publish'],
            ['label' => 'Draft', 'value' => 'draft'],
            ['label' => 'Private', 'value' => 'private'],
        ];
    }

    protected static function get_yes_no_options(): array
    {
        return [
            ['label' => 'Yes', 'value' => '1'],
            ['label' => 'No', 'value' => '0'],
        ];
    }

    protected static function get_expire_type_options(): array
    {
        return [
            ['label' => 'None', 'value' => 'none'],
            ['label' => 'Delay', 'value' => 'delay'],
            ['label' => 'Fixed', 'value' => 'fixed'],
        ];
    }

    protected static function get_expire_unit_options(): array
    {
        return [
            ['label' => 'Days', 'value' => 'days'],
            ['label' => 'Weeks', 'value' => 'weeks'],
            ['label' => 'Months', 'value' => 'months'],
            ['label' => 'Years', 'value' => 'years'],
        ];
    }

    protected static function get_period_type_options(): array
    {
        return [
            ['label' => 'Days', 'value' => 'days'],
            ['label' => 'Weeks', 'value' => 'weeks'],
            ['label' => 'Months', 'value' => 'months'],
            ['label' => 'Years', 'value' => 'years'],
        ];
    }

    protected static function get_memberpress_events(): array
    {
        return [
            'member-added' => 'Member Added',
            'member-signup-completed' => 'Member Signup Completed',
            'member-account-updated' => 'Member Account Updated',
            'member-deleted' => 'Member Deleted',
            'login' => 'Member Logged In',
            'subscription-created' => 'Subscription Created',
            'subscription-paused' => 'Subscription Paused',
            'subscription-resumed' => 'Subscription Resumed',
            'subscription-stopped' => 'Subscription Stopped',
            'subscription-upgraded' => 'Subscription Upgraded',
            'subscription-downgraded' => 'Subscription Downgraded',
            'subscription-upgraded-to-one-time' => 'Subscription Upgraded To One-Time',
            'subscription-upgraded-to-recurring' => 'Subscription Upgraded To Recurring',
            'subscription-downgraded-to-one-time' => 'Subscription Downgraded To One-Time',
            'subscription-downgraded-to-recurring' => 'Subscription Downgraded To Recurring',
            'subscription-expired' => 'Subscription Expired',
            'subscription-changed' => 'Subscription Changed',
            'transaction-completed' => 'Transaction Completed',
            'transaction-refunded' => 'Transaction Refunded',
            'transaction-failed' => 'Transaction Failed',
            'transaction-expired' => 'Transaction Expired',
            'offline-payment-pending' => 'Offline Payment Pending',
            'offline-payment-complete' => 'Offline Payment Complete',
            'offline-payment-refunded' => 'Offline Payment Refunded',
            'recurring-transaction-completed' => 'Recurring Transaction Completed',
            'renewal-transaction-completed' => 'Renewal Transaction Completed',
            'recurring-transaction-failed' => 'Recurring Transaction Failed',
            'recurring-transaction-expired' => 'Recurring Transaction Expired',
            'recurring-transaction-refunded' => 'Recurring Transaction Refunded',
            'non-recurring-transaction-completed' => 'Non-Recurring Transaction Completed',
            'non-recurring-transaction-expired' => 'Non-Recurring Transaction Expired',
            'account-is-active' => 'Account Is Active',
            'account-is-inactive' => 'Account Is Inactive',
        ];
    }
}
