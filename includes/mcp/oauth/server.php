<?php

namespace Zaplane\Mcp\OAuth;

use Zaplane\Mcp\TokenStore;
use Zaplane\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authorization-code flow with PKCE, for MCP clients that cannot be handed a
 * token by hand.
 *
 * The shape is deliberately small. Every client is public, so there is no client
 * secret anywhere; PKCE (S256 only) is what binds the code to the client that
 * asked for it. Codes live for a minute in a transient and are single-use —
 * reading one deletes it, so a replay finds nothing.
 *
 * Consent is a real WordPress screen: whoever approves must be signed in and
 * able to manage this site, and the token is issued against *their* account, so
 * a workflow built through it is attributed to a person rather than to nobody.
 *
 * The consent screen is a front-end URL rather than a REST route on purpose.
 * WordPress zeroes the current user on any REST request that arrives without a
 * `wp_rest` nonce, so a cookie-signed-in administrator would read as logged out
 * there and the login redirect would loop forever. The machine-to-machine
 * endpoints — register, token, revoke — have no such problem and stay on REST.
 */
class Server {

	public const AUTHORIZE_PATH = 'zaplane-oauth/authorize';

	private const CODE_PREFIX = 'zaplane_mcp_authcode_';
	private const CODE_TTL    = MINUTE_IN_SECONDS;

	/** Access tokens are short; the refresh token is what keeps a connector attached. */
	public const TOKEN_TTL = HOUR_IN_SECONDS;

	/** Query parameters the authorization request is made of. */
	private const AUTHORIZE_PARAMS = [
		'response_type',
		'client_id',
		'redirect_uri',
		'state',
		'scope',
		'code_challenge',
		'code_challenge_method',
	];

	public static function boot(): void {
		add_action( 'parse_request', [ self::class, 'maybe_authorize' ], 0 );
	}

	public static function authorize_url(): string {
		return home_url( '/' . self::AUTHORIZE_PATH );
	}

	/* ------------------------------ register ------------------------------ */

	/**
	 * RFC 7591 dynamic client registration.
	 *
	 * @param \WP_REST_Request $request
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function register( $request ) {
		if ( ! Settings::feature_enabled( 'mcp_server' ) ) {
			return new \WP_Error( 'invalid_request', 'The MCP server is not enabled on this site.', [ 'status' => 404 ] );
		}

		$metadata = $request->get_json_params();
		if ( ! is_array( $metadata ) || [] === $metadata ) {
			$metadata = (array) $request->get_body_params();
		}

		try {
			$client = ClientStore::register( $metadata );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'invalid_redirect_uri', $e->getMessage(), [ 'status' => 400 ] );
		}

		return [
			'client_id'                  => $client['client_id'],
			'client_name'                => $client['client_name'],
			'redirect_uris'              => $client['redirect_uris'],
			'token_endpoint_auth_method' => 'none',
			'grant_types'                => [ 'authorization_code', 'refresh_token' ],
			'response_types'             => [ 'code' ],
			'client_id_issued_at'        => time(),
			// Zero means "never expires", per RFC 7591.
			'client_secret_expires_at'   => 0,
		];
	}

	/* ----------------------------- authorize ------------------------------ */

	/** Front-end router: only claims the one path it owns. */
	public static function maybe_authorize(): void {
		$path = (string) wp_parse_url( self::request_uri(), PHP_URL_PATH );

		if ( trim( $path, '/' ) !== self::AUTHORIZE_PATH ) {
			return;
		}

		if ( ! Settings::feature_enabled( 'mcp_server' ) ) {
			return; // Let WordPress answer 404; the site is not advertising OAuth.
		}

		self::authorize();
	}

