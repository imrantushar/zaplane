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

	/** Anonymous client registrations one address may make in an hour. */
	private const REGISTRATIONS_PER_HOUR = 20;

	/** Query parameters the authorization request is made of. */
	private const AUTHORIZE_PARAMS = [
		'response_type',
		'client_id',
		'redirect_uri',
		'state',
		'scope',
		'code_challenge',
		'code_challenge_method',
		'resource',
	];

	public static function boot(): void {
		add_action( 'parse_request', [ self::class, 'maybe_authorize' ], 0 );
	}

	/**
	 * Where the person is sent to approve a client.
	 *
	 * Deliberately the canonical host, even when discovery is answering under
	 * another name for the same site. Consent is the one step that needs a signed
	 * in WordPress user, and WordPress keeps that on one host: the login cookie is
	 * set for the site's own hostname, wp_login_url() points at site_url(), and
	 * wp_safe_redirect() will not send anyone back to a host outside home_url().
	 * A consent screen on the www. alias would therefore ask for a login and then
	 * be unable to return to itself. The endpoints that follow have no cookie to
	 * lose and stay on the host the client is talking to.
	 */
	public static function authorize_url(): string {
		return home_url( '/' . self::AUTHORIZE_PATH );
	}

	/**
	 * The client behind an identifier, however it identifies itself.
	 *
	 * Either one that registered here, or one that is a URL serving its own
	 * metadata. Callers should not have to care which.
	 *
	 * @param string $client_id The presented identifier.
	 * @return array<string,mixed>|null
	 */
	public static function client_for( string $client_id ): ?array {
		if ( '' === $client_id ) {
			return null;
		}

		if ( ClientIdDocument::is_client_id_url( $client_id ) ) {
			return ClientIdDocument::resolve( $client_id );
		}

		return ClientStore::get( $client_id );
	}

	/**
	 * RFC 8707: a client names the resource server it wants a token for. Honour
	 * it only when it is this site — a token minted here must never be usable as
	 * one issued for somewhere else. Compared per site rather than per exact URL,
	 * so a www. spelling is not treated as a different server.
	 *
	 * @param string $target The `resource` parameter, empty when not sent.
	 */
	private static function resource_ours( string $target ): bool {
		if ( '' === $target ) {
			return true; // Not sent. Older clients do not send one.
		}

		$host = (string) wp_parse_url( $target, PHP_URL_HOST );
		$mine = (string) wp_parse_url( Discovery::resource_url(), PHP_URL_HOST );

		return '' !== $host && Discovery::same_site( $host, $mine );
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

		// Registration is anonymous, so it is metered per address.
		$address  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$meter    = 'zaplane_mcp_reg_' . md5( $address );
		$attempts = (int) get_transient( $meter );
		if ( $attempts >= self::REGISTRATIONS_PER_HOUR ) {
			return new \WP_Error( 'slow_down', 'Too many client registrations from this address. Try again later.', [ 'status' => 429 ] );
		}
		set_transient( $meter, $attempts + 1, HOUR_IN_SECONDS );

		try {
			$client = ClientStore::register( $metadata );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'invalid_redirect_uri', $e->getMessage(), [ 'status' => 400 ] );
		} catch ( \OverflowException $e ) {
			return new \WP_Error( 'temporarily_unavailable', $e->getMessage(), [ 'status' => 429 ] );
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

		$client = self::client_for( $client_id );

		// Anything wrong with the client or its redirect is shown here rather than
		// bounced onward: redirecting to an address we have not verified is how a
		// bad redirect_uri turns into a delivery mechanism. A malformed request is shown
		// here too: registration is open, so a registered redirect proves nothing about
		// who asked, and nothing goes to one before an administrator has answered.
		if ( ! $client ) {
			self::fail_page( __( 'Unknown client. Register the connector again.', 'zaplane' ) );
		}

		if ( '' === $redirect_uri || ! ClientStore::redirect_allowed( $client, $redirect_uri ) ) {
			self::fail_page( __( 'That redirect address is not one this client registered.', 'zaplane' ) );
		}

		if ( 'code' !== self::query( 'response_type' ) ) {
			self::fail_page( __( 'This connector asked for a sign-in flow Zaplane does not support.', 'zaplane' ) );
		}

		if ( '' === $challenge || 'S256' !== $method ) {
			self::fail_page( __( 'This connector did not use PKCE (S256), which Zaplane requires.', 'zaplane' ) );
		}

		if ( ! self::resource_ours( self::query( 'resource' ) ) ) {
			self::fail_page( __( 'This connector asked for access to a different site.', 'zaplane' ) );
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

		// Zaplane asks `manage_options` of everyone on every one of its screens,
		// and a token cannot be a way around that — the endpoint asks the same
		// question of every call. Somebody who cannot manage the site would be
		// approving a credential that is refused the moment it is used, so say
		// so here instead of issuing one.
		if ( ! current_user_can( 'manage_options' ) ) {
			self::refuse_page( $client );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the verb, not form data; the branch it opens checks a nonce first.
		if ( 'POST' === strtoupper( sanitize_text_field( wp_unslash( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) ) ) {
			check_admin_referer( 'zaplane_mcp_consent' );

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- check_admin_referer() above.
			if ( ! isset( $_POST['approve'] ) ) {
				self::bounce( $redirect_uri, $state, 'access_denied', 'The request was declined.' );
			}

			self::grant( $client, $redirect_uri, $state, $challenge, self::ticked( $scopes ) );
		}

		self::consent_page( $client, $scopes, $redirect_uri );
	}

	/**
	 * Tell somebody who cannot manage this site that this is not for them.
	 *
	 * This used to park the request for an administrator to allow, on the
	 * reasoning that the token would act as the person who asked and so carry no
	 * more authority than they had. It never did act as them — nothing applied
	 * the recorded user, so every token had an administrator's reach — and now
	 * that it does, a token issued to somebody without `manage_options` is
	 * refused on every call. Approving one would have been a courtesy that
	 * produced a credential which does not work.
	 *
	 * @param array<string,mixed> $client The client asking to connect.
	 */
	private static function refuse_page( array $client ): void {
		self::page(
			__( 'You cannot approve this', 'zaplane' ),
			'<p>' . sprintf(
				/* translators: %s: the connecting application's name. */
				esc_html__( '%s is asking to connect to this site through Zaplane, which only an administrator can allow.', 'zaplane' ),
				'<strong>' . esc_html( (string) $client['client_name'] ) . '</strong>'
			) . '</p>'
			. '<p class="muted">' . esc_html__( 'Ask whoever administers this site to connect it from their own account. A credential issued to yours would be refused every time it was used.', 'zaplane' ) . '</p>'
		);
	}

	/**
	 * @param array<string,mixed> $client       The approved client.
	 * @param string              $redirect_uri Where the code is delivered.
	 * @param string              $state        Client state, echoed back untouched.
	 * @param string              $challenge    The PKCE challenge the code is bound to.
	 * @param array<int,string>   $scopes       Scopes the administrator approved.
	 * @param int                 $user_id      Who the token acts as; 0 means the
	 *                                          person on this page.
	 */
	private static function grant( array $client, string $redirect_uri, string $state, string $challenge, array $scopes, int $user_id = 0 ): void {
		$code = 'zac_' . wp_generate_password( 40, false );

		set_transient(
			self::CODE_PREFIX . hash( 'sha256', $code ),
			[
				'client_id'    => (string) $client['client_id'],
				'client_name'  => (string) $client['client_name'],
				'redirect_uri' => $redirect_uri,
				'challenge'    => $challenge,
				'scopes'       => $scopes,
				'user_id'      => $user_id > 0 ? $user_id : get_current_user_id(),
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

		if ( ! self::resource_ours( (string) $request->get_param( 'resource' ) ) ) {
			return new \WP_Error( 'invalid_target', 'That resource is not served by this site.', [ 'status' => 400 ] );
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
	 * @param string              $redirect_uri Where the browser goes after the answer.
	 */
	private static function consent_page( array $client, array $scopes, string $redirect_uri ): void {
		$labels = [
			TokenStore::SCOPE_READ  => __( 'Read your apps, workflows, runs and connections', 'zaplane' ),
			TokenStore::SCOPE_WRITE => __( 'Create and edit workflows', 'zaplane' ),
			TokenStore::SCOPE_RUN   => __( 'Run workflows for real — sending mail, taking payments, posting to other services', 'zaplane' ),
		];

		// Every scope the client asked for is offered, but `run` starts unticked.
		// Clients ask for everything the server advertises — mcp-remote sends
		// `read write run` without being told to — so an approve-everything button
		// hands out the scope that spends money without anyone deciding to.
		$rows = '';
		foreach ( $scopes as $scope ) {
			$rows .= '<li><label><input type="checkbox" name="scope[]" value="' . esc_attr( $scope ) . '"'
				. ( TokenStore::SCOPE_RUN === $scope ? '' : ' checked' ) . '> '
				. '<strong>' . esc_html( ucfirst( $scope ) ) . '</strong> — '
				. esc_html( $labels[ $scope ] ?? $scope ) . '</label></li>';
		}

		$body = '<p>' . sprintf(
			/* translators: %s: the connecting application's name. */
			esc_html__( '%s is asking to:', 'zaplane' ),
			'<strong>' . esc_html( (string) $client['client_name'] ) . '</strong>'
		) . '</p>'
			. '<p class="muted">' . esc_html(
				sprintf(
					/* translators: %s: the host the browser returns to after answering. */
					__( 'After you answer, you will be sent back to %s.', 'zaplane' ),
					self::redirect_label( $redirect_uri )
				)
			) . '</p>'
			. '<form method="post" action="' . esc_url( self::current_url() ) . '">'
			. '<ul class="scopes">' . $rows . '</ul>'
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

	/**
	 * The scopes actually ticked, never more than the client asked for.
	 *
	 * Ticking nothing is treated as read rather than as everything: an empty box
	 * is what a mis-click looks like, and it should not be the widest outcome.
	 *
	 * @param array<int,string> $requested What the client asked for.
	 * @return array<int,string>
	 */
	private static function ticked( array $requested ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- check_admin_referer() ran before this is reached, and sanitize_scopes() below is the sanitizer.
		$raw = isset( $_POST['scope'] ) && is_array( $_POST['scope'] ) ? wp_unslash( $_POST['scope'] ) : [];
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$granted = array_values(
			array_intersect( TokenStore::sanitize_scopes( array_map( 'strval', $raw ) ), $requested )
		);

		return empty( $granted ) ? [ TokenStore::SCOPE_READ ] : $granted;
	}

	/** The part of a redirect URI a person can check: its host, or an app's scheme. */
	private static function redirect_label( string $uri ): string {
		$host = (string) wp_parse_url( $uri, PHP_URL_HOST );

		return '' !== $host ? $host : (string) wp_parse_url( $uri, PHP_URL_SCHEME ) . ':';
	}

	private static function fail_page( string $message ): void {
		status_header( 400 );
		self::page( __( 'Cannot connect', 'zaplane' ), '<p>' . esc_html( $message ) . '</p>' );
	}

	/**
	 * The markup the consent, refusal and failure pages are built from.
	 *
	 * Every page here is assembled from escaped parts, but the body is run
	 * through wp_kses() on the way out anyway, so that what can reach the
	 * browser is fixed by this list rather than by each caller getting it right.
	 */
	private const PAGE_HTML = [
		'p'      => [ 'class' => true ],
		'strong' => [],
		'em'     => [],
		'br'     => [],
		'ul'     => [ 'class' => true ],
		'li'     => [ 'class' => true ],
		'label'  => [ 'for' => true ],
		'code'   => [],
		'form'   => [
			'method' => true,
			'action' => true,
		],
		'input'  => [
			'type'    => true,
			'name'    => true,
			'value'   => true,
			'checked' => true,
			'id'      => true,
		],
		'button' => [
			'type'  => true,
			'name'  => true,
			'value' => true,
			'class' => true,
		],
	];

	private static function page( string $title, string $body ): void {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'X-Robots-Tag: noindex' );
			header( 'Cache-Control: no-store' );
			// A consent screen inside somebody else's frame can be clicked through
			// without the person approving ever seeing what they approve.
			header( 'X-Frame-Options: DENY' );
			header( "Content-Security-Policy: frame-ancestors 'none'" );
		}

		wp_register_style( 'zaplane-oauth', false, [], ZAPLANE_VERSION );
		wp_add_inline_style(
			'zaplane-oauth',
			'body{font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f4f5f7;color:#141a24;margin:0;padding:48px 20px}'
			. '.card{max-width:460px;margin:0 auto;background:#fff;border:1px solid #dcdfe4;border-radius:12px;padding:28px}'
			. 'h1{font-size:19px;line-height:1.35;margin:0 0 14px}ul{padding-left:18px;margin:0 0 22px}li{margin-bottom:7px}'
			. 'ul.scopes{list-style:none;padding:0}ul.scopes li{margin-bottom:10px}ul.scopes label{display:flex;gap:8px;align-items:flex-start;cursor:pointer}'
			. 'button{font:inherit;padding:9px 18px;border-radius:6px;border:1px solid #dcdfe4;background:#fff;cursor:pointer}'
			. 'button.primary{background:#006BFF;border-color:#006BFF;color:#fff;font-weight:600}'
			. '.muted{color:#6b7280;font-size:13px;margin:20px 0 0}'
		);

		echo '<!DOCTYPE html><html ';
		language_attributes();
		echo '><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $title ) . '</title>';
		wp_print_styles( 'zaplane-oauth' );
		echo '</head><body><div class="card"><h1>' . esc_html( $title ) . '</h1>'
			. wp_kses( $body, self::PAGE_HTML )
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
	 * There is deliberately no nonce check here, and there cannot be one: these
	 * parameters arrive on the query string of a GET that an AI client
	 * constructed on another machine, before this site has met it and before
	 * anyone has agreed to anything. A nonce is issued by this site to a person
	 * already on it, so the client has none to send.
	 *
	 * Nothing read here changes anything either. Everything this method feeds is
	 * a decision about what to show: which client is asking, where the answer
	 * goes, which scopes to offer. The one branch that does change state —
	 * approval, which issues a credential — is a POST from the consent form on
	 * this site, and {@see self::authorize()} checks both `manage_options` and
	 * check_admin_referer( 'zaplane_mcp_consent' ) before it runs.
	 *
	 * @param string $key Parameter name.
	 */
	private static function query( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No nonce can exist on this request; see the note above.
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- No nonce can exist on this request (see the note above); scrub() is the sanitizer.
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
