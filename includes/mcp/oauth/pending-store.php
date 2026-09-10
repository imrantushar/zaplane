<?php

namespace Zaplane\Mcp\OAuth;

use Zaplane\Mcp\TokenStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connection requests waiting on an administrator.
 *
 * Someone who is signed in but cannot manage the site used to reach the consent
 * screen and find a dead end. Their request is now parked here instead: an
 * administrator sees it, decides what it may do, and the browser that is waiting
 * carries on by itself.
 *
 * Nothing about the protocol changes. To the client this is still the ordinary
 * authorization-code redirect — it simply takes longer than usual. That matters,
 * because no MCP client implements the grant designed for approving elsewhere
 * (RFC 8628), so anything that required their cooperation would never be used.
 *
 * Only signed-in users can leave a request. An endpoint that let strangers raise
 * notifications in wp-admin would be a way to nag an administrator into clicking
 * approve on something they did not expect.
 */
class PendingStore {

	private const OPTION = 'zaplane_mcp_pending_requests';

	/** Long enough to walk to another machine, short enough not to accumulate. */
	private const TTL = 15 * MINUTE_IN_SECONDS;

	/** A cap, so a busy site cannot grow the option without bound. */
	private const MAX = 20;

	public const WAITING  = 'waiting';
	public const APPROVED = 'approved';

	public static function boot(): void {
		add_action( 'admin_notices', [ self::class, 'notice' ] );
	}

	/**
	 * Tell an administrator that somebody is waiting, wherever they happen to be
	 * in wp-admin. A request that nobody notices is the same as a refusal.
	 */
	public static function notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! \Zaplane\Settings::feature_enabled( 'mcp_server' ) ) {
			return;
		}

		$count = self::waiting_count();

		if ( 0 === $count ) {
			return;
		}

		printf(
			// A plain core notice: common.js relocates these on load, and a custom
			// wrapper ends up emptied out.
			'<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of waiting requests. */
					_n(
						'%d person is waiting for you to allow an AI client to connect to Zaplane.',
						'%d people are waiting for you to allow an AI client to connect to Zaplane.',
						$count,
						'zaplane'
					),
					$count
				)
			),
			esc_url( admin_url( 'admin.php?page=' . ZAPLANE_PLUGIN_SLUG . '-settings' ) ),
			esc_html__( 'Review the request', 'zaplane' )
		);
	}

	/**
	 * Record a request, or return the one already standing for this exact
	 * attempt. A reloading wait page must not queue a new row every few seconds.
	 *
	 * @param array<string,mixed> $client       The registered client.
	 * @param string              $redirect_uri Already matched against the client.
	 * @param string              $challenge    The PKCE challenge this is bound to.
	 * @param array<int,string>   $scopes       What the client asked for.
	 * @param int                 $user_id      Who is asking.
	 * @return array<string,mixed>
	 */
	public static function request( array $client, string $redirect_uri, string $challenge, array $scopes, int $user_id ): array {
		$existing = self::find_for( $user_id, (string) $client['client_id'], $challenge );
		if ( null !== $existing ) {
			return $existing;
		}

		$user = get_userdata( $user_id );

		$pending = [
			'id'            => 'zpq_' . strtolower( wp_generate_password( 16, false ) ),
			'client_id'     => (string) $client['client_id'],
			'client_name'   => (string) $client['client_name'],
			'redirect_uri'  => $redirect_uri,
			'challenge'     => $challenge,
			'scopes'        => array_values( $scopes ),
			'granted'       => [],
			'user_id'       => $user_id,
			'user_name'     => $user ? $user->display_name : '',
			'status'        => self::WAITING,
			'requested_at'  => time(),
		];

		$all = self::all();
		array_unshift( $all, $pending );
		self::persist( array_slice( $all, 0, self::MAX ) );

		return $pending;
	}

	/**
	 * Every live request, newest first. Expired ones are dropped on read, which
	 * is the only sweep this needs — nothing here is worth a scheduled job.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		$raw   = get_option( self::OPTION, [] );
		$rows  = is_array( $raw ) ? $raw : [];
		$fresh = array_values(
			array_filter( $rows, fn( $r ) => isset( $r['requested_at'] ) && ( time() - (int) $r['requested_at'] ) < self::TTL )
		);

		if ( count( $fresh ) !== count( $rows ) ) {
			self::persist( $fresh );
		}

		return $fresh;
	}

	/** How many are waiting, for a badge. */
	public static function waiting_count(): int {
		return count( array_filter( self::all(), fn( $r ) => self::WAITING === $r['status'] ) );
	}

	/**
	 * @param string $id Request identifier.
	 * @return array<string,mixed>|null
	 */
	public static function find( string $id ): ?array {
		foreach ( self::all() as $row ) {
			if ( $row['id'] === $id ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * The row the waiting page is looking for: this person, this client, this
	 * exact challenge. Matching on the challenge means an approval cannot be
	 * reused for a second attempt.
	 *
	 * @param int    $user_id   Who is asking.
	 * @param string $client_id The registered client.
	 * @param string $challenge The PKCE challenge of this attempt.
	 * @return array<string,mixed>|null
	 */
	public static function find_for( int $user_id, string $client_id, string $challenge ): ?array {
		foreach ( self::all() as $row ) {
			if ( (int) $row['user_id'] === $user_id
				&& $row['client_id'] === $client_id
				&& hash_equals( (string) $row['challenge'], $challenge ) ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Approve a request, granting no more than was asked for.
	 *
	 * @param string            $id     Request identifier.
	 * @param array<int,string> $scopes What the administrator ticked.
	 */
	public static function approve( string $id, array $scopes ): bool {
		$granted = array_values(
			array_intersect( TokenStore::sanitize_scopes( $scopes ), self::find( $id )['scopes'] ?? [] )
		);

		if ( empty( $granted ) ) {
			$granted = [ TokenStore::SCOPE_READ ];
		}

		return self::update(
			$id,
			[
				'status'  => self::APPROVED,
				'granted' => $granted,
			]
		);
	}

	/**
	 * @param string $id Request identifier.
	 */
	public static function forget( string $id ): bool {
		$all  = self::all();
		$kept = array_values( array_filter( $all, fn( $r ) => $r['id'] !== $id ) );

		if ( count( $kept ) === count( $all ) ) {
			return false;
		}

		self::persist( $kept );

		return true;
	}

	/**
	 * @param string              $id      Request identifier.
	 * @param array<string,mixed> $changes Fields to merge in.
	 */
	private static function update( string $id, array $changes ): bool {
		$all   = self::all();
		$found = false;

		foreach ( $all as $index => $row ) {
			if ( $row['id'] === $id ) {
				$all[ $index ] = array_merge( $row, $changes );
				$found         = true;
				break;
			}
		}

		if ( $found ) {
			self::persist( $all );
		}

		return $found;
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 */
	private static function persist( array $rows ): void {
		update_option( self::OPTION, array_values( $rows ), false );
	}
}
