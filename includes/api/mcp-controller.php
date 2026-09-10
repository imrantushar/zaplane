<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use Zaplane\Framework\Classes\Container;
use Zaplane\Mcp\OAuth\ClientStore;
use Zaplane\Mcp\OAuth\Discovery;
use Zaplane\Mcp\OAuth\PendingStore;
use Zaplane\Mcp\OAuth\Server as OAuthServer;
use Zaplane\Mcp\ToolRegistry;
use Zaplane\Mcp\TokenStore;
use Zaplane\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Model Context Protocol server.
 *
 * Exposes Zaplane's automation surface to AI clients over JSON-RPC at
 * POST /wp-json/zaplane/v1/mcp, in the Streamable-HTTP single-JSON-response
 * mode. Implements initialize, ping, tools/list and tools/call.
 *
 * Off until a site owner turns it on, then reachable only with a scoped token
 * issued from the settings screen. tools/list is filtered to what the presenting
 * token may actually call, so a read-only client is never shown a tool it would
 * be refused.
 *
 * @see \Zaplane\Mcp\ToolRegistry for the tools themselves.
 * @see \Zaplane\Mcp\TokenStore   for tokens and scopes.
 */
class McpController extends WP_REST_Controller {

	private const PROTOCOL_VERSION = '2025-06-18';

	/** Calls allowed per token per minute. */
	private const RATE_LIMIT = 120;

	protected ?Container $container;

