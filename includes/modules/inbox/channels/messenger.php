<?php

namespace Zaplane\Modules\Inbox\Channels;

use Zaplane\Framework\Classes\MetaGraph;
use Zaplane\Modules\Inbox\Commerce\Commerce;
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Identity;
use Zaplane\Modules\Inbox\Models\Message;
use Zaplane\Modules\Inbox\Services\Ingest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Facebook Page messages. Uses the Messenger connection's Page access token
 * and the Messenger integration's webhook.
 */
class Messenger extends MetaChannel {

	/**
	 * Messenger allows a person (not an automated reply) to answer up to seven
	 * days after the customer's last message, with the HUMAN_AGENT tag.
	 */
	private const HUMAN_AGENT_HOURS = 168;

	/** Marks our own sends so their echoes are not recorded twice. */
	public const ECHO_MARK = 'zaplane_inbox';

	public static function slug(): string {
		return 'messenger';
	}

	public static function label(): string {
		return __( 'Messenger', 'zaplane' );
	}

	protected static function integration(): string {
		return \Zaplane\Integrations\Messenger::class;
	}

	public static function ingest( \WP_REST_Request $request ): int {
		$data = json_decode( $request->get_body(), true );
		if ( ! is_array( $data ) || 'page' !== ( $data['object'] ?? 'page' ) ) {
			return 0;
		}

		$stored = 0;
		foreach ( (array) ( $data['entry'] ?? [] ) as $entry ) {
			$page_id = (string) ( $entry['id'] ?? '' );
			foreach ( (array) ( $entry['messaging'] ?? [] ) as $event ) {
				if ( is_array( $event ) && self::ingest_event( $page_id, $event ) ) {
					$stored++;
				}
			}
		}
		return $stored;
	}

	/**
	 * @param array<string,mixed> $event
	 */
	private static function ingest_event( string $page_id, array $event ): bool {
		$sender    = (string) ( $event['sender']['id'] ?? '' );
		$recipient = (string) ( $event['recipient']['id'] ?? '' );
		$message   = is_array( $event['message'] ?? null ) ? $event['message'] : null;

		if ( '' === $page_id ) {
			$page_id = ! empty( $message['is_echo'] ) ? $sender : $recipient;
		}

		// A reply the Page sent. Ours carry our mark; anything else was typed
		// by someone in the Page's own inbox.
		if ( $message && ! empty( $message['is_echo'] ) ) {
			if ( self::ECHO_MARK === ( $message['metadata'] ?? '' ) ) {
				return false;
			}
			return (bool) Ingest::external_reply(
				'messenger',
				$page_id,
				$recipient,
				(string) ( $message['mid'] ?? '' ),
				(string) ( $message['text'] ?? '' ),
				self::attachments( $message )
			);
		}

		$body       = '';
		$message_id = '';
		$attach     = [];
		if ( $message ) {
			$body = (string) ( $message['text'] ?? '' );
			// A tapped quick reply carries its full text (the title may be cut).
			$qr = (string) ( $message['quick_reply']['payload'] ?? '' );
			if ( 0 === strpos( $qr, 'ZAPLANE_QR:' ) ) {
				$body = substr( $qr, strlen( 'ZAPLANE_QR:' ) );
			}
			$message_id = (string) ( $message['mid'] ?? '' );
			$attach     = self::attachments( $message );
		} elseif ( is_array( $event['postback'] ?? null ) ) {
			// A tapped button: the title is what the customer "said".
			$body       = (string) ( $event['postback']['title'] ?? $event['postback']['payload'] ?? '' );
			$message_id = (string) ( $event['postback']['mid'] ?? ( 'pb_' . md5( $sender . '|' . ( $event['timestamp'] ?? '' ) ) ) );
		} else {
			return false;
		}

		$contact = Ingest::knows( 'messenger', $page_id, $sender ) ? [] : self::profile( $sender );

		return (bool) Ingest::inbound( [
			'channel'     => 'messenger',
			'account_id'  => $page_id,
			'external_id' => $sender,
			'message_id'  => $message_id,
			'body'        => $body,
			'attachments' => $attach,
			'contact'     => $contact,
		] );
	}

