<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Typeform extends IntegrationBase {

	private const API_BASE_URL = 'https://api.typeform.com';

	// -------------------------------------------------------------------------
	// Identity
	// -------------------------------------------------------------------------

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

	public static function supports_oauth(): bool {
		return false;
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'access_token' => [
				'type'     => 'password',
				'label'    => 'Personal Access Token',
				'required' => true,
			],
		];
	}

	// -------------------------------------------------------------------------
	// Connection Test
	// -------------------------------------------------------------------------

	public static function test_connection( array $credentials ): array {

		$token = $credentials['access_token'] ?? '';

		if ( empty( $token ) ) {
			return [ 'success' => false, 'message' => 'Token required' ];
		}

		$res = wp_remote_get(
			self::API_BASE_URL . '/me',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
				],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $res ) ) {
			return [ 'success' => false, 'message' => $res->get_error_message() ];
		}

		$body = json_decode( wp_remote_retrieve_body( $res ), true );

		return [
			'success' => ! empty( $body['email'] ),
			'message' => 'Connected: ' . ( $body['email'] ?? '' ),
		];
	}

	// -------------------------------------------------------------------------
	// Triggers
	// -------------------------------------------------------------------------

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'tf_new_entry',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {

		if ( 'form_submitted' === $trigger ) {
			return [
				[
					'key'     => 'form_id',
					'label'   => 'Form',
					'type'    => 'select',
					'dynamic' => [
						'integration' => 'typeform',
						'query'       => 'form_query',
						'select'      => [ 'value', 'label' ],
					],
					'required' => true,
				],
			];
		}

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {

		if ( $node['event'] !== 'form_submitted' ) {
			return false;
		}

		$payload = $args[0] ?? [];

		$form_id = $payload['form_response']['form_id'] ?? '';
		if ( empty( $form_id ) ) return false;

		$selected = $node['form_id'] ?? '';

		if ( $selected && $selected !== 'any' && $selected !== $form_id ) {
			return false;
		}

		return [
			'success'  => true,
			'form_id'  => $form_id,
			'entry_id' => $payload['form_response']['token'] ?? '',
			'data'     => self::parse_answers( $payload['form_response']['answers'] ?? [] ),
		];
	}

	// -------------------------------------------------------------------------
	// Actions
	// -------------------------------------------------------------------------

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
					'key'      => 'title',
					'type'     => 'text',
					'label'    => 'Form Title',
					'required' => true,
				],
				[
					'key'   => 'workspace_id',
					'label' => 'Workspace',
					'type'  => 'select',
					'dynamic' => [
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
					'type'     => 'text',
					'label'    => 'Form ID',
					'required' => true,
				],
			];
		}

		return [];
	}

	// -------------------------------------------------------------------------
	// EXECUTE NODE (FIXED)
	// -------------------------------------------------------------------------

	public static function execute_node( array $node, array $input ): array {

		$event       = $node['data']['event'] ?? '';
		$credentials = $node['_connection_credentials'] ?? null;

		if ( ! $credentials ) {
			throw new \Exception( 'No Typeform credentials found' );
		}

		$token = $credentials['access_token'] ?? '';

		if ( empty( $token ) ) {
			throw new \Exception( 'Access token required' );
		}

		switch ( $event ) {

			case 'create_form':

				$title        = $node['data']['config']['title'] ?? '';
				$workspace_id = $node['data']['config']['workspace_id'] ?? '';

				if ( empty( $title ) ) {
					throw new \Exception( 'Title required' );
				}

				$payload = [ 'title' => $title ];

				if ( $workspace_id ) {
					$payload['workspace'] = [
						'href' => self::API_BASE_URL . '/workspaces/' . $workspace_id
					];
				}

				$body = self::typeform_request( $token, 'POST', '/forms', $payload );

				// ✅ FIXED: proper form id
				$form_id = $body['id'] ?? '';

				// ✅ FIXED: webhook now uses correct id
				if ( $form_id ) {
					self::ensure_webhook( $token, $form_id );
				}

				return [
					'port' => 'main',
					'data' => array_merge( $input, [
						'typeform_form_id' => $form_id,
						'typeform_url'     => $body['_links']['display'] ?? '',
						'typeform_title'   => $body['title'] ?? '',
					] ),
				];

			case 'delete_form':

				$form_id = $node['data']['config']['form_id'] ?? '';

				if ( empty( $form_id ) ) {
					throw new \Exception( 'Form ID required' );
				}

				self::typeform_request( $token, 'DELETE', '/forms/' . $form_id );

				return [
					'port' => 'main',
					'data' => [
						'typeform_deleted_form_id' => $form_id,
						'typeform_status' => 'deleted',
					],
				];
		}

		return [
			'port' => 'main',
			'data' => $input,
		];
	}

	// -------------------------------------------------------------------------
	// Dynamic Queries
	// -------------------------------------------------------------------------

	public static function get_dynamic_queries(): array {
		return [
			'form_query'      => [ self::class, 'query_form' ],
			'workspace_query' => [ self::class, 'query_workspace' ],
		];
	}

	public static function query_form( $query ): array {

	error_log('TYPEFORM FORM QUERY HIT');
	error_log(print_r($query, true));

	$options = [
		[ 'label' => 'Any Form', 'value' => 'any' ],
	];

	$token =
		$query['access_token']
		?? ($query['credentials']['access_token'] ?? '')
		?? ($query['auth']['access_token'] ?? '');

	if ( empty( $token ) ) {
		error_log('NO TYPEFORM TOKEN FOUND');
		return $options;
	}

	$response = wp_remote_get(
		self::API_BASE_URL . '/forms?page_size=100',
		[
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
			],
			'timeout' => 15,
		]
	);

	if ( is_wp_error( $response ) ) {
		error_log('TYPEFORM API ERROR: ' . $response->get_error_message());
		return $options;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	error_log(print_r($body, true));

	$forms = $body['items'] ?? [];

	foreach ( $forms as $form ) {
		$options[] = [
			'label' => $form['title'] ?? 'Untitled',
			'value' => $form['id'] ?? '',
		];
	}

	return $options;
}

