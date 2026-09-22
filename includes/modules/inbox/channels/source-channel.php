<?php

namespace Zaplane\Modules\Inbox\Channels;

use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Identity;
use Zaplane\Modules\Inbox\Models\Message;
use Zaplane\Modules\Inbox\Services\Conversations;
use Zaplane\Modules\Inbox\Services\Presenter;
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
		if ( ! Sources::has_delivery() ) {
			return [
				'ok'     => false,
				'reason' => sprintf(
					/* translators: %s: source name, e.g. Comments. */
					__( 'No active workflow delivers replies to %s. Turn on the "Reply to Deliver" workflow from its recipe.', 'zaplane' ),
					Sources::label( (string) $conversation->channel )
				),
			];
		}
		return [ 'ok' => true ];
	}

	public static function send( Conversation $conversation, ?Identity $identity, Message $message ): array {
		$meta = is_array( $conversation->meta ) ? $conversation->meta : [];

		// Reply under the newest message from the customer.
		$last_in = Message::where( 'conversation_id', (int) $conversation->id )
			->where( 'direction', 'in' )
			->orderBy( 'id', 'desc' )
			->fresh()
			->first();

		$user = 'agent' === $message->sender_type && $message->sender_id ? get_userdata( (int) $message->sender_id ) : null;

		/**
		 * The team (or the assistant, or a workflow) replied in a conversation
		 * from a workflow source. The "Reply to Deliver" trigger listens here.
		 *
		 * @param array<string,mixed> $payload
		 */
		do_action( 'zaplane/inbox/reply_requested', Conversations::payload( $conversation, [
			'message_id'     => (int) $message->id,
			'text'           => (string) $message->body,
			'source'         => (string) $conversation->channel,
			'thread_id'      => $identity ? (string) $identity->external_id : '',
			// The source's own ID (stored as "source:id").
			'reply_to'       => $last_in ? (string) preg_replace( '/^[^:]*:/', '', (string) $last_in->external_id ) : '',
			'link_url'       => (string) ( $meta['link_url'] ?? '' ),
			'link_title'     => (string) ( $meta['link_title'] ?? '' ),
			'sender_type'    => (string) $message->sender_type,
			'sender_user_id' => $user ? (int) $user->ID : 0,
			'sender_name'    => $user ? (string) $user->display_name : (string) ( Presenter::message( $message )['sender_name'] ?? '' ),
			'sender_email'   => $user ? (string) $user->user_email : (string) get_option( 'admin_email' ),
		] ) );

		// Handed to the workflow; it may confirm (or report a failure) later.
		return [ 'status' => 'sent' ];
	}
}
