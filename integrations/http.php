<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Http extends IntegrationBase {

	/* ---------------------------------------------------------
	 * Identity
	 * --------------------------------------------------------- */

	public static function get_slug(): string { return 'http'; }
	public static function get_name(): string { return 'HTTP Request'; }
	public static function get_icon(): string { return 'http'; }
	public static function get_category(): string { return 'tool'; }

	/* ---------------------------------------------------------
	 * Actions
	 * --------------------------------------------------------- */

	public static function get_actions(): array {
		return [
			'request' => [ 'label' => 'Send HTTP Request' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		return [
			[ 'key' => 'url',     'label' => 'URL',             'type' => 'expression', 'required' => true ],
			[ 'key' => 'method',  'label' => 'Method',          'type' => 'select', 'options' => [
				[ 'label' => 'GET',    'value' => 'GET' ],
				[ 'label' => 'POST',   'value' => 'POST' ],
				[ 'label' => 'PUT',    'value' => 'PUT' ],
				[ 'label' => 'PATCH',  'value' => 'PATCH' ],
				[ 'label' => 'DELETE', 'value' => 'DELETE' ],
			] ],
			[ 'key' => 'headers', 'label' => 'Headers (JSON)',  'type' => 'textarea' ],
			[ 'key' => 'body',    'label' => 'Body',            'type' => 'expression' ],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];

		$url     = $config['url'] ?? '';
		$method  = $config['method'] ?? 'GET';
		$body    = $config['body'] ?? '';
		$headers = json_decode( $config['headers'] ?? '{}', true );

		if ( ! is_array( $headers ) ) {
			$headers = [];
		}

		if ( empty( $url ) ) {
			throw new \Exception( 'URL is required for HTTP request' );
		}

		$response = wp_remote_request( $url, [
			'method'  => $method,
			'headers' => $headers,
			'body'    => $body,
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'HTTP request failed: ' . $response->get_error_message() );
		}

		$response_body = wp_remote_retrieve_body( $response );
		$decoded       = json_decode( $response_body, true );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'http_status'  => wp_remote_retrieve_response_code( $response ),
				'http_body'    => is_array( $decoded ) ? $decoded : $response_body,
				'http_headers' => wp_remote_retrieve_headers( $response )->getAll(),
			] ),
		];
	}
}
