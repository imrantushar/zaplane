<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\ExternalAppIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Slack extends ExternalAppIntegration {

	private const API_BASE_URL        = 'https://slack.com/api';
	private const OAUTH_AUTHORIZE_URL = 'https://slack.com/oauth/v2/authorize';
	private const OAUTH_TOKEN_URL     = 'https://slack.com/api/oauth.v2.access';

	/* ---------------------------------------------------------
	 * Identity
	 * --------------------------------------------------------- */

	public static function get_slug(): string { return 'slack'; }
	public static function get_name(): string { return 'Slack'; }
	public static function get_icon(): string { return 'slack'; }

	/* ---------------------------------------------------------
	 * Authentication
	 * --------------------------------------------------------- */

	public static function get_auth_type(): string { return 'both'; }

	public static function get_available_auth_types(): array {
		return [
			'oauth2'  => [
				'label'       => 'OAuth 2.0',
				'description' => 'Connect securely using Slack OAuth. Recommended for most users.',
			],
			'api_key' => [
				'label'       => 'Bot Token',
				'description' => 'Use a Bot User OAuth Token directly. Requires creating a Slack App.',
			],
		];
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		$oauth_fields = [
			'client_id' => [
				'type'        => 'text',
				'label'       => 'Client ID',
				'placeholder' => 'Your Slack App Client ID',
				'required'    => true,
				'help'        => 'Go to api.slack.com/apps → Your App → Basic Information → App Credentials',
			],
			'client_secret' => [
				'type'        => 'password',
				'label'       => 'Client Secret',
				'placeholder' => 'Your Slack App Client Secret',
				'required'    => true,
				'help'        => 'Found in the same location as Client ID',
			],
		];

		$token_fields = [
			'bot_token' => [
				'type'        => 'password',
				'label'       => 'Bot Token',
				'placeholder' => 'xoxb-xxxx-xxxx-xxxx',
				'required'    => true,
				'help'        => 'Go to api.slack.com/apps → Your App → OAuth & Permissions → Bot User OAuth Token',
			],
		];

		if ( $auth_type === 'oauth2' )  return $oauth_fields;
		if ( $auth_type === 'api_key' ) return $token_fields;

		return [ 'oauth2' => $oauth_fields, 'api_key' => $token_fields ];
	}

	public static function test_connection( array $credentials ): array {
		$token = $credentials['access_token'] ?? $credentials['bot_token'] ?? '';

		if ( empty( $token ) ) {
			return [ 'success' => false, 'message' => 'No access token or bot token provided', 'details' => [] ];
		}

		if ( isset( $credentials['bot_token'] ) && strpos( $token, 'xoxb-' ) !== 0 ) {
			return [ 'success' => false, 'message' => 'Invalid token format. Bot tokens should start with xoxb-', 'details' => [] ];
		}

		try {
			$body = self::slack_api( 'auth.test', [], $token, 'GET' );
		} catch ( \Exception $e ) {
			return [ 'success' => false, 'message' => $e->getMessage(), 'details' => [] ];
		}

		return [
			'success' => true,
			'message' => 'Connected to workspace: ' . ( $body['team'] ?? 'Unknown' ),
			'details' => [
				'team'    => $body['team'] ?? '',
				'user'    => $body['user'] ?? '',
				'user_id' => $body['user_id'] ?? '',
				'team_id' => $body['team_id'] ?? '',
				'url'     => $body['url'] ?? '',
			],
		];
	}

	/* ---------------------------------------------------------
	 * OAuth
	 * --------------------------------------------------------- */

	public static function get_oauth_scopes(): array {
		return [ 'chat:write', 'channels:read', 'users:read', 'im:write' ];
	}

	public static function get_oauth_auth_url( string $redirect_uri, string $state, array $credentials = [] ): ?string {
		$client_id = $credentials['client_id'] ?? '';
		if ( empty( $client_id ) ) return null;

		return self::OAUTH_AUTHORIZE_URL . '?' . http_build_query( [
			'client_id'    => $client_id,
			'redirect_uri' => $redirect_uri,
			'state'        => $state,
			'scope'        => implode( ',', self::get_oauth_scopes() ),
		] );
	}

	public static function exchange_oauth_code( string $code, string $redirect_uri, array $credentials = [] ): array {
		$client_id     = $credentials['client_id'] ?? '';
		$client_secret = $credentials['client_secret'] ?? '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			throw new \Exception( 'Client ID and Client Secret are required for OAuth token exchange' );
		}

		$response = wp_remote_post( self::OAUTH_TOKEN_URL, [
			'body' => [
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'code'          => $code,
				'redirect_uri'  => $redirect_uri,
			],
		] );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'OAuth token exchange failed: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['ok'] ) ) {
			throw new \Exception( 'Slack OAuth error: ' . ( $body['error'] ?? 'Unknown error' ) );
		}

		return [
			'access_token'  => $body['access_token'] ?? '',
			'token_type'    => $body['token_type'] ?? 'bot',
			'scope'         => $body['scope'] ?? '',
			'team_id'       => $body['team']['id'] ?? '',
			'team_name'     => $body['team']['name'] ?? '',
			'bot_user_id'   => $body['bot_user_id'] ?? '',
			'expires_in'    => null,
			'refresh_token' => null,
		];
	}

	/* ---------------------------------------------------------
	 * Triggers
	 * --------------------------------------------------------- */

	public static function get_triggers(): array {
		return [
			'message_received' => [
				'label' => 'Message Received',
				'hook'  => 'slack_webhook_message',
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		return [ 'message' => $args[0] ?? '' ];
	}

	/* ---------------------------------------------------------
	 * Actions
	 * --------------------------------------------------------- */

	public static function get_actions(): array {
		return [
			'send_message' => [ 'label' => 'Send Message' ],
			'send_dm'      => [ 'label' => 'Send Direct Message' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		if ( $action === 'send_message' ) {
			return [
				'channel' => [
					'type'        => 'text',
					'label'       => 'Channel',
					'placeholder' => '#general or channel ID',
					'required'    => true,
				],
				'text' => [
					'type'        => 'textarea',
					'label'       => 'Message',
					'placeholder' => 'Enter your message...',
					'required'    => true,
				],
			];
		}

		if ( $action === 'send_dm' ) {
			return [
				'user_id' => [
					'type'        => 'text',
					'label'       => 'User ID',
					'placeholder' => 'Slack user ID',
					'required'    => true,
				],
				'text' => [
					'type'        => 'textarea',
					'label'       => 'Message',
					'placeholder' => 'Enter your message...',
					'required'    => true,
				],
			];
		}

		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$action = $node['config']['action'] ?? '';
		$config = $node['config']['data'] ?? $node['data']['config'] ?? [];
		$creds  = $node['_connection_credentials'] ?? [];

		$token = $creds['access_token'] ?? $creds['bot_token'] ?? '';
		if ( empty( $token ) ) {
			throw new \Exception( 'Slack access token is missing' );
		}

		if ( $action === 'send_message' ) {
			return self::action_send_message( $config, $input, $token );
		}

		if ( $action === 'send_dm' ) {
			return self::action_send_dm( $config, $input, $token );
		}

		return [ 'port' => 'main', 'data' => $input ];
	}

	/* ---------------------------------------------------------
	 * Rate Limit
	 * --------------------------------------------------------- */

	public static function get_rate_limit(): int {
		return 50;
	}

	/* ---------------------------------------------------------
	 * Private Action Methods
	 * --------------------------------------------------------- */

	private static function action_send_message( array $config, array $input, string $token ): array {
		$body = self::slack_api( 'chat.postMessage', [
			'channel' => $config['channel'] ?? '',
			'text'    => $config['text'] ?? '',
		], $token );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'slack_message_ts' => $body['ts'] ?? '',
				'slack_channel'    => $body['channel'] ?? '',
			] ),
		];
	}

	private static function action_send_dm( array $config, array $input, string $token ): array {
		$dm_body = self::slack_api( 'conversations.open', [
			'users' => $config['user_id'] ?? '',
		], $token );

		$channel_id = $dm_body['channel']['id'] ?? '';

		$body = self::slack_api( 'chat.postMessage', [
			'channel' => $channel_id,
			'text'    => $config['text'] ?? '',
		], $token );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'slack_message_ts' => $body['ts'] ?? '',
				'slack_channel'    => $body['channel'] ?? '',
			] ),
		];
	}

	/* ---------------------------------------------------------
	 * Slack API Helper
	 * --------------------------------------------------------- */

	private static function slack_api( string $method, array $data, string $token, string $http = 'POST' ): array {
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
			$response = wp_remote_post( $url, $args );
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
}
