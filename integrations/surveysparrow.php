<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Traits\ActionResponseTrait;

class Surveysparrow extends IntegrationBase {

	use ActionResponseTrait;

	private const API_BASE_URL = 'https://api.surveysparrow.com';
	private const API_VERSION  = '/v3';

	public static function get_slug(): string {
		return 'surveysparrow';
	}

	public static function get_name(): string {
		return 'SurveySparrow';
	}

	public static function get_icon(): string {
		return 'surveysparrow.svg';
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
				'label'    => 'Personal Access Token',
				'required' => true,
				'help'     => 'Go to SurveySparrow → Settings → Apps & Integrations → Create a Custom App → Copy the Access Token.',
			],
		];
	}

	public static function test_connection( array $credentials ): array {
		$token = trim( $credentials['access_token'] ?? '' );

		if ( empty( $token ) ) {
			return [
				'success' => false,
				'message' => 'Access token is required.',
				'details' => [],
			];
		}

		$response = wp_remote_get(
			self::API_BASE_URL . self::API_VERSION . '/surveys?limit=1',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
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

		if ( 200 !== $code || ! array_key_exists( 'data', $body ) ) {
			$api_msg = $body['message'] ?? $body['error'] ?? '';
			return [
				'success' => false,
				'message' => sprintf( 'Invalid token (HTTP %d). %s', $code, $api_msg ),
				'details' => [],
			];
		}

		return [
			'success' => true,
			'message' => 'Connected to SurveySparrow successfully.',
			'details' => [
				'webhook_url' => self::get_webhook_url(),
			],
		];
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'zaplane_surveysparrow_webhook_form_submitted',
			],
		];
	}

    public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'form_submitted' === $trigger ) {
			return [
				[
                    'key'      => 'survey_id',
                    'label'    => 'Survey',
                    'type'     => 'select',
                    'required' => true,
                    'dynamic'  => [
                        'integration' => 'surveysparrow',
                        'query'       => 'survey_query',
                        'select'      => [ 'value', 'label' ],
                    ],
                ],
				[
                    'key'      => 'webhook_endpoint',
                    'label'    => 'Webhook Endpoint URL',
                    'type'     => 'copy',
                    'value'    => self::get_webhook_url(),
                    'readonly' => true,
                    'help'     => 'Copy this URL → open your Survey in SurveySparrow → Build → Integrations → Webhook → New Webhook → paste the URL → Save.',
                ],
			];
		}//end if

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'form_submitted':

                $payload    = $args[0] ?? [];
                $submission = $payload['submission'] ?? [];
                $survey     = $payload['survey']     ?? [];
                if ( empty( $submission ) || empty( $survey ) ) {
                    return false;
                }

                $survey_id = (int) ( $survey['id'] ?? 0 );
                if ( $survey_id <= 0 ) {
                    return false;
                }

                $selected_raw = $node['data']['config']['survey_id'] ?? 'any';
                $selected     = (string) $selected_raw;

                if ( '' !== $selected && 'any' !== $selected && (int) $selected !== $survey_id ) {
                    return false;
                }

                return [
                    'ss_survey_id'     => $survey_id,
                    'ss_survey_name'   => sanitize_text_field( $survey['name'] ?? '' ),
                    'ss_submission_id' => $submission['id'] ?? '',
                    'ss_submitted_at'  => $submission['submitted_at'] ?? '',
                    'ss_contact'       => $submission['contact'] ?? [],
                    'ss_answers'       => self::parse_answers( $submission['answers'] ?? [] ),
                    'ss_variables'     => $submission['variables'] ?? [],
                ];
        }//end switch

		return false;
	}
	public static function get_dynamic_queries(): array {
		return [
			'survey_query' => [ self::class, 'query_survey' ],
		];
	}

	public static function get_dynamic_fields(): array {
		return self::get_dynamic_queries();
	}

	public static function query_survey( array $query ): array {
		$creds = self::extract_credentials( $query );
		$token = trim( $creds['access_token'] ?? '' );

		if ( empty( $token ) ) {
			return [];
		}

		$options = [
			[
				'label' => 'Any Survey',
				'value' => 'any',
			],
		];

		foreach ( self::fetch_surveys( $token ) as $survey ) {
			$id = $survey['id'] ?? '';

			if ( empty( $id ) ) {
				continue;
			}

			$options[] = [
				'label' => sanitize_text_field( $survey['name'] ?? 'Untitled' ),
				'value' => $id,
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
			$raw = $request->get_body();
			if ( ! empty( $raw ) ) {
				$payload = json_decode( $raw, true );
			}
		}

		if ( empty( $payload ) || ! is_array( $payload ) ) {
			return null;
		}

		if ( isset( $payload['data'] ) && is_array( $payload['data'] ) ) {
			$payload = $payload['data'];
		}

		if ( empty( $payload['submission'] ) || empty( $payload['survey'] ) ) {
			return null;
		}

		return [
			'event'   => 'form_submitted',
			'payload' => $payload,
		];
	}

	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		$signature = $request->get_header( 'x-surveysparrow-signature' );
		
		if ( empty( $signature ) ) {
			$signature = $request->get_header( 'x-appnest-signature' );
		}

		if ( empty( $signature ) ) {
			return true;
		}

		$secret = self::get_webhook_secret();

		if ( empty( $secret ) ) {
			return true;
		}

		$raw_body = $request->get_body();
		$expected = hash( 'sha384', $secret . $raw_body );

		return hash_equals( $expected, $signature );
	}

	private static function ensure_webhook( string $token, int $survey_id ): void {
		if ( $survey_id <= 0 ) {
			return;
		}

		if ( self::is_local_environment() ) {
			return;
		}

		self::ss_request(
			$token,
			'POST',
			'/webhooks',
			[
				'name'        => 'zaplane_survey_' . $survey_id,
				'url'         => self::get_webhook_url(),
				'survey_id'   => $survey_id,
				'event_type'  => 'submission_completed',
				'object_type' => 'survey',
				'http_method' => 'POST',
			]
		);
	}

	public static function register_webhooks_for_all_surveys(): array {
		$connection_id = self::get_ss_connection_id();
		$creds         = self::get_decrypted_credentials( $connection_id );
		$token         = trim( $creds['access_token'] ?? '' );

		if ( empty( $token ) ) {
			return [ 'error' => 'No active SurveySparrow connection found.' ];
		}

		$surveys = self::fetch_surveys( $token );

		if ( empty( $surveys ) ) {
			return [ 'error' => 'No surveys found in SurveySparrow account.' ];
		}

		$results = [];

		foreach ( $surveys as $survey ) {
			$survey_id = (int) ( $survey['id'] ?? 0 );

			if ( $survey_id <= 0 ) {
				continue;
			}

			$name = sanitize_text_field( $survey['name'] ?? 'Untitled' );

			try {
				self::ensure_webhook( $token, $survey_id );
				$results[] = [
					'survey_id' => $survey_id,
					'name'      => $name,
					'status'    => self::is_local_environment() ? 'skipped_local' : 'webhook_registered',
				];
			} catch ( \Exception $e ) {
				$results[] = [
					'survey_id' => $survey_id,
					'name'      => $name,
					'status'    => 'failed: ' . $e->getMessage(),
				];
			}
		}

		return $results;
	}

	private static function is_local_environment(): bool {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		$local_suffixes = [ '.local', '.test', '.localhost', '.ngrok-free.app', '.ngrok.io' ];

		if ( in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true ) ) {
			return true;
		}

		foreach ( $local_suffixes as $suffix ) {
			if ( self::str_ends_with( $host, $suffix ) ) {
				return true;
			}
		}

		return false;
	}

	private static function str_ends_with( string $haystack, string $needle ): bool {
		if ( '' === $needle ) {
			return true;
		}
		return substr( $haystack, -strlen( $needle ) ) === $needle;
	}

	private static function extract_credentials( array $params ): array {
		$connection_id = (int) (
			$params['where']['connection_id']
			?? $params['connection_id']
			?? 0
		);

		return self::get_decrypted_credentials( $connection_id );
	}

	private static function get_decrypted_credentials( int $connection_id = 0 ): array {
		try {
			$cm = new ConnectionManager();

			if ( $connection_id <= 0 ) {
				$connection_id = self::get_ss_connection_id();
			}

			if ( $connection_id <= 0 ) {
				return [];
			}

			$creds = $cm->get_execution_credentials( $connection_id );

			if ( is_object( $creds ) ) {
				$creds = (array) $creds;
			}

			if ( is_array( $creds ) && ! empty( $creds['access_token'] ) ) {
				return $creds;
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		return [];
	}

	private static function get_ss_connection_id(): int {
		global $wpdb;

		$table = $wpdb->prefix . 'zaplane_connections';

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
		$id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM `' . esc_sql( $table ) . "` WHERE app = %s AND status = 'active' ORDER BY id DESC LIMIT 1",
				self::get_slug()
			)
		);

		return (int) $id;
	}

	private static function fetch_surveys( string $token ): array {
		$all   = [];
		$page  = 1;
		$limit = 100;

		do {
			$url = add_query_arg(
				[
					'limit' => $limit,
					'page'  => $page,
				],
				self::API_BASE_URL . self::API_VERSION . '/surveys'
			);

			$response = wp_remote_get(
				$url,
				[
					'headers' => [
						'Authorization' => 'Bearer ' . $token,
						'Accept'        => 'application/json',
					],
					'timeout' => 15,
				]
			);

			if ( is_wp_error( $response ) ) {
				break;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

			if ( 200 !== $code ) {
				break;
			}

			$page_data = $body['data'] ?? [];
			if ( empty( $page_data ) ) {
				break;
			}

			$all   = array_merge( $all, $page_data );
			$page++;

		} while ( ! empty( $body['has_next_page'] ) );

		return $all;
	}

	private static function parse_answers( array $answers ): array {
		$data = [];

		foreach ( $answers as $answer ) {
			$key = $answer['question_id'] ?? null;

			if ( null === $key || '' === (string) $key ) {
				continue;
			}

			$data[ $key ] = [
				'question' => $answer['question'] ?? '',
				'answer'   => $answer['answer']   ?? null,
				'type'     => $answer['type']      ?? '',
			];
		}

		return $data;
	}

	private static function ss_request( string $token, string $method, string $endpoint, array $payload = [] ): array {
		$args = [
			'method'  => strtoupper( $method ),
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			],
		];

		if ( ! empty( $payload ) ) {
			$args['body'] = wp_json_encode( $payload );
		}

		$url      = self::API_BASE_URL . self::API_VERSION . $endpoint;
		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception(
				'SurveySparrow API request failed: ' . esc_html( $response->get_error_message() )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( 204 === $code ) {
			return [];
		}

		if ( $code >= 400 ) {
			$message = $body['message'] ?? $body['error'] ?? ( 'HTTP ' . $code );
			throw new \Exception(
				'SurveySparrow API error: ' . esc_html( $message )
			);
		}

		return $body;
	}

	private static function get_webhook_secret(): string {
		$connection_id = self::get_ss_connection_id();
		$creds         = self::get_decrypted_credentials( $connection_id );
		return trim( $creds['webhook_secret'] ?? '' );
	}
}