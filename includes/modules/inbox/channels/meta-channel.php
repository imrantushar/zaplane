<?php

namespace Zaplane\Modules\Inbox\Channels;

use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Framework\Classes\MetaGraph;
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Identity;
use Zaplane\Modules\Inbox\Models\Message;
use Zaplane\Modules\Inbox\Services\Connectors;
use Zaplane\Modules\Inbox\Services\Sources;
use Zaplane\Modules\Inbox\Settings as InboxSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What Messenger and WhatsApp share: a Zaplane connection for credentials,
 * the integration's signed webhook, and Meta's messaging windows.
 */
abstract class MetaChannel implements ChannelInterface {

	/** Replies after the customer's last message are free for this long. */
	protected const WINDOW_HOURS = 24;

	/** @var array<string,array<string,mixed>> */
	private static array $credentials = [];

	/**
	 * The workflow integration that owns the webhook and connection type.
	 *
	 * @return class-string<\Zaplane\Framework\Classes\IntegrationBase>
	 */
	abstract protected static function integration(): string;

	/**
	 * Turn one verified delivery into inbox messages.
	 *
	 * @return int How many messages were stored.
	 */
	abstract public static function ingest( \WP_REST_Request $request ): int;

	/**
	 * @return array{enabled:bool,connection_id:int}
	 */
	public static function settings(): array {
		$all = InboxSettings::get()['channels'];
		return $all[ static::slug() ] ?? [
			'enabled'       => false,
			'connection_id' => 0,
		];
	}

	/**
	 * On while a workflow feeds this channel into the inbox ("Add Event to
	 * Inbox", set up by its recipe). Pausing that workflow turns it off.
	 */
	public static function enabled(): bool {
		return Connectors::receives( static::slug() );
	}

	/**
	 * Why replies can't go out on this channel at all (no workflow sends
	 * them), or null. Checked before the channel's own rules.
	 *
	 * @return array{ok:bool,reason:string}|null
	 */
	protected static function not_set_up(): ?array {
		if ( Connectors::delivers( static::slug() ) ) {
			return null;
		}
		return [
			'ok'     => false,
			'reason' => sprintf(
				/* translators: %s: channel name, e.g. Messenger. */
				__( 'No active workflow sends Inbox replies on %s. Set it up (or turn it back on) in Inbox → Settings → Channels.', 'zaplane' ),
				static::label()
			),
		];
	}

	/**
	 * Send one stored reply: the channel's "Send Inbox Reply" workflow does
	 * it (see deliver()), so pausing that workflow stops replies too.
	 */
	public static function send( Conversation $conversation, ?Identity $identity, Message $message ): array {
		if ( ! Connectors::delivers( static::slug() ) ) {
			return [
				'status' => 'failed',
				'error'  => sprintf(
					/* translators: %s: channel name, e.g. Messenger. */
					__( 'No active workflow sends Inbox replies on %s. Set it up (or turn it back on) in Inbox → Settings → Channels.', 'zaplane' ),
					static::label()
				),
			];
		}

		/** This action is documented in channels/source-channel.php */
		do_action( 'zaplane/inbox/reply_requested', Sources::reply_payload( $conversation, $identity, $message ) );

		// The workflow reports back: sent (with the provider's id) or failed.
		return [ 'status' => 'queued' ];
	}

	/**
	 * Deliver a stored reply through the provider now. Called by the
	 * channel's "Send Inbox Reply" workflow step.
	 *
	 * @return array{status:string,external_id?:string,error?:string}
	 */
	abstract public static function deliver( Conversation $conversation, ?Identity $identity, Message $message ): array;

	/**
	 * The "Add Event to Inbox" workflow step: store what one webhook delivery
	 * holds (messages, taps, pictures, replies sent from the app…).
	 *
	 * @param array<string,mixed> $credentials The step's connection, if any.
	 * @return array{ok:bool,stored?:int,error?:string}
	 */
	public static function receive_from_workflow( string $body, string $signature, array $credentials = [] ): array {
		$request = new \WP_REST_Request( 'POST', '/' );
		$request->set_body( $body );
		$request->set_header( 'x_hub_signature_256', $signature );

		// Only deliveries Meta signed: see verified().
		if ( ! static::verified( $request ) ) {
			return [
				'ok'    => false,
				'error' => sprintf(
					/* translators: %s: channel name. */
					__( 'The delivery is not signed by Meta. Add your App Secret to the %s webhook settings (Inbox → Settings → Channels).', 'zaplane' ),
					static::label()
				),
			];
		}

		if ( $credentials ) {
			static::use_credentials( $credentials );
		}
		return [
			'ok'     => true,
			'stored' => static::ingest( $request ),
		];
	}

