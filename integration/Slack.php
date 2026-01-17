<?php
namespace Zaplane\Integration;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slack Integration
 * Supports both OAuth 2.0 and Bot Token authentication
 */
class Slack extends IntegrationBase {

	private const API_BASE_URL = 'https://slack.com/api';
	private const OAUTH_AUTHORIZE_URL = 'https://slack.com/oauth/v2/authorize';
	private const OAUTH_TOKEN_URL = 'https://slack.com/api/oauth.v2.access';

	/* ---------------------------------------------------------
	 * Core Identity
	 * --------------------------------------------------------- */

	public static function get_slug(): string {
		return 'slack';
	}

	public static function get_name(): string {
		return 'Slack';
	}

	public static function get_icon(): string {
		return 'slack';
	}

	/* ---------------------------------------------------------
	 * Triggers & Actions
	 * --------------------------------------------------------- */

	public static function get_triggers(): array {
		return array(
			'message_received' => array(
				'label' => 'Message Received',
				'hook'  => 'slack_webhook_message',
			),
		);
	}

	public static function get_actions(): array {
		return array(
			'send_message' => array( 'label' => 'Send Message' ),
			'send_dm'      => array( 'label' => 'Send Direct Message' ),
		);
	}

	public static function get_action_config_schema( string $action ): array {
		if ( $action === 'send_message' ) {
			return array(
				'channel' => array(
					'type'        => 'text',
					'label'       => 'Channel',
					'placeholder' => '#general or channel ID',
					'required'    => true,
				),
				'text'    => array(
					'type'        => 'textarea',
					'label'       => 'Message',
					'placeholder' => 'Enter your message...',
					'required'    => true,
				),
			);
		}

		if ( $action === 'send_dm' ) {
			return array(
				'user_id' => array(
					'type'        => 'text',
					'label'       => 'User ID',
					'placeholder' => 'Slack user ID',
					'required'    => true,
				),
				'text'    => array(
					'type'        => 'textarea',
					'label'       => 'Message',
					'placeholder' => 'Enter your message...',
					'required'    => true,
				),
			);
		}

		return array();
	}

	/* ---------------------------------------------------------
	 * Execution
	 * --------------------------------------------------------- */

	public static function resolve_trigger( array $node, array $args ) {
		return array( 'message' => $args[0] ?? '' );
	}

	public static function execute_node( array $node, array $input ): array {
		$action = $node['config']['action'] ?? '';
		$credentials = $node['_connection_credentials'] ?? null;

		if ( ! $credentials ) {
			throw new \Exception( 'No connection credentials available for Slack' );
		}

		// Get token - supports both OAuth (access_token) and direct bot token
		$token = $credentials['access_token'] ?? $credentials['bot_token'] ?? '';

		if ( empty( $token ) ) {
			throw new \Exception( 'Slack access token is missing' );
		}

		if ( $action === 'send_message' ) {
			return self::action_send_message( $node, $input, $token );
		}

		if ( $action === 'send_dm' ) {
			return self::action_send_dm( $node, $input, $token );
		}

		return array(
			'port' => 'main',
			'data' => $input,
		);
	}

	/**
	 * Send message to a channel
	 */
	private static function action_send_message( array $node, array $input, string $token ): array {
		$channel = $node['config']['data']['channel'] ?? '';
		$text = $node['config']['data']['text'] ?? '';

		// Variable substitution from input
		$text = self::substitute_variables( $text, $input );

		$response = wp_remote_post(
			self::API_BASE_URL . '/chat.postMessage',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json; charset=utf-8',
				),
				'body'    => wp_json_encode(
					array(
						'channel' => $channel,
						'text'    => $text,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Slack API request failed: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['ok'] ) ) {
			throw new \Exception( 'Slack API error: ' . ( $body['error'] ?? 'Unknown error' ) );
		}

		return array(
			'port' => 'main',
			'data' => array_merge(
				$input,
				array(
					'slack_message_ts' => $body['ts'] ?? '',
					'slack_channel'    => $body['channel'] ?? '',
				)
			),
		);
	}

