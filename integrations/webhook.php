<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

/**
 * Webhook — a dedicated integration for both directions:
 *
 *  • Incoming ("Catch Webhook" trigger): any service can POST/GET to the
 *    workflow's webhook URL (/wp-json/zaplane/v1/hook/<workflow_id>) and the
 *    whole payload becomes the trigger output. An optional secret can be
 *    required. Fired by IncomingWebhookController::handle_workflow_hook() via
 *    run_workflow — the hook name only marks it as an active trigger.
 *
 *  • Outgoing ("Send Webhook" action): POST/PUT/PATCH/GET/DELETE a payload to an
 *    external URL, with custom headers and an optional HMAC-SHA256 signature of
 *    the raw body so the receiver can verify authenticity. This is the piece the
 *    generic HTTP Request tool doesn't do for you.
 */
class Webhook extends IntegrationBase {

	public static function get_slug(): string {
		return 'webhook';
	}

	public static function get_name(): string {
		return 'Webhook';
	}

	public static function get_icon(): string {
		return 'webhook.svg';
	}

	/** @inheritDoc */
	public static function get_docs_url(): array {
		return [
			'trigger' => 'https://zaplane.app/docs/webhook/',
			'action'  => 'https://zaplane.app/docs/webhook/',
		];
	}

	/*
	 ---------------------------------------------------------------------
	 * Incoming — Catch Webhook trigger
	 * ------------------------------------------------------------------- */

