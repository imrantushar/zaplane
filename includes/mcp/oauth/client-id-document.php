<?php

namespace Zaplane\Mcp\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clients that identify themselves by URL instead of registering.
 *
 * The client_id is an https URL serving a JSON document describing the client.
 * Nothing is stored here — the document is the registration — which is why the
 * connector dialogs recommend it: a busy server otherwise accumulates a row for
 * every client that ever connects.
 *
 * The whole risk is in the fetch. A stranger chooses the URL and this server
 * makes the request, so an unguarded implementation is a way to reach whatever
 * the site can reach and nobody else can. wp_safe_remote_get() refuses private
 * and reserved addresses, redirects are not followed, the read is capped, and
 * only a 200 counts.
 *
 * @see https://datatracker.ietf.org/doc/html/draft-ietf-oauth-client-id-metadata-document
 */
class ClientIdDocument {

	/** The draft recommends no more than this. */
	private const MAX_BYTES = 5120;

	private const TIMEOUT = 5;

	/** Successes are cached; failures deliberately are not. */
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Whether this identifier is a Client Identifier URL at all.
	 *
	 * Required shape: the https scheme, a path component, and no fragment.
	 * Anything else is one of our own zpc_ ids, or nothing at all.
	 *
	 * @param string $client_id The presented identifier.
	 */
	public static function is_client_id_url( string $client_id ): bool {
		if ( 0 !== stripos( $client_id, 'https://' ) ) {
			return false;
		}

		$parts = wp_parse_url( $client_id );

		if ( ! is_array( $parts ) || ! empty( $parts['fragment'] ) ) {
			return false;
		}

		// A path is required, so https://example.com alone is not one.
		return '' !== trim( (string) ( $parts['path'] ?? '' ), '/' );
	}

	/**
	 * Fetch and validate the document, returning a client shaped like a
	 * registered one so callers do not care which kind they were given.
	 *
	 * @param string $client_id The Client Identifier URL.
	 * @return array<string,mixed>|null Null when it cannot be trusted.
	 */
	public static function resolve( string $client_id ): ?array {
		if ( ! self::is_client_id_url( $client_id ) ) {
			return null;
		}

		$cache_key = 'zaplane_mcp_cidoc_' . md5( $client_id );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// wp_safe_remote_get validates the host against private and reserved
		// ranges before connecting, which is the guard that matters here.
		$response = wp_safe_remote_get(
			$client_id,
			[
				'timeout'             => self::TIMEOUT,
				// The draft is explicit: a redirect is not followed. A client id
				// that answers 302 is not the document it claims to be.
				'redirection'         => 0,
				'limit_response_size' => self::MAX_BYTES,
				'headers'             => [ 'Accept' => 'application/json' ],
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$document = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $document ) ) {
			return null;
		}

		$client = self::shape( $client_id, $document );

		if ( null !== $client ) {
			// Only successes are cached. An error answer must not become the
			// client's identity for the next hour.
			set_transient( $cache_key, $client, self::CACHE_TTL );
		}

		return $client;
	}

	/**
	 * Turn a fetched document into a client, or refuse it.
	 *
	 * @param string              $client_id The URL it was fetched from.
	 * @param array<string,mixed> $document  What came back.
	 * @return array<string,mixed>|null
	 */
	private static function shape( string $client_id, array $document ): ?array {
		// The document must name itself, and name itself as the URL it was found
		// at. Without this, any JSON anywhere could claim any identity.
		if ( ! isset( $document['client_id'] ) || ! hash_equals( $client_id, (string) $document['client_id'] ) ) {
			return null;
		}

		$redirects = array_values(
			array_filter(
				array_map( 'strval', (array) ( $document['redirect_uris'] ?? [] ) ),
				[ ClientStore::class, 'is_valid_redirect' ]
			)
		);

		if ( empty( $redirects ) ) {
			return null;
		}

		return [
			'client_id'     => $client_id,
			'client_name'   => sanitize_text_field( (string) ( $document['client_name'] ?? $client_id ) ),
			'redirect_uris' => $redirects,
			'created_at'    => '',
			'by_url'        => true,
		];
	}
}
