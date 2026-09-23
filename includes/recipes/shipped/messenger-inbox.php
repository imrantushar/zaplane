<?php
/**
 * Messenger in the Inbox: Messenger chats come into the Inbox, and replies from
 * the Inbox (the team, the assistant or automatic answers) go back out.
 *
 * Two workflows, both using the Messenger connection chosen during setup:
 *
 * - "Bring Messenger chats into the Inbox": Messenger: Webhook Received →
 *   Messenger: Add Event to Inbox. Every event in the delivery is read:
 *   messages, pictures, taps on buttons, replies sent from the Messenger app.
 * - "Send Inbox replies on Messenger": Inbox: Reply to Deliver (source
 *   `messenger`) → Messenger: Send Inbox Reply.
 *
 * The channel is on while these are active: pause them and it stops.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'title'       => 'Messenger in the Inbox',
	'description' => 'Answer Messenger chats from the Inbox: conversations come in with their pictures and button taps, and your replies (and the assistant\'s) go back on Messenger. Uses the Messenger connection you pick here; pause the workflows to turn it off.',
	'folder'      => 'Inbox: Messenger',
	'tags'        => [ 'inbox' ],
	'inbox'       => [ 'channel' => 'messenger' ],
	'workflows'   => [
		[
			'key'         => 'receive',
			'title'       => 'Bring Messenger chats into the Inbox',
			'description' => 'Every Messenger delivery is added to the Inbox, one conversation per customer.',
			'steps'       => [
				[
					'trigger' => 'messenger.webhook_received',
					'name'    => 'Webhook Received',
				],
				[
					'action' => 'messenger.inbox_receive',
					'name'   => 'Add to Inbox',
				],
			],
		],
		[
			'key'         => 'deliver',
			'title'       => 'Send Inbox replies on Messenger',
			'description' => 'Replies written in the Inbox (and the assistant\'s) are sent to the customer on Messenger.',
			'steps'       => [
				[
					'trigger' => 'inbox.reply_requested',
					'name'    => 'Reply to Deliver',
					'config'  => [ 'source' => 'messenger' ],
				],
				[
					'action' => 'messenger.inbox_send',
					'name'   => 'Send Inbox Reply',
					'config' => [ 'message_id' => '{{trigger.message_id}}' ],
				],
			],
		],
	],
];
