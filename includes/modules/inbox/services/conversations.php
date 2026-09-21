<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Modules\Inbox\Models\Contact;
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Message;
use Zaplane\Modules\Inbox\Models\Tag;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * State changes on a conversation. Everything that changes who answers — the
 * AI, a person, a workflow — goes through here so the rules live in one place.
 */
class Conversations {

	public const STATUSES = [ 'open', 'pending', 'snoozed', 'closed' ];
	public const HANDLERS = [ 'bot', 'human', 'workflow' ];

	public static function now(): string {
		return current_time( 'mysql' );
	}

	public static function find( int $id ): ?Conversation {
		if ( $id <= 0 ) {
			return null;
		}
		return Conversation::where( 'id', $id )->fresh()->first();
	}

	/**
	 * The AI stops for good in this conversation. Called when a person replies
	 * and when the AI itself hands over: resuming after either would talk over
	 * the human who is now answering.
	 */
	public static function hand_to_human( Conversation $conversation, string $reason = '', bool $announce = true ): void {
		$was_bot = $conversation->ai_enabled || 'bot' === $conversation->handler;

		$conversation->ai_enabled = false;
		$conversation->handler    = 'human';
		if ( 'closed' === $conversation->status ) {
			$conversation->status = 'open';
		}
		$conversation->save();

		if ( ! $was_bot ) {
			return;
		}

		if ( $announce ) {
			self::system_note( $conversation, '' !== $reason
				/* translators: %s: why the assistant handed the conversation over. */
				? sprintf( __( 'Assistant handed this conversation to the team: %s', 'zaplane' ), $reason )
				: __( 'Assistant handed this conversation to the team.', 'zaplane' ) );
		}

		do_action( 'zaplane/inbox/handed_to_human', self::payload( $conversation, [ 'reason' => $reason ] ) );
	}

	public static function set_ai( Conversation $conversation, bool $enabled ): void {
		$conversation->ai_enabled = $enabled;
		$conversation->handler    = $enabled ? 'bot' : 'human';
		$conversation->save();
	}

	public static function set_handler( Conversation $conversation, string $handler ): void {
		if ( ! in_array( $handler, self::HANDLERS, true ) ) {
			return;
		}
		$conversation->handler    = $handler;
		$conversation->ai_enabled = 'bot' === $handler;
		$conversation->save();
	}

	public static function assign( Conversation $conversation, int $user_id ): void {
		$conversation->assignee_id = $user_id > 0 ? $user_id : 0;
		$conversation->save();
		do_action( 'zaplane/inbox/conversation_assigned', self::payload( $conversation ) );
	}

	public static function set_status( Conversation $conversation, string $status ): void {
		if ( ! in_array( $status, self::STATUSES, true ) || $status === $conversation->status ) {
			return;
		}
		$conversation->status    = $status;
		$conversation->closed_at = 'closed' === $status ? self::now() : null;
		$conversation->save();

		if ( 'closed' === $status ) {
			do_action( 'zaplane/inbox/conversation_closed', self::payload( $conversation ) );
		}
	}

	public static function mark_read( Conversation $conversation ): void {
		if ( $conversation->unread_count > 0 ) {
			$conversation->unread_count = 0;
			$conversation->save();
		}
	}

	/**
	 * @param array<int,string> $tags
	 */
	public static function add_tags( Conversation $conversation, array $tags ): void {
		foreach ( self::clean_tags( $tags ) as $tag ) {
			$exists = Tag::where( 'conversation_id', $conversation->id )->where( 'tag', $tag )->fresh()->first();
			if ( ! $exists ) {
				Tag::create( [
					'conversation_id' => $conversation->id,
					'tag'             => $tag,
				] );
			}
		}
	}

	/**
	 * Replace the conversation's tags with exactly this set.
	 *
	 * @param array<int,string> $tags
	 */
	public static function set_tags( Conversation $conversation, array $tags ): void {
		$want = self::clean_tags( $tags );
		foreach ( self::tags( $conversation->id ) as $have ) {
			if ( ! in_array( $have, $want, true ) ) {
				Tag::where( 'conversation_id', $conversation->id )->where( 'tag', $have )->delete();
			}
		}
		self::add_tags( $conversation, $want );
	}

	/**
	 * @return array<int,string>
	 */
	public static function tags( int $conversation_id ): array {
		return array_values( array_map(
			'strval',
			Tag::where( 'conversation_id', $conversation_id )->fresh()->pluck( 'tag' )->toArray()
		) );
	}

	/**
	 * @param array<int,mixed> $tags
	 * @return array<int,string>
	 */
	private static function clean_tags( array $tags ): array {
		$out = [];
		foreach ( $tags as $tag ) {
			$tag = trim( sanitize_text_field( (string) $tag ) );
			if ( '' !== $tag ) {
				$out[] = mb_substr( $tag, 0, 100 );
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * A line in the thread that only the team sees and nothing delivers.
	 */
	public static function system_note( Conversation $conversation, string $text ): Message {
		return Message::create( [
			'conversation_id' => $conversation->id,
			'direction'       => 'out',
			'sender_type'     => 'system',
			'body'            => $text,
			'is_note'         => true,
			'channel'         => $conversation->channel,
			'delivery_status' => 'sent',
			'created_at'      => self::now(),
		] );
	}

	/**
	 * The flat shape workflow triggers receive.
	 *
	 * @param array<string,mixed> $extra
	 * @return array<string,mixed>
	 */
	public static function payload( Conversation $conversation, array $extra = [] ): array {
		$contact = Contact::where( 'id', (int) $conversation->contact_id )->fresh()->first();

		return array_merge( [
			'conversation_id' => (int) $conversation->id,
			'channel'         => (string) $conversation->channel,
			'status'          => (string) $conversation->status,
			'handler'         => (string) $conversation->handler,
			'assignee_id'     => (int) $conversation->assignee_id,
			'contact_id'      => (int) $conversation->contact_id,
			'contact_name'    => $contact ? (string) $contact->name : '',
			'contact_email'   => $contact ? (string) $contact->email : '',
			'contact_phone'   => $contact ? (string) $contact->phone : '',
		], $extra );
	}
}
