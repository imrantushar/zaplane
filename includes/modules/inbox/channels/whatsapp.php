<?php

namespace Zaplane\Modules\Inbox\Channels;

use Zaplane\Modules\Inbox\Commerce\Commerce;
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Identity;
use Zaplane\Modules\Inbox\Models\Message;
use Zaplane\Modules\Inbox\Services\Ingest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WhatsApp Business (Cloud API) messages. Uses the WhatsApp connection's
 * access token and phone number, and the WhatsApp integration's webhook.
 */
class Whatsapp extends MetaChannel {

	private const MEDIA_TYPES = [ 'image', 'video', 'audio', 'document', 'sticker' ];

	public static function slug(): string {
		return 'whatsapp';
	}

	public static function label(): string {
		return __( 'WhatsApp', 'zaplane' );
	}

	protected static function integration(): string {
		return \Zaplane\Integrations\Whatsapp::class;
	}

	public static function ingest( \WP_REST_Request $request ): int {
		$data = json_decode( $request->get_body(), true );
		if ( ! is_array( $data ) ) {
			return 0;
		}

		$own_number = (string) ( self::credentials()['phone_number_id'] ?? '' );
		$stored     = 0;

		foreach ( (array) ( $data['entry'] ?? [] ) as $entry ) {
			foreach ( (array) ( $entry['changes'] ?? [] ) as $change ) {
				$value     = (array) ( $change['value'] ?? [] );
				$number_id = (string) ( $value['metadata']['phone_number_id'] ?? '' );

				// Another number on the same app belongs to someone else's setup.
				if ( '' !== $own_number && '' !== $number_id && $own_number !== $number_id ) {
					continue;
				}

				$names = [];
				foreach ( (array) ( $value['contacts'] ?? [] ) as $c ) {
					$names[ (string) ( $c['wa_id'] ?? '' ) ] = (string) ( $c['profile']['name'] ?? '' );
				}

				foreach ( (array) ( $value['messages'] ?? [] ) as $message ) {
					if ( is_array( $message ) && self::ingest_message( $number_id, $message, $names ) ) {
						$stored++;
					}
				}

				foreach ( (array) ( $value['statuses'] ?? [] ) as $status ) {
					if ( is_array( $status ) ) {
						self::apply_status( $status );
					}
				}
			}
		}

		return $stored;
	}

	/**
	 * @param array<string,mixed>  $message
	 * @param array<string,string> $names   Profile names keyed by WhatsApp id.
	 */
	private static function ingest_message( string $number_id, array $message, array $names ): bool {
		$from = (string) ( $message['from'] ?? '' );
		$type = (string) ( $message['type'] ?? '' );

		$attachments = [];
		if ( in_array( $type, self::MEDIA_TYPES, true ) && is_array( $message[ $type ] ?? null ) ) {
			$media         = $message[ $type ];
			$attachments[] = array_filter( [
				'type'      => 'sticker' === $type ? 'image' : $type,
				'media_id'  => (string) ( $media['id'] ?? '' ),
				'mime_type' => (string) ( $media['mime_type'] ?? '' ),
				'filename'  => (string) ( $media['filename'] ?? '' ),
			] );
		}

		$body = \Zaplane\Integrations\Whatsapp::message_text( $message );
		$picked = self::picked_option( $number_id, $from, $message );
		if ( null !== $picked ) {
			$body = $picked;
		}
		if ( 'location' === $type ) {
			$loc  = (array) ( $message['location'] ?? [] );
			$body = trim( ( $loc['name'] ?? '' ) . ' ' . ( $loc['address'] ?? '' ) . ' ' . sprintf( '(%s, %s)', $loc['latitude'] ?? '', $loc['longitude'] ?? '' ) );
		}

		return (bool) Ingest::inbound( [
			'channel'     => 'whatsapp',
			'account_id'  => $number_id,
			'external_id' => $from,
			'message_id'  => (string) ( $message['id'] ?? '' ),
			'body'        => $body,
			'attachments' => $attachments,
			'contact'     => [
				'name'  => $names[ $from ] ?? '',
				'phone' => '' !== $from ? '+' . ltrim( $from, '+' ) : '',
			],
		] );
	}