	/**
	 * Send direct message to a user
	 */
	private static function action_send_dm( array $node, array $input, string $token ): array {
		$user_id = $node['config']['data']['user_id'] ?? '';
		$text = $node['config']['data']['text'] ?? '';

		$text = self::substitute_variables( $text, $input );

		// First, open a DM conversation
		$dm_response = wp_remote_post(
			self::API_BASE_URL . '/conversations.open',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json; charset=utf-8',
				),
				'body'    => wp_json_encode( array( 'users' => $user_id ) ),
			)
		);

		if ( is_wp_error( $dm_response ) ) {
			throw new \Exception( 'Failed to open DM: ' . $dm_response->get_error_message() );
		}

		$dm_body = json_decode( wp_remote_retrieve_body( $dm_response ), true );

		if ( empty( $dm_body['ok'] ) ) {
			throw new \Exception( 'Failed to open DM: ' . ( $dm_body['error'] ?? 'Unknown error' ) );
		}

		$channel_id = $dm_body['channel']['id'] ?? '';

		// Send the message
		$response = wp_remote_post(
			self::API_BASE_URL . '/chat.postMessage',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json; charset=utf-8',
				),
				'body'    => wp_json_encode(
					array(
						'channel' => $channel_id,
						'text'    => $text,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Slack API request failed: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['ok'] ) ) {
			throw new \Exception( 'Slack API error: ' . ( $body['error'] ?? 'Unknown error' ) );
		}

		return array(
			'port' => 'main',
			'data' => array_merge(
				$input,
				array(
					'slack_message_ts' => $body['ts'] ?? '',
					'slack_channel'    => $body['channel'] ?? '',
				)
			),
		);
	}

	/* ---------------------------------------------------------
	 * Connection & Authentication
	 * --------------------------------------------------------- */

	public static function requires_connection(): bool {
		return true;
	}

	/**
	 * Slack supports both OAuth 2.0 and token-based authentication
	 * Return 'both' to indicate multiple auth types are available
	 */
	public static function get_auth_type(): string {
		return 'both';
	}

	/**
	 * Get available authentication methods for this integration
	 *
	 * @return array List of auth methods with labels
	 */
	public static function get_available_auth_types(): array {
		return array(
			'oauth2'  => array(
				'label'       => 'OAuth 2.0',
				'description' => 'Connect securely using Slack OAuth. Recommended for most users.',
			),
			'api_key' => array(
				'label'       => 'Bot Token',
				'description' => 'Use a Bot User OAuth Token directly. Requires creating a Slack App.',
			),
		);
	}

	/**
	 * Define the authentication fields for the connection form
	 * Returns fields based on the selected auth type
	 *
	 * @param string|null $auth_type The selected auth type (oauth2 or api_key)
	 * @return array Field definitions
	 */
	public static function get_auth_fields( ?string $auth_type = null ): array {
		// OAuth 2.0 fields - shown when user selects OAuth
		$oauth_fields = array(
			'client_id'     => array(
				'type'        => 'text',
				'label'       => 'Client ID',
				'placeholder' => 'Your Slack App Client ID',
				'required'    => true,
				'help'        => 'Go to api.slack.com/apps → Your App → Basic Information → App Credentials',
			),
			'client_secret' => array(
				'type'        => 'password',
				'label'       => 'Client Secret',
				'placeholder' => 'Your Slack App Client Secret',
				'required'    => true,
				'help'        => 'Found in the same location as Client ID',
			),
		);

		// Token-based fields - shown when user selects Bot Token
		$token_fields = array(
			'bot_token' => array(
				'type'        => 'password',
				'label'       => 'Bot Token',
				'placeholder' => 'xoxb-xxxx-xxxx-xxxx',
				'required'    => true,
				'help'        => 'Go to api.slack.com/apps → Your App → OAuth & Permissions → Bot User OAuth Token',
			),
		);

		// Return fields based on auth type
		if ( $auth_type === 'oauth2' ) {
			return $oauth_fields;
		}

		if ( $auth_type === 'api_key' ) {
			return $token_fields;
		}

		// Return all fields if no specific type requested
		return array(
			'oauth2'  => $oauth_fields,
			'api_key' => $token_fields,
		);
	}

	/**
	 * Test connection with provided credentials
	 * Works with both OAuth tokens and Bot tokens
	 */
	public static function test_connection( array $credentials ): array {
		// Get the token - could be from OAuth (access_token) or direct bot token
		$token = $credentials['access_token'] ?? $credentials['bot_token'] ?? '';

		if ( empty( $token ) ) {
			return array(
				'success' => false,
				'message' => 'No access token or bot token provided',
				'details' => array(),
			);
		}

		// Validate token format for bot tokens
		if ( isset( $credentials['bot_token'] ) && strpos( $token, 'xoxb-' ) !== 0 ) {
			return array(
				'success' => false,
				'message' => 'Invalid token format. Bot tokens should start with xoxb-',
				'details' => array(),
			);
		}

		$response = wp_remote_get(
			self::API_BASE_URL . '/auth.test',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => 'Connection test failed: ' . $response->get_error_message(),
				'details' => array(),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['ok'] ) ) {
			return array(
				'success' => false,
				'message' => 'Slack API error: ' . ( $body['error'] ?? 'Unknown error' ),
				'details' => array(),
			);
		}

		return array(
			'success' => true,
			'message' => 'Connected to workspace: ' . ( $body['team'] ?? 'Unknown' ),
			'details' => array(
				'team'    => $body['team'] ?? '',
				'user'    => $body['user'] ?? '',
				'user_id' => $body['user_id'] ?? '',
				'team_id' => $body['team_id'] ?? '',
				'url'     => $body['url'] ?? '',
			),
		);
	}

	/* ---------------------------------------------------------
	 * OAuth 2.0 Methods
	 * --------------------------------------------------------- */

	/**
	 * Get OAuth 2.0 scopes required for Slack
	 */
	public static function get_oauth_scopes(): array {
		return array(
			'chat:write',
			'channels:read',
			'users:read',
			'im:write',
		);
	}

	/**
	 * Get OAuth authorization URL
	 *
	 * @param string $redirect_uri Callback URL
	 * @param string $state        CSRF state token
	 * @param array  $credentials  Contains client_id and client_secret
	 * @return string|null Authorization URL
	 */
	public static function get_oauth_auth_url( string $redirect_uri, string $state, array $credentials = array() ): ?string {
		$client_id = $credentials['client_id'] ?? '';

		if ( empty( $client_id ) ) {
			return null;
		}

		$params = array(
			'client_id'    => $client_id,
			'redirect_uri' => $redirect_uri,
			'state'        => $state,
			'scope'        => implode( ',', self::get_oauth_scopes() ),
		);

		return self::OAUTH_AUTHORIZE_URL . '?' . http_build_query( $params );
	}

	/**
	 * Exchange authorization code for tokens
	 *
	 * @param string $code         Authorization code
	 * @param string $redirect_uri Callback URL
	 * @param array  $credentials  Contains client_id and client_secret
	 * @return array Token data
	 * @throws \Exception on failure
	 */
	public static function exchange_oauth_code( string $code, string $redirect_uri, array $credentials = array() ): array {
		$client_id = $credentials['client_id'] ?? '';
		$client_secret = $credentials['client_secret'] ?? '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			throw new \Exception( 'Client ID and Client Secret are required for OAuth token exchange' );
		}

		$response = wp_remote_post(
			self::OAUTH_TOKEN_URL,
			array(
				'body' => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'code'          => $code,
					'redirect_uri'  => $redirect_uri,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'OAuth token exchange failed: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['ok'] ) ) {
			throw new \Exception( 'Slack OAuth error: ' . ( $body['error'] ?? 'Unknown error' ) );
		}

		return array(
			'access_token'  => $body['access_token'] ?? '',
			'token_type'    => $body['token_type'] ?? 'bot',
			'scope'         => $body['scope'] ?? '',
			'team_id'       => $body['team']['id'] ?? '',
			'team_name'     => $body['team']['name'] ?? '',
			'bot_user_id'   => $body['bot_user_id'] ?? '',
			// Slack bot tokens don't expire, but we track this for consistency
			'expires_in'    => null,
			'refresh_token' => null,
		);
	}

	/* ---------------------------------------------------------
	 * Helper Methods
	 * --------------------------------------------------------- */

	/**
	 * Substitute variables in text from input data
	 * Replaces {{variable}} patterns with values from input
	 */
	private static function substitute_variables( string $text, array $data ): string {
		return preg_replace_callback(
			'/\{\{([^}]+)\}\}/',
			function ( $matches ) use ( $data ) {
				$key = trim( $matches[1] );
				$keys = explode( '.', $key );
				$value = $data;

				foreach ( $keys as $k ) {
					if ( is_array( $value ) && isset( $value[ $k ] ) ) {
						$value = $value[ $k ];
					} else {
						return $matches[0]; // Return original if not found
					}
				}

				return is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
			},
			$text
		);
	}
}
