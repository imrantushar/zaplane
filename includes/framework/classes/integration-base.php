<?php
namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class IntegrationBase {


	abstract public static function get_slug(): string;

	public static function get_name(): string {
		return ucfirst( static::get_slug() );
	}

	public static function get_icon(): string {
		return '';
	}

	public static function get_category(): string {
		return 'app';
	}





	public static function get_triggers(): array {
		return [];
	}



	public static function get_actions(): array {
		return [];
	}





	public static function resolve_trigger( array $node, array $hook_args ) {
		return false;
	}



	public static function execute_node( array $node, array $input ): array {
		return [
			'port' => 'main',
			'data' => $input,
		];
	}



	public static function validate_config( array $config ): bool {
		return true;
	}

	public static function get_config_schema(): array {
		return [];
	}



	public static function get_output_ports(): array {
		return [ 'main' ];
	}



	public static function supports_webhook(): bool {
		return false;
	}



	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		return true;
	}



	public static function parse_webhook_event( \WP_REST_Request $request ): ?array {
		return null;
	}

	public static function supports_polling(): bool {
		return false;
	}

	public static function get_rate_limit(): int {
		return 0;
	}



	public static function get_trigger_config_schema( string $trigger ): array {
		return [];
	}



	public static function get_action_config_schema( string $action ): array {
		return [];
	}



	public static function get_dynamic_fields(): array {
		return [];
	}





	public static function requires_connection(): bool {
		return false;
	}



	public static function get_auth_type(): string {
		return 'none';
	}



	public static function get_available_auth_types(): array {
		return [];
	}



	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [];
	}



	public static function test_connection( array $credentials ): array {
		return [
			'success' => true,
			'message' => 'Connection test not implemented for this integration',
			'details' => [],
		];
	}





	public static function get_oauth_auth_url( string $redirect_uri, string $state, array $credentials = [] ): ?string {
		return null;
	}



	public static function exchange_oauth_code( string $code, string $redirect_uri, array $credentials = [] ): array {
		return [];
	}



	public static function refresh_oauth_token( string $refresh_token ): array {
		return [];
	}



	public static function get_oauth_scopes(): array {
		return [];
	}





	protected static function http_get( string $url, array $headers = [] ): array {
		return static::http_request( 'GET', $url, [ 'headers' => $headers ] );
	}



	protected static function http_post( string $url, array $body = [], array $headers = [] ): array {
		return static::http_request('POST', $url, [
			'headers' => array_merge( [ 'Content-Type' => 'application/json' ], $headers ),
			'body'    => wp_json_encode( $body ),
		]);
	}



	protected static function http_request( string $method, string $url, array $args = [] ): array {
		$response = wp_remote_request( $url, array_merge( [ 'method' => strtoupper( $method ) ], $args ) );
		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'HTTP request failed: ' . $response->get_error_message() );
		}
		$body   = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
		$status = (int) wp_remote_retrieve_response_code( $response );
		return [ $body, $status ];
	}
}
