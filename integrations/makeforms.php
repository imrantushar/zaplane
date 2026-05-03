<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Traits\ActionResponseTrait;

class Makeforms extends IntegrationBase {

	use ActionResponseTrait;

	public static function get_slug(): string {
		return 'makeforms';
	}

	public static function get_name(): string {
		return 'MakeForms';
	}

	public static function get_icon(): string {
		return 'makeforms.svg';
	}

	public static function requires_connection(): bool {
		return true;
	}

	public static function get_auth_type(): string {
		return 'token_key';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'access_api' => [
				'type'     => 'password',
				'label'    => 'API Key',
				'required' => true,
				'help'     => 'MakeForms → Workspace Settings → API Access থেকে key copy করুন।',
			],
		];
	}

	public static function test_connection( array $credentials ): array {
		$token = $credentials['access_api'] ?? '';

		if ( empty( $token ) ) {
			return [
				'success' => false,
				'message' => 'API Key is required.',
				'details' => [],
			];
		}

		$workspace_id = self::resolve_workspace_id( $token );

		if ( empty( $workspace_id ) ) {
			return [
				'success' => false,
				'message' => 'Could not resolve workspace. Check your API Key.',
				'details' => [],
			];
		}

		$response = wp_remote_get(
			'/folder/all?page=1&limit=1&workspace=' . rawurlencode( $workspace_id ) . '&isActive=true',
			[
				'headers' => [
					'token'  => $token,
					'accept' => 'application/json',
				],
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

		if ( 401 === $code || 403 === $code ) {
			return [
				'success' => false,
				'message' => 'Invalid API Key (HTTP ' . $code . ').',
				'details' => [],
			];
		}

		if ( 200 !== $code ) {
			$error_msg = $body['message'] ?? $body['error'] ?? '';
			return [
				'success' => false,
				'message' => 'Connection failed (HTTP ' . $code . ')' . ( $error_msg ? ': ' . $error_msg : '.' ),
				'details' => [],
			];
		}

		$total = $body['data']['total'] ?? $body['total'] ?? 0;

		return [
			'success' => true,
			'message' => 'Connected successfully. ' . $total . ' form(s) found.',
			'details' => [
				'workspace_id' => $workspace_id,
				'form_count'   => $total,
				'webhook_url'  => self::get_webhook_url(),
			],
		];
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'zaplane_makeforms_webhook_form_submitted',
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
						'integration' => 'makeforms',
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
					'help'     => 'Copy this URL → MakeForms Dashboard → Your Form → Connect → Webhooks → Add a webhook → Paste & Save.',
				],
			];
		}

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
				} elseif ( isset( $payload['data'] ) && is_array( $payload['data'] ) ) {
					$form_response = $payload['data'];
				} else {
					$form_response = [];
				}

				if ( empty( $form_response ) ) {
					return false;
				}

				$form_id = $form_response['form_id'] ?? $form_response['formId'] ?? $form_response['id'] ?? '';

				if ( empty( $form_id ) ) {
					return false;
				}

				$selected = $node['data']['config']['form_id'] ?? 'any';

				if ( ! empty( $selected ) && 'any' !== $selected && $selected !== $form_id ) {
					return false;
				}

				$definition = $form_response['definition'] ?? [];

				return [
					'makeforms_form_id'      => $form_id,
					'makeforms_form_title'   => $definition['title'] ?? $form_response['title'] ?? $form_response['form_title'] ?? '',
					'makeforms_entry_token'  => $form_response['token'] ?? $form_response['response_id'] ?? '',
					'makeforms_landed_at'    => $form_response['landed_at'] ?? '',
					'makeforms_submitted_at' => $form_response['submitted_at'] ?? '',
					'makeforms_answers'      => self::parse_answers( $form_response['answers'] ?? [] ),
					'makeforms_variables'    => $form_response['variables'] ?? [],
					'makeforms_hidden'       => $form_response['hidden'] ?? [],
				];
		}

		return false;
	}

	public static function get_dynamic_queries(): array {
		return [
			'form_query' => [ self::class, 'query_form' ],
		];
	}

	public static function get_dynamic_fields(): array {
		return self::get_dynamic_queries();
	}

	public static function query_form( array $query ): array {
		$options = [];

		$creds = self::extract_credentials( $query );
		$token = $creds['access_api'] ?? '';

		if ( empty( $token ) ) {
			return $options;
		}

		$workspace_id = self::resolve_workspace_id( $token );

		if ( empty( $workspace_id ) ) {
			return $options;
		}

		$options[] = [
			'label' => 'Any Form',
			'value' => 'any',
		];

		foreach ( self::fetch_forms( $token, $workspace_id ) as $form ) {
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

	public static function supports_webhook(): bool {
		return true;
	}

	public static function get_webhook_url(): string {
		$url = rest_url( 'zaplane/v1/incoming/' . self::get_slug() );
		return set_url_scheme( $url, 'https' );
	}

	public static function parse_webhook_event( \WP_REST_Request $request ): ?array {
		$payload = $request->get_json_params();

		if ( empty( $payload ) ) {
			$body = $request->get_body();
			if ( ! empty( $body ) ) {
				$payload = json_decode( $body, true );
			}
		}

		if ( empty( $payload ) || ! is_array( $payload ) ) {
			return null;
		}

		if ( ! empty( $payload['form_response'] ) ) {
			return [
				'event'   => 'form_submitted',
				'payload' => $payload,
			];
		}

		if ( ! empty( $payload['event_type'] ) && ! empty( $payload['data'] ) ) {
			return [
				'event'   => 'form_submitted',
				'payload' => [ 'form_response' => $payload['data'] ],
			];
		}

		if ( ! empty( $payload['form_id'] ) || ! empty( $payload['formId'] ) ) {
			return [
				'event'   => 'form_submitted',
				'payload' => [ 'form_response' => $payload ],
			];
		}

		return null;
	}

	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		return true;
	}

	public static function register_webhooks_for_all_forms(): array {
		$results = [];

		$connection_id = self::get_makeforms_connection_id();
		$creds         = self::get_decrypted_credentials( $connection_id );
		$token         = $creds['access_api'] ?? '';

		if ( empty( $token ) ) {
			return [ 'error' => 'No active MakeForms connection found.' ];
		}

		$workspace_id = self::resolve_workspace_id( $token );
		$forms        = self::fetch_forms( $token, $workspace_id );

		if ( empty( $forms ) ) {
			return [ 'error' => 'No forms found in MakeForms account.' ];
		}

		foreach ( $forms as $form ) {
			$form_id = $form['id'] ?? '';

			if ( empty( $form_id ) ) {
				continue;
			}

			$results[] = [
				'form_id' => $form_id,
				'title'   => $form['title'] ?? 'Untitled',
				'status'  => 'webhook_must_be_set_manually',
			];
		}

		return $results;
	}

	private static function resolve_workspace_id( string $token ): string {
		$parts = explode( '.', $token );

		if ( 3 === count( $parts ) ) {
			$payload = json_decode(
				base64_decode(
					str_pad(
						strtr( $parts[1], '-_', '+/' ),
						strlen( $parts[1] ) % 4,
						'=',
						STR_PAD_RIGHT
					)
				),
				true
			);

			$uid = $payload['user_id'] ?? $payload['sub'] ?? '';

			if ( ! empty( $uid ) ) {
				return $uid;
			}
		}

		$response = wp_remote_get(
			'/workspace/info',
			[
				'headers' => [
					'token'  => $token,
					'accept' => 'application/json',
				],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( 200 === $code ) {
			return $body['data']['id'] ?? $body['data']['workspace_id'] ?? $body['id'] ?? '';
		}

		return '';
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
				$connection_id = self::get_makeforms_connection_id();
			}

			if ( $connection_id <= 0 ) {
				return [];
			}

			$creds = $cm->get_execution_credentials( $connection_id );

			if ( is_array( $creds ) && ! empty( $creds['access_api'] ) ) {
				return $creds;
			}

			if ( is_object( $creds ) ) {
				$creds = (array) $creds;
				if ( ! empty( $creds['access_api'] ) ) {
					return $creds;
				}
			}
		} catch ( \Throwable $error ) {
			unset( $error );
		}

		return [];
	}

	private static function get_makeforms_connection_id(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'zaplane_connections';
		$id    = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholders
				'SELECT id FROM `' . esc_sql( $table ) . "` WHERE app = %s AND status = 'active' ORDER BY id DESC LIMIT 1",
				'makeforms'
			)
		);
		return (int) $id;
	}

	private static function fetch_forms( string $token, string $workspace_id ): array {
		if ( empty( $workspace_id ) ) {
			return [];
		}

		$response = wp_remote_get(
			'/folder/all?page=1&limit=200&workspace=' . rawurlencode( $workspace_id ) . '&isActive=true',
			[
				'headers' => [
					'token'  => $token,
					'accept' => 'application/json',
				],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( 200 !== $code ) {
			return [];
		}

		return $body['data']['list'] ?? $body['data'] ?? $body['items'] ?? [];
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
					$data[ $key ] = $answer['choice']['label'] ?? $answer['choice']['other'] ?? '';
					break;

				case 'choices':
					$labels       = $answer['choices']['labels'] ?? [];
					$other        = $answer['choices']['other'] ?? null;
					if ( $other ) {
						$labels[] = $other;
					}
					$data[ $key ] = $labels;
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

	private static function makeforms_request( string $token, string $method, string $endpoint, array $payload = [] ): array {
		$args = [
			'method'  => strtoupper( $method ),
			'timeout' => 20,
			'headers' => [
				'token'        => $token,
				'Content-Type' => 'application/json',
				'accept'       => 'application/json',
			],
		];

		if ( ! empty( $payload ) ) {
			$args['body'] = wp_json_encode( $payload );
		}

		$response = wp_remote_request( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'MakeForms API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( 204 === $code ) {
			return [];
		}

		if ( $code >= 200 && $code < 300 ) {
			return is_array( $body ) ? $body : [];
		}

		$message = $body['message'] ?? $body['error'] ?? $body['description'] ?? ( 'HTTP ' . $code );
		throw new \Exception( 'MakeForms API error: ' . esc_html( $message ) );
	}
}
