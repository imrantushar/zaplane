<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Message;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides who answers a new customer message.
 *
 * Business Knowledge answers first when automatic answers are on. `bot`
 * queues the assistant. `human` and `workflow` do nothing here: a
 * person answers from the inbox, and a workflow answers from its own
 * `message_received` trigger, which fires for every message regardless.
 */
class Router {

	public const AI_HOOK  = 'zaplane_inbox_ai_reply';
	public const AS_GROUP = 'zaplane-inbox';

	public static function route( Conversation $conversation, Message $message ): void {
		// Business Knowledge goes first when it may answer (or when the
		// customer is replying to "Did this answer your question?"); that job
		// hands over to the assistant itself when it has nothing to send.
		if ( KnowledgeAnswer::applies( $conversation ) || self::awaiting_feedback( $conversation ) ) {
			self::queue( KnowledgeAnswer::HOOK, $conversation, $message );
			return;
		}

		if ( 'bot' !== $conversation->handler || ! $conversation->ai_enabled ) {
			return;
		}

		self::queue( self::AI_HOOK, $conversation, $message );
	}

	private static function awaiting_feedback( Conversation $conversation ): bool {
		$meta = is_array( $conversation->meta ) ? $conversation->meta : [];
		return ! empty( $meta['kb_pending'] );
	}

	/**
	 * Replies are made off the request, so the customer's message is accepted
	 * at once and a slow lookup or model never holds a PHP worker.
	 */
	private static function queue( string $hook, Conversation $conversation, Message $message ): void {
		$args = [ (int) $conversation->id, (int) $message->id ];

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( $hook, $args, self::AS_GROUP );
			return;
		}

		wp_schedule_single_event( time(), $hook, $args );
	}
}