	/**
	 * The "Send Inbox Reply" workflow step: deliver one queued reply with
	 * the step's connection, and record how it went.
	 *
	 * @param array<string,mixed> $credentials
	 * @return array{ok:bool,status:string,external_id?:string,error?:string}
	 */
	public static function send_from_workflow( int $message_id, array $credentials ): array {
		$message = Message::where( 'id', $message_id )->where( 'direction', 'out' )->fresh()->first();
		if ( ! $message || static::slug() !== $message->channel ) {
			return [
				'ok'     => false,
				'status' => 'failed',
				'error'  => __( 'Inbox message not found for this channel.', 'zaplane' ),
			];
		}
		$conversation = \Zaplane\Modules\Inbox\Services\Conversations::find( (int) $message->conversation_id );
		$identity     = $conversation && $conversation->identity_id ? Identity::where( 'id', (int) $conversation->identity_id )->fresh()->first() : null;

		static::use_credentials( $credentials );
		$check = $conversation ? static::can_send( $conversation ) : [ 'ok' => false, 'reason' => __( 'Conversation not found.', 'zaplane' ) ];
		if ( empty( $check['ok'] ) ) {
			$result = [
				'status' => 'failed',
				'error'  => (string) ( $check['reason'] ?? '' ),
			];
		} else {
			try {
				$result = static::deliver( $conversation, $identity, $message );
			} catch ( \Throwable $e ) {
				$result = [
					'status' => 'failed',
					'error'  => $e->getMessage(),
				];
			}
		}

		\Zaplane\Modules\Inbox\Services\Outbound::record( $message, $result );
		return [
			'ok'          => 'sent' === ( $result['status'] ?? '' ),
			'status'      => (string) $message->delivery_status,
			'external_id' => (string) $message->external_id,
			'error'       => (string) $message->error,
		];
	}

	/**
	 * Use a workflow step's connection for the rest of this request (the
	 * step's own credentials, not whichever the inbox would look up).
	 *
	 * @param array<string,mixed> $credentials
	 */
	public static function use_credentials( array $credentials ): void {
		self::$credentials[ static::slug() ] = $credentials;
	}

	/**
	 * Only deliveries Meta signed reach the inbox. An integration without an
	 * App Secret accepts unsigned deliveries for its workflow trigger; the
	 * inbox does not, or anyone could open conversations and spend AI replies.
	 */
	public static function verified( \WP_REST_Request $request ): bool {
		$integration = static::integration();
		if ( '' === $integration::get_webhook_app_secret() ) {
			return false;
		}
		return $integration::verify_webhook_signature( $request );
	}

	/**
	 * @return array<string,mixed>
	 */
	protected static function credentials(): array {
		$slug = static::slug();
		if ( ! isset( self::$credentials[ $slug ] ) ) {
			try {
				// The connection linked in the channel's workflows.
				$id                         = Connectors::connection_id( $slug );
				self::$credentials[ $slug ] = $id ? ( new ConnectionManager() )->get_execution_credentials( $id ) : [];
			} catch ( \Throwable $e ) {
				self::$credentials[ $slug ] = [];
			}
		}
		return self::$credentials[ $slug ];
	}

	/** For tests: forget cached credentials. */
	public static function reset(): void {
		self::$credentials = [];
		Connectors::reset();
	}

	protected static function hours_since_customer( Conversation $conversation ): ?float {
		if ( empty( $conversation->last_customer_at ) ) {
			return null;
		}
		$then = strtotime( get_gmt_from_date( (string) $conversation->last_customer_at ) . ' UTC' );
		return $then ? ( time() - $then ) / HOUR_IN_SECONDS : null;
	}

	/**
	 * Show up to four questions a customer can tap to start a conversation
	 * (Messenger Ice Breakers, WhatsApp conversation starters). An empty
	 * list removes them.
	 *
	 * @param array<int,string> $questions
	 * @return array{ok:bool,error:string}
	 */
	public static function set_starters( array $questions ): array {
		return [
			'ok'    => false,
			'error' => __( 'This channel has no conversation starters.', 'zaplane' ),
		];
	}

	/**
	 * POST to the Graph API.
	 *
	 * @param array<string,mixed> $body
	 * @param array<string,string> $headers
	 * @return array{ok:bool,data:array<string,mixed>,error:string}
	 */
	protected static function graph_post( string $path, array $body, array $headers, ?string $version ): array {
		return self::graph_request( 'POST', $path, $body, $headers, $version );
	}

	/**
	 * @param array<string,mixed> $body
	 * @param array<string,string> $headers
	 * @return array{ok:bool,data:array<string,mixed>,error:string}
	 */
	protected static function graph_request( string $method, string $path, array $body, array $headers, ?string $version ): array {
		$response = wp_remote_request( MetaGraph::url( $path, $version ), [
			'method'  => $method,
			'headers' => array_merge( [ 'Content-Type' => 'application/json' ], $headers ),
			'body'    => wp_json_encode( $body ),
			'timeout' => 20,
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'ok'    => false,
				'data'  => [],
				'error' => $response->get_error_message(),
			];
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = is_array( $data ) ? $data : [];

		if ( isset( $data['error'] ) ) {
			return [
				'ok'    => false,
				'data'  => $data,
				'error' => (string) ( $data['error']['message'] ?? __( 'Unknown error from Meta.', 'zaplane' ) ),
			];
		}

		return [
			'ok'    => true,
			'data'  => $data,
			'error' => '',
		];
	}
}
