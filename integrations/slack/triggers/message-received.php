<?php

namespace Zaplane\Integrations\Slack\Triggers;

use Zaplane\Framework\Classes\BaseTrigger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Slack Message Received Trigger
 *
 * Fires when a message is received via Slack webhook.
 */
class MessageReceived extends BaseTrigger {

    /**
     * Trigger key (lowercase with underscores)
     */
    public static function get_key(): string {
        return 'message_received';
    }

    /**
     * Human-readable label
     */
    public static function get_label(): string {
        return 'Message Received';
    }

    /**
     * WordPress hook this trigger listens to
     */
    public static function get_hook(): string {
        return 'slack_webhook_message';
    }

    /**
     * Trigger description
     */
    public static function get_description(): string {
        return 'Fires when a message is received via Slack Events API or webhook.';
    }

    /**
     * Configuration schema for this trigger
     */
    public static function get_config_schema(): array {
        return [
            [
                'key'         => 'channel_filter',
                'type'        => 'text',
                'label'       => 'Channel Filter',
                'placeholder' => 'Leave empty for all channels',
                'required'    => false,
                'help'        => 'Optionally filter messages from a specific channel ID.',
            ],
        ];
    }

    /**
     * Output schema - what data this trigger produces
     */
    public static function get_output_schema(): array {
        return [
            'message'    => 'string',
            'channel'    => 'string',
            'user'       => 'string',
            'ts'         => 'string',
            'event_type' => 'string',
        ];
    }

    /**
     * Filter trigger based on configuration
     */
    public static function matches( array $node, array $hook_args ): bool {
        $config = $node['config'] ?? $node['data']['config'] ?? [];

        // If channel filter is set, check if it matches
        if ( ! empty( $config['channel_filter'] ) ) {
            $event   = $hook_args[0] ?? [];
            $channel = $event['channel'] ?? '';

            if ( $channel !== $config['channel_filter'] ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve trigger payload from webhook arguments
     */
    public static function resolve( array $node, array $hook_args ) {
        $event = $hook_args[0] ?? [];

        // Basic validation
        if ( empty( $event ) ) {
            return false;
        }

        return [
            'message'    => $event['text'] ?? '',
            'channel'    => $event['channel'] ?? '',
            'user'       => $event['user'] ?? '',
            'ts'         => $event['ts'] ?? '',
            'event_type' => $event['type'] ?? 'message',
        ];
    }
}