	public static function get_triggers(): array {
		return [
			'catch_hook' => [
				'label' => 'Catch Webhook',
				'hook'  => 'zaplane/webhook/catch',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'catch_hook' !== $trigger ) {
			return [];
		}

		return [
			[
				// Read-only URL to POST to. The `{workflow_id}` token is resolved
				// on the client from the open workflow, then prefixed with the
				// site's REST base (the CopyInput renderer handles both).
				'key'   => 'webhook_url',
				'label' => 'Webhook URL',
				'type'  => 'copy',
				'value' => 'zaplane/v1/hook/{workflow_id}',
				'help'  => 'Send a GET or POST request (JSON body and/or query params) to this URL to trigger the workflow. Available after the workflow is saved.',
			],
			[
				'key'      => 'secret',
				'label'    => 'Secret (optional)',
				'type'     => 'expression',
				'required' => false,
				'help'     => 'If set, requests must include ?secret=... (or an X-Zaplane-Secret header) that matches.',
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$payload = $args[0] ?? [];
		return is_array( $payload ) ? $payload : [ 'body' => $payload ];
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		return [
			'example' => 'value',
			'nested' => [ 'a' => 1 ]
		];
	}

	/*
	 ---------------------------------------------------------------------
	 * Outgoing — Send Webhook action
	 * ------------------------------------------------------------------- */

	public static function get_actions(): array {
		return [
			'send_hook' => [ 'label' => 'Send Webhook' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		if ( 'send_hook' !== $action ) {
			return [];
		}

		$with_body = [ 'method' => [ 'POST', 'PUT', 'PATCH', 'DELETE' ] ];

		return [
			[
				'key'      => 'url',
				'label'    => 'Webhook URL',
				'type'     => 'expression',
				'required' => true,
				'help'     => 'The endpoint that should receive this webhook.',
			],
			[
				'key'     => 'method',
				'label'   => 'Method',
				'type'    => 'select',
				'default' => 'POST',
				'options' => [
					[
						'label' => 'POST',
						'value' => 'POST'
					],
					[
						'label' => 'PUT',
						'value' => 'PUT'
					],
					[
						'label' => 'PATCH',
						'value' => 'PATCH'
					],
					[
						'label' => 'GET',
						'value' => 'GET'
					],
					[
						'label' => 'DELETE',
						'value' => 'DELETE'
					],
				],
			],
			[
				'key'        => 'payload_type',
				'label'      => 'Payload format',
				'type'       => 'select',
				'default'    => 'json',
				'depends_on' => $with_body,
				'options'    => [
					[
						'label' => 'JSON',
						'value' => 'json'
					],
					[
						'label' => 'Form (url-encoded)',
						'value' => 'form'
					],
					[
						'label' => 'Raw',
						'value' => 'raw'
					],
				],
			],
			[
				'key'        => 'payload_fields',
				'label'      => 'Body fields',
				'type'       => 'map',
				'depends_on' => $with_body,
				'help'       => 'Recommended. Build the body field by field — each value is safely encoded, so multi-line or quoted values (e.g. an AI reply) never break the JSON. Use "@" in a value to insert data from earlier steps.',
				'fields'     => [
					[
						'key' => 'key',
						'label' => 'Field',
						'type' => 'text'
					],
					[
						'key' => 'value',
						'label' => 'Value',
						'type' => 'expression'
					],
				],
			],
			[
				'key'        => 'payload',
				'label'      => 'Raw body',
				'type'       => 'expression',
				'depends_on' => $with_body,
				'help'       => 'Optional. A raw body string, used only when no Body fields are set above. For JSON, write the object yourself (remember to escape values).',
			],
			[
				'key'    => 'headers',
				'label'  => 'Headers',
				'type'   => 'map',
				'help'   => 'Optional. Add a row per header (e.g. Authorization).',
				'fields' => [
					[
						'key' => 'key',
						'label' => 'Header',
						'type' => 'text'
					],
					[
						'key' => 'value',
						'label' => 'Value',
						'type' => 'expression'
					],
				],
			],
			[
				'key'     => 'sign_type',
				'label'   => 'Signature',
				'type'    => 'select',
				'default' => 'none',
				'help'    => 'Optionally sign the raw body so the receiver can verify the request came from you.',
				'options' => [
					[
						'label' => 'None',
						'value' => 'none'
					],
					[
						'label' => 'HMAC SHA-256',
						'value' => 'hmac_sha256'
					],
				],
			],
			[
				'key'        => 'sign_secret',
				'label'      => 'Signing secret',
				'type'       => 'expression',
				'depends_on' => [ 'sign_type' => 'hmac_sha256' ],
			],
			[
				'key'        => 'sign_header',
				'label'      => 'Signature header',
				'type'       => 'text',
				'default'    => 'X-Zaplane-Signature',
				'depends_on' => [ 'sign_type' => 'hmac_sha256' ],
				'help'       => 'The header the signature is sent in. Value format: sha256=<hex digest>.',
			],
			[
				'key'     => 'timeout',
				'label'   => 'Timeout (seconds)',
				'type'    => 'number',
				'default' => 15,
			],
		];
	}

	public static function get_action_sample_output( string $action ): array {
		return [
			'success' => true,
			'status'  => 200,
			'body'    => null,
			'headers' => [],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$c      = $node['data']['config'] ?? [];
		$url    = is_string( $c['url'] ?? '' ) ? trim( (string) $c['url'] ) : '';
		$method = strtoupper( (string) ( $c['method'] ?? 'POST' ) );

		// SSRF guard: only http/https, and a filter lets sites add their own
		// block list (RFC1918 / 169.254/16 / 127.0.0.0/8 …). Shared with the HTTP
		// Request tool via the same `zaplane_http_block_request` filter.
		$parsed = '' !== $url ? wp_parse_url( $url ) : null;
		$scheme = strtolower( (string) ( $parsed['scheme'] ?? '' ) );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return self::error_output( "Webhook refused: scheme `{$scheme}` is not in the allowlist (http, https)." );
		}
		if ( apply_filters( 'zaplane_http_block_request', false, $url, $parsed ) ) {
			return self::error_output( 'Webhook refused by the zaplane_http_block_request filter.' );
		}

		$headers   = self::kv_to_assoc( $c['headers'] ?? [] );
		$has_body  = in_array( $method, [ 'POST', 'PUT', 'PATCH', 'DELETE' ], true );
		$body      = null;

		if ( $has_body ) {
			// Prefer the structured field builder: an assoc array is JSON/form encoded
			// with proper escaping, so multi-line or quoted values (an AI reply, HTML,
			// etc.) can't produce a malformed body. Fall back to a raw string body.
			$fields = self::kv_to_assoc( $c['payload_fields'] ?? [] );
			$source = ! empty( $fields ) ? $fields : ( $c['payload'] ?? '' );
			$body   = self::encode_body( $source, (string) ( $c['payload_type'] ?? 'json' ), $headers );
		}

		// Sign the exact bytes we're about to send so the receiver can recompute
		// the digest over the raw body and compare.
		if ( 'hmac_sha256' === ( $c['sign_type'] ?? 'none' ) ) {
			$secret = (string) ( $c['sign_secret'] ?? '' );
			$header = trim( (string) ( $c['sign_header'] ?? 'X-Zaplane-Signature' ) );
			if ( '' !== $secret && '' !== $header ) {
				$headers[ $header ] = 'sha256=' . hash_hmac( 'sha256', (string) $body, $secret );
			}
		}

		$response = wp_remote_request( $url, [
			'method'  => $method,
			'headers' => $headers,
			'body'    => $body,
			'timeout' => max( 1, (int) ( $c['timeout'] ?? 15 ) ),
		] );

		if ( is_wp_error( $response ) ) {
			return self::error_output( $response->get_error_message(), 500 );
		}

		$response_body = wp_remote_retrieve_body( $response );

		// Parse JSON responses so downstream nodes can use dot notation.
		$decoded = json_decode( $response_body, true );
		if ( JSON_ERROR_NONE === json_last_error() ) {
			$response_body = $decoded;
		}

		$response_headers = wp_remote_retrieve_headers( $response );
		$headers_array    = is_object( $response_headers ) && method_exists( $response_headers, 'getAll' )
			? $response_headers->getAll()
			: (array) $response_headers;

		$status = (int) wp_remote_retrieve_response_code( $response );

		return [
			'port' => 'main',
			'data' => [
				'success' => $status >= 200 && $status < 300,
				'status'  => $status,
				'body'    => $response_body,
				'headers' => $headers_array,
			],
		];
	}

	/**
	 * Encode the payload per the chosen format, defaulting the Content-Type
	 * header when the user hasn't set one. Returns the raw string body to send.
	 *
	 * @param mixed               $payload
	 * @param array<string,mixed> $headers Passed by reference to set Content-Type.
	 */
	protected static function encode_body( $payload, string $type, array &$headers ): string {
		$has_ct = false;
		foreach ( array_keys( $headers ) as $h ) {
			if ( 0 === strcasecmp( (string) $h, 'Content-Type' ) ) {
				$has_ct = true;
				break;
			}
		}

		if ( 'form' === $type ) {
			$data = is_array( $payload ) ? $payload : self::kv_to_assoc( $payload );
			if ( ! $has_ct ) {
				$headers['Content-Type'] = 'application/x-www-form-urlencoded';
			}
			return http_build_query( $data );
		}

		if ( 'raw' === $type ) {
			return is_scalar( $payload ) ? (string) $payload : (string) wp_json_encode( $payload );
		}

		// JSON (default).
		if ( is_array( $payload ) ) {
			$body = (string) wp_json_encode( $payload );
		} else {
			// A string is assumed to already be JSON (possibly built from tokens).
			$body = (string) $payload;
		}
		if ( ! $has_ct && '' !== trim( $body ) ) {
			$headers['Content-Type'] = 'application/json';
		}
		return $body;
	}

	/**
	 * @return array{port:string,data:array<string,mixed>}
	 */
	protected static function error_output( string $error, int $status = 0 ): array {
		return [
			'port' => 'main',
			'data' => [
				'success' => false,
				'status'  => $status,
				'error'   => $error,
				'body'    => null,
				'headers' => [],
			],
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
}
