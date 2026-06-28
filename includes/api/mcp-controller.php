<?php

namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use Zaplane\Framework\Classes\Container;
use Zaplane\Models\Recipe;
use Zaplane\Services\BlueprintService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Model Context Protocol (MCP) server for Zaplane.
 *
 * Exposes Zaplane's automation surface to AI clients (Claude, ChatGPT, Cursor)
 * over a JSON-RPC endpoint at POST /wp-json/zaplane/v1/mcp, authenticated with a
 * bearer token. Implements the core MCP methods (initialize, tools/list,
 * tools/call) using the Streamable-HTTP single-JSON-response mode.
 */
class McpController extends WP_REST_Controller {

	private const PROTOCOL_VERSION = '2025-06-18';
	private const TOKEN_OPTION      = 'zaplane_mcp_token';

	protected ?Container $container;

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
		$this->namespace = 'zaplane/v1';
	}

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/mcp', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_rpc' ],
				'permission_callback' => [ $this, 'check_bearer' ],
			],
		] );

		// Admin-only: returns the endpoint URL + bearer token to paste into an AI client.
		register_rest_route( $this->namespace, '/mcp/info', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'info' ],
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			],
		] );
	}

	/* ---------------------------------------------------------------------- */

	public function info() {
		return rest_ensure_response( [
			'url'   => rest_url( $this->namespace . '/mcp' ),
			'token' => self::get_or_create_token(),
		] );
	}

	public static function get_or_create_token(): string {
		$token = (string) get_option( self::TOKEN_OPTION, '' );
		if ( '' === $token ) {
			$token = wp_generate_password( 48, false );
			update_option( self::TOKEN_OPTION, $token, false );
		}
		return $token;
	}

	public function check_bearer( $request ): bool {
		$expected = (string) get_option( self::TOKEN_OPTION, '' );
		if ( '' === $expected ) {
			return false; // not configured yet
		}

		$header = (string) $request->get_header( 'authorization' );
		if ( stripos( $header, 'bearer ' ) !== 0 ) {
			return false;
		}
		$token = trim( substr( $header, 7 ) );

		return hash_equals( $expected, $token );
	}

	/* ---------------------------------------------------------------------- */

	public function handle_rpc( $request ) {
		$body = $request->get_json_params();

		// Fall back to raw body if the client didn't send a JSON content-type.
		if ( null === $body || [] === $body ) {
			$decoded = json_decode( (string) $request->get_body(), true );
			if ( is_array( $decoded ) ) {
				$body = $decoded;
			}
		}

		// Batch request.
		if ( is_array( $body ) && isset( $body[0] ) ) {
			$out = [];
			foreach ( $body as $msg ) {
				$resp = $this->dispatch( (array) $msg );
				if ( null !== $resp ) {
					$out[] = $resp;
				}
			}
			return rest_ensure_response( $out );
		}

		$resp = $this->dispatch( (array) $body );

		// Notifications (no id) get an empty 202-style ack.
		if ( null === $resp ) {
			return new \WP_REST_Response( null, 202 );
		}

		return rest_ensure_response( $resp );
	}

	private function dispatch( array $msg ) {
		$method = $msg['method'] ?? '';
		$id     = $msg['id'] ?? null;
		$params = $msg['params'] ?? [];

		// Notifications have no id — acknowledge without a response body.
		if ( null === $id && 0 === strpos( (string) $method, 'notifications/' ) ) {
			return null;
		}

		switch ( $method ) {
			case 'initialize':
				return self::result( $id, [
					'protocolVersion' => self::PROTOCOL_VERSION,
					'capabilities'    => [ 'tools' => [ 'listChanged' => false ] ],
					'serverInfo'      => [
						'name'    => 'Zaplane',
						'version' => defined( 'ZAPLANE_VERSION' ) ? ZAPLANE_VERSION : '1.0.0',
					],
				] );

			case 'ping':
				return self::result( $id, (object) [] );

			case 'tools/list':
				return self::result( $id, [ 'tools' => array_values( self::tool_defs() ) ] );

			case 'tools/call':
				return $this->call_tool( $id, $params );
		}

		return self::error( $id, -32601, 'Method not found: ' . $method );
	}

	/* ---------------------------------------------------------------------- */

	private function call_tool( $id, array $params ) {
		$name = $params['name'] ?? '';
		$args = (array) ( $params['arguments'] ?? [] );

		$map = [
			'list_integrations'           => 'tool_list_integrations',
			'list_workflows'              => 'tool_list_workflows',
			'run_workflow'                => 'tool_run_workflow',
			'list_recipes'                => 'tool_list_recipes',
			'create_workflow_from_recipe' => 'tool_create_workflow_from_recipe',
			'list_runs'                   => 'tool_list_runs',
			'search_knowledge'            => 'tool_search_knowledge',
		];

		if ( ! isset( $map[ $name ] ) ) {
			return self::error( $id, -32602, 'Unknown tool: ' . $name );
		}

		try {
			$data = $this->{$map[ $name ]}( $args );
		} catch ( \Throwable $e ) {
			return self::result( $id, [
				'content' => [ [ 'type' => 'text', 'text' => 'Error: ' . $e->getMessage() ] ],
				'isError' => true,
			] );
		}

		return self::result( $id, [
			'content' => [ [ 'type' => 'text', 'text' => wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ] ],
		] );
	}

	/* ----------------------------- Tools ---------------------------------- */

	private function tool_list_integrations( array $args ): array {
		$file = ZAPLANE_ROOT_DIR_PATH . 'assets/json/integrations.json';
		if ( ! file_exists( $file ) ) {
			return [ 'apps' => [], 'tools' => [] ];
		}
		$m    = json_decode( file_get_contents( $file ), true ); // phpcs:ignore
		$apps = [];
		foreach ( (array) ( $m['apps'] ?? [] ) as $slug => $a ) {
			$apps[] = [
				'slug'     => $slug,
				'name'     => $a['name'] ?? $slug,
				'triggers' => array_keys( $a['triggers'] ?? [] ),
				'actions'  => array_keys( $a['actions'] ?? [] ),
			];
		}
		$tools = [];
		foreach ( (array) ( $m['tools'] ?? [] ) as $slug => $a ) {
			$tools[] = [ 'slug' => $slug, 'name' => $a['name'] ?? $slug, 'actions' => array_keys( $a['actions'] ?? [] ) ];
		}
		return [ 'apps' => $apps, 'tools' => $tools ];
	}

	private function tool_list_workflows( array $args ): array {
		global $wpdb;
		$t = $wpdb->prefix . 'zaplane_workflows';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT id, title, status FROM {$t} ORDER BY id DESC LIMIT 100", ARRAY_A );
		return [ 'workflows' => is_array( $rows ) ? $rows : [] ];
	}

	private function tool_run_workflow( array $args ): array {
		$id = (int) ( $args['workflow_id'] ?? 0 );
		if ( ! $id ) {
			throw new \Exception( 'workflow_id is required.' );
		}
		if ( ! function_exists( 'zaplane_run_workflow' ) ) {
			throw new \Exception( 'Run engine unavailable.' );
		}
		$data   = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : [];
		$run_id = zaplane_run_workflow( $id, $data );
		if ( ! $run_id ) {
			throw new \Exception( 'Failed to start workflow (not found or no active version).' );
		}
		return [ 'started' => true, 'run_id' => $run_id, 'workflow_id' => $id ];
	}

	private function tool_list_recipes( array $args ): array {
		global $wpdb;
		$t = $wpdb->prefix . 'zaplane_recipes';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT id, title, description FROM {$t} ORDER BY id ASC LIMIT 200", ARRAY_A );
		return [ 'recipes' => is_array( $rows ) ? $rows : [] ];
	}

	private function tool_create_workflow_from_recipe( array $args ): array {
		$recipe_id = (int) ( $args['recipe_id'] ?? 0 );
		$recipe    = $recipe_id ? Recipe::find( $recipe_id ) : null;
		if ( ! $recipe ) {
			throw new \Exception( 'Recipe not found.' );
		}
		$blueprint = $recipe->getBlueprint();
		if ( empty( $blueprint ) ) {
			throw new \Exception( 'Recipe blueprint is empty.' );
		}
		$title    = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
		$workflow = ( new BlueprintService() )->import( $blueprint, $title );

		return [
			'workflow_id'           => $workflow->id,
			'title'                 => $workflow->title,
			'status'                => $workflow->status,
			'connections_to_relink' => $blueprint['connections'] ?? [],
		];
	}

	private function tool_list_runs( array $args ): array {
		global $wpdb;
		$limit = min( 100, max( 1, (int) ( $args['limit'] ?? 20 ) ) );
		$t     = $wpdb->prefix . 'zaplane_runs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, workflow_id, status, started_at, finished_at FROM {$t} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
		return [ 'runs' => is_array( $rows ) ? $rows : [] ];
	}

	private function tool_search_knowledge( array $args ): array {
		if ( ! class_exists( '\Zaplane\Integrations\Knowledge' ) ) {
			throw new \Exception( 'Knowledge integration unavailable.' );
		}
		$node = [ 'data' => [ 'event' => 'retrieve', 'config' => [
			'business_key' => (string) ( $args['business_key'] ?? '' ),
			'query'        => (string) ( $args['query'] ?? '' ),
			'limit'        => (int) ( $args['limit'] ?? 5 ),
		] ] ];
		$res  = \Zaplane\Integrations\Knowledge::execute_node( $node, [] );
		$data = $res['data'] ?? [];
		return [
			'context' => $data['context'] ?? '',
			'matches' => $data['matches'] ?? [],
		];
	}

	/* --------------------------- Tool schemas ----------------------------- */

	private static function tool_defs(): array {
		$obj = fn( $props = [], $required = [] ) => [
			'type'       => 'object',
			'properties' => (object) $props,
			'required'   => $required,
		];

		return [
			[
				'name'        => 'list_integrations',
				'description' => 'List all available Zaplane integrations with their triggers and actions.',
				'inputSchema' => $obj(),
			],
			[
				'name'        => 'list_workflows',
				'description' => 'List Zaplane workflows (id, title, status).',
				'inputSchema' => $obj(),
			],
			[
				'name'        => 'run_workflow',
				'description' => 'Run a Zaplane workflow now by id, with optional trigger data.',
				'inputSchema' => $obj( [
					'workflow_id' => [ 'type' => 'integer', 'description' => 'Workflow id to run.' ],
					'data'        => [ 'type' => 'object', 'description' => 'Optional trigger data passed to the workflow.' ],
				], [ 'workflow_id' ] ),
			],
			[
				'name'        => 'list_recipes',
				'description' => 'List available Zaplane recipes (workflow templates).',
				'inputSchema' => $obj(),
			],
			[
				'name'        => 'create_workflow_from_recipe',
				'description' => 'Create a new workflow from a recipe id. Connections must be re-linked afterward.',
				'inputSchema' => $obj( [
					'recipe_id' => [ 'type' => 'integer', 'description' => 'Recipe id to instantiate.' ],
					'title'     => [ 'type' => 'string', 'description' => 'Optional title for the new workflow.' ],
				], [ 'recipe_id' ] ),
			],
			[
				'name'        => 'list_runs',
				'description' => 'List recent workflow runs and their status.',
				'inputSchema' => $obj( [
					'limit' => [ 'type' => 'integer', 'description' => 'Max runs to return (default 20).' ],
				] ),
			],
			[
				'name'        => 'search_knowledge',
				'description' => 'Search a business knowledge base (products, prices, FAQ) for relevant entries.',
				'inputSchema' => $obj( [
					'business_key' => [ 'type' => 'string', 'description' => 'Business key, e.g. business_a.' ],
					'query'        => [ 'type' => 'string', 'description' => 'What to search for.' ],
					'limit'        => [ 'type' => 'integer', 'description' => 'Max results (default 5).' ],
				], [ 'business_key', 'query' ] ),
			],
		];
	}

	/* --------------------------- JSON-RPC helpers ------------------------- */

	private static function result( $id, $result ): array {
		return [ 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result ];
	}

	private static function error( $id, int $code, string $message ): array {
		return [ 'jsonrpc' => '2.0', 'id' => $id, 'error' => [ 'code' => $code, 'message' => $message ] ];
	}
}
