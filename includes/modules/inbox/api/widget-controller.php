<?php

namespace Zaplane\Modules\Inbox\Api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Identity;
use Zaplane\Modules\Inbox\Models\Message;
use Zaplane\Modules\Inbox\Services\Ingest;
use Zaplane\Modules\Inbox\Services\Presenter;
use Zaplane\Modules\Inbox\Settings as InboxSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public endpoints the website chat widget talks to.
 *
 * A visitor is identified by a token this site signed: `<visitor id>.<hmac>`.
 * Nothing is stored until the first message, so a page view that never chats
 * leaves no row behind. A signed-in visitor gets a token for their user id,
 * so their thread follows them between devices.
 */
class WidgetController {

	private const NS = 'zaplane/v1';

	/** Messages a visitor (one IP) may send per minute. */
	private const RATE_MESSAGES = 20;

	/** Sessions one IP may start per minute. */
	private const RATE_SESSIONS = 10;

	public function register_routes(): void {
		register_rest_route( self::NS, '/inbox/widget/session', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'start_session' ],
			'permission_callback' => [ $this, 'can_start_session' ],
		] );

		register_rest_route( self::NS, '/inbox/widget/messages', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_messages' ],
				'permission_callback' => [ $this, 'is_visitor' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'post_message' ],
				'permission_callback' => [ $this, 'can_post' ],
			],
		] );
	}

	/* ---------------------------------------------------------------- *
	 * Permission checks — each one is a real gate, never a pass-through.
	 * ---------------------------------------------------------------- */

	public function can_start_session( WP_REST_Request $request ) {
		$open = $this->widget_open( $request );
		if ( true !== $open ) {
			return $open;
		}
		return $this->within_rate( 'session', self::RATE_SESSIONS );
	}

	public function is_visitor( WP_REST_Request $request ) {
		$open = $this->widget_open( $request );
		if ( true !== $open ) {
			return $open;
		}
		return null !== self::visitor_from( $request )
			? true
			: new WP_Error( 'zaplane_inbox_visitor', __( 'Your chat session has expired. Please reload the page.', 'zaplane' ), [ 'status' => 401 ] );
	}

	public function can_post( WP_REST_Request $request ) {
		$visitor = $this->is_visitor( $request );
		if ( true !== $visitor ) {
			return $visitor;
		}
		return $this->within_rate( 'message', self::RATE_MESSAGES );
	}

	/**
	 * @return true|WP_Error
	 */
	private function widget_open( WP_REST_Request $request ) {
		if ( empty( InboxSettings::get()['widget']['enabled'] ) ) {
			return new WP_Error( 'zaplane_inbox_closed', __( 'Chat is not available.', 'zaplane' ), [ 'status' => 403 ] );
		}

		$origin = (string) $request->get_header( 'origin' );
		if ( '' !== $origin && ! self::origin_allowed( $origin ) ) {
			return new WP_Error( 'zaplane_inbox_origin', __( 'Chat is not available on this website.', 'zaplane' ), [ 'status' => 403 ] );
		}

		return true;
	}

	public static function origin_allowed( string $origin ): bool {
		$origin = strtolower( untrailingslashit( $origin ) );
		$home   = wp_parse_url( home_url() );
		$own    = strtolower( ( $home['scheme'] ?? 'https' ) . '://' . ( $home['host'] ?? '' ) . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) );

		return $origin === $own || in_array( $origin, (array) InboxSettings::get()['widget']['allowed_origins'], true );
	}

	/**
	 * A fixed window per IP. REMOTE_ADDR only: a forwarded-for header is the
	 * visitor's own claim and would let them pick whose budget they spend.
	 *
	 * @return true|WP_Error
	 */
	private function within_rate( string $bucket, int $limit ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'zaplane_inbox_rl_' . $bucket . '_' . md5( $ip );

		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return new WP_Error( 'zaplane_inbox_rate', __( 'You are sending messages too quickly. Please wait a moment.', 'zaplane' ), [ 'status' => 429 ] );
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/* ---------------------------------------------------------------- *
	 * Visitor tokens
	 * ---------------------------------------------------------------- */

	private static function sign( string $visitor_id ): string {
		return $visitor_id . '.' . hash_hmac( 'sha256', $visitor_id, wp_salt( 'auth' ) . '|zaplane-inbox-visitor' );
	}

	/**
	 * The visitor id the request's token proves, or null.
	 */
	public static function visitor_from( WP_REST_Request $request ): ?string {
		$token = (string) $request->get_header( 'x-zaplane-visitor' );
		if ( '' === $token || false === strpos( $token, '.' ) ) {
			return null;
		}

		[ $visitor_id ] = explode( '.', $token, 2 );
		if ( ! preg_match( '/^(v_[a-f0-9]{24}|u_\d+)$/', $visitor_id ) ) {
			return null;
		}

		if ( ! hash_equals( self::sign( $visitor_id ), $token ) ) {
			return null;
		}

		// A user token is only good while that user is the one signed in.
		if ( 0 === strpos( $visitor_id, 'u_' ) && (int) substr( $visitor_id, 2 ) !== get_current_user_id() ) {
			return null;
		}

		return $visitor_id;
	}

	/* ---------------------------------------------------------------- *
	 * Endpoints
	 * ---------------------------------------------------------------- */

	public function start_session( WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		// Keep an anonymous visitor's existing token if it is still valid.
		$existing = self::visitor_from( $request );
		if ( null !== $existing && ( 0 === strpos( $existing, 'u_' ) || ! $user_id ) ) {
			$visitor_id = $existing;
		} else {
			$visitor_id = $user_id ? 'u_' . $user_id : 'v_' . bin2hex( random_bytes( 12 ) );
		}

		$known = null;
		if ( $user_id ) {
			$user  = wp_get_current_user();
			$known = [
				'name'  => (string) $user->display_name,
				'email' => (string) $user->user_email,
			];
		}

		return rest_ensure_response( [
			'token'   => self::sign( $visitor_id ),
			'visitor' => $known,
		] );
	}

	public function get_messages( WP_REST_Request $request ) {
		$visitor_id   = (string) self::visitor_from( $request );
		$conversation = $this->conversation_for( $visitor_id );
		if ( ! $conversation ) {
			return rest_ensure_response( [
				'messages' => [],
				'status'   => null,
				'waiting'  => false,
			] );
		}

		$after = (int) ( $request->get_param( 'after_id' ) ?? 0 );
		$query = Message::where( 'conversation_id', (int) $conversation->id )->where( 'is_note', 0 )->fresh();
		$rows  = $after > 0
			? $query->where( 'id', '>', $after )->orderBy( 'id', 'asc' )->limit( 100 )->get()->all()
			: array_reverse( $query->orderBy( 'id', 'desc' )->limit( 50 )->get()->all() );

		return rest_ensure_response( [
			'messages' => array_map( static function ( $m ) {
				return Presenter::message( $m, true );
			}, $rows ),
			'status'   => (string) $conversation->status,
			'waiting'  => $this->assistant_is_typing( $conversation ),
		] );
	}

	public function post_message( WP_REST_Request $request ) {
		$visitor_id = (string) self::visitor_from( $request );
		$body       = trim( sanitize_textarea_field( (string) $request->get_param( 'body' ) ) );

		if ( '' === $body ) {
			return new WP_Error( 'zaplane_inbox_empty', __( 'Write a message first.', 'zaplane' ), [ 'status' => 400 ] );
		}
		$body = mb_substr( $body, 0, 4000 );

		// The account's own details win over anything the browser sends.
		$contact = [];
		if ( 0 === strpos( $visitor_id, 'u_' ) ) {
			$user    = wp_get_current_user();
			$contact = [
				'name'       => (string) $user->display_name,
				'email'      => (string) $user->user_email,
				'wp_user_id' => (int) $user->ID,
				'avatar_url' => (string) get_avatar_url( $user->ID ),
			];
		} else {
			$contact = [
				'name'  => (string) $request->get_param( 'name' ),
				'email' => (string) $request->get_param( 'email' ),
			];
		}

		$message = Ingest::inbound( [
			'channel'     => 'web',
			'external_id' => $visitor_id,
			'body'        => $body,
			'contact'     => $contact,
			'meta'        => [ 'page_url' => esc_url_raw( (string) $request->get_param( 'page_url' ) ) ],
		] );

		if ( ! $message ) {
			return new WP_Error( 'zaplane_inbox_rejected', __( 'Your message could not be sent.', 'zaplane' ), [ 'status' => 400 ] );
		}

		return rest_ensure_response( [ 'message' => Presenter::message( $message, true ) ] );
	}

	private function conversation_for( string $visitor_id ): ?Conversation {
		$identity = Identity::where( 'channel', 'web' )
			->where( 'account_id', '' )
			->where( 'external_id', $visitor_id )
			->fresh()
			->first();
		if ( ! $identity ) {
			return null;
		}

		return Conversation::where( 'identity_id', (int) $identity->id )->orderBy( 'id', 'desc' )->fresh()->first();
	}

	/**
	 * The assistant owes the latest customer message a reply.
	 */
	private function assistant_is_typing( Conversation $conversation ): bool {
		if ( 'bot' !== $conversation->handler || ! $conversation->ai_enabled ) {
			return false;
		}

		$last = Message::where( 'conversation_id', (int) $conversation->id )
			->where( 'is_note', 0 )
			->orderBy( 'id', 'desc' )
			->fresh()
			->first();

		return $last && 'contact' === $last->sender_type;
	}
}
