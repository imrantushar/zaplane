<?php

namespace Zaplane\Socket;

use Zaplane\Modules\Inbox\Api\WidgetController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The realtime server: one process, started with `wp zaplane socket start`,
 * that holds every open chat's connection and passes on what the site pushes
 * to it.
 *
 * It is deliberately dumb. A notice says only "channel X has something new";
 * the browser then reads it back over REST, where the usual permission checks
 * apply. Nothing private travels over this socket, and a client that should
 * not see a message cannot get it by listening.
 *
 * Who may listen is settled at the handshake: a visitor proves their own
 * chat's token, an agent proves a signed token from the inbox screen, and the
 * site proves the push secret. A connection that proves nothing is closed.
 */
class Server {

	/** How long a connection may stay silent before it is pinged. */
	private const PING_AFTER = 30;

	/** How long after that before it is dropped. */
	private const DROP_AFTER = 90;

	private string $host;

	private int $port;

	/** @var resource|null */
	private $listener = null;

	/**
	 * Open connections.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $clients = [];

	private bool $running = false;

	private int $last_beat = 0;

	/** @var callable|null */
	private $logger = null;

	public function __construct( string $host = '127.0.0.1', int $port = 8088, ?callable $logger = null ) {
		$this->host   = $host;
		$this->port   = $port;
		$this->logger = $logger;
	}

	/**
	 * Listen until something stops us.
	 *
	 * @return bool False when the address could not be taken.
	 */
	public function run(): bool {
		$context = stream_context_create( [ 'socket' => [ 'backlog' => 128 ] ] );
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A taken port is reported, not fatal.
		$this->listener = @stream_socket_server( 'tcp://' . $this->host . ':' . $this->port, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context );

		if ( ! $this->listener ) {
			$this->log( sprintf( 'Could not listen on %s:%d — %s', $this->host, $this->port, $errstr ?: $errno ) );
			return false;
		}

		stream_set_blocking( $this->listener, false );
		$this->running = true;
		$this->started();
		$this->log( sprintf( 'Listening on ws://%s:%d%s', $this->host, $this->port, Client::PATH ) );

		if ( function_exists( 'pcntl_signal' ) && function_exists( 'pcntl_async_signals' ) ) {
			pcntl_async_signals( true );
			foreach ( [ SIGTERM, SIGINT, SIGHUP ] as $signal ) {
				pcntl_signal( $signal, function () {
					$this->log( 'Stopping.' );
					$this->running = false;
				} );
			}
		}

		while ( $this->running ) {
			$this->tick();
		}

		$this->shut_down();

		return true;
	}

	/** One turn of the loop: accept, read, keep alive, beat. */
	private function tick(): void {
		$read = [ $this->listener ];
		foreach ( $this->clients as $id => $client ) {
			$read[ $id ] = $client['stream'];
		}
		$write  = null;
		$except = null;

		// A second at a time, so signals and the heartbeat are never far away.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A signal interrupts select; that is not an error.
		$ready = @stream_select( $read, $write, $except, 1 );
		if ( false === $ready ) {
			return;
		}

		foreach ( $read as $stream ) {
			if ( $stream === $this->listener ) {
				$this->accept();
				continue;
			}
			// Connections are keyed by the stream's own id, so there is nothing
			// to search for.
			$id = (int) $stream;
			if ( isset( $this->clients[ $id ] ) ) {
				$this->receive( $id );
			}
		}

		$this->keep_alive();
		$this->beat();
	}

	private function accept(): void {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Nothing to accept is normal.
		$stream = @stream_socket_accept( $this->listener, 0 );
		if ( ! $stream ) {
			return;
		}

		stream_set_blocking( $stream, false );
		$id                   = (int) $stream;
		$this->clients[ $id ] = [
			'stream'    => $stream,
			'buffer'    => '',
			'message'   => '',
			'ready'     => false,
			'channels'  => [],
			'is_push'   => false,
			'last_seen' => time(),
			'pinged'    => false,
		];
	}

