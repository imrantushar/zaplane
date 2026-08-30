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
		return 'slack.svg';
	}

	public static function get_triggers(): array {
		return [
			'message_received'  => [
				'label' => 'Message Received',
				'hook'  => 'slack_webhook_message',
			],
			'app_mention'       => [
				'label' => 'App Mentioned',
				'hook'  => 'slack_webhook_app_mention',
			],
			'reaction_added'    => [
				'label' => 'Reaction Added',
				'hook'  => 'slack_webhook_reaction_added',
			],
			'channel_created'   => [
				'label' => 'Channel Created',
				'hook'  => 'slack_webhook_channel_created',
			],
			'file_shared'       => [
				'label' => 'File Shared',
				'hook'  => 'slack_webhook_file_shared',
			],
		];
	}

	public static function get_trigger_sample_output( string $event ): array {
		$user_id    = 'U012AB3CD';
		$channel_id = 'C012AB3CD';

		$meta = [
			'team_id'    => 'T012AB3CD',
			'api_app_id' => 'A012XYZ99',
		];

		$message = array_merge(
			[
				'type'         => 'message',
				'channel'      => $channel_id,
				'channel_type' => 'channel',
				'user'         => $user_id,
				'text'         => 'Hello team, the deploy is complete!',
				'ts'           => '1720535405.001200',
				'event_ts'     => '1720535405.001200',
			],
			$meta
		);

		$app_mention = array_merge(
			[
				'type'     => 'app_mention',
				'user'     => $user_id,
				'text'     => '<@U0LAN0Z01> can you run the report?',
				'ts'       => '1720535410.002200',
				'channel'  => $channel_id,
				'event_ts' => '1720535410.002200',
			],
			$meta
		);

		$reaction_added = array_merge(
			[
				'type'      => 'reaction_added',
				'user'      => $user_id,
				'reaction'  => 'thumbsup',
				'item_user' => 'U024BE7LH',
				'item'      => [
					'type'    => 'message',
					'channel' => $channel_id,
					'ts'      => '1720535405.001200',
				],
				'event_ts'  => '1720535420.003200',
			],
			$meta
		);

		$channel_created = array_merge(
			[
				'type'    => 'channel_created',
				'channel' => [
					'id'      => 'C0987NEW1',
					'name'    => 'project-launch',
					'created' => 1720535430,
					'creator' => $user_id,
				],
			],
			$meta
		);

		$file_shared = array_merge(
			[
				'type'       => 'file_shared',
				'file_id'    => 'F012AB3CD',
				'user_id'    => $user_id,
				'file'       => [ 'id' => 'F012AB3CD' ],
				'channel_id' => $channel_id,
				'event_ts'   => '1720535440.004200',
			],
			$meta
		);

		$samples = [
			'message_received' => $message,
			'app_mention'      => $app_mention,
			'reaction_added'   => $reaction_added,
			'channel_created'  => $channel_created,
			'file_shared'      => $file_shared,
		];

		if ( isset( $samples[ $event ] ) ) {
			return $samples[ $event ];
		}

		if ( 0 === strpos( $event, 'message' ) ) {
			return $message;
		}
		if ( 0 === strpos( $event, 'reaction' ) ) {
			return $reaction_added;
		}
		if ( 0 === strpos( $event, 'channel' ) ) {
			return $channel_created;
		}
		if ( 0 === strpos( $event, 'file' ) ) {
			return $file_shared;
		}

		return $message;
	}

	public static function get_actions(): array {
		return [
			'send_message'      => [ 'label' => 'Send Message' ],
			'send_dm'           => [ 'label' => 'Send Direct Message' ],
			'create_channel'    => [ 'label' => 'Create Channel' ],
			'invite_to_channel' => [ 'label' => 'Invite User to Channel' ],
			'set_topic'         => [ 'label' => 'Set Channel Topic' ],
			'add_reaction'      => [ 'label' => 'Add Reaction' ],
			'get_user_info'     => [ 'label' => 'Get User Info' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		if ( 'send_message' === $action ) {
			return [
				[
					'key' => 'channel',
					'type' => 'text',
					'label' => 'Channel',
					'placeholder' => '#general or channel ID',
					'required' => true
				],
				[
					'key' => 'text',
					'type' => 'textarea',
					'label' => 'Message',
					'placeholder' => 'Enter your message... Use {{variable}} for dynamic values',
					'required' => true
				],
			];
		}

		if ( 'send_dm' === $action ) {
			return [
				[
					'key' => 'user_id',
					'type' => 'text',
					'label' => 'User ID',
					'placeholder' => 'Slack user ID (e.g. U012AB3CD)',
					'required' => true
				],
				[
					'key' => 'text',
					'type' => 'textarea',
					'label' => 'Message',
					'placeholder' => 'Enter your message... Use {{variable}} for dynamic values',
					'required' => true
				],
			];
		}

		if ( 'create_channel' === $action ) {
			return [
				[
					'key' => 'name',
					'type' => 'text',
					'label' => 'Channel Name',
					'placeholder' => 'my-new-channel',
					'required' => true,
					'help' => 'Lowercase letters, numbers and hyphens only.'
				],
				[
					'key' => 'is_private',
					'type' => 'select',
					'label' => 'Visibility',
					'options' => [
						[
							'value' => 'false',
							'label' => 'Public'
						],
						[
							'value' => 'true',
							'label' => 'Private'
						],
					]
				],
			];
		}//end if

		if ( 'invite_to_channel' === $action ) {
			return [
				[
					'key' => 'channel',
					'type' => 'text',
					'label' => 'Channel ID',
					'placeholder' => 'C012AB3CD',
					'required' => true
				],
				[
					'key' => 'user_ids',
					'type' => 'text',
					'label' => 'User IDs',
					'placeholder' => 'U012AB3CD,U012AB3CE',
					'required' => true,
					'help' => 'Comma-separated Slack user IDs.'
				],
			];
		}

		if ( 'set_topic' === $action ) {
			return [
				[
					'key' => 'channel',
					'type' => 'text',
					'label' => 'Channel ID',
					'placeholder' => 'C012AB3CD',
					'required' => true
				],
				[
					'key' => 'topic',
					'type' => 'text',
					'label' => 'Topic',
					'placeholder' => 'New channel topic...',
					'required' => true
				],
			];
		}

		if ( 'add_reaction' === $action ) {
			return [
				[
					'key' => 'channel',
					'type' => 'text',
					'label' => 'Channel ID',
					'placeholder' => 'C012AB3CD',
					'required' => true
				],
				[
					'key' => 'timestamp',
					'type' => 'text',
					'label' => 'Message Timestamp',
					'placeholder' => '{{slack_message_ts}}',
					'required' => true,
					'help' => 'The ts of the message to react to.'
				],
				[
					'key' => 'emoji',
					'type' => 'text',
					'label' => 'Emoji Name',
					'placeholder' => 'thumbsup',
					'required' => true,
					'help' => 'Emoji name without colons (e.g. thumbsup).'
				],
			];
		}//end if

		if ( 'get_user_info' === $action ) {
			return [
				[
					'key' => 'user_id',
					'type' => 'text',
					'label' => 'User ID',
					'placeholder' => 'U012AB3CD or {{user_id}}',
					'required' => true
				],
			];
		}

		return [];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'channel_created' === $trigger ) {
			return [];
		}

		return [
			[
				'key'         => 'channel',
				'label'       => 'Channel ID',
				'type'        => 'text',
				'placeholder' => 'C012AB3CD',
				'help'        => 'Optional. Only continue when the event came from this channel.',
			],
		];
	}

	/**
	 * The webhook hands us the Slack event object (already merged with team_id /
	 * api_app_id by parse_webhook_event), so it is returned as-is — its keys are
	 * exactly what get_trigger_sample_output() advertises and what workflows map
	 * against. The previous version wrapped it as [ 'message' => $args[0] ],
	 * which meant `text`, `user` and `channel` never existed on the payload.
	 */
	public static function resolve_trigger( array $node, array $args ) {
		$payload = $args[0] ?? null;

		if ( ! is_array( $payload ) || empty( $payload ) ) {
			return false;
		}

		// Never react to our own posts. Without this a workflow that both listens
		// for messages and sends one re-triggers itself indefinitely.
		if ( ! empty( $payload['bot_id'] ) || 'bot_message' === ( $payload['subtype'] ?? '' ) ) {
			return false;
		}

		$config  = $node['data']['config'] ?? ( $node['config'] ?? [] );
		$channel = is_array( $config ) ? trim( (string) ( $config['channel'] ?? '' ) ) : '';

		if ( '' !== $channel ) {
			// reaction_added / file_shared nest the channel differently to message.
			$actual = (string) (
				$payload['channel']
				?? $payload['channel_id']
				?? ( $payload['item']['channel'] ?? '' )
			);
			if ( $channel !== $actual ) {
				return false;
			}
		}

		return $payload;
	}

	public static function execute_node( array $node, array $input ): array {
		$action = $node['data']['event'] ?? '';
		$credentials = $node['_connection_credentials'] ?? null;

		if ( ! $credentials ) {
			throw new \Exception( 'No connection credentials available for Slack' );
		}

		$token = $credentials['access_token'] ?? $credentials['bot_token'] ?? '';

		if ( empty( $token ) ) {
			throw new \Exception( 'Slack access token is missing' );
		}

		if ( 'send_message' === $action ) {
			return self::action_send_message( $node, $input, $token );
		}

		if ( 'send_dm' === $action ) {
			return self::action_send_dm( $node, $input, $token );
		}

		if ( 'create_channel' === $action ) {
			return self::action_create_channel( $node, $input, $token );
		}

		if ( 'invite_to_channel' === $action ) {
			return self::action_invite_to_channel( $node, $input, $token );
		}

		if ( 'set_topic' === $action ) {
			return self::action_set_topic( $node, $input, $token );
		}

		if ( 'add_reaction' === $action ) {
			return self::action_add_reaction( $node, $input, $token );
		}

		if ( 'get_user_info' === $action ) {
			return self::action_get_user_info( $node, $input, $token );
		}

		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	private static function action_send_message( array $node, array $input, string $token ): array {
		$channel = $node['data']['config']['channel'] ?? '';
		$text    = $node['data']['config']['text'] ?? '';

		$response = wp_remote_post(
			self::API_BASE_URL . '/chat.postMessage',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json; charset=utf-8',
				],
				'body'    => wp_json_encode(
					[
						'channel' => $channel,
						'text'    => $text,
					]
				),
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Slack API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['ok'] ) ) {
			throw new \Exception( 'Slack API error: ' . esc_html( $body['error'] ?? 'Unknown error' ) );
		}

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'slack_message_ts' => $body['ts'] ?? '',
					'slack_channel'    => $body['channel'] ?? '',
				]
			),
		];
	}

	private static function action_send_dm( array $node, array $input, string $token ): array {
		$user_id = $node['data']['config']['user_id'] ?? '';
		$text    = $node['data']['config']['text'] ?? '';

		$dm_response = wp_remote_post(
			self::API_BASE_URL . '/conversations.open',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json; charset=utf-8',
				],
				'body'    => wp_json_encode( [ 'users' => $user_id ] ),
			]
		);

		if ( is_wp_error( $dm_response ) ) {
			throw new \Exception( 'Failed to open DM: ' . esc_html( $dm_response->get_error_message() ) );
		}

		$dm_body = json_decode( wp_remote_retrieve_body( $dm_response ), true );

		if ( empty( $dm_body['ok'] ) ) {
			throw new \Exception( 'Failed to open DM: ' . esc_html( $dm_body['error'] ?? 'Unknown error' ) );
		}

		$channel_id = $dm_body['channel']['id'] ?? '';

		$response = wp_remote_post(
			self::API_BASE_URL . '/chat.postMessage',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json; charset=utf-8',
				],
				'body'    => wp_json_encode(
					[
						'channel' => $channel_id,
						'text'    => $text,
					]
				),
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Slack API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['ok'] ) ) {
			throw new \Exception( 'Slack API error: ' . esc_html( $body['error'] ?? 'Unknown error' ) );
		}

		return [
			'port' => 'main',
			'data' => array_merge(
				$input,
				[
					'slack_message_ts' => $body['ts'] ?? '',
					'slack_channel'    => $body['channel'] ?? '',
				]
			),
		];
	}

	public static function requires_connection(): bool {
		return true;
	}

	public static function get_auth_type(): string {
		return 'both';
	}

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
			'client_id'     => [
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

		if ( 'oauth2' === $auth_type ) {
			return $oauth_fields;
		}

		if ( 'api_key' === $auth_type ) {
			return $token_fields;
		}

		return [
			'oauth2'  => $oauth_fields,
			'api_key' => $token_fields,
		];
	}

	public static function test_connection( array $credentials ): array {

		$token = $credentials['access_token'] ?? $credentials['bot_token'] ?? '';

		if ( empty( $token ) ) {
			return [
				'success' => false,
				'message' => 'No access token or bot token provided',
				'details' => [],
			];
		}

		if ( isset( $credentials['bot_token'] ) && strpos( $token, 'xoxb-' ) !== 0 ) {
			return [
				'success' => false,
				'message' => 'Invalid token format. Bot tokens should start with xoxb-',
				'details' => [],
			];
		}

		$response = wp_remote_get(
			self::API_BASE_URL . '/auth.test',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => 'Connection test failed: ' . $response->get_error_message(),
				'details' => [],
			];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['ok'] ) ) {
			return [
				'success' => false,
				'message' => 'Slack API error: ' . ( $body['error'] ?? 'Unknown error' ),
				'details' => [],
			];
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

	public static function get_oauth_scopes(): array {
		return [
			'chat:write',
			'channels:read',
			'users:read',
			'im:write',
		];
	}

	public static function get_oauth_auth_url( string $redirect_uri, string $state, array $credentials = [] ): ?string {
		$client_id = $credentials['client_id'] ?? '';

		if ( empty( $client_id ) ) {
			return null;
		}

		$params = [
			'client_id'    => $client_id,
			'redirect_uri' => $redirect_uri,
			'state'        => $state,
			'scope'        => implode( ',', self::get_oauth_scopes() ),
		];

		return self::OAUTH_AUTHORIZE_URL . '?' . http_build_query( $params );
	}

	public static function exchange_oauth_code( string $code, string $redirect_uri, array $credentials = [] ): array {
		$client_id = $credentials['client_id'] ?? '';
		$client_secret = $credentials['client_secret'] ?? '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			throw new \Exception( 'Client ID and Client Secret are required for OAuth token exchange' );
		}

		$response = wp_remote_post(
			self::OAUTH_TOKEN_URL,
			[
				'body' => [
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'code'          => $code,
					'redirect_uri'  => $redirect_uri,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'OAuth token exchange failed: ' . esc_html( $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['ok'] ) ) {
			throw new \Exception( 'Slack OAuth error: ' . esc_html( $body['error'] ?? 'Unknown error' ) );
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

	private static function action_create_channel( array $node, array $input, string $token ): array {
		$name       = $node['data']['config']['name'] ?? '';
		$is_private = ( $node['data']['config']['is_private'] ?? 'false' ) === 'true';

		[ $body, $status ] = self::http_post(
			self::API_BASE_URL . '/conversations.create',
			[
				'name'       => $name,
				'is_private' => $is_private,
			],
			[ 'Authorization' => 'Bearer ' . $token ]
		);

		if ( empty( $body['ok'] ) ) {
			throw new \Exception( 'Slack API error: ' . esc_html( $body['error'] ?? 'Unknown error' ) );
		}

		return [
			'port' => 'main',
			'data' => array_merge($input, [
				'channel_id'   => $body['channel']['id'] ?? '',
				'channel_name' => $body['channel']['name'] ?? '',
			]),
		];
	}

	private static function action_invite_to_channel( array $node, array $input, string $token ): array {
		$channel  = $node['data']['config']['channel'] ?? '';
		$user_ids = $node['data']['config']['user_ids'] ?? '';

		[ $body ] = self::http_post(
			self::API_BASE_URL . '/conversations.invite',
			[
				'channel' => $channel,
				'users'   => $user_ids,
			],
			[ 'Authorization' => 'Bearer ' . $token ]
		);

		if ( empty( $body['ok'] ) ) {
			throw new \Exception( 'Slack API error: ' . esc_html( $body['error'] ?? 'Unknown error' ) );
		}

		return [
			'port' => 'main',
			'data' => array_merge($input, [
				'channel_id' => $body['channel']['id'] ?? '',
			]),
		];
	}

	private static function action_set_topic( array $node, array $input, string $token ): array {
		$channel = $node['data']['config']['channel'] ?? '';
		$topic   = $node['data']['config']['topic'] ?? '';

		[ $body ] = self::http_post(
			self::API_BASE_URL . '/conversations.setTopic',
			[
				'channel' => $channel,
				'topic'   => $topic,
			],
			[ 'Authorization' => 'Bearer ' . $token ]
		);

		if ( empty( $body['ok'] ) ) {
			throw new \Exception( 'Slack API error: ' . esc_html( $body['error'] ?? 'Unknown error' ) );
		}

		return [
			'port' => 'main',
			'data' => array_merge($input, [
				'topic' => $body['topic'] ?? $topic,
			]),
		];
	}

	private static function action_add_reaction( array $node, array $input, string $token ): array {
		$channel   = $node['data']['config']['channel'] ?? '';
		$timestamp = $node['data']['config']['timestamp'] ?? '';
		$emoji     = trim( $node['data']['config']['emoji'] ?? '', ':' );

		[ $body ] = self::http_post(
			self::API_BASE_URL . '/reactions.add',
			[
				'channel'   => $channel,
				'timestamp' => $timestamp,
				'name'      => $emoji,
			],
			[ 'Authorization' => 'Bearer ' . $token ]
		);

		if ( empty( $body['ok'] ) && ( $body['error'] ?? '' ) !== 'already_reacted' ) {
			throw new \Exception( 'Slack API error: ' . esc_html( $body['error'] ?? 'Unknown error' ) );
		}

		return [
			'port' => 'main',
			'data' => array_merge($input, [
				'reaction_added' => true,
			]),
		];
	}

	private static function action_get_user_info( array $node, array $input, string $token ): array {
		$user_id = $node['data']['config']['user_id'] ?? '';

		[ $body ] = self::http_get(
			self::API_BASE_URL . '/users.info?user=' . rawurlencode( $user_id ),
			[ 'Authorization' => 'Bearer ' . $token ]
		);

		if ( empty( $body['ok'] ) ) {
			throw new \Exception( 'Slack API error: ' . esc_html( $body['error'] ?? 'Unknown error' ) );
		}

		$user = $body['user'] ?? [];

		return [
			'port' => 'main',
			'data' => array_merge($input, [
				'user_id'      => $user['id'] ?? '',
				'user_name'    => $user['name'] ?? '',
				'display_name' => $user['profile']['display_name'] ?? '',
				'email'        => $user['profile']['email'] ?? '',
				'is_admin'     => $user['is_admin'] ?? false,
			]),
		];
	}



	public static function supports_webhook(): bool {
		return true;
	}



	public static function get_webhook_setup_fields(): array {
		return [
			[
				'key'      => 'signing_secret',
				'label'    => 'Signing Secret',
				'type'     => 'password',
				'required' => true,
				'help'     => 'Slack app → Basic Information → App Credentials → Signing Secret. Without it this endpoint accepts unsigned requests from anyone.',
			],
		];
	}

	/**
	 * Slack verifies the Request URL by POSTing {"type":"url_verification",
	 * "challenge":"..."} to it and requires the challenge echoed back. Returning
	 * null here (as parse_webhook_event did) made Slack see the generic
	 * {"received":true} envelope and refuse to enable Event Subscriptions.
	 */
	public static function handle_webhook_handshake( \WP_REST_Request $request ): ?array {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) || 'url_verification' !== ( $body['type'] ?? '' ) ) {
			return null;
		}

		return [
			'body'         => (string) ( $body['challenge'] ?? '' ),
			'content_type' => 'text/plain',
		];
	}

	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		$signing_secret = self::get_webhook_setting( 'signing_secret', 'zaplane_slack_signing_secret' );

		if ( empty( $signing_secret ) ) {
			return true;
		}

		$timestamp = $request->get_header( 'x-slack-request-timestamp' );
		$signature = $request->get_header( 'x-slack-signature' );

		if ( ! $timestamp || ! $signature ) {
			return false;
		}

		if ( abs( time() - (int) $timestamp ) > 300 ) {
			return false;
		}

		$base_string    = 'v0:' . $timestamp . ':' . $request->get_body();
		$expected       = 'v0=' . hash_hmac( 'sha256', $base_string, $signing_secret );

		return hash_equals( $expected, $signature );
	}



	public static function parse_webhook_event( \WP_REST_Request $request ): ?array {
		$body = $request->get_json_params();
		$type = $body['type'] ?? '';

		if ( 'url_verification' === $type ) {
			return null;
		}

		if ( 'event_callback' !== $type ) {
			return null;
		}

		$event      = $body['event'] ?? [];
		$event_type = $event['type'] ?? '';

		$map = [
			'message'         => 'message_received',
			'app_mention'     => 'app_mention',
			'reaction_added'  => 'reaction_added',
			'channel_created' => 'channel_created',
			'file_shared'     => 'file_shared',
		];

		$normalized = $map[ $event_type ] ?? null;
		if ( ! $normalized ) {
			return null;
		}

		return [
			'event'   => $normalized,
			'payload' => array_merge($event, [
				'team_id'    => $body['team_id'] ?? '',
				'api_app_id' => $body['api_app_id'] ?? '',
			]),
		];
	}
}
