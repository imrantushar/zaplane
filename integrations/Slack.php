<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Slack extends IntegrationBase {

	private const API_BASE_URL = 'https://slack.com/api';
	private const OAUTH_AUTHORIZE_URL = 'https://slack.com/oauth/v2/authorize';
	private const OAUTH_TOKEN_URL = 'https://slack.com/api/oauth.v2.access';

	public static function get_slug(): string {
		return 'slack';
	}

	public static function get_name(): string {
		return 'Slack';
	}

	public static function get_icon(): string {
		return 'slack';
	}

	public static function get_triggers(): array {
		return array(
			'message_received'  => array(
				'label' => 'Message Received',
				'hook'  => 'slack_webhook_message',
			),
			'app_mention'       => array(
				'label' => 'App Mentioned',
				'hook'  => 'slack_webhook_app_mention',
			),
			'reaction_added'    => array(
				'label' => 'Reaction Added',
				'hook'  => 'slack_webhook_reaction_added',
			),
			'channel_created'   => array(
				'label' => 'Channel Created',
				'hook'  => 'slack_webhook_channel_created',
			),
			'file_shared'       => array(
				'label' => 'File Shared',
				'hook'  => 'slack_webhook_file_shared',
			),
		);
	}

	public static function get_actions(): array {
		return array(
			'send_message'      => array( 'label' => 'Send Message' ),
			'send_dm'           => array( 'label' => 'Send Direct Message' ),
			'create_channel'    => array( 'label' => 'Create Channel' ),
			'invite_to_channel' => array( 'label' => 'Invite User to Channel' ),
			'set_topic'         => array( 'label' => 'Set Channel Topic' ),
			'add_reaction'      => array( 'label' => 'Add Reaction' ),
			'get_user_info'     => array( 'label' => 'Get User Info' ),
		);
	}

	public static function get_action_config_schema( string $action ): array {
		if ( $action === 'send_message' ) {
			return array(
				array( 'key' => 'channel', 'type' => 'text',     'label' => 'Channel', 'placeholder' => '#general or channel ID',                                    'required' => true ),
				array( 'key' => 'text',    'type' => 'textarea', 'label' => 'Message', 'placeholder' => 'Enter your message... Use {{variable}} for dynamic values', 'required' => true ),
			);
		}

		if ( $action === 'send_dm' ) {
			return array(
				array( 'key' => 'user_id', 'type' => 'text',     'label' => 'User ID', 'placeholder' => 'Slack user ID (e.g. U012AB3CD)',                            'required' => true ),
				array( 'key' => 'text',    'type' => 'textarea', 'label' => 'Message', 'placeholder' => 'Enter your message... Use {{variable}} for dynamic values', 'required' => true ),
			);
		}

		if ( $action === 'create_channel' ) {
			return array(
				array( 'key' => 'name',       'type' => 'text',   'label' => 'Channel Name', 'placeholder' => 'my-new-channel', 'required' => true, 'help' => 'Lowercase letters, numbers and hyphens only.' ),
				array( 'key' => 'is_private', 'type' => 'select', 'label' => 'Visibility',   'options' => array(
					array( 'value' => 'false', 'label' => 'Public' ),
					array( 'value' => 'true',  'label' => 'Private' ),
				) ),
			);
		}

		if ( $action === 'invite_to_channel' ) {
			return array(
				array( 'key' => 'channel',  'type' => 'text', 'label' => 'Channel ID', 'placeholder' => 'C012AB3CD',              'required' => true ),
				array( 'key' => 'user_ids', 'type' => 'text', 'label' => 'User IDs',   'placeholder' => 'U012AB3CD,U012AB3CE',    'required' => true, 'help' => 'Comma-separated Slack user IDs.' ),
			);
		}

		if ( $action === 'set_topic' ) {
			return array(
				array( 'key' => 'channel', 'type' => 'text', 'label' => 'Channel ID', 'placeholder' => 'C012AB3CD',          'required' => true ),
				array( 'key' => 'topic',   'type' => 'text', 'label' => 'Topic',      'placeholder' => 'New channel topic...', 'required' => true ),
			);
		}

		if ( $action === 'add_reaction' ) {
			return array(
				array( 'key' => 'channel',   'type' => 'text', 'label' => 'Channel ID',        'placeholder' => 'C012AB3CD',             'required' => true ),
				array( 'key' => 'timestamp', 'type' => 'text', 'label' => 'Message Timestamp', 'placeholder' => '{{slack_message_ts}}',  'required' => true, 'help' => 'The ts of the message to react to.' ),
				array( 'key' => 'emoji',     'type' => 'text', 'label' => 'Emoji Name',        'placeholder' => 'thumbsup',              'required' => true, 'help' => 'Emoji name without colons (e.g. thumbsup).' ),
			);
		}

		if ( $action === 'get_user_info' ) {
			return array(
				array( 'key' => 'user_id', 'type' => 'text', 'label' => 'User ID', 'placeholder' => 'U012AB3CD or {{user_id}}', 'required' => true ),
			);
		}

		return array();
	}

	public static function resolve_trigger( array $node, array $args ) {
		return array( 'message' => $args[0] ?? '' );
	}

	public static function execute_node( array $node, array $input ): array {
		$action = $node['data']['event'] ?? '';
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

		if ( $action === 'create_channel' ) {
			return self::action_create_channel( $node, $input, $token );
		}

		if ( $action === 'invite_to_channel' ) {
			return self::action_invite_to_channel( $node, $input, $token );
		}

		if ( $action === 'set_topic' ) {
			return self::action_set_topic( $node, $input, $token );
		}

		if ( $action === 'add_reaction' ) {
			return self::action_add_reaction( $node, $input, $token );
		}

		if ( $action === 'get_user_info' ) {
			return self::action_get_user_info( $node, $input, $token );
		}

		return array(
			'port' => 'main',
			'data' => $input,
		);
	}

	private static function action_send_message( array $node, array $input, string $token ): array {
		$channel = $node['data']['config']['channel'] ?? '';
		$text    = $node['data']['config']['text'] ?? '';

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

	private static function action_send_dm( array $node, array $input, string $token ): array {
		$user_id = $node['data']['config']['user_id'] ?? '';
		$text    = $node['data']['config']['text'] ?? '';

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

	public static function requires_connection(): bool {
		return true;
	}

	public static function get_auth_type(): string {
		return 'both';
	}

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

	public static function get_oauth_scopes(): array {
		return array(
			'chat:write',
			'channels:read',
			'users:read',
			'im:write',
		);
	}

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

	private static function action_create_channel( array $node, array $input, string $token ): array {
		$name       = self::substitute_variables( $node['data']['config']['name'] ?? '', $input );
		$is_private = ( $node['data']['config']['is_private'] ?? 'false' ) === 'true';

		[ $body, $status ] = self::http_post(
			self::API_BASE_URL . '/conversations.create',
			array(
				'name'       => $name,
				'is_private' => $is_private,
			),
			array( 'Authorization' => 'Bearer ' . $token )
		);

		if ( empty( $body['ok'] ) ) {
			throw new \Exception( 'Slack API error: ' . ( $body['error'] ?? 'Unknown error' ) );
		}

		return array(
			'port' => 'main',
			'data' => array_merge( $input, array(
				'channel_id'   => $body['channel']['id']   ?? '',
				'channel_name' => $body['channel']['name'] ?? '',
			) ),
		);
	}

	private static function action_invite_to_channel( array $node, array $input, string $token ): array {
		$channel  = self::substitute_variables( $node['data']['config']['channel'] ?? '', $input );
		$user_ids = self::substitute_variables( $node['data']['config']['user_ids'] ?? '', $input );

		[ $body ] = self::http_post(
			self::API_BASE_URL . '/conversations.invite',
			array(
				'channel' => $channel,
				'users'   => $user_ids,
			),
			array( 'Authorization' => 'Bearer ' . $token )
		);

		if ( empty( $body['ok'] ) ) {
			throw new \Exception( 'Slack API error: ' . ( $body['error'] ?? 'Unknown error' ) );
		}

		return array(
			'port' => 'main',
			'data' => array_merge( $input, array(
				'channel_id' => $body['channel']['id'] ?? '',
			) ),
		);
	}

	private static function action_set_topic( array $node, array $input, string $token ): array {
		$channel = self::substitute_variables( $node['data']['config']['channel'] ?? '', $input );
		$topic   = self::substitute_variables( $node['data']['config']['topic']   ?? '', $input );

		[ $body ] = self::http_post(
			self::API_BASE_URL . '/conversations.setTopic',
			array(
				'channel' => $channel,
				'topic'   => $topic,
			),
			array( 'Authorization' => 'Bearer ' . $token )
		);

		if ( empty( $body['ok'] ) ) {
			throw new \Exception( 'Slack API error: ' . ( $body['error'] ?? 'Unknown error' ) );
		}

		return array(
			'port' => 'main',
			'data' => array_merge( $input, array(
				'topic' => $body['topic'] ?? $topic,
			) ),
		);
	}

	private static function action_add_reaction( array $node, array $input, string $token ): array {
		$channel   = self::substitute_variables( $node['data']['config']['channel']   ?? '', $input );
		$timestamp = self::substitute_variables( $node['data']['config']['timestamp'] ?? '', $input );
		$emoji     = trim( $node['data']['config']['emoji'] ?? '', ':' );

		[ $body ] = self::http_post(
			self::API_BASE_URL . '/reactions.add',
			array(
				'channel'   => $channel,
				'timestamp' => $timestamp,
				'name'      => $emoji,
			),
			array( 'Authorization' => 'Bearer ' . $token )
		);

		if ( empty( $body['ok'] ) && ( $body['error'] ?? '' ) !== 'already_reacted' ) {
			throw new \Exception( 'Slack API error: ' . ( $body['error'] ?? 'Unknown error' ) );
		}

		return array(
			'port' => 'main',
			'data' => array_merge( $input, array(
				'reaction_added' => true,
			) ),
		);
	}

	private static function action_get_user_info( array $node, array $input, string $token ): array {
		$user_id = self::substitute_variables( $node['data']['config']['user_id'] ?? '', $input );

		[ $body ] = self::http_get(
			self::API_BASE_URL . '/users.info?user=' . rawurlencode( $user_id ),
			array( 'Authorization' => 'Bearer ' . $token )
		);

		if ( empty( $body['ok'] ) ) {
			throw new \Exception( 'Slack API error: ' . ( $body['error'] ?? 'Unknown error' ) );
		}

		$user = $body['user'] ?? array();

		return array(
			'port' => 'main',
			'data' => array_merge( $input, array(
				'user_id'      => $user['id']                         ?? '',
				'user_name'    => $user['name']                       ?? '',
				'display_name' => $user['profile']['display_name']    ?? '',
				'email'        => $user['profile']['email']           ?? '',
				'is_admin'     => $user['is_admin']                   ?? false,
			) ),
		);
	}

	// ── Incoming webhook support ───────────────────────────────────────────────

	public static function supports_webhook(): bool {
		return true;
	}

	/**
	 * Verify Slack's request signature.
	 * Slack signs every request with HMAC-SHA256 using the app's Signing Secret.
	 * The signing secret is stored as the WordPress option 'zaplane_slack_signing_secret'.
	 */
	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		$signing_secret = get_option( 'zaplane_slack_signing_secret', '' );

		// If no secret configured, accept all (dev/testing mode).
		if ( empty( $signing_secret ) ) {
			return true;
		}

		$timestamp = $request->get_header( 'x-slack-request-timestamp' );
		$signature = $request->get_header( 'x-slack-signature' );

		if ( ! $timestamp || ! $signature ) {
			return false;
		}

		// Reject requests older than 5 minutes to prevent replay attacks.
		if ( abs( time() - (int) $timestamp ) > 300 ) {
			return false;
		}

		$base_string    = 'v0:' . $timestamp . ':' . $request->get_body();
		$expected       = 'v0=' . hash_hmac( 'sha256', $base_string, $signing_secret );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Parse a Slack Event API payload into a normalized [event, payload] pair.
	 * Returns null for url_verification challenges and unknown event types.
	 */
	public static function parse_webhook_event( \WP_REST_Request $request ): ?array {
		$body = $request->get_json_params();
		$type = $body['type'] ?? '';

		// Slack URL verification challenge — respond with challenge, no workflow.
		if ( $type === 'url_verification' ) {
			return null;
		}

		if ( $type !== 'event_callback' ) {
			return null;
		}

		$event      = $body['event'] ?? array();
		$event_type = $event['type'] ?? '';

		$map = array(
			'message'         => 'message_received',
			'app_mention'     => 'app_mention',
			'reaction_added'  => 'reaction_added',
			'channel_created' => 'channel_created',
			'file_shared'     => 'file_shared',
		);

		$normalized = $map[ $event_type ] ?? null;
		if ( ! $normalized ) {
			return null; // Unknown event — accept HTTP, skip workflow.
		}

		return array(
			'event'   => $normalized,
			'payload' => array_merge( $event, array(
				'team_id'    => $body['team_id']    ?? '',
				'api_app_id' => $body['api_app_id'] ?? '',
			) ),
		);
	}

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