	private function receive( int $id ): void {
		$client = $this->clients[ $id ] ?? null;
		if ( ! $client ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		$chunk = @fread( $client['stream'], 8192 );
		if ( '' === $chunk || false === $chunk ) {
			if ( feof( $client['stream'] ) ) {
				$this->drop( $id, 'eof' );
			}
			return;
		}

		$this->clients[ $id ]['buffer']    = $client['buffer'] . $chunk;
		$this->clients[ $id ]['last_seen'] = time();
		$this->clients[ $id ]['pinged']    = false;

		if ( ! $this->clients[ $id ]['ready'] ) {
			$this->handshake( $id );
			return;
		}

		$this->read_frames( $id );
	}

	/**
	 * Answer the opening HTTP request, and decide what this connection may
	 * listen to. Anything we cannot place is shown the door.
	 */
	private function handshake( int $id ): void {
		$buffer = $this->clients[ $id ]['buffer'];
		if ( false === strpos( $buffer, "\r\n\r\n" ) ) {
			// Someone is sending something that is not a request at all.
			if ( strlen( $buffer ) > 8192 ) {
				$this->drop( $id, 'garbage' );
			}
			return;
		}

		$head  = substr( $buffer, 0, strpos( $buffer, "\r\n\r\n" ) );
		$lines = explode( "\r\n", $head );
		$start = (string) array_shift( $lines );

		$headers = [];
		foreach ( $lines as $line ) {
			$colon = strpos( $line, ':' );
			if ( false !== $colon ) {
				$headers[ strtolower( trim( substr( $line, 0, $colon ) ) ) ] = trim( substr( $line, $colon + 1 ) );
			}
		}

		if ( ! preg_match( '#^GET\s+(\S+)\s+HTTP/1\.1$#i', $start, $parts ) || empty( $headers['sec-websocket-key'] ) ) {
			$this->refuse( $id, '400 Bad Request' );
			return;
		}

		$target = $parts[1];
		$query  = [];
		parse_str( (string) wp_parse_url( $target, PHP_URL_QUERY ), $query );

		if ( rtrim( (string) wp_parse_url( $target, PHP_URL_PATH ), '/' ) !== Client::PATH ) {
			$this->refuse( $id, '404 Not Found' );
			return;
		}

		$channels = $this->channels_for( $query );
		if ( null === $channels ) {
			$this->refuse( $id, '403 Forbidden' );
			return;
		}

		$this->clients[ $id ]['ready']    = true;
		$this->clients[ $id ]['channels'] = $channels['channels'];
		$this->clients[ $id ]['is_push']  = $channels['is_push'];
		$this->clients[ $id ]['buffer']   = substr( $buffer, strpos( $buffer, "\r\n\r\n" ) + 4 );

		$this->write_raw(
			$id,
			"HTTP/1.1 101 Switching Protocols\r\n"
			. "Upgrade: websocket\r\n"
			. "Connection: Upgrade\r\n"
			. 'Sec-WebSocket-Accept: ' . Frames::accept_key( $headers['sec-websocket-key'] ) . "\r\n\r\n"
		);

		if ( ! $channels['is_push'] ) {
			$this->send( $id, [
				'type'     => 'welcome',
				'channels' => $channels['channels'],
			] );
		}

		// A push may already be in the same packet as its handshake.
		if ( '' !== $this->clients[ $id ]['buffer'] ) {
			$this->read_frames( $id );
		}
	}

	/**
	 * What this connection is allowed to hear, or null when it proves nothing.
	 *
	 * @param array<string,mixed> $query
	 * @return array{channels:array<int,string>,is_push:bool}|null
	 */
	private function channels_for( array $query ): ?array {
		$push_key = (string) ( $query['push_key'] ?? '' );
		if ( '' !== $push_key ) {
			return hash_equals( Client::secret(), $push_key )
				? [
					'channels' => [],
					'is_push'  => true,
				]
				: null;
		}

		$visitor = (string) ( $query['visitor'] ?? '' );
		if ( '' !== $visitor ) {
			$id = $this->visitor_from_token( $visitor );
			return null === $id ? null : [
				'channels' => [ $id ],
				'is_push'  => false,
			];
		}

		$agent = (string) ( $query['agent'] ?? '' );
		if ( '' !== $agent && Client::agent_from_token( $agent ) > 0 ) {
			return [
				'channels' => [ 'agents' ],
				'is_push'  => false,
			];
		}

		return null;
	}

	/**
	 * The visitor a widget token proves. The same signature the REST endpoints
	 * check, so a token that works there works here and nowhere else.
	 */
	private function visitor_from_token( string $token ): ?string {
		$request = new \WP_REST_Request( 'GET', '/' );
		$request->set_header( 'x-zaplane-visitor', $token );

		// A signed-in visitor's token is tied to the session that made it, and
		// this process has no session: check the signature only, and never
		// accept one without it.
		$id = WidgetController::visitor_from( $request );
		if ( null !== $id ) {
			return $id;
		}

		return null;
	}

	private function read_frames( int $id ): void {
		$result = Frames::decode( $this->clients[ $id ]['buffer'] );
		if ( '' !== $result['error'] ) {
			$this->drop( $id, 'bad-frame' );
			return;
		}
		$this->clients[ $id ]['buffer'] = $result['rest'];

		foreach ( $result['frames'] as $frame ) {
			switch ( $frame['opcode'] ) {
				case Frames::OP_CLOSE:
					$this->drop( $id, 'client-close' );
					return;
				case Frames::OP_PING:
					$this->write_raw( $id, Frames::encode( $frame['payload'], Frames::OP_PONG ) );
					break;
				case Frames::OP_PONG:
					break;
				case Frames::OP_CONTINUE:
				case Frames::OP_TEXT:
					$this->clients[ $id ]['message'] .= $frame['payload'];
					if ( $frame['fin'] ) {
						$message                         = $this->clients[ $id ]['message'];
						$this->clients[ $id ]['message'] = '';
						$this->handle( $id, $message );
					}
					break;
			}
		}
	}

	/** One message from a client. */
	private function handle( int $id, string $raw ): void {
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return;
		}

		$action = (string) ( $data['action'] ?? '' );

		if ( 'publish' === $action ) {
			// Only the site may publish, and only ever to one channel.
			if ( empty( $this->clients[ $id ]['is_push'] ) ) {
				$this->drop( $id, 'not-a-publisher' );
				return;
			}
			$this->publish(
				(string) ( $data['channel'] ?? '' ),
				(string) ( $data['event'] ?? 'message' ),
				is_array( $data['data'] ?? null ) ? $data['data'] : []
			);
			return;
		}

		if ( 'ping' === $action ) {
			$this->send( $id, [ 'type' => 'pong' ] );
		}
	}

