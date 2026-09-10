<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Zoom extends IntegrationBase {

	private const API_BASE_URL    = 'https://api.zoom.us/v2';
	private const OAUTH_TOKEN_URL = 'https://zoom.us/oauth/token';
	private const OAUTH_AUTH_URL  = 'https://zoom.us/oauth/authorize';

	public static function get_slug(): string {
		return 'zoom';
	}

	public static function get_name(): string {
		return 'Zoom';
	}

	public static function get_icon(): string {
		return 'zoom.svg';
	}

	/** @inheritDoc */
	public static function get_docs_url(): array {
		return [
			'trigger' => 'https://zaplane.app/docs/zoom/',
			'action'  => 'https://zaplane.app/docs/zoom/',
		];
	}

	public static function get_triggers(): array {
		return [
			'meeting_started'    => [
				'label' => 'Meeting Started',
				'hook'  => 'zoom_webhook_meeting_started',
			],
			'meeting_ended'      => [
				'label' => 'Meeting Ended',
				'hook'  => 'zoom_webhook_meeting_ended',
			],
			'participant_joined' => [
				'label' => 'Participant Joined',
				'hook'  => 'zoom_webhook_participant_joined',
			],
		];
	}

	public static function get_actions(): array {
		return [
			'create_meeting' => [ 'label' => 'Create Meeting' ],
			'update_meeting' => [ 'label' => 'Update Meeting' ],
			'delete_meeting' => [ 'label' => 'Delete Meeting' ],
			'add_registrant' => [ 'label' => 'Add Meeting Registrant' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {

		$start_field = [
			'key'         => 'start_time',
			'type'        => 'text',
			'label'       => 'Start Time',
			'placeholder' => '2026-06-10T10:00:00',
			'required'    => true,
			'help'        => 'ISO 8601 format: YYYY-MM-DDTHH:MM:SS — e.g. 2026-06-10T10:00:00',
		];

		$timezone_field = [
			'key'      => 'timezone',
			'type'     => 'select',
			'label'    => 'Timezone',
			'required' => false,
			'help'     => 'Timezone for the meeting. Defaults to UTC.',
			'options'  => self::get_timezone_options(),
		];

		switch ( $action ) {

			case 'create_meeting':
				return [
					[
						'key'         => 'topic',
						'type'        => 'text',
						'label'       => 'Meeting Topic',
						'placeholder' => 'Team Standup',
						'required'    => true,
					],
					$start_field,
					[
						'key'         => 'duration',
						'type'        => 'text',
						'label'       => 'Duration (minutes)',
						'placeholder' => '60',
						'required'    => false,
						'help'        => 'Meeting length in minutes. Default: 60.',
					],
					$timezone_field,
					[
						'key'         => 'agenda',
						'type'        => 'textarea',
						'label'       => 'Agenda',
						'placeholder' => 'Meeting agenda...',
						'required'    => false,
					],
					[
						'key'         => 'password',
						'type'        => 'text',
						'label'       => 'Passcode',
						'placeholder' => 'Optional meeting passcode',
						'required'    => false,
						'help'        => 'Up to 10 characters. Alphanumeric and @, -, _, * only.',
					],
					[
						'key'      => 'waiting_room',
						'type'     => 'select',
						'label'    => 'Waiting Room',
						'required' => false,
						'options'  => [
							[
								'value' => 'false',
								'label' => 'Disabled'
							],
							[
								'value' => 'true',
								'label' => 'Enabled'
							],
						],
					],
					[
						'key'      => 'host_video',
						'type'     => 'select',
						'label'    => 'Start with Host Video On',
						'required' => false,
						'options'  => [
							[
								'value' => 'true',
								'label' => 'Yes'
							],
							[
								'value' => 'false',
								'label' => 'No'
							],
						],
					],
					[
						'key'         => 'user_id',
						'type'        => 'text',
						'label'       => 'Host User ID / Email',
						'placeholder' => 'me',
						'required'    => false,
						'help'        => 'Zoom user ID or email. Default: me (authenticated user).',
					],
				];

			case 'update_meeting':
				return [
					[
						'key'         => 'meeting_id',
						'type'        => 'text',
						'label'       => 'Meeting ID',
						'placeholder' => '{{zoom_meeting_id}}',
						'required'    => true,
						'help'        => 'Use {{zoom_meeting_id}} from a Create Meeting node.',
					],
					[
						'key'         => 'topic',
						'type'        => 'text',
						'label'       => 'New Topic',
						'placeholder' => 'Updated title',
						'required'    => false,
					],
					array_merge( $start_field, [
						'required' => false,
						'help'     => 'ISO 8601 format. Leave empty to keep existing.',
					] ),
					[
						'key'         => 'duration',
						'type'        => 'text',
						'label'       => 'New Duration (minutes)',
						'placeholder' => '60',
						'required'    => false,
					],
					$timezone_field,
					[
						'key'         => 'agenda',
						'type'        => 'textarea',
						'label'       => 'New Agenda',
						'placeholder' => 'Updated agenda...',
						'required'    => false,
					],
					[
						'key'         => 'password',
						'type'        => 'text',
						'label'       => 'New Passcode',
						'placeholder' => 'newpasscode',
						'required'    => false,
					],
				];

			case 'delete_meeting':
				return [
					[
						'key'         => 'meeting_id',
						'type'        => 'text',
						'label'       => 'Meeting ID',
						'placeholder' => '{{zoom_meeting_id}}',
						'required'    => true,
						'help'        => 'Numeric Zoom meeting ID to delete.',
					],
					[
						'key'      => 'notify_registrants',
						'type'     => 'select',
						'label'    => 'Notify Registrants',
						'required' => false,
						'options'  => [
							[
								'value' => 'false',
								'label' => 'No'
							],
							[
								'value' => 'true',
								'label' => 'Yes — send cancellation email'
							],
						],
					],
				];

			case 'add_registrant':
				return [
					[
						'key'         => 'meeting_id',
						'type'        => 'text',
						'label'       => 'Meeting ID',
						'placeholder' => '{{zoom_meeting_id}}',
						'required'    => true,
					],
					[
						'key'         => 'email',
						'type'        => 'email',
						'label'       => 'Email',
						'placeholder' => 'attendee@example.com',
						'required'    => true,
					],
					[
						'key'         => 'first_name',
						'type'        => 'text',
						'label'       => 'First Name',
						'placeholder' => 'Alice',
						'required'    => true,
					],
					[
						'key'         => 'last_name',
						'type'        => 'text',
						'label'       => 'Last Name',
						'placeholder' => 'Smith',
						'required'    => false,
					],
					[
						'key'         => 'org',
						'type'        => 'text',
						'label'       => 'Organisation',
						'placeholder' => 'Acme Corp',
						'required'    => false,
					],
					[
						'key'         => 'job_title',
						'type'        => 'text',
						'label'       => 'Job Title',
						'placeholder' => 'Marketing Manager',
						'required'    => false,
					],
				];
		}//end switch

		return [];
	}

	public static function resolve_trigger( array $node, array $args ): array {
		$payload = $args[0] ?? [];
		$object  = $payload['payload']['object'] ?? [];

		$base = [
			'zoom_meeting_id' => (string) ( $object['id'] ?? '' ),
			'zoom_topic'      => $object['topic'] ?? '',
			'zoom_host_id'    => $object['host_id'] ?? '',
			'zoom_start_time' => $object['start_time'] ?? '',
			'zoom_end_time'   => $object['end_time'] ?? '',
			'zoom_duration'   => $object['duration'] ?? '',
			'zoom_uuid'       => $object['uuid'] ?? '',
		];

		$event = $node['data']['event'] ?? '';

		if ( 'participant_joined' === $event ) {
			$participant = $object['participant'] ?? [];

			$base['zoom_participant_id']    = $participant['user_id'] ?? '';
			$base['zoom_participant_name']  = $participant['user_name'] ?? '';
			$base['zoom_participant_email'] = $participant['email'] ?? '';
			$base['zoom_join_time']         = $participant['join_time'] ?? '';
		}

		return $base;
	}

	public static function get_trigger_sample_output( string $event ): array {
		$base = [
			'zoom_meeting_id' => '89012345678',
			'zoom_topic'      => 'Weekly Team Standup',
			'zoom_host_id'    => 'u8Kx3vT2Rn2h9abcDEfghi',
			'zoom_start_time' => '2026-07-10T10:00:00Z',
			'zoom_end_time'   => '2026-07-10T10:45:00Z',
			'zoom_duration'   => 45,
			'zoom_uuid'       => 'aB1cD2eF3gH4iJ5kL6mN7w==',
		];

		$participant = array_merge(
			$base,
			[
				'zoom_participant_id'    => '16778240',
				'zoom_participant_name'  => 'Jane Doe',
				'zoom_participant_email' => 'jane.doe@example.com',
				'zoom_join_time'         => '2026-07-10T10:02:15Z',
			]
		);

		$samples = [
			'meeting_started'    => $base,
			'meeting_ended'      => $base,
			'participant_joined' => $participant,
		];

		if ( isset( $samples[ $event ] ) ) {
			return $samples[ $event ];
		}

		if ( false !== strpos( $event, 'participant' ) ) {
			return $participant;
		}

		return $base;
	}

	public static function execute_node( array $node, array $input ): array {
		$action      = $node['data']['event'] ?? '';
		$credentials = $node['_connection_credentials'] ?? null;

		if ( ! $credentials ) {
			throw new \Exception( 'Zoom: no connection credentials found.' );
		}

		$token = $credentials['access_token'] ?? '';

		if ( empty( $token ) ) {
			throw new \Exception( 'Zoom: access_token is missing.' );
		}

		switch ( $action ) {
			case 'create_meeting':
				return self::action_create_meeting( $node, $input, $token );
			case 'update_meeting':
				return self::action_update_meeting( $node, $input, $token );
			case 'delete_meeting':
				return self::action_delete_meeting( $node, $input, $token );
			case 'add_registrant':
				return self::action_add_registrant( $node, $input, $token );
			default:
				throw new \Exception( 'Zoom: unknown action "' . esc_html( $action ) . '".' );
		}
	}

	public static function requires_connection(): bool {
		return true;
	}

	public static function get_auth_type(): string {
		return 'oauth2';
	}

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'client_id'     => [
				'type'        => 'text',
				'label'       => 'Client ID',
				'placeholder' => 'Your Zoom app Client ID',
				'required'    => true,
				'help'        => 'Zoom Marketplace → Your App → App Credentials.',
			],
			'client_secret' => [
				'type'        => 'password',
				'label'       => 'Client Secret',
				'placeholder' => 'Your Zoom app Client Secret',
				'required'    => true,
				'help'        => 'Found alongside the Client ID.',
			],
		];
	}

	public static function get_oauth_scopes(): array {
		return [
			'meeting:write:admin',
			'meeting:write',
			'meeting:read:admin',
			'meeting:read',
			'user:read:admin',
			'user:read',
		];
	}

	public static function get_oauth_auth_url( string $redirect_uri, string $state, array $credentials = [] ): ?string {
		$client_id = $credentials['client_id'] ?? '';

		if ( empty( $client_id ) ) {
			return null;
		}

		return self::OAUTH_AUTH_URL . '?' . http_build_query( [
			'response_type' => 'code',
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'state'         => $state,
		] );
	}

	public static function exchange_oauth_code( string $code, string $redirect_uri, array $credentials = [] ): array {
		$client_id     = $credentials['client_id'] ?? '';
		$client_secret = $credentials['client_secret'] ?? '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			throw new \Exception( 'Client ID and Client Secret are required for OAuth token exchange.' );
		}

		$response = wp_remote_post( self::OAUTH_TOKEN_URL, [
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			],
			'body' => [
				'grant_type'   => 'authorization_code',
				'code'         => $code,
				'redirect_uri' => $redirect_uri,
			],
		] );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'OAuth token exchange failed: ' . esc_html( $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			throw new \Exception( 'Zoom OAuth error: ' . esc_html( $body['reason'] ?? $body['error'] ) );
		}

		return [
			'access_token'  => $body['access_token'] ?? '',
			'refresh_token' => $body['refresh_token'] ?? '',
			'expires_in'    => $body['expires_in'] ?? 3600,
			'token_type'    => $body['token_type'] ?? 'bearer',
			'scope'         => $body['scope'] ?? '',
		];
	}

	public static function refresh_oauth_token( array $credentials ): array {
		$refresh_token = $credentials['refresh_token'] ?? '';
		$client_id     = $credentials['client_id'] ?? '';
		$client_secret = $credentials['client_secret'] ?? '';

		if ( empty( $refresh_token ) ) {
			throw new \Exception( 'Zoom: no refresh_token available. Please reconnect.' );
		}

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			throw new \Exception( 'Zoom: client_id / client_secret missing for token refresh.' );
		}

		$response = wp_remote_post( self::OAUTH_TOKEN_URL, [
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			],
			'body' => [
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refresh_token,
			],
		] );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Zoom token refresh failed: ' . esc_html( $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			throw new \Exception( 'Zoom token refresh error: ' . esc_html( $body['reason'] ?? $body['error'] ) );
		}

		return [
			'access_token' => $body['access_token'],
			'expires_in'   => $body['expires_in'] ?? 3600,
		];
	}

	public static function test_connection( array $credentials ): array {
		$token = $credentials['access_token'] ?? '';

		if ( empty( $token ) ) {
			return [
				'success' => false,
				'message' => 'access_token is missing.',
				'details' => []
			];
		}

		$response = wp_remote_get( self::API_BASE_URL . '/users/me', [
			'headers' => [ 'Authorization' => 'Bearer ' . $token ],
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => 'Connection test failed: ' . $response->get_error_message(),
				'details' => []
			];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['code'] ) && (int) $body['code'] !== 0 ) {
			return [
				'success' => false,
				'message' => $body['message'] ?? 'Unknown Zoom API error',
				'details' => []
			];
		}

		$email = $body['email'] ?? '';

		return [
			'success' => true,
			'message' => 'Connected as ' . $email,
			'details' => [
				'id'         => $body['id'] ?? '',
				'email'      => $email,
				'first_name' => $body['first_name'] ?? '',
				'last_name'  => $body['last_name'] ?? '',
				'type'       => $body['type'] ?? '',
			],
		];
	}

	public static function supports_webhook(): bool {
		return true;
	}

	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		$secret    = get_option( 'zaplane_zoom_webhook_secret', '' );
		$timestamp = $request->get_header( 'x-zm-request-timestamp' );
		$signature = $request->get_header( 'x-zm-signature' );

		if ( empty( $secret ) || ! $timestamp || ! $signature ) {
			return true; // skip verification if secret not configured
		}

		if ( abs( time() - (int) $timestamp ) > 300 ) {
			return false;
		}

		$message  = 'v0:' . $timestamp . ':' . $request->get_body();
		$expected = 'v0=' . hash_hmac( 'sha256', $message, $secret );

		return hash_equals( $expected, $signature );
	}

	public static function parse_webhook_event( \WP_REST_Request $request ): ?array {
		$body  = $request->get_json_params();
		$event = $body['event'] ?? '';

		$map = [
			'meeting.started'           => 'meeting_started',
			'meeting.ended'             => 'meeting_ended',
			'meeting.participant_joined' => 'participant_joined',
		];

		$normalized = $map[ $event ] ?? null;

		if ( ! $normalized ) {
			return null;
		}

		return [
			'event'   => $normalized,
			'payload' => $body['payload'] ?? [],
		];
	}

	private static function action_create_meeting( array $node, array $input, string $token ): array {
		$config     = $node['data']['config'] ?? [];
		$topic      = trim( $config['topic'] ?? '' );
		$start_time = trim( $config['start_time'] ?? '' );

		if ( empty( $topic ) ) {
			throw new \Exception( 'Zoom: Meeting Topic is required.' );
		}

		if ( empty( $start_time ) ) {
			throw new \Exception( 'Zoom: Start Time is required.' );
		}

		if ( ! self::is_valid_datetime( $start_time ) ) {
			throw new \Exception(
				'Zoom: Start Time must be ISO 8601 format (e.g. 2026-06-10T10:00:00). You provided: "' . $start_time . '".'
			);
		}

		$user_id = ! empty( $config['user_id'] ) ? $config['user_id'] : 'me';

		$body = [
			'topic'      => $topic,
			'type'       => 2, // Scheduled meeting
			'start_time' => $start_time,
			'duration'   => (int) ( $config['duration'] ?? 60 ),
			'timezone'   => ! empty( $config['timezone'] ) ? $config['timezone'] : 'UTC',
			'settings'   => [
				'host_video'        => ( $config['host_video'] ?? 'true' ) === 'true',
				'participant_video' => true,
				'waiting_room'      => ( $config['waiting_room'] ?? 'false' ) === 'true',
				'join_before_host'  => false,
				'mute_upon_entry'   => false,
			],
		];

		if ( ! empty( $config['agenda'] ) ) {
			$body['agenda'] = $config['agenda'];
		}

		if ( ! empty( $config['password'] ) ) {
			$body['password'] = $config['password'];
		}

		$data = self::zoom_request( $token, 'POST', '/users/' . rawurlencode( $user_id ) . '/meetings', $body );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'zoom_meeting_id' => (string) ( $data['id'] ?? '' ),
				'zoom_topic'      => $data['topic'] ?? '',
				'zoom_join_url'   => $data['join_url'] ?? '',
				'zoom_start_url'  => $data['start_url'] ?? '',
				'zoom_password'   => $data['password'] ?? '',
				'zoom_start_time' => $data['start_time'] ?? '',
				'zoom_duration'   => $data['duration'] ?? '',
				'zoom_timezone'   => $data['timezone'] ?? '',
			] ),
		];
	}

	private static function action_update_meeting( array $node, array $input, string $token ): array {
		$config     = $node['data']['config'] ?? [];
		$meeting_id = trim( $config['meeting_id'] ?? '' );

		if ( empty( $meeting_id ) ) {
			throw new \Exception( 'Zoom: Meeting ID is required to update a meeting.' );
		}

		$body = [];

		if ( ! empty( $config['topic'] ) ) {
			$body['topic'] = $config['topic'];
		}

		if ( ! empty( $config['start_time'] ) ) {
			$start_time = trim( $config['start_time'] );
			if ( ! self::is_valid_datetime( $start_time ) ) {
				throw new \Exception( 'Zoom: New Start Time must be ISO 8601 format (e.g. 2026-06-10T10:00:00).' );
			}
			$body['start_time'] = $start_time;
		}

		if ( ! empty( $config['duration'] ) ) {
			$body['duration'] = (int) $config['duration'];
		}

		if ( ! empty( $config['timezone'] ) ) {
			$body['timezone'] = $config['timezone'];
		}

		if ( ! empty( $config['agenda'] ) ) {
			$body['agenda'] = $config['agenda'];
		}

		if ( ! empty( $config['password'] ) ) {
			$body['password'] = $config['password'];
		}

		if ( empty( $body ) ) {
			throw new \Exception( 'Zoom: No fields provided to update.' );
		}

		self::zoom_request( $token, 'PATCH', '/meetings/' . rawurlencode( $meeting_id ), $body );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'zoom_meeting_id' => $meeting_id,
				'zoom_updated'    => true,
			] ),
		];
	}

	private static function action_delete_meeting( array $node, array $input, string $token ): array {
		$config             = $node['data']['config'] ?? [];
		$meeting_id         = trim( $config['meeting_id'] ?? '' );
		$notify_registrants = ( $config['notify_registrants'] ?? 'false' ) === 'true';

		if ( empty( $meeting_id ) ) {
			throw new \Exception( 'Zoom: Meeting ID is required to delete a meeting.' );
		}

		$endpoint = '/meetings/' . rawurlencode( $meeting_id );

		if ( $notify_registrants ) {
			$endpoint .= '?notify_registrants=true';
		}

		self::zoom_request( $token, 'DELETE', $endpoint );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'zoom_meeting_id' => $meeting_id,
				'zoom_deleted'    => true,
			] ),
		];
	}

	private static function action_add_registrant( array $node, array $input, string $token ): array {
		$config     = $node['data']['config'] ?? [];
		$meeting_id = trim( $config['meeting_id'] ?? '' );
		$email      = trim( $config['email'] ?? '' );
		$first_name = trim( $config['first_name'] ?? '' );

		if ( empty( $meeting_id ) ) {
			throw new \Exception( 'Zoom: Meeting ID is required to add a registrant.' );
		}

		if ( empty( $email ) ) {
			throw new \Exception( 'Zoom: Email is required to add a registrant.' );
		}

		if ( ! is_email( $email ) ) {
			throw new \Exception( 'Zoom: "' . $email . '" is not a valid email address.' );
		}

		if ( empty( $first_name ) ) {
			throw new \Exception( 'Zoom: First Name is required to add a registrant.' );
		}

		$body = [
			'email'      => $email,
			'first_name' => $first_name,
		];

		if ( ! empty( $config['last_name'] ) ) {
			$body['last_name'] = $config['last_name'];
		}

		if ( ! empty( $config['org'] ) ) {
			$body['org'] = $config['org'];
		}

		if ( ! empty( $config['job_title'] ) ) {
			$body['job_title'] = $config['job_title'];
		}

		$data = self::zoom_request( $token, 'POST', '/meetings/' . rawurlencode( $meeting_id ) . '/registrants', $body );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'zoom_registrant_id' => $data['registrant_id'] ?? '',
				'zoom_join_url'      => $data['join_url'] ?? '',
				'zoom_meeting_id'    => (string) ( $data['id'] ?? $meeting_id ),
				'zoom_topic'         => $data['topic'] ?? '',
				'zoom_start_time'    => $data['start_time'] ?? '',
			] ),
		];
	}

	private static function is_valid_datetime( string $dt ): bool {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $dt );
	}

	private static function get_timezone_options(): array {
		return [
			[
				'value' => 'UTC',
				'label' => 'UTC'
			],
			[
				'value' => 'Asia/Dhaka',
				'label' => 'Asia/Dhaka (BD, UTC+6)'
			],
			[
				'value' => 'Asia/Kolkata',
				'label' => 'Asia/Kolkata (IST, UTC+5:30)'
			],
			[
				'value' => 'Asia/Karachi',
				'label' => 'Asia/Karachi (PKT, UTC+5)'
			],
			[
				'value' => 'Asia/Dubai',
				'label' => 'Asia/Dubai (GST, UTC+4)'
			],
			[
				'value' => 'Asia/Riyadh',
				'label' => 'Asia/Riyadh (AST, UTC+3)'
			],
			[
				'value' => 'Europe/Istanbul',
				'label' => 'Europe/Istanbul (TRT, UTC+3)'
			],
			[
				'value' => 'Europe/Moscow',
				'label' => 'Europe/Moscow (MSK, UTC+3)'
			],
			[
				'value' => 'Europe/Berlin',
				'label' => 'Europe/Berlin (CET, UTC+1)'
			],
			[
				'value' => 'Europe/London',
				'label' => 'Europe/London (GMT, UTC+0)'
			],
			[
				'value' => 'America/New_York',
				'label' => 'America/New_York (EST, UTC-5)'
			],
			[
				'value' => 'America/Chicago',
				'label' => 'America/Chicago (CST, UTC-6)'
			],
			[
				'value' => 'America/Denver',
				'label' => 'America/Denver (MST, UTC-7)'
			],
			[
				'value' => 'America/Los_Angeles',
				'label' => 'America/Los_Angeles (PST, UTC-8)'
			],
			[
				'value' => 'America/Sao_Paulo',
				'label' => 'America/Sao_Paulo (BRT, UTC-3)'
			],
			[
				'value' => 'Asia/Singapore',
				'label' => 'Asia/Singapore (SGT, UTC+8)'
			],
			[
				'value' => 'Asia/Tokyo',
				'label' => 'Asia/Tokyo (JST, UTC+9)'
			],
			[
				'value' => 'Australia/Sydney',
				'label' => 'Australia/Sydney (AEDT, UTC+11)'
			],
		];
	}

	private static function zoom_request(
		string $token,
		string $method,
		string $endpoint,
		array $body = []
	): array {
		$url  = self::API_BASE_URL . $endpoint;
		$args = [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'timeout' => 30,
		];

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		switch ( strtoupper( $method ) ) {
			case 'GET':
				$response = wp_remote_get( $url, $args );
				break;
			case 'POST':
				$response = wp_remote_post( $url, $args );
				break;
			case 'PATCH':
			case 'DELETE':
				$args['method'] = strtoupper( $method );
				$response       = wp_remote_request( $url, $args );
				break;
			default:
				throw new \Exception( 'Zoom: unsupported HTTP method "' . esc_html( $method ) . '".' );
		}

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Zoom API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body  = wp_remote_retrieve_body( $response );

		if ( 204 === $http_code ) {
			return [];
		}

		$data = json_decode( $raw_body, true ) ?? [];

		if ( isset( $data['code'] ) && (int) $data['code'] !== 0 ) {
			throw new \Exception(
				sprintf( 'Zoom API error [%d]: %s', (int) $data['code'], esc_html( $data['message'] ?? 'Unknown error' ) )
			);
		}

		if ( $http_code < 200 || $http_code >= 300 ) {
			throw new \Exception(
				sprintf( 'Zoom API returned HTTP %d: %s', $http_code, esc_html( wp_strip_all_tags( $raw_body ) ) )
			);
		}

		return $data;
	}
}
