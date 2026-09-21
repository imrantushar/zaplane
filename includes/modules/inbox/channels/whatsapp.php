<?php

namespace Zaplane\Modules\Inbox\Channels;

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

		// Shown as a quoted reply in WhatsApp when we know the original's id.
		$quote = is_array( $message->meta['reply_to'] ?? null ) ? $message->meta['reply_to'] : [];
		if ( ! empty( $quote['external_id'] ) ) {
			$payload['context'] = [ 'message_id' => (string) $quote['external_id'] ];
		}

		$result = self::graph_post(
			(string) $credentials['phone_number_id'] . '/messages',
			$payload,
			[ 'Authorization' => 'Bearer ' . (string) $credentials['access_token'] ],
			$credentials['api_version'] ?? null
		);

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
}
