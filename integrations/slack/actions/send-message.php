<?php

namespace Zaplane\Integrations\Slack\Actions;

use Zaplane\Framework\Classes\BaseAction;
use Zaplane\Integrations\Slack\SlackApi;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Slack Send Message Action
 *
 * Sends a message to a Slack channel.
 */
class SendMessage extends BaseAction {

    /**
     * Action key (lowercase with underscores)
     */
    public static function get_key(): string {
        return 'send_message';
    }

    /**
     * Human-readable label
     */
    public static function get_label(): string {
        return 'Send Message';
    }

    /**
     * Action description
     */
    public static function get_description(): string {
        return 'Send a message to a Slack channel.';
    }

    /**
     * Configuration schema for this action
     */
    public static function get_config_schema(): array {
        return [
            [
                'key'         => 'channel',
                'type'        => 'text',
                'label'       => 'Channel',
                'placeholder' => '#general or channel ID',
                'required'    => true,
                'help'        => 'Enter a channel name (e.g., #general) or channel ID.',
            ],
            [
                'key'         => 'text',
                'type'        => 'textarea',
                'label'       => 'Message',
                'placeholder' => 'Enter your message...',
                'required'    => true,
                'help'        => 'The message text to send. Supports Slack markdown formatting.',
            ],
        ];
    }

    /**
     * Output schema - what additional data this action produces
     */
    public static function get_output_schema(): array {
        return [
            'slack_message_ts' => 'string',
            'slack_channel'    => 'string',
        ];
    }

    /**
     * Execute the action
     *
     * @param array $config      Resolved configuration values
     * @param array $input       Data from previous nodes
     * @param array $credentials Decrypted connection credentials
     * @return array ['port' => 'main', 'data' => [...]]
     */
    public static function execute( array $config, array $input, array $credentials = [] ): array {
        // Get and validate token
        $token = SlackApi::requireToken( $credentials );

        // Validate required fields
        $channel = $config['channel'] ?? '';
        $text    = $config['text'] ?? '';

        if ( empty( $channel ) ) {
            throw new \Exception( 'Channel is required' );
        }

        if ( empty( $text ) ) {
            throw new \Exception( 'Message text is required' );
        }

        // Make API call
        $body = SlackApi::call( 'chat.postMessage', [
            'channel' => $channel,
            'text'    => $text,
        ], $token );

        // Return success with merged data
        return static::success( $input, [
            'slack_message_ts' => $body['ts'] ?? '',
            'slack_channel'    => $body['channel'] ?? '',
        ] );
    }
}
