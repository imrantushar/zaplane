<?php
namespace Zaplane\CustomApps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single outbound-HTTP choke point for Custom Apps.
 *
 * Every request a custom app makes — actions, polling triggers, connection
 * tests, and the builder's "send test request" preview — goes through here so
 * the SSRF guard is enforced in exactly one place. The guard mirrors the
 * built-in HTTP integration: only http/https schemes, plus the shared
 * `zaplane_http_block_request` filter sites use to block private ranges.
 */
class HttpClient {

	/**
	 * Perform a request.
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $url      Fully-resolved absolute URL.
	 * @param array<string,string> $headers
	 * @param mixed                $body     String, or array (JSON-encoded unless already a string).
	 * @param array<string,mixed>  $args     Extra wp_remote_request args (e.g. timeout).
	 * @return array{status:int,body:mixed,headers:array,error:?string}
	 */
	public static function request( string $method, string $url, array $headers = [], $body = null, array $args = [] ): array {
		$guard = self::guard( $url );
		if ( null !== $guard ) {
			return $guard;
		}

		// Encode array bodies as JSON and default the content type.
		if ( is_array( $body ) ) {
			$body = wp_json_encode( $body );
			if ( ! self::has_header( $headers, 'content-type' ) ) {
				$headers['Content-Type'] = 'application/json';
			}
		}

		$request_args = array_merge(
			[
				'method'  => strtoupper( $method ),
				'headers' => $headers,
				'timeout' => 20,
			],
			$args
		);

		if ( null !== $body && '' !== $body ) {
			$request_args['body'] = $body;
		}

		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return [
				'status'  => 0,
				'body'    => null,
				'headers' => [],
				'error'   => $response->get_error_message(),
			];
		}

		$raw_body = wp_remote_retrieve_body( $response );
		$parsed   = json_decode( $raw_body, true );
		$out_body = JSON_ERROR_NONE === json_last_error() ? $parsed : $raw_body;

		$response_headers = wp_remote_retrieve_headers( $response );
		$headers_array    = is_object( $response_headers ) && method_exists( $response_headers, 'getAll' )
			? $response_headers->getAll()
			: (array) $response_headers;

		return [
			'status'  => (int) wp_remote_retrieve_response_code( $response ),
			'body'    => $out_body,
			'headers' => $headers_array,
			'error'   => null,
		];
	}

	/**
	 * Return an error-shaped result if the URL is not allowed, or null if it is.
	 *
	 * @return array{status:int,body:null,headers:array,error:string}|null
	 */
	protected static function guard( string $url ): ?array {
		$parsed = '' !== $url ? wp_parse_url( $url ) : null;
		$scheme = strtolower( (string) ( $parsed['scheme'] ?? '' ) );

		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return [
				'status'  => 0,
				'body'    => null,
				'headers' => [],
				'error'   => sprintf( 'Request refused: scheme `%s` is not allowed (http, https only).', $scheme ),
			];
		}

		/** This filter is shared with the built-in HTTP integration. */
		if ( apply_filters( 'zaplane_http_block_request', false, $url, $parsed ) ) {
			return [
				'status'  => 0,
				'body'    => null,
				'headers' => [],
				'error'   => 'Request refused by the zaplane_http_block_request filter.',
			];
		}

		return null;
	}

	/**
	 * Case-insensitive header presence check.
	 *
	 * @param array<string,string> $headers
	 */
	protected static function has_header( array $headers, string $name ): bool {
		foreach ( array_keys( $headers ) as $key ) {
			if ( strtolower( (string) $key ) === strtolower( $name ) ) {
				return true;
			}
		}
		return false;
	}
}
