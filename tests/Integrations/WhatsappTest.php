<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Whatsapp;
use WP_REST_Request;

class WhatsappTest extends IntegrationTestCase {

	private array $credentials = [
		'access_token'    => 'EAAtest1234567890',
		'phone_number_id' => '1234567890',
		'api_version'     => 'v19.0',
	];

	protected function getIntegrationClass(): string {
		return Whatsapp::class;
	}

	protected function tearDown(): void {
		delete_option( 'zaplane_webhook_config' );
		parent::tearDown();
	}

	// ========== CONTRACT TESTS (inherited automatically) ==========
	// - integration_has_slug
	// - all_triggers_have_labels_and_hooks     (no triggers — passes vacuously)
	// - all_actions_have_labels
	// - trigger_config_schemas_are_valid       (no triggers — passes vacuously)
	// - action_config_schemas_are_valid
	// - output_ports_are_valid

	// ========== ACTION: send_text ==========

	public function test_send_text_succeeds(): void {
		$this->mockHttp( [
			'messages' => [ [ 'id' => 'wamid.abc123' ] ],
		] );

		$node   = $this->makeActionNode( 'send_text', [ 'to' => '15551234567', 'body' => 'Hello!' ], $this->credentials );
		$result = Whatsapp::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'wamid.abc123', $result['data']['whatsapp_message_id'] );
		$this->assertEquals( '15551234567', $result['data']['whatsapp_to'] );
		$this->assertEquals( 'sent', $result['data']['whatsapp_status'] );
	}

	public function test_send_text_throws_without_credentials(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/credentials/' );

		$node = $this->makeActionNode( 'send_text', [ 'to' => '15551234567', 'body' => 'Hi' ] );
		Whatsapp::execute_node( $node, [] );
	}

	public function test_send_text_throws_without_to(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/recipient/' );

		$node = $this->makeActionNode( 'send_text', [ 'body' => 'Hello' ], $this->credentials );
		Whatsapp::execute_node( $node, [] );
	}

	public function test_send_text_throws_without_body(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/message body/' );

		$node = $this->makeActionNode( 'send_text', [ 'to' => '15551234567' ], $this->credentials );
		Whatsapp::execute_node( $node, [] );
	}

	public function test_send_text_throws_on_api_error(): void {
		$this->mockHttp( [
			'error' => [
				'message' => 'Invalid phone number',
				'code'    => 100,
			],
		], 400 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/Invalid phone number/' );

		$node = $this->makeActionNode( 'send_text', [ 'to' => 'bad-number', 'body' => 'Hi' ], $this->credentials );
		Whatsapp::execute_node( $node, [] );
	}

	// ========== ACTION: send_template ==========

	public function test_send_template_succeeds(): void {
		$this->mockHttp( [
			'messages' => [ [ 'id' => 'wamid.tpl123' ] ],
		] );

		$node   = $this->makeActionNode( 'send_template', [
			'to'            => '15551234567',
			'template_name' => 'hello_world',
			'language_code' => 'en_US',
		], $this->credentials );
		$result = Whatsapp::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'wamid.tpl123', $result['data']['whatsapp_message_id'] );
	}

	public function test_send_template_throws_without_template_name(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/template name/' );

		$node = $this->makeActionNode( 'send_template', [ 'to' => '15551234567' ], $this->credentials );
		Whatsapp::execute_node( $node, [] );
	}

	// ========== ACTION: send_image ==========

	public function test_send_image_succeeds(): void {
		$this->mockHttp( [
			'messages' => [ [ 'id' => 'wamid.img123' ] ],
		] );

		$node   = $this->makeActionNode( 'send_image', [
			'to'      => '15551234567',
			'link'    => 'https://example.com/image.jpg',
			'caption' => 'Check this out!',
		], $this->credentials );
		$result = Whatsapp::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'wamid.img123', $result['data']['whatsapp_message_id'] );
	}

	public function test_send_image_throws_without_link(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/image URL/' );

		$node = $this->makeActionNode( 'send_image', [ 'to' => '15551234567' ], $this->credentials );
		Whatsapp::execute_node( $node, [] );
	}

	// ========== ACTION: send_document ==========

	public function test_send_document_succeeds(): void {
		$this->mockHttp( [
			'messages' => [ [ 'id' => 'wamid.doc123' ] ],
		] );

		$node   = $this->makeActionNode( 'send_document', [
			'to'       => '15551234567',
			'link'     => 'https://example.com/doc.pdf',
			'filename' => 'report.pdf',
			'caption'  => 'Here is your report',
		], $this->credentials );
		$result = Whatsapp::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'wamid.doc123', $result['data']['whatsapp_message_id'] );
	}

	public function test_send_document_throws_without_link(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/document URL/' );

		$node = $this->makeActionNode( 'send_document', [ 'to' => '15551234567' ], $this->credentials );
		Whatsapp::execute_node( $node, [] );
	}

	// ========== ACTION: send_video ==========

	public function test_send_video_succeeds(): void {
		$this->mockHttp( [
			'messages' => [ [ 'id' => 'wamid.vid123' ] ],
		] );

		$node   = $this->makeActionNode( 'send_video', [
			'to'      => '15551234567',
			'link'    => 'https://example.com/video.mp4',
			'caption' => 'Watch this!',
		], $this->credentials );
		$result = Whatsapp::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'wamid.vid123', $result['data']['whatsapp_message_id'] );
	}

	public function test_send_video_throws_without_link(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/video URL/' );

		$node = $this->makeActionNode( 'send_video', [ 'to' => '15551234567' ], $this->credentials );
		Whatsapp::execute_node( $node, [] );
	}

	// ========== ACTION: send_audio ==========

	public function test_send_audio_succeeds(): void {
		$this->mockHttp( [
			'messages' => [ [ 'id' => 'wamid.aud123' ] ],
		] );

		$node   = $this->makeActionNode( 'send_audio', [
			'to'   => '15551234567',
			'link' => 'https://example.com/audio.mp3',
		], $this->credentials );
		$result = Whatsapp::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'wamid.aud123', $result['data']['whatsapp_message_id'] );
	}

	public function test_send_audio_throws_without_link(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/audio URL/' );

		$node = $this->makeActionNode( 'send_audio', [ 'to' => '15551234567' ], $this->credentials );
		Whatsapp::execute_node( $node, [] );
	}

	// ========== ACTION: send_location ==========

	public function test_send_location_succeeds(): void {
		$this->mockHttp( [
			'messages' => [ [ 'id' => 'wamid.loc123' ] ],
		] );

		$node   = $this->makeActionNode( 'send_location', [
			'to'        => '15551234567',
			'latitude'  => '40.7128',
			'longitude' => '-74.0060',
			'name'      => 'New York City',
			'address'   => 'New York, NY, USA',
		], $this->credentials );
		$result = Whatsapp::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'wamid.loc123', $result['data']['whatsapp_message_id'] );
	}

	public function test_send_location_throws_without_latitude(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/latitude/' );

		$node = $this->makeActionNode( 'send_location', [ 'to' => '15551234567', 'longitude' => '-74.0060' ], $this->credentials );
		Whatsapp::execute_node( $node, [] );
	}

	public function test_send_location_throws_without_longitude(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/longitude/' );

		$node = $this->makeActionNode( 'send_location', [ 'to' => '15551234567', 'latitude' => '40.7128' ], $this->credentials );
		Whatsapp::execute_node( $node, [] );
	}

	// ========== API error handling ==========

	public function test_wp_error_throws_exception(): void {
		// No HTTP response queued → wp_remote_post returns WP_Error automatically
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/request failed|Mock/' );

		$node = $this->makeActionNode( 'send_text', [ 'to' => '15551234567', 'body' => 'Hi' ], $this->credentials );
		Whatsapp::execute_node( $node, [] );
	}

	// ========== Unknown action passthrough ==========

	public function test_unknown_action_returns_passthrough(): void {
		$node   = $this->makeActionNode( 'nonexistent_action', [], $this->credentials );
		$result = Whatsapp::execute_node( $node, [ 'foo' => 'bar' ] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( [ 'foo' => 'bar' ], $result['data'] );
	}

	// ========== test_connection ==========

	public function test_connection_succeeds(): void {
		$this->mockHttp( [
			'id'           => '1234567890',
			'display_phone_number' => '+1 555 1234567',
			'verified_name' => 'Test Business',
		] );

		$result = Whatsapp::test_connection( $this->credentials );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'Test Business', $result['message'] );
	}

	public function test_connection_fails_without_access_token(): void {
		$result = Whatsapp::test_connection( [ 'phone_number_id' => '123' ] );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'access_token', $result['message'] );
	}

	public function test_connection_fails_without_phone_number_id(): void {
		$result = Whatsapp::test_connection( [ 'access_token' => 'EAAtest' ] );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'phone_number_id', $result['message'] );
	}

	public function test_connection_fails_on_api_error(): void {
		$this->mockHttp( [
			'error' => [
				'message' => 'Invalid OAuth access token',
				'code'    => 190,
			],
		], 401 );

		$result = Whatsapp::test_connection( $this->credentials );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Invalid OAuth access token', $result['message'] );
	}

	// ========== get_triggers: message_received (incoming webhook) ==========

	public function test_has_message_received_trigger(): void {
		$triggers = Whatsapp::get_triggers();

		$this->assertArrayHasKey( 'message_received', $triggers );
		$this->assertNotEmpty( $triggers['message_received']['hook'] ?? '' );
	}

	// ========== Webhook setup / Meta handshake ==========
	// Mirrors MessengerTest.php: Meta will not accept a callback URL until the
	// verify-token handshake succeeds, and WhatsApp shares the same base-class
	// verify_webhook_challenge() as Messenger, so it needs the same coverage.

	public function test_declares_the_setup_fields_meta_requires(): void {
		$keys = array_column( Whatsapp::get_webhook_setup_fields(), 'key' );

		$this->assertContains( 'verify_token', $keys );
		$this->assertContains( 'app_secret', $keys );
	}

	public function test_handshake_fails_until_a_verify_token_is_saved(): void {
		$request = $this->makeChallengeRequest( 'whatever' );

		$this->assertNull(
			Whatsapp::verify_webhook_challenge( $request ),
			'An unset verify token must reject, not silently pass.'
		);
	}

	public function test_handshake_echoes_the_challenge_when_the_token_matches(): void {
		update_option( 'zaplane_webhook_config', [ 'whatsapp' => [ 'verify_token' => 'my-token' ] ] );

		$this->assertSame( '1158201444', Whatsapp::verify_webhook_challenge( $this->makeChallengeRequest( 'my-token' ) ) );
		$this->assertNull( Whatsapp::verify_webhook_challenge( $this->makeChallengeRequest( 'wrong-token' ) ) );
	}

	public function test_app_secret_reads_from_the_shared_config(): void {
		update_option( 'zaplane_webhook_config', [ 'whatsapp' => [ 'app_secret' => 'sh4red' ] ] );

		$this->assertSame( 'sh4red', Whatsapp::get_webhook_app_secret() );
	}

	// ========== parse_webhook_event: WhatsApp Cloud API payload shape ==========

	public function test_parse_webhook_event_extracts_an_inbound_text_message(): void {
		$parsed = Whatsapp::parse_webhook_event( $this->makeEventRequest( [
			'entry' => [ [ 'changes' => [ [ 'value' => [
				'metadata' => [ 'phone_number_id' => '1234567890' ],
				'contacts' => [ [ 'profile' => [ 'name' => 'John Doe' ] ] ],
				'messages' => [ [
					'id'        => 'wamid.abc123',
					'from'      => '15551234567',
					'timestamp' => '1700000000',
					'type'      => 'text',
					'text'      => [ 'body' => 'Hello, I need help with my order' ],
				] ],
			] ] ] ] ],
		] ) );

		$this->assertSame( 'message_received', $parsed['event'] );
		$this->assertSame( 'Hello, I need help with my order', $parsed['payload']['text'] );
		$this->assertSame( '15551234567', $parsed['payload']['from'] );
		$this->assertSame( 'John Doe', $parsed['payload']['sender_name'] );
	}

	public function test_parse_webhook_event_ignores_status_updates(): void {
		// Delivery/read receipts carry a `statuses` array, not `messages` — must
		// be skipped rather than treated as an inbound message.
		$this->assertNull( Whatsapp::parse_webhook_event( $this->makeEventRequest( [
			'entry' => [ [ 'changes' => [ [ 'value' => [
				'statuses' => [ [ 'id' => 'wamid.abc123', 'status' => 'delivered' ] ],
			] ] ] ] ],
		] ) ) );
	}

	public function test_parse_webhook_event_dedupes_repeated_message_ids(): void {
		$request_body = [
			'entry' => [ [ 'changes' => [ [ 'value' => [
				'messages' => [ [
					'id'        => 'wamid.dupe1',
					'from'      => '15551234567',
					'timestamp' => '1700000000',
					'type'      => 'text',
					'text'      => [ 'body' => 'Hi' ],
				] ],
			] ] ] ] ],
		];

		$first  = Whatsapp::parse_webhook_event( $this->makeEventRequest( $request_body ) );
		$second = Whatsapp::parse_webhook_event( $this->makeEventRequest( $request_body ) );

		$this->assertSame( 'message_received', $first['event'] );
		$this->assertNull( $second, 'Meta retries webhooks until it gets a 200 — a repeated message id must not fire the workflow twice.' );
	}

	// ========== resolve_trigger ==========

	public function test_trigger_rejects_empty_payload(): void {
		$this->assertFalse( Whatsapp::resolve_trigger( $this->makeTriggerNode( 'message_received' ), [] ) );
	}

	public function test_trigger_rejects_our_own_echo(): void {
		$node = $this->makeTriggerNode( 'message_received' );

		$this->assertFalse( Whatsapp::resolve_trigger( $node, [ [ 'is_echo' => true, 'text' => 'sent by us' ] ] ) );
	}

	public function test_trigger_returns_the_parsed_payload(): void {
		$node    = $this->makeTriggerNode( 'message_received' );
		$payload = [ 'message_id' => 'wamid.abc123', 'from' => '15551234567', 'text' => 'Hi' ];

		$this->assertSame( $payload, Whatsapp::resolve_trigger( $node, [ $payload ] ) );
	}

	private function makeChallengeRequest( string $token ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/zaplane/v1/incoming/whatsapp' );
		$request->set_param( 'hub_mode', 'subscribe' );
		$request->set_param( 'hub_verify_token', $token );
		$request->set_param( 'hub_challenge', '1158201444' );

		return $request;
	}

	private function makeEventRequest( array $body ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/zaplane/v1/incoming/whatsapp' );
		$request->set_body( (string) wp_json_encode( $body ) );
		$request->set_header( 'content-type', 'application/json' );

		return $request;
	}

	// ========== Schema coverage for all 7 actions ==========

	public function test_all_action_schemas_have_to_field(): void {
		$actions = Whatsapp::get_actions();
		foreach ( array_keys( $actions ) as $action ) {
			$schema = Whatsapp::get_action_config_schema( $action );
			$keys   = array_column( $schema, 'key' );
			$this->assertContains( 'to', $keys, "Action '{$action}' schema is missing the 'to' field" );
		}
	}
}
