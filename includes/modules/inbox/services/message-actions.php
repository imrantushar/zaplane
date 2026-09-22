<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Message;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editing, deleting and quoting messages from the inbox.
 *
 * A change is only offered where the customer's copy changes too: private
 * notes (the customer never sees them) and website chat (the widget reloads
 * on the next poll). Messenger and WhatsApp can't edit or unsend a delivered
 * message, so the inbox doesn't pretend it can.
 *
 * Every change bumps the conversation's `revision`, which is how the widget
 * and other open inboxes know to reload the thread instead of only fetching
 * newer messages.
 */
class MessageActions {

	/** Channels whose customer-facing copy we control. */
	private const EDITABLE_CHANNELS = [ 'web' ];

	/** Quoted text stored with a reply. */
	private const EXCERPT = 140;

	/**
	 * Whether the current user may edit or delete this message, and why not.
	 *
	 * @return array{ok:bool,reason:string}
	 */
	public static function can_change( Message $message ): array {
		$meta = is_array( $message->meta ) ? $message->meta : [];

		if ( ! empty( $meta['deleted_at'] ) ) {
			return self::no( __( 'This message was deleted.', 'zaplane' ) );
		}
		if ( 'out' !== $message->direction || 'system' === $message->sender_type ) {
			return self::no( __( "Customers' messages and system lines can't be changed.", 'zaplane' ) );
		}
		if ( ! empty( $message->attachments ) ) {
			return self::no( __( 'Messages with a product or file can only be removed by the channel.', 'zaplane' ) );
		}

		$own = 'agent' === $message->sender_type && (int) $message->sender_id === get_current_user_id();
		if ( ! $own && ! current_user_can( 'manage_options' ) ) {
			return self::no( __( 'Only the person who sent it, or an administrator, can change this message.', 'zaplane' ) );
		}

		if ( ! $message->is_note && Sources::is( (string) $message->channel ) ) {
			return self::no( __( 'A workflow delivered this reply, so it can only be changed where it was posted.', 'zaplane' ) );
		}
		if ( ! $message->is_note && ! in_array( (string) $message->channel, self::EDITABLE_CHANNELS, true ) ) {
			return self::no(
				/* translators: %s: channel name, e.g. Messenger. */
				sprintf( __( "%s doesn't allow editing or unsending a message after it's delivered.", 'zaplane' ), self::channel_label( (string) $message->channel ) )
			);
		}

		return [
			'ok'     => true,
			'reason' => '',
		];
	}

	public static function edit( Conversation $conversation, Message $message, string $body ): Message {
		$meta               = is_array( $message->meta ) ? $message->meta : [];
		$meta['edited_at']  = Conversations::now();
		$meta['edit_count'] = (int) ( $meta['edit_count'] ?? 0 ) + 1;

		$message->body = $body;
		$message->meta = $meta;
		$message->save();

		self::after_change( $conversation, $message );
		return $message;
	}

	public static function delete( Conversation $conversation, Message $message ): Message {
		$meta               = is_array( $message->meta ) ? $message->meta : [];
		$meta['deleted_at'] = Conversations::now();
		$meta['deleted_by'] = get_current_user_id();

		// The row stays, so the thread keeps its shape and the audit trail its
		// gap; the words themselves go.
		$message->body        = '';
		$message->attachments = [];
		$message->meta        = $meta;
		$message->save();

		self::after_change( $conversation, $message );
		return $message;
	}

	/**
	 * The quote to store with a new message, or an error when it can't quote
	 * that message. A reply to the customer never quotes a private note.
	 *
	 * @return array<string,mixed>|\WP_Error|null
	 */
	public static function quote( Conversation $conversation, int $reply_to, bool $is_note ) {
		if ( $reply_to <= 0 ) {
			return null;
		}

		$quoted = Message::where( 'id', $reply_to )->where( 'conversation_id', (int) $conversation->id )->fresh()->first();
		if ( ! $quoted || 'system' === $quoted->sender_type ) {
			return new \WP_Error( 'zaplane_inbox_quote', __( 'That message is no longer in this conversation.', 'zaplane' ), [ 'status' => 400 ] );
		}
		if ( $quoted->is_note && ! $is_note ) {
			return new \WP_Error( 'zaplane_inbox_quote', __( "A reply to the customer can't quote a private note.", 'zaplane' ), [ 'status' => 400 ] );
		}

		$meta = is_array( $quoted->meta ) ? $quoted->meta : [];
		$text = ! empty( $meta['deleted_at'] ) ? '' : Ingest::preview( (string) $quoted->body, is_array( $quoted->attachments ) ? $quoted->attachments : [] );

		return [
			'id'          => (int) $quoted->id,
			'external_id' => (string) $quoted->external_id,
			'sender_name' => (string) ( Presenter::message( $quoted, true )['sender_name'] ?: __( 'Customer', 'zaplane' ) ),
			'direction'   => (string) $quoted->direction,
			'excerpt'     => mb_substr( $text, 0, self::EXCERPT ),
		];
	}

	/** Revision the widget and other open inboxes compare against. */
	public static function revision( Conversation $conversation ): int {
		$meta = is_array( $conversation->meta ) ? $conversation->meta : [];
		return (int) ( $meta['revision'] ?? 0 );
	}

	private static function after_change( Conversation $conversation, Message $message ): void {
		$meta             = is_array( $conversation->meta ) ? $conversation->meta : [];
		$meta['revision'] = (int) ( $meta['revision'] ?? 0 ) + 1;
		$conversation->meta = $meta;

		// Keep the list preview honest when the newest reply changed.
		if ( ! $message->is_note ) {
			$latest = Message::where( 'conversation_id', (int) $conversation->id )
				->where( 'is_note', 0 )
				->where( 'sender_type', '!=', 'system' )
				->orderBy( 'id', 'desc' )
				->fresh()
				->first();
			if ( $latest && (int) $latest->id === (int) $message->id ) {
				$conversation->last_message_preview = '' === (string) $message->body
					? __( 'Message deleted', 'zaplane' )
					: Ingest::preview( (string) $message->body, [] );
			}
		}

		$conversation->save();
	}

	private static function channel_label( string $slug ): string {
		if ( Sources::is( $slug ) ) {
			return Sources::label( $slug );
		}
		$class = \Zaplane\Modules\Inbox\Channels\Registry::get( $slug );
		return $class ? (string) $class::label() : $slug;
	}

	/**
	 * @return array{ok:bool,reason:string}
	 */
	private static function no( string $reason ): array {
		return [
			'ok'     => false,
			'reason' => $reason,
		];
	}
}
