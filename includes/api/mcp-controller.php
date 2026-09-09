<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use Zaplane\Framework\Classes\Container;
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
	}

	/* ------------------------------- admin -------------------------------- */

	public function info() {
		return rest_ensure_response(
			[
				'enabled'   => self::enabled(),
				'url'       => rest_url( $this->namespace . '/mcp' ),
				'protocol'  => self::PROTOCOL_VERSION,
				'scopes'    => TokenStore::ALL_SCOPES,
				'tokens'    => TokenStore::all(),
				'tool_count' => count( ToolRegistry::definitions() ),
			]
		);
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
		if ( null === $token ) {
			// RFC 6750: a 401 from a bearer-protected resource states the scheme.
			// Without it a client only sees an opaque refusal and cannot tell how
			// it was meant to authenticate.
			if ( ! headers_sent() ) {
				// A fixed realm names the protection space, which is what a realm is
				// for; the site title would leak into an unauthenticated response
				// for no benefit.
				header( 'WWW-Authenticate: Bearer realm="Zaplane MCP"', true );
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