	/**
	 * Delivery receipts for messages we sent.
	 *
	 * @param array<string,mixed> $status
	 */
	private static function apply_status( array $status ): void {
		$map   = [
			'sent'      => 'sent',
			'delivered' => 'delivered',
			'read'      => 'read',
			'failed'    => 'failed',
		];
		$state = $map[ (string) ( $status['status'] ?? '' ) ] ?? '';
		$id    = (string) ( $status['id'] ?? '' );
		if ( '' === $state || '' === $id ) {
			return;
		}

		$message = Message::where( 'channel', 'whatsapp' )->where( 'external_id', $id )->fresh()->first();
		if ( ! $message ) {
			return;
		}

		// Receipts can arrive out of order; never step back from read.
		$rank = [
			'queued'    => 0,
			'sent'      => 1,
			'delivered' => 2,
			'read'      => 3,
		];
		if ( 'failed' !== $state && ( $rank[ $state ] ?? 0 ) <= ( $rank[ (string) $message->delivery_status ] ?? 0 ) ) {
			return;
		}

		$message->delivery_status = $state;
		if ( 'failed' === $state ) {
			$message->error = (string) ( $status['errors'][0]['title'] ?? $status['errors'][0]['message'] ?? __( 'WhatsApp could not deliver this message.', 'zaplane' ) );
		}
		$message->save();
	}

	public static function can_send( Conversation $conversation ): array {
		$credentials = self::credentials();
		if ( empty( $credentials['access_token'] ) || empty( $credentials['phone_number_id'] ) ) {
			return [
				'ok'     => false,
				'reason' => __( 'WhatsApp is not connected. Choose a WhatsApp connection in Inbox settings.', 'zaplane' ),
			];
		}

		$hours = self::hours_since_customer( $conversation );
		if ( null === $hours || $hours > self::WINDOW_HOURS ) {
			return [
				'ok'     => false,
				'reason' => __( 'WhatsApp only allows free-form replies within 24 hours of the customer\'s last message. Send an approved template from a workflow instead.', 'zaplane' ),
			];
		}

		return [ 'ok' => true ];
	}

	/**
	 * The full label of a tapped button or list row we sent (ids "zqr_N" are
	 * indexes into that message's options); null for anything else.
	 *
	 * @param array<string,mixed> $message
	 */
	private static function picked_option( string $number_id, string $from, array $message ): ?string {
		$reply = (array) ( $message['interactive']['list_reply'] ?? $message['interactive']['button_reply'] ?? [] );
		if ( ! preg_match( '/^zqr_(\d+)$/', (string) ( $reply['id'] ?? '' ), $m ) ) {
			return null;
		}
		$identity = Identity::where( 'channel', 'whatsapp' )->where( 'account_id', $number_id )->where( 'external_id', $from )->fresh()->first();
		if ( ! $identity ) {
			return null;
		}
		$conversation = Conversation::where( 'identity_id', (int) $identity->id )->orderBy( 'id', 'desc' )->fresh()->first();
		if ( ! $conversation ) {
			return null;
		}
		foreach ( Message::where( 'conversation_id', (int) $conversation->id )->where( 'direction', 'out' )->orderBy( 'id', 'desc' )->limit( 5 )->fresh()->get()->all() as $sent ) {
			$options = (array) ( is_array( $sent->meta ) ? ( $sent->meta['quick_replies'] ?? [] ) : [] );
			if ( $options ) {
				return isset( $options[ (int) $m[1] ] ) ? (string) $options[ (int) $m[1] ] : null;
			}
		}
		return null;
	}

	/**
	 * WhatsApp conversation starters ("ice breakers"): shown when a customer
	 * opens a chat with the business number for the first time. Tapping one
	 * sends it as an ordinary text message.
	 *
	 * @param array<int,string> $questions
	 * @return array{ok:bool,error:string}
	 */
	public static function set_starters( array $questions ): array {
		$credentials = self::credentials();
		if ( empty( $credentials['access_token'] ) || empty( $credentials['phone_number_id'] ) ) {
			return [
				'ok'    => false,
				'error' => __( 'Choose a WhatsApp connection first.', 'zaplane' ),
			];
		}

		$result = self::graph_post(
			(string) $credentials['phone_number_id'] . '/conversational_automation',
			[ 'prompts' => array_values( $questions ) ],
			[ 'Authorization' => 'Bearer ' . (string) $credentials['access_token'] ],
			$credentials['api_version'] ?? null
		);

		return [
			'ok'    => $result['ok'],
			'error' => $result['error'],
		];
	}

