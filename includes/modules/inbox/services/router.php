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
 * `bot` queues the assistant. `human` and `workflow` do nothing here: a
 * person answers from the inbox, and a workflow answers from its own
 * `message_received` trigger, which fires for every message regardless.
 */
class Router {

	public const AI_HOOK  = 'zaplane_inbox_ai_reply';
	public const AS_GROUP = 'zaplane-inbox';

	public static function route( Conversation $conversation, Message $message ): void {
		if ( 'bot' !== $conversation->handler || ! $conversation->ai_enabled ) {
			return;
		}

		$args = [ (int) $conversation->id, (int) $message->id ];

		// The reply is generated off the request so the customer's message is
		// accepted at once and a slow model never holds a PHP worker.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::AI_HOOK, $args, self::AS_GROUP );
			return;
		}

		wp_schedule_single_event( time(), self::AI_HOOK, $args );
	}
}
