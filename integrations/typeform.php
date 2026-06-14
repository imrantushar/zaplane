<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Traits\ActionResponseTrait;

class Typeform extends IntegrationBase {

	use ActionResponseTrait;

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
		return 'token_key';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'access_token'     => [
				'type'     => 'password',
				'label'    => 'Personal Access Token',
				'required' => true,
				'help'     => 'Generate from Typeform → Account → Personal tokens (admin.typeform.com/user/tokens).',
			],
		];
	}

	public static function test_connection( array $credentials ): array {
		$token = $credentials['access_token'] ?? '';

		if ( empty( $token ) ) {
			return [
				'success' => false,
				'message' => 'access_token is required.',
				'details' => [],
			];
		}

		$response = wp_remote_get(
			self::API_BASE_URL . '/me',
			[
				'headers' => [ 'Authorization' => 'Bearer ' . $token ],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => 'Connection failed: ' . $response->get_error_message(),
				'details' => [],
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( 200 !== $code || empty( $body['email'] ) ) {
			return [
				'success' => false,
				'message' => 'Invalid token (HTTP ' . $code . ').',
				'details' => [],
			];
		}

		return [
			'success' => true,
			'message' => 'Connected as: ' . $body['email'],
			'details' => [
				'email'       => $body['email'],
				'alias'       => $body['alias'] ?? '',
				'webhook_url' => self::get_webhook_url(),
			],
		];
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'zaplane_typeform_webhook_form_submitted',
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
					'dynamic'  => [
						'integration' => 'typeform',
						'query'       => 'form_query',
						'select'      => [ 'value', 'label' ],
					],
				],
				[
					'key'      => 'webhook_endpoint',
					'label'    => 'Webhook Endpoint URL',
					'type'     => 'copy',
					'value'    => self::get_webhook_url(),
					'readonly' => true,
					'help'     => 'Copy this URL → Typeform Dashboard → Your Form → Connect → Webhooks → Add a webhook → Paste & Save.',
				],
			];
		}//end if

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'form_submitted':
				$payload = $args[0] ?? [];

				if ( isset( $payload['payload']['form_response'] ) ) {
					$form_response = $payload['payload']['form_response'];
				} elseif ( isset( $payload['form_response'] ) ) {
					$form_response = $payload['form_response'];
				} else {
					$form_response = [];
				}

				if ( empty( $form_response ) ) {
					return false;
				}

				$form_id = $form_response['form_id'] ?? '';

				if ( empty( $form_id ) ) {
					return false;
				}

				$selected = $node['data']['config']['form_id'] ?? 'any';

				if ( ! empty( $selected ) && 'any' !== $selected && $selected !== $form_id ) {
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
		}//end switch

		return false;
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
					'key'         => 'form_title',
					'type'        => 'text',
					'label'       => 'Form Title',
					'placeholder' => 'My New Form',
					'required'    => true,
				],
				[
					'key'      => 'workspace_id',
					'type'     => 'select',
					'label'    => 'Workspace',
					'required' => true,
					'dynamic'  => [
						'integration' => 'typeform',
						'query'       => 'workspace_query',
						'select'      => [ 'value', 'label' ],
					],
				],
			];
		}//end if

		if ( 'delete_form' === $action ) {
			return [
				[
					'key'      => 'form_id',
					'type'     => 'select',
					'label'    => 'Form',
					'required' => true,
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

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];
		$event  = $node['data']['event'] ?? '';

		$creds = self::get_connection_credentials( $node );
		$token = $creds['access_token'] ?? '';

		if ( empty( $token ) ) {
			return self::error( __( 'Typeform access_token is required.', 'zaplane' ), $input );
		}

		switch ( $event ) {

			case 'create_form':
				$title        = $config['form_title'] ?? '';
				$workspace_id = $config['workspace_id'] ?? '';

				if ( empty( $title ) ) {
					return self::error( __( 'Typeform: form title is required.', 'zaplane' ), $input );
				}

				$payload = [ 'title' => $title ];

				if ( ! empty( $workspace_id ) ) {
					$payload['workspace'] = [
						'href' => self::API_BASE_URL . '/workspaces/' . rawurlencode( $workspace_id ),
					];
				}

				try {
					$body    = self::typeform_request( $token, 'POST', '/forms', $payload );
					$form_id = $body['id'] ?? '';

					if ( ! empty( $form_id ) ) {
						self::ensure_webhook( $token, $form_id );
					}

					return self::success( array_merge( $input, [
						'typeform_form_id'    => $form_id,
						'typeform_form_title' => $body['title'] ?? '',
						'typeform_form_url'   => $body['_links']['display'] ?? '',
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}

			case 'delete_form':
				$form_id = $config['form_id'] ?? '';

				if ( empty( $form_id ) ) {
					return self::error( __( 'Typeform: form_id is required to delete a form.', 'zaplane' ), $input );
				}

				try {
					self::typeform_request( $token, 'DELETE', '/forms/' . rawurlencode( $form_id ) );

					return self::success( array_merge( $input, [
						'typeform_deleted_form_id' => $form_id,
						'typeform_status'          => 'deleted',
					] ) );
				} catch ( \Exception $error ) {
					return self::error( $error->getMessage(), $input );
				}
		}//end switch

		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'form_query'      => [ self::class, 'query_form' ],
			'workspace_query' => [ self::class, 'query_workspace' ],
		];
	}

	public static function get_dynamic_fields(): array {
		return self::get_dynamic_queries();
	}

	public static function query_form( array $query ): array {
		$options = [];

		$creds = self::extract_credentials( $query );
		$token = $creds['access_token'] ?? '';

		if ( empty( $token ) ) {
			return $options;
		}

		$options[] = [
			'label' => 'Any Form',
			'value' => 'any',
		];

		foreach ( self::fetch_forms( $token ) as $form ) {
			$form_id = $form['id'] ?? '';

			if ( empty( $form_id ) ) {
				continue;
			}

			$options[] = [
				'label' => $form['title'] ?? 'Untitled',
				'value' => $form_id,
			];
		}

		return $options;
	}

	public static function query_workspace( array $query ): array {
		$options = [];

		$creds = self::extract_credentials( $query );
		$token = $creds['access_token'] ?? '';

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

		if ( 200 !== $code ) {
			return $options;
		}

		foreach ( ( $body['items'] ?? [] ) as $ws ) {
			$ws_id = '';

			if ( ! empty( $ws['id'] ) ) {
				$ws_id = $ws['id'];
			} elseif ( ! empty( $ws['self']['href'] ) ) {
				$ws_id = basename( rtrim( $ws['self']['href'], '/' ) );
			}

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

		if ( empty( $payload ) ) {
			$body = $request->get_body();
			if ( ! empty( $body ) ) {
				$payload = json_decode( $body, true );
			}
		}

		if ( empty( $payload ) || empty( $payload['form_response'] ) ) {
			return null;
		}

		return [
			'event'   => 'form_submitted',
			'payload' => $payload,
		];
	}

	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		return true;
	}

	public static function register_webhooks_for_all_forms(): array {
		$results = [];

		$connection_id = self::get_typeform_connection_id();
		$creds         = self::get_decrypted_credentials( $connection_id );
		$token         = $creds['access_token'] ?? '';

		if ( empty( $token ) ) {
			return [ 'error' => 'No active Typeform connection found.' ];
		}

		$forms = self::fetch_forms( $token );

		if ( empty( $forms ) ) {
			return [ 'error' => 'No forms found in Typeform account.' ];
		}

		foreach ( $forms as $form ) {
			$form_id = $form['id'] ?? '';

			if ( empty( $form_id ) ) {
				continue;
			}

			try {
				self::ensure_webhook( $token, $form_id );

				$results[] = [
					'form_id' => $form_id,
					'title'   => $form['title'] ?? 'Untitled',
					'status'  => self::is_local_environment() ? 'skipped_local' : 'webhook_registered',
				];
			} catch ( \Exception $e ) {
				$results[] = [
					'form_id' => $form_id,
					'title'   => $form['title'] ?? 'Untitled',
					'status'  => 'failed: ' . $e->getMessage(),
				];
			}
		}//end foreach

		return $results;
	}

	private static function is_local_environment(): bool {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return (
			'localhost' === $host
			|| '127.0.0.1' === $host
			|| self::string_ends_with( (string) $host, '.local' )
			|| self::string_ends_with( (string) $host, '.test' )
			|| self::string_ends_with( (string) $host, '.localhost' )
		);
	}

	private static function string_ends_with( string $haystack, string $needle ): bool {
		if ( '' === $needle ) {
			return true;
		}

		return substr( $haystack, -strlen( $needle ) ) === $needle;
	}

	private static function ensure_webhook( string $token, string $form_id ): void {
		if ( empty( $form_id ) || 'any' === $form_id ) {
			return;
		}

		if ( self::is_local_environment() ) {
			return;
		}

		$tag = 'zaplane_' . sanitize_key( $form_id );

		try {
			self::typeform_request(
				$token,
				'PUT',
				'/forms/' . rawurlencode( $form_id ) . '/webhooks/' . $tag,
				[
					'url'     => self::get_webhook_url(),
					'enabled' => true,
				]
			);
		} catch ( \Exception $error ) {
			unset( $error );
		}
	}

	private static function get_connection_credentials( array $node ): array {
		$connection_id = (int) (
			$node['data']['connection_id']
			?? $node['connection_id']
			?? 0
		);

		return self::get_decrypted_credentials( $connection_id );
	}

	private static function extract_credentials( array $params ): array {
		$connection_id = $params['where']['connection_id']
			?? $params['connection_id']
			?? 0;

		return self::get_decrypted_credentials( (int) $connection_id );
	}

	private static function get_decrypted_credentials( int $connection_id = 0 ): array {
		try {
			$cm = new ConnectionManager();

			if ( $connection_id <= 0 ) {
				$connection_id = self::get_typeform_connection_id();
			}

			if ( $connection_id <= 0 ) {
				return [];
			}

			$creds = $cm->get_execution_credentials( $connection_id );

			if ( is_array( $creds ) && ! empty( $creds['access_token'] ) ) {
				return $creds;
			}

			if ( is_object( $creds ) ) {
				$creds = (array) $creds;
				if ( ! empty( $creds['access_token'] ) ) {
					return $creds;
				}
			}
		} catch ( \Throwable $error ) {
			unset( $error );
		}//end try

		return [];
	}

	private static function get_typeform_connection_id(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'zaplane_connections';
		$id    = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
				'SELECT id FROM `' . esc_sql( $table ) . "` WHERE app = %s AND status = 'active' ORDER BY id DESC LIMIT 1",
				'typeform'
			)
		);
		return (int) $id;
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

		return ( 200 === $code ) ? ( $body['items'] ?? [] ) : [];
	}

	private static function parse_answers( array $answers ): array {
		$data = [];

		foreach ( $answers as $answer ) {
			$key = $answer['field']['ref'] ?? $answer['field']['id'] ?? null;

			if ( null === $key ) {
				continue;
			}

			$type = $answer['type'] ?? '';

			switch ( $type ) {
				case 'choice':
					$data[ $key ] = $answer['choice']['label'] ?? '';
					break;

				case 'choices':
					$data[ $key ] = $answer['choices']['labels'] ?? [];
					break;

				case 'boolean':
					$data[ $key ] = (bool) ( $answer['boolean'] ?? false );
					break;

				case 'number':
					$data[ $key ] = $answer['number'] ?? null;
					break;

				case 'date':
					$data[ $key ] = $answer['date'] ?? null;
					break;

				case 'file_url':
					$data[ $key ] = $answer['file_url'] ?? null;
					break;

				case 'payment':
					$data[ $key ] = $answer['payment'] ?? null;
					break;

				default:
					$data[ $key ] = $answer[ $type ] ?? null;
					break;
			}
		}

		return $data;
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

		if ( 204 === $code ) {
			return [];
		}

		if ( $code >= 400 ) {
			$message = $body['description'] ?? $body['message'] ?? ( 'HTTP ' . $code );
			throw new \Exception( 'Typeform API error: ' . esc_html( $message ) );
		}

		return $body;
	}
}