	public static function send( Conversation $conversation, ?Identity $identity, Message $message ): array {
		if ( ! $identity ) {
			return [
				'status' => 'failed',
				'error'  => __( 'This conversation has no WhatsApp number.', 'zaplane' ),
			];
		}

		$credentials = self::credentials();
		$payload     = [
			'messaging_product' => 'whatsapp',
			'recipient_type'    => 'individual',
			'to'                => (string) $identity->external_id,
			'type'              => 'text',
			'text'              => [
				'body'        => (string) $message->body,
				'preview_url' => true,
			],
		];

		// Tappable options. Up to three short ones are reply buttons; more, or
		// longer ones (full questions), a list menu of up to ten rows. Either
		// way a tap comes back with our row id, which ingest maps to the full
		// label (WhatsApp only returns a shortened title).
		$options = array_values( array_filter( array_map( 'strval', (array) ( $message->meta['quick_replies'] ?? [] ) ) ) );
		$body    = (string) $message->body;
		if ( $options && mb_strlen( $body ) <= 1024 ) {
			$short = count( $options ) <= 3 && ! array_filter( $options, static fn( $o ) => mb_strlen( $o ) > 20 );
			unset( $payload['text'] );
			$payload['type'] = 'interactive';

			if ( $short ) {
				$payload['interactive'] = [
					'type'   => 'button',
					'body'   => [ 'text' => $body ],
					'action' => [
						'buttons' => array_map( static fn( $o, $i ) => [
							'type'  => 'reply',
							'reply' => [
								'id'    => 'zqr_' . $i,
								'title' => $o,
							],
						], $options, array_keys( $options ) ),
					],
				];
			} else {
				$payload['interactive'] = [
					'type'   => 'list',
					'body'   => [ 'text' => $body ],
					'action' => [
						'button'   => __( 'Choose', 'zaplane' ),
						'sections' => [
							[
								'title' => mb_substr( __( 'Options', 'zaplane' ), 0, 24 ),
								'rows'  => array_map( static fn( $o, $i ) => array_filter( [
									'id'          => 'zqr_' . $i,
									'title'       => mb_strlen( $o ) > 24 ? rtrim( mb_substr( $o, 0, 23 ) ) . '…' : $o,
									'description' => mb_strlen( $o ) > 24 ? mb_substr( $o, 0, 72 ) : '',
								] ), array_slice( $options, 0, 10 ), array_keys( array_slice( $options, 0, 10 ) ) ),
							],
						],
					],
				];
			}
		}

		// A product goes as a card: its picture on top, name, price and a
		// "View product" button.
		$card = empty( $payload['interactive'] ) ? Commerce::card_of( $message ) : null;
		if ( $card ) {
			unset( $payload['text'] );
			$payload = array_merge( $payload, self::product_message( $card, (string) ( $message->meta['product_note'] ?? '' ) ) );
		}

		// Shown as a quoted reply in WhatsApp when we know the original's id.
		$quote = is_array( $message->meta['reply_to'] ?? null ) ? $message->meta['reply_to'] : [];
		if ( ! empty( $quote['external_id'] ) ) {
			$payload['context'] = [ 'message_id' => (string) $quote['external_id'] ];
		}

		$send   = static fn( array $p ) => self::graph_post(
			(string) $credentials['phone_number_id'] . '/messages',
			$p,
			[ 'Authorization' => 'Bearer ' . (string) $credentials['access_token'] ],
			$credentials['api_version'] ?? null
		);
		$result = $send( $payload );
		if ( ! $result['ok'] && $card ) {
			// A card WhatsApp won't take (a picture it can't fetch…): the text with a link preview.
			$payload         = array_diff_key( $payload, [ 'interactive' => 1, 'image' => 1 ] );
			$payload['type'] = 'text';
			$payload['text'] = [
				'body'        => (string) $message->body,
				'preview_url' => true,
			];
			$result          = $send( $payload );
		}

		return $result['ok']
			? [
				'status'      => 'sent',
				'external_id' => (string) ( $result['data']['messages'][0]['id'] ?? '' ),
			]
			: [
				'status' => 'failed',
				'error'  => $result['error'],
			];
	}

	/**
	 * One product card as a WhatsApp message: the picture on top and a
	 * "View product" button when there is a link, else the picture with a
	 * caption, else plain text.
	 *
	 * @param array<string,mixed> $card Commerce::card().
	 * @return array<string,mixed> type + its payload.
	 */
	public static function product_message( array $card, string $note = '' ): array {
		$url   = (string) ( $card['url'] ?? '' );
		$image = (string) ( $card['image'] ?? '' );
		$text  = trim( trim( $note ) . "\n\n*" . $card['name'] . "*\n" . Commerce::card_subtitle( $card ) );

		if ( '' !== $url ) {
			return [
				'type'        => 'interactive',
				'interactive' => array_filter( [
					'type'   => 'cta_url',
					'header' => '' !== $image ? [
						'type'  => 'image',
						'image' => [ 'link' => $image ],
					] : null,
					'body'   => [ 'text' => mb_substr( $text, 0, 1024 ) ],
					'action' => [
						'name'       => 'cta_url',
						'parameters' => [
							'display_text' => mb_substr( __( 'View product', 'zaplane' ), 0, 20 ),
							'url'          => $url,
						],
					],
				] ),
			];
		}

		if ( '' !== $image ) {
			return [
				'type'  => 'image',
				'image' => [
					'link'    => $image,
					'caption' => mb_substr( $text, 0, 1024 ),
				],
			];
		}

		return [
			'type' => 'text',
			'text' => [
				'body'        => $text,
				'preview_url' => false,
			],
		];
	}
}
