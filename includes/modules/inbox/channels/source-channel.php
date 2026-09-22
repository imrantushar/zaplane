<?php

namespace Zaplane\Modules\Inbox\Channels;

use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Identity;
use Zaplane\Modules\Inbox\Models\Message;
use Zaplane\Modules\Inbox\Services\Sources;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A source a workflow brings in (see Services\Sources). The inbox can't
 * deliver to it itself: a reply fires `zaplane/inbox/reply_requested`, and
 * the workflow listening on it posts the reply (a comment reply, a ticket
 * note…), then may confirm with "Confirm Reply Delivered".
 */
class SourceChannel implements ChannelInterface {

	public static function slug(): string {
		return 'source';
	}

	public static function label(): string {
		return __( 'Workflow source', 'zaplane' );
	}

	public static function can_send( Conversation $conversation ): array {
		if ( ! Sources::has_delivery( (string) $conversation->channel ) ) {
			return [
				'ok'     => false,
				'reason' => sprintf(
					/* translators: %s: source name, e.g. Comments. */
					__( 'No active workflow delivers replies to %s. Turn on its "Reply to Deliver" workflow (Inbox → Settings).', 'zaplane' ),
					Sources::label( (string) $conversation->channel )
				),
			];
		}
		return [ 'ok' => true ];
	}

	public static function send( Conversation $conversation, ?Identity $identity, Message $message ): array {
		/**
		 * The team (or the assistant, or a workflow) replied in a conversation
		 * whose replies a workflow delivers. The "Reply to Deliver" trigger
		 * listens here.
		 *
		 * @param array<string,mixed> $payload
		 */
		do_action( 'zaplane/inbox/reply_requested', Sources::reply_payload( $conversation, $identity, $message ) );

		// Handed to the workflow; it may confirm (or report a failure) later.
		return [ 'status' => 'sent' ];
	}
}
