<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

/**
 * MCP Client — call a tool on an external Model Context Protocol server from a
 * workflow step. Performs the JSON-RPC handshake (initialize → initialized →
 * tools/call) over Streamable HTTP, handling JSON or SSE responses and the
 * Mcp-Session-Id header.
 */
class Mcpclient extends IntegrationBase {

	private const PROTOCOL_VERSION = '2025-06-18';

	public static function get_slug(): string {
		return 'mcp-client';
	}

	public static function get_name(): string {
		return 'MCP Client';
	}

	public static function get_icon(): string {
		return 'mcp.svg';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_triggers(): array {
		return [];
	}

	public static function requires_connection(): bool {
		return false;
	}

	public static function get_actions(): array {
		return [
			'list_tools' => [ 'label' => 'List MCP Tools' ],
			'call_tool'  => [ 'label' => 'Call MCP Tool' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$server = [
			'key'         => 'server_url',
			'label'       => 'MCP Server URL',
			'type'        => 'expression',
			'required'    => true,
			'placeholder' => 'https://mcp.example.com/mcp',
		];
		$token = [
			'key'      => 'auth_token',
			'label'    => 'Bearer Token (optional)',
			'type'     => 'expression',
			'required' => false,
		];

		if ( 'list_tools' === $action ) {
			return [ $server, $token ];
		}

		if ( 'call_tool' === $action ) {
			return [
				$server,
				$token,
				[ 'key' => 'tool_name', 'label' => 'Tool Name', 'type' => 'expression', 'required' => true ],
				[ 'key' => 'arguments', 'label' => 'Arguments (JSON)', 'type' => 'textarea', 'required' => false, 'placeholder' => '{"query":"hello"}' ],
			];
		}

		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$event  = $node['data']['event'] ?? '';
		$config = $node['data']['config'] ?? [];

		$server = trim( (string) ( $config['server_url'] ?? '' ) );
		$token  = trim( (string) ( $config['auth_token'] ?? '' ) );

		if ( '' === $server ) {
			return self::respond( array_merge( $input, [ 'success' => false, 'error' => 'server_url is required.' ] ) );
		}

		// Handshake: initialize, capture session, send initialized notification.
		$init = self::rpc( $server, $token, null, 'initialize', [
			'protocolVersion' => self::PROTOCOL_VERSION,
			'capabilities'    => (object) [],
			'clientInfo'      => [ 'name' => 'Zaplane', 'version' => '1.0.0' ],
		] );

		if ( ! empty( $init['error'] ) ) {
			return self::respond( array_merge( $input, [ 'success' => false, 'error' => 'initialize failed: ' . $init['error'] ] ) );
		}

		$session = $init['session'] ?? '';
		self::rpc( $server, $token, $session, 'notifications/initialized', [], true );

		if ( 'list_tools' === $event ) {
			$res = self::rpc( $server, $token, $session, 'tools/list', [] );
			if ( ! empty( $res['error'] ) ) {
				return self::respond( array_merge( $input, [ 'success' => false, 'error' => $res['error'] ] ) );
			}
			$tools = $res['result']['tools'] ?? [];
			return self::respond( array_merge( $input, [ 'success' => true, 'tools' => $tools ] ) );
		}

		if ( 'call_tool' === $event ) {
			$tool = trim( (string) ( $config['tool_name'] ?? '' ) );
			if ( '' === $tool ) {
				return self::respond( array_merge( $input, [ 'success' => false, 'error' => 'tool_name is required.' ] ) );
			}

			$args = [];
			$raw  = trim( (string) ( $config['arguments'] ?? '' ) );
			if ( '' !== $raw ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					$args = $decoded;
				}
			}

			$res = self::rpc( $server, $token, $session, 'tools/call', [
				'name'      => $tool,
				'arguments' => (object) $args,
			] );

			if ( ! empty( $res['error'] ) ) {
				return self::respond( array_merge( $input, [ 'success' => false, 'error' => $res['error'] ] ) );
			}

			$text = self::extract_text( $res['result'] ?? [] );

			return self::respond( array_merge( $input, [
				'success' => empty( $res['result']['isError'] ),
				'result'  => $text,
				'raw'     => $res['result'] ?? null,
			] ) );
		}

		return self::respond( $input );
	}

	/**
	 * One JSON-RPC round trip. Returns [ 'result'=>..., 'session'=>..., 'error'=>string|null ].
	 * For notifications pass $notify=true (no id, no response expected).
	 */
	private static function rpc( string $url, string $token, ?string $session, string $method, array $params, bool $notify = false ): array {
		$payload = [ 'jsonrpc' => '2.0', 'method' => $method ];
		if ( ! $notify ) {
			$payload['id'] = wp_generate_uuid4();
		}
		if ( ! empty( $params ) || 'initialize' === $method || 'tools/call' === $method ) {
			$payload['params'] = $params ?: (object) [];
		}

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json, text/event-stream',
		];
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		if ( $session ) {
			$headers['Mcp-Session-Id'] = $session;
		}

		$response = wp_remote_post( $url, [
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			return [ 'error' => $response->get_error_message() ];
		}

		$new_session = wp_remote_retrieve_header( $response, 'mcp-session-id' );
		$body        = wp_remote_retrieve_body( $response );

		if ( $notify ) {
			return [ 'session' => $new_session ?: $session ];
		}

		$data = self::decode_body( $body );

		if ( isset( $data['error'] ) ) {
			$msg = is_array( $data['error'] ) ? ( $data['error']['message'] ?? 'RPC error' ) : (string) $data['error'];
			return [ 'error' => $msg, 'session' => $new_session ?: $session ];
		}

		return [ 'result' => $data['result'] ?? null, 'session' => $new_session ?: $session ];
	}

	/** Decode a response body that may be plain JSON or an SSE stream. */
	private static function decode_body( string $body ): array {
		$trimmed = ltrim( $body );

		if ( '' !== $trimmed && ( '{' === $trimmed[0] || '[' === $trimmed[0] ) ) {
			$json = json_decode( $body, true );
			return is_array( $json ) ? $json : [];
		}

		// SSE: collect "data:" lines and parse the JSON payload.
		$data_lines = [];
		foreach ( preg_split( '/\r\n|\n|\r/', $body ) as $line ) {
			if ( 0 === strpos( $line, 'data:' ) ) {
				$data_lines[] = trim( substr( $line, 5 ) );
			}
		}
		$joined = implode( '', $data_lines );
		$json   = json_decode( $joined, true );

		return is_array( $json ) ? $json : [];
	}

	/** Pull the text out of an MCP tool result's content blocks. */
	private static function extract_text( array $result ): string {
		$out = '';
		foreach ( (array) ( $result['content'] ?? [] ) as $block ) {
			if ( ( $block['type'] ?? '' ) === 'text' ) {
				$out .= $block['text'] ?? '';
			}
		}
		return $out;
	}

	private static function respond( array $data ): array {
		return [ 'port' => 'main', 'data' => $data ];
	}
}
