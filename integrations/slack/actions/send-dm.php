<?php

namespace Zaplane\Integrations\Slack\Actions;

use Zaplane\Framework\Classes\BaseAction;
use Zaplane\Integrations\Slack\SlackApi;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Slack Send Direct Message Action
 *
 * Sends a direct message to a Slack user.
 */
class SendDm extends BaseAction {

    /**
     * Action key (lowercase with underscores)
     */
    public static function get_key(): string {
        return 'send_dm';
    }

    /**
     * Human-readable label
     */
    public static function get_label(): string {
        return 'Send Direct Message';
    }

    /**
     * Action description
     */
    public static function get_description(): string {
        return 'Send a direct message to a Slack user.';
    }

    /**
     * Configuration schema for this action
     */
    public static function get_config_schema(): array {
        return [
            [
                'key'         => 'user_id',
                'type'        => 'text',
                'label'       => 'User ID',
                'placeholder' => 'Slack user ID (e.g., U12345678)',
                'required'    => true,
                'help'        => 'The Slack user ID to send the message to. You can find this in the user\'s profile.',
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
            'slack_dm_channel' => 'string',
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
        $user_id = $config['user_id'] ?? '';
        $text    = $config['text'] ?? '';

        if ( empty( $user_id ) ) {
            throw new \Exception( 'User ID is required' );
        }

        if ( empty( $text ) ) {
            throw new \Exception( 'Message text is required' );
        }

        // First, open a DM channel with the user
        $dm_body = SlackApi::call( 'conversations.open', [
            'users' => $user_id,
        ], $token );

        $channel_id = $dm_body['channel']['id'] ?? '';

        if ( empty( $channel_id ) ) {
            throw new \Exception( 'Failed to open DM channel with user: ' . $user_id );
        }

        // Now send the message to the DM channel
        $body = SlackApi::call( 'chat.postMessage', [
            'channel' => $channel_id,
            'text'    => $text,
        ], $token );

        // Return success with merged data
        return static::success( $input, [
            'slack_message_ts' => $body['ts'] ?? '',
            'slack_channel'    => $body['channel'] ?? '',
            'slack_dm_channel' => $channel_id,
        ] );
    }
}
