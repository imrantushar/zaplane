<?php

namespace Zaplane\Socket;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One short-lived WebSocket connection, used by PHP to hand the server a
 * notice and hang up. Nothing here waits for a reply: the push is
 * fire-and-forget on a request that is busy doing something else (saving a
 * chat message), so it is kept to a couple of seconds at worst.
 */
class Connection {

	public string $error = '';

	private string $url;

	private int $timeout;

	public function __construct( string $url, int $timeout = 3 ) {
		$this->url     = $url;
		$this->timeout = $timeout;
	}

	/**
	 * Open, handshake, send one text frame, close.
	 *
	 * @param array<string,mixed> $payload
	 */
	public function send( array $payload ): bool {
		$parts  = wp_parse_url( $this->url );
		$scheme = strtolower( (string) ( $parts['scheme'] ?? 'ws' ) );
		$host   = (string) ( $parts['host'] ?? '127.0.0.1' );
		$port   = (int) ( $parts['port'] ?? ( 'wss' === $scheme ? 443 : 80 ) );
		$path   = (string) ( $parts['path'] ?? '/' );
		if ( ! empty( $parts['query'] ) ) {
			$path .= '?' . $parts['query'];
		}

		$transport = 'wss' === $scheme ? 'ssl://' : '';
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A refused connection is an expected outcome, reported below.
		$socket = @stream_socket_client( $transport . $host . ':' . $port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT );

		if ( ! $socket ) {
			$this->error = sprintf( 'could not reach %s:%d (%s)', $host, $port, $errstr ?: $errno );
			return false;
		}

		stream_set_timeout( $socket, $this->timeout );

		$key     = base64_encode( random_bytes( 16 ) );
		$request = "GET {$path} HTTP/1.1\r\n"
			. "Host: {$host}:{$port}\r\n"
			. "Upgrade: websocket\r\n"
			. "Connection: Upgrade\r\n"
			. "Sec-WebSocket-Key: {$key}\r\n"
			. "Sec-WebSocket-Version: 13\r\n\r\n";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( false === @fwrite( $socket, $request ) ) {
			$this->error = 'the handshake could not be sent';
			fclose( $socket );
			return false;
		}

		$response = '';
		while ( false === strpos( $response, "\r\n\r\n" ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			$chunk = fgets( $socket, 1024 );
			if ( false === $chunk || '' === $chunk ) {
				break;
			}
			$response .= $chunk;
		}

		if ( false === stripos( $response, ' 101 ' ) ) {
			$this->error = 'the server refused the connection: ' . trim( strtok( $response, "\r\n" ) ?: 'no answer' );
			fclose( $socket );
			return false;
		}

		$frame = Frames::encode( (string) wp_json_encode( $payload ), Frames::OP_TEXT, true );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		$written = @fwrite( $socket, $frame );

		// Ask politely to close so the server doesn't log a dropped connection.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		@fwrite( $socket, Frames::encode( pack( 'n', 1000 ), Frames::OP_CLOSE, true ) );
		fclose( $socket );

		if ( false === $written ) {
			$this->error = 'the notice could not be written';
			return false;
		}

		return true;
	}
}
