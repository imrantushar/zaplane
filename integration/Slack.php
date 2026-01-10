<?php
namespace Zaplane\Integration;

use Zaplane\Classes\IntegrationBase;
use Zaplane\Classes\OAuthHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slack Integration
 * Reference implementation for OAuth2 integrations
 */
class Slack extends IntegrationBase {

	private const OAUTH_AUTHORIZE_URL = 'https://slack.com/oauth/v2/authorize';
	private const OAUTH_TOKEN_URL = 'https://slack.com/api/oauth.v2.access';
	private const API_BASE_URL = 'https://slack.com/api';

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

		$access_token = $credentials['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			throw new \Exception( 'Slack access token is missing' );
		}

		if ( $action === 'send_message' ) {
			return self::action_send_message( $node, $input, $access_token );
		}

		if ( $action === 'send_dm' ) {
			return self::action_send_dm( $node, $input, $access_token );
		}

		return array(
			'port' => 'main',
			'data' => $input,
		);
	}

	/**
	 * Send message to a channel
	 */
	private static function action_send_message( array $node, array $input, string $access_token ): array {
		$channel = $node['config']['data']['channel'] ?? '';
		$text = $node['config']['data']['text'] ?? '';

		// Variable substitution from input
		$text = self::substitute_variables( $text, $input );

		$response = wp_remote_post(
			self::API_BASE_URL . '/chat.postMessage',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
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
	private static function action_send_dm( array $node, array $input, string $access_token ): array {
		$user_id = $node['config']['data']['user_id'] ?? '';
		$text = $node['config']['data']['text'] ?? '';

		$text = self::substitute_variables( $text, $input );

		// First, open a DM conversation
		$dm_response = wp_remote_post(
			self::API_BASE_URL . '/conversations.open',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
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
					'Authorization' => 'Bearer ' . $access_token,
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

	public static function get_auth_type(): string {
		return 'oauth2';
	}

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
	 */
	public static function get_oauth_auth_url( string $redirect_uri, string $state ): ?string {
		$client_id = self::get_client_id();

		if ( empty( $client_id ) ) {
			return null;
		}

		return OAuthHandler::build_auth_url(
			self::OAUTH_AUTHORIZE_URL,
			$client_id,
			$redirect_uri,
			$state,
			self::get_oauth_scopes()
		);
	}

	/**
	 * Exchange authorization code for tokens
	 */
	public static function exchange_oauth_code( string $code, string $redirect_uri ): array {
		$response = wp_remote_post(
			self::OAUTH_TOKEN_URL,
			array(
				'body' => array(
					'client_id'     => self::get_client_id(),
					'client_secret' => self::get_client_secret(),
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
			throw new \Exception( 'OAuth error: ' . ( $body['error'] ?? 'Unknown error' ) );
		}

		return array(
			'access_token'  => $body['access_token'] ?? '',
			'refresh_token' => $body['refresh_token'] ?? null,
			'expires_in'    => $body['expires_in'] ?? null,
			'token_type'    => $body['token_type'] ?? 'bearer',
			'team_id'       => $body['team']['id'] ?? '',
			'team_name'     => $body['team']['name'] ?? '',
			'bot_user_id'   => $body['bot_user_id'] ?? '',
		);
	}

	/**
	 * Refresh OAuth token (Slack tokens don't typically expire, but implemented for completeness)
	 */
	public static function refresh_oauth_token( string $refresh_token ): array {
		// Slack bot tokens don't expire by default
		// If using user tokens with rotation, implement refresh here
		return array();
	}

	/**
	 * Test connection with provided credentials
	 */
	public static function test_connection( array $credentials ): array {
		$access_token = $credentials['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'message' => 'Access token is missing',
				'details' => array(),
			);
		}

		$response = wp_remote_get(
			self::API_BASE_URL . '/auth.test',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
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
	 * Helper Methods
	 * --------------------------------------------------------- */

	/**
	 * Get Slack Client ID from settings
	 */
	private static function get_client_id(): string {
		$settings = $GLOBALS['zaplane_settings'] ?? new \stdClass();
		return $settings->slack_client_id ?? get_option( 'zaplane_slack_client_id', '' );
	}

	/**
	 * Get Slack Client Secret from settings
	 */
	private static function get_client_secret(): string {
		$settings = $GLOBALS['zaplane_settings'] ?? new \stdClass();
		return $settings->slack_client_secret ?? get_option( 'zaplane_slack_client_secret', '' );
	}

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
