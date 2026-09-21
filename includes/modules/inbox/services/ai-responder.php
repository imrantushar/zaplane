<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Modules\Inbox\Commerce\Commerce;
use Zaplane\Modules\Inbox\Models\Contact;
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Message;
use Zaplane\Modules\Inbox\Settings as InboxSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The assistant's reply to a customer message.
 *
 * Runs as a background job queued by Router. Most of this class is deciding
 * whether to answer at all: a job can run late, run twice, or run after a
 * person has already stepped in, and in each of those cases the right number
 * of replies is zero.
 */
class AiResponder {

	/** How long a claim on a conversation holds before another job may take it. */
	private const LOCK_TTL = 120;

	/** Messages of context sent with each request. */
	private const HISTORY_LIMIT = 20;

	/**
	 * Conversations whose handover the assistant itself requested during this
	 * request. Its closing line ("a teammate will reply") still goes out; any
	 * other handover means a person is answering and the reply is dropped.
	 *
	 * @var array<int,bool>
	 */
	private static array $self_handed = [];

	/** The conversation a reply is being generated for right now, or 0. */
	private static int $current = 0;

	/**
	 * Record a handover made from inside the assistant's own run. A handover
	 * from anywhere else — a workflow in the same batch of jobs — is not ours.
	 */
	public static function note_handover( int $conversation_id ): void {
		if ( self::$current === $conversation_id ) {
			self::$self_handed[ $conversation_id ] = true;
		}
	}

	public static function handle( int $conversation_id, int $message_id ): void {
		$ai = InboxSettings::get()['ai'];
		if ( empty( $ai['enabled'] ) || empty( $ai['connection_id'] ) ) {
			return;
		}

		$conversation = Conversations::find( $conversation_id );
		if ( ! $conversation || ! self::should_answer( $conversation, $message_id ) ) {
			return;
		}

		if ( ! self::claim( $conversation_id ) ) {
			return;
		}

		self::$current = $conversation_id;
		try {
			self::reply( $conversation, $message_id, $ai );
		} finally {
			self::$current = 0;
			delete_option( self::lock_key( $conversation_id ) );
			unset( self::$self_handed[ $conversation_id ] );
		}
	}

	/**
	 * @param array<string,mixed> $ai
	 */
	private static function reply( Conversation $conversation, int $message_id, array $ai ): void {
		try {
			$credentials = ( new ConnectionManager() )->get_execution_credentials( (int) $ai['connection_id'] );
		} catch ( \Throwable $e ) {
			Conversations::hand_to_human( $conversation, __( 'the AI connection is not available', 'zaplane' ) );
			return;
		}

		[ $history, $task ] = self::transcript( $conversation, $message_id );
		if ( '' === trim( $task ) ) {
			return;
		}

		$node = [
			'data'                    => [
				'config' => [
					'system_prompt' => self::system_prompt( $conversation, $ai ),
					'task'          => $task,
					'max_steps'     => (int) $ai['max_steps'] + ( self::selling( $ai ) ? 2 : 0 ),
					'business_key'  => (string) $ai['business_key'],
				],
			],
			'_connection_credentials' => $credentials,
			'_sub_nodes'              => [ 'ai_tool' => self::tools( $conversation, $ai ) ],
			'_history'                => $history,
		];

		/**
		 * Filter the agent node before the assistant runs — add tools, change
		 * the prompt, or swap the task.
		 *
		 * @param array<string,mixed> $node
		 * @param Conversation        $conversation
		 */
		$node = (array) apply_filters( 'zaplane/inbox/ai_node', $node, $conversation );

		// Decided before the run: a product card the tools send counts as an
		// assistant message and would otherwise swallow the introduction.
		$first = self::is_first_reply( (int) $conversation->id );

		$result = \Zaplane\Integrations\Aiagent::execute_node( $node, [] );
		$data   = (array) ( $result['data'] ?? [] );

		// Whatever happened while the model was thinking decides what we do now.
		$fresh = Conversations::find( (int) $conversation->id );
		if ( ! $fresh ) {
			return;
		}
		$handed_by_us = ! empty( self::$self_handed[ (int) $fresh->id ] );
		if ( ! $handed_by_us && ( ! $fresh->ai_enabled || 'bot' !== $fresh->handler ) ) {
			return;
		}
		if ( self::newer_customer_message( (int) $fresh->id, $message_id ) ) {
			// That message has its own job, which will answer with this one in view.
			return;
		}

		if ( empty( $data['success'] ) ) {
			$error = (string) ( $data['error'] ?? __( 'no reply from the model', 'zaplane' ) );
			Conversations::hand_to_human( $fresh, sprintf(
				/* translators: %s: the provider's error message. */
				__( 'the assistant could not answer (%s)', 'zaplane' ),
				mb_substr( $error, 0, 200 )
			) );
			return;
		}

		$reply = trim( (string) ( $data['reply'] ?? '' ) );
		if ( '' === $reply ) {
			return;
		}

		if ( $first ) {
			$reply = self::intro_line( $ai ) . ' ' . $reply;
		}

		Outbound::send( $fresh, $reply, [
			'sender_type' => 'ai',
			'meta'        => [
				'usage'      => $data['usage'] ?? [],
				'cost_usd'   => $data['cost_usd'] ?? 0,
				'tool_calls' => is_array( $data['tool_calls'] ?? null ) ? count( $data['tool_calls'] ) : 0,
			],
		] );
	}

