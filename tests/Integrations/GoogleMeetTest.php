<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\GoogleMeet;

class GoogleMeetTest extends IntegrationTestCase {

	private array $credentials = [
		'access_token' => 'ya29.test_google_meet_token',
	];

	protected function getIntegrationClass(): string {
		return GoogleMeet::class;
	}

	// ========== CONTRACT TESTS (inherited automatically) ==========
	// - integration_has_slug
	// - all_triggers_have_labels_and_hooks  (no triggers — passes vacuously)
	// - all_actions_have_labels
	// - trigger_config_schemas_are_valid    (no triggers — passes vacuously)
	// - action_config_schemas_are_valid
	// - output_ports_are_valid

	// =========================================================
	// ACTION: create_meeting
	// =========================================================

	public function test_create_meeting_succeeds(): void {
		$this->mockHttp( [
			'id'             => 'event_abc123',
			'summary'        => 'Team Standup',
			'htmlLink'       => 'https://calendar.google.com/event?eid=abc123',
			'conferenceData' => [
				'conferenceId' => 'conf_abc123',
				'entryPoints'  => [
					[ 'entryPointType' => 'video', 'uri' => 'https://meet.google.com/abc-defg-hij' ],
				],
			],
		] );

		$node   = $this->makeActionNode( 'create_meeting', [
			'summary'        => 'Team Standup',
			'start_datetime' => '2026-05-01T10:00:00',
			'end_datetime'   => '2026-05-01T11:00:00',
		], $this->credentials );
		$result = GoogleMeet::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'event_abc123', $result['data']['google_event_id'] );
		$this->assertEquals( 'https://meet.google.com/abc-defg-hij', $result['data']['google_meet_link'] );
		$this->assertEquals( 'Team Standup', $result['data']['google_event_summary'] );
		$this->assertArrayHasKey( 'google_event_link', $result['data'] );
	}

	public function test_create_meeting_throws_without_credentials(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/credentials/' );

		$node = $this->makeActionNode( 'create_meeting', [
			'summary'        => 'Test',
			'start_datetime' => '2026-05-01T10:00:00',
			'end_datetime'   => '2026-05-01T11:00:00',
		] );
		GoogleMeet::execute_node( $node, [] );
	}

	public function test_create_meeting_throws_without_summary(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/summary/' );

		$node = $this->makeActionNode( 'create_meeting', [
			'start_datetime' => '2026-05-01T10:00:00',
			'end_datetime'   => '2026-05-01T11:00:00',
		], $this->credentials );
		GoogleMeet::execute_node( $node, [] );
	}

	public function test_create_meeting_throws_without_start_datetime(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/start date/' );

		$node = $this->makeActionNode( 'create_meeting', [
			'summary'      => 'Test',
			'end_datetime' => '2026-05-01T11:00:00',
		], $this->credentials );
		GoogleMeet::execute_node( $node, [] );
	}

	public function test_create_meeting_throws_without_end_datetime(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/end date/' );

		$node = $this->makeActionNode( 'create_meeting', [
			'summary'        => 'Test',
			'start_datetime' => '2026-05-01T10:00:00',
		], $this->credentials );
		GoogleMeet::execute_node( $node, [] );
	}

	public function test_create_meeting_supports_attendees_and_description(): void {
		$this->mockHttp( [
			'id'             => 'event_xyz789',
			'summary'        => 'All Hands',
			'htmlLink'       => 'https://calendar.google.com/event?eid=xyz789',
			'conferenceData' => [
				'conferenceId' => 'conf_xyz789',
				'entryPoints'  => [
					[ 'entryPointType' => 'video', 'uri' => 'https://meet.google.com/xyz-uvwx-yz' ],
				],
			],
		] );

		$node   = $this->makeActionNode( 'create_meeting', [
			'summary'        => 'All Hands',
			'description'    => 'Monthly all-hands meeting',
			'start_datetime' => '2026-05-01T14:00:00',
			'end_datetime'   => '2026-05-01T15:00:00',
			'attendees'      => 'alice@example.com,bob@example.com',
			'timezone'       => 'America/New_York',
		], $this->credentials );
		$result = GoogleMeet::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'event_xyz789', $result['data']['google_event_id'] );
		$this->assertEquals( 'https://meet.google.com/xyz-uvwx-yz', $result['data']['google_meet_link'] );
	}

	public function test_create_meeting_throws_on_api_error(): void {
		$this->mockHttp( [
			'error' => [
				'code'    => 403,
				'message' => 'The caller does not have permission',
			],
		], 403 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/does not have permission/' );

		$node = $this->makeActionNode( 'create_meeting', [
			'summary'        => 'Test',
			'start_datetime' => '2026-05-01T10:00:00',
			'end_datetime'   => '2026-05-01T11:00:00',
		], $this->credentials );
		GoogleMeet::execute_node( $node, [] );
	}

	// =========================================================
	// ACTION: update_meeting
	// =========================================================

	public function test_update_meeting_succeeds(): void {
		$this->mockHttp( [
			'id'             => 'event_abc123',
			'summary'        => 'Updated Meeting Title',
			'htmlLink'       => 'https://calendar.google.com/event?eid=abc123',
			'conferenceData' => [
				'conferenceId' => 'conf_abc123',
				'entryPoints'  => [
					[ 'entryPointType' => 'video', 'uri' => 'https://meet.google.com/abc-defg-hij' ],
				],
			],
		] );

		$node   = $this->makeActionNode( 'update_meeting', [
			'event_id' => 'event_abc123',
			'summary'  => 'Updated Meeting Title',
		], $this->credentials );
		$result = GoogleMeet::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'event_abc123', $result['data']['google_event_id'] );
		$this->assertEquals( 'Updated Meeting Title', $result['data']['google_event_summary'] );
	}

	public function test_update_meeting_throws_without_event_id(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/event_id/' );

		$node = $this->makeActionNode( 'update_meeting', [
			'summary' => 'New Title',
		], $this->credentials );
		GoogleMeet::execute_node( $node, [] );
	}

	public function test_update_meeting_throws_on_api_error(): void {
		$this->mockHttp( [
			'error' => [
				'code'    => 404,
				'message' => 'Not Found',
			],
		], 404 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/Not Found/' );

		$node = $this->makeActionNode( 'update_meeting', [
			'event_id' => 'nonexistent_event',
			'summary'  => 'New Title',
		], $this->credentials );
		GoogleMeet::execute_node( $node, [] );
	}

	// =========================================================
	// ACTION: cancel_meeting
	// =========================================================

	public function test_cancel_meeting_succeeds(): void {
		// DELETE returns 204 No Content — empty body
		$this->mockHttp( [], 204 );

		$node   = $this->makeActionNode( 'cancel_meeting', [
			'event_id' => 'event_abc123',
		], $this->credentials );
		$result = GoogleMeet::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertTrue( $result['data']['google_cancelled'] );
		$this->assertEquals( 'event_abc123', $result['data']['google_event_id'] );
	}

	public function test_cancel_meeting_throws_without_event_id(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/event_id/' );

		$node = $this->makeActionNode( 'cancel_meeting', [], $this->credentials );
		GoogleMeet::execute_node( $node, [] );
	}

	// =========================================================
	// ACTION: create_space
	// =========================================================

	public function test_create_space_succeeds(): void {
		$this->mockHttp( [
			'name'        => 'spaces/jQCFfuBOdKE',
			'meetingUri'  => 'https://meet.google.com/abc-defg-hij',
			'meetingCode' => 'abc-defg-hij',
		] );

		$node   = $this->makeActionNode( 'create_space', [], $this->credentials );
		$result = GoogleMeet::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'https://meet.google.com/abc-defg-hij', $result['data']['google_meet_link'] );
		$this->assertEquals( 'spaces/jQCFfuBOdKE', $result['data']['google_space_name'] );
		$this->assertEquals( 'abc-defg-hij', $result['data']['google_meeting_code'] );
	}

	public function test_create_space_throws_on_api_error(): void {
		$this->mockHttp( [
			'error' => [
				'code'    => 401,
				'message' => 'Request is missing required authentication credential',
			],
		], 401 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/authentication credential/' );

		$node = $this->makeActionNode( 'create_space', [], $this->credentials );
		GoogleMeet::execute_node( $node, [] );
	}

	// =========================================================
	// ACTION: end_conference
	// =========================================================

	public function test_end_conference_succeeds(): void {
		$this->mockHttp( [], 200 );

		$node   = $this->makeActionNode( 'end_conference', [
			'space_name' => 'spaces/jQCFfuBOdKE',
		], $this->credentials );
		$result = GoogleMeet::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertTrue( $result['data']['google_conference_ended'] );
		$this->assertEquals( 'spaces/jQCFfuBOdKE', $result['data']['google_space_name'] );
	}

	public function test_end_conference_throws_without_space_name(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/space_name/' );

		$node = $this->makeActionNode( 'end_conference', [], $this->credentials );
		GoogleMeet::execute_node( $node, [] );
	}

	public function test_end_conference_throws_on_api_error(): void {
		$this->mockHttp( [
			'error' => [
				'code'    => 404,
				'message' => 'Space not found',
			],
		], 404 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/Space not found/' );

		$node = $this->makeActionNode( 'end_conference', [
			'space_name' => 'spaces/nonexistent',
		], $this->credentials );
		GoogleMeet::execute_node( $node, [] );
	}

	// =========================================================
	// WP_Error / unknown action
	// =========================================================

	public function test_wp_error_throws_exception(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/request failed|Mock/' );

		$node = $this->makeActionNode( 'create_meeting', [
			'summary'        => 'Test',
			'start_datetime' => '2026-05-01T10:00:00',
			'end_datetime'   => '2026-05-01T11:00:00',
		], $this->credentials );
		GoogleMeet::execute_node( $node, [] );
	}

	public function test_unknown_action_returns_passthrough(): void {
		$node   = $this->makeActionNode( 'nonexistent_action', [], $this->credentials );
		$result = GoogleMeet::execute_node( $node, [ 'foo' => 'bar' ] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( [ 'foo' => 'bar' ], $result['data'] );
	}

	// =========================================================
	// test_connection
	// =========================================================

	public function test_connection_succeeds(): void {
		$this->mockHttp( [
			'id'    => '123456789',
			'email' => 'user@gmail.com',
			'name'  => 'Test User',
		] );

		$result = GoogleMeet::test_connection( $this->credentials );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'user@gmail.com', $result['message'] );
		$this->assertEquals( 'user@gmail.com', $result['details']['email'] );
	}

	public function test_connection_fails_without_access_token(): void {
		$result = GoogleMeet::test_connection( [] );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'access_token', $result['message'] );
	}

	public function test_connection_fails_on_api_error(): void {
		$this->mockHttp( [
			'error' => 'invalid_token',
			'error_description' => 'Token has been expired or revoked',
		], 401 );

		$result = GoogleMeet::test_connection( $this->credentials );

		$this->assertFalse( $result['success'] );
	}

	// =========================================================
	// Dynamic query: google_calendars
	// =========================================================

	public function test_dynamic_query_google_calendars_returns_list(): void {
		$this->mockHttp( [
			'items' => [
				[ 'id' => 'primary',          'summary' => 'My Calendar' ],
				[ 'id' => 'work@example.com', 'summary' => 'Work Calendar' ],
			],
		] );

		$queries = GoogleMeet::get_dynamic_queries();
		$this->assertArrayHasKey( 'google_calendars', $queries );

		$result = call_user_func( $queries['google_calendars'], [
			'credentials' => $this->credentials,
		] );

		$this->assertCount( 2, $result );
		$this->assertEquals( 'primary', $result[0]['value'] );
		$this->assertEquals( 'My Calendar', $result[0]['label'] );
		$this->assertEquals( 'work@example.com', $result[1]['value'] );
	}

	public function test_dynamic_query_google_calendars_returns_empty_without_credentials(): void {
		$queries = GoogleMeet::get_dynamic_queries();
		$result  = call_user_func( $queries['google_calendars'], [] );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	// =========================================================
	// No triggers
	// =========================================================

	public function test_has_no_triggers(): void {
		$this->assertEmpty( GoogleMeet::get_triggers() );
	}

	// =========================================================
	// Schema: all actions include required fields
	// =========================================================

	public function test_all_action_schemas_are_arrays(): void {
		foreach ( array_keys( GoogleMeet::get_actions() ) as $action ) {
			$schema = GoogleMeet::get_action_config_schema( $action );
			$this->assertIsArray( $schema, "Schema for '{$action}' must be an array" );
		}
	}
}
