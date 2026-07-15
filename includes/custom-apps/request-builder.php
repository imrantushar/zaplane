<?php
namespace Zaplane\CustomApps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a manifest request template + resolution context into a concrete
 * { method, url, headers, body } ready for the HttpClient.
 *
 * Handles three things the template alone doesn't: resolving a relative `path`
 * against the app's `base_url`, merging query parameters onto the URL, and
 * injecting authentication (bearer/api-key/basic/oauth2) from the connection
 * credentials.
 */
class RequestBuilder {

	/**
	 * @param array               $request  The request template ({ method, path|url, headers, body, query }).
	 * @param array               $manifest The full manifest (for base_url + auth).
	 * @param array<string,mixed> $context  Output of Template::build_context().
	 * @return array{method:string,url:string,headers:array,body:mixed}
	 */
	public static function build( array $request, array $manifest, array $context ): array {
		$method   = isset( $request['method'] ) ? strtoupper( (string) $request['method'] ) : 'GET';
		$headers  = self::to_array( Template::interpolate( $request['headers'] ?? [], $context ) );
		$query    = self::to_array( Template::interpolate( $request['query'] ?? [], $context ) );
		$body     = array_key_exists( 'body', $request ) ? Template::interpolate( $request['body'], $context ) : null;
		$url      = self::resolve_url( $request, $manifest, $context );

		// Auth may add headers and/or query params.
		self::apply_auth( $manifest['auth'] ?? [], $context, $headers, $query );

		if ( ! empty( $query ) ) {
			$url = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $query ) ), $url );
		}

		return [
			'method'  => $method,
			'url'     => $url,
			'headers' => $headers,
			'body'    => $body,
		];
	}

	/**
	 * Resolve the absolute request URL from either an explicit `url` or a
	 * `path` joined onto the manifest base_url. Both are interpolated first.
	 *
	 * @param array<string,mixed> $context
	 */
	protected static function resolve_url( array $request, array $manifest, array $context ): string {
		$explicit = isset( $request['url'] ) ? (string) $request['url'] : '';
		if ( '' !== $explicit ) {
			return (string) Template::interpolate( $explicit, $context );
		}

		$base = isset( $manifest['base_url'] ) ? rtrim( (string) $manifest['base_url'], '/' ) : '';
		$path = isset( $request['path'] ) ? (string) Template::interpolate( (string) $request['path'], $context ) : '';

		if ( '' === $path ) {
			return $base;
		}
		if ( '' === $base ) {
			return $path;
		}

		return $base . '/' . ltrim( $path, '/' );
	}

	/**
	 * Inject authentication into the outgoing headers/query.
	 *
	 * - basic:   builds an Authorization: Basic header from creds.username/password.
	 * - oauth2:  defaults to Authorization: Bearer {{creds.access_token}}.
	 * - others:  driven entirely by the manifest's auth.inject definition
	 *            ({ in: 'header'|'query', name, template }).
	 *
	 * @param array<string,mixed>  $auth
	 * @param array<string,mixed>  $context
	 * @param array<string,string> $headers Passed by reference.
	 * @param array<string,string> $query   Passed by reference.
	 */
	protected static function apply_auth( array $auth, array $context, array &$headers, array &$query ): void {
		$type  = isset( $auth['type'] ) ? (string) $auth['type'] : 'none';
		$creds = isset( $context['creds'] ) && is_array( $context['creds'] ) ? $context['creds'] : [];

		if ( 'none' === $type || empty( $creds ) ) {
			// Still honour an explicit inject even without a typed auth.
			self::apply_inject( $auth['inject'] ?? null, $context, $headers, $query );
			return;
		}

		if ( 'basic' === $type ) {
			$user = (string) ( $creds['username'] ?? '' );
			$pass = (string) ( $creds['password'] ?? '' );
			if ( '' !== $user || '' !== $pass ) {
				$headers['Authorization'] = 'Basic ' . base64_encode( $user . ':' . $pass );
			}
			return;
		}

		// A manifest-defined inject always wins (lets api_key/bearer name their field).
		if ( ! empty( $auth['inject'] ) ) {
			self::apply_inject( $auth['inject'], $context, $headers, $query );
			return;
		}

		// Sensible default for OAuth2 / bearer when no explicit inject is given.
		if ( 'oauth2' === $type || 'bearer' === $type ) {
			$token = (string) ( $creds['access_token'] ?? $creds['token'] ?? '' );
			if ( '' !== $token ) {
				$headers['Authorization'] = 'Bearer ' . $token;
			}
		}
	}

	/**
	 * Apply a single inject descriptor.
	 *
	 * @param mixed                $inject
	 * @param array<string,mixed>  $context
	 * @param array<string,string> $headers Passed by reference.
	 * @param array<string,string> $query   Passed by reference.
	 */
	protected static function apply_inject( $inject, array $context, array &$headers, array &$query ): void {
		if ( ! is_array( $inject ) || empty( $inject['name'] ) ) {
			return;
		}

		$name  = (string) $inject['name'];
		$value = isset( $inject['template'] ) ? (string) Template::interpolate( (string) $inject['template'], $context ) : '';
		$in    = isset( $inject['in'] ) ? (string) $inject['in'] : 'header';

		if ( 'query' === $in ) {
			$query[ $name ] = $value;
		} else {
			$headers[ $name ] = $value;
		}
	}

	/**
	 * Coerce an interpolated value to an associative array (empty on mismatch).
	 *
	 * @param mixed $value
	 * @return array
	 */
	protected static function to_array( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );
			return is_array( $decoded ) ? $decoded : [];
		}
		return [];
	}
}
