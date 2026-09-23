<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Zoom;

class ZoomTest extends IntegrationTestCase {

	private array $credentials = [
		'access_token' => 'eyJhbGciOiJIUzI1NiJ9.test_zoom_token',
	];

	protected function getIntegrationClass(): string {
		return Zoom::class;
	}

	// ========== CONTRACT TESTS (inherited automatically) ==========
	// - integration_has_slug
	// - all_triggers_have_labels_and_hooks
	// - all_actions_have_labels
	// - trigger_config_schemas_are_valid
	// - action_config_schemas_are_valid
	// - output_ports_are_valid

	// =========================================================
	// ACTION: create_meeting
	// =========================================================

	public function test_create_meeting_succeeds(): void {
		$this->mockHttp( [
			'id'        => 87654321,
			'topic'     => 'Team Standup',
			'join_url'  => 'https://zoom.us/j/87654321?pwd=abc123',
			'start_url' => 'https://zoom.us/s/87654321?zak=xyz',
			'password'  => 'abc123',
			'start_time' => '2026-05-01T10:00:00Z',
			'duration'  => 60,
		] );

		$node   = $this->makeActionNode( 'create_meeting', [
			'topic'      => 'Team Standup',
			'start_time' => '2026-05-01T10:00:00',
			'duration'   => '60',
		], $this->credentials );
		$result = Zoom::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 87654321, $result['data']['zoom_meeting_id'] );
		$this->assertEquals( 'https://zoom.us/j/87654321?pwd=abc123', $result['data']['zoom_join_url'] );
		$this->assertEquals( 'https://zoom.us/s/87654321?zak=xyz', $result['data']['zoom_start_url'] );
		$this->assertEquals( 'abc123', $result['data']['zoom_password'] );
		$this->assertEquals( 'Team Standup', $result['data']['zoom_topic'] );
	}

	public function test_create_meeting_throws_without_credentials(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/credentials/' );

		$node = $this->makeActionNode( 'create_meeting', [
			'topic'      => 'Test',
			'start_time' => '2026-05-01T10:00:00',
		] );
		Zoom::execute_node( $node, [] );
	}

	public function test_create_meeting_throws_without_topic(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/topic/i' );

		$node = $this->makeActionNode( 'create_meeting', [
			'start_time' => '2026-05-01T10:00:00',
		], $this->credentials );
		Zoom::execute_node( $node, [] );
	}

	public function test_create_meeting_throws_without_start_time(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/start time/i' );

		$node = $this->makeActionNode( 'create_meeting', [
			'topic' => 'Test Meeting',
		], $this->credentials );
		Zoom::execute_node( $node, [] );
	}

	public function test_create_meeting_throws_on_api_error(): void {
		$this->mockHttp( [
			'code'    => 124,
			'message' => 'Access token is expired.',
		], 401 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/Access token is expired/' );

		$node = $this->makeActionNode( 'create_meeting', [
			'topic'      => 'Test',
			'start_time' => '2026-05-01T10:00:00',
		], $this->credentials );
		Zoom::execute_node( $node, [] );
	}

	public function test_create_meeting_supports_optional_fields(): void {
		$this->mockHttp( [
			'id'        => 11223344,
			'topic'     => 'All Hands',
			'join_url'  => 'https://zoom.us/j/11223344',
			'start_url' => 'https://zoom.us/s/11223344?zak=abc',
			'password'  => '',
			'start_time' => '2026-06-01T14:00:00Z',
			'duration'  => 90,
		] );

		$node   = $this->makeActionNode( 'create_meeting', [
			'topic'        => 'All Hands',
			'start_time'   => '2026-06-01T14:00:00',
			'duration'     => '90',
			'timezone'     => 'America/New_York',
			'agenda'       => 'Quarterly review',
			'waiting_room' => 'true',
			'password'     => 'secret',
		], $this->credentials );
		$result = Zoom::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 11223344, $result['data']['zoom_meeting_id'] );
	}

	// =========================================================
	// ACTION: update_meeting
	// =========================================================

	public function test_update_meeting_succeeds(): void {
		// Zoom returns 204 No Content on successful update
		$this->mockHttp( [], 204 );

		$node   = $this->makeActionNode( 'update_meeting', [
			'meeting_id' => '87654321',
			'topic'      => 'Updated Meeting Title',
		], $this->credentials );
		$result = Zoom::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( '87654321', $result['data']['zoom_meeting_id'] );
		$this->assertTrue( $result['data']['zoom_updated'] );
	}

	public function test_update_meeting_throws_without_meeting_id(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/meeting id/i' );

		$node = $this->makeActionNode( 'update_meeting', [
			'topic' => 'New Title',
		], $this->credentials );
		Zoom::execute_node( $node, [] );
	}

	public function test_update_meeting_throws_on_api_error(): void {
		$this->mockHttp( [
			'code'    => 3001,
			'message' => 'Meeting does not exist: 99999999',
		], 404 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/Meeting does not exist/' );

		$node = $this->makeActionNode( 'update_meeting', [
			'meeting_id' => '99999999',
			'topic'      => 'New Title',
		], $this->credentials );
		Zoom::execute_node( $node, [] );
	}

	// =========================================================
	// ACTION: delete_meeting
	// =========================================================

	public function test_delete_meeting_succeeds(): void {
		$this->mockHttp( [], 204 );

		$node   = $this->makeActionNode( 'delete_meeting', [
			'meeting_id' => '87654321',
		], $this->credentials );
		$result = Zoom::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( '87654321', $result['data']['zoom_meeting_id'] );
		$this->assertTrue( $result['data']['zoom_deleted'] );
	}

	public function test_delete_meeting_throws_without_meeting_id(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/meeting id/i' );

		$node = $this->makeActionNode( 'delete_meeting', [], $this->credentials );
		Zoom::execute_node( $node, [] );
	}

	public function test_delete_meeting_throws_on_api_error(): void {
		$this->mockHttp( [
			'code'    => 3001,
			'message' => 'Meeting does not exist: 00000000',
		], 404 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/Meeting does not exist/' );

		$node = $this->makeActionNode( 'delete_meeting', [
			'meeting_id' => '00000000',
		], $this->credentials );
		Zoom::execute_node( $node, [] );
	}

	// =========================================================
	// ACTION: add_registrant
	// =========================================================

	public function test_add_registrant_succeeds(): void {
		$this->mockHttp( [
			'id'            => '87654321',
			'registrant_id' => 'reg_abc123',
			'join_url'      => 'https://zoom.us/j/87654321?tk=uniquetoken',
			'topic'         => 'Team Standup',
			'start_time'    => '2026-05-01T10:00:00Z',
		] );

		$node   = $this->makeActionNode( 'add_registrant', [
			'meeting_id' => '87654321',
			'email'      => 'alice@example.com',
			'first_name' => 'Alice',
			'last_name'  => 'Smith',
		], $this->credentials );
		$result = Zoom::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'reg_abc123', $result['data']['zoom_registrant_id'] );
		$this->assertEquals( 'https://zoom.us/j/87654321?tk=uniquetoken', $result['data']['zoom_join_url'] );
		$this->assertEquals( '87654321', $result['data']['zoom_meeting_id'] );
	}

	public function test_add_registrant_throws_without_meeting_id(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/meeting id/i' );

		$node = $this->makeActionNode( 'add_registrant', [
			'email'      => 'alice@example.com',
			'first_name' => 'Alice',
		], $this->credentials );
		Zoom::execute_node( $node, [] );
	}

	public function test_add_registrant_throws_without_email(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/email/i' );

		$node = $this->makeActionNode( 'add_registrant', [
			'meeting_id' => '87654321',
			'first_name' => 'Alice',
		], $this->credentials );
		Zoom::execute_node( $node, [] );
	}

	public function test_add_registrant_throws_without_first_name(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/first name/i' );

		$node = $this->makeActionNode( 'add_registrant', [
			'meeting_id' => '87654321',
			'email'      => 'alice@example.com',
		], $this->credentials );
		Zoom::execute_node( $node, [] );
	}

	public function test_add_registrant_throws_on_api_error(): void {
		$this->mockHttp( [
			'code'    => 3001,
			'message' => 'Meeting does not exist.',
		], 404 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/Meeting does not exist/' );

		$node = $this->makeActionNode( 'add_registrant', [
			'meeting_id' => '99999',
			'email'      => 'alice@example.com',
			'first_name' => 'Alice',
		], $this->credentials );
		Zoom::execute_node( $node, [] );
	}

	// =========================================================
	// TRIGGERS
	// =========================================================

	public function test_trigger_meeting_started_returns_payload(): void {
		$args = [
			[
				'event'   => 'meeting.started',
				'payload' => [
					'account_id' => 'acc_abc',
					'object'     => [
						'id'         => '87654321',
						'uuid'       => 'uuid==abc==',
						'topic'      => 'Team Standup',
						'host_id'    => 'host_123',
						'start_time' => '2026-05-01T10:00:00Z',
						'duration'   => 60,
						'type'       => 2,
					],
				],
			],
		];

		$node   = $this->makeTriggerNode( 'meeting_started' );
		$result = Zoom::resolve_trigger( $node, $args );

		$this->assertIsArray( $result );
		$this->assertEquals( '87654321', $result['zoom_meeting_id'] );
		$this->assertEquals( 'Team Standup', $result['zoom_topic'] );
		$this->assertEquals( 'host_123', $result['zoom_host_id'] );
		$this->assertEquals( '2026-05-01T10:00:00Z', $result['zoom_start_time'] );
		$this->assertEquals( 60, $result['zoom_duration'] );
	}

	public function test_trigger_meeting_ended_returns_payload(): void {
		$args = [
			[
				'event'   => 'meeting.ended',
				'payload' => [
					'account_id' => 'acc_abc',
					'object'     => [
						'id'       => '87654321',
						'topic'    => 'Team Standup',
						'host_id'  => 'host_123',
						'end_time' => '2026-05-01T11:00:00Z',
						'duration' => 60,
					],
				],
			],
		];

		$node   = $this->makeTriggerNode( 'meeting_ended' );
		$result = Zoom::resolve_trigger( $node, $args );

		$this->assertIsArray( $result );
		$this->assertEquals( '87654321', $result['zoom_meeting_id'] );
		$this->assertEquals( 'Team Standup', $result['zoom_topic'] );
	}

	public function test_trigger_participant_joined_returns_payload(): void {
		$args = [
			[
				'event'   => 'meeting.participant_joined',
				'payload' => [
					'object' => [
						'id'          => '87654321',
						'topic'       => 'Team Standup',
						'participant' => [
							'user_id'   => 'p_001',
							'user_name' => 'Alice Smith',
							'email'     => 'alice@example.com',
							'join_time' => '2026-05-01T10:05:00Z',
						],
					],
				],
			],
		];

		$node   = $this->makeTriggerNode( 'participant_joined' );
		$result = Zoom::resolve_trigger( $node, $args );

		$this->assertIsArray( $result );
		$this->assertEquals( '87654321', $result['zoom_meeting_id'] );
		$this->assertEquals( 'Alice Smith', $result['zoom_participant_name'] );
		$this->assertEquals( 'alice@example.com', $result['zoom_participant_email'] );
		$this->assertEquals( '2026-05-01T10:05:00Z', $result['zoom_join_time'] );
	}

	public function test_trigger_handles_empty_args(): void {
		$node   = $this->makeTriggerNode( 'meeting_started' );
		$result = Zoom::resolve_trigger( $node, [] );

		$this->assertIsArray( $result );
		$this->assertEquals( '', $result['zoom_meeting_id'] );
	}

	// =========================================================
	// WP_Error / unknown action
	// =========================================================

	public function test_wp_error_throws_exception(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/request failed|Mock/' );

		$node = $this->makeActionNode( 'create_meeting', [
			'topic'      => 'Test',
			'start_time' => '2026-05-01T10:00:00',
		], $this->credentials );
		Zoom::execute_node( $node, [] );
	}

	public function test_unknown_action_fails_loudly(): void {
		// A step naming an action Zoom doesn't have is a broken workflow: the
		// run fails with the reason instead of silently passing through.
		$node = $this->makeActionNode( 'nonexistent_action', [], $this->credentials );
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/unknown action/i' );
		Zoom::execute_node( $node, [ 'foo' => 'bar' ] );
	}

	// =========================================================
	// test_connection
	// =========================================================

	public function test_connection_succeeds(): void {
		$this->mockHttp( [
			'id'         => 'user_abc123',
			'email'      => 'host@example.com',
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'type'       => 2,
		] );

		$result = Zoom::test_connection( $this->credentials );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'host@example.com', $result['message'] );
		$this->assertEquals( 'host@example.com', $result['details']['email'] );
	}

	public function test_connection_fails_without_access_token(): void {
		$result = Zoom::test_connection( [] );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'access_token', $result['message'] );
	}

	public function test_connection_fails_on_api_error(): void {
		$this->mockHttp( [
			'code'    => 124,
			'message' => 'Access token is expired.',
		], 401 );

		$result = Zoom::test_connection( $this->credentials );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Access token is expired', $result['message'] );
	}

	// =========================================================
	// Triggers registered
	// =========================================================

	public function test_has_triggers(): void {
		$triggers = Zoom::get_triggers();
		$this->assertNotEmpty( $triggers );
		$this->assertArrayHasKey( 'meeting_started', $triggers );
		$this->assertArrayHasKey( 'meeting_ended', $triggers );
		$this->assertArrayHasKey( 'participant_joined', $triggers );
	}

	// =========================================================
	// Schema: all actions have meeting_id or topic
	// =========================================================

	public function test_all_action_schemas_are_arrays(): void {
		foreach ( array_keys( Zoom::get_actions() ) as $action ) {
			$schema = Zoom::get_action_config_schema( $action );
			$this->assertIsArray( $schema );
			$this->assertNotEmpty( $schema, "Schema for '{$action}' should not be empty" );
		}
	}
}
