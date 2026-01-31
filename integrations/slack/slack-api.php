<?php

namespace Zaplane\Integrations\Slack;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Slack API Helper
 *
 * Shared helper class for making Slack API calls.
 * Used by all Slack actions and the integration class.
 */
class SlackApi {

    /**
     * Slack API base URL
     */
    public const API_BASE_URL = 'https://slack.com/api';

    /**
     * Slack OAuth authorization URL
     */
    public const OAUTH_AUTHORIZE_URL = 'https://slack.com/oauth/v2/authorize';

    /**
     * Slack OAuth token exchange URL
     */
    public const OAUTH_TOKEN_URL = 'https://slack.com/api/oauth.v2.access';

    /**
     * Make a Slack API call.
     *
     * @param string $method Slack API method (e.g., 'chat.postMessage')
     * @param array  $data   Request body data
     * @param string $token  OAuth or bot token
     * @param string $http   HTTP method ('GET' or 'POST')
     * @return array Response body
     * @throws \Exception On API error
     */
    public static function call( string $method, array $data, string $token, string $http = 'POST' ): array {
        $url  = self::API_BASE_URL . '/' . $method;
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json; charset=utf-8',
            ],
        ];

        if ( $http === 'GET' ) {
            $response = wp_remote_get( $url, $args );
        } else {
            $args['body'] = wp_json_encode( $data );
            $response     = wp_remote_post( $url, $args );
        }

        if ( is_wp_error( $response ) ) {
            throw new \Exception( 'Slack API request failed: ' . $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['ok'] ) ) {
            throw new \Exception( 'Slack API error: ' . ( $body['error'] ?? 'Unknown error' ) );
        }

        return $body;
    }

    /**
     * Extract token from credentials array.
     *
     * Handles both OAuth tokens (access_token) and direct bot tokens.
     *
     * @param array $credentials Decrypted credentials
     * @return string Token or empty string
     */
    public static function getToken( array $credentials ): string {
        return $credentials['access_token'] ?? $credentials['bot_token'] ?? '';
    }

    /**
     * Validate that we have a token.
     *
     * @param array $credentials Decrypted credentials
     * @throws \Exception If no token found
     */
    public static function requireToken( array $credentials ): string {
        $token = self::getToken( $credentials );

        if ( empty( $token ) ) {
            throw new \Exception( 'Slack access token is missing. Please reconnect your Slack account.' );
        }

        return $token;
    }
}
