<?php

namespace Zaplane\Integrations;

if (! defined('ABSPATH')) {
    exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Mailster extends IntegrationBase
{
    public static function get_slug(): string
    {
        return 'mailster';
    }

    public static function get_name(): string
    {
        return 'Mailster';
    }

    public static function get_icon(): string
    {
        return 'mailster.svg';
    }

    public static function get_triggers(): array
    {
        return [
            'add_subscriber' => [
                'label' => 'Subscriber Added',
                'hook'  => 'mailster_add_subscriber',
            ],
        ];
    }

    public static function get_trigger_config_schema(string $trigger): array
    {
        // No configuration needed
        return [];
    }

    public static function resolve_trigger(array $node, array $args): ?array
    {
        if (($node['event'] ?? '') !== 'add_subscriber') {
            return null;
        }

        if (count($args) < 1) {
            return null;
        }

        $userId = (int) $args[0];

        if ($userId === 0) {
            return null;
        }

        // Check if Mailster is active
        if (!class_exists('MailsterSubscribers')) {
            return null;
        }

        // Get subscriber data
        $mailsterSubscribers = new \MailsterSubscribers();
        $subscriber = (array) $mailsterSubscribers->get($userId, true);

        if (empty($subscriber)) {
            return null;
        }

        return [
            'user_id'     => $userId,
            'email'       => $subscriber['email'] ?? '',
            'firstname'   => $subscriber['firstname'] ?? '',
            'lastname'    => $subscriber['lastname'] ?? '',
            'status'      => $subscriber['status'] ?? '',
            'lists'       => $subscriber['lists'] ?? [],
            'ip'          => $subscriber['ip'] ?? '',
            'signup_ip'   => $subscriber['signupip'] ?? '',
            'timestamp'   => $subscriber['timestamp'] ?? '',
            'subscriber_data' => $subscriber,
        ];
    }

    public static function get_actions(): array
    {
        return [];
    }

    public static function get_action_config_schema(string $action): array
    {
        return [];
    }

    public static function execute_node(array $node, array $input): array
    {
        return [
            'port' => 'main',
            'data' => $input,
        ];
    }

    public static function get_dynamic_queries(): array
    {
        return [];
    }

    public static function get_output_ports(): array
    {
        return [
            'main' => 'Main output',
        ];
    }
}
