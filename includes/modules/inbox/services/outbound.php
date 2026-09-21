<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Modules\Inbox\Channels\Registry;
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Identity;
use Zaplane\Modules\Inbox\Models\Message;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every reply and note leaves through here — from a person in the inbox, the
 * assistant, or a workflow — so storing, delivering and the takeover rule
 * happen the same way for each.
 */
class Outbound {

	public const SENDERS = [ 'agent', 'ai', 'workflow', 'system' ];

	/**
	 * @param array{
	 *   sender_type?:string,
	 *   sender_id?:int,
	 *   is_note?:bool,
	 *   attachments?:array<int,mixed>,
	 *   meta?:array<string,mixed>
	 * } $opts
	 */
	public static function send( Conversation $conversation, string $body, array $opts = [] ): Message {
		$sender_type = in_array( $opts['sender_type'] ?? '', self::SENDERS, true ) ? $opts['sender_type'] : 'agent';
		$sender_id   = (int) ( $opts['sender_id'] ?? 0 );
		$is_note     = ! empty( $opts['is_note'] );
		$attachments = is_array( $opts['attachments'] ?? null ) ? $opts['attachments'] : [];
		$now         = Conversations::now();

		// A person replying is a takeover: the assistant stays quiet in this
		// conversation from now on. A private note is not a reply to the
		// customer, so it changes nothing.
		if ( 'agent' === $sender_type && ! $is_note ) {
			if ( $conversation->ai_enabled || 'bot' === $conversation->handler ) {
				Conversations::hand_to_human( $conversation, '', false );
			}
			if ( $sender_id > 0 && ! (int) $conversation->assignee_id ) {
				$conversation->assignee_id = $sender_id;
			}
		}

		$message = Message::create( [
			'conversation_id' => $conversation->id,
			'direction'       => 'out',
			'sender_type'     => $sender_type,
			'sender_id'       => $sender_id,
			'body'            => $body,
			'attachments'     => $attachments,
			'is_note'         => $is_note,
			'is_ai_generated' => 'ai' === $sender_type,
			'channel'         => $conversation->channel,
			'delivery_status' => $is_note ? 'sent' : 'queued',
			'meta'            => (array) ( $opts['meta'] ?? [] ),
			'created_at'      => $now,
		] );

		if ( $is_note ) {
			return $message;
		}

		self::deliver( $conversation, $message );

		$conversation->last_message_preview = Ingest::preview( $body, $attachments );
		$conversation->last_message_at      = $now;
		if ( 'agent' === $sender_type ) {
			$conversation->unread_count = 0;
		}
		$conversation->save();

		do_action( 'zaplane/inbox/message_sent', Conversations::payload( $conversation, [
			'message_id'  => (int) $message->id,
			'text'        => $body,
			'sender_type' => $sender_type,
			'status'      => (string) $message->delivery_status,
		] ) );

		return $message;
	}

	private static function deliver( Conversation $conversation, Message $message ): void {
		$channel = Registry::get( (string) $conversation->channel );

		if ( ! $channel ) {
			self::fail( $message, __( 'This channel is not available.', 'zaplane' ) );
			return;
		}

		$check = $channel::can_send( $conversation );
		if ( empty( $check['ok'] ) ) {
			self::fail( $message, (string) ( $check['reason'] ?? __( 'The channel refused this reply.', 'zaplane' ) ) );
			return;
		}

		$identity = $conversation->identity_id
			? Identity::where( 'id', (int) $conversation->identity_id )->fresh()->first()
			: null;

		try {
			$result = $channel::send( $conversation, $identity, $message );
		} catch ( \Throwable $e ) {
			$result = [
				'status' => 'failed',
				'error'  => $e->getMessage(),
			];
		}

		$message->delivery_status = 'sent' === ( $result['status'] ?? '' ) ? 'sent' : 'failed';
		if ( ! empty( $result['external_id'] ) ) {
			$message->external_id = (string) $result['external_id'];
		}
		if ( ! empty( $result['error'] ) ) {
			$message->error = (string) $result['error'];
		}
		$message->save();
	}

	private static function fail( Message $message, string $error ): void {
		$message->delivery_status = 'failed';
		$message->error           = $error;
		$message->save();
	}
}
