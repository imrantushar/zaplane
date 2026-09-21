<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Framework\Exceptions\DatabaseException;
use Zaplane\Modules\Inbox\Models\Contact;
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Identity;
use Zaplane\Modules\Inbox\Models\Message;
use Zaplane\Modules\Inbox\Settings as InboxSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single way a customer message enters the inbox, whatever channel it
 * came from. Finds or creates the contact, finds or reopens the conversation,
 * stores the message once, then tells the router and any workflow.
 */
class Ingest {

	/**
	 * @param array{
	 *   channel:string,
	 *   external_id:string,
	 *   body?:string,
	 *   account_id?:string,
	 *   message_id?:string,
	 *   attachments?:array<int,mixed>,
	 *   contact?:array<string,mixed>,
	 *   meta?:array<string,mixed>
	 * } $in
	 * @return Message|null The stored message, or null when it was a duplicate
	 *                      or had nothing in it.
	 */
	public static function inbound( array $in ): ?Message {
		$channel     = sanitize_key( (string) ( $in['channel'] ?? '' ) );
		$external_id = (string) ( $in['external_id'] ?? '' );
		$account_id  = (string) ( $in['account_id'] ?? '' );
		$body        = (string) ( $in['body'] ?? '' );
		$attachments = is_array( $in['attachments'] ?? null ) ? $in['attachments'] : [];
		$message_id  = (string) ( $in['message_id'] ?? '' );

		if ( '' === $channel || '' === $external_id || ( '' === trim( $body ) && empty( $attachments ) ) ) {
			return null;
		}

		// A provider retry of a message we already have.
		if ( '' !== $message_id && Message::where( 'channel', $channel )->where( 'external_id', $message_id )->fresh()->first() ) {
			return null;
		}

		$identity     = self::identity( $channel, $account_id, $external_id, (array) ( $in['contact'] ?? [] ) );
		$conversation = self::conversation( $identity, $channel, $account_id, $is_new );
		$now          = Conversations::now();

		try {
			$message = Message::create( [
				'conversation_id' => $conversation->id,
				'direction'       => 'in',
				'sender_type'     => 'contact',
				'sender_id'       => (int) $identity->contact_id,
				'body'            => $body,
				'attachments'     => $attachments,
				'channel'         => $channel,
				'external_id'     => '' !== $message_id ? $message_id : null,
				'delivery_status' => 'received',
				'meta'            => (array) ( $in['meta'] ?? [] ),
				'created_at'      => $now,
			] );
		} catch ( DatabaseException $e ) {
			// Lost a race with a concurrent retry of the same message: the unique
			// key did its job.
			return null;
		}

		$conversation->last_message_preview = self::preview( $body, $attachments );
		$conversation->last_message_at      = $now;
		$conversation->last_customer_at     = $now;
		$conversation->unread_count         = (int) $conversation->unread_count + 1;
		if ( in_array( $conversation->status, [ 'pending', 'snoozed' ], true ) ) {
			$conversation->status = 'open';
		}
		$conversation->save();

		$payload = Conversations::payload( $conversation, [
			'message_id' => (int) $message->id,
			'text'       => $body,
			'sender_id'  => $external_id,
		] );

		if ( $is_new ) {
			do_action( 'zaplane/inbox/conversation_created', $payload );
		}

		Router::route( $conversation, $message );

		/**
		 * A customer message was stored. Workflows listen here.
		 *
		 * @param array<string,mixed> $payload
		 */
		do_action( 'zaplane/inbox/message_received', $payload );

		return $message;
	}

	/**
	 * @param array<string,mixed> $contact Known details: name, email, phone, avatar_url, wp_user_id.
	 */
	private static function identity( string $channel, string $account_id, string $external_id, array $contact ): Identity {
		$identity = Identity::where( 'channel', $channel )
			->where( 'account_id', $account_id )
			->where( 'external_id', $external_id )
			->fresh()
			->first();

		$details = self::clean_contact( $contact );

		if ( $identity ) {
			$existing = Contact::where( 'id', (int) $identity->contact_id )->fresh()->first();
			if ( $existing ) {
				self::fill_blanks( $existing, $details );
				return $identity;
			}
		}

		// A signed-in visitor or a known email joins the contact we already have,
		// so one person is one contact across channels.
		$contact_row = null;
		if ( ! empty( $details['wp_user_id'] ) ) {
			$contact_row = Contact::where( 'wp_user_id', (int) $details['wp_user_id'] )->fresh()->first();
		}
		if ( ! $contact_row && ! empty( $details['email'] ) ) {
			$contact_row = Contact::where( 'email', $details['email'] )->fresh()->first();
		}

		if ( $contact_row ) {
			self::fill_blanks( $contact_row, $details );
		} else {
			$contact_row = Contact::create( $details + [ 'meta' => [] ] );
		}

		if ( $identity ) {
			$identity->contact_id = (int) $contact_row->id;
			$identity->save();
			return $identity;
		}

		return Identity::create( [
			'contact_id'  => (int) $contact_row->id,
			'channel'     => $channel,
			'account_id'  => $account_id,
			'external_id' => $external_id,
		] );
	}

