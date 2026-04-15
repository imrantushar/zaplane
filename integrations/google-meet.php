<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoogleMeet extends IntegrationBase {

	private const CALENDAR_API_URL = 'https://www.googleapis.com/calendar/v3';
	private const MEET_API_URL     = 'https://meet.googleapis.com/v2';
	private const USERINFO_URL     = 'https://www.googleapis.com/oauth2/v1/userinfo';

	public static function get_slug(): string {
		return 'google-meet';
	}

	public static function get_name(): string {
		return 'Google Meet';
	}

	public static function get_icon(): string {
		return 'google-meet.svg';
	}

	public static function get_triggers(): array {
		return [];
	}

	public static function get_actions(): array {
		return [
			'create_meeting' => [ 'label' => 'Create Meeting' ],
			'update_meeting' => [ 'label' => 'Update Meeting' ],
			'cancel_meeting' => [ 'label' => 'Cancel Meeting' ],
			'create_space'   => [ 'label' => 'Create Instant Space' ],
			'end_conference' => [ 'label' => 'End Active Conference' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$calendar_field = [
			'key'      => 'calendar_id',
			'type'     => 'text',
			'label'    => 'Calendar',
			'required' => false,
			'help'     => 'Calendar ID to use (default: primary). Pick from the dynamic dropdown.',
			'dynamic'  => [
				'integration' => 'google-meet',
				'query'       => 'google_calendars',
				'select'      => [ 'value', 'label' ],
			],
		];

		if ( 'create_meeting' === $action ) {
			return [
				[
					'key'         => 'summary',
					'type'        => 'text',
					'label'       => 'Meeting Title',
					'placeholder' => 'Team Standup',
					'required'    => true,
				],
				[
					'key'         => 'description',
					'type'        => 'textarea',
					'label'       => 'Description',
					'placeholder' => 'Meeting agenda or notes...',
					'required'    => false,
				],
				[
					'key'         => 'start_datetime',
					'type'        => 'text',
					'label'       => 'Start Date & Time',
					'placeholder' => '2026-05-01T10:00:00',
					'required'    => true,
					'help'        => 'ISO 8601 format: YYYY-MM-DDTHH:MM:SS (e.g. 2026-05-01T10:00:00)',
				],
				[
					'key'         => 'end_datetime',
					'type'        => 'text',
					'label'       => 'End Date & Time',
					'placeholder' => '2026-05-01T11:00:00',
					'required'    => true,
					'help'        => 'ISO 8601 format: YYYY-MM-DDTHH:MM:SS',
				],
				[
					'key'         => 'timezone',
					'type'        => 'text',
					'label'       => 'Timezone',
					'placeholder' => 'UTC',
					'required'    => false,
					'help'        => 'IANA timezone name (e.g. America/New_York, Europe/London). Defaults to UTC.',
				],
				[
					'key'         => 'attendees',
					'type'        => 'text',
					'label'       => 'Attendees',
					'placeholder' => 'alice@example.com,bob@example.com',
					'required'    => false,
					'help'        => 'Comma-separated email addresses. Attendees will receive a calendar invite.',
				],
				[
					'key'      => 'send_notifications',
					'type'     => 'select',
					'label'    => 'Send Invites to Attendees',
					'required' => false,
					'options'  => [
						[ 'value' => 'true',  'label' => 'Yes' ],
						[ 'value' => 'false', 'label' => 'No' ],
					],
				],
				$calendar_field,
			];
		}

		if ( 'update_meeting' === $action ) {
			return [
				[
					'key'         => 'event_id',
					'type'        => 'text',
					'label'       => 'Event ID',
					'placeholder' => '{{google_event_id}}',
					'required'    => true,
					'help'        => 'The Google Calendar event ID. Use {{google_event_id}} from a Create Meeting node.',
				],
				[
					'key'         => 'summary',
					'type'        => 'text',
					'label'       => 'New Title',
					'placeholder' => 'Updated Meeting Title',
					'required'    => false,
				],
				[
					'key'         => 'description',
					'type'        => 'textarea',
					'label'       => 'New Description',
					'placeholder' => 'Updated agenda...',
					'required'    => false,
				],
				[
					'key'         => 'start_datetime',
					'type'        => 'text',
					'label'       => 'New Start Date & Time',
					'placeholder' => '2026-05-01T10:00:00',
					'required'    => false,
					'help'        => 'ISO 8601 format. Leave empty to keep existing.',
				],
				[
					'key'         => 'end_datetime',
					'type'        => 'text',
					'label'       => 'New End Date & Time',
					'placeholder' => '2026-05-01T11:00:00',
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
					'key'         => 'attendees',
					'type'        => 'text',
					'label'       => 'Attendees',
					'placeholder' => 'alice@example.com,bob@example.com',
					'required'    => false,
					'help'        => 'Replaces the existing attendee list.',
				],
				$calendar_field,
			];
		}

		if ( 'cancel_meeting' === $action ) {
			return [
				[
					'key'         => 'event_id',
					'type'        => 'text',
					'label'       => 'Event ID',
					'placeholder' => '{{google_event_id}}',
					'required'    => true,
					'help'        => 'The Google Calendar event ID to cancel.',
				],
				[
					'key'      => 'send_notifications',
					'type'     => 'select',
					'label'    => 'Notify Attendees',
					'required' => false,
					'options'  => [
						[ 'value' => 'true',  'label' => 'Yes — send cancellation emails' ],
						[ 'value' => 'false', 'label' => 'No' ],
					],
				],
				$calendar_field,
			];
		}

		if ( 'create_space' === $action ) {
			return [
				[
					'key'      => 'access_type',
					'type'     => 'select',
					'label'    => 'Access Type',
					'required' => false,
					'options'  => [
						[ 'value' => '',             'label' => 'Default' ],
						[ 'value' => 'OPEN',         'label' => 'Open — anyone with the link' ],
						[ 'value' => 'TRUSTED',      'label' => 'Trusted — organisation members' ],
						[ 'value' => 'RESTRICTED',   'label' => 'Restricted — invited members only' ],
					],
					'help' => 'Who can join this Meet space.',
				],
			];
		}

		if ( 'end_conference' === $action ) {
			return [
				[
					'key'         => 'space_name',
					'type'        => 'text',
					'label'       => 'Space Name',
					'placeholder' => '{{google_space_name}}',
					'required'    => true,
					'help'        => 'The Meet space resource name (e.g. spaces/jQCFfuBOdKE). Use {{google_space_name}} from a Create Space node.',
				],
			];
		}

		return [];
	}

	public static function resolve_trigger( array $node, array $args ): array {
		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$action      = $node['data']['event'] ?? '';
		$credentials = $node['_connection_credentials'] ?? null;

		if ( ! $credentials ) {
			throw new \Exception( 'No connection credentials available for Google Meet' );
		}

		$token = $credentials['access_token'] ?? '';

		if ( empty( $token ) ) {
			throw new \Exception( 'Google Meet credentials (access_token) are required' );
		}

		if ( 'create_meeting' === $action ) {
			return self::action_create_meeting( $node, $input, $token );
		}

		if ( 'update_meeting' === $action ) {
			return self::action_update_meeting( $node, $input, $token );
		}

		if ( 'cancel_meeting' === $action ) {
			return self::action_cancel_meeting( $node, $input, $token );
		}

		if ( 'create_space' === $action ) {
			return self::action_create_space( $node, $input, $token );
		}

		if ( 'end_conference' === $action ) {
			return self::action_end_conference( $node, $input, $token );
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
				'placeholder' => 'xxxx.apps.googleusercontent.com',
				'required'    => true,
				'help'        => 'From Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 Client IDs.',
			],
			'client_secret' => [
				'type'        => 'password',
				'label'       => 'Client Secret',
				'placeholder' => 'GOCSPX-xxxx',
				'required'    => true,
				'help'        => 'Found alongside the Client ID in Google Cloud Console.',
			],
		];
	}

	public static function get_oauth_scopes(): array {
		return [
			'https://www.googleapis.com/auth/calendar',
			'https://www.googleapis.com/auth/calendar.events',
			'https://www.googleapis.com/auth/meetings.space.created',
		];
	}

	public static function get_oauth_auth_url( string $redirect_uri, string $state, array $credentials = [] ): ?string {
		$client_id = $credentials['client_id'] ?? '';

		if ( empty( $client_id ) ) {
			return null;
		}

		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( [
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => implode( ' ', self::get_oauth_scopes() ),
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		] );
	}

	public static function exchange_oauth_code( string $code, string $redirect_uri, array $credentials = [] ): array {
		$client_id     = $credentials['client_id'] ?? '';
		$client_secret = $credentials['client_secret'] ?? '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			throw new \Exception( 'Client ID and Client Secret are required for OAuth token exchange' );
		}

		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
			'body' => [
				'code'          => $code,
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'redirect_uri'  => $redirect_uri,
				'grant_type'    => 'authorization_code',
			],
		] );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'OAuth token exchange failed: ' . esc_html( $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			throw new \Exception( 'Google OAuth error: ' . esc_html( $body['error_description'] ?? $body['error'] ) );
		}

		return [
			'access_token'  => $body['access_token'] ?? '',
			'refresh_token' => $body['refresh_token'] ?? '',
			'expires_in'    => $body['expires_in'] ?? 3600,
			'token_type'    => $body['token_type'] ?? 'Bearer',
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

		$response = wp_remote_get( self::USERINFO_URL, [
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

		if ( isset( $body['error'] ) ) {
			$msg = is_array( $body['error'] )
				? ( $body['error']['message'] ?? 'Unknown error' )
				: ( $body['error_description'] ?? (string) $body['error'] );

			return [
				'success' => false,
				'message' => $msg,
				'details' => [],
			];
		}

		$email = $body['email'] ?? '';

		return [
			'success' => true,
			'message' => 'Connected as ' . $email,
			'details' => [
				'email' => $email,
				'name'  => $body['name'] ?? '',
				'id'    => $body['id'] ?? '',
			],
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'google_calendars' => [ self::class, 'query_calendars' ],
		];
	}

	public static function query_calendars( array $query ): array {
		$token = $query['credentials']['access_token'] ?? '';

		if ( empty( $token ) ) {
			return [];
		}

		$response = wp_remote_get( self::CALENDAR_API_URL . '/users/me/calendarList', [
			'headers' => [ 'Authorization' => 'Bearer ' . $token ],
		] );

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$result = [];

		foreach ( $body['items'] ?? [] as $cal ) {
			$result[] = [
				'value' => $cal['id'],
				'label' => $cal['summary'] ?? $cal['id'],
			];
		}

		return $result;
	}

	// ── Private action helpers ────────────────────────────────────────────────

	private static function action_create_meeting( array $node, array $input, string $token ): array {
		$config   = $node['data']['config'] ?? [];
		$summary  = $config['summary'] ?? '';
		$start_dt = $config['start_datetime'] ?? '';
		$end_dt   = $config['end_datetime'] ?? '';

		if ( empty( $summary ) ) {
			throw new \Exception( 'Google Meet: meeting summary (title) is required' );
		}

		if ( empty( $start_dt ) ) {
			throw new \Exception( 'Google Meet: start date & time is required' );
		}

		if ( empty( $end_dt ) ) {
			throw new \Exception( 'Google Meet: end date & time is required' );
		}

		$timezone    = $config['timezone'] ?? 'UTC';
		$calendar_id = $config['calendar_id'] ?? 'primary';
		$send_notif  = ( $config['send_notifications'] ?? 'true' ) === 'true';

		$body = [
			'summary' => $summary,
			'start'   => [ 'dateTime' => $start_dt, 'timeZone' => $timezone ],
			'end'     => [ 'dateTime' => $end_dt,   'timeZone' => $timezone ],
			'conferenceData' => [
				'createRequest' => [
					'requestId'             => sprintf(
						'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
						mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
						mt_rand( 0, 0xffff ),
						mt_rand( 0, 0x0fff ) | 0x4000,
						mt_rand( 0, 0x3fff ) | 0x8000,
						mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
					),
					'conferenceSolutionKey' => [ 'type' => 'hangoutsMeet' ],
				],
			],
		];

		if ( ! empty( $config['description'] ) ) {
			$body['description'] = $config['description'];
		}

		$attendees_raw = $config['attendees'] ?? '';
		if ( ! empty( $attendees_raw ) ) {
			$body['attendees'] = array_values(
				array_map(
					fn( $e ) => [ 'email' => trim( $e ) ],
					array_filter( explode( ',', $attendees_raw ) )
				)
			);
		}

		$url  = self::CALENDAR_API_URL . '/calendars/' . rawurlencode( $calendar_id ) . '/events';
		$url .= '?conferenceDataVersion=1';

		if ( $send_notif ) {
			$url .= '&sendNotifications=true';
		}

		$data = self::google_request( $token, 'POST', $url, $body );

		$meet_link = '';
		foreach ( $data['conferenceData']['entryPoints'] ?? [] as $ep ) {
			if ( 'video' === ( $ep['entryPointType'] ?? '' ) ) {
				$meet_link = $ep['uri'] ?? '';
				break;
			}
		}

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'google_event_id'      => $data['id'] ?? '',
				'google_event_summary' => $data['summary'] ?? '',
				'google_event_link'    => $data['htmlLink'] ?? '',
				'google_meet_link'     => $meet_link,
				'google_conference_id' => $data['conferenceData']['conferenceId'] ?? '',
				'google_start'         => $data['start']['dateTime'] ?? $start_dt,
				'google_end'           => $data['end']['dateTime'] ?? $end_dt,
			] ),
		];
	}

	private static function action_update_meeting( array $node, array $input, string $token ): array {
		$config      = $node['data']['config'] ?? [];
		$event_id    = $config['event_id'] ?? '';
		$calendar_id = $config['calendar_id'] ?? 'primary';

		if ( empty( $event_id ) ) {
			throw new \Exception( 'Google Meet: event_id is required to update a meeting' );
		}

		$body     = [];
		$timezone = $config['timezone'] ?? 'UTC';

		if ( ! empty( $config['summary'] ) ) {
			$body['summary'] = $config['summary'];
		}

		if ( ! empty( $config['description'] ) ) {
			$body['description'] = $config['description'];
		}

		if ( ! empty( $config['start_datetime'] ) ) {
			$body['start'] = [ 'dateTime' => $config['start_datetime'], 'timeZone' => $timezone ];
		}

		if ( ! empty( $config['end_datetime'] ) ) {
			$body['end'] = [ 'dateTime' => $config['end_datetime'], 'timeZone' => $timezone ];
		}

		if ( ! empty( $config['attendees'] ) ) {
			$body['attendees'] = array_values(
				array_map(
					fn( $e ) => [ 'email' => trim( $e ) ],
					array_filter( explode( ',', $config['attendees'] ) )
				)
			);
		}

		$url  = self::CALENDAR_API_URL . '/calendars/' . rawurlencode( $calendar_id ) . '/events/' . rawurlencode( $event_id );
		$data = self::google_request( $token, 'PATCH', $url, $body );

		$meet_link = '';
		foreach ( $data['conferenceData']['entryPoints'] ?? [] as $ep ) {
			if ( 'video' === ( $ep['entryPointType'] ?? '' ) ) {
				$meet_link = $ep['uri'] ?? '';
				break;
			}
		}

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'google_event_id'      => $data['id'] ?? $event_id,
				'google_event_summary' => $data['summary'] ?? '',
				'google_event_link'    => $data['htmlLink'] ?? '',
				'google_meet_link'     => $meet_link,
			] ),
		];
	}

	private static function action_cancel_meeting( array $node, array $input, string $token ): array {
		$config      = $node['data']['config'] ?? [];
		$event_id    = $config['event_id'] ?? '';
		$calendar_id = $config['calendar_id'] ?? 'primary';

		if ( empty( $event_id ) ) {
			throw new \Exception( 'Google Meet: event_id is required to cancel a meeting' );
		}

		$send_notif = ( $config['send_notifications'] ?? 'true' ) === 'true';
		$url        = self::CALENDAR_API_URL . '/calendars/' . rawurlencode( $calendar_id ) . '/events/' . rawurlencode( $event_id );

		if ( $send_notif ) {
			$url .= '?sendNotifications=true';
		}

		self::google_request( $token, 'DELETE', $url );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'google_event_id'  => $event_id,
				'google_cancelled' => true,
			] ),
		];
	}

	private static function action_create_space( array $node, array $input, string $token ): array {
		$config = $node['data']['config'] ?? [];
		$body   = [];

		$access_type = $config['access_type'] ?? '';
		if ( ! empty( $access_type ) ) {
			$body['config'] = [ 'accessType' => $access_type ];
		}

		$data = self::google_request( $token, 'POST', self::MEET_API_URL . '/spaces', $body );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'google_meet_link'    => $data['meetingUri'] ?? '',
				'google_space_name'   => $data['name'] ?? '',
				'google_meeting_code' => $data['meetingCode'] ?? '',
			] ),
		];
	}

	private static function action_end_conference( array $node, array $input, string $token ): array {
		$config     = $node['data']['config'] ?? [];
		$space_name = $config['space_name'] ?? '';

		if ( empty( $space_name ) ) {
			throw new \Exception( 'Google Meet: space_name is required to end a conference' );
		}

		$url = self::MEET_API_URL . '/' . ltrim( $space_name, '/' ) . ':endActiveConference';
		self::google_request( $token, 'POST', $url );

		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'google_space_name'        => $space_name,
				'google_conference_ended'  => true,
			] ),
		];
	}

	// ── Internal utility ─────────────────────────────────────────────────────

	/**
	 * Make an authenticated request to a Google API endpoint.
	 *
	 * @throws \Exception on WP_Error or API error response.
	 */
	private static function google_request( string $token, string $method, string $url, array $body = [] ): array {
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
			throw new \Exception( 'Google API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

		if ( isset( $data['error'] ) ) {
			$message = is_array( $data['error'] )
				? ( $data['error']['message'] ?? 'Unknown Google API error' )
				: (string) $data['error'];
			throw new \Exception( 'Google API error: ' . esc_html( $message ) );
		}

		return $data;
	}
}
