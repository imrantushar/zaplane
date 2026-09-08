<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Telegram;

class TelegramTest extends IntegrationTestCase {

	private array $credentials = [
		'bot_token' => '123456789:AABBCCDDEEFFaabbccddeeff1122334455',
	];

	protected function getIntegrationClass(): string {
		return Telegram::class;
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
		$this->mockHttp( [
			'ok'     => true,
			'result' => [
				'message_id' => 101,
				'chat'       => [ 'id' => 987654321 ],
				'date'       => 1700000000,
			],
		] );

		$node   = $this->makeActionNode( 'send_message', [ 'chat_id' => '987654321', 'text' => 'Hello from Zaplane!' ], $this->credentials );
		$result = Telegram::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 101, $result['data']['telegram_message_id'] );
		$this->assertEquals( '987654321', $result['data']['telegram_chat_id'] );
		$this->assertEquals( 'sent', $result['data']['telegram_status'] );
		$this->assertArrayHasKey( 'telegram_timestamp', $result['data'] );
	}

	public function test_send_message_throws_without_credentials(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/credentials/' );

		$node = $this->makeActionNode( 'send_message', [ 'chat_id' => '123', 'text' => 'Hi' ] );
		Telegram::execute_node( $node, [] );
	}

	public function test_send_message_throws_without_chat_id(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/chat_id/' );

		$node = $this->makeActionNode( 'send_message', [ 'text' => 'Hello' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	public function test_send_message_throws_without_text(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/message text/' );

		$node = $this->makeActionNode( 'send_message', [ 'chat_id' => '987654321' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	public function test_send_message_throws_on_api_error(): void {
		$this->mockHttp( [
			'ok'          => false,
			'error_code'  => 400,
			'description' => 'Bad Request: chat not found',
		], 400 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/chat not found/' );

		$node = $this->makeActionNode( 'send_message', [ 'chat_id' => '000', 'text' => 'Hi' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	public function test_send_message_supports_parse_mode(): void {
		$this->mockHttp( [
			'ok'     => true,
			'result' => [ 'message_id' => 102, 'chat' => [ 'id' => 111 ], 'date' => 1700000001 ],
		] );

		$node   = $this->makeActionNode( 'send_message', [
			'chat_id'    => '111',
			'text'       => '<b>Bold text</b>',
			'parse_mode' => 'HTML',
		], $this->credentials );
		$result = Telegram::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 102, $result['data']['telegram_message_id'] );
	}

	// ========== ACTION: send_photo ==========

	public function test_send_photo_succeeds(): void {
		$this->mockHttp( [
			'ok'     => true,
			'result' => [ 'message_id' => 201, 'chat' => [ 'id' => 987654321 ], 'date' => 1700000002 ],
		] );

		$node   = $this->makeActionNode( 'send_photo', [
			'chat_id' => '987654321',
			'photo'   => 'https://example.com/image.jpg',
			'caption' => 'Look at this!',
		], $this->credentials );
		$result = Telegram::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 201, $result['data']['telegram_message_id'] );
	}

	public function test_send_photo_throws_without_photo_url(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/photo URL/' );

		$node = $this->makeActionNode( 'send_photo', [ 'chat_id' => '123' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	// ========== ACTION: send_document ==========

	public function test_send_document_succeeds(): void {
		$this->mockHttp( [
			'ok'     => true,
			'result' => [ 'message_id' => 301, 'chat' => [ 'id' => 987654321 ], 'date' => 1700000003 ],
		] );

		$node   = $this->makeActionNode( 'send_document', [
			'chat_id'  => '987654321',
			'document' => 'https://example.com/report.pdf',
			'caption'  => 'Here is your report',
		], $this->credentials );
		$result = Telegram::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 301, $result['data']['telegram_message_id'] );
	}

	public function test_send_document_throws_without_document_url(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/document URL/' );

		$node = $this->makeActionNode( 'send_document', [ 'chat_id' => '123' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	// ========== ACTION: send_video ==========

	public function test_send_video_succeeds(): void {
		$this->mockHttp( [
			'ok'     => true,
			'result' => [ 'message_id' => 401, 'chat' => [ 'id' => 987654321 ], 'date' => 1700000004 ],
		] );

		$node   = $this->makeActionNode( 'send_video', [
			'chat_id' => '987654321',
			'video'   => 'https://example.com/video.mp4',
			'caption' => 'Watch this!',
		], $this->credentials );
		$result = Telegram::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 401, $result['data']['telegram_message_id'] );
	}

	public function test_send_video_throws_without_video_url(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/video URL/' );

		$node = $this->makeActionNode( 'send_video', [ 'chat_id' => '123' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	// ========== ACTION: send_audio ==========

	public function test_send_audio_succeeds(): void {
		$this->mockHttp( [
			'ok'     => true,
			'result' => [ 'message_id' => 501, 'chat' => [ 'id' => 987654321 ], 'date' => 1700000005 ],
		] );

		$node   = $this->makeActionNode( 'send_audio', [
			'chat_id' => '987654321',
			'audio'   => 'https://example.com/audio.mp3',
			'caption' => 'Listen!',
		], $this->credentials );
		$result = Telegram::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 501, $result['data']['telegram_message_id'] );
	}

	public function test_send_audio_throws_without_audio_url(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/audio URL/' );

		$node = $this->makeActionNode( 'send_audio', [ 'chat_id' => '123' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	// ========== ACTION: send_location ==========

	public function test_send_location_succeeds(): void {
		$this->mockHttp( [
			'ok'     => true,
			'result' => [ 'message_id' => 601, 'chat' => [ 'id' => 987654321 ], 'date' => 1700000006 ],
		] );

		$node   = $this->makeActionNode( 'send_location', [
			'chat_id'   => '987654321',
			'latitude'  => '40.7128',
			'longitude' => '-74.0060',
		], $this->credentials );
		$result = Telegram::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 601, $result['data']['telegram_message_id'] );
	}

	public function test_send_location_throws_without_latitude(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/latitude/' );

		$node = $this->makeActionNode( 'send_location', [ 'chat_id' => '123', 'longitude' => '-74.0060' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	public function test_send_location_throws_without_longitude(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/longitude/' );

		$node = $this->makeActionNode( 'send_location', [ 'chat_id' => '123', 'latitude' => '40.7128' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	// ========== ACTION: pin_message ==========

	public function test_pin_message_succeeds(): void {
		$this->mockHttp( [
			'ok'     => true,
			'result' => true,
		] );

		$node   = $this->makeActionNode( 'pin_message', [
			'chat_id'    => '987654321',
			'message_id' => '101',
		], $this->credentials );
		$result = Telegram::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertTrue( $result['data']['telegram_pinned'] );
		$this->assertEquals( '987654321', $result['data']['telegram_chat_id'] );
	}

	public function test_pin_message_throws_without_message_id(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/message_id/' );

		$node = $this->makeActionNode( 'pin_message', [ 'chat_id' => '123' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	public function test_pin_message_throws_on_api_error(): void {
		$this->mockHttp( [
			'ok'          => false,
			'error_code'  => 403,
			'description' => 'Forbidden: bot is not a member of the channel chat',
		], 403 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/not a member/' );

		$node = $this->makeActionNode( 'pin_message', [ 'chat_id' => '-100123', 'message_id' => '99' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	// ========== ACTION: send_poll ==========

	public function test_send_poll_succeeds(): void {
		$this->mockHttp( [
			'ok'     => true,
			'result' => [ 'message_id' => 701, 'chat' => [ 'id' => 987654321 ], 'date' => 1700000007 ],
		] );

		$node   = $this->makeActionNode( 'send_poll', [
			'chat_id'  => '987654321',
			'question' => 'What is your favourite colour?',
			'options'  => "Red\nBlue\nGreen",
		], $this->credentials );
		$result = Telegram::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 701, $result['data']['telegram_message_id'] );
	}

	public function test_send_poll_throws_without_question(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/question/' );

		$node = $this->makeActionNode( 'send_poll', [ 'chat_id' => '123', 'options' => "A\nB" ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	public function test_send_poll_throws_without_options(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/options/' );

		$node = $this->makeActionNode( 'send_poll', [ 'chat_id' => '123', 'question' => 'Pick one?' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	// ========== TRIGGERS ==========

	public function test_trigger_message_received_returns_payload(): void {
		$args   = [
			[
				'message_id' => 42,
				'from'       => [ 'id' => 111, 'first_name' => 'Alice', 'username' => 'alice' ],
				'chat'       => [ 'id' => 111, 'type' => 'private' ],
				'text'       => 'Hello bot!',
				'date'       => 1700000000,
			],
		];
		$node   = $this->makeTriggerNode( 'message_received' );
		$result = Telegram::resolve_trigger( $node, $args );

		$this->assertIsArray( $result );
		$this->assertEquals( 42, $result['telegram_message_id'] );
		$this->assertEquals( 'Hello bot!', $result['telegram_text'] );
		$this->assertEquals( 111, $result['telegram_chat_id'] );
		$this->assertEquals( 'Alice', $result['telegram_from_first_name'] );
		$this->assertEquals( 'alice', $result['telegram_from_username'] );
	}

	/**
	 * An empty hook arg has to be rejected, not turned into a payload of empty
	 * strings — otherwise the router starts a run with nothing in it.
	 */
	public function test_trigger_message_received_rejects_empty_args(): void {
		$node = $this->makeTriggerNode( 'message_received' );

		$this->assertFalse( Telegram::resolve_trigger( $node, [] ) );
		$this->assertFalse( Telegram::resolve_trigger( $node, [ [] ] ) );
	}

	// ========== WEBHOOK DELIVERY ==========

	/**
	 * Telegram delivery is the only way these triggers can ever fire, so the
	 * endpoint has to accept it at all.
	 */
	public function test_supports_incoming_webhooks(): void {
		$this->assertTrue( Telegram::supports_webhook() );
	}

	public function test_parse_webhook_event_unwraps_the_update_envelope(): void {
		$parsed = Telegram::parse_webhook_event( $this->makeUpdateRequest( [
			'update_id' => 90210,
			'message'   => [
				'message_id' => 42,
				'from'       => [ 'id' => 111, 'first_name' => 'Alice', 'is_bot' => false ],
				'chat'       => [ 'id' => 111, 'type' => 'private' ],
				'text'       => 'Hello bot!',
				'date'       => 1700000000,
			],
		] ) );

		$this->assertIsArray( $parsed );
		$this->assertSame( 'message_received', $parsed['event'] );
		$this->assertSame( 42, $parsed['payload']['message_id'] );

		// The payload has to be the message object resolve_trigger consumes.
		$node   = $this->makeTriggerNode( 'message_received' );
		$result = Telegram::resolve_trigger( $node, [ $parsed['payload'] ] );
		$this->assertSame( 'Hello bot!', $result['telegram_text'] );
	}

	public function test_parse_webhook_event_routes_slash_commands(): void {
		$parsed = Telegram::parse_webhook_event( $this->makeUpdateRequest( [
			'message' => [
				'message_id' => 55,
				'from'       => [ 'id' => 222, 'first_name' => 'Bob' ],
				'chat'       => [ 'id' => 222, 'type' => 'private' ],
				'text'       => '/start welcome',
			],
		] ) );

		$this->assertSame( 'command_received', $parsed['event'] );
	}

	public function test_parse_webhook_event_skips_non_text_and_bot_updates(): void {
		// A callback query carries no message at all.
		$this->assertNull( Telegram::parse_webhook_event( $this->makeUpdateRequest( [
			'callback_query' => [ 'id' => '1' ],
		] ) ) );

		// A join notice is a message with no text.
		$this->assertNull( Telegram::parse_webhook_event( $this->makeUpdateRequest( [
			'message' => [ 'message_id' => 1, 'chat' => [ 'id' => 5 ], 'new_chat_members' => [] ],
		] ) ) );

		// Another bot's message would otherwise loop.
		$this->assertNull( Telegram::parse_webhook_event( $this->makeUpdateRequest( [
			'message' => [
				'message_id' => 2,
				'from'       => [ 'id' => 9, 'is_bot' => true ],
				'chat'       => [ 'id' => 5 ],
				'text'       => 'beep',
			],
		] ) ) );
	}

	public function test_webhook_signature_checks_the_secret_token(): void {
		$request = $this->makeUpdateRequest( [ 'message' => [ 'text' => 'hi' ] ] );

		// Unset secret: allowed, so first-time setup isn't a chicken-and-egg.
		$this->assertTrue( Telegram::verify_webhook_signature( $request ) );

		update_option( 'zaplane_webhook_config', [ 'telegram' => [ 'secret_token' => 's3cret' ] ] );

		$this->assertFalse( Telegram::verify_webhook_signature( $request ) );

		$request->set_header( 'x_telegram_bot_api_secret_token', 's3cret' );
		$this->assertTrue( Telegram::verify_webhook_signature( $request ) );

		delete_option( 'zaplane_webhook_config' );
	}

	private function makeUpdateRequest( array $update ): \WP_REST_Request {
		$request = new \WP_REST_Request( 'POST', '/zaplane/v1/incoming/telegram' );
		$request->set_body( (string) wp_json_encode( $update ) );
		$request->set_header( 'content-type', 'application/json' );

		return $request;
	}

	public function test_trigger_command_received_returns_payload(): void {
		$args   = [
			[
				'message_id' => 55,
				'from'       => [ 'id' => 222, 'first_name' => 'Bob', 'username' => 'bob' ],
				'chat'       => [ 'id' => 222, 'type' => 'private' ],
				'text'       => '/start welcome',
				'date'       => 1700000100,
			],
		];
		$node   = $this->makeTriggerNode( 'command_received' );
		$result = Telegram::resolve_trigger( $node, $args );

		$this->assertIsArray( $result );
		$this->assertEquals( '/start', $result['telegram_command'] );
		$this->assertEquals( 'welcome', $result['telegram_command_args'] );
		$this->assertEquals( '/start welcome', $result['telegram_text'] );
	}

	public function test_trigger_command_received_handles_command_without_args(): void {
		$args   = [
			[
				'message_id' => 56,
				'from'       => [ 'id' => 222, 'first_name' => 'Bob', 'username' => 'bob' ],
				'chat'       => [ 'id' => 222, 'type' => 'private' ],
				'text'       => '/help',
				'date'       => 1700000200,
			],
		];
		$node   = $this->makeTriggerNode( 'command_received' );
		$result = Telegram::resolve_trigger( $node, $args );

		$this->assertEquals( '/help', $result['telegram_command'] );
		$this->assertEquals( '', $result['telegram_command_args'] );
	}

	// ========== WP_Error passthrough ==========

	public function test_wp_error_throws_exception(): void {
		// No HTTP response queued → wp_remote_post returns WP_Error automatically
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/request failed|Mock/' );

		$node = $this->makeActionNode( 'send_message', [ 'chat_id' => '123', 'text' => 'Hi' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	// ========== Unknown action passthrough ==========

	public function test_unknown_action_returns_passthrough(): void {
		$node   = $this->makeActionNode( 'nonexistent_action', [], $this->credentials );
		$result = Telegram::execute_node( $node, [ 'foo' => 'bar' ] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( [ 'foo' => 'bar' ], $result['data'] );
	}

	// ========== test_connection ==========

	public function test_connection_succeeds(): void {
		$this->mockHttp( [
			'ok'     => true,
			'result' => [
				'id'         => 123456789,
				'first_name' => 'MyBot',
				'username'   => 'my_zaplane_bot',
				'is_bot'     => true,
			],
		] );

		$result = Telegram::test_connection( $this->credentials );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'my_zaplane_bot', $result['message'] );
		$this->assertEquals( 'my_zaplane_bot', $result['details']['username'] );
	}

	public function test_connection_fails_without_bot_token(): void {
		$result = Telegram::test_connection( [] );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'bot_token', $result['message'] );
	}

	public function test_connection_fails_on_api_error(): void {
		$this->mockHttp( [
			'ok'          => false,
			'error_code'  => 401,
			'description' => 'Unauthorized',
		], 401 );

		$result = Telegram::test_connection( [ 'bot_token' => 'invalid:token' ] );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Unauthorized', $result['message'] );
	}

	// ========== Schema coverage: all actions must have chat_id ==========

	public function test_all_action_schemas_have_chat_id_field(): void {
		$actions = Telegram::get_actions();
		foreach ( array_keys( $actions ) as $action ) {
			$schema = Telegram::get_action_config_schema( $action );
			$keys   = array_column( $schema, 'key' );
			$this->assertContains( 'chat_id', $keys, "Action '{$action}' schema is missing the 'chat_id' field" );
		}
	}

	// ========== Triggers registered ==========

	public function test_has_triggers(): void {
		$triggers = Telegram::get_triggers();
		$this->assertNotEmpty( $triggers );
		$this->assertArrayHasKey( 'message_received', $triggers );
		$this->assertArrayHasKey( 'command_received', $triggers );
	}
}
