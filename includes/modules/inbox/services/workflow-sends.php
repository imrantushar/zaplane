<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Framework\Exceptions\DatabaseException;
use Zaplane\Models\Run;
use Zaplane\Models\Workflow;
use Zaplane\Models\WorkflowVersion;
use Zaplane\Modules\Inbox\Channels\MetaChannel;
use Zaplane\Modules\Inbox\Channels\Registry;
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Identity;
use Zaplane\Modules\Inbox\Models\Message;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Messages a workflow sends straight through Messenger or WhatsApp (not the
 * Inbox's own "Send Reply"), to a customer the Inbox already has.
 *
 * - One answerer per conversation: unless the conversation belongs to
 *   workflows, a workflow's reply is held rather than sent, so the customer
 *   never gets two answers. The held text is kept as a private note the team
 *   can send with one click.
 * - Whatever a workflow does send is recorded in the conversation, labelled
 *   with the workflow, without counting as a person taking over.
 *
 * Listens to {@see \Zaplane\Framework\Classes\ChannelSend}.
 */
class WorkflowSends {

	public static function register(): void {
		add_filter( 'zaplane/channel_send/check', [ self::class, 'check' ], 10, 2 );
		add_action( 'zaplane/channel_send/held', [ self::class, 'held' ], 10, 2 );
		add_action( 'zaplane/channel_send/sent', [ self::class, 'record' ], 10, 1 );
		add_filter( 'zaplane/trigger/should_start', [ self::class, 'should_start' ], 10, 3 );
		add_filter( 'zaplane/memory/inbox_history', [ self::class, 'memory_history' ], 10, 3 );
	}

	/**
	 * A Memory step reading "the Inbox conversation": the real thread as chat
	 * turns. Private notes, system lines and deleted messages are left out.
	 *
	 * The customer's newest message is left out when nothing has answered it
	 * yet: that is the message the workflow is answering, and it arrives as
	 * the agent's task.
	 *
	 * @param array<int,array{role:string,content:string}>|null $history
	 * @return array<int,array{role:string,content:string}>|null
	 */
	public static function memory_history( $history, string $key, int $limit ) {
		if ( is_array( $history ) || false === strpos( $key, ':' ) ) {
			return $history;
		}

		list( $channel, $customer ) = array_map( 'trim', explode( ':', $key, 2 ) );
		if ( 'whatsapp' === $channel ) {
			$customer = (string) preg_replace( '/\D+/', '', $customer );
		}

		$conversation = self::conversation( [
			'channel'   => $channel,
			'recipient' => $customer,
		] );
		if ( ! $conversation ) {
			return null;
		}

		$rows = Message::where( 'conversation_id', (int) $conversation->id )
			->where( 'is_note', 0 )
			->where( 'sender_type', '!=', 'system' )
			->orderBy( 'id', 'desc' )
			// Room for the pending message and deleted rows, which are dropped
			// below; the result is trimmed to $limit afterwards.
			->limit( max( 1, $limit ) * 2 + 10 )
			->fresh()
			->get()
			->all();

		if ( $rows && 'contact' === $rows[0]->sender_type ) {
			array_shift( $rows );
		}

		$turns = [];
		foreach ( array_reverse( $rows ) as $row ) {
			$meta = is_array( $row->meta ) ? $row->meta : [];
			$text = '';
			if ( empty( $meta['deleted_at'] ) ) {
				$text = trim( wp_strip_all_tags( (string) $row->body ) );
				if ( '' === $text && ! empty( $row->attachments ) ) {
					$text = Ingest::preview( '', (array) $row->attachments );
				}
			}
			if ( '' === trim( $text ) ) {
				continue;
			}
			$turns[] = [
				'role'    => 'contact' === $row->sender_type ? 'user' : 'assistant',
				'content' => $text,
			];
		}

		return array_slice( $turns, -max( 1, $limit ) );
	}

	/** Channel => [ trigger payload key for the customer, key for the business account ]. */
	private const TRIGGER_KEYS = [
		'messenger' => [ 'sender_id', '' ],
		'whatsapp'  => [ 'from', 'phone_number_id' ],
	];

	/**
	 * Don't start a workflow that replies to customers for a message in a
	 * conversation someone else is answering. Holding its reply later would
	 * still spend the AI call, and would leave that unsent reply in the
	 * workflow's Memory. Workflows that don't reply on the channel (alerts,
	 * logging) always run.
	 *
	 * @param array<string,mixed> $trigger
	 * @param array<string,mixed> $payload
	 */
	public static function should_start( bool $start, array $trigger, array $payload ): bool {
		$data    = (array) ( $trigger['graph_node']['data'] ?? [] );
		$channel = (string) ( $data['app'] ?? '' );
		if ( ! $start || 'message_received' !== ( $data['event'] ?? '' ) || ! isset( self::TRIGGER_KEYS[ $channel ] ) ) {
			return $start;
		}

		list( $customer_key, $account_key ) = self::TRIGGER_KEYS[ $channel ];
		$conversation = self::conversation( [
			'channel'    => $channel,
			'recipient'  => preg_replace( '/\s+/', '', (string) ( $payload[ $customer_key ] ?? '' ) ),
			'account_id' => '' !== $account_key ? (string) ( $payload[ $account_key ] ?? '' ) : '',
		] );
		if ( ! $conversation || 'closed' === $conversation->status || 'workflow' === $conversation->handler ) {
			return true;
		}

		$version = WorkflowVersion::find( (int) ( $trigger['workflow_version_id'] ?? 0 ) );
		$graph   = $version && is_array( $version->graph_json ) ? $version->graph_json : [];

		return ! self::replies_on( $graph, $channel );
	}

	/**
	 * Whether a workflow graph has a step that replies on this channel and
	 * waits its turn in the Inbox (the default for send steps).
	 *
	 * @param array<string,mixed> $graph
	 */
	private static function replies_on( array $graph, string $channel ): bool {
		foreach ( (array) ( $graph['nodes'] ?? [] ) as $node ) {
			$data  = (array) ( $node['data'] ?? [] );
			$event = (string) ( $data['event'] ?? '' );
			if (
				$channel === ( $data['app'] ?? '' )
				&& 0 === strpos( $event, 'send_' )
				&& 'send_template' !== $event
				&& 'always' !== ( $data['config']['inbox_mode'] ?? '' )
			) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $context
	 */
	public static function check( string $reason, array $context ): string {
		if ( '' !== $reason || 'always' === ( $context['mode'] ?? '' ) ) {
			return $reason;
		}
		// A person running one step by hand from the builder meant to send it.
		if ( (int) ( $context['run_id'] ?? 0 ) <= 0 ) {
			return '';
		}

		$conversation = self::conversation( $context );
		if ( ! $conversation || 'closed' === $conversation->status || 'workflow' === $conversation->handler ) {
			return '';
		}

		return 'bot' === $conversation->handler
			? __( 'Held: the Inbox AI assistant is answering this customer. Saved as a private note in the Inbox. To let workflows answer, set "Who answers" for this channel to Workflows in Inbox settings.', 'zaplane' )
			: __( 'Held: someone on your team is handling this customer in the Inbox. Saved as a private note there.', 'zaplane' );
	}

	/**
	 * @param array<string,mixed> $context
	 */
	public static function held( array $context, string $reason ): void {
		$conversation = self::conversation( $context );
		if ( ! $conversation || '' === trim( (string) ( $context['body'] ?? '' ) ) ) {
			return;
		}

		Outbound::send( $conversation, (string) $context['body'], [
			'sender_type' => 'workflow',
			'is_note'     => true,
			'meta'        => self::source( $context ) + [
				'held'   => true,
				'reason' => $reason,
			],
		] );
	}

	/**
	 * @param array<string,mixed> $context
	 */
	public static function record( array $context ): void {
		$conversation = self::conversation( $context );
		if ( ! $conversation ) {
			return;
		}

		$body        = (string) ( $context['body'] ?? '' );
		$attachments = is_array( $context['attachments'] ?? null ) ? $context['attachments'] : [];
		$message_id  = (string) ( $context['message_id'] ?? '' );
		$now         = Conversations::now();

		try {
			Message::create( [
				'conversation_id' => (int) $conversation->id,
				'direction'       => 'out',
				'sender_type'     => 'workflow',
				'sender_id'       => 0,
				'body'            => $body,
				'attachments'     => $attachments,
				'channel'         => (string) $conversation->channel,
				'external_id'     => '' !== $message_id ? $message_id : null,
				'delivery_status' => 'sent',
				'meta'            => self::source( $context ),
				'created_at'      => $now,
			] );
		} catch ( DatabaseException $e ) {
			// Already recorded (Meta's echo of this message got here first).
			return;
		}

		// Unlike a reply typed in the channel's own app, this isn't a person
		// taking over: the assistant (if it's answering) stays on.
		$conversation->last_message_preview = Ingest::preview( $body, $attachments );
		$conversation->last_message_at      = $now;
		$conversation->save();
	}

	/**
	 * Active workflows that reply on a channel with its own send step, and so
	 * wait their turn while the assistant or the team answers. Shown in Inbox
	 * settings so nobody wonders why a workflow went quiet.
	 *
	 * @return array<int,array{id:int,name:string}>
	 */
	public static function senders( string $channel ): array {
		$out = [];
		foreach ( Workflow::where( 'status', 'active' )->fresh()->get()->all() as $workflow ) {
			$version = WorkflowVersion::where( 'workflow_id', (int) $workflow->id )->where( 'is_active', 1 )->fresh()->first();
			$graph   = $version && is_array( $version->graph_json ) ? $version->graph_json : [];
			if ( self::replies_on( $graph, $channel ) ) {
				$out[] = [
					'id'   => (int) $workflow->id,
					'name' => (string) $workflow->title,
				];
			}
		}
		return $out;
	}

	/**
	 * The Inbox conversation a workflow is messaging, if the Inbox receives
	 * that channel and already knows the customer.
	 *
	 * @param array<string,mixed> $context
	 */
	private static function conversation( array $context ): ?Conversation {
		$channel   = (string) ( $context['channel'] ?? '' );
		$recipient = (string) ( $context['recipient'] ?? '' );
		$class     = Registry::get( $channel );
		if ( '' === $recipient || ! $class || ! is_subclass_of( $class, MetaChannel::class ) || ! $class::enabled() ) {
			return null;
		}

		// A Messenger step doesn't know the Page id, but a PSID is already
		// specific to one Page; WhatsApp numbers are matched on the sending number.
		$query = Identity::where( 'channel', $channel )->where( 'external_id', $recipient );
		if ( '' !== (string) ( $context['account_id'] ?? '' ) ) {
			$query = $query->where( 'account_id', (string) $context['account_id'] );
		}
		$identity = $query->fresh()->first();
		if ( ! $identity ) {
			return null;
		}

		return Conversation::where( 'identity_id', (int) $identity->id )->orderBy( 'id', 'desc' )->fresh()->first();
	}

	/**
	 * Which workflow sent it, for the label in the thread.
	 *
	 * @param array<string,mixed> $context
	 * @return array<string,mixed>
	 */
	private static function source( array $context ): array {
		$out = [
			'source' => 'workflow',
			'run_id' => (int) ( $context['run_id'] ?? 0 ),
			'step'   => (string) ( $context['step'] ?? '' ),
		];

		$run = $out['run_id'] > 0 ? Run::find( $out['run_id'] ) : null;
		if ( ! $run ) {
			return $out;
		}

		$workflow = Workflow::find( (int) $run->workflow_id );
		if ( $workflow ) {
			$out['workflow_id']   = (int) $workflow->id;
			$out['workflow_name'] = (string) $workflow->title;
		}

		// A workflow with an AI step most likely wrote this reply with AI.
		$version = WorkflowVersion::find( (int) $run->workflow_version_id );
		$graph   = $version && is_array( $version->graph_json ) ? $version->graph_json : [];
		foreach ( (array) ( $graph['nodes'] ?? [] ) as $node ) {
			if ( in_array( $node['data']['app'] ?? '', [ 'ai-agent', 'ai' ], true ) ) {
				$out['by_ai'] = true;
				break;
			}
		}

		return $out;
	}
}