	/** Pass a notice to everyone listening on a channel. */
	private function publish( string $channel, string $event, array $data ): void {
		if ( '' === $channel ) {
			return;
		}

		$message = [
			'type'    => 'event',
			'channel' => $channel,
			'event'   => $event,
			'data'    => $data,
		];

		$sent = 0;
		foreach ( $this->clients as $id => $client ) {
			if ( ! $client['ready'] || $client['is_push'] || ! in_array( $channel, $client['channels'], true ) ) {
				continue;
			}
			$this->send( $id, $message );
			++$sent;
		}

		// The team's own channel hears about every conversation, so the inbox
		// updates without anyone having it open on the visitor's page.
		if ( 'agents' !== $channel ) {
			foreach ( $this->clients as $id => $client ) {
				if ( $client['ready'] && ! $client['is_push'] && in_array( 'agents', $client['channels'], true ) ) {
					$this->send( $id, $message );
					++$sent;
				}
			}
		}

		$this->log( sprintf( 'published %s on %s to %d listener(s)', $event, $channel, $sent ) );
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	private function send( int $id, array $payload ): void {
		$this->write_raw( $id, Frames::encode( (string) wp_json_encode( $payload ) ) );
	}

	private function write_raw( int $id, string $bytes ): void {
		if ( ! isset( $this->clients[ $id ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		$written = @fwrite( $this->clients[ $id ]['stream'], $bytes );
		if ( false === $written ) {
			$this->drop( $id, 'write-failed' );
		}
	}

	/** Ping the quiet ones; drop the ones that never answer. */
	private function keep_alive(): void {
		$now = time();
		foreach ( $this->clients as $id => $client ) {
			$silent = $now - $client['last_seen'];
			if ( $silent > self::DROP_AFTER ) {
				$this->drop( $id, 'silent' );
				continue;
			}
			if ( $client['ready'] && ! $client['is_push'] && ! $client['pinged'] && $silent > self::PING_AFTER ) {
				$this->clients[ $id ]['pinged'] = true;
				$this->write_raw( $id, Frames::encode( '', Frames::OP_PING ) );
			}
		}
	}

	private function drop( int $id, string $why = '' ): void {
		if ( ! isset( $this->clients[ $id ] ) ) {
			return;
		}
		if ( '' !== $why && ! in_array( $why, [ 'client-close', 'eof' ], true ) ) {
			$this->log( sprintf( 'dropped a connection: %s', $why ) );
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Already-closed streams are fine.
		@fclose( $this->clients[ $id ]['stream'] );
		unset( $this->clients[ $id ] );
	}

	/* ---------------------------------------------------------------- *
	 * What the site can see from outside
	 * ---------------------------------------------------------------- */

	private function started(): void {
		update_option( Client::OPT_STATUS, 'running', false );
		update_option( Client::OPT_HOST, $this->host, false );
		update_option( Client::OPT_PORT, $this->port, false );
		update_option( Client::OPT_HEARTBEAT, time(), false );
		Client::secret();
		$this->last_beat = time();
	}

	/**
	 * Say we are still here. Also the moment to check the database is: a long
	 * idle night can have MySQL hang up on us, and the next push would be the
	 * thing that discovered it.
	 */
	private function beat(): void {
		if ( time() - $this->last_beat < Client::HEARTBEAT_EVERY ) {
			return;
		}
		$this->last_beat = time();

		global $wpdb;
		if ( isset( $wpdb ) && method_exists( $wpdb, 'check_connection' ) ) {
			$wpdb->check_connection( false );
		}

		update_option( Client::OPT_HEARTBEAT, time(), false );
	}

	private function shut_down(): void {
		foreach ( array_keys( $this->clients ) as $id ) {
			$this->write_raw( $id, Frames::encode( pack( 'n', 1001 ), Frames::OP_CLOSE ) );
			$this->drop( $id );
		}
		if ( $this->listener ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@fclose( $this->listener );
		}
		update_option( Client::OPT_STATUS, 'stopped', false );
		delete_option( Client::OPT_HEARTBEAT );
	}

	private function refuse( int $id, string $status ): void {
		$this->write_raw( $id, "HTTP/1.1 {$status}\r\nConnection: close\r\nContent-Length: 0\r\n\r\n" );
		$this->drop( $id );
	}

	private function log( string $message ): void {
		if ( is_callable( $this->logger ) ) {
			call_user_func( $this->logger, $message );
		}
	}

	/** How many connections are open, for the status command. */
	public function connection_count(): int {
		return count( $this->clients );
	}
}
