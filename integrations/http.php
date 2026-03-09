<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\Expression;

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

		$c = $node['data']['config'];

		$url = Expression::evaluate( $c['url'], $input );
		$body = Expression::evaluate( $c['body'] ?? '', $input );

		$headers = json_decode( $c['headers'] ?? '{}', true );

		$response = wp_remote_request($url, [
			'method' => $c['method'] ?? 'GET',
			'headers' => $headers,
			'body' => $body
		]);

		return [
			'port' => 'main',
			'data' => [
				'status' => wp_remote_retrieve_response_code( $response ),
				'body' => wp_remote_retrieve_body( $response ),
				'headers' => wp_remote_retrieve_headers( $response ),
			]
		];
	}
}