	/**
	 * The token this request authenticated with, handed from check_bearer to
	 * handle_rpc.
	 *
	 * Held on the instance rather than stashed on the request. set_param() writes
	 * into the request's JSON parameter set, which *is* the decoded body — and
	 * when that body is a JSON array, as a JSON-RPC batch is, the stash becomes an
	 * extra element of it. The dispatcher then answered a message the client never
	 * sent, echoing the token's id back as that response's id. The same instance
	 * serves check_bearer and handle_rpc for a request, so a property is both
	 * simpler and inert.
	 *
	 * @var array<string,mixed>
	 */
	private array $token = [];

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
		$this->namespace = 'zaplane/v1';
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/mcp',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handle_rpc' ],
					'permission_callback' => [ $this, 'check_bearer' ],
				],
				[
					// Streamable HTTP reserves GET for a server-initiated event
					// stream and DELETE for ending a session. This server does
					// neither, and the spec is specific that the answer is then 405
					// — not the 404 an unregistered method would give, which reads
					// as "this endpoint does not exist" to a client and to anyone
					// who opens the URL in a browser.
					'methods'             => 'GET, DELETE',
					'callback'            => [ $this, 'method_not_allowed' ],
					'permission_callback' => '__return_true',
				],
			]
		);

		$admin = fn() => current_user_can( 'manage_options' );

		// Endpoint URL, whether the feature is on, and the issued tokens
		// (never their secrets).
		register_rest_route(
			$this->namespace,
			'/mcp/info',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'info' ],
					'permission_callback' => $admin,
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/mcp/tokens',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_token' ],
					'permission_callback' => $admin,
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/mcp/tokens/(?P<id>[a-z0-9]+)',
			[
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_token' ],
					'permission_callback' => $admin,
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/mcp/pending',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_pending' ],
					'permission_callback' => $admin,
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/mcp/pending/(?P<id>zpq_[a-z0-9]+)',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'decide_pending' ],
					'permission_callback' => $admin,
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/mcp/clients',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_clients' ],
					'permission_callback' => $admin,
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/mcp/clients/(?P<id>zpc_[a-z0-9]+)',
			[
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_client' ],
					'permission_callback' => $admin,
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/mcp/diagnostics',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'diagnostics' ],
					'permission_callback' => $admin,
				],
			]
		);

		$this->register_oauth_routes();

		// rest_send_allow_header() rebuilds Allow from the handlers whose
		// permission_callback passes. On an unauthenticated request that is the
		// 405 handler and not the POST one, so the header ends up advertising
		// exactly the two methods this endpoint refuses. Put it back afterwards.
		add_filter( 'rest_post_dispatch', [ $this, 'correct_allow_header' ], 20, 3 );
	}

	/**
	 * @param \WP_HTTP_Response $response
	 * @param \WP_REST_Server   $server
	 * @param \WP_REST_Request  $request
	 * @return \WP_HTTP_Response
	 */
	public function correct_allow_header( $response, $server, $request ) {
		if ( $response instanceof \WP_REST_Response
			&& '/' . $this->namespace . '/mcp' === (string) $response->get_matched_route() ) {
			$response->header( 'Allow', 'POST' );
		}

		return $response;
	}

	/**
	 * The machine-to-machine half of the OAuth flow.
	 *
	 * Open by design: a client has no credential to present until it has
	 * registered, and registration on its own grants nothing — a client becomes
	 * useful only once an administrator approves it on the consent screen. The
	 * consent screen itself is not here; it is a front-end URL, because the REST
	 * stack discards the cookie-signed-in user when no wp_rest nonce is sent.
	 *
	 * @see \Zaplane\Mcp\OAuth\Server
	 */
	private function register_oauth_routes(): void {
		$public = '__return_true';

		register_rest_route(
			$this->namespace,
			'/oauth/register',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ OAuthServer::class, 'register' ],
					'permission_callback' => $public,
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/oauth/token',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ OAuthServer::class, 'token' ],
					'permission_callback' => $public,
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/oauth/revoke',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ OAuthServer::class, 'revoke' ],
					'permission_callback' => $public,
				],
			]
		);
	}

	/* ------------------------------- admin -------------------------------- */

	public function info() {
		return rest_ensure_response(
			[
				'enabled'    => self::enabled(),
				'url'        => rest_url( $this->namespace . '/mcp' ),
				'protocol'   => self::PROTOCOL_VERSION,
				'scopes'     => TokenStore::ALL_SCOPES,
				'tokens'     => TokenStore::all(),
				'tool_count' => count( ToolRegistry::definitions() ),
				'reachable'  => self::publicly_reachable(),
			]
		);
	}

	/**
	 * Whether a hosted connector could reach this site at all.
	 *
	 * One run by somebody else resolves the address from their servers, so a
	 * development hostname or a private address is not a configuration mistake it
	 * can report usefully — it simply never arrives, and the connector says it
	 * could not register. Worth stating on the screen rather than leaving someone
	 * to work it out from the other end.
	 */
	public static function publicly_reachable(): bool {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		if ( '' === $host || 'localhost' === $host ) {
			return false;
		}

		// Development suffixes that resolve only on the machine running them.
		if ( (bool) preg_match( '/\.(test|local|localhost|invalid|example|internal|lan|home|dev)$/', $host ) ) {
			return false;
		}

		// A bare IP is only reachable if it is a routable one.
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return (bool) filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		// Anything without a dot cannot be a public name.
		return false !== strpos( $host, '.' );
	}

	public function create_token( $request ) {
		$body = (array) $request->get_json_params();

		$issued = TokenStore::issue(
			(string) ( $body['name'] ?? '' ),
			(array) ( $body['scopes'] ?? TokenStore::DEFAULT_SCOPES ),
			get_current_user_id()
		);

		// The secret is in this response and nowhere else.
		return rest_ensure_response( $issued );
	}

	public function delete_token( $request ) {
		$revoked = TokenStore::revoke( (string) $request['id'] );

		return rest_ensure_response(
			[
				'revoked' => $revoked,
				'id'      => (string) $request['id'],
			]
		);
	}

	/* -------------------------------- auth -------------------------------- */

	public static function enabled(): bool {
		return Settings::feature_enabled( 'mcp_server' );
	}

	/**
	 * Resolve the presented bearer token. Stashed on the request so handle_rpc
	 * can read the scopes without resolving twice.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error True when allowed, false for 401, WP_Error to pick
	 *                        the status (429 when throttled).
	 */
	public function check_bearer( $request ) {
		if ( ! self::enabled() ) {
			return false;
		}

		$token = TokenStore::resolve( self::bearer( $request ) );

		// Nothing presented as a bearer? WordPress may already have authenticated
		// this request by application password, which is a credential the site
		// owner knows how to issue and revoke from a screen they already use.
		if ( null === $token ) {
			$token = self::application_password_grant();
		}

		if ( null === $token ) {
			// RFC 6750: a 401 from a bearer-protected resource states the scheme.
			// Without it a client only sees an opaque refusal and cannot tell how
			// it was meant to authenticate.
			if ( ! headers_sent() ) {
				header( 'WWW-Authenticate: ' . self::challenge(), true );
			}

			return false;
		}

		$retry_after = self::rate_limit_retry_after( (string) $token['id'] );
		if ( $retry_after > 0 ) {
			// Returning false here would render as 401 "you are not allowed to do
			// that", which a client reads as a bad credential — so it stops
			// retrying, or worse, drops the connection and asks to re-authorise.
			// Throttling is temporary and has to say so.
			if ( ! headers_sent() ) {
				header( 'Retry-After: ' . $retry_after );
			}

			return new \WP_Error(
				'zaplane_mcp_rate_limited',
				sprintf(
					/* translators: 1: calls allowed per minute, 2: seconds until the window resets. */
					__( 'Rate limit reached: %1$d calls per minute. Retry in %2$d seconds.', 'zaplane' ),
					self::RATE_LIMIT,
					$retry_after
				),
				[ 'status' => 429 ]
			);
		}

		$this->token = $token;

		return true;
	}

	/**
	 * @return \WP_Error
	 */
	public function method_not_allowed() {
		return new \WP_Error(
			'zaplane_mcp_method_not_allowed',
			__( 'The MCP endpoint accepts POST. It has no event stream to open and no session to end.', 'zaplane' ),
			[ 'status' => 405 ]
		);
	}

	/**
	 * Clients that registered themselves, with how many tokens each still holds.
	 *
	 * A registration is not access on its own, but it is the anchor a connected
	 * client keeps pointing at, and until now there was no way to see one or take
	 * it away.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_clients() {
		$tokens = TokenStore::all();

		$rows = array_values(
			array_map(
				fn( $c ) => [
					'client_id'     => $c['client_id'],
					'client_name'   => $c['client_name'],
					'redirect_uris' => array_values( (array) $c['redirect_uris'] ),
					'created_at'    => $c['created_at'] ?? '',
					'token_count'   => count( array_filter( $tokens, fn( $t ) => (string) $t['client_id'] === (string) $c['client_id'] ) ),
				],
				ClientStore::all()
			)
		);

		return rest_ensure_response( [ 'clients' => $rows ] );
	}

	/**
	 * Remove a registration and everything issued through it.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_client( $request ) {
		$id = (string) $request['id'];

		if ( null === ClientStore::get( $id ) ) {
			return new \WP_Error( 'not_found', __( 'That client is not registered.', 'zaplane' ), [ 'status' => 404 ] );
		}

		// Tokens first: a token outliving the registration it came from is access
		// with no visible origin.
		$revoked = TokenStore::revoke_for_client( $id );
		ClientStore::forget( $id );

		return rest_ensure_response(
			[
				'removed'         => true,
				'tokens_revoked'  => $revoked,
			]
		);
	}

	/**
	 * Connection requests waiting on a decision.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_pending() {
		$rows = array_map(
			fn( $r ) => [
				'id'           => $r['id'],
				'client_name'  => $r['client_name'],
				'user_name'    => $r['user_name'],
				'scopes'       => $r['scopes'],
				'status'       => $r['status'],
				'requested_at' => $r['requested_at'],
			],
			PendingStore::all()
		);

		return rest_ensure_response( [ 'pending' => $rows ] );
	}

	/**
	 * Allow or refuse one, granting no more than the administrator ticked.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function decide_pending( $request ) {
		$id = (string) $request['id'];

		if ( null === PendingStore::find( $id ) ) {
			return new \WP_Error( 'not_found', __( 'That request is no longer waiting.', 'zaplane' ), [ 'status' => 404 ] );
		}

		if ( 'approve' !== $request->get_param( 'decision' ) ) {
			PendingStore::forget( $id );

			return rest_ensure_response( [ 'denied' => true ] );
		}

		PendingStore::approve( $id, (array) $request->get_param( 'scopes' ) );

		return rest_ensure_response( [ 'approved' => true ] );
	}

	/**
	 * Check this site's own MCP surface and say what is wrong in plain words.
	 *
	 * A client that cannot connect reports the symptom from the outside — "could
	 * not reach", "could not register" — which says nothing about the cause. These
	 * checks run from the site itself, where the cause is visible.
	 *
	 * @return \WP_REST_Response
	 */
	public function diagnostics() {
		$checks = [];

		$checks[] = self::enabled()
			? self::check( 'module', __( 'AI access module is on', 'zaplane' ), 'ok' )
			: self::check(
				'module',
				__( 'AI access module is off', 'zaplane' ),
				'fail',
				__( 'Every request is refused before the credential is even looked at, so any client reports a sign-in failure. Turn it on under Settings → Modules.', 'zaplane' )
			);

		// With the module off, the endpoint and the discovery documents are meant
		// to be unavailable. Probing them anyway would report two more failures
		// with causes that are not the cause, burying the one that is.
		if ( self::enabled() ) {
			$checks[] = self::probe_endpoint();
			$checks[] = self::probe_discovery();
		} else {
			$checks[] = self::check( 'endpoint', __( 'Endpoint — not checked while the module is off', 'zaplane' ), 'skip' );
			$checks[] = self::check( 'discovery', __( 'Sign-in discovery — not checked while the module is off', 'zaplane' ), 'skip' );
		}

		$checks[] = self::publicly_reachable()
			? self::check( 'reachable', __( 'Address is reachable from the internet', 'zaplane' ), 'ok' )
			: self::check(
				'reachable',
				__( 'Address cannot be reached from the internet', 'zaplane' ),
				'warn',
				__( 'Hosted connectors such as claude.ai and ChatGPT look this address up from their own servers, so a development hostname never arrives and they report that they could not sign in. Clients running on this machine are unaffected.', 'zaplane' )
			);

		$checks[] = function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available()
			? self::check( 'app_passwords', __( 'Application passwords are available', 'zaplane' ), 'ok' )
			: self::check(
				'app_passwords',
				__( 'Application passwords are switched off', 'zaplane' ),
				'warn',
				__( 'A security plugin or a site filter has disabled them. Connect with an issued token or through OAuth instead.', 'zaplane' )
			);

		$tokens = count( TokenStore::all() );
		$checks[] = $tokens > 0
			/* translators: %d: number of credentials. */
			? self::check( 'credentials', sprintf( _n( '%d credential issued', '%d credentials issued', $tokens, 'zaplane' ), $tokens ), 'ok' )
			: self::check(
				'credentials',
				__( 'No credentials issued yet', 'zaplane' ),
				'warn',
				__( 'Nothing is connected. Issue a token below, use a WordPress application password, or let a hosted connector sign in.', 'zaplane' )
			);

		return rest_ensure_response( [ 'checks' => $checks ] );
	}

	/**
	 * The endpoint should refuse an unauthenticated call, and say how to
	 * authenticate while doing it. Anything else means something in front of
	 * WordPress is answering instead.
	 *
	 * @return array<string,string>
	 */
	private static function probe_endpoint(): array {
		$response = wp_remote_post(
			rest_url( 'zaplane/v1/mcp' ),
			[
				'timeout'  => 10,
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'body'     => wp_json_encode(
					[
						'jsonrpc' => '2.0',
						'id'      => 1,
						'method'  => 'ping',
					]
				),
			]
		);

		if ( is_wp_error( $response ) ) {
			return self::check(
				'endpoint',
				__( 'The endpoint could not be reached from this site', 'zaplane' ),
				'fail',
				$response->get_error_message() . ' — ' . __( 'a certificate this server does not trust will also read like this. A hosted connector refuses an untrusted certificate outright.', 'zaplane' )
			);
		}

		$code      = (int) wp_remote_retrieve_response_code( $response );
		$challenge = (string) wp_remote_retrieve_header( $response, 'www-authenticate' );

		if ( 401 !== $code ) {
			return self::check(
				'endpoint',
				/* translators: %d: HTTP status code. */
				sprintf( __( 'The endpoint answered %d, not 401', 'zaplane' ), $code ),
				'fail',
				__( 'An unauthenticated call should be refused with 401. Another status usually means a security plugin, firewall or cache is answering before WordPress does.', 'zaplane' )
			);
		}

		if ( false === strpos( $challenge, 'resource_metadata' ) ) {
			return self::check(
				'endpoint',
				__( 'The refusal carries no pointer to the sign-in service', 'zaplane' ),
				'fail',
				__( 'Hosted connectors follow that pointer to discover OAuth; without it they report that this server does not implement it. Something is stripping response headers.', 'zaplane' )
			);
		}

		return self::check( 'endpoint', __( 'Endpoint answers and advertises how to sign in', 'zaplane' ), 'ok' );
	}

	/**
	 * @return array<string,string>
	 */
	private static function probe_discovery(): array {
		foreach ( [ Discovery::PROTECTED_RESOURCE, Discovery::AUTHORIZATION_SERVER ] as $name ) {
			$response = wp_remote_get( home_url( '/.well-known/' . $name ), [ 'timeout' => 10 ] );

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return self::check(
					'discovery',
					/* translators: %s: the well-known document name. */
					sprintf( __( '/.well-known/%s is not being served', 'zaplane' ), $name ),
					'fail',
					__( 'A hosted connector reads these two documents before it can sign in. Some hosts serve /.well-known/ from disk, which shadows them; a static-cache plugin can do the same.', 'zaplane' )
				);
			}
		}

		return self::check( 'discovery', __( 'Sign-in discovery documents are being served', 'zaplane' ), 'ok' );
	}

	/**
	 * @param string $key    Stable identifier for the check.
	 * @param string $label  What was checked, in plain words.
	 * @param string $status ok, warn, fail or skip.
	 * @param string $fix    What to do about it, when there is something to do.
	 * @return array<string,string>
	 */
	private static function check( string $key, string $label, string $status, string $fix = '' ): array {
		return [
			'key'    => $key,
			'label'  => $label,
			'status' => $status,
			'fix'    => $fix,
		];
	}

	/**
	 * Treat a WordPress application password as a way in.
	 *
	 * Core has already done the work by this point — it authenticates Basic auth
	 * on REST requests itself — so this only decides whether to honour it. Using
	 * one means no Zaplane token to issue, copy or lose, and revoking it is where
	 * a WordPress user already looks: Users → Profile → Application Passwords.
	 *
	 * Never granted `run`. An application password is the whole user, with no way
	 * to withhold one capability, so the scope that sends mail and takes payments
	 * has to come from a credential that was asked for deliberately — an issued
	 * token, or an approved OAuth grant.
	 *
	 * rest_get_authenticated_app_password() is what separates this from an
	 * administrator who merely happens to be signed in: it is set only when the
	 * request itself carried an application password.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function application_password_grant(): ?array {
		if ( ! function_exists( 'rest_get_authenticated_app_password' ) ) {
			return null;
		}

		$uuid = rest_get_authenticated_app_password();

		if ( ! $uuid || ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		return [
			'id'      => 'app-' . $uuid,
			'name'    => __( 'Application password', 'zaplane' ),
			'scopes'  => TokenStore::DEFAULT_SCOPES,
			'user_id' => get_current_user_id(),
		];
	}

	/**
	 * The value of the WWW-Authenticate header on a 401.
	 *
	 * A fixed realm names the protection space, which is what a realm is for; the
	 * site title would leak into an unauthenticated response for no benefit.
	 *
	 * resource_metadata is the part a hosted connector needs (RFC 9728). Such a
	 * client arrives holding nothing but this endpoint's URL, and this pointer is
	 * the only way it can find the authorization server. Without it there is
	 * nothing to follow, and the client reports — correctly — that the server does
	 * not implement OAuth.
	 */
	public static function challenge(): string {
		return sprintf(
			'Bearer realm="Zaplane MCP", resource_metadata="%s"',
			esc_url_raw( Discovery::protected_resource_url() )
		);
	}

	/**
	 * Pull the bearer value out of the request.
	 *
	 * Falls back to the CGI variables because Apache under CGI/FastCGI drops the
	 * Authorization header before PHP sees it, which otherwise presents as every
	 * call failing to authenticate for no visible reason.
	 *
	 * @param \WP_REST_Request $request
	 */
	private static function bearer( $request ): string {
		$header = (string) $request->get_header( 'authorization' );

		if ( '' === $header ) {
			foreach ( [ 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ] as $key ) {
				if ( ! empty( $_SERVER[ $key ] ) ) {
					$header = sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) );
					break;
				}
			}
		}

		if ( 0 !== stripos( $header, 'bearer ' ) ) {
			return '';
		}

		return trim( substr( $header, 7 ) );
	}

	/**
	 * Memoized per request — see below for why that matters.
	 *
	 * @var array<string,int>
	 */
	private static array $rate_checked = [];

	/**
	 * Count this call against the token's window.
	 *
	 * Returns the seconds until the window resets once the limit is reached, or
	 * 0 while there is room. A client that is told to back off needs to know for
	 * how long, so the window carries its own reset time rather than relying on
	 * the transient's opaque TTL.
	 *
	 * The result is memoized because WordPress calls a route's
	 * permission_callback twice on a real HTTP request: once to authorise the
	 * call, then again from rest_send_allow_header() on rest_post_dispatch,
	 * which re-invokes it to work out the Allow header
	 * (wp-includes/rest-api.php). A counter incremented in a permission callback
	 * therefore charges two against the quota for every one call, halving the
	 * limit — 120/min behaved as 60/min. Internal dispatch via rest_do_request()
	 * skips rest_post_dispatch, so this only shows up over real HTTP.
	 */
	private static function rate_limit_retry_after( string $token_id ): int {
		if ( isset( self::$rate_checked[ $token_id ] ) ) {
			return self::$rate_checked[ $token_id ];
		}

		$key   = 'zaplane_mcp_rl_' . md5( $token_id );
		$now   = time();
		$state = get_transient( $key );

		if ( ! is_array( $state ) || empty( $state['reset'] ) || $state['reset'] <= $now ) {
			$state = [
				'count' => 0,
				'reset' => $now + MINUTE_IN_SECONDS,
			];
		}

		if ( $state['count'] >= self::RATE_LIMIT ) {
			self::$rate_checked[ $token_id ] = max( 1, (int) $state['reset'] - $now );
			return self::$rate_checked[ $token_id ];
		}

		++$state['count'];
		set_transient( $key, $state, max( 1, (int) $state['reset'] - $now ) );

		self::$rate_checked[ $token_id ] = 0;

		return 0;
	}

	/**
	 * True when this request came from this same site's MCP client — a workflow
	 * calling back into the server that started it.
	 *
	 * @param \WP_REST_Request $request
	 */
	private static function is_self_call( $request ): bool {
		$origin = trim( (string) $request->get_header( 'x-zaplane-origin' ) );

		if ( '' === $origin ) {
			return false;
		}

		return untrailingslashit( $origin ) === untrailingslashit( home_url() );
	}

	/* ------------------------------- JSON-RPC ----------------------------- */

	public function handle_rpc( $request ) {
		$body = $request->get_json_params();

		// Fall back to the raw body when the client didn't set a JSON content type.
		if ( null === $body || [] === $body ) {
			$decoded = json_decode( (string) $request->get_body(), true );
			if ( is_array( $decoded ) ) {
				$body = $decoded;
			}
		}

		$token = $this->token;
		$self  = self::is_self_call( $request );

		// Batch.
		if ( is_array( $body ) && isset( $body[0] ) ) {
			$out = [];
			foreach ( $body as $message ) {
				$response = $this->dispatch( (array) $message, $token, $self );
				if ( null !== $response ) {
					$out[] = $response;
				}
			}
			return rest_ensure_response( $out );
		}

		$response = $this->dispatch( (array) $body, $token, $self );

		// Notifications get an ack with no body.
		if ( null === $response ) {
			return new \WP_REST_Response( null, 202 );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * @param array<string,mixed> $message
	 * @param array<string,mixed> $token
	 * @return array<string,mixed>|null
	 */
	private function dispatch( array $message, array $token, bool $self ) {
		$method = (string) ( $message['method'] ?? '' );
		$id     = $message['id'] ?? null;
		$params = (array) ( $message['params'] ?? [] );
		$scopes = (array) ( $token['scopes'] ?? [] );

		if ( null === $id && 0 === strpos( $method, 'notifications/' ) ) {
			return null;
		}

		switch ( $method ) {
			case 'initialize':
				return self::result(
					$id,
					[
						'protocolVersion' => self::PROTOCOL_VERSION,
						'capabilities'    => [ 'tools' => [ 'listChanged' => false ] ],
						'serverInfo'      => [
							'name'    => 'Zaplane',
							'version' => defined( 'ZAPLANE_VERSION' ) ? ZAPLANE_VERSION : '1.0.0',
						],
						'instructions'    => 'Automation for this WordPress site. To build a workflow: search_capabilities to find the trigger and actions, describe_app for their exact config fields, validate_graph to check your draft, then create_workflow. To diagnose one: list_runs then get_run.',
					]
				);

			case 'ping':
				return self::result( $id, (object) [] );

			case 'tools/list':
				return self::result( $id, [ 'tools' => ToolRegistry::schemas( $scopes ) ] );

			case 'tools/call':
				return $this->call_tool( $id, $params, $token, $self );
		}

		return self::error( $id, -32601, 'Method not found: ' . $method );
	}

	/**
	 * @param array<string,mixed> $params
	 * @param array<string,mixed> $token
	 * @return array<string,mixed>
	 */
	private function call_tool( $id, array $params, array $token, bool $self ) {
		$name = (string) ( $params['name'] ?? '' );
		$args = (array) ( $params['arguments'] ?? [] );

		$required = ToolRegistry::scope_for( $name );
		if ( null === $required ) {
			return self::error( $id, -32602, 'Unknown tool: ' . $name );
		}

		if ( ! TokenStore::has_scope( $token, $required ) ) {
			return self::tool_error(
				$id,
				sprintf(
					'This token does not have the "%s" scope, which "%s" requires. Issue a new token with that scope from Zaplane → Settings.',
					$required,
					$name
				)
			);
		}

		// A workflow whose AI Agent points back at this site could otherwise start
		// the workflow that is calling, and so on. Reads are harmless; starting a
		// run is not.
		if ( $self && TokenStore::SCOPE_RUN === $required ) {
			return self::tool_error(
				$id,
				'Refused: this call came from this same site, so running a workflow here could re-enter the workflow that made the call.'
			);
		}

		try {
			$data = ToolRegistry::call( $name, $args, $token );
		} catch ( \Throwable $e ) {
			return self::tool_error( $id, $e->getMessage() );
		}

		return self::result(
			$id,
			[
				'content' => [
					[
						'type' => 'text',
						'text' => (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
					],
				],
			]
		);
	}

	/* ------------------------------- helpers ------------------------------ */

	/**
	 * @param mixed $result
	 * @return array<string,mixed>
	 */
	private static function result( $id, $result ): array {
		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		];
	}

	/**
	 * A failure the model should see and can act on, rather than a protocol error.
	 *
	 * @return array<string,mixed>
	 */
	private static function tool_error( $id, string $message ): array {
		return self::result(
			$id,
			[
				'content' => [
					[
						'type' => 'text',
						'text' => 'Error: ' . $message,
					],
				],
				'isError' => true,
			]
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function error( $id, int $code, string $message ): array {
		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => [
				'code'    => $code,
				'message' => $message,
			],
		];
	}
}
