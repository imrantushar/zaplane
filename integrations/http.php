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
				'options' => [
					[
						'label' => 'GET',
						'value' => 'GET'
					],
					[
						'label' => 'POST',
						'value' => 'POST'
					],
					[
						'label' => 'PUT',
						'value' => 'PUT'
					],
					[
						'label' => 'DELETE',
						'value' => 'DELETE'
					],
				]
			],
			[
				'key' => 'headers',
				'label' => 'Headers (JSON)',
				'type' => 'textarea'
			],
			[
				'key' => 'body',
				'label' => 'Body',
				'type' => 'expression'
			],
		];
	}

	public static function execute_node( array $node, array $input ): array {

		$c = $node['data']['config'] ?? [];

		$url = $c['url'] ?? '';
		$body = $c['body'] ?? '';

		$headers = $c['headers'] ?? [];
		if ( is_string( $headers ) ) {
			$decoded = json_decode( $headers, true );
			$headers = is_array( $decoded ) ? $decoded : [];
		}

		// If the user mapped an array dynamically directly into the body field, encode to JSON for HTTP transport unless it's form-encoded (which we'll just encode standard for now)
		if ( is_array( $body ) ) {
			$body = wp_json_encode( $body );
			if ( ! isset( $headers['Content-Type'] ) ) {
				$headers['Content-Type'] = 'application/json';
			}
		}

		$response = wp_remote_request($url, [
			'method' => $c['method'] ?? 'GET',
			'headers' => $headers,
			'body' => $body
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
}
