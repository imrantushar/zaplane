<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Slack;

class SlackTest extends IntegrationTestCase {

	private array $credentials = [ 'access_token' => 'xoxb-test-token' ];

	protected function getIntegrationClass(): string {
		return Slack::class;
	}

	// ========== CONTRACT TESTS (inherited automatically) ==========
	// - integration_has_slug
	// - all_triggers_have_labels_and_hooks
	// - all_actions_have_labels
	// - trigger_config_schemas_are_valid
	// - action_config_schemas_are_valid
	// - output_ports_are_valid

	// ========== ACTION: send_message ==========

	public function test_send_message_succeeds(): void {
		$this->mockHttp( [ 'ok' => true, 'ts' => '1234567890.123456', 'channel' => 'C123ABC' ] );

		$node   = $this->makeActionNode( 'send_message', [ 'channel' => '#general', 'text' => 'Hello!' ], $this->credentials );
		$result = Slack::execute_node( $node, [] );

		$this->assertArrayHasKey( 'port', $result );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( '1234567890.123456', $result['data']['slack_message_ts'] );
		$this->assertEquals( 'C123ABC', $result['data']['slack_channel'] );
	}

	public function test_send_message_throws_on_api_error(): void {
		$this->mockHttp( [ 'ok' => false, 'error' => 'channel_not_found' ] );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/channel_not_found/' );

		$node = $this->makeActionNode( 'send_message', [ 'channel' => '#nonexistent', 'text' => 'Hi' ], $this->credentials );
		Slack::execute_node( $node, [] );
	}

	public function test_send_message_throws_without_credentials(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/credentials/' );

		$node = $this->makeActionNode( 'send_message', [ 'channel' => '#general', 'text' => 'Hi' ] );
		Slack::execute_node( $node, [] );
	}

	// ========== ACTION: send_dm ==========

	public function test_send_dm_succeeds(): void {
		$this->mockHttp( [ 'ok' => true, 'channel' => [ 'id' => 'D123ABC' ] ] );
		$this->mockHttp( [ 'ok' => true, 'ts' => '111.222', 'channel' => 'D123ABC' ] );

		$node   = $this->makeActionNode( 'send_dm', [ 'user_id' => 'U123', 'text' => 'Hey!' ], $this->credentials );
		$result = Slack::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( '111.222', $result['data']['slack_message_ts'] );
	}

	public function test_send_dm_throws_when_open_channel_fails(): void {
		$this->mockHttp( [ 'ok' => false, 'error' => 'user_not_found' ] );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/user_not_found/' );

		$node = $this->makeActionNode( 'send_dm', [ 'user_id' => 'UBAD', 'text' => 'Hi' ], $this->credentials );
		Slack::execute_node( $node, [] );
	}

	// ========== ACTION: create_channel ==========

