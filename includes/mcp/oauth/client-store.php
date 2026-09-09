<?php

namespace Zaplane\Mcp\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clients registered dynamically by an MCP client (RFC 7591).
 *
 * A hosted connector — claude.ai, ChatGPT — cannot be given a client id by hand,
 * because nobody is there to paste one in. It registers itself, gets an id back,
 * and then runs the authorization-code flow. Registration alone grants nothing:
 * a client only becomes useful once a signed-in administrator approves it on the
 * consent screen, so an open registration endpoint costs nothing but a row.
 *
 * Every client is public — no secret. Confidential clients would need somewhere
 * safe to keep one, which a browser-based connector does not have; PKCE is what
 * protects the exchange instead.
 */
class ClientStore {

	private const OPTION = 'zaplane_mcp_oauth_clients';

	/** Registrations are capped so an open endpoint cannot grow the option forever. */
	private const MAX_CLIENTS = 50;

	/**
	 * @param array<string,mixed> $metadata
	 * @return array<string,mixed>
	 * @throws \InvalidArgumentException When the redirect URIs are unusable.
	 */
	public static function register( array $metadata ): array {
		$redirects = array_values(
			array_filter(
				array_map( 'strval', (array) ( $metadata['redirect_uris'] ?? [] ) ),
				[ self::class, 'is_valid_redirect' ]
			)
		);

		if ( empty( $redirects ) ) {
			throw new \InvalidArgumentException( 'redirect_uris must contain at least one https (or loopback http) URI without a fragment.' );
		}

		$name = sanitize_text_field( (string) ( $metadata['client_name'] ?? 'MCP client' ) );

		// A connector that re-registers on every reconnect — several do — would
		// otherwise push a new row each time and walk the whole cap in a week.
		// Handing back the existing registration costs nothing: a client_id is
		// public, and the client is still useless until an administrator approves
		// it for this particular authorization, with its own PKCE challenge.
		$existing = self::find( $name, $redirects );
		if ( null !== $existing ) {
			return $existing;
		}

		$client = [
			'client_id'     => 'zpc_' . strtolower( wp_generate_password( 20, false ) ),
			'client_name'   => $name,
			'redirect_uris' => $redirects,
			'created_at'    => current_time( 'mysql' ),
		];

		$clients = self::all();
		$clients[ $client['client_id'] ] = $client;

		// Oldest first out, so a burst of registrations cannot push out the client
		// someone is actually using before the older ones go.
		if ( count( $clients ) > self::MAX_CLIENTS ) {
			$clients = array_slice( $clients, -self::MAX_CLIENTS, null, true );
		}

		update_option( self::OPTION, $clients, false );

		return $client;
	}

	/**
	 * An already-registered client with exactly this name and these redirects.
	 *
	 * @param string            $name      Client name as registered.
	 * @param array<int,string> $redirects Redirect URIs, in any order.
	 * @return array<string,mixed>|null
	 */
	private static function find( string $name, array $redirects ): ?array {
		sort( $redirects );

		foreach ( self::all() as $client ) {
			$known = (array) $client['redirect_uris'];
			sort( $known );

			if ( (string) $client['client_name'] === $name && $known === $redirects ) {
				return $client;
			}
		}

		return null;
	}

	/**
	 * @param string $client_id Identifier handed out at registration.
	 * @return array<string,mixed>|null
	 */
	public static function get( string $client_id ): ?array {
		$clients = self::all();
		return $clients[ $client_id ] ?? null;
	}

	/**
	 * A redirect URI has to match one the client registered, exactly.
	 *
	 * Comparing whole strings rather than prefixes is deliberate: prefix matching
	 * is how an open redirector becomes a token thief.
	 *
	 * @param array<string,mixed> $client       The stored registration.
	 * @param string              $redirect_uri The address being asked for.
	 */
	public static function redirect_allowed( array $client, string $redirect_uri ): bool {
		foreach ( (array) $client['redirect_uris'] as $known ) {
			if ( hash_equals( (string) $known, $redirect_uri ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		$raw = get_option( self::OPTION, [] );
		return is_array( $raw ) ? $raw : [];
	}

	public static function forget( string $client_id ): bool {
		$clients = self::all();
		if ( ! isset( $clients[ $client_id ] ) ) {
			return false;
		}

		unset( $clients[ $client_id ] );
		update_option( self::OPTION, $clients, false );

		return true;
	}

	/**
	 * HTTPS everywhere, except loopback, which is how a desktop client receives
	 * its redirect. A fragment is never valid on a redirect URI.
	 *
	 * @param string $uri Candidate redirect URI.
	 */
	private static function is_valid_redirect( string $uri ): bool {
		$parts = wp_parse_url( $uri );

		if ( ! $parts || empty( $parts['scheme'] ) || ! empty( $parts['fragment'] ) ) {
			return false;
		}

		$scheme = strtolower( $parts['scheme'] );
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );

		if ( 'https' === $scheme ) {
			return '' !== $host;
		}

		if ( 'http' === $scheme ) {
			return in_array( $host, [ '127.0.0.1', '::1', 'localhost' ], true );
		}

		// A private-use scheme (com.example.app:/cb) is how a native client is
		// reached; it has no host to check.
		return (bool) preg_match( '/^[a-z][a-z0-9+.\-]*$/', $scheme );
	}
}
