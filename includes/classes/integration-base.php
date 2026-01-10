<?php
namespace Zaplane\Classes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class IntegrationBase {

    /* ---------------------------------------------------------
     * Core identity
     * --------------------------------------------------------- */

    abstract public static function get_slug(): string;

    public static function get_name(): string {
        return ucfirst( static::get_slug() );
    }

    public static function get_icon(): string {
        return '';
    }

    /* ---------------------------------------------------------
     * Trigger & Action Definitions
     * --------------------------------------------------------- */

    /**
     * Triggers exposed by this integration
     * [
     *   'publish_post' => [
     *      'label' => 'Post Published',
     *      'hook'  => 'publish_post'
     *   ]
     * ]
     */
    public static function get_triggers(): array {
        return [];
    }

    /**
     * Actions exposed by this integration
     * [
     *   'send_message' => [
     *      'label' => 'Send Message'
     *   ]
     * ]
     */
    public static function get_actions(): array {
        return [];
    }

    /* ---------------------------------------------------------
     * Execution
     * --------------------------------------------------------- */

    /**
     * Trigger resolver
     * Called when WP hook fires
     */
    public static function resolve_trigger( array $node, array $hook_args ) {
        return false;
    }

    /**
     * Execute action / logic node
     */
    public static function execute_node( array $node, array $input ): array {
        return [
            'port' => 'main',
            'data' => $input,
        ];
    }

    /* ---------------------------------------------------------
     * Validation & Config
     * --------------------------------------------------------- */

    public static function validate_config( array $config ): bool {
        return true;
    }

    public static function get_config_schema(): array {
        return [];
    }

    /* ---------------------------------------------------------
     * Ports / Branching
     * --------------------------------------------------------- */

    public static function get_output_ports(): array {
        return [ 'main' ];
    }

    /* ---------------------------------------------------------
     * Capabilities
     * --------------------------------------------------------- */

    public static function supports_webhook(): bool {
        return false;
    }

    public static function supports_polling(): bool {
        return false;
    }

    public static function get_rate_limit(): int {
        return 0; // requests per minute, 0 = unlimited
    }


    /**
     * Schema for trigger config UI
     */
    public static function get_trigger_config_schema( string $trigger ): array {
        return [];
    }

    /**
     * Schema for action config UI
     */
    public static function get_action_config_schema( string $action ): array {
        return [];
    }

    /**
     * Dynamic option loaders for UI
     * [
     *   'posts' => callable,
     *   'post_types' => callable
     * ]
     */
    public static function get_dynamic_fields(): array {
        return [];
    }

    /* ---------------------------------------------------------
     * Connection & Authentication
     * --------------------------------------------------------- */

    /**
     * Whether this integration requires a connection
     * Override to return true for external services (Slack, Gmail, etc.)
     */
    public static function requires_connection(): bool {
        return false;
    }

    /**
     * Get authentication type for this integration
     * @return string 'none' | 'api_key' | 'oauth2' | 'basic'
     */
    public static function get_auth_type(): string {
        return 'none';
    }

    /**
     * Get authentication field definitions for UI
     * Used for API key and basic auth flows
     *
     * Example return for API key auth:
     * [
     *   'api_key' => [
     *     'type' => 'password',
     *     'label' => 'API Key',
     *     'required' => true,
     *     'help' => 'Find this in your app settings'
     *   ]
     * ]
     *
     * @return array Field definitions
     */
    public static function get_auth_fields(): array {
        return [];
    }

    /**
     * Test connection with provided credentials
     * Must be implemented by integrations that requires_connection()
     *
     * @param array $credentials Decrypted credentials
     * @return array ['success' => bool, 'message' => string, 'details' => array]
     */
    public static function test_connection( array $credentials ): array {
        return [
            'success' => true,
            'message' => 'Connection test not implemented for this integration',
            'details' => [],
        ];
    }

    /* ---------------------------------------------------------
     * OAuth 2.0 Methods (override for OAuth integrations)
     * --------------------------------------------------------- */

    /**
     * Get OAuth 2.0 authorization URL
     * Override for OAuth2 integrations
     *
     * @param string $redirect_uri Callback URL
     * @param string $state        CSRF state token
     * @return string|null Authorization URL or null if not OAuth2
     */
    public static function get_oauth_auth_url( string $redirect_uri, string $state ): ?string {
        return null;
    }

    /**
     * Exchange OAuth authorization code for tokens
     * Override for OAuth2 integrations
     *
     * @param string $code         Authorization code from provider
     * @param string $redirect_uri Callback URL used in auth request
     * @return array Token data ['access_token', 'refresh_token', 'expires_in', ...]
     * @throws \Exception on failure
     */
    public static function exchange_oauth_code( string $code, string $redirect_uri ): array {
        return [];
    }

    /**
     * Refresh OAuth access token
     * Override for OAuth2 integrations that support token refresh
     *
     * @param string $refresh_token Refresh token
     * @return array New token data ['access_token', 'refresh_token', 'expires_in', ...]
     * @throws \Exception on failure
     */
    public static function refresh_oauth_token( string $refresh_token ): array {
        return [];
    }

    /**
     * Get required OAuth scopes
     * Override for OAuth2 integrations
     *
     * @return array List of scope strings
     */
    public static function get_oauth_scopes(): array {
        return [];
    }
}
