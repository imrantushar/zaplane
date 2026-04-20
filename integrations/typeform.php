<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Typeform extends IntegrationBase {

	private const API_BASE_URL = 'https://api.typeform.com';

	public static function get_slug(): string {
		return 'typeform';
	}

	public static function get_name(): string {
		return 'Typeform';
	}

	public static function get_icon(): string {
		return 'typeform.svg';
	}

	public static function requires_connection(): bool {
		return true;
	}

	public static function get_auth_type(): string {
		return 'api_key';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'access_token' => [
				'type'     => 'password',
				'label'    => 'Personal Access Token',
				'required' => true,
				'help'     => 'Generate from Typeform → Account → Personal tokens.',
			],
		];
	}

	public static function test_connection( array $credentials ): array {
		$token = $credentials['access_token'] ?? '';

		if ( empty( $token ) ) {
			return [ 'success' => false, 'message' => 'access_token is required', 'details' => [] ];
		}

		$response = wp_remote_get(
			self::API_BASE_URL . '/me',
			[
				'headers' => [ 'Authorization' => 'Bearer ' . $token ],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'message' => 'Connection failed: ' . $response->get_error_message(), 'details' => [] ];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( $code !== 200 || empty( $body['email'] ) ) {
			return [ 'success' => false, 'message' => 'Invalid token (HTTP ' . $code . ')', 'details' => [] ];
		}

		return [
			'success' => true,
			'message' => 'Connected as: ' . $body['email'],
			'details' => [
				'email' => $body['email'],
				'alias' => $body['alias'] ?? '',
			],
		];
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'typeform_webhook_form_submitted',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'form_submitted' === $trigger ) {
			return [
				[
					'key'      => 'form_id',
					'label'    => 'Form',
					'type'     => 'select',
					'required' => true,
					'help'     => 'Select the Typeform to watch. Choose "Any Form" to trigger on all submissions.',
					'dynamic'  => [
						'integration' => 'typeform',
						'query'       => 'form_query',
						'select'      => [ 'value', 'label' ],
					],
				],
			];
		}

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$payload       = $args[0] ?? [];
		$form_response = $payload['form_response'] ?? [];
		$form_id       = $form_response['form_id'] ?? '';

		if ( empty( $form_id ) ) {
			return false;
		}

		// Filter: only run if the user selected this specific form (or "any")
		$selected = $node['data']['config']['form_id'] ?? 'any';

		if ( ! empty( $selected ) && $selected !== 'any' && $selected !== $form_id ) {
			return false;
		}

		$definition = $form_response['definition'] ?? [];

		return [
			'typeform_form_id'      => $form_id,
			'typeform_form_title'   => $definition['title'] ?? '',
			'typeform_entry_token'  => $form_response['token'] ?? '',
			'typeform_landed_at'    => $form_response['landed_at'] ?? '',
			'typeform_submitted_at' => $form_response['submitted_at'] ?? '',
			'typeform_answers'      => self::parse_answers( $form_response['answers'] ?? [] ),
			'typeform_variables'    => $form_response['variables'] ?? [],
			'typeform_hidden'       => $form_response['hidden'] ?? [],
		];
	}

	public static function get_actions(): array {
		return [
			'create_form' => [ 'label' => 'Create Form' ],
			'delete_form' => [ 'label' => 'Delete Form' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		if ( 'create_form' === $action ) {
			return [
				[
					'key'         => 'title',
					'type'        => 'text',
					'label'       => 'Form Title',
					'placeholder' => 'My New Form',
					'required'    => true,
				],
				[
					'key'      => 'workspace_id',
					'type'     => 'select',
					'label'    => 'Workspace',
					'required' => false,
					'help'     => 'Optional. Leave blank to use the default workspace.',
					'dynamic'  => [
						'integration' => 'typeform',
						'query'       => 'workspace_query',
						'select'      => [ 'value', 'label' ],
					],
				],
			];
		}

		if ( 'delete_form' === $action ) {
			return [
				[
					'key'      => 'form_id',
					'type'     => 'select',
					'label'    => 'Form',
					'required' => true,
					'help'     => 'Select the form to delete. This cannot be undone.',
					'dynamic'  => [
						'integration' => 'typeform',
						'query'       => 'form_query_no_any',
						'select'      => [ 'value', 'label' ],
					],
				],
			];
		}

		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$event       = $node['data']['event'] ?? '';
		$credentials = $node['_connection_credentials'] ?? null;

		if ( ! $credentials ) {
			throw new \Exception( 'No Typeform credentials found on this node.' );
		}

		$token = $credentials['access_token'] ?? '';

		if ( empty( $token ) ) {
			throw new \Exception( 'Typeform access_token is required.' );
		}

		if ( 'create_form' === $event ) {
			return self::action_create_form( $node, $input, $token );
		}

		if ( 'delete_form' === $event ) {
			return self::action_delete_form( $node, $input, $token );
		}

		return [ 'port' => 'main', 'data' => $input ];
	}

	public static function get_dynamic_queries(): array {
		return [
			'form_query'        => [ self::class, 'query_forms' ],
			'form_query_no_any' => [ self::class, 'query_forms_no_any' ],
			'workspace_query'   => [ self::class, 'query_workspaces' ],
		];
	}

	public static function query_forms( array $params ): array {
		$options = [ [ 'label' => 'Any Form', 'value' => 'any' ] ];

		$token = self::extract_token( $params );
		if ( empty( $token ) ) {
			return $options;
		}

		foreach ( self::fetch_forms( $token ) as $form ) {
			$options[] = [
				'label' => $form['title'] ?? 'Untitled',
				'value' => $form['id'] ?? '',
			];
		}

		return $options;
	}

	public static function query_forms_no_any( array $params ): array {
		$options = [];
		$token   = self::extract_token( $params );
		if ( empty( $token ) ) {
			return $options;
		}

		foreach ( self::fetch_forms( $token ) as $form ) {
			$options[] = [
				'label' => $form['title'] ?? 'Untitled',
				'value' => $form['id'] ?? '',
			];
		}

		return $options;
	}

	public static function query_workspaces( array $params ): array {
		$options = [];
		$token   = self::extract_token( $params );
		if ( empty( $token ) ) {
			return $options;
		}

		$response = wp_remote_get(
			self::API_BASE_URL . '/workspaces?page_size=100',
			[
				'headers' => [ 'Authorization' => 'Bearer ' . $token ],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $options;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( $code !== 200 ) {
			return $options;
		}

		foreach ( ( $body['items'] ?? [] ) as $ws ) {
			// Typeform workspace id lives inside self.href: /workspaces/{id}
			$ws_id = $ws['id'] ?? basename( rtrim( $ws['self']['href'] ?? '', '/' ) );

			if ( empty( $ws_id ) ) {
				continue;
			}

			$options[] = [
				'label' => $ws['name'] ?? ( $ws['title'] ?? 'Workspace' ),
				'value' => $ws_id,
			];
		}

		return $options;
	}

	public static function supports_webhook(): bool {
		return true;
	}

	public static function parse_webhook_event( \WP_REST_Request $request ): ?array {
		$payload = $request->get_json_params();

		if ( empty( $payload['form_response'] ) ) {
			return null;
		}

		return [
			'event'   => 'form_submitted',
			'payload' => $payload,
		];
	}

	private static function action_create_form( array $node, array $input, string $token ): array {
		$config       = $node['data']['config'] ?? [];
		$title        = $config['title'] ?? '';
		$workspace_id = $config['workspace_id'] ?? '';

		if ( empty( $title ) ) {
			throw new \Exception( 'Typeform: form title is required.' );
		}

		$payload = [ 'title' => $title ];

		if ( ! empty( $workspace_id ) ) {
			$payload['workspace'] = [
				'href' => self::API_BASE_URL . '/workspaces/' . rawurlencode( $workspace_id ),
			];
		}

		$body    = self::typeform_request( $token, 'POST', '/forms', $payload );
		$form_id = $body['id'] ?? '';

		if ( ! empty( $form_id ) ) {
			self::ensure_webhook( $token, $form_id );
		}

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'typeform_form_id'    => $form_id,
				'typeform_form_title' => $body['title'] ?? '',
				'typeform_form_url'   => $body['_links']['display'] ?? '',
			] ),
		];
	}

	private static function action_delete_form( array $node, array $input, string $token ): array {
		$form_id = $node['data']['config']['form_id'] ?? '';

		if ( empty( $form_id ) ) {
			throw new \Exception( 'Typeform: form_id is required to delete a form.' );
		}

		self::typeform_request( $token, 'DELETE', '/forms/' . rawurlencode( $form_id ) );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'typeform_deleted_form_id' => $form_id,
				'typeform_status'          => 'deleted',
			] ),
		];
	}

	private static function fetch_forms( string $token ): array {
		$response = wp_remote_get(
			self::API_BASE_URL . '/forms?page_size=200',
			[
				'headers' => [ 'Authorization' => 'Bearer ' . $token ],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		return ( $code === 200 ) ? ( $body['items'] ?? [] ) : [];
	}

	private static function extract_token( array $params ): string {
		$paths = [
			[ 'credentials', 'access_token' ],   // ← primary (standard Zaplane shape)
			[ 'access_token' ],                   // ← flat fallback
			[ 'auth', 'access_token' ],           // ← alternate key
			[ 'connection', 'credentials', 'access_token' ],
			[ 'connection', 'access_token' ],
		];

		foreach ( $paths as $path ) {
			$val = $params;
			foreach ( $path as $key ) {
				if ( ! is_array( $val ) || ! array_key_exists( $key, $val ) ) {
					$val = null;
					break;
				}
				$val = $val[ $key ];
			}
			if ( ! empty( $val ) && is_string( $val ) ) {
				return $val;
			}
		}

		return '';
	}

	private static function parse_answers( array $answers ): array {
		$data = [];

		foreach ( $answers as $answer ) {
			$key = $answer['field']['ref'] ?? $answer['field']['id'] ?? null;
			if ( null === $key ) {
				continue;
			}

			$type = $answer['type'] ?? '';

			$data[ $key ] = match ( $type ) {
				'choice'   => $answer['choice']['label'] ?? '',
				'choices'  => $answer['choices']['labels'] ?? [],
				'boolean'  => (bool) ( $answer['boolean'] ?? false ),
				'number'   => $answer['number'] ?? null,
				'date'     => $answer['date'] ?? null,
				'file_url' => $answer['file_url'] ?? null,
				'payment'  => $answer['payment'] ?? null,
				default    => $answer[ $type ] ?? null,
			};
		}

		return $data;
	}

	private static function ensure_webhook( string $token, string $form_id ): void {
		if ( empty( $form_id ) || $form_id === 'any' ) {
			return;
		}

		$tag = 'zaplane_' . sanitize_key( $form_id );

		try {
			self::typeform_request(
				$token,
				'PUT',
				'/forms/' . rawurlencode( $form_id ) . '/webhooks/' . $tag,
				[
					'url'     => home_url( '/wp-json/zaplane/v1/webhook/typeform' ),
					'enabled' => true,
				]
			);
		} catch ( \Exception $e ) {
			// Non-fatal — form was created; just log the webhook failure.
			error_log( '[Zaplane][Typeform] ensure_webhook failed for ' . $form_id . ': ' . $e->getMessage() );
		}
	}

	private static function typeform_request( string $token, string $method, string $endpoint, array $payload = [] ): array {
		$args = [
			'method'  => strtoupper( $method ),
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
		];

		if ( ! empty( $payload ) ) {
			$args['body'] = wp_json_encode( $payload );
		}

		$response = wp_remote_request( self::API_BASE_URL . $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Typeform API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( $code === 204 ) {
			return []; // DELETE success — no body
		}

		if ( $code >= 400 ) {
			$message = $body['description'] ?? $body['message'] ?? ( 'HTTP ' . $code );
			throw new \Exception( 'Typeform API error: ' . esc_html( $message ) );
		}

		return $body;
	}
}