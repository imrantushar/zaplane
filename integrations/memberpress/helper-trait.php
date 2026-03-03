<?php
namespace Zaplane\Integrations\Memberpress;

trait HelperTrait
{
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

    protected static function get_pricing_display_options(): array
    {
        return [
            ['label' => 'Auto', 'value' => 'auto'],
            ['label' => 'Custom', 'value' => 'custom'],
            ['label' => 'None', 'value' => 'none'],
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

}
