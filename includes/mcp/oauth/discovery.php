<?php

namespace Zaplane\Mcp\OAuth;

use Zaplane\Mcp\TokenStore;
use Zaplane\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The two documents a client reads before it can authenticate at all.
 *
 * A hosted connector is handed nothing but the MCP endpoint URL. It calls it,
 * gets a 401, and follows the `resource_metadata` pointer in the challenge to
 * the protected-resource document (RFC 9728), which names the authorization
 * server; it then reads that server's metadata (RFC 8414) to find where to
 * register, authorize and get a token. Without these it can only report that the
 * server "does not implement OAuth", which is exactly what claude.ai and ChatGPT
 * were saying.
 *
 * They live at `/.well-known/...` on the domain root, which is outside the REST
 * namespace, so they are served from parse_request before WordPress decides the
 * URL is a 404 or worth a canonical redirect.
 */
class Discovery {

	public const PROTECTED_RESOURCE = 'oauth-protected-resource';
	public const AUTHORIZATION_SERVER = 'oauth-authorization-server';

	public static function boot(): void {
		add_action( 'parse_request', [ self::class, 'maybe_serve' ], 0 );
	}

	/** The URL a 401 points at, so the client can find everything else. */
	public static function protected_resource_url(): string {
		return home_url( '/.well-known/' . self::PROTECTED_RESOURCE );
	}

	public static function issuer(): string {
		return untrailingslashit( home_url() );
	}

	public static function resource_url(): string {
		return rest_url( 'zaplane/v1/mcp' );
	}

	public static function maybe_serve(): void {
		$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
		$path = trim( $path, '/' );

		if ( 0 !== strpos( $path, '.well-known/' ) ) {
			return;
		}

		// RFC 9728 allows the protected resource's own path to be appended, so
		// match on the prefix rather than the whole string.
		$name = substr( $path, strlen( '.well-known/' ) );

		if ( 0 === strpos( $name, self::PROTECTED_RESOURCE ) ) {
			self::respond( self::protected_resource_document() );
		}

		if ( 0 === strpos( $name, self::AUTHORIZATION_SERVER ) ) {
			self::respond( self::authorization_server_document() );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function protected_resource_document(): array {
		return [
			'resource'                 => self::resource_url(),
			'authorization_servers'    => [ self::issuer() ],
			'scopes_supported'         => TokenStore::ALL_SCOPES,
			'bearer_methods_supported' => [ 'header' ],
			'resource_name'            => 'Zaplane',
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function authorization_server_document(): array {
		return [
			'issuer'                                => self::issuer(),
			// Not a REST route: the consent screen needs the signed-in cookie user,
			// which the REST stack discards when no wp_rest nonce is present.
			'authorization_endpoint'                => Server::authorize_url(),
			'token_endpoint'                        => rest_url( 'zaplane/v1/oauth/token' ),
			'registration_endpoint'                 => rest_url( 'zaplane/v1/oauth/register' ),
			'revocation_endpoint'                   => rest_url( 'zaplane/v1/oauth/revoke' ),
			'scopes_supported'                      => TokenStore::ALL_SCOPES,
			'response_types_supported'              => [ 'code' ],
			'grant_types_supported'                 => [ 'authorization_code', 'refresh_token' ],
			// S256 only. "plain" exists in the spec and protects nothing.
			'code_challenge_methods_supported'      => [ 'S256' ],
			// Every client here is public, so there is no client secret to present.
			'token_endpoint_auth_methods_supported' => [ 'none' ],
		];
	}

	/**
	 * @param array<string,mixed> $document
	 */
	private static function respond( array $document ): void {
		// Discovery has to work before a token exists, so these two documents are
		// deliberately unauthenticated — they carry nothing but public URLs. They
		// are still withheld when the feature is switched off, so a site that has
		// not enabled MCP does not advertise an authorization server.
		if ( ! Settings::feature_enabled( 'mcp_server' ) ) {
			status_header( 404 );
			exit;
		}

		if ( ! headers_sent() ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Cache-Control: public, max-age=3600' );
			// A client may fetch these from a browser context.
			header( 'Access-Control-Allow-Origin: *' );
		}

		echo wp_json_encode( $document, JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON document.
		exit;
	}
}
