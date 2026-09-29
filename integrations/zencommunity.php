<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Zencommunity\QueryTrait;
use ZenCommunity\Database\Models\{ Group, Feed, Profile, Event, Notification };
use ZenCommunity\Database\Utils\QueryBuilder;
use ZenCommunity\Classes\RoleManager;

/**
 * ZenCommunity Core + Pro integration. Core actions use installed model APIs;
 * optional Pro chat and ticketing capabilities are registered only when the
 * corresponding Pro model classes are available.
 */
class Zencommunity extends IntegrationBase {
	use QueryTrait;

	public static function get_slug(): string { return 'zencommunity'; }
	public static function get_name(): string { return 'ZenCommunity'; }

	private static function available(): bool {
		return class_exists( Group::class ) && class_exists( Feed::class ) && class_exists( Profile::class );
	}

	public static function get_triggers(): array {
		$triggers = [
			'user_registers' => [ 'label' => 'User Registers in Community', 'hook' => 'zencommunity/profile/created' ],
			'profile_updated' => [ 'label' => 'User Updates Profile', 'hook' => 'zencommunity/profile/updated' ],
			'joins_space' => [ 'label' => 'User Joins Space', 'hook' => 'zencommunity/group/user_added' ],
			'leaves_space' => [ 'label' => 'User Leaves Space', 'hook' => 'zencommunity/group/remove_member' ],
			'requests_space' => [ 'label' => 'User Requests to Join Space', 'hook' => 'zencommunity/group/user_added' ],
			'removed_space' => [ 'label' => 'User Removed from Space', 'hook' => 'zencommunity/group/remove_member' ],
			'space_role_changed' => [ 'label' => 'User Role Changed in Space', 'hook' => 'zencommunity/group/user_role_changed' ],
			'space_created' => [ 'label' => 'Space Created', 'hook' => 'zencommunity/group/created' ],
			'space_updated' => [ 'label' => 'Space Updated', 'hook' => 'zencommunity/group/updated' ],
			'space_deleted' => [ 'label' => 'Space Deleted', 'hook' => 'zencommunity/group/deleted' ],
			'post_created' => [ 'label' => 'Post Created', 'hook' => 'zencommunity/feed/created' ],
			'post_in_space' => [ 'label' => 'Post Created in Specific Space', 'hook' => 'zencommunity/feed/created' ],
			'post_updated' => [ 'label' => 'Post Updated', 'hook' => 'zencommunity/feed/updated' ],
			'post_deleted' => [ 'label' => 'Post Deleted', 'hook' => 'zencommunity/feed/deleted' ],
			'post_mentioned' => [ 'label' => 'User Mentioned in Post', 'hook' => 'zencommunity/feed/mentioned' ],
			'post_reacted' => [ 'label' => 'Post Reaction Added', 'hook' => 'zencommunity/feed/react' ],
			'comment_added' => [ 'label' => 'Comment Added', 'hook' => 'zencommunity/feed/comment_created' ],
			'comment_reply' => [ 'label' => 'Comment Reply Added', 'hook' => 'zencommunity/feed/comment_created' ],
			'comment_updated' => [ 'label' => 'Comment Updated', 'hook' => 'zencommunity/feed/comment_updated' ],
			'comment_deleted' => [ 'label' => 'Comment Deleted', 'hook' => 'zencommunity/feed/comment_deleted' ],
			'comment_reacted' => [ 'label' => 'Comment Reaction Added', 'hook' => 'zencommunity/feed/react' ],
			'poll_created' => [ 'label' => 'Poll Created', 'hook' => 'zencommunity/feed/created' ],
			'event_created' => [ 'label' => 'Event Created', 'hook' => 'zencommunity/event/created' ],
		];
		if ( class_exists( \ZenCommunityPro\Addons\TicketingSystem\Database\Models\Ticket::class ) ) {
			$triggers += [
				'ticket_created' => [ 'label' => 'Support Ticket Created', 'hook' => 'zencommunity/ticket/created' ],
				'ticket_status' => [ 'label' => 'Ticket Status Changed', 'hook' => 'zencommunity/ticket/updated' ],
				'ticket_priority' => [ 'label' => 'Ticket Priority Changed', 'hook' => 'zencommunity/ticket/updated' ],
				'ticket_assigned' => [ 'label' => 'Ticket Assigned to Agent', 'hook' => 'zencommunity/notification/created' ],
				'ticket_closed' => [ 'label' => 'Ticket Closed', 'hook' => 'zencommunity_pro/ticket/closed' ],
				'ticket_reopened' => [ 'label' => 'Ticket Reopened', 'hook' => 'zencommunity_pro/ticket/reopened' ],
				'agent_reply' => [ 'label' => 'Agent Replies to Ticket', 'hook' => 'zencommunity/ticket/conversation_created' ],
				'customer_reply' => [ 'label' => 'Customer Replies to Ticket', 'hook' => 'zencommunity/ticket/conversation_created' ],
			];
		}
		if ( class_exists( \ZenCommunityPro\Addons\Messaging\Database\Models\PrivateMessage::class ) ) {
			$triggers['private_conversation'] = [ 'label' => 'Private Conversation Started', 'hook' => 'zencommunity_pro/private/message/created' ];
		}
		return $triggers;
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'post_in_space' === $trigger ) {
			return [ self::field( 'group_id', 'Space', 'select', true, 'spaces' ) ];
		}
		return [];
	}

	private static function field( string $key, string $label, string $type = 'text', bool $required = false, string $query = '' ): array {
		$field = [ 'key' => $key, 'label' => $label, 'type' => $type, 'required' => $required ];
		if ( $query ) {
			$field['dynamic'] = [ 'integration' => 'zencommunity', 'query' => $query, 'select' => [ 'value', 'label' ] ];
		}
		return $field;
	}

	public static function get_dynamic_queries(): array {
		return [
			'spaces' => [ self::class, 'query_spaces' ],
			'users' => [ self::class, 'query_users' ],
			'roles' => [ self::class, 'query_roles' ],
			'group_roles' => [ self::class, 'query_group_roles' ],
			'events' => [ self::class, 'query_events' ],
			'feeds' => [ self::class, 'query_feeds' ],
			'ticket_types' => [ self::class, 'query_ticket_types' ],
			'ticket_products' => [ self::class, 'query_ticket_products' ],
			'ticket_priorities' => [ self::class, 'query_ticket_priorities' ],

		];
	}

	private static function payload( array $data ): array {
		$user_id = (int) ( $data['user_id'] ?? 0 );
		if ( $user_id && function_exists( 'get_userdata' ) ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$data['user_email'] = $user->user_email;
				$data['display_name'] = $user->display_name;
			}
		}
		return $data;
	}

	public static function get_trigger_sample_output( string $event ): array {
		return self::payload( [
			'user_id' => 2, 'group_id' => 3, 'feed_id' => 4, 'comment_id' => 5,
			'event_id' => 6, 'status' => 'active', 'role' => 'member',
			'content' => 'Example community post', 'event' => $event,
		] );
	}

	public static function resolve_trigger( array $node, array $args ) {
		if ( ! self::available() ) { return false; }
		$event = $node['event'] ?? $node['data']['event'] ?? '';
		$config = $node['data']['config'] ?? $node['config'] ?? [];
		$pro_ticket = \ZenCommunityPro\Addons\TicketingSystem\Database\Models\Ticket::class;
		if ( 'ticket_assigned' === $event ) {
			$notification = is_array( $args[0] ?? null ) ? $args[0] : [];
			$ndata = is_array( $notification['data'] ?? null ) ? $notification['data'] : [];
			if ( 'ticket_assigned' !== ( $ndata['event_type'] ?? '' ) ) { return false; }
			$ticket_id = absint( $ndata['object_id'] ?? 0 );
			$to = is_array( $notification['to_user_ids'] ?? null ) ? $notification['to_user_ids'] : [];
			$agent_id = absint( $to[0] ?? 0 );
			if ( ! $ticket_id || ! $agent_id ) { return false; }
			return self::payload( [ 'ticket_id' => $ticket_id, 'user_id' => $agent_id, 'agent_id' => $agent_id,
				'notification_id' => absint( $notification['notification_id'] ?? 0 ) ] );
		}
		if ( 0 === strpos( $event, 'ticket_' ) || in_array( $event, [ 'agent_reply', 'customer_reply' ], true ) ) {
			if ( ! class_exists( $pro_ticket ) || empty( $args[0] ) ) { return false; }
			$ticket_id = absint( $args[0] );
			try {
				$ticket = $pro_ticket::by_id( $ticket_id );
				if ( ! is_array( $ticket ) ) { return false; }
				$old = is_array( $args[1] ?? null ) ? $args[1] : [];
				if ( 'ticket_status' === $event && (string) ( $old['status'] ?? '' ) === (string) ( $ticket['status'] ?? '' ) ) { return false; }
				if ( 'ticket_priority' === $event && (int) ( $old['priority_id'] ?? 0 ) === (int) ( $ticket['priority_id'] ?? 0 ) ) { return false; }
				if ( in_array( $event, [ 'agent_reply', 'customer_reply' ], true ) ) {
					$user_id = absint( $args[1] ?? 0 );
					$conversation = (array) ( $args[3] ?? [] );
					if ( ! $user_id || ! empty( $conversation['is_note'] ) ) { return false; }
					$is_customer = $user_id === absint( $ticket['user_id'] ?? 0 );
					if ( ( 'customer_reply' === $event ) !== $is_customer ) { return false; }
					return self::payload( [ 'ticket_id' => $ticket_id, 'user_id' => $user_id,
						'conversation_id' => absint( $args[2] ?? 0 ), 'conversation' => $conversation, 'ticket' => $ticket ] );
				}
				return self::payload( [ 'ticket_id' => $ticket_id, 'user_id' => absint( $ticket['user_id'] ?? 0 ),
					'status' => $ticket['status'] ?? '', 'priority_id' => absint( $ticket['priority_id'] ?? 0 ),
					'previous' => $old, 'ticket' => $ticket ] );
			} catch ( \Throwable $e ) { return false; }
		}
		if ( 'private_conversation' === $event ) {
			if ( ! class_exists( \ZenCommunityPro\Addons\Messaging\Database\Models\PrivateMessage::class ) ) { return false; }
			$message = is_array( $args[1] ?? null ) ? $args[1] : [];
			$sender = absint( $message['sender_id'] ?? 0 );
			$receiver = absint( $message['receiver_id'] ?? 0 );
			$message_id = absint( $args[0] ?? 0 );
			if ( ! $sender || ! $receiver || ! $message_id ) { return false; }
			global $wpdb;
			$table = $wpdb->prefix . 'zenc_messages';
			$first = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT MIN(id) FROM {$table} WHERE group_id IS NULL AND ((sender_id = %d AND receiver_id = %d) OR (sender_id = %d AND receiver_id = %d))",
				$sender, $receiver, $receiver, $sender
			) );
			return $first === $message_id ? self::payload( [ 'message_id' => $message_id,
				'user_id' => $sender, 'receiver_id' => $receiver, 'message' => $message ] ) : false;
		}
		$id = absint( $args[0] ?? 0 );
		$data = is_array( $args[1] ?? null ) ? $args[1] : [];
		switch ( $event ) {
			case 'user_registers':
				// Profile::create passes its record ID first; the WP user ID is in the payload.
				$user_id = absint( $data['user_id'] ?? 0 );
				if ( ! $id || ! $user_id || ! get_userdata( $user_id ) ) { return false; }
				return self::payload( [ 'profile_id' => $id, 'user_id' => $user_id, 'profile' => $data ] );
			case 'profile_updated':
				if ( ! $id ) { return false; }
				if ( isset( $data['status'] ) && count( $data ) === 1 ) { return false; }
				return self::payload( [ 'user_id' => $id, 'profile' => $data, 'previous' => $args[2] ?? [] ] );
			case 'joins_space':
			case 'requests_space':
				$member = $data;
				$status = $member['status'] ?? '';
				if ( ! $id || ( 'joins_space' === $event && 'active' !== $status )
					|| ( 'requests_space' === $event && 'pending' !== $status ) ) { return false; }
				return self::payload( [ 'group_id' => $id, 'user_id' => absint( $member['user_id'] ?? 0 ), 'role' => $member['role'] ?? '', 'status' => $status ] );
			case 'leaves_space':
			case 'removed_space':
				$user_id = absint( $args[1] ?? 0 );
				$actor = get_current_user_id();
				if ( ! $id || ! $user_id || ! $actor || ( 'leaves_space' === $event ) !== ( $user_id === $actor ) ) { return false; }
				return self::payload( [ 'group_id' => $id, 'user_id' => $user_id, 'actor_user_id' => $actor ] );
			case 'space_role_changed':
				if ( ! $id || empty( $args[1] ) ) { return false; }
				return self::payload( [ 'group_id' => $id, 'user_id' => absint( $args[1] ), 'role' => (string) ( $args[2] ?? '' ) ] );
			case 'space_created':
			case 'space_updated':
			case 'space_deleted':
				if ( ! $id ) { return false; }
				return [ 'group_id' => $id, 'space' => 'space_deleted' === $event ? [] : ( Group::by_id( $id ) ?? [] ), 'previous' => $data ];
			case 'event_created':
				if ( ! $id ) { return false; }
				return [ 'event_id' => $id, 'group_id' => absint( $args[1] ?? 0 ), 'event' => $args[2] ?? [] ];
			case 'post_created':
			case 'post_in_space':
			case 'post_updated':
			case 'post_deleted':
			case 'poll_created':
				if ( ! $id ) { return false; }
				$feed = 'post_deleted' === $event ? $data : ( Feed::by_id( $id ) ?? [] );
				if ( ! is_array( $feed ) ) { return false; }
				if ( 'poll_created' === $event && ( $feed['type'] ?? '' ) !== 'poll' ) { return false; }
				if ( in_array( $event, [ 'post_created', 'post_in_space', 'post_updated', 'post_deleted' ], true )
					&& ( $feed['type'] ?? 'post' ) !== 'post' ) { return false; }
				$group_id = absint( $feed['group_id'] ?? 0 );
				if ( 'post_in_space' === $event && ( ! $group_id || $group_id !== absint( $config['group_id'] ?? 0 ) ) ) { return false; }
				return self::payload( [ 'feed_id' => $id, 'group_id' => $group_id, 'user_id' => absint( $feed['user_id'] ?? 0 ), 'post' => $feed ] );
			case 'post_mentioned':
				$mentioned = $args[1] ?? [];
				if ( ! $id || ! is_array( $mentioned ) || ! $mentioned ) { return false; }
				return [ 'feed_id' => $id, 'mentioned_users' => $mentioned, 'content' => $args[2] ?? '' ];
			case 'post_reacted':
			case 'comment_reacted':
				$comment_id = absint( $args[4] ?? 0 );
				$reaction_id = absint( $args[1] ?? 0 );
				if ( ! $reaction_id || ( 'comment_reacted' === $event ) !== ( $comment_id > 0 ) ) { return false; }
				return self::payload( [ 'reaction_type' => $args[0] ?? '', 'reaction_id' => absint( $args[1] ?? 0 ),
					'feed_id' => absint( $args[2] ?? 0 ), 'user_id' => absint( $args[3] ?? 0 ), 'comment_id' => $comment_id ] );
			case 'comment_added':
			case 'comment_reply':
				$parent_id = absint( $args[3] ?? 0 );
				if ( ! $id || ( 'comment_reply' === $event ) !== ( $parent_id > 0 ) ) { return false; }
				return self::payload( [ 'comment_id' => $id, 'feed_id' => absint( $args[1] ?? 0 ),
					'user_id' => absint( $args[2] ?? 0 ), 'parent_id' => $parent_id, 'comment' => $args[5] ?? [] ] );
			case 'comment_updated':
				// ZenCommunity calls edit_comment() internally while first creating a comment.
				foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 ) as $frame ) {
					if ( ( $frame['function'] ?? '' ) === 'comment' && ( $frame['class'] ?? '' ) === Feed::class ) { return false; }
				}
				if ( ! $id ) { return false; }
				return self::payload( [ 'feed_id' => $id, 'user_id' => absint( $args[1] ?? 0 ),
					'comment_id' => absint( $args[2] ?? 0 ) ] );
			case 'comment_deleted':
				if ( ! $id ) { return false; }
				return self::payload( [ 'comment_id' => $id, 'feed_id' => absint( $args[1] ?? 0 ),
					'user_id' => absint( $args[2] ?? 0 ), 'deleted_reply_ids' => $args[3] ?? [] ] );
		}
		return false;
	}


	public static function get_actions(): array {
		$labels = [
			'add_user_space' => 'Add User to Space',
			'remove_user_space' => 'Remove User from Space',
			'approve_space_request' => 'Approve Space Join Request',
			'reject_space_request' => 'Reject Space Join Request',
			'change_space_role' => 'Change User Role in Space',
			'block_space_user' => 'Block User from Space',
			'unblock_space_user' => 'Unblock User from Space',
			'approve_user' => 'Approve Community User',
			'reject_user' => 'Reject Community User',
			'update_profile' => 'Update Community Profile',
			'change_community_role' => 'Change Community Role',
			'create_post' => 'Create Post in Space',
			'update_post' => 'Update Post',
			'delete_post' => 'Delete Post',
			'add_comment' => 'Add Comment to Post',
			'add_reply' => 'Add Reply to Comment',
			'delete_comment' => 'Delete Comment',
			'add_reaction' => 'Add Reaction to Post',
			'send_notification' => 'Send Community Notification',
			'create_poll' => 'Create Poll',
			'create_event' => 'Create Event',
			'update_event' => 'Update Event',
			'set_rsvp' => 'Set User RSVP Status',
			'cancel_rsvp' => 'Cancel User RSVP',
		];
		if ( class_exists( \ZenCommunityPro\Addons\Messaging\Database\Models\PrivateMessage::class ) ) {
			$labels['send_private_message'] = 'Send Private Message to User';
			$labels['send_group_message'] = 'Send Message to Group Chat';
		}
		if ( class_exists( \ZenCommunityPro\Addons\TicketingSystem\Database\Models\Ticket::class ) ) {
			$labels += [
				'create_ticket' => 'Create Support Ticket',
				'assign_ticket' => 'Assign Ticket to Agent',
				'change_ticket_status' => 'Change Ticket Status',
				'change_ticket_priority' => 'Change Ticket Priority',
				'reply_ticket' => 'Add Reply to Ticket',
				'note_ticket' => 'Add Internal Note to Ticket',
				'close_ticket' => 'Close Ticket',
				'reopen_ticket' => 'Reopen Ticket',
			];
		}
		$actions = [];
		foreach ( $labels as $key => $label ) { $actions[ $key ] = [ 'label' => $label ]; }
		return $actions;
	}

	public static function get_action_config_schema( string $action ): array {
		$user = self::field( 'user_id', 'User ID', 'number', true );
		$group = self::field( 'group_id', 'Space', 'select', true, 'spaces' );
		$feed = self::field( 'feed_id', 'Post', 'number', true );
		$comment = self::field( 'comment_id', 'Comment ID', 'number', true );
		$event = self::field( 'event_id', 'Event', 'select', true, 'events' );
		$content = self::field( 'content', 'Content', 'textarea', true );
		switch ( $action ) {
			case 'add_user_space': return [ $group, $user, self::field( 'role', 'Space Role', 'select', false, 'group_roles' ) ];
			case 'remove_user_space':
			case 'approve_space_request':
			case 'reject_space_request':
			case 'block_space_user':
			case 'unblock_space_user': return [ $group, $user ];
			case 'change_space_role': return [ $group, $user, self::field( 'role', 'Space Role', 'select', true, 'group_roles' ) ];
			case 'approve_user':
			case 'reject_user': return [ $user ];
			case 'change_community_role': return [ $user, self::field( 'role', 'Global Community Role', 'select', true, 'roles' ) ];
			case 'update_profile': return [ $user, self::field( 'first_name', 'First Name' ), self::field( 'last_name', 'Last Name' ),
				self::field( 'bio', 'Biography', 'textarea' ), self::field( 'display_name', 'Display Name' ) ];
			case 'create_post': return [ $group, $user, self::field( 'title', 'Title' ), $content ];
			case 'update_post': return [ $feed, self::field( 'title', 'Title' ), $content ];
			case 'delete_post': return [ $feed ];
			case 'add_comment': return [ $feed, $user, $content ];
			case 'add_reply': return [ $feed, $comment, $user, $content ];
			case 'delete_comment': return [ $feed, $comment, $user ];
			case 'add_reaction': return [ $feed, $user, self::field( 'reaction_type', 'Reaction (like / love / haha / wow / sad / angry / bookmark / upvote / downvote)', 'text', true ) ];
			case 'send_notification': return [ $user, self::field( 'message', 'Message', 'textarea', true ),
				self::field( 'notification_type', 'Notification Type' ) ];
			case 'create_poll': return [ $group, $user, self::field( 'title', 'Question', 'text', true ),
				self::field( 'options', 'Options (one per line)', 'textarea', true ),
				self::field( 'expires_at', 'Expiry (YYYY-MM-DD HH:MM:SS)', 'text', true ) ];
			case 'create_event': return [ $group, self::field( 'title', 'Title', 'text', true ),
				self::field( 'start_at', 'Start (YYYY-MM-DD HH:MM:SS)', 'text', true ),
				self::field( 'end_at', 'End (YYYY-MM-DD HH:MM:SS)' ),
				self::field( 'description', 'Description', 'textarea' ), self::field( 'location', 'Location' ) ];
			case 'update_event': return [ $event, self::field( 'title', 'Title' ), self::field( 'start_at', 'Start' ),
				self::field( 'end_at', 'End' ), self::field( 'description', 'Description', 'textarea' ), self::field( 'location', 'Location' ) ];
			case 'set_rsvp': return [ $event, $user, self::field( 'status', 'RSVP (going / interested / not_going)', 'text', true ) ];
			case 'cancel_rsvp': return [ $event, $user ];
			case 'send_private_message': return [ self::field( 'sender_id', 'Sender ID', 'number', true ),
				self::field( 'receiver_id', 'Recipient ID', 'number', true ), self::field( 'message', 'Message', 'textarea', true ) ];
			case 'send_group_message': return [ $group, self::field( 'sender_id', 'Sender ID', 'number', true ),
				self::field( 'message', 'Message', 'textarea', true ) ];
			case 'create_ticket': return [ $user,
				self::field( 'type_id', 'Ticket Type', 'select', true, 'ticket_types' ),
				self::field( 'product_id', 'Product', 'select', true, 'ticket_products' ),
				self::field( 'priority_id', 'Priority', 'select', true, 'ticket_priorities' ),
				self::field( 'title', 'Subject', 'text', true ),
				self::field( 'content', 'Description', 'textarea', true ) ];
			case 'assign_ticket': return [ self::field( 'ticket_id', 'Ticket ID', 'number', true ),
				self::field( 'agent_id', 'Support Agent ID', 'number', true ) ];
			case 'change_ticket_status': return [ self::field( 'ticket_id', 'Ticket ID', 'number', true ),
				self::field( 'status', 'Status (new / waiting / open / in-progress / blocked-dev / waiting-release / resolved / closed)', 'text', true ) ];
			case 'change_ticket_priority': return [ self::field( 'ticket_id', 'Ticket ID', 'number', true ),
				self::field( 'priority_id', 'Existing Priority Label ID', 'number', true ) ];
			case 'reply_ticket': return [ self::field( 'ticket_id', 'Ticket ID', 'number', true ),
				$user, self::field( 'content', 'Reply', 'textarea', true ) ];
			case 'note_ticket': return [ self::field( 'ticket_id', 'Ticket ID', 'number', true ),
				self::field( 'content', 'Internal Note', 'textarea', true ) ];
			case 'close_ticket':
			case 'reopen_ticket': return [ self::field( 'ticket_id', 'Ticket ID', 'number', true ) ];
		}
		return [];
	}

	private static function ok( array $data ): array {
		return [ 'port' => 'main', 'data' => array_merge( [ 'success' => true ], $data ) ];
	}

	private static function fail( string $message, array $input = [] ): array {
		// Zaplane marks returned outputs as completed. Throw so the engine records
		// a failed node/run and cannot route this error down a success edge.
		throw new \RuntimeException( $message );
	}

	private static function member_status( int $user_id, int $group_id ): string {
		$row = QueryBuilder::ins()->from( 'zenc_group_members', 'gm' )
			->select( [ 'gm.status' ] )->where( 'gm.group_id', '=', $group_id )
			->where( 'gm.user_id', '=', $user_id )->first();
		return (string) ( $row['status'] ?? '' );
	}

	public static function execute_node( array $node, array $input ): array {
		if ( ! self::available() ) { return self::fail( 'ZenCommunity is not active.', $input ); }
		$action = $node['data']['event'] ?? $node['event'] ?? '';
		if ( ! array_key_exists( $action, self::get_actions() ) ) {
			return self::fail( 'Unsupported ZenCommunity action.', $input );
		}
		$config = $node['data']['config'] ?? $node['config'] ?? [];
		$values = array_merge( $input, is_array( $config ) ? $config : [] );
		$uid = absint( $values['user_id'] ?? 0 );
		$gid = absint( $values['group_id'] ?? 0 );
		$fid = absint( $values['feed_id'] ?? 0 );
		$cid = absint( $values['comment_id'] ?? 0 );
		$eid = absint( $values['event_id'] ?? 0 );
		// Async jobs do not have the request cookie; recover the trusted user
		// captured by Zaplane at trigger time, never from user-editable config.
		$actor = get_current_user_id();
		$restore_actor = 0;
		$hydrated_actor = false;
		if ( ! $actor && ! empty( $node['_run_id'] ) ) {
			$run = \Zaplane\Models\Run::find( absint( $node['_run_id'] ) );
			$stored = is_array( $run->trigger_data ?? null ) ? $run->trigger_data : [];
			$actor = absint( $stored['__wp_user_id'] ?? 0 );
			if ( $actor && get_userdata( $actor ) ) {
				$restore_actor = get_current_user_id();
				wp_set_current_user( $actor );
				$hydrated_actor = true;
			}
		}
		// A community member may fire an administrator-owned automation. For
		// privileged actions, authorize the trusted workflow owner, not the member
		// who happened to cause the hook. Never elevate a non-admin-owned workflow.
		$member_actions = [ 'create_post', 'add_comment', 'add_reply', 'add_reaction', 'set_rsvp', 'cancel_rsvp', 'send_private_message', 'send_group_message', 'reply_ticket' ];
		// Even a member-allowed action needs the owner's authority when its saved
		// config explicitly acts as somebody other than the triggering member.
		$acting_as = in_array( $action, [ 'send_private_message', 'send_group_message' ], true )
			? absint( $values['sender_id'] ?? 0 ) : absint( $values['user_id'] ?? 0 );
		$needs_owner = ! in_array( $action, $member_actions, true ) || ( $acting_as && $acting_as !== $actor );
		if ( $actor && ! user_can( $actor, 'manage_options' ) && $needs_owner && ! empty( $node['_run_id'] ) ) {
			$owner_run = \Zaplane\Models\Run::find( absint( $node['_run_id'] ) );
			$owner_workflow = $owner_run ? \Zaplane\Models\Workflow::find( absint( $owner_run->workflow_id ) ) : null;
			$owner_id = absint( $owner_workflow->user_id ?? 0 );
			if ( $owner_id && user_can( $owner_id, 'manage_options' ) ) {
				if ( ! $hydrated_actor ) { $restore_actor = get_current_user_id(); }
				wp_set_current_user( $owner_id );
				$actor = $owner_id;
				$hydrated_actor = true;
			}
		}
		if ( ! $actor || ( ! user_can( $actor, 'manage_options' ) && ! in_array( $action, $member_actions, true ) ) ) {
			if ( $hydrated_actor ) { wp_set_current_user( $restore_actor ); }
			return self::fail( 'Authorized WordPress user required for this action.', $input );
		}
		try {
			switch ( $action ) {
				case 'send_private_message':
				case 'send_group_message':
					$sender = absint( $values['sender_id'] ?? 0 );
					$receiver = absint( $values['receiver_id'] ?? 0 );
					$message = trim( (string) ( $values['message'] ?? '' ) );
					if ( ! $sender || ! get_userdata( $sender ) || '' === $message
						|| ( 'send_private_message' === $action && ( ! $receiver || ! get_userdata( $receiver ) ) )
						|| ( 'send_group_message' === $action && ! $gid ) ) {
						throw new \InvalidArgumentException( 'Valid sender, recipient/chat and message required.' );
					}
					if ( ! user_can( $actor, 'manage_options' ) && $sender !== $actor ) {
						throw new \InvalidArgumentException( 'Cannot send a message as another user.' );
					}
					$model = 'send_private_message' === $action
						? \ZenCommunityPro\Addons\Messaging\Database\Models\PrivateMessage::class
						: \ZenCommunityPro\Addons\Messaging\Database\Models\GroupMessage::class;
					if ( ! class_exists( $model ) ) { throw new \RuntimeException( 'ZenCommunity Pro messaging is unavailable.' ); }
					$message_data = [ 'sender_id' => $sender, 'message' => $message ];
					if ( 'send_private_message' === $action ) { $message_data['receiver_id'] = $receiver; }
					else { $message_data['group_id'] = $gid; }
					$message_id = $model::send( $message_data );
					return self::ok( [ 'message_id' => $message_id, 'sender_id' => $sender,
						'receiver_id' => 'send_private_message' === $action ? $receiver : 0,
						'group_id' => 'send_group_message' === $action ? $gid : 0 ] );
				case 'create_ticket':
				case 'assign_ticket':
				case 'change_ticket_status':
				case 'change_ticket_priority':
				case 'reply_ticket':
				case 'note_ticket':
				case 'close_ticket':
				case 'reopen_ticket':
					$ticket_model = \ZenCommunityPro\Addons\TicketingSystem\Database\Models\Ticket::class;
					if ( ! class_exists( $ticket_model ) ) { throw new \RuntimeException( 'ZenCommunity Pro ticketing is unavailable.' ); }
					$ticket_id = absint( $values['ticket_id'] ?? 0 );
					if ( 'create_ticket' === $action ) {
						$type_id = absint( $values['type_id'] ?? 0 );
						$product_id = absint( $values['product_id'] ?? 0 );
						$priority_id = absint( $values['priority_id'] ?? 0 );
						if ( ! $uid || ! get_userdata( $uid ) || ! $type_id || ! $product_id || ! $priority_id
							|| empty( $values['title'] ) || empty( $values['content'] ) ) {
							throw new \InvalidArgumentException( 'Existing user, ticket type, product, priority, subject and description required.' );
						}
						$ticket_id = $ticket_model::create( [ 'user_id' => $uid, 'type_id' => $type_id,
							'product_id' => $product_id, 'priority_id' => $priority_id, 'title' => (string) $values['title'],
							'content' => (string) $values['content'] ], $actor );
						return self::ok( [ 'ticket_id' => $ticket_id, 'user_id' => $uid ] );
					}
					if ( ! $ticket_id || ! $ticket_model::exists( $ticket_id ) ) {
						throw new \InvalidArgumentException( 'Existing ticket required.' );
					}
					$ticket = $ticket_model::by_id( $ticket_id );
					if ( 'assign_ticket' === $action ) {
						$agent_id = absint( $values['agent_id'] ?? 0 );
						$assignment = \ZenCommunityPro\Addons\TicketingSystem\Database\Models\TicketAssignment::class;
						if ( ! $agent_id || ! get_userdata( $agent_id ) || $assignment::exists( $ticket_id, $agent_id ) ) {
							throw new \InvalidArgumentException( 'Valid unassigned support agent required.' );
						}
						if ( ! $assignment::attach( $ticket_id, $agent_id, $actor, $actor ) ) {
							throw new \RuntimeException( 'Agent assignment did not succeed.' );
						}
						return self::ok( [ 'ticket_id' => $ticket_id, 'agent_id' => $agent_id ] );
					}
					if ( 'reply_ticket' === $action || 'note_ticket' === $action ) {
						$author = 'note_ticket' === $action ? $actor : $uid;
						if ( ! $author || ! get_userdata( $author ) || empty( $values['content'] ) ) {
							throw new \InvalidArgumentException( 'Reply author and nonempty content required.' );
						}
						if ( 'reply_ticket' === $action && ! user_can( $actor, 'manage_options' ) && $author !== $actor ) {
							throw new \InvalidArgumentException( 'Cannot reply as another user.' );
						}
						$conversation = $ticket_model::conversation( $ticket_id, $author, [
							'content' => (string) $values['content'], 'is_note' => 'note_ticket' === $action,
						] );
						return self::ok( [ 'ticket_id' => $ticket_id,
							'conversation_id' => absint( $conversation['id'] ?? 0 ), 'is_note' => 'note_ticket' === $action ] );
					}
					if ( 'change_ticket_priority' === $action ) {
						$priority = absint( $values['priority_id'] ?? 0 );
						$label = \ZenCommunityPro\Addons\TicketingSystem\Database\Models\Label::class;
						if ( ! $priority || ! $label::exists( $priority, 'priority' ) ) {
							throw new \InvalidArgumentException( 'Existing priority label required.' );
						}
						$changes = [ 'priority_id' => $priority ];
					} else {
						$status = 'close_ticket' === $action ? 'closed'
							: ( 'reopen_ticket' === $action ? 'open' : (string) ( $values['status'] ?? '' ) );
						if ( ! in_array( $status, [ 'new', 'waiting', 'open', 'in-progress', 'blocked-dev',
							'waiting-release', 'resolved', 'closed', 'draft' ], true ) ) {
							throw new \InvalidArgumentException( 'Invalid ticket status.' );
						}
						if ( 'reopen_ticket' === $action && 'closed' !== ( $ticket['status'] ?? '' ) ) {
							throw new \InvalidArgumentException( 'Only a closed ticket can be reopened.' );
						}
						$changes = [ 'status' => $status ];
					}
					$ticket_model::update( $ticket_id, $changes, $actor );
					return self::ok( array_merge( [ 'ticket_id' => $ticket_id ], $changes ) );
				case 'add_user_space':
					if ( ! $uid || ! $gid || ! get_userdata( $uid ) || ! Group::exists( $gid ) ) { throw new \InvalidArgumentException( 'Valid user and space are required.' ); }
					$role = sanitize_key( (string) ( $values['role'] ?? 'member' ) );
					if ( ! in_array( $role, RoleManager::group_role_keys(), true ) ) { throw new \InvalidArgumentException( 'Invalid space role.' ); }
					Group::add_member( $uid, $gid, $role );
					return self::ok( [ 'user_id' => $uid, 'group_id' => $gid, 'status' => self::member_status( $uid, $gid ) ] );
				case 'remove_user_space':
					if ( ! $uid || ! $gid || ! Group::is_member_of( $uid, $gid, [], null ) ) { throw new \InvalidArgumentException( 'Space membership does not exist.' ); }
					Group::remove_member( $uid, $gid );
					return self::ok( [ 'user_id' => $uid, 'group_id' => $gid ] );
				case 'approve_space_request':
				case 'reject_space_request':
					if ( ! $uid || ! $gid || self::member_status( $uid, $gid ) !== 'pending' ) { throw new \InvalidArgumentException( 'A pending space join request is required.' ); }
					$status = 'approve_space_request' === $action ? 'active' : 'inactive';
					Group::change_member_status( $uid, $gid, $status );
					return self::ok( [ 'user_id' => $uid, 'group_id' => $gid, 'status' => $status ] );
				case 'change_space_role':
					$role = sanitize_key( (string) ( $values['role'] ?? '' ) );
					if ( ! $uid || ! $gid || ! in_array( $role, RoleManager::group_role_keys(), true ) ) { throw new \InvalidArgumentException( 'Valid membership and space role are required.' ); }
					Group::change_member_role( $uid, $gid, $role );
					return self::ok( [ 'user_id' => $uid, 'group_id' => $gid, 'role' => $role ] );
				case 'block_space_user':
				case 'unblock_space_user':
					$existing_status = $uid && $gid ? self::member_status( $uid, $gid ) : '';
					if ( ! $existing_status ) { throw new \InvalidArgumentException( 'Valid membership is required.' ); }
					if ( 'unblock_space_user' === $action && 'banned' !== $existing_status ) { throw new \InvalidArgumentException( 'Only banned members can be unblocked.' ); }
					$status = 'block_space_user' === $action ? 'banned' : 'active';
					Group::change_member_status( $uid, $gid, $status );
					return self::ok( [ 'user_id' => $uid, 'group_id' => $gid, 'status' => $status ] );
				case 'approve_user':
				case 'reject_user':
					if ( ! $uid || ! Profile::exists( $uid ) ) { throw new \InvalidArgumentException( 'Community profile not found.' ); }
					$status = 'approve_user' === $action ? 'active' : 'inactive';
					if ( ! Profile::change_user_status( $uid, $status ) ) { throw new \RuntimeException( 'Community status was not changed.' ); }
					return self::ok( [ 'user_id' => $uid, 'status' => $status ] );
				case 'update_profile':
					if ( ! $uid || ! Profile::exists( $uid ) ) { throw new \InvalidArgumentException( 'Community profile not found.' ); }
					$updates = [];
					foreach ( [ 'first_name', 'last_name', 'bio' ] as $key ) {
						if ( array_key_exists( $key, $values ) && '' !== (string) $values[ $key ] ) { $updates[ $key ] = $values[ $key ]; }
					}
					if ( isset( $values['display_name'] ) && '' !== (string) $values['display_name'] ) {
						$updates['meta'] = [ 'display_name' => $values['display_name'] ];
					}
					if ( ! $updates ) { throw new \InvalidArgumentException( 'Provide at least one profile field.' ); }
					Profile::update( $uid, $updates );
					return self::ok( [ 'user_id' => $uid, 'profile' => Profile::by_id( $uid ) ] );
				case 'change_community_role':
					$role = sanitize_key( (string) ( $values['role'] ?? '' ) );
					$roles = RoleManager::roles();
					if ( ! $uid || ! Profile::exists( $uid ) || ! isset( $roles[ $role ] )
						|| ( empty( $roles[ $role ]['is_global'] ) && ! in_array( $role, RoleManager::BUILT_IN_GLOBAL_ROLES, true ) ) ) {
						throw new \InvalidArgumentException( 'Valid user and global community role required.' );
					}
					RoleManager::update_global_roles_by_user_id( $uid, [ 'role' => $role, 'action' => 'add' ] );
					return self::ok( [ 'user_id' => $uid, 'role' => $role,
						'global_roles' => array_keys( RoleManager::get_global_roles_by_user_id( $uid ) ) ] );
				case 'create_post':
					if ( ! $gid || ! $uid || ! get_userdata( $uid ) || ! Group::exists( $gid ) || empty( $values['content'] ) ) { throw new \InvalidArgumentException( 'Valid space, author and content required.' ); }
					if ( $actor && ! current_user_can( 'manage_options' ) && $actor !== $uid ) { throw new \InvalidArgumentException( 'Cannot post as another user.' ); }
					$id = Feed::create( $gid, [ 'title' => (string) ( $values['title'] ?? '' ), 'content' => (string) $values['content'], 'type' => 'post' ], $uid );
					return self::ok( [ 'feed_id' => $id, 'group_id' => $gid, 'user_id' => $uid ] );
				case 'update_post':
				case 'delete_post':
					if ( ! $fid || ! Feed::exists( $fid ) ) { throw new \InvalidArgumentException( 'Post not found.' ); }
					$post = Feed::by_id( $fid );
					if ( ( $post['type'] ?? 'post' ) !== 'post' ) { throw new \InvalidArgumentException( 'This action requires a regular post.' ); }
					if ( 'delete_post' === $action ) { Feed::delete( $fid ); }
					else {
						$updates = [];
						foreach ( [ 'title', 'content' ] as $key ) { if ( array_key_exists( $key, $values ) && '' !== (string) $values[ $key ] ) { $updates[ $key ] = $values[ $key ]; } }
						if ( ! $updates ) { throw new \InvalidArgumentException( 'Provide a post field to update.' ); }
						Feed::update( $fid, $updates );
					}
					return self::ok( [ 'feed_id' => $fid, 'deleted' => 'delete_post' === $action ] );
				case 'add_comment':
				case 'add_reply':
					if ( ! $fid || ! $uid || ! get_userdata( $uid ) || empty( $values['content'] ) || ( 'add_reply' === $action && ! $cid ) ) { throw new \InvalidArgumentException( 'Post, author, content and optional parent comment required.' ); }
					if ( $actor && ! current_user_can( 'manage_options' ) && $actor !== $uid ) { throw new \InvalidArgumentException( 'Cannot comment as another user.' ); }
					$created = Feed::comment( $fid, $uid, [ 'content' => (string) $values['content'] ], 'add_reply' === $action ? $cid : null );
					return self::ok( [ 'feed_id' => $fid, 'comment_id' => absint( $created['id'] ?? 0 ), 'comment' => $created ] );
				case 'delete_comment':
					if ( ! $fid || ! $cid || ! $uid ) { throw new \InvalidArgumentException( 'Post, comment and acting user required.' ); }
					Feed::delete_comment( $fid, $uid, $cid );
					return self::ok( [ 'feed_id' => $fid, 'comment_id' => $cid ] );
				case 'add_reaction':
					$type = sanitize_key( (string) ( $values['reaction_type'] ?? '' ) );
					if ( ! $fid || ! $uid || ! get_userdata( $uid ) || ! in_array( $type, [ 'like', 'love', 'haha', 'wow', 'sad', 'angry', 'bookmark', 'upvote', 'downvote' ], true ) ) { throw new \InvalidArgumentException( 'Valid post, user and reaction required.' ); }
					if ( $actor && ! current_user_can( 'manage_options' ) && $actor !== $uid ) { throw new \InvalidArgumentException( 'Cannot react as another user.' ); }
					if ( in_array( $type, Feed::get_reaction( $uid, $fid ) ?? [], true ) ) { return self::ok( [ 'feed_id' => $fid, 'user_id' => $uid, 'reaction_type' => $type, 'already_exists' => true ] ); }
					Feed::react( $fid, $type, $uid );
					return self::ok( [ 'feed_id' => $fid, 'user_id' => $uid, 'reaction_type' => $type ] );
				case 'send_notification':
					if ( ! $uid || ! Profile::exists( $uid ) || empty( $values['message'] ) ) { throw new \InvalidArgumentException( 'Existing community user and message required.' ); }
					$sent = Notification::send( [ 'type' => sanitize_key( (string) ( $values['notification_type'] ?? 'custom' ) ),
						'from_user_id' => $actor ?: null, 'to_user_ids' => [ $uid ],
						'message' => sanitize_textarea_field( (string) $values['message'] ), 'data' => [] ] );
					if ( ! $sent ) { throw new \RuntimeException( 'Notification not sent (check recipient preferences).' ); }
					return self::ok( [ 'user_id' => $uid, 'notification_sent' => true ] );
				case 'create_poll':
					if ( ! $gid || ! $uid || empty( $values['title'] ) || empty( $values['expires_at'] ) ) { throw new \InvalidArgumentException( 'Space, author, question and expiry required.' ); }
					$raw = $values['options'] ?? [];
					$options = is_array( $raw ) ? $raw : preg_split( '/\r\n|\r|\n/', (string) $raw );
					$options = array_values( array_filter( array_map( static function ( $option ) {
						$text = is_array( $option ) ? ( $option['text'] ?? '' ) : $option;
						return [ 'text' => sanitize_text_field( (string) $text ) ];
					}, $options ), static fn( $option ) => '' !== $option['text'] ) );
					if ( count( $options ) < 2 || false === strtotime( (string) $values['expires_at'] ) ) { throw new \InvalidArgumentException( 'At least two options and a valid expiry are required.' ); }
					$id = Feed::create( $gid, [ 'title' => (string) $values['title'], 'content' => (string) ( $values['content'] ?? '' ),
						'type' => 'poll', 'meta' => [ 'poll' => [ 'options' => $options, 'expires_at' => (string) $values['expires_at'],
						'allow_multiple' => ! empty( $values['allow_multiple'] ) ] ] ], $uid );
					return self::ok( [ 'feed_id' => $id, 'group_id' => $gid, 'type' => 'poll' ] );
				case 'create_event':
				case 'update_event':
					if ( 'create_event' === $action && ( ! $gid || ! Group::exists( $gid ) ) ) { throw new \InvalidArgumentException( 'Valid space required.' ); }
					if ( 'update_event' === $action && ! $eid ) { throw new \InvalidArgumentException( 'Event ID required.' ); }
					$fields = [];
					foreach ( [ 'title', 'description', 'start_at', 'end_at', 'location', 'cover_image', 'rsvp_limit', 'status' ] as $key ) {
						if ( isset( $values[ $key ] ) && '' !== (string) $values[ $key ] ) { $fields[ $key ] = $values[ $key ]; }
					}
					if ( 'create_event' === $action ) {
						if ( empty( $fields['title'] ) || empty( $fields['start_at'] ) ) { throw new \InvalidArgumentException( 'Title and start time required.' ); }
						$eid = Event::create( $gid, $fields );
						// The model does not dispatch this hook; ZenCommunity's REST controller does.
						do_action( 'zencommunity/event/created', $eid, $gid, $fields );
					} else { if ( ! $fields ) { throw new \InvalidArgumentException( 'No event changes provided.' ); } Event::update( $eid, $fields ); }
					return self::ok( [ 'event_id' => $eid, 'group_id' => $gid ] );
				case 'set_rsvp':
				case 'cancel_rsvp':
					if ( ! $eid || ! $uid || ! get_userdata( $uid ) ) { throw new \InvalidArgumentException( 'Valid event and user required.' ); }
					if ( $actor && ! current_user_can( 'manage_options' ) && $actor !== $uid ) { throw new \InvalidArgumentException( 'Cannot RSVP as another user.' ); }
					if ( 'cancel_rsvp' === $action ) { Event::remove_rsvp( $eid, $uid ); }
					else {
						$status = (string) ( $values['status'] ?? '' );
						if ( ! in_array( $status, [ 'going', 'interested', 'not_going' ], true ) ) { throw new \InvalidArgumentException( 'Invalid RSVP status.' ); }
						Event::rsvp( $eid, $uid, $status );
					}
					return self::ok( [ 'event_id' => $eid, 'user_id' => $uid, 'status' => 'cancel_rsvp' === $action ? 'cancelled' : $status ] );
			}
		} catch ( \Throwable $e ) {
			return self::fail( $e->getMessage(), $input );
		} finally {
			if ( $hydrated_actor ) { wp_set_current_user( $restore_actor ); }
		}
		return self::fail( 'ZenCommunity operation was not implemented.', $input );
	}
}

