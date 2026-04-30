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
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

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
					'help'     => 'Copy this URL → Fillout Dashboard → Your Form → Integrations → Webhooks → Add Webhook → Paste & Save.',
				],
			];
		}

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'form_submitted':
				$payload = $args[0] ?? [];

				if ( isset( $payload['payload'] ) ) {
					$submission = $payload['payload'];
				} else {
					$submission = $payload;
				}

				if ( empty( $submission ) ) {
					return false;
				}

				$form_id = $submission['formId'] ?? '';

				if ( empty( $form_id ) ) {
					return false;
				}

				$selected = $node['data']['config']['form_id'] ?? 'any';

				if ( ! empty( $selected ) && 'any' !== $selected && $selected !== $form_id ) {
					return false;
				}

				return [
					'fillout_form_id'       => $form_id,
					'fillout_form_name'     => $submission['formName'] ?? '',
					'fillout_submission_id' => $submission['submissionId'] ?? '',
					'fillout_submitted_at'  => $submission['submissionTime'] ?? '',
					'fillout_answers'       => self::parse_answers( $submission['questions'] ?? [] ),
					'fillout_url_params'    => $submission['urlParameters'] ?? [],
					'fillout_quiz'          => $submission['quiz'] ?? [],
					'fillout_calc'          => $submission['calculations'] ?? [],
				];
		}

		return false;
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

		if ( empty( $payload ) || empty( $payload['formId'] ) ) {
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

			$results[] = [
				'form_id' => $form_id,
				'title'   => $form['name'] ?? 'Untitled',
				'status'  => 'webhook_registered',
			];
		}

		return $results;
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
		}

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
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		return ( 200 === $code ) ? ( $body ?? [] ) : [];
	}

	private static function parse_answers( array $questions ): array {
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
}