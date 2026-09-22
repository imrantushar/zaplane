<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Modules\Inbox\Models\Contact;
use Zaplane\Modules\Inbox\Models\Conversation;
use Zaplane\Modules\Inbox\Models\Identity;
use Zaplane\Modules\Inbox\Settings as InboxSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Who is on the site right now. The chat widget reports the page it is on
 * every half minute while the tab is visible; anyone heard from in the last
 * ONLINE seconds is online. A row unseen for a day is deleted.
 */
class Visitors {

	/** Seconds since the last report for a visitor to count as online. */
	public const ONLINE = 75;

	public static function enabled(): bool {
		$widget = InboxSettings::get()['widget'];
		return ! empty( $widget['enabled'] ) && ! empty( $widget['visitors'] );
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'zaplane_inbox_visitors';
	}

	/**
	 * Record a visitor's current page.
	 *
	 * @param array{page_url?:string,page_title?:string,referrer?:string,user_agent?:string,wp_user_id?:int} $page
	 */
	public static function seen( string $visitor_id, array $page ): void {
		global $wpdb;
		$now   = gmdate( 'Y-m-d H:i:s' );
		$url   = mb_substr( esc_url_raw( (string) ( $page['page_url'] ?? '' ) ), 0, 500 );
		$title = mb_substr( sanitize_text_field( (string) ( $page['page_title'] ?? '' ) ), 0, 255 );
		$ref   = mb_substr( esc_url_raw( (string) ( $page['referrer'] ?? '' ) ), 0, 500 );
		// Only another site counts as where they came from.
		if ( '' !== $ref && wp_parse_url( $ref, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			$ref = '';
		}

		// One statement, so two tabs reporting at once can't both insert.
		// A new page counts as a view; the same page reporting again doesn't.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A presence upsert; nothing to cache.
		$wpdb->query( $wpdb->prepare(
			'INSERT INTO %i (visitor_id, wp_user_id, page_url, page_title, referrer, device, page_views, first_seen_at, last_seen_at)
			VALUES (%s, %d, %s, %s, %s, %s, 1, %s, %s)
			ON DUPLICATE KEY UPDATE
				page_views = page_views + IF(page_url <=> VALUES(page_url), 0, 1),
				page_url = VALUES(page_url),
				page_title = VALUES(page_title),
				wp_user_id = VALUES(wp_user_id),
				device = VALUES(device),
				referrer = IF(referrer IS NULL OR referrer = \'\', VALUES(referrer), referrer),
				last_seen_at = VALUES(last_seen_at)',
			self::table(),
			$visitor_id,
			(int) ( $page['wp_user_id'] ?? 0 ),
			$url,
			$title,
			$ref,
			self::device( (string) ( $page['user_agent'] ?? '' ) ),
			$now,
			$now
		) );

		// Now and then, forget visitors gone for a day.
		if ( 1 === wp_rand( 1, 50 ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE last_seen_at < %s', self::table(), gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) );
		}
	}

	public static function is_online( string $visitor_id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$seen = $wpdb->get_var( $wpdb->prepare( 'SELECT last_seen_at FROM %i WHERE visitor_id = %s', self::table(), $visitor_id ) );
		return $seen && strtotime( $seen . ' UTC' ) >= time() - self::ONLINE;
	}

	public static function count_online(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE last_seen_at >= %s', self::table(), gmdate( 'Y-m-d H:i:s', time() - self::ONLINE ) ) );
	}

	/**
	 * Everyone online, most recently arrived first, with who they are when
	 * they have chatted before.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function online( int $limit = 100 ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = (array) $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM %i WHERE last_seen_at >= %s ORDER BY first_seen_at DESC LIMIT %d',
			self::table(),
			gmdate( 'Y-m-d H:i:s', time() - self::ONLINE ),
			max( 1, min( 200, $limit ) )
		), ARRAY_A );

		$out = [];
		foreach ( $rows as $row ) {
			$vid          = (string) $row['visitor_id'];
			$conversation = self::conversation( $vid );
			$contact      = $conversation ? Contact::where( 'id', (int) $conversation->contact_id )->fresh()->first() : null;
			// Named as the inbox names them, once they have a conversation.
			$who = $contact ? Presenter::contact( $contact ) : [];
			if ( ! $contact && (int) $row['wp_user_id'] ) {
				$user = get_userdata( (int) $row['wp_user_id'] );
				$who  = $user ? [
					'name'  => $user->display_name,
					'email' => $user->user_email,
				] : [];
			}

			$out[] = [
				'visitor_id'      => $vid,
				'name'            => (string) ( $who['name'] ?? '' ),
				'email'           => (string) ( $who['email'] ?? '' ),
				'signed_in'       => (int) $row['wp_user_id'] > 0,
				'page_url'        => (string) $row['page_url'],
				'page_title'      => (string) $row['page_title'],
				'referrer'        => (string) $row['referrer'],
				'device'          => (string) $row['device'],
				'page_views'      => (int) $row['page_views'],
				// Stored in UTC; say so, or browsers read it as their local time.
				'first_seen_at'   => gmdate( 'c', (int) strtotime( $row['first_seen_at'] . ' UTC' ) ),
				'last_seen_at'    => gmdate( 'c', (int) strtotime( $row['last_seen_at'] . ' UTC' ) ),
				'conversation_id' => $conversation ? (int) $conversation->id : 0,
				'status'          => $conversation ? (string) $conversation->status : '',
				'unread'          => $conversation ? (int) $conversation->unread_count : 0,
			];
		}
		return $out;
	}

	/**
	 * The visitor's conversation (the latest), if they have one.
	 */
	public static function conversation( string $visitor_id ): ?Conversation {
		$identity = Identity::where( 'channel', 'web' )
			->where( 'account_id', '' )
			->where( 'external_id', $visitor_id )
			->fresh()
			->first();
		return $identity
			? Conversation::where( 'identity_id', (int) $identity->id )->orderBy( 'id', 'desc' )->fresh()->first()
			: null;
	}

	/**
	 * Start (or continue) a chat with someone browsing: their conversation
	 * gets the team's message, and their widget opens on it.
	 *
	 * @return \Zaplane\Modules\Inbox\Models\Message|\WP_Error
	 */
	public static function message( string $visitor_id, string $body, int $user_id ) {
		if ( ! preg_match( '/^(v_[a-f0-9]{24}|u_\d+)$/', $visitor_id ) ) {
			return new \WP_Error( 'zaplane_inbox_visitor', __( 'Unknown visitor.', 'zaplane' ) );
		}

		$conversation = self::conversation( $visitor_id );
		if ( ! $conversation ) {
			$contact = [];
			if ( 0 === strpos( $visitor_id, 'u_' ) ) {
				$user = get_userdata( (int) substr( $visitor_id, 2 ) );
				if ( $user ) {
					$contact = [
						'name'       => $user->display_name,
						'email'      => $user->user_email,
						'wp_user_id' => (int) $user->ID,
					];
				}
			}
			$conversation = Ingest::open( 'web', '', $visitor_id, $contact );
		}

		return Outbound::send( $conversation, $body, [
			'sender_type' => 'agent',
			'sender_id'   => $user_id,
		] );
	}

	private static function device( string $ua ): string {
		if ( '' === $ua ) {
			return '';
		}
		if ( preg_match( '/iPad|Tablet|(Android(?!.*Mobile))/i', $ua ) ) {
			return 'tablet';
		}
		return preg_match( '/Mobi|iPhone|Android/i', $ua ) ? 'mobile' : 'desktop';
	}
}
