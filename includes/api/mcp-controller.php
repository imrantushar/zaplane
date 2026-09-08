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
	 */
	public function check_bearer( $request ): bool {
		if ( ! self::enabled() ) {
			return false;
		}

		$token = TokenStore::resolve( self::bearer( $request ) );
		if ( null === $token ) {
			return false;
		}

		if ( ! self::within_rate_limit( (string) $token['id'] ) ) {
			return false;
		}

		$request->set_param( '_zaplane_mcp_token', $token );

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

	private static function within_rate_limit( string $token_id ): bool {
		$key   = 'zaplane_mcp_rl_' . md5( $token_id );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
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

		$token = (array) ( $request->get_param( '_zaplane_mcp_token' ) ?? [] );
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