	/** Render the consent screen, or bounce back to the client with a code. */
	public static function authorize(): void {
		$client_id    = self::query( 'client_id' );
		$redirect_uri = self::query( 'redirect_uri' );
		$state        = self::query( 'state' );
		$challenge    = self::query( 'code_challenge' );
		$method       = strtoupper( self::query( 'code_challenge_method' ) );

		$client = '' !== $client_id ? ClientStore::get( $client_id ) : null;

		// Anything wrong with the client or its redirect is shown here rather than
		// bounced onward: redirecting to an address we have not verified is how a
		// bad redirect_uri turns into a delivery mechanism.
		if ( ! $client ) {
			self::fail_page( __( 'Unknown client. Register the connector again.', 'zaplane' ) );
		}

		if ( '' === $redirect_uri || ! ClientStore::redirect_allowed( $client, $redirect_uri ) ) {
			self::fail_page( __( 'That redirect address is not one this client registered.', 'zaplane' ) );
		}

		if ( 'code' !== self::query( 'response_type' ) ) {
			self::bounce( $redirect_uri, $state, 'unsupported_response_type', 'Only the authorization code flow is supported.' );
		}

		if ( '' === $challenge || 'S256' !== $method ) {
			self::bounce( $redirect_uri, $state, 'invalid_request', 'PKCE with code_challenge_method=S256 is required.' );
		}

		$requested = preg_split( '/[\s,+]+/', self::query( 'scope' ), -1, PREG_SPLIT_NO_EMPTY );
		$scopes    = TokenStore::sanitize_scopes( is_array( $requested ) ? $requested : [] );
		if ( empty( $scopes ) ) {
			$scopes = TokenStore::DEFAULT_SCOPES;
		}

		// Signing in comes first, and has to come back here afterwards.
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::current_url() ) );
			exit;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			self::fail_page( __( 'Only an administrator can connect an AI client to this site.', 'zaplane' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the verb, not form data; the branch it opens checks a nonce first.
		if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
			check_admin_referer( 'zaplane_mcp_consent' );

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- check_admin_referer() above.
			if ( ! isset( $_POST['approve'] ) ) {
				self::bounce( $redirect_uri, $state, 'access_denied', 'The request was declined.' );
			}

			self::grant( $client, $redirect_uri, $state, $challenge, $scopes );
		}

		self::consent_page( $client, $scopes );
	}

	/**
	 * @param array<string,mixed> $client       The approved client.
	 * @param string              $redirect_uri Where the code is delivered.
	 * @param string              $state        Client state, echoed back untouched.
	 * @param string              $challenge    The PKCE challenge the code is bound to.
	 * @param array<int,string>   $scopes       Scopes the administrator approved.
	 */
	private static function grant( array $client, string $redirect_uri, string $state, string $challenge, array $scopes ): void {
		$code = 'zac_' . wp_generate_password( 40, false );

		set_transient(
			self::CODE_PREFIX . hash( 'sha256', $code ),
			[
				'client_id'    => (string) $client['client_id'],
				'client_name'  => (string) $client['client_name'],
				'redirect_uri' => $redirect_uri,
				'challenge'    => $challenge,
				'scopes'       => $scopes,
				'user_id'      => get_current_user_id(),
			],
			self::CODE_TTL
		);

		$args = [ 'code' => rawurlencode( $code ) ];
		if ( '' !== $state ) {
			$args['state'] = rawurlencode( $state );
		}

		// Not wp_safe_redirect: the destination is the client's own address, which
		// is off-site by definition. It is trusted because it was matched exactly
		// against what that client registered, above.
		wp_redirect( add_query_arg( $args, $redirect_uri ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/* ------------------------------- token -------------------------------- */

	/**
	 * @param \WP_REST_Request $request
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function token( $request ) {
		if ( ! Settings::feature_enabled( 'mcp_server' ) ) {
			return new \WP_Error( 'invalid_request', 'The MCP server is not enabled on this site.', [ 'status' => 404 ] );
		}

		$grant = (string) $request->get_param( 'grant_type' );

		if ( 'refresh_token' === $grant ) {
			return self::refresh( $request );
		}

		if ( 'authorization_code' !== $grant ) {
			return new \WP_Error( 'unsupported_grant_type', 'Only authorization_code and refresh_token are supported.', [ 'status' => 400 ] );
		}

		$code   = (string) $request->get_param( 'code' );
		$key    = self::CODE_PREFIX . hash( 'sha256', $code );
		$stored = get_transient( $key );

		// Single use: a code that has been read is gone, so a replay finds nothing
		// even inside its minute.
		delete_transient( $key );

		if ( ! is_array( $stored ) ) {
			return new \WP_Error( 'invalid_grant', 'That code is unknown, already used, or expired.', [ 'status' => 400 ] );
		}

		if ( (string) $request->get_param( 'client_id' ) !== (string) $stored['client_id'] ) {
			return new \WP_Error( 'invalid_grant', 'That code was issued to a different client.', [ 'status' => 400 ] );
		}

		if ( (string) $request->get_param( 'redirect_uri' ) !== (string) $stored['redirect_uri'] ) {
			return new \WP_Error( 'invalid_grant', 'redirect_uri does not match the one the code was issued for.', [ 'status' => 400 ] );
		}

		// The PKCE check: only the client that generated the verifier can spend the
		// code, even if the code itself leaked through a redirect or a log.
		$verifier = (string) $request->get_param( 'code_verifier' );
		$expected = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- PKCE challenges are base64url by definition.

		if ( '' === $verifier || ! hash_equals( (string) $stored['challenge'], $expected ) ) {
			return new \WP_Error( 'invalid_grant', 'code_verifier does not match the challenge.', [ 'status' => 400 ] );
		}

		return self::mint(
			(string) $stored['client_name'],
			(array) $stored['scopes'],
			(int) $stored['user_id'],
			(string) $stored['client_id']
		);
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function refresh( $request ) {
		$record = TokenStore::resolve_refresh( (string) $request->get_param( 'refresh_token' ) );

		if ( null === $record ) {
			return new \WP_Error( 'invalid_grant', 'That refresh token is not valid.', [ 'status' => 400 ] );
		}

		$presented_client = (string) $request->get_param( 'client_id' );
		if ( '' !== $presented_client && $presented_client !== (string) $record['client_id'] ) {
			return new \WP_Error( 'invalid_grant', 'That refresh token belongs to a different client.', [ 'status' => 400 ] );
		}

		// Rotate: the spent refresh token and its access token go away together.
		TokenStore::revoke( (string) $record['id'] );

		return self::mint(
			(string) $record['name'],
			(array) $record['scopes'],
			(int) $record['user_id'],
			(string) $record['client_id']
		);
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return array<string,mixed>
	 */
	public static function revoke( $request ): array {
		$presented = (string) $request->get_param( 'token' );

		$record = TokenStore::resolve( $presented );
		if ( null === $record ) {
			$record = TokenStore::resolve_refresh( $presented );
		}

		if ( null !== $record && ! empty( $record['id'] ) ) {
			TokenStore::revoke( (string) $record['id'] );
		}

		// RFC 7009: an unknown token is not an error. Saying which ones existed
		// would turn this into an oracle.
		return [ 'revoked' => true ];
	}

	/**
	 * @param string            $name      Label the token is listed under.
	 * @param array<int,string> $scopes    Scopes to grant.
	 * @param int               $user_id   The administrator the token acts as.
	 * @param string            $client_id The registered client it belongs to.
	 * @return array<string,mixed>
	 */
	private static function mint( string $name, array $scopes, int $user_id, string $client_id ): array {
		$issued = TokenStore::issue(
			$name,
			$scopes,
			$user_id,
			[
				'client_id'    => $client_id,
				'expires_in'   => self::TOKEN_TTL,
				'with_refresh' => true,
			]
		);

		return [
			'access_token'  => $issued['token'],
			'token_type'    => 'Bearer',
			'expires_in'    => self::TOKEN_TTL,
			'refresh_token' => $issued['refresh_token'],
			'scope'         => implode( ' ', $issued['scopes'] ),
		];
	}

	/* ------------------------------ rendering ----------------------------- */

	/**
	 * @param array<string,mixed> $client
	 * @param array<int,string>   $scopes
	 */
	private static function consent_page( array $client, array $scopes ): void {
		$labels = [
			TokenStore::SCOPE_READ  => __( 'Read your apps, workflows, runs and connections', 'zaplane' ),
			TokenStore::SCOPE_WRITE => __( 'Create and edit workflows', 'zaplane' ),
			TokenStore::SCOPE_RUN   => __( 'Run workflows for real — sending mail, taking payments, posting to other services', 'zaplane' ),
		];

		$rows = '';
		foreach ( $scopes as $scope ) {
			$rows .= '<li><strong>' . esc_html( ucfirst( $scope ) ) . '</strong> — '
				. esc_html( $labels[ $scope ] ?? $scope ) . '</li>';
		}

		$body = '<p>' . sprintf(
			/* translators: %s: the connecting application's name. */
			esc_html__( '%s is asking to:', 'zaplane' ),
			'<strong>' . esc_html( (string) $client['client_name'] ) . '</strong>'
		) . '</p>'
			. '<ul>' . $rows . '</ul>'
			. '<form method="post" action="' . esc_url( self::current_url() ) . '">'
			. wp_nonce_field( 'zaplane_mcp_consent', '_wpnonce', true, false )
			. '<button type="submit" name="approve" value="1" class="primary">' . esc_html__( 'Approve', 'zaplane' ) . '</button> '
			. '<button type="submit" name="deny" value="1">' . esc_html__( 'Cancel', 'zaplane' ) . '</button>'
			. '</form>'
			. '<p class="muted">' . esc_html(
				sprintf(
					/* translators: %s: the WordPress user the token will act as. */
					__( 'Approving issues a token that acts as %s. You can revoke it at any time from Zaplane settings.', 'zaplane' ),
					wp_get_current_user()->display_name
				)
			) . '</p>';

		self::page(
			sprintf(
				/* translators: %s: the connecting application's name. */
				__( 'Connect %s to Zaplane', 'zaplane' ),
				(string) $client['client_name']
			),
			$body
		);
	}

	private static function fail_page( string $message ): void {
		status_header( 400 );
		self::page( __( 'Cannot connect', 'zaplane' ), '<p>' . esc_html( $message ) . '</p>' );
	}

	private static function page( string $title, string $body ): void {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'X-Robots-Tag: noindex' );
			header( 'Cache-Control: no-store' );
		}

		echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head><meta charset="utf-8">' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<title>' . esc_html( $title ) . '</title><style>'
			. 'body{font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f4f5f7;color:#141a24;margin:0;padding:48px 20px}'
			. '.card{max-width:460px;margin:0 auto;background:#fff;border:1px solid #dcdfe4;border-radius:12px;padding:28px}'
			. 'h1{font-size:19px;line-height:1.35;margin:0 0 14px}ul{padding-left:18px;margin:0 0 22px}li{margin-bottom:7px}'
			. 'button{font:inherit;padding:9px 18px;border-radius:6px;border:1px solid #dcdfe4;background:#fff;cursor:pointer}'
			. 'button.primary{background:#006BFF;border-color:#006BFF;color:#fff;font-weight:600}'
			. '.muted{color:#6b7280;font-size:13px;margin:20px 0 0}'
			. '</style></head><body><div class="card"><h1>' . esc_html( $title ) . '</h1>'
			. $body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Composed from escaped parts above.
			. '</div></body></html>';
		exit;
	}

	private static function bounce( string $redirect_uri, string $state, string $error, string $description ): void {
		$args = [
			'error'             => rawurlencode( $error ),
			'error_description' => rawurlencode( $description ),
		];
		if ( '' !== $state ) {
			$args['state'] = rawurlencode( $state );
		}

		wp_redirect( add_query_arg( $args, $redirect_uri ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Matched exactly against the client's registered URIs.
		exit;
	}

	/* ------------------------------- request ------------------------------ */

	/**
	 * Rebuild this request's own URL from parameters we recognise, rather than
	 * echoing REQUEST_URI back into a page and a login redirect.
	 */
	private static function current_url(): string {
		$args = [];

		foreach ( self::AUTHORIZE_PARAMS as $key ) {
			$value = self::query( $key );
			if ( '' !== $value ) {
				$args[ $key ] = rawurlencode( $value );
			}
		}

		return add_query_arg( $args, self::authorize_url() );
	}

	/**
	 * One authorization-request parameter, scrubbed.
	 *
	 * These arrive on the query string of a GET the client constructed, before
	 * anyone has consented to anything, so there is no nonce to check yet — the
	 * one branch that changes state, approval, checks its own.
	 *
	 * @param string $key Parameter name.
	 */
	private static function query( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- scrub() is the sanitizer; see its note.
		return self::scrub( wp_unslash( (string) $_GET[ $key ] ) );
	}

	private static function request_uri(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- scrub() below.
		return isset( $_SERVER['REQUEST_URI'] ) ? self::scrub( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
	}

	/**
	 * Strip control characters — CR and LF above all, which is what would turn a
	 * parameter into an injected header — and cap the length. Deliberately not
	 * sanitize_text_field(): that deletes percent-encoded octets, which would
	 * quietly corrupt a redirect_uri or an opaque state value.
	 *
	 * @param string $value Raw request value.
	 */
	private static function scrub( string $value ): string {
		return substr( trim( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', $value ) ), 0, 2048 );
	}
}