	/**
	 * The conversation this sender is in: the latest one on this identity,
	 * reopened when it was closed, or a new one.
	 */
	private static function conversation( Identity $identity, string $channel, string $account_id, ?bool &$is_new ): Conversation {
		$is_new = false;

		$conversation = Conversation::where( 'identity_id', (int) $identity->id )
			->orderBy( 'id', 'desc' )
			->fresh()
			->first();

		$ai_default = self::ai_default();

		if ( $conversation ) {
			if ( 'closed' === $conversation->status ) {
				// A new question after a close is a fresh start for the assistant.
				$conversation->status     = 'open';
				$conversation->closed_at  = null;
				$conversation->ai_enabled = $ai_default;
				$conversation->handler    = $ai_default ? 'bot' : 'human';
				$conversation->save();
			}
			return $conversation;
		}

		$is_new = true;

		return Conversation::create( [
			'contact_id'   => (int) $identity->contact_id,
			'identity_id'  => (int) $identity->id,
			'channel'      => $channel,
			'account_id'   => $account_id,
			'status'       => 'open',
			'handler'      => $ai_default ? 'bot' : 'human',
			'ai_enabled'   => $ai_default,
			'assignee_id'  => 0,
			'unread_count' => 0,
			'meta'         => [],
		] );
	}

	private static function ai_default(): bool {
		$ai = InboxSettings::get()['ai'];
		return ! empty( $ai['enabled'] ) && ! empty( $ai['connection_id'] );
	}

	/**
	 * @param array<string,mixed> $contact
	 * @return array<string,mixed>
	 */
	private static function clean_contact( array $contact ): array {
		$out = [];
		if ( ! empty( $contact['name'] ) ) {
			$out['name'] = mb_substr( sanitize_text_field( (string) $contact['name'] ), 0, 191 );
		}
		if ( ! empty( $contact['email'] ) && is_email( (string) $contact['email'] ) ) {
			$out['email'] = strtolower( sanitize_email( (string) $contact['email'] ) );
		}
		if ( ! empty( $contact['phone'] ) ) {
			$out['phone'] = mb_substr( preg_replace( '/[^0-9+]/', '', (string) $contact['phone'] ), 0, 50 );
		}
		if ( ! empty( $contact['avatar_url'] ) ) {
			$out['avatar_url'] = esc_url_raw( (string) $contact['avatar_url'] );
		}
		if ( ! empty( $contact['wp_user_id'] ) ) {
			$out['wp_user_id'] = absint( $contact['wp_user_id'] );
		}
		return $out;
	}

	/**
	 * Add details we did not have. Never overwrite: what the team or an earlier
	 * channel recorded is not replaced by whatever a later message claims.
	 *
	 * @param array<string,mixed> $details
	 */
	private static function fill_blanks( Contact $contact, array $details ): void {
		$dirty = false;
		foreach ( $details as $key => $value ) {
			if ( '' !== (string) $value && empty( $contact->{$key} ) ) {
				$contact->{$key} = $value;
				$dirty           = true;
			}
		}
		if ( $dirty ) {
			$contact->save();
		}
	}

	/**
	 * Record a reply the business sent outside Zaplane — from the channel's own
	 * app, say — so the thread stays complete. It counts as a person taking
	 * over: the assistant stops, exactly as for a reply from the inbox.
	 *
	 * @param array<int,mixed> $attachments
	 */
	public static function external_reply( string $channel, string $account_id, string $customer_id, string $message_id, string $body, array $attachments = [] ): ?Message {
		if ( '' !== $message_id && Message::where( 'channel', $channel )->where( 'external_id', $message_id )->fresh()->first() ) {
			return null;
		}

		$identity = Identity::where( 'channel', $channel )
			->where( 'account_id', $account_id )
			->where( 'external_id', $customer_id )
			->fresh()
			->first();
		if ( ! $identity ) {
			return null;
		}

		$conversation = Conversation::where( 'identity_id', (int) $identity->id )->orderBy( 'id', 'desc' )->fresh()->first();
		if ( ! $conversation ) {
			return null;
		}

		$now = Conversations::now();
		try {
			$message = Message::create( [
				'conversation_id' => $conversation->id,
				'direction'       => 'out',
				'sender_type'     => 'agent',
				'sender_id'       => 0,
				'body'            => $body,
				'attachments'     => $attachments,
				'channel'         => $channel,
				'external_id'     => '' !== $message_id ? $message_id : null,
				'delivery_status' => 'sent',
				'meta'            => [ 'source' => 'channel_app' ],
				'created_at'      => $now,
			] );
		} catch ( DatabaseException $e ) {
			return null;
		}

		if ( $conversation->ai_enabled || 'bot' === $conversation->handler ) {
			Conversations::hand_to_human( $conversation, '', false );
		}
		$conversation->last_message_preview = self::preview( $body, $attachments );
		$conversation->last_message_at      = $now;
		$conversation->unread_count         = 0;
		$conversation->save();

		return $message;
	}

	/**
	 * Whether a contact is already known on this channel address.
	 */
	public static function knows( string $channel, string $account_id, string $external_id ): bool {
		return (bool) Identity::where( 'channel', $channel )
			->where( 'account_id', $account_id )
			->where( 'external_id', $external_id )
			->fresh()
			->first();
	}

	/**
	 * @param array<int,mixed> $attachments
	 */
	public static function preview( string $body, array $attachments = [] ): string {
		$text = trim( wp_strip_all_tags( $body ) );
		if ( '' === $text && ! empty( $attachments ) ) {
			$first = is_array( $attachments[0] ?? null ) ? $attachments[0] : [];
			$text  = 'product' === ( $first['type'] ?? '' ) && ! empty( $first['name'] )
				/* translators: %s: product name. */
				? sprintf( __( '[Product] %s', 'zaplane' ), $first['name'] )
				: __( '[Attachment]', 'zaplane' );
		}
		return mb_substr( preg_replace( '/\s+/', ' ', $text ), 0, 250 );
	}
}
