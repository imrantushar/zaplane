<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ExternalAppIntegration
 *
 * Base class for external service integrations (Slack, Mailchimp, Stripe, etc.).
 * Extends IntegrationBase with:
 *  - `requires_connection()` pre-set to true
 *  - `get_category()` pre-set to 'app'
 *  - Helper `api_request()` for HTTP calls with error handling
 *  - Enforced abstract methods for auth fields & connection testing
 *
 * Junior developers extend this class to build new external app integrations.
 *
 * Example:
 * ```php
 * class Mailchimp extends ExternalAppIntegration {
 *     public static function get_slug(): string { return 'mailchimp'; }
 *     public static function get_name(): string { return 'Mailchimp'; }
 *     public static function get_auth_type(): string { return 'api_key'; }
 *
 *     public static function get_auth_fields(?string $auth_type = null): array {
 *         return [
 *             'api_key' => ['type' => 'password', 'label' => 'API Key', 'required' => true],
 *         ];
 *     }
 *
 *     public static function test_connection(array $credentials): array {
 *         $response = self::api_request('GET', 'https://usX.api.mailchimp.com/3.0/', [], $credentials);
 *         return ['success' => true, 'message' => 'Connected', 'details' => $response];
 *     }
 *
 *     public static function get_actions(): array {
 *         return [
 *             'add_subscriber' => ['label' => 'Add Subscriber'],
 *         ];
 *     }
 *
 *     public static function execute_node(array $node, array $input): array {
 *         $config = $node['config']['data'] ?? $node['data']['config'] ?? [];
 *         $creds  = $node['_connection_credentials'] ?? [];
 *         // ... call API using self::api_request() ...
 *         return ['port' => 'main', 'data' => array_merge($input, $result)];
 *     }
 * }
 * ```
 */
abstract class ExternalAppIntegration extends IntegrationBase {

    /* ---------------------------------------------------------
     * Pre-set: external apps always need connections
     * --------------------------------------------------------- */

    public static function requires_connection(): bool {
        return true;
    }

    public static function get_category(): string {
        return 'app';
    }

    /* ---------------------------------------------------------
     * HTTP Helper
     * --------------------------------------------------------- */

    /**
     * Make an HTTP API request with standardised error handling.
     *
     * @param string $method      HTTP method: GET, POST, PUT, PATCH, DELETE.
     * @param string $url         Full endpoint URL.
     * @param array  $body        Request body (auto JSON-encoded for non-GET).
     * @param array  $credentials Decrypted connection credentials.
     * @param array  $headers     Extra headers (merged with defaults).
     * @param array  $options     Extra wp_remote_request options.
     * @return array              Decoded JSON response body.
     * @throws \Exception         On transport or API error.
     */
    protected static function api_request(
        string $method,
        string $url,
        array $body = [],
        array $credentials = [],
        array $headers = [],
        array $options = []
    ): array {
        // Build authorisation header from credentials
        $auth_header = static::build_auth_header( $credentials );

        $default_headers = [
            'Content-Type' => 'application/json; charset=utf-8',
        ];

        if ( $auth_header ) {
            $default_headers['Authorization'] = $auth_header;
        }

        $merged_headers = array_merge( $default_headers, $headers );

        $request_args = array_merge([
            'method'  => strtoupper( $method ),
            'headers' => $merged_headers,
            'timeout' => 30,
        ], $options);

        if ( ! empty( $body ) && strtoupper( $method ) !== 'GET' ) {
            $request_args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $request_args );

        if ( is_wp_error( $response ) ) {
            throw new \Exception(
                sprintf( '%s API request failed: %s', static::get_name(), $response->get_error_message() )
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $raw_body    = wp_remote_retrieve_body( $response );
        $decoded     = json_decode( $raw_body, true );

        if ( $status_code >= 400 ) {
            $error_msg = $decoded['message']
                ?? $decoded['error']['message']
                ?? $decoded['error']
                ?? $raw_body;

            throw new \Exception(
                sprintf( '%s API error (%d): %s', static::get_name(), $status_code, $error_msg )
            );
        }

        return is_array( $decoded ) ? $decoded : [];
    }

    /**
     * Build an Authorization header value from credentials.
     *
     * Supports:
     *  - OAuth tokens:  Bearer <access_token>
     *  - Bot tokens:    Bearer <bot_token>
     *  - API keys:      Bearer <api_key>  (override in subclass for other schemes)
     *  - Basic auth:    Basic base64(username:password)
     *
     * Override in subclass for custom schemes.
     *
     * @param array $credentials Decrypted credentials.
     * @return string|null       Header value or null.
     */
    protected static function build_auth_header( array $credentials ): ?string {
        // OAuth / Bot token
        $token = $credentials['access_token']
            ?? $credentials['bot_token']
            ?? $credentials['api_key']
            ?? $credentials['token']
            ?? null;

        if ( $token ) {
            return 'Bearer ' . $token;
        }

        // Basic auth
        if ( ! empty( $credentials['username'] ) && ! empty( $credentials['password'] ) ) {
            return 'Basic ' . base64_encode( $credentials['username'] . ':' . $credentials['password'] );
        }

        return null;
    }
}
