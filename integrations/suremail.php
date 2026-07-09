<?php

namespace Zaplane\Integrations;

if (! defined('ABSPATH')) {
    exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Suremail extends IntegrationBase
{
    public static function get_slug(): string
    {
        return 'suremail';
    }

    public static function get_name(): string
    {
        return 'SureMail';
    }

    public static function get_icon(): string
    {
        return 'suremail.svg';
    }

    public static function get_triggers(): array
    {
        return [
            'email_sent_successfully' => [
                'label' => 'Email Sent Successfully',
                'hook'  => 'wp_mail_succeeded',
            ],
            'email_sent_failed' => [
                'label' => 'Email Sent Failed',
                'hook'  => 'wp_mail_failed',
            ],
        ];
    }

    public static function get_trigger_config_schema(string $trigger): array
    {
        // SureMail triggers don't need additional configuration
        // since they don't have specific forms/IDs to filter by
        return [];
    }

    public static function resolve_trigger(array $node, array $args): ?array
    {
        $event = $node['event'] ?? '';

        if (!in_array($event, ['email_sent_successfully', 'email_sent_failed'])) {
            return null;
        }

        // Both hooks pass a single argument - the mail data array
        if (count($args) < 1 || !is_array($args[0])) {
            return null;
        }

        $mailData = $args[0];

        if (empty($mailData)) {
            return null;
        }

        // No form_id filtering needed for SureMail
        // Return all available mail data
        return [
            'mail_data' => $mailData,
            'event'     => $event,
            // Common mail fields that might be present
            'to'        => $mailData['to'] ?? '',
            'subject'   => $mailData['subject'] ?? '',
            'message'   => $mailData['message'] ?? '',
            'headers'   => $mailData['headers'] ?? [],
            'attachments' => $mailData['attachments'] ?? [],
        ];
    }

    public static function get_trigger_sample_output(string $event): array
    {
        $mailData = [
            'to'          => 'jane.doe@example.com',
            'subject'     => 'Your receipt from Acme',
            'message'     => '<p>Thanks for your order!</p>',
            'headers'     => ['Content-Type: text/html; charset=UTF-8'],
            'attachments' => ['/var/www/uploads/invoice-1042.pdf'],
        ];

        $build = static function (string $event) use ($mailData): array {
            return [
                'mail_data'   => $mailData,
                'event'       => $event,
                'to'          => $mailData['to'],
                'subject'     => $mailData['subject'],
                'message'     => $mailData['message'],
                'headers'     => $mailData['headers'],
                'attachments' => $mailData['attachments'],
            ];
        };

        $samples = [
            'email_sent_successfully' => $build('email_sent_successfully'),
            'email_sent_failed'       => $build('email_sent_failed'),
        ];

        if (isset($samples[$event])) {
            return $samples[$event];
        }

        return $build('' !== $event ? $event : 'email_sent_successfully');
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
        // SureMail doesn't have dynamic queries like forms
        return [];
    }

    public static function get_output_ports(): array
    {
        return [
            'main' => 'Main output',
        ];
    }
}
