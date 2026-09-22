<?php
/**
 * WordPress Comments in the Inbox: comments come in as conversations, and a
 * reply from the Inbox is posted as a comment reply.
 *
 * Nothing about comments is built into the Inbox. It's two workflows:
 *
 * - "Bring comments into the Inbox": Comment Posted → (not spam, a real
 *   comment) → Inbox: Add Incoming Message, filed under the source
 *   `wp_comments`, one conversation per comment thread. A comment your team
 *   writes in wp-admin is recorded as your reply.
 * - "Post Inbox replies as comments": Inbox: Reply to Deliver (source
 *   `wp_comments`) → WordPress: Reply to Comment (as the team member who
 *   replied) → Inbox: Confirm Reply Delivered.
 *
 * A reply the workflow posts is inserted directly, which doesn't fire
 * Comment Posted, so it doesn't come back in as a new message.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$source = 'wp_comments';

return [
	'title'       => 'WordPress Comments in the Inbox',
	'description' => 'Answer your site\'s comments from the Inbox. Each comment thread becomes a conversation (with the page it\'s on), and your reply is posted under the comment as you. Replies made in wp-admin show up in the conversation too.',
	'folder'      => 'Inbox: Comments',
	'tags'        => [ 'inbox' ],
	'workflows'   => [
		[
			'key'         => 'comments_in',
			'title'       => 'Bring comments into the Inbox',
			'description' => 'Every new comment (not spam, not a pingback) lands in the Inbox, one conversation per thread.',
			'options'     => [
				[
					'key'     => 'approved_only',
					'label'   => 'Only approved comments (skip ones waiting for moderation)',
					// Off: a comment waiting for moderation is worth an answer, and replying approves it.
					'default' => false,
				],
			],
			'steps'       => [
				[
					'trigger' => 'wordpress.comment_post',
					'name'    => 'Comment Posted',
				],
				[
					'action' => 'filter.filter',
					'name'   => 'A Real Comment',
					'config' => [
						'conditions' => [
							'logic'      => 'AND',
							'conditions' => [
								[
									'left'     => '{{trigger.comment_approved}}',
									'operator' => '!=',
									'right'    => 'spam',
								],
								[
									'left'     => '{{trigger.comment_type}}',
									'operator' => '==',
									'right'    => 'comment',
								],
							],
						],
					],
				],
				[
					'action' => 'filter.filter',
					'name'   => 'Approved Only',
					'option' => 'approved_only',
					'config' => [
						'conditions' => [
							'logic'      => 'AND',
							'conditions' => [
								[
									'left'     => '{{trigger.comment_approved}}',
									'operator' => '==',
									'right'    => '1',
								],
							],
						],
					],
				],
				[
					'action' => 'inbox.receive_message',
					'name'   => 'Add to Inbox',
					'config' => [
						'source'       => $source,
						'source_label' => 'Comments',
						'thread_id'    => 'comment-{{trigger.thread_id}}',
						'message_id'   => '{{trigger.comment_ID}}',
						'text'         => '{{trigger.comment_content}}',
						'name'         => '{{trigger.comment_author}}',
						'email'        => '{{trigger.comment_author_email}}',
						'link_url'     => '{{trigger.post_url}}',
						'link_title'   => '{{trigger.post_title}}',
						'from_team'    => '{{trigger.author_is_team}}',
					],
				],
			],
		],
		[
			'key'         => 'replies_out',
			'title'       => 'Post Inbox replies as comments',
			'description' => 'A reply you send from the Inbox is posted under the comment, as you, and approves it if it was waiting.',
			'steps'       => [
				[
					'trigger' => 'inbox.reply_requested',
					'name'    => 'Reply to Deliver',
					'config'  => [ 'source' => $source ],
				],
				[
					'action' => 'wordpress.reply_comment',
					'name'   => 'Reply to Comment',
					'config' => [
						'parent_id'    => '{{trigger.reply_to}}',
						'author_name'  => '{{trigger.sender_name}}',
						'author_email' => '{{trigger.sender_email}}',
						'content'      => '{{trigger.text}}',
						'user_id'      => '{{trigger.sender_user_id}}',
					],
				],
				[
					'action' => 'inbox.confirm_delivery',
					'name'   => 'Confirm Delivered',
					'config' => [
						'message_id'  => '{{trigger.message_id}}',
						'external_id' => '{{comment_id}}',
					],
				],
			],
		],
	],
];