	/**
	 * The gates every job passes before it may spend a model call.
	 */
	public static function should_answer( Conversation $conversation, int $message_id ): bool {
		if ( ! $conversation->ai_enabled || 'bot' !== $conversation->handler || 'closed' === $conversation->status ) {
			return false;
		}

		$message = Message::where( 'id', $message_id )->fresh()->first();
		if ( ! $message || (int) $message->conversation_id !== (int) $conversation->id || 'contact' !== $message->sender_type ) {
			return false;
		}

		// Only the newest customer message gets an answer; the ones before it are
		// part of what it answers.
		if ( self::newer_customer_message( (int) $conversation->id, $message_id ) ) {
			return false;
		}

		// Someone already answered it. A workflow's message here is a
		// notification (order shipped…) sent while the assistant owns the
		// conversation; it doesn't answer what the customer asked.
		$answered = Message::where( 'conversation_id', (int) $conversation->id )
			->where( 'id', '>', $message_id )
			->where( 'direction', 'out' )
			->where( 'is_note', 0 )
			->where( 'sender_type', '!=', 'workflow' )
			->fresh()
			->first();

		return ! $answered;
	}

	private static function newer_customer_message( int $conversation_id, int $message_id ): bool {
		return (bool) Message::where( 'conversation_id', $conversation_id )
			->where( 'id', '>', $message_id )
			->where( 'sender_type', 'contact' )
			->fresh()
			->first();
	}

	/**
	 * One job per conversation at a time. add_option() is atomic on the unique
	 * option name, which a transient is not; a claim older than LOCK_TTL is
	 * from a job that died and may be taken over.
	 */
	private static function claim( int $conversation_id ): bool {
		$key = self::lock_key( $conversation_id );
		if ( add_option( $key, time(), '', false ) ) {
			return true;
		}

		$held_since = (int) get_option( $key, 0 );
		if ( $held_since > 0 && time() - $held_since > self::LOCK_TTL ) {
			update_option( $key, time(), false );
			return true;
		}

		return false;
	}

	private static function lock_key( int $conversation_id ): string {
		return 'zaplane_inbox_ai_lock_' . $conversation_id;
	}

	/**
	 * Split the thread into prior turns and the task. The task is every
	 * customer message since the last reply, so three quick messages get one
	 * answer that covers all three.
	 *
	 * @return array{0:array<int,array{role:string,content:string}>,1:string}
	 */
	private static function transcript( Conversation $conversation, int $message_id ): array {
		$rows = Message::where( 'conversation_id', (int) $conversation->id )
			->where( 'id', '<=', $message_id )
			->where( 'is_note', 0 )
			->orderBy( 'id', 'desc' )
			->limit( self::HISTORY_LIMIT + 10 )
			->fresh()
			->get()
			->all();
		$rows = array_reverse( $rows );

		$pending = [];
		$history = [];
		foreach ( $rows as $row ) {
			$text = self::message_text( $row );
			if ( '' === $text ) {
				continue;
			}
			if ( 'contact' === $row->sender_type ) {
				$pending[] = $text;
				continue;
			}
			foreach ( $pending as $p ) {
				$history[] = [
					'role'    => 'user',
					'content' => $p,
				];
			}
			$pending   = [];
			$history[] = [
				'role'    => 'assistant',
				'content' => $text,
			];
		}

		return [ array_slice( $history, -self::HISTORY_LIMIT ), implode( "\n", $pending ) ];
	}

	private static function message_text( Message $message ): string {
		$text = trim( wp_strip_all_tags( (string) $message->body ) );
		if ( ! empty( $message->attachments ) ) {
			$text = trim( $text . ' ' . __( '[sent an attachment]', 'zaplane' ) );
		}
		return $text;
	}

	private static function is_first_reply( int $conversation_id ): bool {
		return ! Message::where( 'conversation_id', $conversation_id )
			->where( 'is_ai_generated', 1 )
			->fresh()
			->first();
	}

