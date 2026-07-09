<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

class Http extends IntegrationBase {


	public static function get_slug(): string {
		return 'http';
	}

	public static function get_name(): string {
		return 'HTTP Request';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_icon(): string {
		return 'http-request';
	}

	public static function get_actions(): array {
		return [
			'request' => [ 'label' => 'Send HTTP Request' ]
		];
	}

	public static function get_action_config_schema( string $action ): array {
		return [
			[
				'key' => 'url',
				'label' => 'URL',
				'type' => 'expression',
				'required' => true
			],
			[
				'key' => 'method',
				'label' => 'Method',
				'type' => 'select',
				'default' => 'GET',
				'options' => [
					[ 'label' => 'GET', 'value' => 'GET' ],
					[ 'label' => 'POST', 'value' => 'POST' ],
					[ 'label' => 'PUT', 'value' => 'PUT' ],
					[ 'label' => 'PATCH', 'value' => 'PATCH' ],
					[ 'label' => 'DELETE', 'value' => 'DELETE' ],
					[ 'label' => 'HEAD', 'value' => 'HEAD' ],
				]
			],
			[
				'key'    => 'query_params',
				'label'  => 'Query Parameters',
				'type'   => 'map',
				'help'   => 'Appended to the URL as ?key=value. Add a row per parameter.',
				'fields' => [
					[ 'key' => 'key',   'label' => 'Key',   'type' => 'text' ],
					[ 'key' => 'value', 'label' => 'Value', 'type' => 'expression' ],
				],
			],
			[
				'key'     => 'auth_type',
				'label'   => 'Authentication',
				'type'    => 'select',
				'default' => 'none',
				'options' => [
					[ 'label' => 'None', 'value' => 'none' ],
					[ 'label' => 'Bearer token', 'value' => 'bearer' ],
					[ 'label' => 'Basic auth', 'value' => 'basic' ],
					[ 'label' => 'API key header', 'value' => 'api_key' ],
				],
			],
			[ 'key' => 'auth_token', 'label' => 'Bearer token', 'type' => 'expression', 'depends_on' => [ 'auth_type' => 'bearer' ] ],
			[ 'key' => 'auth_user', 'label' => 'Username', 'type' => 'expression', 'depends_on' => [ 'auth_type' => 'basic' ] ],
			[ 'key' => 'auth_pass', 'label' => 'Password', 'type' => 'expression', 'depends_on' => [ 'auth_type' => 'basic' ] ],
			[ 'key' => 'auth_header', 'label' => 'Header name', 'type' => 'text', 'default' => 'X-API-Key', 'depends_on' => [ 'auth_type' => 'api_key' ] ],
			[ 'key' => 'auth_value', 'label' => 'API key value', 'type' => 'expression', 'depends_on' => [ 'auth_type' => 'api_key' ] ],
			[
				'key'    => 'headers',
				'label'  => 'Headers',
				'type'   => 'map',
				'help'   => 'Add a row per header.',
				'fields' => [
					[ 'key' => 'key',   'label' => 'Header', 'type' => 'text' ],
					[ 'key' => 'value', 'label' => 'Value',  'type' => 'expression' ],
				],
			],
			[
				'key'        => 'body_type',
				'label'      => 'Body type',
				'type'       => 'select',
				'default'    => 'json',
				'depends_on' => [ 'method' => [ 'POST', 'PUT', 'PATCH', 'DELETE' ] ],
				'options'    => [
					[ 'label' => 'JSON', 'value' => 'json' ],
					[ 'label' => 'Form (url-encoded)', 'value' => 'form' ],
					[ 'label' => 'Raw', 'value' => 'raw' ],
				],
			],
			[
				'key' => 'body',
				'label' => 'Body',
				'type' => 'expression',
				'depends_on' => [ 'method' => [ 'POST', 'PUT', 'PATCH', 'DELETE' ] ],
			],
			[
				'key'     => 'timeout',
				'label'   => 'Timeout (seconds)',
				'type'    => 'number',
				'default' => 15,
			],
		];
	}

	public static function execute_node( array $node, array $input ): array {

		$c = $node['data']['config'] ?? [];

		$url = $c['url'] ?? '';
		$body = $c['body'] ?? '';

		// SSRF guard: only http/https are allowed, and a filter lets sites
		// add their own block list (e.g. RFC1918 / 169.254/16 / 127.0.0.0/8).
		$parsed = is_string( $url ) && '' !== $url ? wp_parse_url( $url ) : null;
		$scheme = strtolower( (string) ( $parsed['scheme'] ?? '' ) );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return [
				'port' => 'main',
				'data' => [
					'status'  => 0,
					'error'   => "HTTP request refused: scheme `{$scheme}` is not in the allowlist (http, https).",
					'body'    => null,
					'headers' => [],
				],
			];
		}
		if ( apply_filters( 'zaplane_http_block_request', false, $url, $parsed ) ) {
			return [
				'port' => 'main',
				'data' => [
					'status'  => 0,
					'error'   => 'HTTP request refused by the zaplane_http_block_request filter.',
					'body'    => null,
					'headers' => [],
				],
			];
		}

		$headers = self::kv_to_assoc( $c['headers'] ?? [] );

		// Append query parameters to the URL.
		$query = self::kv_to_assoc( $c['query_params'] ?? [] );
		if ( ! empty( $query ) ) {
			$url = add_query_arg( array_map( 'strval', $query ), $url );
		}

		// Authentication helper — spares the user hand-writing auth headers.
		$headers = self::apply_auth( $headers, $c );

		// Encode the body according to the chosen body type.
		$body_type = $c['body_type'] ?? 'json';
		if ( is_array( $body ) ) {
			if ( 'form' === $body_type ) {
				$body = http_build_query( $body );
				$headers['Content-Type'] = $headers['Content-Type'] ?? 'application/x-www-form-urlencoded';
			} else {
				$body = wp_json_encode( $body );
				$headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
			}
		} elseif ( 'json' === $body_type && is_string( $body ) && '' !== trim( $body ) && ! isset( $headers['Content-Type'] ) ) {
			$headers['Content-Type'] = 'application/json';
		}

		$response = wp_remote_request($url, [
			'method'  => $c['method'] ?? 'GET',
			'headers' => $headers,
			'body'    => $body,
			'timeout' => max( 1, (int) ( $c['timeout'] ?? 15 ) ),
		]);

		if ( is_wp_error( $response ) ) {
			return [
				'port' => 'main',
				'data' => [
					'status' => 500,
					'error'  => $response->get_error_message(),
					'body'   => null,
					'headers' => []
				]
			];
		}

		$response_body = wp_remote_retrieve_body( $response );

		// Attempt to parse the response body as JSON so downstream nodes can use dot notation (e.g. `1.data.body.user.name`)
		$parsed_body = json_decode( $response_body, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$response_body = $parsed_body;
		}

		$response_headers = wp_remote_retrieve_headers( $response );
		$headers_array = is_object( $response_headers ) && method_exists( $response_headers, 'getAll' ) ? $response_headers->getAll() : (array) $response_headers;

		return [
			'port' => 'main',
			'data' => [
				'status'  => wp_remote_retrieve_response_code( $response ),
				'body'    => $response_body,
				'headers' => $headers_array,
			]
		];
	}

