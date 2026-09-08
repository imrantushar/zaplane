<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Traits\ActionResponseTrait;


class Discord extends IntegrationBase {

	use ActionResponseTrait;

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

	public static function get_auth_type(): string {
		return 'bot_token';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'bot_token' => [
				'type'     => 'password',
				'label'    => 'Bot Token',
				'required' => true,
				'help'     => 'Go To Discord Developer Portal (discord.com/developers/applications) → Your App → Bot → Reset Token.',
			],
		];
	}

	public static function test_connection( array $credentials ): array {
		$bot_token = $credentials['bot_token'] ?? '';

		if ( empty( $bot_token ) ) {
			return [
				'success' => false,
				'message' => 'Bot token is required.',
				'details' => []
			];
		}

		$body = self::api_request( $bot_token, 'GET', '/users/@me' );

		if ( isset( $body['error'] ) ) {
			return [
				'success' => false,
				'message' => 'Invalid bot token: ' . $body['error'],
				'details' => [],
			];
		}

		$guilds      = self::fetch_bot_guilds( $bot_token );
		$guild_count = count( $guilds );

		return [
			'success' => true,
			'message' => 'Connected as: ' . ( $body['username'] ?? 'Unknown Bot' ) . ' | Servers: ' . $guild_count,
			'details' => [
				'bot_username' => $body['username'] ?? '',
				'bot_id'       => $body['id'] ?? '',
				'guild_count'  => $guild_count,
			],
		];
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

	private static function guild_id(): array {
		return [
			[
				'key'      => 'guild_id',
				'type'     => 'select',
				'label'    => 'Select Server',
				'required' => true,
				'dynamic'  => [
					'integration' => 'discord',
					'query'       => 'guild_query',
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
					'depends_on'  => [ 'guild_id' ],
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
					'depends_on'  => [ 'guild_id' ],
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
				'type'     => 'textarea',
				'label'    => 'Message Content',
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
					'depends_on'  => [ 'guild_id' ],
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
					'depends_on'  => [ 'guild_id' ],
				],
			],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		switch ( $action ) {

			case 'create_channel':
				return [
					...self::guild_id(),
					...self::channel_name(),
					...self::channel_type(),
					...self::channel_topic(),
				];

			case 'update_channel':
				return [
					...self::guild_id(),
					...self::channel_id(),
					...self::channel_name(),
					...self::channel_topic(),
				];

			case 'get_channel_by_name':
				return [
					...self::guild_id(),
					...self::channel_name(),
				];

			case 'send_channel_message':
				return [
					...self::guild_id(),
					...self::channel_id(),
					...self::message_content(),
				];

			case 'find_channel':
				return [
					...self::guild_id(),
					[
						'key'      => 'search_query',
						'type'     => 'text',
						'label'    => 'Search Query (name or ID)',
						'required' => true,
					],
					...self::channel_type(),
				];

			case 'create_a_channel_invite':
				return [
					...self::guild_id(),
					...self::channel_id(),
					[
						'key'      => 'max_age',
						'type'     => 'number',
						'label'    => 'Max Age (seconds, 0 = never expires)',
						'required' => true,
					],
					[
						'key'      => 'max_uses',
						'type'     => 'number',
						'label'    => 'Max Uses (0 = unlimited)',
						'required' => true,
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
					...self::user_id(),
					...self::message_content(),
				];

			case 'delete_message':
			case 'get_message':
				return [
					...self::guild_id(),
					...self::channel_id(),
					...self::message_id(),
				];

			case 'get_many_message':
				return [
					...self::guild_id(),
					...self::channel_id(),
					[
						'key'      => 'limit',
						'type'     => 'number',
						'label'    => 'Message Limit',
						'required' => true,
						'min'      => 1,
						'max'      => 100,
					],
				];

			case 'react_with_emoji_to_message':
				return [
					...self::guild_id(),
					...self::channel_id(),
					...self::message_id(),
					[
						'key'         => 'emoji',
						'type'        => 'text',
						'label'       => 'Emoji',
						'required'    => true,
						'placeholder' => 'e.g. or custom_name:emoji_id',
					],
				];

			case 'get_many_member':
				return [
					...self::guild_id(),
					[
						'key'      => 'limit',
						'type'     => 'number',
						'label'    => 'Member Limit',
						'required' => true,
						'min'      => 1,
						'max'      => 1000,
					],
				];

			case 'add_role_to_member':
			case 'remove_role_from_member':
				return [
					...self::guild_id(),
					...self::member_id(),
					...self::role_id(),
				];

			case 'find_user':
				return [
					...self::guild_id(),
					[
						'key'      => 'search_query',
						'type'     => 'text',
						'label'    => 'Username or User ID',
						'required' => true,
					],
				];

			case 'create_new_forum_post':
				return [
					...self::guild_id(),
					...self::channel_id(),
					[
						'key'      => 'name',
						'type'     => 'text',
						'label'    => 'Post Title',
						'required' => true,
					],
					...self::message_content(),
				];
		}//end switch

		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$config    = $node['data']['config'] ?? [];
		$event     = $node['data']['event'] ?? '';
		$creds     = self::get_connection_credentials( $node );
		$bot_token = $creds['bot_token'] ?? '';
		$guild_id  = $config['guild_id'] ?? '';

		if ( empty( $bot_token ) ) {
			return [
				'port' => 'main',
				'data' => array_merge( $input, [ 'error' => 'Bot token missing from connection.' ] ),
			];
		}

		switch ( $event ) {

			case 'create_channel':
				$name = trim( $config['name'] ?? '' );
				if ( empty( $name ) ) {
					return self::error( __( 'Channel name is required.', 'zaplane' ), $input );
				}
				$payload = [
					'name' => $name,
					'type' => (int) ( $config['type'] ?? 0 ),
				];
				if ( ! empty( $config['topic'] ) ) {
					$payload['topic'] = $config['topic'];
				}

				return self::success( array_merge( $input, [
					'channel' => self::api_request( $bot_token, 'POST', "/guilds/{$guild_id}/channels", $payload ),
				] ) );

			case 'update_channel':
				$name       = trim( $config['name'] ?? '' );
				$channel_id = $config['channel_id'] ?? '';
				if ( empty( $name ) ) {
					return self::error( __( 'Channel name is required.', 'zaplane' ), $input );
				}
				$payload = [ 'name' => $name ];
				if ( ! empty( $config['topic'] ) ) {
					$payload['topic'] = $config['topic'];
				}
				return self::success( array_merge( $input, [
					'channel' => self::api_request( $bot_token, 'PATCH', "/channels/{$channel_id}", $payload ),
				] ) );

			case 'get_channel_by_name':
				$channels = self::api_request( $bot_token, 'GET', "/guilds/{$guild_id}/channels" );
				$name     = $config['name'] ?? '';
				if ( is_array( $channels ) ) {
					foreach ( $channels as $channel ) {
						if ( isset( $channel['name'] ) && $channel['name'] === $name ) {
							return self::success( array_merge( $input, [
								'channel' => $channel,
							] ) );
						}
					}
				}
				return self::error( __( 'Channel not found.', 'zaplane' ), $input );

			case 'send_channel_message':
				$channel_id = $config['channel_id'] ?? '';
				return self::success( array_merge( $input, [
					'channel' => self::api_request( $bot_token, 'POST', "/channels/{$channel_id}/messages", [
						'content' => $config['content'] ?? '',
					] ),
				] ) );

			case 'find_channel':
				$channels = self::api_request( $bot_token, 'GET', "/guilds/{$guild_id}/channels" );
				$query    = $config['search_query'] ?? '';
				$type     = $config['type'] ?? '';
				if ( is_array( $channels ) ) {
					foreach ( $channels as $channel ) {
						$matches_name = isset( $channel['name'] ) && stripos( $channel['name'], $query ) !== false;
						$matches_id   = isset( $channel['id'] ) && $channel['id'] === $query;
						$matches_type = '' === $type || ( isset( $channel['type'] ) && (string) $channel['type'] === (string) $type );
						if ( ( $matches_name || $matches_id ) && $matches_type ) {
							return self::success( array_merge( $input, [
								'channel' => $channel,
							] ) );
						}
					}
				}
				return self::error( __( 'Channel not found.', 'zaplane' ), $input );

			case 'create_a_channel_invite':
				// FIX: was defaulting to '86400' (copy-paste from max_age) instead of ''.
				$channel_id = $config['channel_id'] ?? '';
				return self::success( array_merge( $input, [
					'channel' => self::api_request( $bot_token, 'POST', "/channels/{$channel_id}/invites", [
						'max_age'   => (int) ( $config['max_age'] ?? 86400 ),
						'max_uses'  => (int) ( $config['max_uses'] ?? 0 ),
						'temporary' => (bool) ( $config['temporary'] ?? false ),
						'unique'    => (bool) ( $config['unique'] ?? false ),
					] ),
				] ) );

			case 'send_direct_message':
				$dm_channel = self::api_request( $bot_token, 'POST', '/users/@me/channels', [
					'recipient_id' => $config['user_id'] ?? '',
				] );

				if ( isset( $dm_channel['error'] ) || empty( $dm_channel['id'] ) ) {
					return self::error(
						sprintf(
							esc_html__( 'Failed to open DM channel: %s', 'zaplane' ),
							$dm_channel['error'] ?? 'No channel id returned'
						),
						$input
					);
				}

				return self::success( array_merge( $input, [
					'channel' => self::api_request( $bot_token, 'POST', "/channels/{$dm_channel['id']}/messages", [
						'content' => $config['content'] ?? '',
					] ),
				] ) );

			case 'delete_message':
				$channel_id = $config['channel_id'] ?? '';
				$message_id = $config['message_id'] ?? '';
				self::api_request( $bot_token, 'DELETE', "/channels/{$channel_id}/messages/{$message_id}" );
				return self::success( array_merge( $input, [
					'deleted' => true,
					'message_id' => $message_id,
				] ) );

			case 'get_message':
				$channel_id = $config['channel_id'] ?? '';
				$message_id = $config['message_id'] ?? '';
				return self::success( array_merge( $input, [
					'message' => self::api_request( $bot_token, 'GET', "/channels/{$channel_id}/messages/{$message_id}" ),
				] ) );

			case 'get_many_message':
				// FIX: was defaulting to '10' (copy-paste from limit) instead of ''.
				$channel_id = $config['channel_id'] ?? '';
				$limit      = max( 1, min( 100, (int) ( $config['limit'] ?? 10 ) ) );
				return self::success( array_merge( $input, [
					'messages' => self::api_request( $bot_token, 'GET', "/channels/{$channel_id}/messages?limit={$limit}" ),
				] ) );

			case 'react_with_emoji_to_message':
				$channel_id = $config['channel_id'] ?? '';
				$message_id = $config['message_id'] ?? '';

				$emoji      = rawurlencode( $config['emoji'] ?? '' );
				self::api_request( $bot_token, 'PUT', "/channels/{$channel_id}/messages/{$message_id}/reactions/{$emoji}/@me" );
				return self::success( array_merge( $input, [
					'reacted' => true,
				] ) );

			case 'get_many_member':
				$limit  = max( 1, min( 1000, (int) ( $config['limit'] ?? 100 ) ) );
				return self::success( array_merge( $input, [
					'members' => self::api_request( $bot_token, 'GET', "/guilds/{$guild_id}/members?limit={$limit}" ),
				] ) );

			case 'add_role_to_member':
				$member_id = $config['member_id'] ?? '';
				$role_id   = $config['role_id'] ?? '';
				self::api_request( $bot_token, 'PUT', "/guilds/{$guild_id}/members/{$member_id}/roles/{$role_id}" );
				return self::success( array_merge( $input, [
					'action' => 'role_added',
					'member_id' => $member_id,
					'role_id' => $role_id,
				] ) );

			case 'remove_role_from_member':
				$member_id = $config['member_id'] ?? '';
				$role_id   = $config['role_id'] ?? '';
				self::api_request( $bot_token, 'DELETE', "/guilds/{$guild_id}/members/{$member_id}/roles/{$role_id}" );
				return self::success( array_merge( $input, [
					'action' => 'role_removed',
					'member_id' => $member_id,
					'role_id' => $role_id,
				] ) );

			case 'find_user':
				$query   = $config['search_query'] ?? '';
				$members = self::fetch_all_members( $bot_token, $guild_id );
				foreach ( $members as $member ) {
					$user         = $member['user'] ?? [];
					$matches_name = isset( $user['username'] ) && stripos( $user['username'], $query ) !== false;
					$matches_id   = isset( $user['id'] ) && $user['id'] === $query;
					if ( $matches_name || $matches_id ) {
						return self::success( array_merge( $input, [
							'member' => $member,
						] ) );
					}
				}
				return self::error( __( 'User not found.', 'zaplane' ), $input );

			case 'create_new_forum_post':
				$channel_id = $config['channel_id'] ?? '';
				return self::success( array_merge( $input, [
					'thread' => self::api_request( $bot_token, 'POST', "/channels/{$channel_id}/threads", [
						'name'                  => $config['name'] ?? '',
						'auto_archive_duration' => 1440,
						'message'               => [
							'content' => $config['content'] ?? '',
						],
					] ),
				] ) );

		}//end switch

		return [
			'port' => 'main',
			'data' => $input
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'guild_query'        => [ self::class, 'query_guild' ],
			'channel_type_query' => [ self::class, 'query_channel_type' ],
			'channel_query'      => [ self::class, 'query_channel' ],
			'user_query'         => [ self::class, 'query_user' ],
			'member_query'       => [ self::class, 'query_member' ],
			'role_query'         => [ self::class, 'query_role' ],
		];
	}

	public static function get_dynamic_fields(): array {
		return self::get_dynamic_queries();
	}

	public static function query_guild( array $query ): array {
		$options   = [];
		$creds     = self::extract_credentials( $query );
		$bot_token = $creds['bot_token'] ?? '';
		if ( empty( $bot_token ) ) {
			return [];
		}

		$guilds = self::fetch_bot_guilds( $bot_token );

		$seen = [];

		foreach ( $guilds as $guild ) {
			$id   = $guild['id'] ?? '';
			$name = $guild['name'] ?? 'Unknown Server';

			if ( empty( $id ) || isset( $seen[ $id ] ) ) {
				continue;
			}

			$seen[ $id ] = true;

			$options[] = [
				'label' => $name,
				'value' => $id,
			];
		}

		return $options;
	}

	public static function query_channel_type( array $query = [] ): array {
		return [
			[
				'value' => '0',
				'label' => 'Text Channel'
			],
			[
				'value' => '2',
				'label' => 'Voice Channel'
			],
			[
				'value' => '4',
				'label' => 'Category'
			],
			[
				'value' => '5',
				'label' => 'Announcement Channel'
			],
			[
				'value' => '15',
				'label' => 'Forum Channel'
			],
			[
				'value' => '16',
				'label' => 'Media Channel'
			],
		];
	}

	public static function query_channel( array $query ): array {
		$options   = [];
		$creds     = self::extract_credentials( $query );
		$bot_token = $creds['bot_token'] ?? '';

		if ( empty( $bot_token ) ) {
			return [];
		}

		$guild_id = $query['where']['guild_id'] ?? $query['guild_id'] ?? '';

		$guilds = $guild_id
			? [
				[
					'id' => $guild_id,
					'name' => ''
				]
			]
			: self::fetch_bot_guilds( $bot_token );

		$multi = count( $guilds ) > 1;

		foreach ( $guilds as $guild ) {
			$gid   = $guild['id'] ?? '';
			$gname = $guild['name'] ?? '';

			if ( empty( $gid ) ) {
				continue;
			}

			$channels = self::api_request( $bot_token, 'GET', "/guilds/{$gid}/channels" );

			if ( ! is_array( $channels ) || isset( $channels['error'] ) ) {
				continue;
			}

			usort( $channels, fn( $a, $b ) => strcmp(
				strtolower( $a['name'] ?? '' ),
				strtolower( $b['name'] ?? '' )
			) );

			foreach ( $channels as $channel ) {
				$id   = $channel['id'] ?? '';
				$name = $channel['name'] ?? '';

				if ( empty( $id ) ) {
					continue;
				}

				$label = '#' . ( ! empty( $name ) ? $name : 'Unknown Channel' );
				if ( $multi && $gname ) {
					$label = '[' . $gname . '] ' . $label;
				}

				$options[] = [
					'label' => $label,
					'value' => $id,
				];
			}
		}//end foreach

		return $options;
	}


	public static function query_member( array $query ): array {
		$options   = [];
		$creds     = self::extract_credentials( $query );
		$bot_token = $creds['bot_token'] ?? '';

		if ( empty( $bot_token ) ) {
			return [];
		}

		$guild_id = $query['where']['guild_id'] ?? $query['guild_id'] ?? '';

		$guilds = $guild_id
			? [
				[
					'id' => $guild_id,
					'name' => ''
				]
			]
			: self::fetch_bot_guilds( $bot_token );

		$multi = count( $guilds ) > 1;
		$seen  = [];

		foreach ( $guilds as $guild ) {
			$gid   = $guild['id'] ?? '';
			$gname = $guild['name'] ?? '';

			if ( empty( $gid ) ) {
				continue;
			}

			$members = self::fetch_all_members( $bot_token, $gid );

			foreach ( $members as $member ) {
				$user = $member['user'] ?? [];
				$uid  = $user['id'] ?? '';

				if ( empty( $uid ) || isset( $seen[ $uid ] ) ) {
					continue;
				}
				$seen[ $uid ] = true;

				$name  = $user['global_name'] ?? $user['username'] ?? $uid;
				$label = $name;
				if ( $multi && $gname ) {
					$label = '[' . $gname . '] ' . $name;
				}

				$options[] = [
					'label' => $label,
					'value' => $uid,
				];
			}
		}//end foreach

		return $options;
	}

	public static function query_user( array $query ): array {
		return self::query_member( $query );
	}

	public static function query_role( array $query ): array {
		$options   = [];
		$creds     = self::extract_credentials( $query );
		$bot_token = $creds['bot_token'] ?? '';

		if ( empty( $bot_token ) ) {
			return [];
		}

		$guild_id = $query['where']['guild_id'] ?? $query['guild_id'] ?? '';

		$guilds = $guild_id
			? [
				[
					'id' => $guild_id,
					'name' => ''
				]
			]
			: self::fetch_bot_guilds( $bot_token );

		$multi = count( $guilds ) > 1;

		foreach ( $guilds as $guild ) {
			$gid   = $guild['id'] ?? '';
			$gname = $guild['name'] ?? '';

			if ( empty( $gid ) ) {
				continue;
			}

			$roles = self::api_request( $bot_token, 'GET', "/guilds/{$gid}/roles" );

			if ( ! is_array( $roles ) || isset( $roles['error'] ) ) {
				continue;
			}

			usort( $roles, fn( $a, $b ) => ( $b['position'] ?? 0 ) <=> ( $a['position'] ?? 0 ) );

			foreach ( $roles as $role ) {
				$id   = $role['id'] ?? '';
				$name = $role['name'] ?? '';

				if ( empty( $id ) || $id === $gid ) {
					continue;
				}

				$label = ! empty( $name ) ? $name : 'Unknown Role';
				if ( $multi && $gname ) {
					$label = '[' . $gname . '] ' . $label;
				}

				$options[] = [
					'label' => $label,
					'value' => $id,
				];
			}
		}//end foreach

		return $options;
	}

	private static function fetch_all_members( string $bot_token, string $guild_id ): array {
		$all        = [];
		$after      = null;
		$page_count = 0;

		do {
			$endpoint = "/guilds/{$guild_id}/members?limit=1000";
			if ( $after ) {
				$endpoint .= '&after=' . $after;
			}

			$page = self::api_request( $bot_token, 'GET', $endpoint );

			if ( ! is_array( $page ) || isset( $page['error'] ) || empty( $page ) ) {
				break;
			}

			foreach ( $page as $member ) {
				$all[] = $member;
			}

			$page_count = count( $page );
			$last       = end( $page );
			$after      = $last['user']['id'] ?? null;

		} while ( 1000 === $page_count && $after );

		return $all;
	}

	private static function fetch_bot_guilds( string $bot_token ): array {
		$guilds = self::api_request( $bot_token, 'GET', '/users/@me/guilds' );

		if ( ! is_array( $guilds ) || isset( $guilds['error'] ) ) {
			return [];
		}

		return $guilds;
	}

	private static function api_request(
		string $bot_token,
		string $method,
		string $endpoint,
		array $payload = [],
		int $retry_count = 0
	): array {
		$method_upper = strtoupper( $method );

		$body_methods = [ 'POST', 'PATCH', 'PUT' ];
		$sends_body   = in_array( $method_upper, $body_methods, true ) && ! empty( $payload );

		$args = [
			'method'  => $method_upper,
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bot ' . $bot_token,
			],
		];

		if ( $sends_body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $payload );
		}

		$response = wp_remote_request( self::API_BASE_URL . $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return [ 'error' => $response->get_error_message() ];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );

		if ( 204 === $code || '' === $raw ) {
			return [];
		}

		$body = json_decode( $raw, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return [
				'error' => 'Invalid JSON response',
				'raw' => $raw
			];
		}

		if ( 429 === $code ) {
			if ( $retry_count >= 3 ) {
				return [
					'error' => 'Discord rate limit — max retries exceeded.',
					'code' => 429
				];
			}

			$retry_after = isset( $body['retry_after'] ) ? (float) $body['retry_after'] : 1.0;

			if ( $retry_after > 10 ) {
				$retry_after = $retry_after / 1000;
			}

			sleep( (int) ceil( $retry_after ) );

			return self::api_request( $bot_token, $method, $endpoint, $payload, $retry_count + 1 );
		}

		if ( $code >= 400 ) {
			$msg = $body['message'] ?? ( 'Discord API error HTTP ' . $code );
			return [
				'error' => $msg,
				'code' => $code
			];
		}

		return $body;
	}

	private static function extract_credentials( array $params ): array {
		$connection_id = $params['where']['connection_id']
			?? $params['connection_id']
			?? $params['data']['connection_id']
			?? 0;

		return self::get_decrypted_credentials( (int) $connection_id );
	}

	private static function get_connection_credentials( array $node ): array {
		$connection_id = (int) (
			$node['data']['connection_id']
			?? $node['connection_id']
			?? 0
		);

		return self::get_decrypted_credentials( $connection_id );
	}

	private static function get_decrypted_credentials( int $connection_id = 0 ): array {
		try {
			$cm = new ConnectionManager();

			if ( $connection_id <= 0 ) {
				$connection_id = self::get_discord_connection_id();
			}

			if ( $connection_id <= 0 ) {
				return [];
			}

			$creds = $cm->get_execution_credentials( $connection_id );

			if ( is_array( $creds ) && ! empty( $creds['bot_token'] ) ) {
				return $creds;
			}

			if ( is_object( $creds ) ) {
				$creds = (array) $creds;
				if ( ! empty( $creds['bot_token'] ) ) {
					return $creds;
				}
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}//end try

		return [];
	}

	private static function get_discord_connection_id(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'zaplane_connections';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from $wpdb->prefix, not user input.
		$id    = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
				'SELECT id FROM `' . esc_sql( $table ) . "` WHERE app = %s AND status = 'active' ORDER BY id DESC LIMIT 1",
				'discord'
			)
		);
		return (int) $id;
	}
}
