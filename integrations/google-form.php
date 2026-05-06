<?php
namespace Zaplane\Framework\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Forms Integration
 *
 * Triggers  : new_response
 * Actions   : get_form, list_responses, get_response, create_form, create_watch
 * Auth      : OAuth 2.0 (Authorization Code)
 * API Base  : https://forms.googleapis.com/v1
 */
class GoogleForms extends IntegrationBase {

	// -------------------------------------------------------------------------
	// Constants
	// -------------------------------------------------------------------------

	private const API_BASE      = 'https://forms.googleapis.com/v1';
	private const OAUTH_AUTH    = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const OAUTH_TOKEN   = 'https://oauth2.googleapis.com/token';
	private const OAUTH_REVOKE  = 'https://oauth2.googleapis.com/revoke';

	// -------------------------------------------------------------------------
	// Identity
	// -------------------------------------------------------------------------

	public static function get_slug(): string {
		return 'google_forms';
	}

	public static function get_name(): string {
		return 'Google Forms';
	}

	public static function get_icon(): string {
		return 'google-forms';
	}

	public static function get_category(): string {
		return 'google';
	}

	// -------------------------------------------------------------------------
	// Triggers
	// -------------------------------------------------------------------------

	public static function get_triggers(): array {
		return [
			[
				'slug'        => 'new_response',
				'name'        => 'New Form Response',
				'description' => 'Fires whenever a new response is submitted to a Google Form.',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( $trigger === 'new_response' ) {
			return [
				[
					'key'         => 'form_id',
					'label'       => 'Form ID',
					'type'        => 'text',
					'required'    => true,
					'description' => 'The ID of the Google Form to watch (from the form URL).',
				],
				[
					'key'         => 'polling_interval',
					'label'       => 'Polling Interval (minutes)',
					'type'        => 'number',
					'required'    => false,
					'default'     => 5,
					'description' => 'How often to check for new responses when webhooks are unavailable.',
				],
			];
		}

		return [];
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		if ( $trigger === 'new_response' ) {
			return [
				'response_id'  => 'ACYDBNhZ1234567890abcdef',
				'form_id'      => '1FAIpQLSe_SAMPLE_FORM_ID',
				'create_time'  => '2024-01-15T10:30:00Z',
				'last_submitted_time' => '2024-01-15T10:30:00Z',
				'respondent_email'    => 'user@example.com',
				'answers'      => [
					'question_id_1' => [
						'question_id'   => 'question_id_1',
						'text_answers'  => [
							'answers' => [
								[ 'value' => 'Sample answer text' ],
							],
						],
					],
					'question_id_2' => [
						'question_id'   => 'question_id_2',
						'text_answers'  => [
							'answers' => [
								[ 'value' => 'Another answer' ],
							],
						],
					],
				],
			];
		}

		return [];
	}

	/**
	 * Polling-based trigger resolver.
	 * Called on every cron tick; returns the mapped data when a new response
	 * has arrived since the last stored response ID, otherwise false.
	 */
	public static function resolve_trigger( array $node, array $hook_args ): bool|array {
		$config      = $node['config'] ?? [];
		$form_id     = $config['form_id'] ?? '';
		$credentials = $node['credentials'] ?? [];

		if ( empty( $form_id ) || empty( $credentials ) ) {
			return false;
		}

		// Retrieve the ID of the last response we already processed.
		$last_response_id = get_option( 'zaplane_gforms_last_response_' . sanitize_key( $form_id ), '' );

		try {
			$token    = static::get_access_token( $credentials );
			$headers  = [ 'Authorization' => 'Bearer ' . $token ];

			[ $body, $status ] = static::http_get(
				static::API_BASE . '/forms/' . rawurlencode( $form_id ) . '/responses?orderBy=createTime+desc&pageSize=1',
				$headers
			);
		} catch ( \Exception $e ) {
			return false;
		}

		if ( $status !== 200 || empty( $body['responses'] ) ) {
			return false;
		}

		$latest   = $body['responses'][0];
		$resp_id  = $latest['responseId'] ?? '';

		// No new response since last run.
		if ( $resp_id === $last_response_id ) {
			return false;
		}

		// Persist the latest response ID.
		update_option( 'zaplane_gforms_last_response_' . sanitize_key( $form_id ), $resp_id, false );

		return static::map_response( $latest, $form_id );
	}

	// -------------------------------------------------------------------------
	// Actions
	// -------------------------------------------------------------------------

	public static function get_actions(): array {
		return [
			[
				'slug'        => 'get_form',
				'name'        => 'Get Form',
				'description' => 'Retrieve metadata and questions for a Google Form.',
			],
			[
				'slug'        => 'list_responses',
				'name'        => 'List Responses',
				'description' => 'List all responses submitted to a Google Form.',
			],
			[
				'slug'        => 'get_response',
				'name'        => 'Get Response',
				'description' => 'Retrieve a single form response by response ID.',
			],
			[
				'slug'        => 'create_form',
				'name'        => 'Create Form',
				'description' => 'Create a new blank Google Form with a given title.',
			],
			[
				'slug'        => 'create_watch',
				'name'        => 'Create Watch',
				'description' => 'Register a push-notification watch on a form so Google calls your webhook on new responses.',
			],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$form_id_field = [
			'key'      => 'form_id',
			'label'    => 'Form ID',
			'type'     => 'text',
			'required' => true,
		];

		switch ( $action ) {

			case 'get_form':
				return [ $form_id_field ];

			case 'list_responses':
				return [
					$form_id_field,
					[
						'key'      => 'page_size',
						'label'    => 'Page Size',
						'type'     => 'number',
						'default'  => 10,
						'required' => false,
					],
					[
						'key'      => 'page_token',
						'label'    => 'Page Token',
						'type'     => 'text',
						'required' => false,
					],
					[
						'key'      => 'filter',
						'label'    => 'Filter (timestamp)',
						'type'     => 'text',
						'required' => false,
						'description' => 'e.g. timestamp > 2024-01-01T00:00:00Z',
					],
				];

			case 'get_response':
				return [
					$form_id_field,
					[
						'key'      => 'response_id',
						'label'    => 'Response ID',
						'type'     => 'text',
						'required' => true,
					],
				];

			case 'create_form':
				return [
					[
						'key'      => 'title',
						'label'    => 'Form Title',
						'type'     => 'text',
						'required' => true,
					],
				];

			case 'create_watch':
				return [
					$form_id_field,
					[
						'key'         => 'webhook_url',
						'label'       => 'Webhook Target URL',
						'type'        => 'text',
						'required'    => true,
						'description' => 'HTTPS endpoint Google will POST to on each new response.',
					],
				];
		}

		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$action      = $node['action'] ?? '';
		$config      = array_merge( $node['config'] ?? [], $input );
		$credentials = $node['credentials'] ?? [];

		try {
			$token   = static::get_access_token( $credentials );
			$headers = [ 'Authorization' => 'Bearer ' . $token ];

			switch ( $action ) {

				case 'get_form':
					return static::action_get_form( $config, $headers );

				case 'list_responses':
					return static::action_list_responses( $config, $headers );

				case 'get_response':
					return static::action_get_response( $config, $headers );

				case 'create_form':
					return static::action_create_form( $config, $headers );

				case 'create_watch':
					return static::action_create_watch( $config, $headers );

				default:
					throw new \Exception( 'Unknown Google Forms action: ' . esc_html( $action ) );
			}
		} catch ( \Exception $e ) {
			return [
				'port'  => 'error',
				'data'  => [ 'error' => $e->getMessage() ],
			];
		}
	}

	// -------------------------------------------------------------------------
	// Action handlers
	// -------------------------------------------------------------------------

	private static function action_get_form( array $config, array $headers ): array {
		$form_id = $config['form_id'] ?? '';
		static::require_field( $form_id, 'form_id' );

		[ $body, $status ] = static::http_get(
			static::API_BASE . '/forms/' . rawurlencode( $form_id ),
			$headers
		);

		static::assert_success( $status, $body );

		return [
			'port' => 'main',
			'data' => [
				'form_id'     => $body['formId']    ?? $form_id,
				'title'       => $body['info']['title'] ?? '',
				'description' => $body['info']['description'] ?? '',
				'items'       => static::map_items( $body['items'] ?? [] ),
				'responder_uri' => $body['responderUri'] ?? '',
				'revision_id' => $body['revisionId'] ?? '',
			],
		];
	}

	private static function action_list_responses( array $config, array $headers ): array {
		$form_id    = $config['form_id'] ?? '';
		$page_size  = (int) ( $config['page_size'] ?? 10 );
		$page_token = $config['page_token'] ?? '';
		$filter     = $config['filter'] ?? '';

		static::require_field( $form_id, 'form_id' );

		$params = [ 'pageSize' => $page_size ];
		if ( $page_token ) {
			$params['pageToken'] = $page_token;
		}
		if ( $filter ) {
			$params['filter'] = $filter;
		}

		$url = static::API_BASE . '/forms/' . rawurlencode( $form_id ) . '/responses?' . http_build_query( $params );

		[ $body, $status ] = static::http_get( $url, $headers );
		static::assert_success( $status, $body );

		$responses = array_map(
			fn( $r ) => static::map_response( $r, $form_id ),
			$body['responses'] ?? []
		);

		return [
			'port' => 'main',
			'data' => [
				'responses'      => $responses,
				'next_page_token' => $body['nextPageToken'] ?? '',
			],
		];
	}

	private static function action_get_response( array $config, array $headers ): array {
		$form_id     = $config['form_id'] ?? '';
		$response_id = $config['response_id'] ?? '';

		static::require_field( $form_id, 'form_id' );
		static::require_field( $response_id, 'response_id' );

		[ $body, $status ] = static::http_get(
			static::API_BASE . '/forms/' . rawurlencode( $form_id ) . '/responses/' . rawurlencode( $response_id ),
			$headers
		);

		static::assert_success( $status, $body );

		return [
			'port' => 'main',
			'data' => static::map_response( $body, $form_id ),
		];
	}

	private static function action_create_form( array $config, array $headers ): array {
		$title = $config['title'] ?? '';
		static::require_field( $title, 'title' );

		$headers['Content-Type'] = 'application/json';

		[ $body, $status ] = static::http_post(
			static::API_BASE . '/forms',
			[ 'info' => [ 'title' => $title ] ],
			$headers
		);

		static::assert_success( $status, $body );

		return [
			'port' => 'main',
			'data' => [
				'form_id'       => $body['formId'] ?? '',
				'title'         => $body['info']['title'] ?? $title,
				'responder_uri' => $body['responderUri'] ?? '',
			],
		];
	}

	private static function action_create_watch( array $config, array $headers ): array {
		$form_id     = $config['form_id'] ?? '';
		$webhook_url = $config['webhook_url'] ?? '';

		static::require_field( $form_id, 'form_id' );
		static::require_field( $webhook_url, 'webhook_url' );

		$headers['Content-Type'] = 'application/json';

		[ $body, $status ] = static::http_post(
			static::API_BASE . '/forms/' . rawurlencode( $form_id ) . '/watches',
			[
				'watch' => [
					'target'    => [ 'topic' => [ 'topicName' => $webhook_url ] ],
					'eventType' => 'RESPONSES',
				],
			],
			$headers
		);

		static::assert_success( $status, $body );

		return [
			'port' => 'main',
			'data' => [
				'watch_id'   => $body['id'] ?? '',
				'form_id'    => $body['formId'] ?? $form_id,
				'event_type' => $body['eventType'] ?? 'RESPONSES',
				'state'      => $body['watchState'] ?? '',
				'expire_time' => $body['expireTime'] ?? '',
			],
		];
	}

	// -------------------------------------------------------------------------
	// Output ports
	// -------------------------------------------------------------------------

	public static function get_output_ports(): array {
		return [ 'main', 'error' ];
	}

	// -------------------------------------------------------------------------
	// Connection / Auth
	// -------------------------------------------------------------------------

	public static function requires_connection(): bool {
		return true;
	}

	public static function get_auth_type(): string {
		return 'oauth2';
	}

	public static function get_available_auth_types(): array {
		return [ 'oauth2' ];
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			[
				'key'      => 'client_id',
				'label'    => 'Client ID',
				'type'     => 'text',
				'required' => true,
			],
			[
				'key'      => 'client_secret',
				'label'    => 'Client Secret',
				'type'     => 'password',
				'required' => true,
			],
			[
				'key'      => 'access_token',
				'label'    => 'Access Token',
				'type'     => 'password',
				'required' => false,
				'description' => 'Auto-populated after OAuth flow.',
			],
			[
				'key'      => 'refresh_token',
				'label'    => 'Refresh Token',
				'type'     => 'password',
				'required' => false,
				'description' => 'Auto-populated after OAuth flow.',
			],
		];
	}

	public static function get_oauth_scopes(): array {
		return [
			'https://www.googleapis.com/auth/forms.body',
			'https://www.googleapis.com/auth/forms.responses.readonly',
		];
	}

	public static function get_oauth_auth_url( string $redirect_uri, string $state, array $credentials = [] ): ?string {
		$params = [
			'client_id'     => $credentials['client_id'] ?? '',
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => implode( ' ', static::get_oauth_scopes() ),
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		];

		return static::OAUTH_AUTH . '?' . http_build_query( $params );
	}

	public static function exchange_oauth_code( string $code, string $redirect_uri, array $credentials = [] ): array {
		[ $body, $status ] = static::http_post(
			static::OAUTH_TOKEN,
			[
				'code'          => $code,
				'client_id'     => $credentials['client_id'] ?? '',
				'client_secret' => $credentials['client_secret'] ?? '',
				'redirect_uri'  => $redirect_uri,
				'grant_type'    => 'authorization_code',
			],
			[ 'Content-Type' => 'application/x-www-form-urlencoded' ]
		);

		if ( $status !== 200 || empty( $body['access_token'] ) ) {
			throw new \Exception( 'Google Forms OAuth token exchange failed.' );
		}

		return [
			'access_token'  => $body['access_token'],
			'refresh_token' => $body['refresh_token'] ?? '',
			'expires_in'    => $body['expires_in'] ?? 3600,
			'token_type'    => $body['token_type'] ?? 'Bearer',
		];
	}

	public static function refresh_oauth_token( string $refresh_token ): array {
		// We need client credentials for refresh; they live inside $credentials
		// but this signature only passes the refresh token.
		// Implementers should override or call with full credentials context.
		[ $body, $status ] = static::http_post(
			static::OAUTH_TOKEN,
			[
				'refresh_token' => $refresh_token,
				'grant_type'    => 'refresh_token',
			]
		);

		if ( $status !== 200 || empty( $body['access_token'] ) ) {
			throw new \Exception( 'Google Forms token refresh failed.' );
		}

		return [
			'access_token' => $body['access_token'],
			'expires_in'   => $body['expires_in'] ?? 3600,
		];
	}

	public static function test_connection( array $credentials ): array {
		try {
			$token   = static::get_access_token( $credentials );
			$headers = [ 'Authorization' => 'Bearer ' . $token ];

			// A lightweight call: list up to 1 form (Drive Files API).
			[ $body, $status ] = static::http_get(
				'https://www.googleapis.com/drive/v3/files?q=mimeType%3D%27application%2Fvnd.google-apps.form%27&pageSize=1&fields=files(id%2Cname)',
				$headers
			);

			if ( $status === 200 ) {
				return [
					'success' => true,
					'message' => 'Connected to Google Forms successfully.',
					'details' => [ 'forms_found' => count( $body['files'] ?? [] ) ],
				];
			}

			return [
				'success' => false,
				'message' => 'Google Forms connection test failed (HTTP ' . $status . ').',
				'details' => $body,
			];
		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'message' => $e->getMessage(),
				'details' => [],
			];
		}
	}

	// -------------------------------------------------------------------------
	// Polling support
	// -------------------------------------------------------------------------

	public static function supports_polling(): bool {
		return true;
	}

	public static function get_rate_limit(): int {
		return 300; // 300 requests / 60 s (Google Forms API default quota).
	}

	// -------------------------------------------------------------------------
	// Config validation
	// -------------------------------------------------------------------------

	public static function validate_config( array $config ): bool {
		return ! empty( $config['client_id'] ) && ! empty( $config['client_secret'] );
	}

	public static function get_config_schema(): array {
		return [
			[
				'key'      => 'client_id',
				'label'    => 'OAuth Client ID',
				'type'     => 'text',
				'required' => true,
			],
			[
				'key'      => 'client_secret',
				'label'    => 'OAuth Client Secret',
				'type'     => 'password',
				'required' => true,
			],
		];
	}

	// -------------------------------------------------------------------------
	// Dynamic fields
	// -------------------------------------------------------------------------

	public static function get_dynamic_fields(): array {
		return [
			[
				'action'     => 'list_responses',
				'depends_on' => 'form_id',
				'field'      => 'form_id',
				'label'      => 'Form',
				'type'       => 'select',
				'source'     => 'get_form_options',
			],
		];
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Return a valid access token, refreshing automatically when needed.
	 */
	private static function get_access_token( array $credentials ): string {
		$access_token  = $credentials['access_token'] ?? '';
		$refresh_token = $credentials['refresh_token'] ?? '';

		if ( ! empty( $access_token ) ) {
			return $access_token;
		}

		if ( ! empty( $refresh_token ) ) {
			$refreshed = static::refresh_oauth_token( $refresh_token );
			return $refreshed['access_token'] ?? '';
		}

		throw new \Exception( 'Google Forms: no access token or refresh token available.' );
	}

	/**
	 * Throw on non-2xx status codes.
	 */
	private static function assert_success( int $status, array $body ): void {
		if ( $status < 200 || $status >= 300 ) {
			$message = $body['error']['message'] ?? ( 'Google Forms API error (HTTP ' . $status . ')' );
			throw new \Exception( esc_html( $message ) );
		}
	}

	/**
	 * Throw if a required string field is empty.
	 */
	private static function require_field( string $value, string $field_name ): void {
		if ( $value === '' ) {
			throw new \Exception( 'Google Forms: required field "' . esc_html( $field_name ) . '" is missing.' );
		}
	}

	/**
	 * Normalize a raw API response object to a flat, snake_case array.
	 */
	private static function map_response( array $raw, string $form_id ): array {
		return [
			'response_id'          => $raw['responseId'] ?? '',
			'form_id'              => $raw['formId'] ?? $form_id,
			'create_time'          => $raw['createTime'] ?? '',
			'last_submitted_time'  => $raw['lastSubmittedTime'] ?? '',
			'respondent_email'     => $raw['respondentEmail'] ?? '',
			'answers'              => static::map_answers( $raw['answers'] ?? [] ),
		];
	}

	/**
	 * Normalize the answers map from the API into a simple snake_case structure.
	 */
	private static function map_answers( array $raw_answers ): array {
		$answers = [];

		foreach ( $raw_answers as $question_id => $answer_obj ) {
			$answers[ $question_id ] = [
				'question_id'  => $answer_obj['questionId'] ?? $question_id,
				'text_answers' => array_map(
					fn( $a ) => $a['value'] ?? '',
					$answer_obj['textAnswers']['answers'] ?? []
				),
				'file_upload_answers' => array_map(
					fn( $f ) => [
						'file_id'   => $f['fileId'] ?? '',
						'file_name' => $f['fileName'] ?? '',
						'mime_type' => $f['mimeType'] ?? '',
					],
					$answer_obj['fileUploadAnswers']['answers'] ?? []
				),
			];
		}

		return $answers;
	}

	/**
	 * Normalize form items (questions) into a simple snake_case list.
	 */
	private static function map_items( array $raw_items ): array {
		return array_map( function ( $item ) {
			$mapped = [
				'item_id'     => $item['itemId'] ?? '',
				'title'       => $item['title'] ?? '',
				'description' => $item['description'] ?? '',
				'kind'        => '',
				'question_id' => '',
				'required'    => false,
			];

			if ( isset( $item['questionItem']['question'] ) ) {
				$q = $item['questionItem']['question'];
				$mapped['kind']        = array_key_first(
					array_diff_key( $q, array_flip( [ 'questionId', 'required', 'grading' ] ) )
				) ?? 'unknown';
				$mapped['question_id'] = $q['questionId'] ?? '';
				$mapped['required']    = (bool) ( $q['required'] ?? false );
			} elseif ( isset( $item['pageBreakItem'] ) ) {
				$mapped['kind'] = 'page_break';
			} elseif ( isset( $item['textItem'] ) ) {
				$mapped['kind'] = 'text';
			} elseif ( isset( $item['imageItem'] ) ) {
				$mapped['kind'] = 'image';
			} elseif ( isset( $item['videoItem'] ) ) {
				$mapped['kind'] = 'video';
			}

			return $mapped;
		}, $raw_items );
	}
}
