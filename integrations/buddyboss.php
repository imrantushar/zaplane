<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Buddyboss\ActionsResponseTrait;
use Zaplane\Integrations\Buddyboss\QueryTrait;
use Zaplane\Integrations\Buddyboss\BuddybossActionsTrait;

class Buddyboss extends IntegrationBase {

	use ActionsResponseTrait;
	use BuddybossActionsTrait;
	use QueryTrait;

	public static function get_slug(): string {
		return 'buddyboss';
	}

	public static function get_name(): string {
		return 'BuddyBoss';
	}

	public static function get_icon(): string {
		return 'buddyboss.svg';
	}

	public static function get_triggers(): array {
		return [
            'account_activated' => [
				'label' => 'Account Activated',
				'hook'  => 'bp_core_activated_user'
			],
            'follower_gained' => [
				'label' => 'New Follower Gained',
				'hook'  => 'bp_start_following'
			],
            'sent_friend_request' => [
				'label' => 'Sent Friend Request',
				'hook'  => 'friends_friendship_requested'
			],
            'accepted_friend_request' => [
				'label' => 'Accepted Friend Request',
				'hook'  => 'friends_friendship_accepted'
			],
            'create_groups' => [
				'label' => 'Create Group',
				'hook'  => 'groups_group_create_complete'
			],
            'access_requested_private_group' => [
				'label' => 'Access Requested Private Group',
				'hook'  => 'groups_membership_requested'
			],
			'joined_public_group' => [
				'label' => 'Joined Public Group',
				'hook'  => 'groups_join_group'
			],
			'joined_private_group' => [
				'label' => 'Joined Private Group',
				'hook'  => 'groups_membership_accepted'
			],
			'joined_specific_group' => [
				'label' => 'Joined Specific Group',
				'hook'  => 'groups_join_group'
			],
			'left_group' => [
				'label' => 'Left Group',
				'hook'  => 'groups_leave_group'
			],
			'left_private_group' => [
				'label' => 'Left Private Group',
				'hook'  => 'groups_leave_group'
			],
			'received_private_message' => [
				'label' => 'Received Private Message',
				'hook'  => 'messages_message_sent'
			],
			'profile_type_change' => [
				'label' => 'Profile Type Change',
				'hook'  => 'bp_set_member_type'
			],
			'updated_profile' => [
				'label' => 'Updated Profile',
				'hook'  => 'xprofile_updated_profile'
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'account_activated':
				$user_id = $args[0] ?? 0;

				if ( ! $user_id ) {
					return false;
				}

				return [
					'success' => true,
					'data'    => self::resolve_user_payload( $user_id ),
				];

			case 'follower_gained':
				// FIX: bp_start_following hook passes a single object (not two separate IDs).
				// $args[0] is a BP_Follow object with ->follower_id and ->leader_id properties.
				$follow = $args[0] ?? null;

				if (
					! is_object( $follow )
					|| ! property_exists( $follow, 'follower_id' )
					|| ! property_exists( $follow, 'leader_id' )
				) {
					return false;
				}

				return [
					'success' => true,
					'data'    => [
						'follower' => self::resolve_user_payload( (int) $follow->follower_id ),
						'leader'   => self::resolve_user_payload( (int) $follow->leader_id ),
					],
				];

			case 'sent_friend_request':
				$friendship_id     = $args[0] ?? 0;
				$initiator_user_id = $args[1] ?? 0;
				$friend_user_id    = $args[2] ?? 0;

				if ( ! $friendship_id ) {
					return false;
				}

				return [
					'success' => true,
					'data'    => [
						'friendship_id' => $friendship_id,
						'initiator'     => self::resolve_user_payload( $initiator_user_id ),
						'friend'        => self::resolve_user_payload( $friend_user_id ),
					],
				];

			case 'accepted_friend_request':
				$friendship_id     = $args[0] ?? 0;
				$initiator_user_id = $args[1] ?? 0;
				$friend_user_id    = $args[2] ?? 0;
				$friendship        = $args[3] ?? null;

				$data = [
					'friendship_id' => $friendship_id,
					'initiator'     => self::resolve_user_payload( $initiator_user_id ),
					'friend'        => self::resolve_user_payload( $friend_user_id ),
				];

				if ( $friendship !== null ) {
					$data['friendship'] = self::object_to_array( $friendship );
				}

				return [
					'success' => true,
					'data'    => $data,
				];

			case 'create_groups':
				$group_id = $args[0] ?? 0;

				if ( ! $group_id || ! function_exists( 'groups_get_group' ) ) {
					return false;
				}

				$group = groups_get_group( $group_id );
				$data  = self::object_to_array( $group );

				if ( function_exists( 'bp_groups_get_group_type' ) ) {
					$data['type'] = bp_groups_get_group_type( $group_id, false );
				}

				if ( function_exists( 'bp_get_group_cover_url' ) ) {
					$data['cover_url'] = bp_get_group_cover_url( $group );
				}

				if ( function_exists( 'bp_get_group_avatar_url' ) ) {
					$data['avatar_url'] = bp_get_group_avatar_url( $group );
				}

				if ( isset( $group->creator_id ) ) {
					$data['creator_data'] = self::resolve_user_payload( $group->creator_id );
				}

				return [
					'success' => true,
					'data'    => $data,
				];

			case 'access_requested_private_group':
			case 'joined_specific_group':
			case 'left_group':
				$group_id = $args[0] ?? 0;
				$user_id  = $args[1] ?? 0;

				if ( ! $group_id || ! function_exists( 'groups_get_group' ) ) {
					return false;
				}

				$group      = groups_get_group( $group_id );
				$group_data = self::object_to_array( $group );

				if ( empty( $group_data['type'] ) && function_exists( 'bp_groups_get_group_type' ) ) {
					$group_data['type'] = bp_groups_get_group_type( $group_id, false );
				}

				return [
					'success' => true,
					'data'    => [
						'user'  => self::resolve_user_payload( $user_id ),
						'group' => $group_data,
					],
				];

			case 'joined_public_group':
				$group_id = $args[0] ?? 0;
				$user_id  = $args[1] ?? 0;

				if ( ! $group_id || ! function_exists( 'groups_get_group' ) ) {
					return false;
				}

				$group = groups_get_group( $group_id );

				if ( isset( $group->status ) && $group->status !== 'public' ) {
					return false;
				}

				$group_data = self::object_to_array( $group );

				if ( empty( $group_data['type'] ) && function_exists( 'bp_groups_get_group_type' ) ) {
					$group_data['type'] = bp_groups_get_group_type( $group_id, false );
				}

				return [
					'success' => true,
					'data'    => [
						'user'  => self::resolve_user_payload( $user_id ),
						'group' => $group_data,
					],
				];

			case 'joined_private_group':
			case 'left_private_group':
				$group_id = $args[0] ?? 0;
				$user_id  = $args[1] ?? 0;

				if ( ! $group_id || ! function_exists( 'groups_get_group' ) ) {
					return false;
				}

				$group = groups_get_group( $group_id );

				if ( isset( $group->status ) && $group->status !== 'private' ) {
					return false;
				}

				$group_data = self::object_to_array( $group );

				if ( empty( $group_data['type'] ) && function_exists( 'bp_groups_get_group_type' ) ) {
					$group_data['type'] = bp_groups_get_group_type( $group_id, false );
				}

				return [
					'success' => true,
					'data'    => [
						'user'  => self::resolve_user_payload( $user_id ),
						'group' => $group_data,
					],
				];

			case 'received_private_message':
				$message = $args[0] ?? null;

				if (
					! is_object( $message )
					|| ! property_exists( $message, 'id' )
					|| ! property_exists( $message, 'sender_id' )
					|| ! property_exists( $message, 'recipients' )
				) {
					return false;
				}

				$data = [
					'message' => self::object_to_array( $message ),
					'sender'  => self::resolve_user_payload( $message->sender_id ),
				];

				if ( ! empty( $message->recipients ) ) {
					foreach ( $message->recipients as $recipient ) {
						if ( empty( $recipient->user_id ) || $recipient->user_id === $message->sender_id ) {
							continue;
						}

						$data['recipients'][] = self::resolve_user_payload( $recipient->user_id );
					}
				}

				return [
					'success' => true,
					'data'    => $data,
				];

			case 'profile_type_change':
				$user_id     = $args[0] ?? 0;
				$member_type = $args[1] ?? '';
				$append      = $args[2] ?? false;

				if ( ! $user_id ) {
					return false;
				}

				$data                = self::resolve_user_payload( $user_id );
				$data['member_type'] = $member_type;
				$data['append']      = $append;

				return [
					'success' => true,
					'data'    => $data,
				];

			case 'updated_profile':
				$user_id          = $args[0] ?? 0;
				$posted_field_ids = $args[1] ?? [];
				$errors           = $args[2] ?? [];
				$old_values       = $args[3] ?? [];
				$new_values       = $args[4] ?? [];

				if ( ! $user_id ) {
					return false;
				}

				$data = [
					'user'             => self::resolve_user_payload( $user_id ),
					'posted_field_ids' => $posted_field_ids,
					'errors'           => $errors,
					'old_values'       => $old_values,
					'new_values'       => $new_values,
				];

				if ( function_exists( 'xprofile_get_field' ) ) {
					foreach ( $posted_field_ids as $field_id ) {
						$field = xprofile_get_field( $field_id );
						if ( $field && isset( $field->data->value ) ) {
							$data['posted_fields'][ $field->name ] = $field->data->value;
						}
					}
				}

				return [
					'success' => true,
					'data'    => $data,
				];

		}//end switch

		return false;
	}

	public static function get_actions(): array {
		return [
			'create_activity_post'       => [ 'label' => 'Create Activity Post' ],
			'create_group_post'          => [ 'label' => 'Create Group Post' ],
			'create_user_activity_post'  => [ 'label' => 'Create User Activity Post' ],
			'add_user_to_group'          => [ 'label' => 'Add User To Group' ],
			'update_member_profile_type' => [ 'label' => 'Update Member Profile Type' ],
			'create_group'               => [ 'label' => 'Create Group' ],
			'remove_friend_connection'   => [ 'label' => 'Remove Friend Connection' ],
			'follow_user'                => [ 'label' => 'Follow User' ],
			'get_forum_subscribers'      => [ 'label' => 'Get Forum Subscribers' ],
			'create_forum_topic_reply'   => [ 'label' => 'Create Forum Topic Reply' ],
			'create_forum_topic'         => [ 'label' => 'Create Forum Topic' ],
			'remove_user_from_group'     => [ 'label' => 'Remove User From Group' ], // FIX: was "remove_user_form_group" (typo)
			'send_friend_request'        => [ 'label' => 'Send Friend Request' ],
			'send_group_message'         => [ 'label' => 'Send Group Message' ],
			'send_private_message'       => [ 'label' => 'Send Private Message' ],
			'send_group_notification'    => [ 'label' => 'Send Group Notification' ],
			'update_extended_profile'    => [ 'label' => 'Update Extended Profile' ],
			'update_user_status'         => [ 'label' => 'Update User Status' ],
			'stop_following_user'        => [ 'label' => 'Stop Following User' ],
			'subscribe_to_forum'         => [ 'label' => 'Subscribe To Forum' ], // FIX: was "Subscriber To Forum" (typo)
		];
	}

	private static function email( string $label = 'Email', string $key = 'email' ): array {
		return [
			[
				'key'      => $key,
				'label'    => $label,
				'type'     => 'email',
				'required' => true,
			],
		];
	}

	private static function content( string $label = 'Content', string $key = 'content' ): array {
		return [
			[
				'key'      => $key,
				'label'    => $label,
				'type'     => 'textarea',
				'required' => true,
			],
		];
	}

	private static function action(): array {
		return [
			[
				'key'      => 'action',
				'label'    => 'Activity Action',
				'type'     => 'text',
				'required' => false,
			],
		];
	}

	private static function action_link(): array {
		return [
			[
				'key'      => 'action_link',
				'label'    => 'Activity Action Link',
				'type'     => 'url',
				'required' => false,
			],
		];
	}

	private static function hide_sitewide(): array {
		return [
			[
				'key'      => 'hide_sitewide',
				'label'    => 'Hide Sitewide',
				'type'     => 'checkbox',
				'required' => false,
			],
		];
	}

	private static function message_subject(): array {
		return [
			[
				'key'      => 'message_subject',
				'label'    => 'Message Subject',
				'type'     => 'text',
				'required' => true,
			],
		];
	}

	public static function forum_id(): array {
		return [
			[
				'key'     => 'forum_id',
				'label'   => 'Select Forum',
				'type'    => 'select',
				'dynamic' => [
					'integration' => 'buddyboss',
					'query'       => 'forums_query',
					'select'      => [ 'value', 'label' ],
				],
				'required' => true,
			],
		];
	}

	public static function group_id(): array {
		return [
			[
				'key'     => 'group_id',
				'label'   => 'Select Group',
				'type'    => 'select',
				'dynamic' => [
					'integration' => 'buddyboss',
					'query'       => 'group_query',
					'select'      => [ 'value', 'label' ],
				],
				'required' => true,
			],
		];
	}

	public static function get_action_config_schema( string $action ): array {

		$schemas = [
			'create_activity_post' => [
				...self::email( 'Author Email', 'author_email' ),
				...self::content( 'Activity Content', 'content' ),
				...self::action(),
				...self::action_link(),
				...self::hide_sitewide(),
			],
			'create_group_post'    => [
				...self::group_id(),
				...self::email( 'Author Email', 'author_email' ),
				...self::content( 'Activity Content', 'content' ),
				...self::action(),
				...self::action_link(),
				...self::hide_sitewide(),
			],
			'create_user_activity_post' => [
				[
					'key'      => 'user_id',
					'label'    => 'User Activity ID',
					'type'     => 'number',
					'required' => true,
				],
				...self::email( 'Author Email', 'author_email' ),
				...self::content( 'Activity Content', 'content' ),
				...self::action(),
				...self::action_link(),
				...self::hide_sitewide(),
			],
			'add_user_to_group'          => [
				...self::email( 'User Email', 'user_email' ),
				...self::group_id(),
			],
			'update_member_profile_type' => [
				...self::email( 'User Email', 'user_email' ),
				[
					'key'      => 'profile_type',
					'label'    => 'Profile Type',
					'type'     => 'select',
					'required' => true,
					'dynamic'  => [
						'integration' => 'buddyboss',
						'query'       => 'member_types_query',
						'select'      => [ 'value', 'label' ],
					],
				],
			],
			'create_group' => [
				[
					'key'      => 'group_name',
					'label'    => 'Group Name',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'      => 'group_status',
					'label'    => 'Group Privacy Status',
					'type'     => 'select',
					'required' => true,
					'options'  => [
						[ 'value' => 'public',  'label' => 'Public' ],
						[ 'value' => 'private', 'label' => 'Private' ],
						[ 'value' => 'hidden',  'label' => 'Hidden' ],
					],
				],
				...self::email( 'Creator Email', 'creator_email' ),
				[
					'key'     => 'group_type',
					'label'   => 'Group Type',
					'type'    => 'select',
					'dynamic' => [
						'integration' => 'buddyboss',
						'query'       => 'group_types_query',
						'select'      => [ 'value', 'label' ],
					],
					'required' => false,
				],
			],
			'remove_friend_connection' => [
				...self::email( 'User Email', 'user_email' ),
				...self::email( 'Friend Email', 'friend_email' ),
			],
			'follow_user'              => [
				...self::email( 'Follower Email', 'follower_email' ),
				...self::email( 'Leader Email', 'leader_email' ),
			],
			'get_forum_subscribers'    => [
				[
					'key'      => 'forum_id',
					'label'    => 'Forum ID',
					'type'     => 'number',
					'required' => true,
				],
			],
			'create_forum_topic_reply' => [
				...self::forum_id(),
				[
					'key'      => 'topic_id',
					'label'    => 'Topic ID',
					'type'     => 'number',
					'required' => true,
				],
				[
					'key'      => 'reply_title',
					'label'    => 'Reply Title',
					'type'     => 'text',
					'required' => true,
				],
				...self::content( 'Reply Content', 'reply_content' ),
				...self::email( 'Author Email', 'author_email' ),
			],
			'create_forum_topic'       => [
				...self::forum_id(),
				[
					'key'      => 'topic_title',
					'label'    => 'Topic Title',
					'type'     => 'text',
					'required' => true,
				],
				...self::content( 'Topic Content', 'topic_content' ),
				...self::email( 'Topic Creator Email', 'creator_email' ),
			],
			'remove_user_from_group'   => [ // FIX: was "remove_user_form_group" (typo)
				...self::email( 'User Email', 'user_email' ),
				...self::group_id(),
			],
			'send_friend_request'      => [
				...self::email( 'Sender Email', 'sender_email' ),
				...self::email( 'Receiver Email', 'receiver_email' ),
			],
			'send_group_message'       => [
				...self::group_id(),
				...self::email( 'Sender Email', 'sender_email' ),
				...self::message_subject(),
				...self::content( 'Message Content', 'message_content' ),
			],
			'send_private_message'     => [
				...self::email( 'Sender Email', 'sender_email' ),
				...self::email( 'Receiver Email', 'receiver_email' ),
				...self::message_subject(),
				...self::content( 'Message Content', 'message_content' ),
			],
			'send_group_notification'  => [
				...self::group_id(),
				...self::email( 'Sender Email', 'sender_email' ),
				...self::content( 'Notification Content', 'notification_content' ),
				[
					'key'      => 'notification_link',
					'label'    => 'Notification Link',
					'type'     => 'url',
					'required' => false,
				],
			],
			'update_extended_profile'  => [
				...self::email( 'User Email', 'user_email' ),
				[
					'key'      => 'first_name',
					'label'    => 'First Name',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'      => 'last_name',
					'label'    => 'Last Name',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'      => 'nick_name',
					'label'    => 'Nickname',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'      => 'paragraph',
					'label'    => 'Paragraph Text',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'      => 'number',
					'label'    => 'Number',
					'type'     => 'number',
					'required' => true,
				],
				[
					'key'      => 'checkbox',
					'label'    => 'Checkbox',
					'type'     => 'checkbox',
					'required' => true,
				],
				[
					'key'      => 'drop_down',
					'label'    => 'Drop Down',
					'type'     => 'text',
					'required' => true,
				],
				[
					'key'      => 'radio_buttons',
					'label'    => 'Radio Buttons',
					'type'     => 'text',
					'required' => true,
				],
			],
			'update_user_status'       => [
				...self::email( 'User Email', 'user_email' ),
				[
					'key'      => 'status',
					'label'    => 'Status',
					'type'     => 'select',
					'required' => true,
					'options'  => [
						[ 'value' => 'suspend',   'label' => 'Suspend' ],
						[ 'value' => 'unsuspend', 'label' => 'Unsuspend' ],
					],
				],
			],
			'stop_following_user'      => [
				...self::email( 'Follower Email', 'follower_email' ),
				...self::email( 'Leader Email', 'leader_email' ),
			],
			'subscribe_to_forum'       => [
				...self::email( 'User Email', 'user_email' ),
				...self::forum_id(),
			],
		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		$event  = $node['data']['event'] ?? '';
		$config = $node['data']['config'] ?? [];
		$method = 'action_' . $event;

		if ( method_exists( static::class, $method ) ) {
			return static::$method( $config, $input );
		}

		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'forums_query'      => [ self::class, 'query_forums' ],
			'group_query'       => [ self::class, 'query_group' ],
			'group_types_query' => [ self::class, 'query_group_types' ],
			'member_types_query' => [ self::class, 'query_member_types' ],
		];
	}

	private static function object_to_array( $object ): array {
		return json_decode( json_encode( $object ), true ) ?? [];
	}

	private static function resolve_user_payload( int $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		return [
			'user_id'      => (string) $user->ID,
			'first_name'   => get_user_meta( $user_id, 'first_name', true ),
			'last_name'    => get_user_meta( $user_id, 'last_name', true ),
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'nickname'     => $user->nickname,
			'avatar_url'   => get_avatar_url( $user_id ),
			'display_name' => $user->display_name,
			'user_roles'   => $user->roles,
		];
	}

	private static function require_fields( array $data, array $fields ): ?array {
		foreach ( $fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return self::action_error( "Field '{$field}' is required." );
			}
		}

		return null;
	}

