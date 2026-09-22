<?php

namespace Zaplane\Modules\Inbox\Services;

use WP_Error;
use Zaplane\Modules\Inbox\Models\Contact;
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Message;
use Zaplane\Modules\Inbox\Settings as InboxSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A website visitor's way back: their email.
 *
 * Once a person is going to answer, the chat asks for a name and email (when
 * we don't have them), optionally confirmed with a one-time code so a typo or
 * a made-up address doesn't go unnoticed. A reply the visitor didn't see in
 * the chat is then emailed to them.
 */
class VisitorContact {

	public const NOTIFY_HOOK = 'zaplane_inbox_notify_visitor';

	/** Seconds a reply may sit unseen in the chat before it is emailed. */
	public const NOTIFY_AFTER = 120;

	/** A code is good for this long, and this many guesses. */
	private const CODE_TTL   = 15 * MINUTE_IN_SECONDS;
	private const CODE_TRIES = 5;

	/** Codes one conversation may be sent per hour, and the gap between two. */
	private const CODES_PER_HOUR = 5;
	private const RESEND_AFTER   = 30;

	public static function register(): void {
		add_action( 'zaplane/inbox/message_sent', [ self::class, 'queue_notify' ] );
		add_action( self::NOTIFY_HOOK, [ self::class, 'notify' ], 10, 1 );
	}

	/**
	 * What the chat should ask for right now, or null.
	 *
	 * @return array{email:string,name:bool,verify:bool,pending:string}|null
	 */
	public static function ask( Conversation $conversation ): ?array {
		$widget = InboxSettings::get()['widget'];
		if ( empty( $widget['ask_contact'] ) || 'web' !== $conversation->channel || 'human' !== $conversation->handler ) {
			return null;
		}
		$meta = is_array( $conversation->meta ) ? $conversation->meta : [];
		if ( ! empty( $meta['contact_skipped'] ) ) {
			return null;
		}

		if ( ! self::waiting_on_person( $conversation, $meta ) ) {
			return null;
		}

		$contact = Contact::where( 'id', (int) $conversation->contact_id )->fresh()->first();
		if ( ! $contact || (int) $contact->wp_user_id > 0 ) {
			return null;
		}

		$verify = ! empty( $widget['verify_email'] );
		$email  = (string) $contact->email;
		if ( '' !== $email && ( ! $verify || self::verified( $contact ) ) ) {
			return null;
		}

		$pending = is_array( $meta['contact_pending'] ?? null ) && (int) $meta['contact_pending']['expires'] > time()
			? (string) $meta['contact_pending']['email']
			: '';

		return [
			'email'   => $email,
			'name'    => '' === (string) $contact->name,
			'verify'  => $verify,
			'pending' => $pending,
		];
	}

	/**
	 * A person is (or will be) answering, not automatic answers: the visitor
	 * chose "Talk to a person", the assistant handed over, the team already
	 * wrote, or nothing answers on its own at all.
	 *
	 * @param array<string,mixed> $meta
	 */
	private static function waiting_on_person( Conversation $conversation, array $meta ): bool {
		if ( ! empty( $meta['kb_person_at'] ) || ! empty( $meta['person_at'] ) || empty( InboxSettings::get()['answers']['enabled'] ) ) {
			return true;
		}
		return (bool) Message::where( 'conversation_id', (int) $conversation->id )->where( 'sender_type', 'agent' )->where( 'is_note', 0 )->fresh()->first();
	}

	public static function verified( Contact $contact ): bool {
		$meta = is_array( $contact->meta ) ? $contact->meta : [];
		return '' !== (string) $contact->email && strtolower( (string) ( $meta['email_verified'] ?? '' ) ) === strtolower( (string) $contact->email );
	}

	/**
	 * The visitor gave their details. Saved as they are, or held until the
	 * emailed code comes back when codes are on.
	 *
	 * @return array{status:string,email:string}|WP_Error status: saved | code_sent
	 */
	public static function submit( Conversation $conversation, string $name, string $email ) {
		$email = strtolower( sanitize_email( $email ) );
		$name  = mb_substr( sanitize_text_field( $name ), 0, 191 );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'zaplane_inbox_email', __( 'Please enter a valid email address.', 'zaplane' ), [ 'status' => 400 ] );
		}

		$contact = Contact::where( 'id', (int) $conversation->contact_id )->fresh()->first();
		if ( ! $contact ) {
			return new WP_Error( 'zaplane_inbox_contact', __( 'Start the chat first.', 'zaplane' ), [ 'status' => 400 ] );
		}
		if ( '' !== $name && '' === (string) $contact->name ) {
			$contact->name = $name;
			$contact->save();
		}

		if ( empty( InboxSettings::get()['widget']['verify_email'] ) ) {
			self::set_email( $conversation, $contact, $email, false );
			return [
				'status' => 'saved',
				'email'  => $email,
			];
		}

		// Codes on: hold the address until they prove it's theirs.
		$meta = is_array( $conversation->meta ) ? $conversation->meta : [];
		$log  = array_values( array_filter( (array) ( $meta['contact_codes'] ?? [] ), static fn( $t ) => (int) $t > time() - HOUR_IN_SECONDS ) );
		if ( $log && max( $log ) > time() - self::RESEND_AFTER ) {
			return new WP_Error( 'zaplane_inbox_code_wait', __( 'We just sent a code. Please wait a few seconds before asking for another.', 'zaplane' ), [ 'status' => 429 ] );
		}
		if ( count( $log ) >= self::CODES_PER_HOUR ) {
			return new WP_Error( 'zaplane_inbox_code_limit', __( 'Too many codes requested. Please try again later.', 'zaplane' ), [ 'status' => 429 ] );
		}

		$code = (string) wp_rand( 100000, 999999 );
		$sent = wp_mail(
			$email,
			/* translators: %s: site name. */
			sprintf( __( 'Your code for chatting with %s', 'zaplane' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			sprintf(
				/* translators: 1: the code, 2: minutes it is valid. */
				__( "Your verification code is: %1\$s\n\nEnter it in the chat window to confirm your email. It expires in %2\$d minutes.\n\nIf you didn't ask for this, you can ignore this email.", 'zaplane' ),
				$code,
				(int) ( self::CODE_TTL / MINUTE_IN_SECONDS )
			)
		);
		if ( ! $sent ) {
			return new WP_Error( 'zaplane_inbox_mail', __( "We couldn't send the code. Please check the address and try again.", 'zaplane' ), [ 'status' => 500 ] );
		}

		$log[] = time();
		Conversations::set_meta( $conversation, [
			'contact_codes'   => $log,
			'contact_pending' => [
				'email'   => $email,
				'hash'    => self::hash( $code, (int) $conversation->id ),
				'expires' => time() + self::CODE_TTL,
				'tries'   => 0,
			],
		] );

		return [
			'status' => 'code_sent',
			'email'  => $email,
		];
	}

	/**
	 * @return array{status:string,email:string}|WP_Error
	 */
	public static function verify( Conversation $conversation, string $code ) {
		$meta    = is_array( $conversation->meta ) ? $conversation->meta : [];
		$pending = is_array( $meta['contact_pending'] ?? null ) ? $meta['contact_pending'] : null;
		if ( ! $pending || (int) $pending['expires'] < time() ) {
			return new WP_Error( 'zaplane_inbox_code_expired', __( 'That code has expired. Ask for a new one.', 'zaplane' ), [ 'status' => 400 ] );
		}
		if ( (int) $pending['tries'] >= self::CODE_TRIES ) {
			return new WP_Error( 'zaplane_inbox_code_tries', __( 'Too many wrong codes. Ask for a new one.', 'zaplane' ), [ 'status' => 429 ] );
		}

		$code = preg_replace( '/\D/', '', $code );
		if ( ! hash_equals( (string) $pending['hash'], self::hash( $code, (int) $conversation->id ) ) ) {
			$pending['tries'] = (int) $pending['tries'] + 1;
			Conversations::set_meta( $conversation, [ 'contact_pending' => $pending ] );
			return new WP_Error( 'zaplane_inbox_code_wrong', __( "That code isn't right. Check the email and try again.", 'zaplane' ), [ 'status' => 400 ] );
		}

		$contact = Contact::where( 'id', (int) $conversation->contact_id )->fresh()->first();
		if ( ! $contact ) {
			return new WP_Error( 'zaplane_inbox_contact', __( 'Start the chat first.', 'zaplane' ), [ 'status' => 400 ] );
		}
		Conversations::set_meta( $conversation, [ 'contact_pending' => null ] );
		self::set_email( $conversation, $contact, (string) $pending['email'], true );

		return [
			'status' => 'verified',
			'email'  => (string) $pending['email'],
		];
	}

	public static function skip( Conversation $conversation ): void {
		Conversations::set_meta( $conversation, [
			'contact_skipped' => time(),
			'contact_pending' => null,
		] );
	}

	private static function set_email( Conversation $conversation, Contact $contact, string $email, bool $verified ): void {
		$meta = is_array( $contact->meta ) ? $contact->meta : [];
		if ( $verified ) {
			$meta['email_verified'] = $email;
		} elseif ( strtolower( (string) ( $meta['email_verified'] ?? '' ) ) !== $email ) {
			unset( $meta['email_verified'] );
		}
		$contact->email = $email;
		$contact->meta  = $meta;
		$contact->save();

		Conversations::system_note( $conversation, $verified
			/* translators: %s: email address. */
			? sprintf( __( 'Visitor confirmed their email: %s', 'zaplane' ), $email )
			/* translators: %s: email address. */
			: sprintf( __( 'Visitor left their email: %s (not confirmed)', 'zaplane' ), $email ) );
	}

	private static function hash( string $code, int $conversation_id ): string {
		return hash_hmac( 'sha256', $conversation_id . '|' . $code, wp_salt( 'auth' ) . '|zaplane-inbox-code' );
	}

	/* Replies the visitor didn't see ----------------------------------- */

	/**
	 * The widget showed the visitor everything up to this message.
	 */
	public static function seen_upto( Conversation $conversation, int $message_id ): void {
		$meta = is_array( $conversation->meta ) ? $conversation->meta : [];
		if ( $message_id > (int) ( $meta['read_upto'] ?? 0 ) ) {
			Conversations::set_meta( $conversation, [ 'read_upto' => $message_id ] );
		}
	}

	/**
	 * A reply from the team (or a workflow / the assistant) went into a
	 * website chat: check back shortly whether the visitor saw it.
	 *
	 * @param array<string,mixed> $payload zaplane/inbox/message_sent
	 */
	public static function queue_notify( array $payload ): void {
		if ( 'web' !== ( $payload['channel'] ?? '' ) || ! in_array( $payload['sender_type'] ?? '', [ 'agent', 'workflow', 'ai' ], true ) ) {
			return;
		}
		if ( empty( InboxSettings::get()['widget']['notify_email'] ) || ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}
		$cid = (int) ( $payload['conversation_id'] ?? 0 );
		// One check per conversation covers every reply sent meanwhile.
		if ( $cid && ! as_has_scheduled_action( self::NOTIFY_HOOK, [ $cid ], Router::AS_GROUP ) ) {
			as_schedule_single_action( time() + self::NOTIFY_AFTER, self::NOTIFY_HOOK, [ $cid ], Router::AS_GROUP );
		}
	}

	/**
	 * Email the replies the visitor hasn't seen, if we have an address for
	 * them (a confirmed one when codes are on).
	 */
	public static function notify( int $conversation_id ): bool {
		$conversation = Conversations::find( $conversation_id );
		if ( ! $conversation || 'web' !== $conversation->channel ) {
			return false;
		}
		$widget  = InboxSettings::get()['widget'];
		$contact = Contact::where( 'id', (int) $conversation->contact_id )->fresh()->first();
		if ( empty( $widget['notify_email'] ) || ! $contact || ! is_email( (string) $contact->email ) ) {
			return false;
		}
		if ( ! empty( $widget['verify_email'] ) && ! (int) $contact->wp_user_id && ! self::verified( $contact ) ) {
			return false;
		}

		$meta  = is_array( $conversation->meta ) ? $conversation->meta : [];
		$after = max( (int) ( $meta['read_upto'] ?? 0 ), (int) ( $meta['notified_upto'] ?? 0 ) );
		$rows  = Message::where( 'conversation_id', (int) $conversation->id )
			->where( 'direction', 'out' )
			->where( 'is_note', 0 )
			->where( 'id', '>', $after )
			->orderBy( 'id', 'asc' )
			->limit( 10 )
			->fresh()
			->get()
			->all();
		$rows = array_values( array_filter( $rows, static fn( $m ) => '' !== trim( (string) $m->body ) ) ); // Deleted ones have no body.
		if ( ! $rows ) {
			return false;
		}

		$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$lines = array_map( static function ( $m ) {
			$who = Presenter::message( $m, true )['sender_name'] ?? '';
			return ( '' !== $who ? $who . ":\n" : '' ) . trim( (string) $m->body );
		}, $rows );

		$sent = wp_mail(
			(string) $contact->email,
			/* translators: %s: site name. */
			sprintf( __( 'New reply from %s', 'zaplane' ), $site ),
			sprintf(
				/* translators: 1: greeting name, 2: site name, 3: the replies, 4: link back to the chat. */
				__( "Hi %1\$s,\n\n%2\$s replied to your chat:\n\n%3\$s\n\nContinue the conversation: %4\$s\n", 'zaplane' ),
				'' !== (string) $contact->name ? (string) $contact->name : __( 'there', 'zaplane' ),
				$site,
				implode( "\n\n", $lines ),
				self::chat_link( $conversation )
			)
		);

		if ( $sent ) {
			Conversations::set_meta( $conversation, [ 'notified_upto' => (int) end( $rows )->id ] );
		}
		return (bool) $sent;
	}

	/** The page they chatted from, with the chat opened. */
	private static function chat_link( Conversation $conversation ): string {
		$last = Message::where( 'conversation_id', (int) $conversation->id )
			->where( 'direction', 'in' )
			->orderBy( 'id', 'desc' )
			->fresh()
			->first();
		$url = $last && is_array( $last->meta ) ? (string) ( $last->meta['page_url'] ?? '' ) : '';
		$url = '' !== $url && wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ? $url : home_url( '/' );
		return strtok( $url, '#' ) . '#zaplane-chat';
	}
}
