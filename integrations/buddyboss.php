<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Traits\ActionResponseTrait;

class Buddyboss extends IntegrationBase {

	use ActionResponseTrait;

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
            'create_group' => [
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
			case 'follower_gained':
			case 'sent_friend_request':
			case 'accepted_friend_request':
			case 'create_group':
			case 'access_requested_private_group':
			case 'joined_public_group':
			case 'joined_private_group':
			case 'joined_specific_group':
			case 'left_group':
			case 'left_private_group':
			case 'received_private_message':
			case 'profile_type_change':
			case 'updated_profile':

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
			'create_group'        		 => [ 'label' => 'Create Group' ],
			'remove_friend_connection'   => [ 'label' => 'Remove Friend Connection' ],
			'follow_user'              	 => [ 'label' => 'Follow User' ],
			'get_forum_subscribers'      => [ 'label' => 'Get Forum Subscribers' ],
			'create_forum_topic_reply'   => [ 'label' => 'Create Forum Topic Reply' ],
			'create_forum_topic'         => [ 'label' => 'Create Forum Topic' ],
			'remove_user_form_group'     => [ 'label' => 'Remove User Form Group' ],
			'send_friend_request'        => [ 'label' => 'Send Friend Request' ],
			'send_group_message'         => [ 'label' => 'Send Group Message' ],
			'send_private_message'       => [ 'label' => 'Send Private Message' ],
			'send_group_notification'    => [ 'label' => 'Send Group Notification' ],
			'update_extended_profile'    => [ 'label' => 'Update Extended Profile' ],
			'update_user_status'         => [ 'label' => 'Update User Status' ],
			'stop_following_user'        => [ 'label' => 'Stop Following User' ],
			'subscribe_to_forum'         => [ 'label' => 'Subscriber To Forum' ],
		];
	}

    private static function email(string $label = 'Email', string $key = 'email'): array {
        return [
            [
                'key'       => $key,
                'label'     => $label,
                'type'      => 'email',
                'required'  => true,
            ],
        ];
    }

    private static function content(string $label = 'Content', string $key = 'content'): array {
        return [
            [
                'key'       => $key,
                'label'     => $label,
                'type'      => 'textarea',
                'required'  => true,
            ],
        ];
    }

	private static function action(): array {
		return [
			[
				'key'       => 'action',
				'label'     => 'Activity Action',
				'type'      => 'text',
				'required'  => false,
			],
		];
	}

	private static function action_link(): array {
		return [
			[
				'key'       => 'action_link',
				'label'     => 'Activity Action Link',
				'type'      => 'url',
				'required'  => false,
			],
		];
	}

	private static function hide_sitewide(): array {
		return [
			[
				'key'       => 'hide_sitewide',
				'label'     => 'Hide Sitewide',
				'type'      => 'checkbox',
				'required'  => false,
			],
		];
	}

	public static function group_id(): array {
		return [
			[
				'key'       => 'group_id',
				'label'     => 'Select Group',
				'type'      => 'select',
				'dynamic' => [
					'integration' => 'buddypress',
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
				...self::email('Author Email', 'author_email'),
				...self::content('Activity Content', 'content'),
				...self::action(),
				...self::action_link(),
				...self::hide_sitewide(),
			],
			'create_group_post' => [
				...self::group_id(),
				...self::email('Author Email', 'author_email'),
				...self::content('Activity Content', 'content'),,
				...self::action(),
				...self::action_link(),
				...self::hide_sitewide(),
			],
			'create_user_activity_post' => [
				[
					'key'       => 'user_id',
					'label'     => 'User Activity ID',
					'type'      => 'number',
					'required'  => true,
					'placeholder' => 'Enter the user ID whose activity feed you want to post to.',
				],
				...self::email('Author Email', 'author_email'),
				...self::content('Activity Content', 'content'),,
				...self::action(),
				...self::action_link(),
				...self::hide_sitewide(),
			],
			'add_user_to_group' => [
				...self::email('User Email', 'user_email'),
				...self::group_id(),
			],
			'update_member_profile_type' => [
				...self::email('User Email', 'user_email')
				[
					'key'       => 'profile_type',
					'label'     => 'Profile Type',
					'type'      => 'select',
					'required'  => true,
					'options'   => [], // dynamic profile types load হবে
					'placeholder' => 'Select the profile type to assign to the user.',

					// dynamic loading (BuddyBoss / BuddyPress Member Types)
					'dynamic' => [
						'integration' => 'buddypress',
						'query'       => 'member_types_query',
						'select'      => [ 'value', 'label' ],
					],
				],
			],
			'create_group' => [
				[
					'key'       => 'group_name',
					'label'     => 'Group Name',
					'type'      => 'text',
					'required'  => true,
					'placeholder' => 'Enter the name of the group you want to create.',
				],
				[
					'key'       => 'group_status',
					'label'     => 'Group Privacy Status',
					'type'      => 'select',
					'required'  => true,
					'options'   => [
						[ 'value' => 'public',  'label' => 'Public' ],
						[ 'value' => 'private', 'label' => 'Private' ],
						[ 'value' => 'hidden',  'label' => 'Hidden' ],
					],
					'placeholder' => 'Select the privacy status of the group.',
				],
				self::email('Topic Creator Email', 'creator_email'),
				[
					'key'       => 'group_type',
					'label'     => 'Group Type',
					'type'      => 'select',
					'required'  => false,
					'options'   => [], // dynamic group types load হবে
					'placeholder' => 'Select the group type.',

					// dynamic loading (BuddyBoss Group Types)
					'dynamic' => [
						'integration' => 'buddypress',
						'query'       => 'group_types_query',
						'select'      => [ 'value', 'label' ],
					],
				],
			],
			'remove_friend_connection' => [
				...self::email('User Email', 'user_email'),
				[
					'key'       => 'friend_email',
					'label'     => 'Friend Email',
					'type'      => 'email',
					'required'  => true,
					'placeholder' => 'Enter the email address of the friend to remove.',
				],
			],
			'follow_user' => [
				...self::email('Follower Email', 'follower_email'),
				...self::email('Leader Email', 'leader_email'),
			],
			'get_forum_subscribers' => [
				[
					'key'       => 'forum_id',
					'label'     => 'Forum ID',
					'type'      => 'number',
					'required'  => true,
					'placeholder' => 'Enter the ID of the forum to get subscribers from.',
				],
			],
			'create_forum_topic_reply' => [
				[
					'key'       => 'forum_id',
					'label'     => 'Select Forum',
					'type'      => 'select',
					'required'  => true,
					'options'   => [], // dynamic forum list load হবে
					'placeholder' => 'Select the forum containing the topic.',

					// dynamic loading (bbPress forums)
					'dynamic' => [
						'integration' => 'bbpress',
						'query'       => 'forums_query',
						'select'      => [ 'value', 'label' ],
					],
				],
				[
					'key'       => 'topic_id',
					'label'     => 'Topic ID',
					'type'      => 'number',
					'required'  => true,
					'placeholder' => 'Enter the topic ID to reply to.',
				],
				[
					'key'       => 'reply_title',
					'label'     => 'Reply Title',
					'type'      => 'text',
					'required'  => true,
					'placeholder' => 'Enter the title of the reply.',
				],
				...self::content('Reply Content', 'reply_content'),
				...self::email('Author Email', 'author_email'),
			],
			'create_forum_topic' => [
				[
					'key'       => 'forum_id',
					'label'     => 'Select Forum',
					'type'      => 'select',
					'required'  => true,
					'options'   => [], // dynamic forum list load হবে
					'placeholder' => 'Select the forum where you want to post the topic.',

					// dynamic loading (bbPress forums)
					'dynamic' => [
						'integration' => 'bbpress',
						'query'       => 'forums_query',
						'select'      => [ 'value', 'label' ],
					],
				],
				[
					'key'       => 'topic_title',
					'label'     => 'Topic Title',
					'type'      => 'text',
					'required'  => true,
					'placeholder' => 'Enter the title of the topic.',
				],
				...self::content('Topic Content', 'topic_content'),
				...self::email('Topic Creator Email', 'creator_email'),
			],
			'remove_user_form_group' => [
				...self::email('User Email', 'user_email')
				...self::group_id(),
			],
			'send_friend_request' => [
				...self::email('Sender Email', 'sender_email'),
				...self::email('Receiver Email', 'receiver_email'),
			],
			'send_group_message' => [
				...self::group_id(),
				...self::email('Sender Email', 'sender_email'),
				[
					'key'       => 'message_subject',
					'label'     => 'Message Subject',
					'type'      => 'text',
					'required'  => true,
					'placeholder' => 'Enter the subject of the message.',
				],
				...self::content('Message Content', 'message_content'),
			],
			'send_private_message' => [
				...self::email('Sender Email', 'sender_email'),
				...sself::email('Receiver Email', 'receiver_email'),
				[
					'key'       => 'message_subject',
					'label'     => 'Message Subject',
					'type'      => 'text',
					'required'  => true,
					'placeholder' => 'Enter the subject of the message.',
				],
				...self::content('Message Content', 'message_content'),
			],
			'send_group_notification' => [
				...self::group_id(),
				...self::email('Sender Email', 'sender_email'),
				...self::content('Notification Content', 'notification_content'),
				[
					'key'       => 'notification_link',
					'label'     => 'Notification Link',
					'type'      => 'url',
					'required'  => false,
					'placeholder' => 'Enter the link of the notification (optional).',
				],
			],
			'update_extended_profile' => [
				...self::email('User Email', 'user_email'),
				[
					'key'       => 'profile_fields',
					'label'     => 'Extended Profile Field Map',
					'type'      => 'repeater', // multiple fields
					'required'  => true,
					'sub_fields' => [
						[
							'key'       => 'field_name',
							'label'     => 'Field',
							'type'      => 'text', // or select if you want to limit to existing fields
							'required'  => true,
							'placeholder' => 'Select or enter the extended profile field name.',
						],
						[
							'key'       => 'field_value',
							'label'     => 'Value',
							'type'      => 'text',
							'required'  => false,
							'placeholder' => 'Enter the value for this field.',
						],
					],
					'add_button_label' => '+ Add Extended Profile Field',
				],
			],
			'update_user_status' => [
				...self::email('User Email', 'user_email'),
				[
					'key'       => 'status',
					'label'     => 'Status',
					'type'      => 'select',
					'required'  => true,
					'options'   => [
						[ 'value' => 'suspend',   'label' => 'Suspend' ],
						[ 'value' => 'unsuspend', 'label' => 'Unsuspend' ],
					],
					'placeholder' => 'Select the status to set for the user.',
				],
			],
			'stop_following_user' => [
				...self::email('Follower Email', 'follower_email'),
				...self::email('Leader Email', 'leader_email'),
			],
			'subscribe_to_forum' => [
				...self::email('User Email', 'user_email'),
				[
					'key'       => 'forum_id',
					'label'     => 'Select Forum',
					'type'      => 'select',
					'required'  => true,
					'options'   => [], // dynamic forum list load হবে
					'placeholder' => 'Select the forum to subscribe the user to.',

					// dynamic loading (bbPress forums)
					'dynamic' => [
						'integration' => 'bbpress',
						'query'       => 'forums_query',
						'select'      => [ 'value', 'label' ],
					],
				],
			],
		];

		return $schemas[ $action ] ?? [];
	}

	public static function get_dynamic_queries(): array {
		return [
			'rank_type_query'        => [ self::class, 'query_rank_type' ],
			'rank_query'             => [ self::class, 'query_rank' ],
			'achievement_type_query' => [ self::class, 'query_achievement_type' ],
			'achievement_query'      => [ self::class, 'query_achievement' ],
		];
	}

	private static function get_value( $query, $key ) {

		if ( isset( $query['where'][ $key ] ) ) {
			return $query['where'][ $key ];
		}

		if ( isset( $query[ $key ] ) ) {
			return $query[ $key ];
		}

		if ( isset( $query['values'][ $key ] ) ) {
			return $query['values'][ $key ];
		}

		if ( isset( $query['data'][ $key ] ) ) {
			return $query['data'][ $key ];
		}

		return '';
	}

	public static function query_rank_type( $query ) {
		$all_rank_type = [
			[
				'value' => 'any',
				'label' => 'Any Rank Type'
			],
		];

		global $wpdb;

        $rank_types = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_name, post_title, post_type FROM {$wpdb->posts} where post_type like %s AND post_status = %s",
                ['rank_type', 'publish']
            )
        );

		foreach ( $rank_types as $rank_type ) {
			$all_rank_type[] = [
				'value' => $rank_type->post_name,
				'label' => $rank_type->post_title,
			];
		}

		return $all_rank_type;
	}

	public static function query_rank( $query ) {
		global $wpdb;

		$rank_type = self::get_value( $query, 'rank_type' );
		$rank_type = sanitize_text_field( $rank_type );

		$all_rank = [
			[	'value' => 'any', 
				'label' => 'Any Rank' 
			],
		];

		if ( empty( $rank_type ) || $rank_type === 'any' ) {

			$all_rank_types = $wpdb->get_results(
				"SELECT post_name FROM {$wpdb->posts} WHERE post_type LIKE 'rank_type' AND post_status = 'publish'"
			);

			foreach ( $all_rank_types as $rt ) {
				$ranks = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT post_name, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
						$rt->post_name
					)
				);
				foreach ( $ranks as $rank ) {
					$all_rank[] = [
						'value' => $rank->post_name,
						'label' => $rank->post_title,
					];
				}
			}

			return $all_rank;
		}

		$ranks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_name, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				$rank_type
			)
		);

		foreach ( $ranks as $rank ) {
			$all_rank[] = [
				'value' => $rank->post_name,
				'label' => $rank->post_title,
			];
		}

		return $all_rank;
	}

    public static function query_achievement_type( $query ) {
		$all_achievement_type = [
			[
				'value' => 'any',
				'label' => 'Any Achievement Type'
			],
		];

		global $wpdb;

        $achievement_types = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_name, post_title, post_type FROM {$wpdb->posts} WHERE post_type LIKE %s AND post_status = %s ORDER BY post_title ASC",
                ['achievement-type', 'publish']
            )
        );

		foreach ( $achievement_types as $achievement_type ) {
			$all_achievement_type[] = [
				'value' => $achievement_type->post_name,
				'label' => $achievement_type->post_title,
			];
		}

		return $all_achievement_type;
	}

    public static function query_achievement( $query ) {
		global $wpdb;

		$achievement_type = self::get_value( $query, 'achievement_type' );
		$achievement_type = sanitize_text_field( $achievement_type );

		$all_achievement = [
			[ 'value' => 'any', 'label' => 'Any Achievement' ],
		];

		if ( empty( $achievement_type ) || $achievement_type === 'any' ) {

			$all_types = $wpdb->get_results(
				"SELECT post_name FROM {$wpdb->posts} WHERE post_type LIKE 'achievement-type' AND post_status = 'publish'"
			);

			foreach ( $all_types as $type ) {
				$achievements = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
						$type->post_name
					)
				);

				foreach ( $achievements as $achievement ) {
					$all_achievement[] = [
						'value' => (string) $achievement->ID,
						'label' => $achievement->post_title,
					];
				}
			}

			return $all_achievement;
		}

		$achievements = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				$achievement_type
			)
		);

		foreach ( $achievements as $achievement ) {
			$all_achievement[] = [
				'value' => (string) $achievement->ID,
				'label' => $achievement->post_title,
			];
		}

		return $all_achievement;
	}
}
