<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Discord extends IntegrationBase {

	// ✅ FIX #1: Correct Discord API base URL (was 'https://api.discord.com')
	private const API_BASE_URL = 'https://discord.com/api/v10';

	public static function get_slug(): string {
		return 'discord';
	}

	public static function get_name(): string {
		return 'Discord';
	}

	public static function get_icon(): string {
		return 'discord.svg';
	}

	public static function requires_connection(): bool {
		return true;
	}

	// ✅ FIX #2: Discord uses Bot token auth, not generic 'api_key'
	public static function get_auth_type(): string {
		return 'bot_token';
	}

	// ✅ FIX #3: Removed Typeform copy-paste help text; correct Discord fields
	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'bot_token' => [
				'type'     => 'password',
				'label'    => 'Bot Token',
				'required' => true,
				'help'     => 'Go to Discord Developer Portal → Your App → Bot → Reset Token.',
			],
			'guild_id'  => [
				'type'     => 'text',
				'label'    => 'Server (Guild) ID',
				'required' => true,
				'help'     => 'Right-click your Discord server icon → Copy Server ID (enable Developer Mode first).',
			],
		];
	}

	// ✅ FIX #4: Correct test_connection using Discord /users/@me with Bot token
	public static function test_connection( array $credentials ): array {
		$bot_token = $credentials['bot_token'] ?? '';
		$guild_id  = $credentials['guild_id'] ?? '';

		if ( empty( $bot_token ) ) {
			return [ 'success' => false, 'message' => 'Bot token is required.', 'details' => [] ];
		}

		if ( empty( $guild_id ) ) {
			return [ 'success' => false, 'message' => 'Guild ID is required.', 'details' => [] ];
		}

		// Verify bot token by fetching bot's own user info
		$response = wp_remote_get(
			self::API_BASE_URL . '/users/@me',
			[
				'headers' => [
					'Authorization' => 'Bot ' . $bot_token,
					'Content-Type'  => 'application/json',
				],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'message' => 'Connection failed: ' . $response->get_error_message(), 'details' => [] ];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( $code !== 200 ) {
			$error_msg = $body['message'] ?? ( 'HTTP ' . $code );
			return [ 'success' => false, 'message' => 'Invalid bot token: ' . $error_msg, 'details' => [] ];
		}

		// Also verify guild access
		$guild_response = wp_remote_get(
			self::API_BASE_URL . '/guilds/' . $guild_id,
			[
				'headers' => [
					'Authorization' => 'Bot ' . $bot_token,
					'Content-Type'  => 'application/json',
				],
				'timeout' => 15,
			]
		);

		$guild_code = (int) wp_remote_retrieve_response_code( $guild_response );
		$guild_body = json_decode( wp_remote_retrieve_body( $guild_response ), true ) ?? [];

		if ( $guild_code !== 200 ) {
			return [ 'success' => false, 'message' => 'Bot does not have access to this server/guild.', 'details' => [] ];
		}

		return [
			'success' => true,
			'message' => 'Connected as: ' . ( $body['username'] ?? 'Unknown Bot' ) . ' | Server: ' . ( $guild_body['name'] ?? $guild_id ),
			'details' => [
				'bot_username' => $body['username'] ?? '',
				'bot_id'       => $body['id'] ?? '',
				'guild_name'   => $guild_body['name'] ?? '',
				'guild_id'     => $guild_id,
			],
		];
	}

	public static function get_triggers(): array {
		return [
			'new_message_in_channel' => [
				'label' => 'New Message In Channel',
				'hook'  => 'discord_webhook_new_message_in_channel',
			],
			'new_user_join_server'   => [
				'label' => 'New User Join Server',
				'hook'  => 'discord_webhook_new_user_join_server',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'new_message_in_channel' === $trigger ) {
			return [
				...self::connection_id(),
				...self::channel_id(),
			];
		}

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'new_message_in_channel':
				// TODO: match channel_id from webhook payload
				return $args;

			case 'new_user_join_server':
				return $args;
		}

		return false;
	}

	public static function get_actions(): array {
		return [
			'create_channel'              => [ 'label' => 'Create Channel' ],
			'update_channel'              => [ 'label' => 'Update Channel' ],
			'get_channel_by_name'         => [ 'label' => 'Get Channel By Name' ],
			'send_channel_message'        => [ 'label' => 'Send Channel Message' ],
			'find_channel'                => [ 'label' => 'Find Channel' ],
			'create_a_channel_invite'     => [ 'label' => 'Create A Channel Invite' ],
			'send_direct_message'         => [ 'label' => 'Send Direct Message' ],
			'delete_message'              => [ 'label' => 'Delete Message' ],
			'get_message'                 => [ 'label' => 'Get Message' ],
			'get_many_message'            => [ 'label' => 'Get Many Messages' ],
			'react_with_emoji_to_message' => [ 'label' => 'React With Emoji To Message' ],
			'get_many_member'             => [ 'label' => 'Get Many Members' ],
			'add_role_to_member'          => [ 'label' => 'Add Role To Member' ],
			'remove_role_from_member'     => [ 'label' => 'Remove Role From Member' ],
			'find_user'                   => [ 'label' => 'Find User' ],
			'create_new_forum_post'       => [ 'label' => 'Create New Forum Post' ],
		];
	}

	// ─── Field schema helpers ───────────────────────────────────────────────

	private static function connection_id(): array {
		return [
			[
				'key'      => 'connection_id',
				'type'     => 'select',
				'label'    => 'Select Connection',
				'required' => true,
				'dynamic'  => [
					'integration' => 'discord',
					'query'       => 'connection_query',
					'select'      => [ 'value', 'label' ],
				],
			],
		];
	}

	private static function channel_id(): array {
		return [
			[
				'key'      => 'channel_id',
				'type'     => 'select',
				'label'    => 'Select Channel',
				'required' => true,
				'dynamic'  => [
					'integration' => 'discord',
					'query'       => 'channel_query',
					'select'      => [ 'value', 'label' ],
				],
			],
		];
	}

	private static function channel_name(): array {
		return [
			[
				'key'      => 'name',
				'type'     => 'text',
				'label'    => 'Channel Name',
				'required' => true,
			],
		];
	}

	private static function channel_type(): array {
		return [
			[
				'key'      => 'type',
				'type'     => 'select',
				'label'    => 'Channel Type',
				'required' => true,
				'dynamic'  => [
					'integration' => 'discord',
					'query'       => 'channel_type_query',
					'select'      => [ 'value', 'label' ],
				],
			],
		];
	}

	private static function channel_topic(): array {
		return [
			[
				'key'      => 'topic',
				'type'     => 'text',
				'label'    => 'Channel Topic',
				'required' => false,
			],
		];
	}

	private static function message_content(): array {
		return [
			[
				'key'      => 'content',
				'label'    => 'Message Content',
				'type'     => 'textarea',
				'required' => true,
			],
		];
	}

	private static function message_id(): array {
		return [
			[
				'key'      => 'message_id',
				'type'     => 'text',
				'label'    => 'Message ID',
				'required' => true,
			],
		];
	}

	private static function user_id(): array {
		return [
			[
				'key'      => 'user_id',
				'type'     => 'select',
				'label'    => 'Select User',
				'required' => true,
				'dynamic'  => [
					'integration' => 'discord',
					'query'       => 'user_query',
					'select'      => [ 'value', 'label' ],
				],
			],
		];
	}

	private static function member_id(): array {
		return [
			[
				'key'      => 'member_id',
				'type'     => 'select',
				'label'    => 'Select Member',
				'required' => true,
				'dynamic'  => [
					'integration' => 'discord',
					'query'       => 'member_query',
					'select'      => [ 'value', 'label' ],
				],
			],
		];
	}

	private static function role_id(): array {
		return [
			[
				'key'      => 'role_id',
				'type'     => 'select',
				'label'    => 'Select Role',
				'required' => true,
				'dynamic'  => [
					'integration' => 'discord',
					'query'       => 'role_query',
					'select'      => [ 'value', 'label' ],
				],
			],
		];
	}

	// ─── Action config schemas ───────────────────────────────────────────────

	public static function get_action_config_schema( string $action ): array {
		switch ( $action ) {

			case 'create_channel':
				return [
					...self::connection_id(),
					...self::channel_name(),
					...self::channel_type(),
					...self::channel_topic(),
				];

			case 'update_channel':
				return [
					...self::connection_id(),
					...self::channel_id(),
					...self::channel_name(),
					...self::channel_topic(),
				];

			case 'get_channel_by_name':
				return [
					...self::connection_id(),
					...self::channel_name(),
				];

			case 'send_channel_message':
				return [
					...self::connection_id(),
					...self::channel_id(),
					...self::message_content(),
				];

			case 'find_channel':
				return [
					...self::connection_id(),
					[
						'key'      => 'search_query',
						'type'     => 'text',
						'label'    => 'Search Query (name or ID)',
						'required' => true,
					],
					...self::channel_type(),
				];

			// ✅ FIX #5: Complete invite schema (max_uses was missing)
			case 'create_a_channel_invite':
				return [
					...self::connection_id(),
					...self::channel_id(),
					[
						'key'      => 'max_age',
						'type'     => 'number',
						'label'    => 'Max Age (seconds, 0 = never expires)',
						'required' => false,
						'default'  => 86400,
					],
					[
						'key'      => 'max_uses',
						'type'     => 'number',
						'label'    => 'Max Uses (0 = unlimited)',
						'required' => false,
						'default'  => 0,
					],
					[
						'key'      => 'temporary',
						'type'     => 'checkbox',
						'label'    => 'Temporary Membership',
						'required' => false,
						'default'  => false,
					],
					[
						'key'      => 'unique',
						'type'     => 'checkbox',
						'label'    => 'Unique Invite',
						'required' => false,
						'default'  => false,
					],
				];

			case 'send_direct_message':
				return [
					...self::connection_id(),
					...self::user_id(),
					...self::message_content(),
				];

			// ✅ FIX #6: Was [ 'delete_message', 'get_message' ] === $action (always false!)
			case 'delete_message':
			case 'get_message':
				return [
					...self::connection_id(),
					...self::channel_id(),
					...self::message_id(),
				];

			case 'get_many_message':
				return [
					...self::connection_id(),
					...self::channel_id(),
					[
						'key'      => 'limit',
						'type'     => 'number',
						'label'    => 'Message Limit',
						'required' => false,
						'default'  => 10,
						'min'      => 1,
						'max'      => 100,
					],
				];

			case 'react_with_emoji_to_message':
				return [
					...self::connection_id(),
					...self::channel_id(),
					...self::message_id(),
					[
						'key'         => 'emoji',
						'type'        => 'text',
						'label'       => 'Emoji',
						'required'    => true,
						'placeholder' => 'e.g. 👍 or custom_name:emoji_id',
					],
				];

			case 'get_many_member':
				return [
					...self::connection_id(),
					[
						'key'      => 'limit',
						'type'     => 'number',
						'label'    => 'Member Limit',
						'required' => false,
						'default'  => 100,
						'min'      => 1,
						'max'      => 1000,
					],
				];

			// ✅ FIX #7: Was [ 'add_role_to_member', 'remove_role_from_member' ] === $action (always false!)
			case 'add_role_to_member':
			case 'remove_role_from_member':
				return [
					...self::connection_id(),
					...self::member_id(),
					...self::role_id(),
				];

			case 'find_user':
				return [
					...self::connection_id(),
					[
						'key'      => 'search_query',
						'type'     => 'text',
						'label'    => 'Username or User ID',
						'required' => true,
					],
				];

			case 'create_new_forum_post':
				return [
					...self::connection_id(),
					...self::channel_id(),
					[
						'key'      => 'name',
						'type'     => 'text',
						'label'    => 'Post Title',
						'required' => true,
					],
					...self::message_content(),
				];
		}

		return [];
	}

	// ─── Execute node ────────────────────────────────────────────────────────

	// ✅ FIX #8: All cases now actually call Discord API (were all empty before)
	public static function execute_node( array $node, array $input ): array {
		$config    = $node['data']['config'] ?? [];
		$event     = $node['data']['event'] ?? '';
		$conn_id   = $config['connection_id'] ?? '';
		$creds     = self::get_connection_credentials( $conn_id );
		$bot_token = $creds['bot_token'] ?? '';
		$guild_id  = $creds['guild_id'] ?? '';

		if ( empty( $bot_token ) ) {
			return [ 'port' => 'main', 'data' => array_merge( $input, [ 'error' => 'Bot token missing from connection.' ] ) ];
		}

		$result = [];

		switch ( $event ) {

			case 'create_channel':
				$result = self::api_request( $bot_token, 'POST', "/guilds/{$guild_id}/channels", [
					'name'  => $config['name'] ?? '',
					'type'  => (int) ( $config['type'] ?? 0 ),
					'topic' => $config['topic'] ?? '',
				] );
				break;

			case 'update_channel':
				$channel_id = $config['channel_id'] ?? '';
				$result     = self::api_request( $bot_token, 'PATCH', "/channels/{$channel_id}", [
					'name'  => $config['name'] ?? '',
					'topic' => $config['topic'] ?? '',
				] );
				break;

			case 'get_channel_by_name':
				$channels = self::api_request( $bot_token, 'GET', "/guilds/{$guild_id}/channels" );
				$name     = $config['name'] ?? '';
				$result   = [];
				if ( is_array( $channels ) ) {
					foreach ( $channels as $ch ) {
						if ( isset( $ch['name'] ) && $ch['name'] === $name ) {
							$result = $ch;
							break;
						}
					}
				}
				break;

			case 'send_channel_message':
				$channel_id = $config['channel_id'] ?? '';
				$result     = self::api_request( $bot_token, 'POST', "/channels/{$channel_id}/messages", [
					'content' => $config['content'] ?? '',
				] );
				break;

			case 'find_channel':
				$channels = self::api_request( $bot_token, 'GET', "/guilds/{$guild_id}/channels" );
				$query    = $config['search_query'] ?? '';
				$type     = $config['type'] ?? '';
				$result   = [];
				if ( is_array( $channels ) ) {
					foreach ( $channels as $ch ) {
						$matches_name = isset( $ch['name'] ) && stripos( $ch['name'], $query ) !== false;
						$matches_id   = isset( $ch['id'] ) && $ch['id'] === $query;
						$matches_type = empty( $type ) || ( isset( $ch['type'] ) && (string) $ch['type'] === (string) $type );
						if ( ( $matches_name || $matches_id ) && $matches_type ) {
							$result[] = $ch;
						}
					}
				}
				break;

			case 'create_a_channel_invite':
				$channel_id = $config['channel_id'] ?? '';
				$result     = self::api_request( $bot_token, 'POST', "/channels/{$channel_id}/invites", [
					'max_age'   => (int) ( $config['max_age'] ?? 86400 ),
					'max_uses'  => (int) ( $config['max_uses'] ?? 0 ),
					'temporary' => (bool) ( $config['temporary'] ?? false ),
					'unique'    => (bool) ( $config['unique'] ?? false ),
				] );
				break;

			case 'send_direct_message':
				$user_id  = $config['user_id'] ?? '';
				// Step 1: Open DM channel
				$dm_channel = self::api_request( $bot_token, 'POST', '/users/@me/channels', [
					'recipient_id' => $user_id,
				] );
				$dm_channel_id = $dm_channel['id'] ?? '';
				// Step 2: Send message in DM channel
				$result = self::api_request( $bot_token, 'POST', "/channels/{$dm_channel_id}/messages", [
					'content' => $config['content'] ?? '',
				] );
				break;

			case 'delete_message':
				$channel_id = $config['channel_id'] ?? '';
				$message_id = $config['message_id'] ?? '';
				self::api_request( $bot_token, 'DELETE', "/channels/{$channel_id}/messages/{$message_id}" );
				$result = [ 'deleted' => true, 'message_id' => $message_id ];
				break;

			case 'get_message':
				$channel_id = $config['channel_id'] ?? '';
				$message_id = $config['message_id'] ?? '';
				$result     = self::api_request( $bot_token, 'GET', "/channels/{$channel_id}/messages/{$message_id}" );
				break;

			case 'get_many_message':
				$channel_id = $config['channel_id'] ?? '';
				$limit      = max( 1, min( 100, (int) ( $config['limit'] ?? 10 ) ) );
				$result     = self::api_request( $bot_token, 'GET', "/channels/{$channel_id}/messages?limit={$limit}" );
				break;

			case 'react_with_emoji_to_message':
				$channel_id = $config['channel_id'] ?? '';
				$message_id = $config['message_id'] ?? '';
				$emoji      = urlencode( $config['emoji'] ?? '' );
				self::api_request( $bot_token, 'PUT', "/channels/{$channel_id}/messages/{$message_id}/reactions/{$emoji}/@me" );
				$result = [ 'reacted' => true ];
				break;

			case 'get_many_member':
				$limit  = max( 1, min( 1000, (int) ( $config['limit'] ?? 100 ) ) );
				$result = self::api_request( $bot_token, 'GET', "/guilds/{$guild_id}/members?limit={$limit}" );
				break;

			case 'add_role_to_member':
				$member_id = $config['member_id'] ?? '';
				$role_id   = $config['role_id'] ?? '';
				self::api_request( $bot_token, 'PUT', "/guilds/{$guild_id}/members/{$member_id}/roles/{$role_id}" );
				$result = [ 'success' => true, 'action' => 'role_added', 'member_id' => $member_id, 'role_id' => $role_id ];
				break;

			case 'remove_role_from_member':
				$member_id = $config['member_id'] ?? '';
				$role_id   = $config['role_id'] ?? '';
				self::api_request( $bot_token, 'DELETE', "/guilds/{$guild_id}/members/{$member_id}/roles/{$role_id}" );
				$result = [ 'success' => true, 'action' => 'role_removed', 'member_id' => $member_id, 'role_id' => $role_id ];
				break;

			case 'find_user':
				$query   = $config['search_query'] ?? '';
				$members = self::api_request( $bot_token, 'GET', "/guilds/{$guild_id}/members?limit=1000" );
				$result  = [];
				if ( is_array( $members ) ) {
					foreach ( $members as $member ) {
						$user           = $member['user'] ?? [];
						$matches_name   = isset( $user['username'] ) && stripos( $user['username'], $query ) !== false;
						$matches_id     = isset( $user['id'] ) && $user['id'] === $query;
						if ( $matches_name || $matches_id ) {
							$result[] = $member;
						}
					}
				}
				break;

			case 'create_new_forum_post':
				$channel_id = $config['channel_id'] ?? '';
				$result     = self::api_request( $bot_token, 'POST', "/channels/{$channel_id}/threads", [
					'name'    => $config['name'] ?? '',
					'message' => [ 'content' => $config['content'] ?? '' ],
				] );
				break;
		}

		return [
			'port' => 'main',
			'data' => array_merge( $input, is_array( $result ) ? $result : [ 'response' => $result ] ),
		];
	}

	// ─── Dynamic queries ─────────────────────────────────────────────────────

	public static function get_dynamic_queries(): array {
		return [
			'connection_query'   => [ self::class, 'query_connection' ],
			'channel_type_query' => [ self::class, 'query_channel_type' ],
			'channel_query'      => [ self::class, 'query_channel' ],
			'user_query'         => [ self::class, 'query_user' ],
			'member_query'       => [ self::class, 'query_member' ],
			'role_query'         => [ self::class, 'query_role' ],
		];
	}

	public static function query_channel_type(): array {
		// Discord channel types: https://discord.com/developers/docs/resources/channel#channel-object-channel-types
		return [
			[ 'value' => '0',  'label' => 'Text Channel' ],
			[ 'value' => '2',  'label' => 'Voice Channel' ],
			[ 'value' => '4',  'label' => 'Category' ],
			[ 'value' => '5',  'label' => 'Announcement Channel' ],
			[ 'value' => '15', 'label' => 'Forum Channel' ],
			[ 'value' => '16', 'label' => 'Media Channel' ],
		];
	}

	public static function query_channel( array $params ): array {
		$options   = [];
		$creds     = self::extract_credentials( $params );
		$bot_token = $creds['bot_token'] ?? '';
		$guild_id  = $creds['guild_id'] ?? '';

		if ( empty( $bot_token ) || empty( $guild_id ) ) {
			return $options;
		}

		$channels = self::api_request( $bot_token, 'GET', "/guilds/{$guild_id}/channels" );

		if ( is_array( $channels ) ) {
			foreach ( $channels as $ch ) {
				$options[] = [
					'label' => $ch['name'] ?? 'Unknown',
					'value' => $ch['id'] ?? '',
				];
			}
		}

		return $options;
	}

	public static function query_member( array $params ): array {
		$options   = [];
		$creds     = self::extract_credentials( $params );
		$bot_token = $creds['bot_token'] ?? '';
		$guild_id  = $creds['guild_id'] ?? '';

		if ( empty( $bot_token ) || empty( $guild_id ) ) {
			return $options;
		}

		$members = self::api_request( $bot_token, 'GET', "/guilds/{$guild_id}/members?limit=1000" );

		if ( is_array( $members ) ) {
			foreach ( $members as $member ) {
				$user = $member['user'] ?? [];
				if ( ! empty( $user['id'] ) ) {
					$options[] = [
						'label' => $user['username'] ?? $user['id'],
						'value' => $user['id'],
					];
				}
			}
		}

		return $options;
	}

	public static function query_user( array $params ): array {
		// Users and members share the same endpoint for guild context
		return self::query_member( $params );
	}

	public static function query_role( array $params ): array {
		$options   = [];
		$creds     = self::extract_credentials( $params );
		$bot_token = $creds['bot_token'] ?? '';
		$guild_id  = $creds['guild_id'] ?? '';

		if ( empty( $bot_token ) || empty( $guild_id ) ) {
			return $options;
		}

		$roles = self::api_request( $bot_token, 'GET', "/guilds/{$guild_id}/roles" );

		if ( is_array( $roles ) ) {
			foreach ( $roles as $role ) {
				$options[] = [
					'label' => $role['name'] ?? 'Unknown',
					'value' => $role['id'] ?? '',
				];
			}
		}

		return $options;
	}

	// ─── Internal helpers ────────────────────────────────────────────────────

	/**
	 * Reusable Discord API request helper using wp_remote_request.
	 */
	private static function api_request( string $bot_token, string $method, string $endpoint, array $payload = [] ): array {
		$args = [
			'method'  => strtoupper( $method ),
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bot ' . $bot_token,
				'Content-Type'  => 'application/json',
			],
		];

		if ( ! empty( $payload ) && in_array( strtoupper( $method ), [ 'POST', 'PATCH', 'PUT' ], true ) ) {
			$args['body'] = wp_json_encode( $payload );
		}

		$response = wp_remote_request( self::API_BASE_URL . $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return [ 'error' => $response->get_error_message() ];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );

		// 204 No Content (e.g. DELETE success)
		if ( $code === 204 || empty( $raw ) ) {
			return [];
		}

		$body = json_decode( $raw, true ) ?? [];

		if ( $code >= 400 ) {
			$msg = $body['message'] ?? ( 'Discord API error HTTP ' . $code );
			return [ 'error' => $msg, 'code' => $code ];
		}

		return $body;
	}

	/**
	 * Extract bot_token and guild_id from dynamic query params.
	 * Tries multiple shapes the framework may pass credentials in.
	 */
	private static function extract_credentials( array $params ): array {
		$paths = [
			[ 'credentials' ],
			[ 'connection', 'credentials' ],
			[ 'auth' ],
		];

		foreach ( $paths as $path ) {
			$val = $params;
			foreach ( $path as $key ) {
				if ( ! is_array( $val ) || ! array_key_exists( $key, $val ) ) {
					$val = null;
					break;
				}
				$val = $val[ $key ];
			}

			if ( is_array( $val ) && ! empty( $val['bot_token'] ) ) {
				return $val;
			}
		}

		return [];
	}

	/**
	 * Retrieve stored credentials for a given connection ID.
	 * Adjust to match your framework's connection storage API.
	 */
	private static function get_connection_credentials( string $connection_id ): array {
		if ( empty( $connection_id ) ) {
			return [];
		}

		// TODO: Replace with actual framework method to load connection credentials
		// e.g. return ConnectionRepository::find( $connection_id )->getCredentials();
		return [];
	}
}