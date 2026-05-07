<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Traits\ActionResponseTrait;

class Tallyforms extends IntegrationBase {

	use ActionResponseTrait;

	private const API_BASE_URL = 'https://api.tally.so';

	public static function get_slug(): string {
		return 'tallyforms';
	}

	public static function get_name(): string {
		return 'Tally Forms';
	}

	public static function get_icon(): string {
		return 'tallyforms.svg';
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
				'help'     => 'Generate from Tally → Settings → API Keys → Create API Key (tally.so/settings/api-keys).',
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
			self::API_BASE_URL . '/users/me',
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
				'name'        => $body['name'] ?? '',
				'webhook_url' => self::get_webhook_url(),
			],
		];
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'zaplane_tallyforms_webhook_form_submitted',
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
						'integration' => 'tallyforms',
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
					'help'     => 'Copy this URL → Tally Dashboard → Your Form → Integrations → Webhooks → Connect → Paste & Save.',
				],
			];
		}

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'form_submitted':
				$payload = $args[0] ?? [];

				$data = $payload['data'] ?? [];

				if ( empty( $data ) || empty( $data['formId'] ) ) {
					return false;
				}

				$form_id  = $data['formId'];
				$selected = $node['data']['config']['form_id'] ?? 'any';

				if ( ! empty( $selected ) && 'any' !== $selected && $selected !== $form_id ) {
					return false;
				}

				return [
					'tally_event_id'      => $payload['eventId'] ?? '',
					'tally_form_id'       => $form_id,
					'tally_form_name'     => $data['formName'] ?? '',
					'tally_response_id'   => $data['responseId'] ?? '',
					'tally_respondent_id' => $data['respondentId'] ?? '',
					'tally_submitted_at'  => $data['createdAt'] ?? '',
					'tally_fields'        => self::parse_fields( $data['fields'] ?? [] ),
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
				'label' => $form['name'] ?? 'Untitled',
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

		if ( empty( $payload ) || empty( $payload['eventType'] ) ) {
			return null;
		}

		if ( 'FORM_RESPONSE' !== $payload['eventType'] ) {
			return null;
		}

		return [
			'event'   => 'form_submitted',
			'payload' => $payload,
		];
	}

	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		$received_signature = $request->get_header( 'Tally-Signature' );

		if ( empty( $received_signature ) ) {
			return true;
		}

		$connection_id = self::get_tallyforms_connection_id();
		$creds         = self::get_decrypted_credentials( $connection_id );
		$signing_secret = $creds['signing_secret'] ?? '';

		if ( empty( $signing_secret ) ) {
			return true;
		}

		$raw_body            = $request->get_body();
		$calculated_signature = base64_encode(
			hash_hmac( 'sha256', $raw_body, $signing_secret, true )
		);

		return hash_equals( $calculated_signature, $received_signature );
	}

	public static function register_webhooks_for_all_forms(): array {
		$results = [];

		$connection_id = self::get_tallyforms_connection_id();
		$creds         = self::get_decrypted_credentials( $connection_id );
		$token         = $creds['access_token'] ?? '';

		if ( empty( $token ) ) {
			return [ 'error' => 'No active Tally Forms connection found.' ];
		}

		$forms = self::fetch_forms( $token );

		if ( empty( $forms ) ) {
			return [ 'error' => 'No forms found in Tally account.' ];
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
		}

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

		$webhook_url = self::get_webhook_url();

		$existing = self::tally_request( $token, 'GET', '/webhooks?formId=' . rawurlencode( $form_id ) );
		$already_registered = false;

		foreach ( $existing['data'] ?? [] as $wh ) {
			if ( isset( $wh['url'] ) && $wh['url'] === $webhook_url && ! empty( $wh['isEnabled'] ) ) {
				$already_registered = true;
				break;
			}
		}

		if ( $already_registered ) {
			return;
		}

		self::tally_request(
			$token,
			'POST',
			'/webhooks',
			[
				'formId'     => $form_id,
				'url'        => $webhook_url,
				'eventTypes' => [ 'FORM_RESPONSE' ],
			]
		);
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
				$connection_id = self::get_tallyforms_connection_id();
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

	private static function get_tallyforms_connection_id(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'zaplane_connections';
		$id    = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
				'SELECT id FROM `' . esc_sql( $table ) . "` WHERE app = %s AND status = 'active' ORDER BY id DESC LIMIT 1",
				'tallyforms'
			)
		);
		return (int) $id;
	}

	private static function fetch_forms( string $token ): array {
		$all_forms = [];
		$page      = 1;

		do {
			$response = wp_remote_get(
				self::API_BASE_URL . '/forms?page=' . $page . '&limit=100',
				[
					'headers' => [ 'Authorization' => 'Bearer ' . $token ],
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

			$forms = $body['data'] ?? [];
			$all_forms = array_merge( $all_forms, $forms );

			$has_next = ! empty( $body['pagination']['hasNextPage'] );
			++$page;
		} while ( $has_next );

		return $all_forms;
	}

	private static function parse_fields( array $fields ): array {
		$data = [];

		$options_map = [];
		foreach ( $fields as $field ) {
			$key     = $field['key'] ?? null;
			$options = $field['options'] ?? null;
			if ( $key && is_array( $options ) ) {
				$options_map[ $key ] = $options;
			}
		}

		foreach ( $fields as $field ) {
			$key   = $field['key']   ?? null;
			$type  = $field['type']  ?? '';
			$value = $field['value'] ?? null;
			$label = $field['label'] ?? $key;

			if ( null === $key ) {
				continue;
			}

			switch ( $type ) {
				case 'MULTIPLE_CHOICE':
				case 'DROPDOWN':
					if ( is_array( $value ) ) {
						$data[ $label ] = self::resolve_option_labels(
							$value,
							$options_map[ $key ] ?? []
						);
						if ( 1 === count( $data[ $label ] ) ) {
							$data[ $label ] = reset( $data[ $label ] );
						}
					} else {
						$data[ $label ] = $value;
					}
					break;

				case 'CHECKBOXES':
				case 'MULTI_SELECT':
				case 'RANKING':
					if ( is_array( $value ) ) {
						$data[ $label ] = self::resolve_option_labels(
							$value,
							$options_map[ $key ] ?? []
						);
					} else {
						$data[ $label ] = (bool) $value;
					}
					break;

				case 'FILE_UPLOAD':
				case 'SIGNATURE':
					if ( is_array( $value ) ) {
						$data[ $label ] = array_map(
							static fn( $f ) => $f['url'] ?? '',
							$value
						);
						if ( 1 === count( $data[ $label ] ) ) {
							$data[ $label ] = reset( $data[ $label ] );
						}
					} else {
						$data[ $label ] = $value;
					}
					break;

				case 'MATRIX':
					$data[ $label ] = $value;
					break;

				case 'PAYMENT':
					$data[ $label ] = $value;
					break;

				default:
					$data[ $label ] = $value;
					break;
			}//end switch
		}//end foreach

		return $data;
	}

	private static function resolve_option_labels( array $ids, array $options ): array {
		if ( empty( $options ) ) {
			return $ids;
		}

		$id_to_text = [];
		foreach ( $options as $option ) {
			if ( isset( $option['id'], $option['text'] ) ) {
				$id_to_text[ $option['id'] ] = $option['text'];
			}
		}

		return array_map(
			static fn( $id ) => $id_to_text[ $id ] ?? $id,
			$ids
		);
	}

	private static function tally_request( string $token, string $method, string $endpoint, array $payload = [] ): array {
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
			throw new \Exception( 'Tally API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( 204 === $code ) {
			return [];
		}

		if ( $code >= 400 ) {
			$message = $body['message'] ?? $body['error'] ?? ( 'HTTP ' . $code );
			throw new \Exception( 'Tally API error: ' . esc_html( $message ) );
		}

		return $body;
	}
}