public static function query_workspace( $query ): array {

	error_log('TYPEFORM WORKSPACE QUERY HIT');
	error_log(print_r($query, true));

	$options = [];

	$token =
		$query['access_token']
		?? ($query['credentials']['access_token'] ?? '')
		?? ($query['auth']['access_token'] ?? '');

	if ( empty( $token ) ) {
		error_log('NO TYPEFORM TOKEN FOUND');
		return $options;
	}

	$response = wp_remote_get(
		self::API_BASE_URL . '/workspaces?page_size=100',
		[
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
			],
			'timeout' => 15,
		]
	);

	if ( is_wp_error( $response ) ) {
		error_log('TYPEFORM API ERROR: ' . $response->get_error_message());
		return $options;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	error_log(print_r($body, true));

	$workspaces = $body['items'] ?? [];

	foreach ( $workspaces as $ws ) {
		$options[] = [
			'label' => $ws['title'] ?? 'Workspace',
			'value' => $ws['id'] ?? '',
		];
	}

	return $options;
}

	// -------------------------------------------------------------------------
	// Parse answers
	// -------------------------------------------------------------------------

	private static function parse_answers( array $answers ): array {

		$data = [];

		foreach ( $answers as $a ) {

			$key = $a['field']['ref'] ?? $a['field']['id'] ?? null;
			if ( ! $key ) continue;

			$type = $a['type'] ?? '';

			$data[$key] = match ($type) {
				'choice'  => $a['choice']['label'] ?? '',
				'choices' => $a['choices']['labels'] ?? [],
				'boolean' => (bool) ($a['boolean'] ?? false),
				'number'  => $a['number'] ?? null,
				default   => $a[$type] ?? null,
			};
		}

		return $data;
	}

	// -------------------------------------------------------------------------
	// Webhook
	// -------------------------------------------------------------------------

	private static function ensure_webhook( string $token, string $form_id ): void {

		if ( ! $form_id || $form_id === 'any' ) return;

		$tag = 'zaplane_' . $form_id;

		self::typeform_request(
			$token,
			'PUT',
			"/forms/{$form_id}/webhooks/{$tag}",
			[
				'url'      => home_url('/wp-json/zaplane/v1/typeform-webhook'),
				'enabled'  => true,
			]
		);
	}

	private static function typeform_request( string $token, string $method, string $endpoint, array $payload = [] ): array {

		$res = wp_remote_request(
			self::API_BASE_URL . $endpoint,
			[
				'method'  => $method,
				'timeout' => 20,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				],
				'body' => $payload ? wp_json_encode($payload) : null,
			]
		);

		if ( is_wp_error( $res ) ) {
			throw new \Exception( $res->get_error_message() );
		}

		return json_decode( wp_remote_retrieve_body( $res ), true ) ?? [];
	}
}

// -----------------------------------------------------------------------------
// REST webhook endpoint
// -----------------------------------------------------------------------------

add_action('rest_api_init', function () {

	register_rest_route('zaplane/v1', '/typeform-webhook', [
		'methods'  => 'POST',
		'callback' => function ($req) {

			$payload = $req->get_json_params();

			if ( empty($payload['form_response']) ) {
				return ['success' => false];
			}

			do_action('tf_new_entry', $payload);

			return ['success' => true];
		},
		'permission_callback' => '__return_true',
	]);

});