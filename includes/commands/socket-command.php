<?php
/**
 * `wp zaplane socket ...` — the realtime chat server.
 *
 * @package Zaplane
 */

namespace Zaplane\Commands;

use Zaplane\Framework\Console\Command;
use Zaplane\Socket\Client;
use Zaplane\Socket\Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the process that keeps chat connections open.
 *
 * WordPress answers a request and stops; a chat that updates the moment a
 * reply is written needs something that stays. This is that something. It is
 * optional — with nothing running, the chat polls instead.
 *
 * ## EXAMPLES
 *
 *     # Run it in the foreground (Ctrl-C stops it).
 *     $ wp zaplane socket start --port=8088
 *
 *     # Is it up, and is the site able to reach it?
 *     $ wp zaplane socket status
 *
 *     # Check a running server end to end.
 *     $ wp zaplane socket test
 */
class SocketCommand extends Command {

	protected string $signature = 'socket';
	protected string $description = 'Run the realtime chat server (start, status, stop, test)';

	public function handle( array $args, array $assoc_args ): void {
		switch ( $args[0] ?? 'status' ) {
			case 'start':
				$this->start( $assoc_args );
				break;
			case 'status':
				$this->status();
				break;
			case 'stop':
				$this->stop();
				break;
			case 'test':
				$this->test();
				break;
			default:
				$this->error( 'Use: wp zaplane socket start|status|stop|test' );
		}
	}

	/**
	 * @param array<string,mixed> $assoc_args
	 */
	private function start( array $assoc_args ): void {
		$host = (string) ( $assoc_args['bind'] ?? '127.0.0.1' );
		$port = absint( $assoc_args['port'] ?? 8088 );

		if ( $port <= 0 || $port > 65535 ) {
			$this->error( 'Give a port between 1 and 65535.' );
			return;
		}

		if ( Client::is_live() ) {
			$this->info( sprintf( 'Another server is already running on %s:%d. Stop it first.', (string) get_option( Client::OPT_HOST ), absint( get_option( Client::OPT_PORT ) ) ) );
		}

		// A long-running process: nothing may hold it up, and each turn of the
		// loop should cost as little as possible.
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 0 );
		}
		ignore_user_abort( true );

		$this->info( sprintf( 'Starting the chat server on %s:%d — Ctrl-C to stop.', $host, $port ) );
		$this->info( 'Point the browser at it in Inbox → Settings → Website chat → Realtime.' );

		$server = new Server( $host, $port, function ( string $line ) {
			$this->info( gmdate( '[H:i:s] ' ) . $line );
		} );

		if ( ! $server->run() ) {
			$this->error( 'The server could not start. Is the port already in use?' );
		}
	}

	private function status(): void {
		$live = Client::is_live();
		$host = (string) get_option( Client::OPT_HOST, '' );
		$port = absint( get_option( Client::OPT_PORT, 0 ) );
		$beat = absint( get_option( Client::OPT_HEARTBEAT, 0 ) );

		$this->info( $live ? 'Running.' : 'Not running.' );
		if ( $host && $port ) {
			$this->info( sprintf( 'Address: ws://%s:%d%s', $host, $port, Client::PATH ) );
		}
		if ( $beat ) {
			$this->info( sprintf( 'Last heartbeat: %ds ago.', max( 0, time() - $beat ) ) );
		}

		$public = Client::public_url();
		$this->info( 'Browser address: ' . ( '' !== $public ? $public : '(not set — the widget uses the address above)' ) );

		$config = Client::widget_config();
		$this->info( $config ? 'The chat will connect in the browser.' : 'The chat will poll (realtime off, no server, or the address is not usable from an HTTPS page).' );

		$error = get_option( Client::OPT_LAST_ERROR );
		if ( is_array( $error ) && ! empty( $error['error'] ) ) {
			$this->info( sprintf( 'Last failed push: %s (%s ago)', (string) $error['error'], human_time_diff( (int) $error['at'] ) ) );
		}
	}

	private function stop(): void {
		// The process owns the status while it runs; this is for the leftovers
		// of one that was killed.
		if ( Client::is_live() ) {
			$this->info( 'A server is still beating. Stop that process (Ctrl-C, or kill it) — this only clears the record.' );
		}
		update_option( Client::OPT_STATUS, 'stopped', false );
		delete_option( Client::OPT_HEARTBEAT );
		$this->success( 'Marked as stopped.' );
	}

	/**
	 * Push a notice through a running server, proving the whole path works:
	 * the site can reach it, the secret matches, and it accepts a publish.
	 */
	private function test(): void {
		if ( ! Client::is_live() ) {
			$this->error( 'No server is running. Start one with: wp zaplane socket start' );
			return;
		}

		$sent = Client::push( 'agents', 'test', [ 'at' => time() ] );
		if ( $sent ) {
			$this->success( 'The server took the notice. Realtime delivery is working.' );
			return;
		}

		$error = get_option( Client::OPT_LAST_ERROR );
		$this->error( 'The push failed: ' . ( is_array( $error ) ? (string) ( $error['error'] ?? 'unknown' ) : 'unknown' ) );
	}
}
