<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Traits\ActionResponseTrait;

class Fillout extends IntegrationBase {

	use ActionResponseTrait;

	private const API_BASE_URL = 'https://api.fillout.com/v1/api';

	private static array $processed_submissions = [];

	public static function get_slug(): string {
		return 'fillout';
	}

	public static function get_name(): string {
		return 'Fillout';
	}

	public static function get_icon(): string {
		return 'fillout.svg';
	}

	public static function requires_connection(): bool {
		return true;
	}

	public static function get_auth_type(): string {
		return 'token_key';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'access_token' => [
				'type'     => 'password',
				'label'    => 'API Key',
				'required' => true,
				'help'     => 'Generate from Fillout → Settings → Developer → API Keys (build.fillout.com/home/settings/developer/api).',
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
			self::API_BASE_URL . '/forms',
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

		if ( 200 !== $code ) {
			return [
				'success' => false,
				'message' => 'Invalid API key (HTTP ' . $code . ').',
				'details' => [],
			];
		}

		return [
			'success' => true,
			'message' => 'Connected successfully to Fillout.',
			'details' => [
				'webhook_url' => self::get_webhook_url(),
			],
		];
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'zaplane_fillout_webhook_form_submitted',
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
						'integration' => 'fillout',
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
					'help'     => 'Copy this URL → Fillout Dashboard → Your Form → Integrate → Webhooks → Add Webhook → Paste & Save.',
				],
			];
		}//end if

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'form_submitted':
				$args_data = $args[0] ?? [];

				$raw = isset( $args_data['payload'] ) ? $args_data['payload'] : $args_data;

				$form_id       = $raw['formId'] ?? '';
				$form_name     = $raw['formName'] ?? '';
				$submission    = $raw['submission'] ?? [];
				$submission_id = $submission['submissionId'] ?? '';

				if ( empty( $form_id ) || empty( $submission ) ) {
					return false;
				}

				if ( empty( $submission_id ) ) {
					return false;
				}

				$dedup_key = $form_id . '|' . $submission_id;

				if ( isset( self::$processed_submissions[ $dedup_key ] ) ) {
					return false;
				}

				self::$processed_submissions[ $dedup_key ] = true;

				$selected = $node['data']['config']['form_id'] ?? 'any';

				if ( ! empty( $selected ) && 'any' !== $selected && $selected !== $form_id ) {
					return false;
				}

				return [
					'fillout_form_id'       => $form_id,
					'fillout_form_name'     => $form_name,
					'fillout_submission_id' => $submission_id,
					'fillout_submitted_at'  => $submission['submissionTime'] ?? '',
					'fillout_last_updated'  => $submission['lastUpdatedAt'] ?? '',
					'fillout_questions'     => self::parse_questions( $submission['questions'] ?? [] ),
					'fillout_calculations'  => $submission['calculations'] ?? [],
					'fillout_url_params'    => $submission['urlParameters'] ?? [],
					'fillout_quiz'          => $submission['quiz'] ?? [],
					'fillout_documents'     => $submission['documents'] ?? [],
					'fillout_scheduling'    => $submission['scheduling'] ?? [],
					'fillout_payments'      => $submission['payments'] ?? [],
				];
		}//end switch

		return false;
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

		if (
			empty( $payload )
			|| empty( $payload['formId'] )
			|| ! isset( $payload['submission'] )
			|| ! is_array( $payload['submission'] )
		) {
			return null;
		}

		if ( empty( $payload['submission']['submissionId'] ) ) {
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
		$token = $creds['access_token'] ?? '';

		if ( empty( $token ) ) {
			return $options;
		}

		$options[] = [
			'label' => 'Any Form',
			'value' => 'any',
		];

		foreach ( self::fetch_forms( $token ) as $form ) {
			$form_id = $form['formId'] ?? '';

			if ( empty( $form_id ) ) {
				continue;
			}

			$options[] = [
				'label' => $form['name'] ?? 'Untitled',
				'value' => $form_id,
			];
		}

		return $options;
	}

	public static function register_webhooks_for_all_forms(): array {
		$results = [];

		$connection_id = self::get_fillout_connection_id();
		$creds         = self::get_decrypted_credentials( $connection_id );
		$token         = $creds['access_token'] ?? '';

		if ( empty( $token ) ) {
			return [ 'error' => 'No active Fillout connection found.' ];
		}

		$forms = self::fetch_forms( $token );

		if ( empty( $forms ) ) {
			return [ 'error' => 'No forms found in Fillout account.' ];
		}

		foreach ( $forms as $form ) {
			$form_id = $form['formId'] ?? '';

			if ( empty( $form_id ) ) {
				continue;
			}

			try {
				self::ensure_webhook( $token, $form_id );

				$results[] = [
					'form_id' => $form_id,
					'title'   => $form['name'] ?? 'Untitled',
					'status'  => self::is_local_environment() ? 'skipped_local' : 'webhook_registered',
				];
			} catch ( \Exception $e ) {
				$results[] = [
					'form_id' => $form_id,
					'title'   => $form['name'] ?? 'Untitled',
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

		try {
			self::fillout_request(
				$token,
				'POST',
				'/webhook/create',
				[
					'formId' => $form_id,
					'url'    => self::get_webhook_url(),
				]
			);
		} catch ( \Exception $error ) {
			unset( $error );
		}
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
				$connection_id = self::get_fillout_connection_id();
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

	private static function get_fillout_connection_id(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'zaplane_connections';
		$id    = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
				'SELECT id FROM `' . esc_sql( $table ) . "` WHERE app = %s AND status = 'active' ORDER BY id DESC LIMIT 1",
				'fillout'
			)
		);

		return (int) $id;
	}

	private static function fetch_forms( string $token ): array {
		$response = wp_remote_get(
			self::API_BASE_URL . '/forms',
			[
				'headers' => [ 'Authorization' => 'Bearer ' . $token ],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $body ) ) {
			return [];
		}

		return $body;
	}

	private static function parse_questions( array $questions ): array {
		$data = [];

		foreach ( $questions as $question ) {
			$key = $question['id'] ?? null;

			if ( null === $key ) {
				continue;
			}

			$data[ $key ] = [
				'label' => $question['name'] ?? '',
				'type'  => $question['type'] ?? '',
				'value' => $question['value'] ?? null,
			];
		}

		return $data;
	}

	private static function fillout_request( string $token, string $method, string $endpoint, array $payload = [] ): array {
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
			throw new \Exception( 'Fillout API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( 204 === $code ) {
			return [];
		}

		if ( $code >= 400 ) {
			$message = $body['message'] ?? $body['error'] ?? ( 'HTTP ' . $code );
			throw new \Exception( 'Fillout API error: ' . esc_html( $message ) );
		}

		return $body;
	}
}
