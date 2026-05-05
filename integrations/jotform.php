<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Traits\ActionResponseTrait;

class Jotform extends IntegrationBase {

	use ActionResponseTrait;

	const BASE_URL = 'https://api.jotform.com';

	public static function get_slug(): string {
		return 'jotform';
	}

	public static function get_name(): string {
		return 'Jotform';
	}

	public static function get_icon(): string {
		return 'jotform.svg';
	}

	public static function requires_connection(): bool {
		return true;
	}

	public static function get_auth_type(): string {
		return 'token_key';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'api_key' => [
				'type'     => 'password',
				'label'    => 'API Key',
				'required' => true,
				'help'     => 'Go To Jotform (jotform.com/myaccount/api) → Myaccount → API → Create New Key → Set Permission to "Full Access" → Copy Key',
			],
		];
	}

	public static function test_connection( array $credentials ): array {
		$api_key = $credentials['api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'message' => 'API Key is required.',
				'details' => [],
			];
		}

		$response = wp_remote_get(
			self::BASE_URL . '/user?apiKey=' . rawurlencode( $api_key ),
			[
				'headers' => [ 'accept' => 'application/json' ],
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
			$error_msg = $body['message'] ?? '';
			return [
				'success' => false,
				'message' => 'Connection failed (HTTP ' . $code . ')' . ( $error_msg ? ': ' . $error_msg : '.' ),
				'details' => [],
			];
		}

		$username = $body['content']['username'] ?? $body['content']['name'] ?? '';

		return [
			'success' => true,
			'message' => 'Connected successfully.' . ( $username ? ' Account: ' . $username : '' ),
			'details' => [
				'username'    => $username,
				'webhook_url' => self::get_webhook_url(),
			],
		];
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'zaplane_jotform_webhook_form_submitted',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'form_submitted' === $trigger ) {
			return array_merge(
				self::field_form_query(),
				[
					[
						'key'      => 'webhook_endpoint',
						'label'    => 'Webhook Endpoint URL',
						'type'     => 'copy',
						'value'    => self::get_webhook_url(),
						'readonly' => true,
						'help'     => 'Copy this URL → Jotform → Your Form → Settings → Integrations → Webhooks → Paste & Save.',
					],
				]
			);
		}

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'form_submitted':
				$payload = $args[0] ?? [];

				if ( empty( $payload ) || ! is_array( $payload ) ) {
					return false;
				}

				$form_id = $payload['formID'] ?? $payload['form_id'] ?? '';

				if ( empty( $form_id ) ) {
					return false;
				}

				$selected = $node['data']['config']['form_id'] ?? 'any';

				if ( ! empty( $selected ) && 'any' !== $selected && $selected !== $form_id ) {
					return false;
				}

				return [
					'jotform_form_id'       => $form_id,
					'jotform_form_title'    => $payload['formTitle'] ?? $payload['form_title'] ?? '',
					'jotform_submission_id' => $payload['submissionID'] ?? $payload['submission_id'] ?? '',
					'jotform_created_at'    => $payload['created_at'] ?? '',
					'jotform_answers'       => self::parse_answers( $payload['answers'] ?? [] ),
					'jotform_ip'            => $payload['ip'] ?? '',
				];
		}//end switch

		return false;
	}

	public static function get_actions(): array {
		return [
			'create_form'           => [ 'label' => 'Create Form' ],
			'add_questions_to_form' => [ 'label' => 'Add Questions to Form' ],
			'delete_form'           => [ 'label' => 'Delete Form' ],
		];
	}

	private static function field_form_query(): array {
		return [
			[
				'key'      => 'form_id',
				'label'    => 'Form',
				'type'     => 'select',
				'required' => true,
				'dynamic'  => [
					'integration' => 'jotform',
					'query'       => 'form_query',
					'select'      => [ 'value', 'label' ],
				],
			],
		];
	}

	private static function field_question(): array {
		return [
			[
				'key'      => 'questions',
				'label'    => 'Questions',
				'type'     => 'repeater',
				'required' => false,
				'fields'   => [
					[
						'key'      => 'label',
						'label'    => 'Label',
						'type'     => 'text',
						'required' => true,
					],
					[
						'key'      => 'slug',
						'label'    => 'Slug',
						'type'     => 'text',
						'required' => true,
					],
					[
						'key'      => 'type',
						'label'    => 'Type',
						'type'     => 'select',
						'required' => true,
						'options'  => [
							[
								'label' => 'Header',
								'value' => 'control_head'
							],
							[
								'label' => 'Full Name',
								'value' => 'control_fullname'
							],
							[
								'label' => 'Email',
								'value' => 'control_email'
							],
							[
								'label' => 'Address',
								'value' => 'control_address'
							],
							[
								'label' => 'Phone',
								'value' => 'control_phone'
							],
							[
								'label' => 'Date Picker',
								'value' => 'control_datetime'
							],
							[
								'label' => 'Signature',
								'value' => 'control_signature'
							],
							[
								'label' => 'Short Text',
								'value' => 'control_textbox'
							],
							[
								'label' => 'Long Text',
								'value' => 'control_textarea'
							],
							[
								'label' => 'Dropdown',
								'value' => 'control_dropdown'
							],
							[
								'label' => 'Single Choice',
								'value' => 'control_radio'
							],
							[
								'label' => 'Multiple Choice',
								'value' => 'control_checkbox'
							],
							[
								'label' => 'Number',
								'value' => 'control_number'
							],
							[
								'label' => 'Image',
								'value' => 'control_image'
							],
							[
								'label' => 'File Upload',
								'value' => 'control_fileupload'
							],
							[
								'label' => 'Time',
								'value' => 'control_time'
							],
							[
								'label' => 'Captcha',
								'value' => 'control_captcha'
							],
							[
								'label' => 'Spinner',
								'value' => 'control_spinner'
							],
							[
								'label' => 'Submit',
								'value' => 'control_button'
							],
						],
					],
					[
						'key'      => 'order',
						'label'    => 'Order',
						'type'     => 'text',
						'required' => false,
					],
				],
			],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		if ( 'create_form' === $action ) {
			return array_merge(
				[
					[
						'key'      => 'form_title',
						'label'    => 'Form Title',
						'type'     => 'text',
						'required' => true,
					],
				],
				self::field_question()
			);
		}

		if ( 'add_questions_to_form' === $action ) {
			return array_merge(
				self::field_form_query(),
				self::field_question()
			);
		}

		if ( 'delete_form' === $action ) {
			return self::field_form_query();
		}

		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$event   = $node['data']['event'] ?? '';
		$config  = $node['data']['config'] ?? [];
		$creds   = self::get_decrypted_credentials( (int) ( $node['data']['connection_id'] ?? 0 ) );
		$api_key = $creds['api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return self::error( 'No API Key found.' );
		}

		switch ( $event ) {

			case 'create_form':
				$form_title = trim( $config['form_title'] ?? '' );
				$questions  = $config['questions'] ?? [];

				if ( empty( $form_title ) ) {
					return self::error( 'Form title is required.' );
				}

				try {
					$form = self::jotform_request( $api_key, 'POST', '/form', [
						'properties[title]' => $form_title,
					] );
				} catch ( \Exception $e ) {
					return self::error( $e->getMessage() );
				}

				$form_id = $form['content']['id'] ?? '';

				if ( empty( $form_id ) ) {
					return self::error( 'Form created but ID not returned.' );
				}

				$added_questions = self::add_questions( $api_key, $form_id, $questions );

				return self::success( [
					'jotform_created_form_id'    => $form_id,
					'jotform_created_form_title' => $form_title,
					'jotform_created_form_url'   => 'https://www.jotform.com/build/' . $form_id,
					'jotform_questions_added'    => count( $added_questions ),
					'jotform_questions'          => $added_questions,
				] );

			case 'add_questions_to_form':
				$form_id   = trim( $config['form_id'] ?? '' );
				$questions = $config['questions'] ?? [];

				if ( empty( $form_id ) ) {
					return self::error( 'Form ID is required.' );
				}

				if ( empty( $questions ) ) {
					return self::error( 'At least one question is required.' );
				}

				$added_questions = self::add_questions( $api_key, $form_id, $questions );

				return self::success( [
					'jotform_form_id'         => $form_id,
					'jotform_questions_added' => count( $added_questions ),
					'jotform_questions'       => $added_questions,
				] );

			case 'delete_form':
				$form_id = trim( $config['form_id'] ?? '' );

				if ( empty( $form_id ) ) {
					return self::error( 'Form ID is required.' );
				}

				try {
					self::jotform_request( $api_key, 'DELETE', '/form/' . $form_id );
				} catch ( \Exception $e ) {
					return self::error( $e->getMessage() );
				}

				return self::success( [
					'jotform_deleted_form_id' => $form_id,
				] );
		}//end switch

		return [
			'port' => 'main',
			'data' => $input,
		];
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

		$creds   = self::extract_credentials( $query );
		$api_key = $creds['api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return $options;
		}

		$options[] = [
			'label' => 'Any Form',
			'value' => 'any',
		];

		foreach ( self::fetch_forms( $api_key ) as $form ) {
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
		$payload = $request->get_body_params();

		if ( empty( $payload ) ) {
			$payload = $request->get_json_params();
		}

		if ( empty( $payload ) || ! is_array( $payload ) ) {
			return null;
		}

		if ( empty( $payload['formID'] ) && empty( $payload['form_id'] ) ) {
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

	private static function add_questions( string $api_key, string $form_id, array $questions ): array {
		$added = [];

		foreach ( $questions as $index => $question ) {
			$label = trim( $question['label'] ?? '' );
			$slug  = trim( $question['slug'] ?? '' );
			$type  = trim( $question['type'] ?? 'control_textbox' );
			$order = trim( $question['order'] ?? (string) ( $index + 1 ) );

			if ( empty( $label ) || empty( $slug ) ) {
				continue;
			}

			try {
				$result  = self::jotform_request( $api_key, 'POST', '/form/' . $form_id . '/questions', [
					'type'  => $type,
					'text'  => $label,
					'name'  => $slug,
					'order' => $order,
				] );
				$added[] = [
					'label' => $label,
					'slug'  => $slug,
					'type'  => $type,
					'qid'   => $result['content']['qid'] ?? '',
				];
			} catch ( \Exception $e ) {
				$added[] = [
					'label' => $label,
					'slug'  => $slug,
					'error' => $e->getMessage(),
				];
			}
		}//end foreach

		return $added;
	}

	private static function fetch_forms( string $api_key ): array {
		$response = wp_remote_get(
			self::BASE_URL . '/user/forms?apiKey=' . rawurlencode( $api_key ) . '&limit=1000&orderby=created_at',
			[
				'headers' => [ 'accept' => 'application/json' ],
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

		return $body['content'] ?? [];
	}

	private static function parse_answers( array $answers ): array {
		$data = [];

		foreach ( $answers as $answer ) {
			$name = $answer['name'] ?? $answer['text'] ?? null;

			if ( null === $name ) {
				continue;
			}

			$data[ $name ] = $answer['answer'] ?? $answer['prettyFormat'] ?? null;
		}

		return $data;
	}

	private static function jotform_request( string $api_key, string $method, string $endpoint, array $payload = [] ): array {
		$url  = self::BASE_URL . $endpoint . '?apiKey=' . rawurlencode( $api_key );
		$args = [
			'method'  => strtoupper( $method ),
			'timeout' => 20,
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded',
				'accept'       => 'application/json',
			],
		];

		if ( ! empty( $payload ) ) {
			$args['body'] = http_build_query( $payload );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Jotform API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( $code >= 200 && $code < 300 ) {
			return $body;
		}

		$message = $body['message'] ?? ( 'HTTP ' . $code );
		throw new \Exception( 'Jotform API error: ' . esc_html( $message ) );
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
				$connection_id = self::get_jotform_connection_id();
			}

			if ( $connection_id <= 0 ) {
				return [];
			}

			$creds = $cm->get_execution_credentials( $connection_id );

			if ( is_array( $creds ) && ! empty( $creds['api_key'] ) ) {
				return $creds;
			}

			if ( is_object( $creds ) ) {
				$creds = (array) $creds;
				if ( ! empty( $creds['api_key'] ) ) {
					return $creds;
				}
			}
		} catch ( \Throwable $error ) {
			unset( $error );
		}//end try

		return [];
	}

	private static function get_jotform_connection_id(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'zaplane_connections';
		$id    = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholders
				'SELECT id FROM `' . esc_sql( $table ) . "` WHERE app = %s AND status = 'active' ORDER BY id DESC LIMIT 1",
				'jotform'
			)
		);
		return (int) $id;
	}
}
