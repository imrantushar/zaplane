<?php

namespace Zaplane\Socket;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The site's side of the realtime channel.
 *
 * WordPress does not hold connections open, so a separate process does: see
 * Zaplane\Socket\Server, started with `wp zaplane socket start`. When a
 * message is saved, this pushes a short notice to that process, which passes
 * it to whoever is listening. The notice says only that something happened —
 * every client then reads it back over REST, where the usual permission
 * checks apply.
 *
 * Nothing here is required: with no server running, push() says so and the
 * chat carries on polling.
 */
class Client {

	public const OPT_STATUS    = 'zaplane_socket_status';
	public const OPT_HOST      = 'zaplane_socket_host';
	public const OPT_PORT      = 'zaplane_socket_port';
	public const OPT_SECRET    = 'zaplane_socket_secret';
	public const OPT_HEARTBEAT = 'zaplane_socket_heartbeat';
	public const OPT_LAST_ERROR = 'zaplane_socket_last_error';

	/** The server records that it is alive this often. */
	public const HEARTBEAT_EVERY = 15;

	/** The path the widget and the push both connect to. */
	public const PATH = '/zaplane';

	/**
	 * Whether a server is actually listening right now.
	 *
	 * The status option only records that one was started — a crash, a closed
	 * terminal or a reboot never corrects it. The heartbeat does: a stale one
	 * means the process is gone, whatever the status says. Read generously, so
	 * a slow tick is not mistaken for an outage.
	 */
	public static function is_live(): bool {
		if ( 'running' !== get_option( self::OPT_STATUS, 'stopped' ) ) {
			return false;
		}

		$beat = absint( get_option( self::OPT_HEARTBEAT, 0 ) );

		return $beat > 0 && ( time() - $beat ) <= ( 3 * self::HEARTBEAT_EVERY );
	}

	/** The shared secret the push channel authenticates with. */
	public static function secret(): string {
		$secret = (string) get_option( self::OPT_SECRET, '' );
		if ( '' === $secret ) {
			$secret = bin2hex( random_bytes( 16 ) );
			update_option( self::OPT_SECRET, $secret, false );
		}

		return $secret;
	}

	/**
	 * Tell everyone listening on a channel that something happened.
	 *
	 * @param string              $channel A visitor id, or 'agents' for the team.
	 * @param string              $event   'message', 'team', 'typing'…
	 * @param array<string,mixed> $data    A few scalars: never the message itself.
	 */
	public static function push( string $channel, string $event, array $data = [] ): bool {
		// Not "was it started" but "is it there": a push at a dead server costs
		// a connect timeout on the very request trying to deliver a reply.
		if ( '' === $channel || ! self::is_live() ) {
			return false;
		}

		$payload = [
			'action'  => 'publish',
			'channel' => $channel,
			'event'   => $event,
			'data'    => $data,
		];

		$sent = false;
		$why  = 'no address to try';
		foreach ( self::push_urls() as $url ) {
			$connection = new Connection( $url );
			$sent       = $connection->send( $payload );
			if ( $sent ) {
				break;
			}
			$why = $connection->error;
		}

		if ( ! $sent ) {
			self::record_failure( $why, $channel, $event );
		}

		return $sent;
	}

	/**
	 * Where to push. The server runs on this machine in the usual setup, so
	 * try it directly first: pushing over wss:// to a local certificate fails
	 * with an unhelpful socket error, and the message is then never delivered.
	 * The public address is the fallback for a server that lives elsewhere.
	 *
	 * @return array<int,string>
	 */
	private static function push_urls(): array {
		$urls = [];
		$host = (string) get_option( self::OPT_HOST, '127.0.0.1' );
		$port = absint( get_option( self::OPT_PORT, 0 ) );
		if ( '' !== $host && $port > 0 ) {
			$urls[] = 'ws://' . $host . ':' . $port . self::PATH;
		}

		$public = self::public_url();
		if ( '' !== $public ) {
			$urls[] = $public;
		}

		return array_map(
			static fn( $url ) => $url . '?push_key=' . rawurlencode( self::secret() ),
			$urls
		);
	}

	/** The address the browser is told to use, if the team set one. */
	public static function public_url(): string {
		$settings = \Zaplane\Modules\Inbox\Settings::get();

		return (string) ( $settings['realtime']['public_url'] ?? '' );
	}

	/**
	 * What the widget needs to connect, or null when realtime is off, no
	 * server is running, or the only address would be blocked by the browser.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function widget_config(): ?array {
		$settings = \Zaplane\Modules\Inbox\Settings::get();
		if ( empty( $settings['realtime']['enabled'] ) || ! self::is_live() ) {
			return null;
		}

		$url = self::public_url();
		if ( '' === $url ) {
			$host = (string) get_option( self::OPT_HOST, '' );
			$port = absint( get_option( self::OPT_PORT, 0 ) );
			// A page served over HTTPS may not open a ws:// connection, and the
			// browser blocks it as mixed content. Better to poll than to spend
			// every page load on a connection that cannot succeed.
			if ( '' === $host || $port <= 0 || is_ssl() ) {
				return null;
			}
			$url = 'ws://' . $host . ':' . $port;
		}

		return [
			'url'  => untrailingslashit( $url ) . self::PATH,
			// The chat keeps polling on a long timer even when connected, so a
			// silently dropped socket cannot lose a message.
			'fallbackPoll' => 30000,
		];
	}

	/**
	 * A failed push is a saved message nobody was told about — the recipient
	 * sees it on their next poll, which is exactly the outage the socket was
	 * meant to avoid. Record it so the settings screen can say so.
	 */
	private static function record_failure( string $error, string $channel, string $event ): void {
		update_option(
			self::OPT_LAST_ERROR,
			[
				'error'   => mb_substr( $error, 0, 300 ),
				'channel' => $channel,
				'event'   => $event,
				'at'      => time(),
			],
			false
		);

		/**
		 * Fires when a realtime push could not be delivered.
		 *
		 * @param string $error
		 * @param string $channel
		 * @param string $event
		 */
		do_action( 'zaplane/socket/push_failed', $error, $channel, $event );
	}

	/**
	 * A token that proves which agent a browser is, for the socket handshake.
	 * Short-lived: the socket is a notification channel, and a leaked token
	 * should stop working the same day.
	 */
	public static function agent_token( int $user_id, int $ttl = DAY_IN_SECONDS ): string {
		$expires = time() + max( 60, $ttl );
		$body    = 'a_' . $user_id . '_' . $expires;

		return $body . '.' . hash_hmac( 'sha256', $body, wp_salt( 'auth' ) . '|zaplane-socket-agent' );
	}

	/** The agent id a token proves, or 0. */
	public static function agent_from_token( string $token ): int {
		if ( false === strpos( $token, '.' ) ) {
			return 0;
		}

		[ $body, $signature ] = explode( '.', $token, 2 );
		if ( ! preg_match( '/^a_(\d+)_(\d+)$/', $body, $parts ) ) {
			return 0;
		}
		if ( ! hash_equals( hash_hmac( 'sha256', $body, wp_salt( 'auth' ) . '|zaplane-socket-agent' ), $signature ) ) {
			return 0;
		}
		if ( (int) $parts[2] < time() ) {
			return 0;
		}

		return (int) $parts[1];
	}
}
