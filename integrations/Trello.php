<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\ExternalAppIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trello extends ExternalAppIntegration {

	private const API_BASE_URL = 'https://api.trello.com/1';

	/* ---------------------------------------------------------
	 * Identity
	 * --------------------------------------------------------- */

	public static function get_slug(): string { return 'trello'; }
	public static function get_name(): string { return 'Trello'; }
	public static function get_icon(): string { return 'trello'; }

	/* ---------------------------------------------------------
	 * Authentication
	 * --------------------------------------------------------- */

	public static function get_auth_type(): string { return 'api_key'; }

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'api_key' => [
				'type'        => 'text',
				'label'       => 'API Key',
				'required'    => true,
				'help'        => 'Get from trello.com/app-key',
			],
			'token' => [
				'type'        => 'password',
				'label'       => 'Token',
				'required'    => true,
				'help'        => 'Generate a token after getting your API key',
			],
		];
	}

	protected static function build_auth_header( array $credentials ): ?string {
		return null; // Trello uses query params, not headers
	}

	public static function test_connection( array $credentials ): array {
		try {
			$result = self::trello_get( '/members/me', $credentials );
			return [
				'success' => true,
				'message' => 'Connected as: ' . ( $result['fullName'] ?? $result['username'] ?? '' ),
				'details' => $result,
			];
		} catch ( \Exception $e ) {
			return [ 'success' => false, 'message' => $e->getMessage(), 'details' => [] ];
		}
	}

	/* ---------------------------------------------------------
	 * Triggers
	 * --------------------------------------------------------- */

	public static function get_triggers(): array {
		return [
			'card_created' => [
				'label' => 'Card Created',
				'hook'  => 'trello_webhook_card_created',
			],
			'card_moved' => [
				'label' => 'Card Moved',
				'hook'  => 'trello_webhook_card_moved',
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$data = $args[0] ?? [];
		if ( empty( $data ) ) return false;

		return [
			'action_type' => $data['action']['type'] ?? '',
			'card_id'     => $data['action']['data']['card']['id'] ?? '',
			'card_name'   => $data['action']['data']['card']['name'] ?? '',
			'board_id'    => $data['action']['data']['board']['id'] ?? '',
			'list_id'     => $data['action']['data']['list']['id'] ?? '',
		];
	}

	/* ---------------------------------------------------------
	 * Actions
	 * --------------------------------------------------------- */

	public static function get_actions(): array {
		return [
			'create_card' => [ 'label' => 'Create Card' ],
			'update_card' => [ 'label' => 'Update Card' ],
			'move_card'   => [ 'label' => 'Move Card to List' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		if ( $action === 'create_card' ) {
			return [
				[ 'key' => 'list_id', 'label' => 'List ID',     'type' => 'text', 'required' => true ],
				[ 'key' => 'name',    'label' => 'Card Name',   'type' => 'expression', 'required' => true ],
				[ 'key' => 'desc',    'label' => 'Description', 'type' => 'textarea' ],
			];
		}
		if ( $action === 'update_card' ) {
			return [
				[ 'key' => 'card_id', 'label' => 'Card ID', 'type' => 'expression', 'required' => true ],
				[ 'key' => 'name',    'label' => 'New Name', 'type' => 'expression' ],
				[ 'key' => 'desc',    'label' => 'New Description', 'type' => 'textarea' ],
			];
		}
		if ( $action === 'move_card' ) {
			return [
				[ 'key' => 'card_id', 'label' => 'Card ID', 'type' => 'expression', 'required' => true ],
				[ 'key' => 'list_id', 'label' => 'Target List ID', 'type' => 'text', 'required' => true ],
			];
		}
		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$action = $node['config']['action'] ?? $node['data']['event'] ?? '';
		$config = $node['config']['data'] ?? $node['data']['config'] ?? [];
		$creds  = $node['_connection_credentials'] ?? [];

		if ( $action === 'create_card' ) {
			$result = self::trello_post( '/cards', [
				'idList' => $config['list_id'] ?? '',
				'name'   => $config['name'] ?? '',
				'desc'   => $config['desc'] ?? '',
			], $creds );
			return [ 'port' => 'main', 'data' => array_merge( $input, [ 'card_id' => $result['id'] ?? '' ] ) ];
		}

		if ( $action === 'update_card' ) {
			$card_id = $config['card_id'] ?? '';
			$body = [];
			if ( ! empty( $config['name'] ) ) $body['name'] = $config['name'];
			if ( ! empty( $config['desc'] ) ) $body['desc'] = $config['desc'];

			$result = self::trello_request( 'PUT', '/cards/' . $card_id, $body, $creds );
			return [ 'port' => 'main', 'data' => array_merge( $input, [ 'card_id' => $card_id, 'updated' => true ] ) ];
		}

		if ( $action === 'move_card' ) {
			$card_id = $config['card_id'] ?? '';
			$result = self::trello_request( 'PUT', '/cards/' . $card_id, [
				'idList' => $config['list_id'] ?? '',
			], $creds );
			return [ 'port' => 'main', 'data' => array_merge( $input, [ 'card_id' => $card_id, 'moved' => true ] ) ];
		}

		return [ 'port' => 'main', 'data' => $input ];
	}

	public static function get_rate_limit(): int {
		return 100;
	}

	/* ---------------------------------------------------------
	 * Trello API Helpers
	 * --------------------------------------------------------- */

	private static function trello_get( string $path, array $creds ): array {
		return self::trello_request( 'GET', $path, [], $creds );
	}

	private static function trello_post( string $path, array $body, array $creds ): array {
		return self::trello_request( 'POST', $path, $body, $creds );
	}

	private static function trello_request( string $method, string $path, array $body, array $creds ): array {
		$url = self::API_BASE_URL . $path;

		// Trello authenticates via query params
		$auth_params = [
			'key'   => $creds['api_key'] ?? '',
			'token' => $creds['token'] ?? '',
		];
		$url .= ( strpos( $url, '?' ) !== false ? '&' : '?' ) . http_build_query( $auth_params );

		$args = [
			'method'  => $method,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'timeout' => 30,
		];

		if ( ! empty( $body ) && $method !== 'GET' ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Trello API request failed: ' . $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status >= 400 ) {
			throw new \Exception( 'Trello API error (' . $status . '): ' . ( $decoded['message'] ?? wp_remote_retrieve_body( $response ) ) );
		}

		return is_array( $decoded ) ? $decoded : [];
	}
}