	/**
	 * Composed here rather than left to the model, so it is said exactly once
	 * and always says what the customer is talking to.
	 *
	 * @param array<string,mixed> $ai
	 */
	private static function intro_line( array $ai ): string {
		$name     = '' !== trim( (string) $ai['agent_name'] ) ? (string) $ai['agent_name'] : 'Ava';
		$business = self::business_name( $ai );

		/* translators: 1: assistant name, 2: business name. */
		return sprintf( __( "Hi, I'm %1\$s, the virtual assistant for %2\$s.", 'zaplane' ), $name, $business );
	}

	/**
	 * @param array<string,mixed> $ai
	 */
	private static function business_name( array $ai ): string {
		$name = trim( (string) $ai['business_name'] );
		return '' !== $name ? $name : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * @param array<string,mixed> $ai
	 */
	private static function system_prompt( Conversation $conversation, array $ai ): string {
		$name     = '' !== trim( (string) $ai['agent_name'] ) ? (string) $ai['agent_name'] : 'Ava';
		$business = self::business_name( $ai );

		$known   = [];
		$contact = Contact::where( 'id', (int) $conversation->contact_id )->fresh()->first();
		if ( $contact ) {
			foreach ( [ 'name', 'email', 'phone' ] as $field ) {
				if ( ! empty( $contact->{$field} ) ) {
					$known[] = $field . ': ' . $contact->{$field};
				}
			}
		}

		$lines = [
			"You are {$name}, part of the team at {$business}, answering customers in a chat. Speak as a member of the team (\"we\", \"our\").",
			'Keep replies short: one to four sentences, plain text, no markdown. Reply in the language the customer writes in.',
			'Before answering anything about products, prices, stock, shipping or policies, use search_knowledge. Answer only from what it returns or from this conversation. If you cannot find the answer, say so plainly and offer to connect the customer with the team.',
			'Never say you did something you cannot do — checked an order, changed an account, issued a refund, placed a booking. If the customer needs that, call tool_forward_to_human.',
			'Call tool_forward_to_human when the customer asks for a person, is upset, or needs something only the team can do. After calling it, tell the customer briefly that a teammate will reply here.',
			'If the customer asks whether you are an AI, say yes.',
			'Do not greet or introduce yourself; the greeting is added for you. Do not call yourself an assistant or a bot.',
		];

		if ( self::selling( $ai ) ) {
			$lines[] = 'You can help customers buy. Use tool_search_products to find products (it returns ids, prices and stock), and tool_send_product to show one as a card. Only mention prices and stock that the tool returned.';
			if ( ! empty( $ai['can_order'] ) ) {
				$lines[] = 'You can place cash-on-delivery orders with tool_create_order. Before calling it you must have the product ids and quantities, the customer\'s name, phone number and full delivery address, and you must first write back the complete order (items, quantities, total if known, address, phone) and receive a clear yes from the customer in their latest message. Never place an order without that confirmation. After it succeeds, give the customer the order number.';
			} else {
				$lines[] = 'You cannot place orders. When the customer wants to order, collect their name, phone number and delivery address, then call tool_forward_to_human so a teammate can confirm the order.';
			}
		}

		if ( ! empty( $known ) ) {
			$lines[] = 'Details the customer already gave (do not ask for them again): ' . implode( '; ', $known ) . '.';
		}

		$extra = trim( (string) $ai['instructions'] );
		if ( '' !== $extra ) {
			$lines[] = "Instructions from {$business}:\n" . $extra;
		}

		return implode( "\n\n", $lines );
	}

	/**
	 * Tool nodes offered to the agent. The conversation id is locked, so the
	 * model can only act on the conversation it is answering.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function tools( Conversation $conversation, array $ai ): array {
		$tool = static function ( string $event, string $description ) use ( $conversation ) {
			return [
				'data' => [
					'app'         => 'inbox',
					'event'       => $event,
					'name'        => $event,
					'description' => $description,
					'config'      => [
						'conversation_id' => (int) $conversation->id,
						'_by_ai'          => true,
					],
					'locked'      => [ 'conversation_id', '_by_ai' ],
				],
			];
		};

		$tools = [ $tool( 'forward_to_human', 'Hand this conversation to a human teammate. Give a short reason.' ) ];

		if ( self::selling( $ai ) ) {
			$tools[] = $tool( 'search_products', 'Search the shop. Returns matching products with id, name, price, stock and link.' );
			$tools[] = $tool( 'send_product', 'Show the customer one product as a card with its picture, price and link. Pass the product id from search results.' );
			if ( ! empty( $ai['can_order'] ) ) {
				$tools[] = $tool( 'create_order', 'Place a cash-on-delivery order, only after the customer confirmed the full order summary. items is "productId:quantity" pairs separated by commas.' );
			}
		}

		return $tools;
	}

	/**
	 * @param array<string,mixed> $ai
	 */
	private static function selling( array $ai ): bool {
		return ! empty( $ai['sell'] ) && null !== Commerce::store();
	}
}