	/**
	 * Normalise a key-value `map` field (array of {key,value} rows) — or a JSON
	 * string / associative array — into a plain associative array.
	 *
	 * @param mixed $val
	 * @return array<string,mixed>
	 */
	protected static function kv_to_assoc( $val ): array {
		if ( is_string( $val ) ) {
			$decoded = json_decode( $val, true );
			$val     = is_array( $decoded ) ? $decoded : [];
		}
		if ( ! is_array( $val ) ) {
			return [];
		}

		// Repeater/map rows: [ ['key' => …, 'value' => …], … ].
		$first = reset( $val );
		if ( is_array( $first ) && array_key_exists( 'key', $first ) ) {
			$out = [];
			foreach ( $val as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$k = trim( (string) ( $row['key'] ?? '' ) );
				if ( '' !== $k ) {
					$out[ $k ] = $row['value'] ?? '';
				}
			}
			return $out;
		}

		return $val;
	}

	/**
	 * Add an Authorization / API-key header based on the chosen auth type.
	 *
	 * @param array<string,mixed> $headers
	 * @param array<string,mixed> $c
	 * @return array<string,mixed>
	 */
	protected static function apply_auth( array $headers, array $c ): array {
		switch ( $c['auth_type'] ?? 'none' ) {
			case 'bearer':
				$token = trim( (string) ( $c['auth_token'] ?? '' ) );
				if ( '' !== $token ) {
					$headers['Authorization'] = 'Bearer ' . $token;
				}
				break;
			case 'basic':
				$headers['Authorization'] = 'Basic ' . base64_encode( (string) ( $c['auth_user'] ?? '' ) . ':' . (string) ( $c['auth_pass'] ?? '' ) );
				break;
			case 'api_key':
				$name = trim( (string) ( $c['auth_header'] ?? 'X-API-Key' ) );
				if ( '' !== $name ) {
					$headers[ $name ] = (string) ( $c['auth_value'] ?? '' );
				}
				break;
		}
		return $headers;
	}
}
