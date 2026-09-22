<?php
/**
 * WhatsApp in the Inbox: WhatsApp chats come into the Inbox, and replies from
 * the Inbox (the team, the assistant or automatic answers) go back out.
 *
 * Two workflows, both using the WhatsApp connection chosen during setup:
 *
 * - "Bring WhatsApp chats into the Inbox": WhatsApp: Webhook Received →
 *   WhatsApp: Add Event to Inbox. Every event in the delivery is read:
 *   messages, pictures, taps on buttons, replies sent from the WhatsApp app.
 * - "Send Inbox replies on WhatsApp": Inbox: Reply to Deliver (source
 *   `whatsapp`) → WhatsApp: Send Inbox Reply.
 *
 * The channel is on while these are active: pause them and it stops.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'title'       => 'WhatsApp in the Inbox',
	'description' => 'Answer WhatsApp chats from the Inbox: conversations come in with their pictures and button taps, and your replies (and the assistant\'s) go back on WhatsApp. Uses the WhatsApp connection you pick here; pause the workflows to turn it off.',
	'folder'      => 'Inbox: WhatsApp',
	'tags'        => [ 'inbox' ],
	'inbox'       => [ 'channel' => 'whatsapp' ],
	'workflows'   => [
		[
			'key'         => 'receive',
			'title'       => 'Bring WhatsApp chats into the Inbox',
			'description' => 'Every WhatsApp delivery is added to the Inbox, one conversation per customer.',
			'steps'       => [
				[
					'trigger' => 'whatsapp.webhook_received',
					'name'    => 'Webhook Received',
				],
				[
					'action' => 'whatsapp.inbox_receive',
					'name'   => 'Add to Inbox',
				],
			],
		],
		[
			'key'         => 'deliver',
			'title'       => 'Send Inbox replies on WhatsApp',
			'description' => 'Replies written in the Inbox (and the assistant\'s) are sent to the customer on WhatsApp.',
			'steps'       => [
				[
					'trigger' => 'inbox.reply_requested',
					'name'    => 'Reply to Deliver',
					'config'  => [ 'source' => 'whatsapp' ],
				],
				[
					'action' => 'whatsapp.inbox_send',
					'name'   => 'Send Inbox Reply',
					'config' => [ 'message_id' => '{{trigger.message_id}}' ],
				],
			],
		],
	],
];
