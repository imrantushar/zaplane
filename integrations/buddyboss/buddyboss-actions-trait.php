<?php
namespace Zaplane\Integrations\Buddyboss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait BuddybossActionsTrait {

	protected static function action_create_activity_post( array $config, array $input ): array {
		$required = [ 'author_email', 'content' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		return self::handle_activity_post( $input );
	}

	protected static function action_create_group_post( array $config, array $input ): array {
		$required = [ 'author_email', 'content', 'group_id' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		$group_id = absint( $input['group_id'] );

		return self::handle_activity_post( $input, 'groups', $group_id );
	}

	protected static function action_create_user_activity_post( array $config, array $input ): array {
		$required = [ 'author_email', 'content', 'user_id' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		$user_activity_id = absint( $input['user_id'] );

		return self::handle_activity_post( $input, 'activity', null, $user_activity_id );
	}

	protected static function action_add_user_to_group( array $config, array $input ): array {
		$required = [ 'user_email', 'group_id' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		$user_id = email_exists( $input['user_email'] );
		if ( ! $user_id ) {
			return self::action_error( 'User not found with provided email.' );
		}

		if ( ! function_exists( 'groups_join_group' ) ) {
			return self::action_error( 'BuddyBoss Groups join function not found.' );
		}

		$group_ids    = is_array( $input['group_id'] ) ? array_map( 'absint', $input['group_id'] ) : [ absint( $input['group_id'] ) ];
		$added_groups = [];

		foreach ( $group_ids as $group_id ) {
			if ( groups_join_group( $group_id, $user_id ) ) {
				$added_groups[] = $group_id;
			}
		}

		return self::action_success( [
			'user'            => self::resolve_user_payload( $user_id ),
			'added_group_ids' => $added_groups,
		] );
	}

	protected static function action_update_member_profile_type( array $config, array $input ): array {
		$required = [ 'user_email', 'profile_type' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		$user_id = email_exists( $input['user_email'] );
		if ( ! $user_id ) {
			return self::action_error( 'User not found with provided email.' );
		}

		if ( ! function_exists( 'bp_set_member_type' ) ) {
			return self::action_error( 'bp_set_member_type function not found.' );
		}

		if ( ! bp_set_member_type( $user_id, $input['profile_type'] ) ) {
			return self::action_error( 'Failed to set member type.' );
		}

		return self::action_success( self::object_to_array( get_userdata( $user_id ) ) );
	}

	protected static function action_create_group( array $config, array $input ): array {
		$required = [ 'group_name', 'group_status', 'creator_email' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		if (
			! function_exists( 'groups_create_group' )
			|| ! function_exists( 'groups_get_group' )
			|| ! function_exists( 'bp_groups_set_group_type' )
		) {
			return self::action_error( 'BuddyBoss Groups functions do not exist.' );
		}

		$creator = get_user_by( 'email', $input['creator_email'] );
		if ( ! $creator ) {
			return self::action_error( 'Group creator not found.' );
		}

		$group_id = groups_create_group( [
			'creator_id' => $creator->ID,
			'name'       => $input['group_name'],
			'status'     => $input['group_status'],
		] );

		if ( is_wp_error( $group_id ) || ! $group_id ) {
			return self::action_error( 'There was an error creating the group.' );
		}

		if ( ! empty( $input['group_type'] ) ) {
			bp_groups_set_group_type( $group_id, $input['group_type'] );
		}

		return self::action_success( self::object_to_array( groups_get_group( $group_id ) ) );
	}

	protected static function action_remove_friend_connection( array $config, array $input ): array {
		$required = [ 'user_email', 'friend_email' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		$user_id   = email_exists( $input['user_email'] );
		$friend_id = email_exists( $input['friend_email'] );

		if ( ! $user_id || ! $friend_id ) {
			return self::action_error( 'One or both users not found.' );
		}

		if ( ! function_exists( 'friends_remove_friend' ) ) {
			return self::action_error( 'BuddyBoss connection module is not active.' );
		}

		if ( friends_remove_friend( $user_id, $friend_id ) ) {
			return self::action_success( [ 'message' => 'Friendship ended successfully.' ] );
		}

		return self::action_error( 'Failed to end friendship.' );
	}

	protected static function action_follow_user( array $config, array $input ): array {
		$required = [ 'follower_email', 'leader_email' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		$follower = get_user_by( 'email', $input['follower_email'] );
		$leader   = get_user_by( 'email', $input['leader_email'] );

		if ( ! $follower || ! $leader ) {
			return self::action_error( 'One or both users not found.' );
		}

		if ( $follower->ID === $leader->ID ) {
			return self::action_error( 'A user cannot follow themselves.' );
		}

		$follow_fn = self::resolve_follow_function( 'start' );
		if ( ! $follow_fn ) {
			return self::action_error( 'BuddyBoss Follow functions not found.' );
		}

		$result = $follow_fn( [
			'follower_id' => $follower->ID,
			'leader_id' => $leader->ID
		] );

		if ( ! $result ) {
			return self::action_error( 'User is already following.' );
		}

		return self::action_success( [
			'follower' => self::object_to_array( $follower ),
			'leader'   => self::object_to_array( $leader ),
		] );
	}

	protected static function action_get_forum_subscribers( array $config, array $input ): array {
		$required = [ 'forum_id' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		if ( ! function_exists( 'bbp_get_forum_subscribers' ) ) {
			return self::action_error( 'BuddyBoss Forum functions do not exist.' );
		}

		$subscriber_ids = bbp_get_forum_subscribers( absint( $input['forum_id'] ) );
		$subscribers    = array_map( fn( $id ) => self::object_to_array( get_userdata( $id ) ), $subscriber_ids );

		return self::action_success( $subscribers );
	}

	protected static function action_create_forum_topic_reply( array $config, array $input ): array {
		$required = [ 'forum_id', 'topic_id', 'reply_title', 'reply_content', 'author_email' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		if ( ! function_exists( 'bbp_insert_reply' ) ) {
			return self::action_error( 'BuddyBoss Forum functions not found.' );
		}

		$user = get_user_by( 'email', $input['author_email'] );
		if ( ! $user ) {
			return self::action_error( 'Reply author user does not exist.' );
		}

		$forum_id = absint( $input['forum_id'] );
		$topic_id = absint( $input['topic_id'] );

		$reply_id = bbp_insert_reply(
			[
				'post_parent'  => $topic_id,
				'post_content' => $input['reply_content'],
				'post_status'  => bbp_get_public_status_id(),
				'post_author'  => $user->ID,
			],
			[
				'topic_id' => $topic_id,
				'forum_id' => $forum_id,
			]
		);

		if ( ! $reply_id ) {
			return self::action_error( 'Failed to create reply.' );
		}

		$response = [
			'topic_id'    => $topic_id,
			'forum_id'    => $forum_id,
			'reply_id'    => $reply_id,
			'forum_title' => get_the_title( $forum_id ),
			'topic_title' => get_the_title( $topic_id ),
			'creator'     => self::object_to_array( $user ),
		];

		if ( function_exists( 'bbp_get_topic' ) ) {
			$response['topic'] = bbp_get_topic( $topic_id, ARRAY_A );
		}

		if ( function_exists( 'bbp_get_reply' ) ) {
			$response['reply'] = bbp_get_reply( $reply_id, ARRAY_A );
		}

		return self::action_success( $response );
	}

	protected static function action_create_forum_topic( array $config, array $input ): array {
		$required = [ 'forum_id', 'topic_title', 'topic_content', 'creator_email' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		if ( ! function_exists( 'bbp_insert_topic' ) ) {
			return self::action_error( 'BuddyBoss Forum functions not found.' );
		}

		$user = get_user_by( 'email', $input['creator_email'] );
		if ( ! $user ) {
			return self::action_error( 'Creator user does not exist.' );
		}

		$forum_id = absint( $input['forum_id'] );

		$topic_id = bbp_insert_topic(
			[
				'post_parent'  => $forum_id,
				'post_title'   => $input['topic_title'],
				'post_content' => $input['topic_content'],
				'post_status'  => bbp_get_public_status_id(),
				'post_author'  => $user->ID,
			],
			[ 'forum_id' => $forum_id ]
		);

		if ( ! $topic_id ) {
			return self::action_error( 'Failed to create topic.' );
		}

		$response = [
			'topic_id'    => $topic_id,
			'forum_id'    => $forum_id,
			'forum_title' => get_the_title( $forum_id ),
			'creator'     => self::object_to_array( $user ),
		];

		if ( function_exists( 'bbp_get_topic' ) ) {
			$response['topic'] = bbp_get_topic( $topic_id, ARRAY_A );
		}

		return self::action_success( $response );
	}

	protected static function action_remove_user_from_group( array $config, array $input ): array {
		$required = [ 'user_email', 'group_id' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		$user_id = email_exists( $input['user_email'] );
		if ( ! $user_id ) {
			return self::action_error( 'User not found.' );
		}

		if ( ! function_exists( 'groups_leave_group' ) ) {
			return self::action_error( 'BuddyBoss Groups functions not found.' );
		}

		$group_ids = is_array( $input['group_id'] ) ? array_map( 'absint', $input['group_id'] ) : [ absint( $input['group_id'] ) ];

		foreach ( $group_ids as $group_id ) {
			if ( function_exists( 'groups_is_user_member' ) && groups_is_user_member( $user_id, $group_id ) ) {
				groups_leave_group( $group_id, $user_id );
			}
		}

		return self::action_success( [ 'message' => 'User removed from group(s) successfully.' ] );
	}

	protected static function action_send_friend_request( array $config, array $input ): array {
		$required = [ 'sender_email', 'receiver_email' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		$sender   = get_user_by( 'email', $input['sender_email'] );
		$receiver = get_user_by( 'email', $input['receiver_email'] );

		if ( ! $sender || ! $receiver ) {
			return self::action_error( 'One or both users not found.' );
		}

		if ( ! function_exists( 'friends_add_friend' ) ) {
			return self::action_error( 'BuddyBoss connection module is not active.' );
		}

		$result = friends_add_friend( $sender->ID, $receiver->ID );

		if ( $result === false ) {
			return self::action_error( 'Unable to send friendship request to selected user.' );
		}

		return self::action_success( [
			'sender'   => self::object_to_array( $sender ),
			'receiver' => self::object_to_array( $receiver ),
		] );
	}

	protected static function action_send_group_message( array $config, array $input ): array {
		$required = [ 'group_id', 'sender_email', 'message_subject', 'message_content' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		$group_id  = absint( $input['group_id'] );
		$sender_id = email_exists( $input['sender_email'] );

		if ( ! $sender_id ) {
			return self::action_error( 'Sender user does not exist.' );
		}

		if ( ! function_exists( 'groups_get_group_members' ) ) {
			return self::action_error( 'BuddyBoss Groups functions not found.' );
		}

		$members       = groups_get_group_members( [ 'group_id' => $group_id ] );
		$recipient_ids = [];

		if ( ! empty( $members['members'] ) ) {
			foreach ( $members['members'] as $member ) {
				if ( $member->ID !== $sender_id ) {
					$recipient_ids[] = $member->ID;
				}
			}
		}

		if ( empty( $recipient_ids ) ) {
			return self::action_error( 'No members found in the group.' );
		}

		if ( ! function_exists( 'messages_new_message' ) ) {
			return self::action_error( 'BuddyBoss message module is not active.' );
		}

		$sent = messages_new_message( [
			'sender_id'  => $sender_id,
			'recipients' => $recipient_ids,
			'subject'    => $input['message_subject'],
			'content'    => $input['message_content'],
			'error_type' => 'wp_error',
		] );

		if ( is_wp_error( $sent ) ) {
			return self::action_error( $sent->get_error_message() );
		}

		if ( ! $sent ) {
			return self::action_error( 'Failed to send message.' );
		}

		return self::action_success( [
			'group_id'         => $group_id,
			'total_recipients' => count( $recipient_ids ),
			'recipient_ids'    => $recipient_ids,
		] );
	}

	protected static function action_send_private_message( array $config, array $input ): array {
		$required = [ 'sender_email', 'receiver_email', 'message_subject', 'message_content' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		$sender_id = email_exists( $input['sender_email'] );
		if ( ! $sender_id ) {
			return self::action_error( 'Sender not found with provided email.' );
		}

		$receiver_emails = array_map( 'trim', explode( ',', $input['receiver_email'] ) );
		$receiver_ids    = [];

		foreach ( $receiver_emails as $email ) {
			if ( ! is_email( $email ) ) {
				return self::action_error( 'Invalid receiver email.' );
			}

			$receiver_id = email_exists( $email );
			if ( ! $receiver_id ) {
				return self::action_error( 'User not found with provided email.' );
			}

			$receiver_ids[] = $receiver_id;
		}

		if ( ! function_exists( 'messages_new_message' ) ) {
			return self::action_error( 'BuddyBoss message module is not active.' );
		}

		$sent = messages_new_message( [
			'sender_id'  => $sender_id,
			'recipients' => $receiver_ids,
			'subject'    => $input['message_subject'],
			'content'    => $input['message_content'],
			'error_type' => 'wp_error',
		] );

		if ( is_wp_error( $sent ) ) {
			return self::action_error( $sent->get_error_message() );
		}

		if ( ! $sent ) {
			return self::action_error( 'Failed to send message.' );
		}

		return self::action_success( [ 'message' => 'Message sent successfully.' ] );
	}

	protected static function action_send_group_notification( array $config, array $input ): array {
		$required = [ 'group_id', 'sender_email', 'notification_content' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		$group_id  = absint( $input['group_id'] );
		$sender_id = email_exists( $input['sender_email'] );

		if ( ! $sender_id ) {
			return self::action_error( 'Sender user does not exist.' );
		}

		if ( ! function_exists( 'groups_get_group_members' ) || ! function_exists( 'bp_notifications_add_notification' ) ) {
			return self::action_error( 'BuddyBoss core functions not found.' );
		}

		$members = groups_get_group_members( [ 'group_id' => $group_id ] );

		if ( ! empty( $members['members'] ) ) {
			foreach ( $members['members'] as $member ) {
				$notification_id = bp_notifications_add_notification( [
					'user_id'           => $member->ID,
					'item_id'           => $group_id,
					'secondary_item_id' => $sender_id,
					'component_name'    => 'zaplane',
					'component_action'  => 'zaplane_bb_notification',
					'date_notified'     => bp_core_current_time(),
					'is_new'            => 1,
					'allow_duplicate'   => true,
				] );

				if ( $notification_id ) {
					bp_notifications_update_meta( $notification_id, 'zaplane_notification_content', $input['notification_content'] );
					bp_notifications_update_meta( $notification_id, 'zaplane_notification_link', $input['notification_link'] ?? '' );
				}
			}
		}

		return self::action_success( $members );
	}

	protected static function action_update_extended_profile( array $config, array $input ): array {
		$required = [
			'user_email',
			'first_name',
			'last_name',
			'nick_name',
			'paragraph',
			'number',
			'checkbox',
			'drop_down',
			'radio_buttons',
		];

		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		$user_id = email_exists( $input['user_email'] );
		if ( ! $user_id ) {
			return self::action_error( 'User not found with provided email.' );
		}

		if ( ! function_exists( 'xprofile_set_field_data' ) ) {
			return self::action_error( 'xprofile_set_field_data function not found.' );
		}

		$field_map = [
			'first_name'    => 'First Name',
			'last_name'     => 'Last Name',
			'nick_name'     => 'Nickname',
			'paragraph'     => 'Paragraph',
			'number'        => 'Number',
			'checkbox'      => 'Checkbox',
			'drop_down'     => 'Drop Down',
			'radio_buttons' => 'Radio Buttons',
		];

		$updated_fields = [];

		foreach ( $field_map as $input_key => $field_name ) {
			if ( ! isset( $input[ $input_key ] ) ) {
				continue;
			}

			xprofile_set_field_data( $field_name, $user_id, $input[ $input_key ] );

			if ( function_exists( 'xprofile_get_field_data' ) ) {
				$updated_fields[ $field_name ] = xprofile_get_field_data( $field_name, $user_id );
			}
		}

		return self::action_success( [
			'user_id'        => $user_id,
			'updated_fields' => $updated_fields,
		] );
	}

	protected static function action_update_user_status( array $config, array $input ): array {
		$required = [ 'user_email', 'status' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		if ( ! function_exists( 'bp_is_active' ) ) {
			return self::action_error( 'BuddyBoss functions not found.' );
		}

		$user_id = email_exists( $input['user_email'] );
		if ( ! $user_id ) {
			return self::action_error( 'User not found.' );
		}

		if ( ! bp_is_active( 'moderation' ) ) {
			return self::action_error( 'Please activate the Moderation component.' );
		}

		if ( ! class_exists( 'BP_Suspend_Member' ) ) {
			$class_file = buddypress()->plugin_dir
				. 'src/bp-moderation/classes/suspend/class-bp-suspend-member.php';

			if ( file_exists( $class_file ) ) {
				require_once $class_file;
			}
		}

		if ( ! class_exists( 'BP_Suspend_Member' ) ) {
			return self::action_error( 'BP_Suspend_Member class not found. BuddyBoss version may be incompatible.' );
		}

		if ( $input['status'] === 'suspend' ) {
			BP_Suspend_Member::suspend_user( $user_id );

		} elseif ( $input['status'] === 'unsuspend' ) {
			if (
				function_exists( 'bp_moderation_is_user_suspended' )
				&& ! bp_moderation_is_user_suspended( $user_id )
			) {
				return self::action_error( 'User is not currently suspended.' );
			}

			BP_Suspend_Member::unsuspend_user( $user_id );

		} else {
			return self::action_error( 'Invalid status. Use "suspend" or "unsuspend".' );
		}

		return self::action_success( self::object_to_array( get_userdata( $user_id ) ) );
	}

	protected static function action_stop_following_user( array $config, array $input ): array {
		$required = [ 'follower_email', 'leader_email' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		$follower = get_user_by( 'email', $input['follower_email'] );
		$leader   = get_user_by( 'email', $input['leader_email'] );

		if ( ! $follower || ! $leader ) {
			return self::action_error( 'One or both users not found.' );
		}

		$stop_fn = self::resolve_follow_function( 'stop' );
		if ( ! $stop_fn ) {
			return self::action_error( 'BuddyBoss Follow functions not available.' );
		}

		$result = $stop_fn( [
			'follower_id' => $follower->ID,
			'leader_id' => $leader->ID
		] );

		return $result
			? self::action_success( [ 'message' => 'Stopped following successfully.' ] )
			: self::action_error( 'Failed to stop following.' );
	}

	protected static function action_subscribe_to_forum( array $config, array $input ): array {
		$required = [ 'user_email', 'forum_id' ];
		if ( $error = self::require_fields( $input, $required ) ) {
			return $error;
		}

		$user_id = email_exists( $input['user_email'] );
		if ( ! $user_id ) {
			return self::action_error( 'User not found with provided email.' );
		}

		if ( ! function_exists( 'bbp_add_user_forum_subscription' ) ) {
			return self::action_error( 'Required BuddyBoss functions are not available.' );
		}

		$result = bbp_add_user_forum_subscription( $user_id, absint( $input['forum_id'] ) );

		return $result
			? self::action_success( [ 'message' => 'User subscribed to forum successfully.' ] )
			: self::action_error( 'Failed to subscribe user to forum.' );
	}
}