	private static function resolve_follow_function( string $action ): ?callable {
		if ( $action === 'start' ) {
			if ( function_exists( 'bp_follow_start_following' ) && function_exists( 'bp_is_active' ) && bp_is_active( 'follow' ) ) {
				return 'bp_follow_start_following';
			}
			if ( function_exists( 'bp_start_following' ) ) {
				return 'bp_start_following';
			}
		}

		if ( $action === 'stop' ) {
			if ( function_exists( 'bp_follow_stop_following' ) && function_exists( 'bp_is_active' ) && bp_is_active( 'follow' ) ) {
				return 'bp_follow_stop_following';
			}
			if ( function_exists( 'bp_stop_following' ) ) {
				return 'bp_stop_following';
			}
		}

		return null;
	}

	private static function handle_activity_post( array $data, string $component = 'activity', ?int $group_id = null, ?int $user_activity_id = null ): array {
		$author_id = email_exists( $data['author_email'] );

		if ( ! $author_id ) {
			return self::action_error( 'User not found with provided email.' );
		}

		if ( ! function_exists( 'bp_activity_add' ) ) {
			return self::action_error( 'BuddyBoss Activity functions not found.' );
		}

		$payload = [
			'action'        => $data['action'] ?? '',
			'content'       => $data['content'] ?? '',
			'primary_link'  => $data['action_link'] ?? '',
			'hide_sitewide' => $data['hide_sitewide'] ?? false,
			'component'     => $component,
			'type'          => 'activity_update',
		];

		if ( $group_id !== null ) {
			$payload['user_id'] = $author_id;
			$payload['item_id'] = $group_id;
		}

		if ( $user_activity_id !== null ) {
			$payload['user_id']           = $author_id;
			$payload['secondary_item_id'] = $user_activity_id;
		}

		$activity_id = bp_activity_add( $payload );

		if ( ! $activity_id ) {
			return self::action_error( 'Failed to add activity.' );
		}

		$response = [
			'activity_id' => $activity_id,
			'user_id'     => $author_id,
		];

		if ( $group_id !== null ) {
			$response['group_id'] = $group_id;
		}

		if ( function_exists( 'bp_activity_get_specific' ) ) {
			$activity = bp_activity_get_specific( [ 'activity_ids' => $activity_id ] );
			if ( ! empty( $activity['activities'] ) ) {
				$response['activities'] = self::object_to_array( $activity );
			}
		}

		return self::action_success( $response );
	}
}