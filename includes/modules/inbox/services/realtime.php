<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Identity;
use Zaplane\Socket\Client as Socket;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells the chat, the moment it happens, that there is something new.
 *
 * Only a nudge travels: a conversation id and the id of the newest message.
 * Both ends then read it back the way they always have, so what a visitor or
 * an agent may see is decided in one place and not here.
 *
 * With no server running, every push says so and nothing else changes — the
 * chat keeps polling, a few seconds behind.
 */
class Realtime {

	public static function register(): void {
		add_action( 'zaplane/inbox/message_sent', [ self::class, 'on_message' ] );
		add_action( 'zaplane/inbox/message_received', [ self::class, 'on_message' ] );
		// Someone came online, went away, or an identity changed: the header
		// says so without waiting for the next poll.
		add_action( 'zaplane/inbox/team_changed', [ self::class, 'on_team' ] );
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	public static function on_message( $payload ): void {
		if ( ! is_array( $payload ) || ! Socket::is_live() ) {
			return;
		}

		$conversation_id = (int) ( $payload['conversation_id'] ?? 0 );
		if ( $conversation_id <= 0 ) {
			return;
		}

		$data = [
			'conversation_id' => $conversation_id,
			'message_id'      => (int) ( $payload['message_id'] ?? 0 ),
			'channel'         => (string) ( $payload['channel'] ?? '' ),
			'sender_type'     => (string) ( $payload['sender_type'] ?? '' ),
		];

		$visitor = self::visitor_of( $conversation_id );
		if ( '' !== $visitor ) {
			Socket::push( $visitor, 'message', $data );
			return;
		}

		// Not a website chat (Messenger, a comment…): only the team is listening.
		Socket::push( 'agents', 'message', $data );
	}

	public static function on_team(): void {
		if ( Socket::is_live() ) {
			Socket::push( 'agents', 'team', [] );
		}
	}

	/**
	 * The visitor a website conversation belongs to, which is also the channel
	 * their browser listens on. Empty for every other channel.
	 */
	private static function visitor_of( int $conversation_id ): string {
		$conversation = Conversation::where( 'id', $conversation_id )->fresh()->first();
		if ( ! $conversation || 'web' !== (string) $conversation->channel ) {
			return '';
		}

		$identity = Identity::where( 'id', (int) $conversation->identity_id )->fresh()->first();

		return $identity ? (string) $identity->external_id : '';
	}
}
