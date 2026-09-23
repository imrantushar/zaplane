<?php

namespace Zaplane\Modules\Inbox\Services;

use Zaplane\Modules\Inbox\Api\AdminController;
use Zaplane\Modules\Inbox\Settings as InboxSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The people who answer the inbox: who they are to a visitor, and whether
 * any of them is at their desk right now.
 *
 * An agent is any user who may work in the inbox. What the visitor sees is
 * their WordPress name and avatar unless the team gives them a chat name and
 * picture of their own — "tusharimran" rarely reads well in a chat window.
 */
class Agents {

	/** User meta holding one agent's chat identity. */
	public const META_IDENTITY = 'zaplane_inbox_agent';

	/** When that agent's inbox was last open. */
	public const META_SEEN = 'zaplane_inbox_seen';

	/** '1' while they have marked themselves away. */
	public const META_AWAY = 'zaplane_inbox_away';

	/** An agent counts as online for this long after their last sign of life. */
	public const ONLINE_WINDOW = 90;

	/** The heartbeat the inbox screen sends; the window is a few times this. */
	public const HEARTBEAT_EVERY = 30;

	/**
	 * Everyone who may answer, newest sign of life first.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function roster(): array {
		$users = get_users( [
			'capability' => AdminController::capability(),
			'number'     => 50,
			'orderby'    => 'display_name',
			'fields'     => [ 'ID', 'display_name' ],
		] );

		$out = [];
		foreach ( $users as $user ) {
			$out[] = self::agent( (int) $user->ID );
		}

		usort( $out, static fn( $a, $b ) => ( $b['online'] <=> $a['online'] ) ?: strcasecmp( $a['name'], $b['name'] ) );

		return $out;
	}

	/**
	 * One agent as the team sees them: their chat identity, whether they are
	 * online, and the WordPress details the identity falls back to.
	 *
	 * @return array<string,mixed>
	 */
	public static function agent( int $user_id ): array {
		$user     = get_userdata( $user_id );
		$identity = self::identity( $user_id );

		return [
			'id'         => $user_id,
			'user_name'  => $user ? (string) $user->display_name : '',
			'name'       => '' !== $identity['name'] ? $identity['name'] : ( $user ? (string) $user->display_name : '' ),
			'title'      => $identity['title'],
			'avatar'     => '' !== $identity['avatar'] ? $identity['avatar'] : (string) get_avatar_url( $user_id, [ 'size' => 96 ] ),
			'avatar_id'  => $identity['avatar_id'],
			'hidden'     => $identity['hidden'],
			'online'     => self::is_online( $user_id ),
			'away'       => '1' === (string) get_user_meta( $user_id, self::META_AWAY, true ),
			'last_seen'  => (int) get_user_meta( $user_id, self::META_SEEN, true ),
		];
	}

	/**
	 * The stored chat identity for one agent, with every key present.
	 *
	 * @return array{name:string,title:string,avatar:string,avatar_id:int,hidden:bool}
	 */
	public static function identity( int $user_id ): array {
		$saved = get_user_meta( $user_id, self::META_IDENTITY, true );
		$saved = is_array( $saved ) ? $saved : [];

		$avatar_id = absint( $saved['avatar_id'] ?? 0 );
		$avatar    = (string) ( $saved['avatar'] ?? '' );
		if ( $avatar_id > 0 ) {
			$from_library = wp_get_attachment_image_url( $avatar_id, 'thumbnail' );
			$avatar       = $from_library ? $from_library : $avatar;
		}

		return [
			'name'      => sanitize_text_field( (string) ( $saved['name'] ?? '' ) ),
			'title'     => sanitize_text_field( (string) ( $saved['title'] ?? '' ) ),
			'avatar'    => esc_url_raw( $avatar ),
			'avatar_id' => $avatar_id,
			'hidden'    => ! empty( $saved['hidden'] ),
		];
	}

