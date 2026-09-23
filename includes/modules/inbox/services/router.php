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
		return ! empty( $meta['kb_pending'] ) || ! empty( $meta['kb_menu'] );
	}

	/** Jobs queued in this request, to run as soon as the customer has their response. */
	private static array $run_now = [];

	/**
	 * Replies are made off the request, so the customer's message is accepted
	 * at once and a slow lookup or model never holds a PHP worker.
	 *
	 * Action Scheduler alone only starts its queue promptly while someone is in
	 * wp-admin; otherwise a job waits for WP-Cron, a minute or more. So the job
	 * is also run straight after the response is sent (see run_now()). The
	 * queued copy stays as the fallback and is marked done when it runs here.
	 */
	private static function queue( string $hook, Conversation $conversation, Message $message ): void {
		$args = [ (int) $conversation->id, (int) $message->id ];

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			wp_schedule_single_event( time(), $hook, $args );
			return;
		}

		$id = (int) as_enqueue_async_action( $hook, $args, self::AS_GROUP );
		if ( $id > 0 ) {
			if ( ! self::$run_now ) {
				add_action( 'shutdown', [ self::class, 'run_now' ], 0 );
			}
			self::$run_now[] = $id;
		}
	}

	/**
	 * Finish the HTTP response, then run this request's inbox jobs. Where the
	 * response can't be finished early, ask Action Scheduler to start its
	 * queue in the background instead of making the customer wait.
	 */
	public static function run_now(): void {
		$ids          = self::$run_now;
		self::$run_now = [];
		if ( ! $ids || ! class_exists( '\ActionScheduler' ) ) {
			return;
		}

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		} else {
			if ( class_exists( '\ActionScheduler_AsyncRequest_QueueRunner' ) ) {
				( new \ActionScheduler_AsyncRequest_QueueRunner( \ActionScheduler::store() ) )->dispatch();
			}
			return;
		}

		ignore_user_abort( true );
		foreach ( $ids as $id ) {
			if ( \ActionScheduler_Store::STATUS_PENDING === \ActionScheduler::store()->get_status( $id ) ) {
				\ActionScheduler::runner()->process_action( $id, 'Zaplane Inbox' );
			}
		}
	}
}