	/**
	 * @param array<string,mixed> $message
	 * @return array<int,array<string,string>>
	 */
	private static function attachments( array $message ): array {
		$out = [];
		foreach ( (array) ( $message['attachments'] ?? [] ) as $a ) {
			if ( ! is_array( $a ) ) {
				continue;
			}
			$out[] = [
				'type' => sanitize_key( (string) ( $a['type'] ?? 'file' ) ),
				'url'  => esc_url_raw( (string) ( $a['payload']['url'] ?? '' ) ),
			];
		}
		return $out;
	}

	/**
	 * Name and picture of a new sender. Best effort: without the permission,
	 * or on any error, the contact simply has no name yet.
	 *
	 * @return array<string,string>
	 */
	private static function profile( string $psid ): array {
		$credentials = self::credentials();
		$token       = (string) ( $credentials['page_access_token'] ?? '' );
		if ( '' === $token || '' === $psid ) {
			return [];
		}

		$response = wp_remote_get(
			MetaGraph::url( rawurlencode( $psid ), $credentials['api_version'] ?? null ) . '?fields=first_name,last_name,profile_pic',
			[
				'timeout' => 5,
				'headers' => [ 'Authorization' => 'Bearer ' . $token ],
			]
		);
		if ( is_wp_error( $response ) ) {
			return [];
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || isset( $data['error'] ) ) {
			return [];
		}

		return array_filter( [
			'name'       => trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) ),
			'avatar_url' => (string) ( $data['profile_pic'] ?? '' ),
		] );
	}

	public static function can_send( Conversation $conversation ): array {
		if ( empty( self::credentials()['page_access_token'] ) ) {
			return [
				'ok'     => false,
				'reason' => __( 'Messenger is not connected. Choose a Messenger connection in Inbox settings.', 'zaplane' ),
			];
		}

		$hours = self::hours_since_customer( $conversation );
		if ( null === $hours || $hours > self::HUMAN_AGENT_HOURS ) {
			return [
				'ok'     => false,
				'reason' => __( 'Messenger only allows replies within 7 days of the customer\'s last message.', 'zaplane' ),
			];
		}

		return [ 'ok' => true ];
	}

	/**
	 * Messenger Ice Breakers: shown to people opening a new conversation with
	 * the Page. Tapping one arrives as a postback whose title is the question.
	 *
	 * @param array<int,string> $questions
	 * @return array{ok:bool,error:string}
	 */
	public static function set_starters( array $questions ): array {
		$credentials = self::credentials();
		$token       = (string) ( $credentials['page_access_token'] ?? '' );
		if ( '' === $token ) {
			return [
				'ok'    => false,
				'error' => __( 'Choose a Messenger connection first.', 'zaplane' ),
			];
		}
		$headers = [ 'Authorization' => 'Bearer ' . $token ];
		$version = $credentials['api_version'] ?? null;

		$result = empty( $questions )
			? self::graph_request( 'DELETE', 'me/messenger_profile', [ 'fields' => [ 'ice_breakers' ] ], $headers, $version )
			: self::graph_post( 'me/messenger_profile', [
				'ice_breakers' => [
					[
						'locale'          => 'default',
						'call_to_actions' => array_map( static function ( $question, $i ) {
							return [
								'question' => (string) $question,
								'payload'  => 'ZAPLANE_STARTER_' . $i,
							];
						}, array_values( $questions ), array_keys( array_values( $questions ) ) ),
					],
				],
			], $headers, $version );

		return [
			'ok'    => $result['ok'],
			'error' => $result['error'],
		];
	}

	public static function send( Conversation $conversation, ?Identity $identity, Message $message ): array {
		if ( ! $identity ) {
			return [
				'status' => 'failed',
				'error'  => __( 'This conversation has no Messenger recipient.', 'zaplane' ),
			];
		}

		$credentials = self::credentials();
		$hours       = (float) self::hours_since_customer( $conversation );

		$payload = [
			'recipient' => [ 'id' => (string) $identity->external_id ],
			'message'   => [
				'text'     => (string) $message->body,
				'metadata' => self::ECHO_MARK,
			],
		];

		// Tappable answers under the message ("Did this answer your question?").
		// Tapping one sends its title back as an ordinary message.
		$buttons = array_slice( array_filter( (array) ( $message->meta['quick_replies'] ?? [] ) ), 0, 13 );
		if ( $buttons ) {
			$payload['message']['quick_replies'] = array_map( static function ( $title, $i ) {
				return [
					'content_type' => 'text',
					'title'        => mb_substr( (string) $title, 0, 20 ),
					// The full text rides in the payload: titles are cut at 20.
					'payload'      => 'ZAPLANE_QR:' . mb_substr( (string) $title, 0, 900 ),
				];
			}, array_values( $buttons ), array_keys( array_values( $buttons ) ) );
		}

		// Shown as a quoted reply in Messenger when we know the original's id.
		$quote = is_array( $message->meta['reply_to'] ?? null ) ? $message->meta['reply_to'] : [];
		if ( ! empty( $quote['external_id'] ) ) {
			$payload['message']['reply_to'] = [ 'mid' => (string) $quote['external_id'] ];
		}

		// A product goes as a card: picture, name, price and a "View product"
		// button. Messenger can't put text and a card in one message, so
		// any note goes just before it.
		$card = Commerce::card_of( $message );
		$note = '';
		if ( $card ) {
			$note                             = trim( (string) ( $message->meta['product_note'] ?? '' ) );
			$payload['message']['attachment'] = self::product_template( $card );
			unset( $payload['message']['text'] );
		}

		if ( $hours <= self::WINDOW_HOURS ) {
			$payload['messaging_type'] = 'RESPONSE';
		} elseif ( 'agent' === $message->sender_type ) {
			$payload['messaging_type'] = 'MESSAGE_TAG';
			$payload['tag']            = 'HUMAN_AGENT';
		} else {
			return [
				'status' => 'failed',
				'error'  => __( 'More than 24 hours since the customer\'s last message: only a person can reply now.', 'zaplane' ),
			];
		}

		$headers = [ 'Authorization' => 'Bearer ' . (string) ( $credentials['page_access_token'] ?? '' ) ];
		if ( '' !== $note ) {
			$first = $payload;
			unset( $first['message']['attachment'], $first['message']['quick_replies'] );
			$first['message']['text'] = $note;
			self::graph_post( 'me/messages', $first, $headers, $credentials['api_version'] ?? null );
			unset( $payload['message']['reply_to'] );
		}

		$result = self::graph_post( 'me/messages', $payload, $headers, $credentials['api_version'] ?? null );
		if ( ! $result['ok'] && $card ) {
			// A card Messenger won't take (a picture it can't fetch…): send the plain text.
			unset( $payload['message']['attachment'] );
			$payload['message']['text'] = Commerce::product_line( $card + [ 'id' => $card['product_id'] ] );
			$result                     = self::graph_post( 'me/messages', $payload, $headers, $credentials['api_version'] ?? null );
		}

		return $result['ok']
			? [
				'status'      => 'sent',
				'external_id' => (string) ( $result['data']['message_id'] ?? '' ),
			]
			: [
				'status' => 'failed',
				'error'  => $result['error'],
			];
	}

	/**
	 * Messenger's generic template for one product card.
	 *
	 * @param array<string,mixed> $card Commerce::card().
	 * @return array<string,mixed>
	 */
	public static function product_template( array $card ): array {
		$url     = (string) ( $card['url'] ?? '' );
		$element = array_filter( [
			'title'     => mb_substr( (string) $card['name'], 0, 80 ),
			'subtitle'  => mb_substr( Commerce::card_subtitle( $card ), 0, 80 ),
			'image_url' => (string) ( $card['image'] ?? '' ),
		] );
		if ( '' !== $url ) {
			$element['default_action'] = [
				'type' => 'web_url',
				'url'  => $url,
			];
			$element['buttons']        = [
				[
					'type'  => 'web_url',
					'url'   => $url,
					'title' => __( 'View product', 'zaplane' ),
				],
			];
		}

		return [
			'type'    => 'template',
			'payload' => [
				'template_type'      => 'generic',
				'image_aspect_ratio' => 'square',
				'elements'           => [ $element ],
			],
		];
	}
}