	public function test_create_channel_succeeds(): void {
		$this->mockHttp( [ 'ok' => true, 'channel' => [ 'id' => 'C999', 'name' => 'my-channel' ] ] );

		$node   = $this->makeActionNode( 'create_channel', [ 'name' => 'my-channel', 'is_private' => 'false' ], $this->credentials );
		$result = Slack::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'C999', $result['data']['channel_id'] );
		$this->assertEquals( 'my-channel', $result['data']['channel_name'] );
	}

	public function test_create_channel_throws_on_api_error(): void {
		$this->mockHttp( [ 'ok' => false, 'error' => 'name_taken' ] );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/name_taken/' );

		$node = $this->makeActionNode( 'create_channel', [ 'name' => 'existing-channel' ], $this->credentials );
		Slack::execute_node( $node, [] );
	}

	// ========== ACTION: invite_to_channel ==========

	public function test_invite_to_channel_succeeds(): void {
		$this->mockHttp( [ 'ok' => true, 'channel' => [ 'id' => 'C123' ] ] );

		$node   = $this->makeActionNode( 'invite_to_channel', [ 'channel' => 'C123', 'user_ids' => 'U001,U002' ], $this->credentials );
		$result = Slack::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'C123', $result['data']['channel_id'] );
	}

	public function test_invite_to_channel_throws_on_api_error(): void {
		$this->mockHttp( [ 'ok' => false, 'error' => 'not_in_channel' ] );

		$this->expectException( \Exception::class );

		$node = $this->makeActionNode( 'invite_to_channel', [ 'channel' => 'C123', 'user_ids' => 'U001' ], $this->credentials );
		Slack::execute_node( $node, [] );
	}

	// ========== ACTION: set_topic ==========

	public function test_set_topic_succeeds(): void {
		$this->mockHttp( [ 'ok' => true, 'topic' => 'New topic here' ] );

		$node   = $this->makeActionNode( 'set_topic', [ 'channel' => 'C123', 'topic' => 'New topic here' ], $this->credentials );
		$result = Slack::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'New topic here', $result['data']['topic'] );
	}

	public function test_set_topic_falls_back_to_config_topic(): void {
		$this->mockHttp( [ 'ok' => true ] );

		$node   = $this->makeActionNode( 'set_topic', [ 'channel' => 'C123', 'topic' => 'Fallback topic' ], $this->credentials );
		$result = Slack::execute_node( $node, [] );

		$this->assertEquals( 'Fallback topic', $result['data']['topic'] );
	}

	// ========== ACTION: add_reaction ==========

	public function test_add_reaction_succeeds(): void {
		$this->mockHttp( [ 'ok' => true ] );

		$node   = $this->makeActionNode( 'add_reaction', [ 'channel' => 'C123', 'timestamp' => '111.222', 'emoji' => ':thumbsup:' ], $this->credentials );
		$result = Slack::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertTrue( $result['data']['reaction_added'] );
	}

	public function test_add_reaction_allows_already_reacted(): void {
		$this->mockHttp( [ 'ok' => false, 'error' => 'already_reacted' ] );

		$node   = $this->makeActionNode( 'add_reaction', [ 'channel' => 'C123', 'timestamp' => '111.222', 'emoji' => 'thumbsup' ], $this->credentials );
		$result = Slack::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertTrue( $result['data']['reaction_added'] );
	}

	public function test_add_reaction_throws_on_other_error(): void {
		$this->mockHttp( [ 'ok' => false, 'error' => 'channel_not_found' ] );

		$this->expectException( \Exception::class );

		$node = $this->makeActionNode( 'add_reaction', [ 'channel' => 'CBAD', 'timestamp' => '111', 'emoji' => 'wave' ], $this->credentials );
		Slack::execute_node( $node, [] );
	}

	// ========== ACTION: get_user_info ==========

	public function test_get_user_info_succeeds(): void {
		$this->mockHttp( [
			'ok'   => true,
			'user' => [
				'id'       => 'U123',
				'name'     => 'john',
				'is_admin' => false,
				'profile'  => [ 'display_name' => 'John Doe', 'email' => 'john@example.com' ],
			],
		] );

		$node   = $this->makeActionNode( 'get_user_info', [ 'user_id' => 'U123' ], $this->credentials );
		$result = Slack::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'U123', $result['data']['user_id'] );
		$this->assertEquals( 'john', $result['data']['user_name'] );
		$this->assertEquals( 'John Doe', $result['data']['display_name'] );
		$this->assertEquals( 'john@example.com', $result['data']['email'] );
		$this->assertFalse( $result['data']['is_admin'] );
	}

	public function test_get_user_info_throws_on_api_error(): void {
		$this->mockHttp( [ 'ok' => false, 'error' => 'user_not_found' ] );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/user_not_found/' );

		$node = $this->makeActionNode( 'get_user_info', [ 'user_id' => 'UBAD' ], $this->credentials );
		Slack::execute_node( $node, [] );
	}

	// ========== TRIGGERS ==========

	/**
	 * The webhook hands resolve_trigger the Slack event object, so the payload
	 * must come back with the event's own keys — the ones
	 * get_trigger_sample_output() advertises and workflows map against.
	 */
	public function test_trigger_resolve_returns_the_slack_event_payload(): void {
		$event = [
			'type'    => 'message',
			'channel' => 'C012AB3CD',
			'user'    => 'U012AB3CD',
			'text'    => 'Hello from Slack',
			'ts'      => '1720535405.001200',
			'team_id' => 'T012AB3CD',
		];

		$node   = $this->makeTriggerNode( 'message_received' );
		$result = Slack::resolve_trigger( $node, [ $event ] );

		$this->assertIsArray( $result );
		$this->assertEquals( 'Hello from Slack', $result['text'] );
		$this->assertEquals( 'U012AB3CD', $result['user'] );
		$this->assertEquals( 'C012AB3CD', $result['channel'] );
	}

	public function test_trigger_resolve_payload_keys_match_sample_output(): void {
		foreach ( [ 'message_received', 'app_mention', 'reaction_added' ] as $event ) {
			$sample = Slack::get_trigger_sample_output( $event );
			$node   = $this->makeTriggerNode( $event );
			$result = Slack::resolve_trigger( $node, [ $sample ] );

			$this->assertIsArray( $result, $event );
			$this->assertSame(
				array_keys( $sample ),
				array_keys( $result ),
				"Slack `{$event}` resolve_trigger output drifted from its sample output."
			);
		}
	}

	public function test_trigger_resolve_handles_empty_args(): void {
		$node = $this->makeTriggerNode( 'app_mention' );

		$this->assertFalse( Slack::resolve_trigger( $node, [] ) );
	}

	/**
	 * A workflow that both listens for messages and posts one would otherwise
	 * re-trigger itself on its own output.
	 */
	public function test_trigger_resolve_ignores_bot_messages(): void {
		$node = $this->makeTriggerNode( 'message_received' );

		$this->assertFalse( Slack::resolve_trigger( $node, [ [
			'type'   => 'message',
			'text'   => 'posted by us',
			'bot_id' => 'B012AB3CD',
		] ] ) );

		$this->assertFalse( Slack::resolve_trigger( $node, [ [
			'type'    => 'message',
			'text'    => 'posted by us',
			'subtype' => 'bot_message',
		] ] ) );
	}

	public function test_trigger_resolve_applies_channel_filter(): void {
		$event = [
			'type'    => 'message',
			'channel' => 'C012AB3CD',
			'text'    => 'hi',
		];

		$match = $this->makeTriggerNode( 'message_received', [ 'channel' => 'C012AB3CD' ] );
		$this->assertIsArray( Slack::resolve_trigger( $match, [ $event ] ) );

		$other = $this->makeTriggerNode( 'message_received', [ 'channel' => 'CSOMEWHERE' ] );
		$this->assertFalse( Slack::resolve_trigger( $other, [ $event ] ) );

		// The router hands over graph_node['data'] itself, not the whole node, so
		// the config has to resolve from that shape too.
		$flat = [ 'app' => 'slack', 'event' => 'message_received', 'config' => [ 'channel' => 'CSOMEWHERE' ] ];
		$this->assertFalse( Slack::resolve_trigger( $flat, [ $event ] ) );
	}

	/**
	 * Slack refuses to enable Event Subscriptions unless the Request URL echoes
	 * back the challenge it POSTs.
	 */
	public function test_url_verification_handshake_returns_the_challenge(): void {
		$request = new \WP_REST_Request( 'POST', '/zaplane/v1/incoming/slack' );
		$request->set_body( (string) wp_json_encode( [
			'type'      => 'url_verification',
			'challenge' => '3eZbrw1aBm2rZgRNFdxV2595E9CY3gmdALWMmHkvFXO7tYXAYM8P',
		] ) );
		$request->set_header( 'content-type', 'application/json' );

		$handshake = Slack::handle_webhook_handshake( $request );

		$this->assertIsArray( $handshake );
		$this->assertSame( '3eZbrw1aBm2rZgRNFdxV2595E9CY3gmdALWMmHkvFXO7tYXAYM8P', $handshake['body'] );
	}

	public function test_normal_event_is_not_treated_as_a_handshake(): void {
		$request = new \WP_REST_Request( 'POST', '/zaplane/v1/incoming/slack' );
		$request->set_body( (string) wp_json_encode( [
			'type'  => 'event_callback',
			'event' => [ 'type' => 'message', 'text' => 'hi' ],
		] ) );
		$request->set_header( 'content-type', 'application/json' );

		$this->assertNull( Slack::handle_webhook_handshake( $request ) );
	}

	// ========== test_connection ==========

	public function test_connection_succeeds(): void {
		$this->mockHttp( [ 'ok' => true, 'team' => 'MyWorkspace', 'user' => 'bot', 'user_id' => 'U001', 'team_id' => 'T001', 'url' => 'https://myworkspace.slack.com' ] );

		$result = Slack::test_connection( [ 'bot_token' => 'xoxb-valid-token' ] );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'MyWorkspace', $result['message'] );
		$this->assertEquals( 'U001', $result['details']['user_id'] );
	}

	public function test_connection_fails_without_token(): void {
		$result = Slack::test_connection( [] );

		$this->assertFalse( $result['success'] );
	}

	public function test_connection_fails_on_invalid_bot_token_format(): void {
		$result = Slack::test_connection( [ 'bot_token' => 'invalid-token' ] );

		$this->assertFalse( $result['success'] );
	}

	public function test_connection_fails_on_slack_error(): void {
		$this->mockHttp( [ 'ok' => false, 'error' => 'invalid_auth' ] );

		$result = Slack::test_connection( [ 'access_token' => 'bad-token' ] );

		$this->assertFalse( $result['success'] );
	}
}
