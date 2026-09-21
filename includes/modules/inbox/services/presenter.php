<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Modules\Inbox\Models\Contact;
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Message;
use Zaplane\Modules\Inbox\Settings as InboxSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The JSON shapes the inbox screen and the widget read. Times go out as UTC
 * ISO 8601 so the browser can show them in the viewer's own zone.
 */
class Presenter {

	/** @var array<int,array<string,mixed>> */
	private static array $users = [];

	public static function time( $mysql ): ?string {
		if ( empty( $mysql ) || '0000-00-00 00:00:00' === $mysql ) {
			return null;
		}
		return get_gmt_from_date( (string) $mysql, 'Y-m-d\TH:i:s\Z' );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function contact( ?Contact $contact ): array {
		if ( ! $contact ) {
			return [
				'id'   => 0,
				'name' => __( 'Visitor', 'zaplane' ),
			];
		}

		return [
			'id'         => (int) $contact->id,
			'name'       => '' !== (string) $contact->name ? (string) $contact->name : self::fallback_name( $contact ),
			'email'      => (string) $contact->email,
			'phone'      => (string) $contact->phone,
			'avatar_url' => (string) $contact->avatar_url,
			'wp_user_id' => (int) $contact->wp_user_id,
		];
	}

	private static function fallback_name( Contact $contact ): string {
		if ( '' !== (string) $contact->email ) {
			return (string) strstr( (string) $contact->email, '@', true );
		}
		/* translators: %d: contact id. */
		return sprintf( __( 'Visitor #%d', 'zaplane' ), (int) $contact->id );
	}

	/**
	 * @param array<int,Contact> $contacts Contacts keyed by id, preloaded by the caller.
	 * @param array<int,array<int,string>> $tags Tags keyed by conversation id.
	 * @return array<string,mixed>
	 */
	public static function conversation( Conversation $conversation, array $contacts = [], array $tags = [] ): array {
		$contact = $contacts[ (int) $conversation->contact_id ]
			?? Contact::where( 'id', (int) $conversation->contact_id )->fresh()->first();

		return [
			'id'                   => (int) $conversation->id,
			'channel'              => (string) $conversation->channel,
			'status'               => (string) $conversation->status,
			'handler'              => (string) $conversation->handler,
			'ai_enabled'           => (bool) $conversation->ai_enabled,
			'assignee'             => self::user( (int) $conversation->assignee_id ),
			'unread_count'         => (int) $conversation->unread_count,
			'last_message_preview' => (string) $conversation->last_message_preview,
			'last_message_at'      => self::time( $conversation->last_message_at ),
			'last_customer_at'     => self::time( $conversation->last_customer_at ),
			'created_at'           => self::time( $conversation->created_at ),
			'updated_at'           => self::time( $conversation->updated_at ),
			'contact'              => self::contact( $contact ),
			'tags'                 => $tags[ (int) $conversation->id ] ?? Conversations::tags( (int) $conversation->id ),
			'orders'               => array_values( (array) ( ( is_array( $conversation->meta ) ? $conversation->meta : [] )['orders'] ?? [] ) ),
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function message( Message $message, bool $for_visitor = false ): array {
		$out = [
			'id'          => (int) $message->id,
			'direction'   => (string) $message->direction,
			'sender_type' => (string) $message->sender_type,
			'sender_name' => self::sender_name( $message ),
			'body'        => (string) $message->body,
			'attachments' => is_array( $message->attachments ) ? $message->attachments : [],
			'created_at'  => self::time( $message->created_at ),
		];

		if ( $for_visitor ) {
			return $out;
		}

		return $out + [
			'sender_id'       => (int) $message->sender_id,
			'is_note'         => (bool) $message->is_note,
			'is_ai_generated' => (bool) $message->is_ai_generated,
			'delivery_status' => (string) $message->delivery_status,
			'error'           => (string) $message->error,
			'meta'            => is_array( $message->meta ) ? $message->meta : [],
		];
	}

	private static function sender_name( Message $message ): string {
		switch ( $message->sender_type ) {
			case 'ai':
				$name = (string) InboxSettings::get()['ai']['agent_name'];
				/* translators: %s: assistant name. The label keeps the AI disclosure visible on every reply. */
				return sprintf( __( '%s · AI assistant', 'zaplane' ), '' !== $name ? $name : 'Ava' );
			case 'agent':
				$user = self::user( (int) $message->sender_id );
				return $user ? (string) $user['name'] : __( 'Team', 'zaplane' );
			case 'workflow':
			case 'system':
				return __( 'Team', 'zaplane' );
		}
		return '';
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function user( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		if ( ! isset( self::$users[ $user_id ] ) ) {
			$user                   = get_userdata( $user_id );
			self::$users[ $user_id ] = $user ? [
				'id'         => $user_id,
				'name'       => (string) $user->display_name,
				'avatar_url' => (string) get_avatar_url( $user_id, [ 'size' => 64 ] ),
			] : [];
		}
		return self::$users[ $user_id ] ?: null;
	}
}
