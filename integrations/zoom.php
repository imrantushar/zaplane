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

	public static function get_triggers(): array {
		return [
			'meeting_started'   => [
				'label' => 'Meeting Started',
				'hook'  => 'zoom_webhook_meeting_started',
			],
			'meeting_ended'     => [
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
		if ( 'create_meeting' === $action ) {
			return [
				[
					'key'         => 'topic',
					'type'        => 'text',
					'label'       => 'Meeting Topic',
					'placeholder' => 'Team Standup',
					'required'    => true,
				],
				[
					'key'         => 'start_time',
					'type'        => 'text',
					'label'       => 'Start Time',
					'placeholder' => '2026-05-01T10:00:00',
					'required'    => true,
					'help'        => 'ISO 8601 format: YYYY-MM-DDTHH:MM:SS (local time in the chosen timezone).',
				],
				[
					'key'         => 'duration',
					'type'        => 'text',
					'label'       => 'Duration (minutes)',
					'placeholder' => '60',
					'required'    => false,
					'help'        => 'Meeting length in minutes. Default: 60.',
				],
				[
					'key'         => 'timezone',
					'type'        => 'text',
					'label'       => 'Timezone',
					'placeholder' => 'UTC',
					'required'    => false,
					'help'        => 'IANA timezone name (e.g. America/New_York). Default: UTC.',
				],
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
						[ 'value' => 'true',  'label' => 'Enabled' ],
						[ 'value' => 'false', 'label' => 'Disabled' ],
					],
				],
				[
					'key'      => 'host_video',
					'type'     => 'select',
					'label'    => 'Start with Host Video On',
					'required' => false,
					'options'  => [
						[ 'value' => 'true',  'label' => 'Yes' ],
						[ 'value' => 'false', 'label' => 'No' ],
					],
				],
				[
					'key'         => 'user_id',
					'type'        => 'text',
					'label'       => 'Host User ID / Email',
					'placeholder' => 'me',
					'required'    => false,
					'help'        => 'Zoom user ID or email to create the meeting under. Default: me (authenticated user).',
				],
			];
		}

		if ( 'update_meeting' === $action ) {
			return [
				[
					'key'         => 'meeting_id',
					'type'        => 'text',
					'label'       => 'Meeting ID',
					'placeholder' => '{{zoom_meeting_id}}',
					'required'    => true,
					'help'        => 'Numeric Zoom meeting ID. Use {{zoom_meeting_id}} from a Create Meeting node.',
				],
				[
					'key'         => 'topic',
					'type'        => 'text',
					'label'       => 'New Topic',
					'placeholder' => 'Updated title',
					'required'    => false,
				],
				[
					'key'         => 'start_time',
					'type'        => 'text',
					'label'       => 'New Start Time',
					'placeholder' => '2026-05-01T10:00:00',
					'required'    => false,
					'help'        => 'ISO 8601 format. Leave empty to keep existing.',
				],
				[
					'key'         => 'duration',
					'type'        => 'text',
					'label'       => 'New Duration (minutes)',
					'placeholder' => '60',
					'required'    => false,
				],
				[
					'key'         => 'timezone',
					'type'        => 'text',
					'label'       => 'Timezone',
					'placeholder' => 'UTC',
					'required'    => false,
				],
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
		}

		if ( 'delete_meeting' === $action ) {
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
						[ 'value' => 'true',  'label' => 'Yes — send cancellation email' ],
						[ 'value' => 'false', 'label' => 'No' ],
					],
				],
			];
		}

		if ( 'add_registrant' === $action ) {
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
					'type'        => 'text',
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
		}

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

	public static function execute_node( array $node, array $input ): array {
		$action      = $node['data']['event'] ?? '';
		$credentials = $node['_connection_credentials'] ?? null;

		if ( ! $credentials ) {
			throw new \Exception( 'No connection credentials available for Zoom' );
		}

		$token = $credentials['access_token'] ?? '';

		if ( empty( $token ) ) {
			throw new \Exception( 'Zoom credentials (access_token) are required' );
		}

		if ( 'create_meeting' === $action ) {
			return self::action_create_meeting( $node, $input, $token );
		}

		if ( 'update_meeting' === $action ) {
			return self::action_update_meeting( $node, $input, $token );
		}

		if ( 'delete_meeting' === $action ) {
			return self::action_delete_meeting( $node, $input, $token );
		}

		if ( 'add_registrant' === $action ) {
			return self::action_add_registrant( $node, $input, $token );
		}

		return [
			'port' => 'main',
			'data' => $input,
		];
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
				'help'        => 'From Zoom Marketplace → Your App → App Credentials.',
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
			throw new \Exception( 'Client ID and Client Secret are required for OAuth token exchange' );
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

	public static function test_connection( array $credentials ): array {
		$token = $credentials['access_token'] ?? '';

		if ( empty( $token ) ) {
			return [
				'success' => false,
				'message' => 'access_token is required',
				'details' => [],
			];
		}

		$response = wp_remote_get( self::API_BASE_URL . '/users/me', [
			'headers' => [ 'Authorization' => 'Bearer ' . $token ],
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => 'Connection test failed: ' . $response->get_error_message(),
				'details' => [],
			];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['code'] ) && $body['code'] !== 0 ) {
			return [
				'success' => false,
				'message' => $body['message'] ?? 'Unknown Zoom API error',
				'details' => [],
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

	// ── Private action helpers ────────────────────────────────────────────────

	private static function action_create_meeting( array $node, array $input, string $token ): array {
		$config     = $node['data']['config'] ?? [];
		$topic      = $config['topic'] ?? '';
		$start_time = $config['start_time'] ?? '';

		if ( empty( $topic ) ) {
			throw new \Exception( 'Zoom: meeting topic is required' );
		}

		if ( empty( $start_time ) ) {
			throw new \Exception( 'Zoom: start time is required' );
		}

		$user_id = $config['user_id'] ?? 'me';

		$body = [
			'topic'      => $topic,
			'type'       => 2, // Scheduled meeting
			'start_time' => $start_time,
			'duration'   => (int) ( $config['duration'] ?? 60 ),
			'timezone'   => $config['timezone'] ?? 'UTC',
			'settings'   => [
				'host_video'         => ( $config['host_video'] ?? 'true' ) === 'true',
				'participant_video'  => true,
				'waiting_room'       => ( $config['waiting_room'] ?? 'false' ) === 'true',
				'join_before_host'   => false,
				'mute_upon_entry'    => false,
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
				'zoom_meeting_id' => $data['id'] ?? '',
				'zoom_topic'      => $data['topic'] ?? '',
				'zoom_join_url'   => $data['join_url'] ?? '',
				'zoom_start_url'  => $data['start_url'] ?? '',
				'zoom_password'   => $data['password'] ?? '',
				'zoom_start_time' => $data['start_time'] ?? '',
				'zoom_duration'   => $data['duration'] ?? '',
			] ),
		];
	}

	private static function action_update_meeting( array $node, array $input, string $token ): array {
		$config     = $node['data']['config'] ?? [];
		$meeting_id = $config['meeting_id'] ?? '';

		if ( empty( $meeting_id ) ) {
			throw new \Exception( 'Zoom: meeting_id is required to update a meeting' );
		}

		$body = [];

		if ( ! empty( $config['topic'] ) ) {
			$body['topic'] = $config['topic'];
		}

		if ( ! empty( $config['start_time'] ) ) {
			$body['start_time'] = $config['start_time'];
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

		// PATCH returns 204 No Content — no body to parse
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
		$meeting_id         = $config['meeting_id'] ?? '';
		$notify_registrants = ( $config['notify_registrants'] ?? 'false' ) === 'true';

		if ( empty( $meeting_id ) ) {
			throw new \Exception( 'Zoom: meeting_id is required to delete a meeting' );
		}

		$endpoint = '/meetings/' . rawurlencode( $meeting_id );

		if ( $notify_registrants ) {
			$endpoint .= '?notify_registrants=true';
		}

		// DELETE returns 204 No Content
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
		$meeting_id = $config['meeting_id'] ?? '';
		$email      = $config['email'] ?? '';
		$first_name = $config['first_name'] ?? '';

		if ( empty( $meeting_id ) ) {
			throw new \Exception( 'Zoom: meeting_id is required to add a registrant' );
		}

		if ( empty( $email ) ) {
			throw new \Exception( 'Zoom: email is required to add a registrant' );
		}

		if ( empty( $first_name ) ) {
			throw new \Exception( 'Zoom: first name is required to add a registrant' );
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

	// ── Internal utility ─────────────────────────────────────────────────────

	/**
	 * Make an authenticated request to the Zoom REST API.
	 *
	 * @throws \Exception on WP_Error or API error response.
	 */
	private static function zoom_request( string $token, string $method, string $endpoint, array $body = [] ): array {
		$url  = self::API_BASE_URL . $endpoint;
		$args = [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
		];

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		if ( 'GET' === $method ) {
			$response = wp_remote_get( $url, $args );
		} elseif ( 'DELETE' === $method ) {
			$args['method'] = 'DELETE';
			$response = wp_remote_request( $url, $args );
		} elseif ( 'PATCH' === $method ) {
			$args['method'] = 'PATCH';
			$response = wp_remote_request( $url, $args );
		} else {
			$response = wp_remote_post( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Zoom API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		// Zoom signals errors with a non-zero `code` field
		if ( isset( $data['code'] ) && (int) $data['code'] !== 0 ) {
			throw new \Exception( 'Zoom API error: ' . esc_html( $data['message'] ?? 'Unknown error' ) );
		}

		return $data;
	}
}