	/**
	 * Store one agent's chat identity. Only the keys given are changed.
	 *
	 * @param array<string,mixed> $input
	 * @return array<string,mixed> The agent as it now stands.
	 */
	public static function save_identity( int $user_id, array $input ): array {
		$identity = self::identity( $user_id );

		foreach ( [ 'name', 'title' ] as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$identity[ $key ] = sanitize_text_field( (string) $input[ $key ] );
			}
		}
		if ( array_key_exists( 'avatar_id', $input ) ) {
			$identity['avatar_id'] = absint( $input['avatar_id'] );
			$identity['avatar']    = $identity['avatar_id'] > 0 ? (string) wp_get_attachment_image_url( $identity['avatar_id'], 'thumbnail' ) : '';
		}
		if ( isset( $input['avatar'] ) && ! isset( $input['avatar_id'] ) ) {
			$identity['avatar'] = esc_url_raw( (string) $input['avatar'] );
		}
		if ( array_key_exists( 'hidden', $input ) ) {
			$identity['hidden'] = (bool) $input['hidden'];
		}

		update_user_meta( $user_id, self::META_IDENTITY, $identity );
		Availability::forget();

		return self::agent( $user_id );
	}

	/**
	 * How a message from this agent is signed in the chat.
	 *
	 * @return array{name:string,avatar:string,title:string}
	 */
	public static function signature( int $user_id ): array {
		$agent = self::agent( $user_id );

		return [
			'name'   => $agent['name'] !== '' ? $agent['name'] : __( 'Support', 'zaplane' ),
			'avatar' => $agent['avatar'],
			'title'  => $agent['title'],
		];
	}

	/* ---------------------------------------------------------------- *
	 * At their desk
	 * ---------------------------------------------------------------- */

	/**
	 * Record that an agent's inbox is open. Called from the screen's own
	 * polling, so being in the inbox is all it takes to look online.
	 */
	public static function touch( int $user_id = 0 ): void {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		// Writing on every poll would be a row update every few seconds per
		// agent; a third of the window is often enough to stay "online".
		$last = (int) get_user_meta( $user_id, self::META_SEEN, true );
		if ( time() - $last < (int) ( self::HEARTBEAT_EVERY / 2 ) ) {
			return;
		}

		update_user_meta( $user_id, self::META_SEEN, time() );
	}

	/** Mark the current agent away (or back), which the widget reflects. */
	public static function set_away( int $user_id, bool $away ): void {
		// Stored as a string: update_user_meta( …, false ) on a meta row that
		// does not exist yet cannot be told apart from "no value".
		update_user_meta( $user_id, self::META_AWAY, $away ? '1' : '0' );
		if ( ! $away ) {
			update_user_meta( $user_id, self::META_SEEN, time() );
		}
		Availability::forget();
	}

	public static function is_online( int $user_id ): bool {
		if ( '1' === (string) get_user_meta( $user_id, self::META_AWAY, true ) ) {
			return false;
		}
		$seen = (int) get_user_meta( $user_id, self::META_SEEN, true );

		return $seen > 0 && ( time() - $seen ) <= self::ONLINE_WINDOW;
	}

	/**
	 * The agents a visitor may see in the chat: online first, never more than
	 * a handful, and only what is safe to show (no email, no user id).
	 *
	 * @return array<int,array{name:string,avatar:string,title:string,online:bool}>
	 */
	public static function for_widget( int $limit = 3 ): array {
		$out = [];
		foreach ( self::roster() as $agent ) {
			if ( $agent['hidden'] || '' === $agent['name'] ) {
				continue;
			}
			$out[] = [
				'name'   => $agent['name'],
				'avatar' => $agent['avatar'],
				'title'  => $agent['title'],
				'online' => $agent['online'],
			];
			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}

	/** Whether anyone is at their desk. */
	public static function anyone_online(): bool {
		foreach ( self::roster() as $agent ) {
			if ( $agent['online'] && ! $agent['hidden'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The assistant's own identity for the chat, or null when it is off.
	 *
	 * @return array{name:string,avatar:string,title:string}|null
	 */
	public static function assistant(): ?array {
		$ai = InboxSettings::get()['ai'];
		if ( empty( $ai['enabled'] ) || empty( $ai['connection_id'] ) ) {
			return null;
		}

		$name = trim( (string) $ai['agent_name'] );

		return [
			'name'   => '' !== $name ? $name : 'Ava',
			'avatar' => esc_url_raw( (string) ( $ai['avatar'] ?? '' ) ),
			'title'  => __( 'AI assistant', 'zaplane' ),
		];
	}
}
