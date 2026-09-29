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

	// ========== ACTION: send_post ==========

	public function test_send_post_succeeds(): void {
		$this->mockHttp( [
			'ok'     => true,
			'result' => [ 'message_id' => 801, 'chat' => [ 'id' => 987654321 ], 'date' => 1700000008 ],
		] );

		$node   = $this->makeActionNode( 'send_post', [
			'chat_id' => '987654321',
			'text'    => 'Channel announcement',
		], $this->credentials );
		$result = Telegram::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 801, $result['data']['telegram_message_id'] );
		$this->assertEquals( '987654321', $result['data']['telegram_chat_id'] );
		$this->assertEquals( 'sent', $result['data']['telegram_status'] );
	}

	public function test_send_post_succeeds_with_inline_buttons(): void {
		$this->mockHttp( [
			'ok'     => true,
			'result' => [ 'message_id' => 802, 'chat' => [ 'id' => 987654321 ], 'date' => 1700000009 ],
		] );

		$node   = $this->makeActionNode( 'send_post', [
			'chat_id'        => '987654321',
			'text'           => 'Approve this order?',
			'inline_buttons' => 'Approve:approve_order_88, Reject:reject_order_88',
		], $this->credentials );
		$result = Telegram::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 802, $result['data']['telegram_message_id'] );
	}

	/**
	 * Buttons field is optional — blank input must not attach reply_markup
	 * or otherwise break the request.
	 */
	public function test_send_post_succeeds_without_inline_buttons(): void {
		$this->mockHttp( [
			'ok'     => true,
			'result' => [ 'message_id' => 803, 'chat' => [ 'id' => 987654321 ], 'date' => 1700000010 ],
		] );

		$node   = $this->makeActionNode( 'send_post', [
			'chat_id'        => '987654321',
			'text'           => 'Plain post, no buttons',
			'inline_buttons' => '',
		], $this->credentials );
		$result = Telegram::execute_node( $node, [] );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 803, $result['data']['telegram_message_id'] );
	}

	public function test_send_post_throws_without_chat_id(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/chat_id/' );

		$node = $this->makeActionNode( 'send_post', [ 'text' => 'Hello' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	public function test_send_post_throws_without_text(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/post text/' );

		$node = $this->makeActionNode( 'send_post', [ 'chat_id' => '987654321' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	public function test_send_post_throws_on_api_error(): void {
		$this->mockHttp( [
			'ok'          => false,
			'error_code'  => 400,
			'description' => 'Bad Request: chat not found',
		], 400 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/chat not found/' );

		$node = $this->makeActionNode( 'send_post', [ 'chat_id' => '000', 'text' => 'Hi' ], $this->credentials );
		Telegram::execute_node( $node, [] );
	}

	public function test_send_post_supports_parse_mode(): void {
		$this->mockHttp( [
			'ok'     => true,
			'result' => [ 'message_id' => 804, 'chat' => [ 'id' => 111 ], 'date' => 1700000011 ],
		] );

		$node   = $this->makeActionNode( 'send_post', [
			'chat_id'    => '111',
			'text'       => '<b>Bold post</b>',
			'parse_mode' => 'HTML',
		], $this->credentials );
		$result = Telegram::execute_node( $node, [] );

		$this->assertEquals( 804, $result['data']['telegram_message_id'] );
	}

	public function test_send_post_schema_has_inline_buttons_field(): void {
		$schema = Telegram::get_action_config_schema( 'send_post' );
		$keys   = array_column( $schema, 'key' );

		$this->assertContains( 'inline_buttons', $keys );

		$field = array_values( array_filter( $schema, fn( $f ) => 'inline_buttons' === $f['key'] ) )[0];
		$this->assertFalse( $field['required'], 'Inline Buttons field must be optional' );
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

	// ========== TRIGGER: callback_query_received ==========

	public function test_trigger_callback_query_received_returns_payload(): void {
		$args = [
			[
				'id'             => 'cbq_123',
				'from'           => [ 'id' => 111, 'first_name' => 'Alice', 'username' => 'alice' ],
				'message'        => [ 'message_id' => 55, 'chat' => [ 'id' => 111 ] ],
				'chat_instance'  => 'abc123',
				'data'           => 'approve_order_88',
			],
		];
		$node   = $this->makeTriggerNode( 'callback_query_received' );
		$result = Telegram::resolve_trigger( $node, $args );

		$this->assertIsArray( $result );
		$this->assertEquals( 'approve_order_88', $result['telegram_callback_data'] );
		$this->assertEquals( 'cbq_123', $result['telegram_callback_id'] );
		$this->assertEquals( 55, $result['telegram_message_id'] );
		$this->assertEquals( 111, $result['telegram_chat_id'] );
	}

	// ========== TRIGGER: inline_query_received ==========

	public function test_trigger_inline_query_received_returns_payload(): void {
		$args = [
			[
				'id'     => 'iq_456',
				'from'   => [ 'id' => 111, 'first_name' => 'Alice', 'username' => 'alice' ],
				'query'  => 'pizza',
				'offset' => '',
			],
		];
		$node   = $this->makeTriggerNode( 'inline_query_received' );
		$result = Telegram::resolve_trigger( $node, $args );

		$this->assertIsArray( $result );
		$this->assertEquals( 'pizza', $result['telegram_query_text'] );
		$this->assertEquals( 'iq_456', $result['telegram_inline_query_id'] );
	}

	// ========== TRIGGER: poll_received ==========

	public function test_trigger_poll_received_returns_payload(): void {
		$args = [
			[
				'id'                => 'poll_789',
				'question'          => 'Favourite colour?',
				'options'           => [
					[ 'text' => 'Red', 'voter_count' => 2 ],
					[ 'text' => 'Blue', 'voter_count' => 1 ],
				],
				'total_voter_count' => 3,
				'is_closed'         => false,
			],
		];
		$node   = $this->makeTriggerNode( 'poll_received' );
		$result = Telegram::resolve_trigger( $node, $args );

		$this->assertIsArray( $result );
		$this->assertEquals( 'poll_789', $result['telegram_poll_id'] );
		$this->assertEquals( 3, $result['telegram_poll_total_votes'] );
		$this->assertFalse( $result['telegram_poll_is_closed'] );
	}

	// ========== TRIGGER: pre_checkout_query_received ==========

	public function test_trigger_pre_checkout_query_received_returns_payload(): void {
		$args = [
			[
				'id'               => 'pcq_321',
				'from'             => [ 'id' => 111, 'username' => 'alice' ],
				'currency'         => 'USD',
				'total_amount'     => 2500,
				'invoice_payload'  => 'order_88',
			],
		];
		$node   = $this->makeTriggerNode( 'pre_checkout_query_received' );
		$result = Telegram::resolve_trigger( $node, $args );

		$this->assertIsArray( $result );
		$this->assertEquals( 'USD', $result['telegram_currency'] );
		$this->assertEquals( 2500, $result['telegram_total_amount'] );
	}

	// ========== TRIGGER: shipping_query_received ==========

	public function test_trigger_shipping_query_received_returns_payload(): void {
		$args = [
			[
				'id'               => 'sq_654',
				'from'             => [ 'id' => 111, 'username' => 'alice' ],
				'invoice_payload'  => 'order_88',
				'shipping_address' => [
					'country_code' => 'US',
					'state'        => 'NY',
					'city'         => 'New York',
					'post_code'    => '10001',
				],
			],
		];
		$node   = $this->makeTriggerNode( 'shipping_query_received' );
		$result = Telegram::resolve_trigger( $node, $args );

		$this->assertIsArray( $result );
		$this->assertEquals( 'US', $result['telegram_shipping_country'] );
		$this->assertEquals( '10001', $result['telegram_shipping_zip'] );
	}

	// ========== TRIGGER: edited_message / channel_post / edited_channel_post ==========

	public function test_trigger_edited_message_received_returns_payload(): void {
		$args = [
			[
				'message_id' => 60,
				'from'       => [ 'id' => 111, 'first_name' => 'Alice' ],
				'chat'       => [ 'id' => 111, 'type' => 'private' ],
				'text'       => 'Edited text',
				'date'       => 1700000000,
				'edit_date'  => 1700000050,
			],
		];
		$node   = $this->makeTriggerNode( 'edited_message_received' );
		$result = Telegram::resolve_trigger( $node, $args );

		$this->assertEquals( 'edited_message', $result['telegram_update_type'] );
		$this->assertEquals( 1700000050, $result['telegram_edit_date'] );
	}

	public function test_trigger_channel_post_received_returns_payload(): void {
		$args = [
			[
				'message_id' => 70,
				'chat'       => [ 'id' => -1001234567890, 'type' => 'channel' ],
				'text'       => 'Announcement',
				'date'       => 1700000000,
			],
		];
		$node   = $this->makeTriggerNode( 'channel_post_received' );
		$result = Telegram::resolve_trigger( $node, $args );

		$this->assertEquals( 'channel_post', $result['telegram_update_type'] );
		$this->assertEquals( -1001234567890, $result['telegram_chat_id'] );
	}

	public function test_trigger_edited_channel_post_received_returns_payload(): void {
		$args = [
			[
				'message_id' => 71,
				'chat'       => [ 'id' => -1001234567890, 'type' => 'channel' ],
				'text'       => 'Announcement (edited)',
				'date'       => 1700000000,
				'edit_date'  => 1700000060,
			],
		];
		$node   = $this->makeTriggerNode( 'edited_channel_post_received' );
		$result = Telegram::resolve_trigger( $node, $args );

		$this->assertEquals( 'edited_channel_post', $result['telegram_update_type'] );
	}

	// ========== TRIGGER: all_updates (shape detection) ==========

	public function test_trigger_all_updates_detects_message_shape(): void {
		$args = [
			[
				'message_id' => 80,
				'chat'       => [ 'id' => 111, 'type' => 'private' ],
				'text'       => 'Hi',
				'date'       => 1700000000,
			],
		];
		$node   = $this->makeTriggerNode( 'all_updates' );
		$result = Telegram::resolve_trigger( $node, $args );

		$this->assertEquals( 'message', $result['telegram_update_type'] );
	}

	public function test_trigger_all_updates_detects_callback_query_shape(): void {
		$args = [
			[
				'id'            => 'cbq_1',
				'chat_instance' => 'abc',
				'data'          => 'x',
				'message'       => [ 'message_id' => 1, 'chat' => [ 'id' => 111 ] ],
			],
		];
		$node   = $this->makeTriggerNode( 'all_updates' );
		$result = Telegram::resolve_trigger( $node, $args );

		$this->assertEquals( 'callback_query', $result['telegram_update_type'] );
	}

	public function test_trigger_all_updates_detects_poll_shape(): void {
		$args = [
			[ 'id' => 'p1', 'question' => 'Q?', 'options' => [], 'total_voter_count' => 0, 'is_closed' => false ],
		];
		$node   = $this->makeTriggerNode( 'all_updates' );
		$result = Telegram::resolve_trigger( $node, $args );

		$this->assertEquals( 'poll', $result['telegram_update_type'] );
	}

	// ========== resolve_trigger: connection_id routing ==========

	public function test_resolve_trigger_skips_mismatched_connection_id(): void {
		$node = $this->makeTriggerNode( 'message_received' );
		$node['connection_id'] = 5;

		$payload = [
			'message_id' => 1,
			'chat'       => [ 'id' => 111, 'type' => 'private' ],
			'text'       => 'Hi',
		];

		// Delivered on a different bot's per-connection URL (id = 9) — must be skipped.
		$this->assertFalse( Telegram::resolve_trigger( $node, [ $payload, 9 ] ) );

		// Same connection id — must resolve.
		$result = Telegram::resolve_trigger( $node, [ $payload, 5 ] );
		$this->assertIsArray( $result );
	}

	public function test_resolve_trigger_allows_legacy_shared_url_regardless_of_connection(): void {
		$node = $this->makeTriggerNode( 'message_received' );
		$node['connection_id'] = 5;

		$payload = [
			'message_id' => 1,
			'chat'       => [ 'id' => 111, 'type' => 'private' ],
			'text'       => 'Hi',
		];

		// null source_connection_id = legacy shared URL, no id in the path.
		$result = Telegram::resolve_trigger( $node, [ $payload, null ] );
		$this->assertIsArray( $result );
	}

	// ========== get_update_type_for_event / get_allowed_updates_for_events ==========

	public function test_get_update_type_for_event_maps_correctly(): void {
		$this->assertEquals( 'message', Telegram::get_update_type_for_event( 'message_received' ) );
		$this->assertEquals( 'message', Telegram::get_update_type_for_event( 'command_received' ) );
		$this->assertEquals( 'callback_query', Telegram::get_update_type_for_event( 'callback_query_received' ) );
		$this->assertEquals( '', Telegram::get_update_type_for_event( 'unknown_event' ) );
	}

	public function test_get_allowed_updates_for_events_deduplicates(): void {
		$result = Telegram::get_allowed_updates_for_events( [ 'message_received', 'command_received' ] );

		// Both map to 'message' — must appear only once.
		$this->assertEquals( [ 'message' ], $result );
	}

	public function test_get_allowed_updates_for_events_expands_all_updates(): void {
		$result = Telegram::get_allowed_updates_for_events( [ 'all_updates' ] );

		$this->assertContains( 'message', $result );
		$this->assertContains( 'callback_query', $result );
		$this->assertContains( 'poll', $result );
		$this->assertCount( 9, $result );
	}

	// ========== parse_webhook_event: remaining update types ==========

	public function test_parse_webhook_event_routes_callback_query(): void {
		$parsed = Telegram::parse_webhook_event( $this->makeUpdateRequest( [
			'callback_query' => [
				'id'            => 'cbq_1',
				'chat_instance' => 'abc',
				'data'          => 'approve_order_88',
				'message'       => [ 'message_id' => 1, 'chat' => [ 'id' => 111 ] ],
			],
		] ) );

		$this->assertSame( 'callback_query_received', $parsed['event'] );
	}

	public function test_parse_webhook_event_routes_poll(): void {
		$parsed = Telegram::parse_webhook_event( $this->makeUpdateRequest( [
			'poll' => [
				'id'                => 'p1',
				'question'          => 'Q?',
				'options'           => [],
				'total_voter_count' => 0,
				'is_closed'         => false,
			],
		] ) );

		$this->assertSame( 'poll_received', $parsed['event'] );
	}

	public function test_parse_webhook_event_returns_null_for_unwatched_update_types(): void {
		// poll_answer, chat_member, my_chat_member, chosen_inline_result etc. aren't in $type_keys.
		$this->assertNull( Telegram::parse_webhook_event( $this->makeUpdateRequest( [
			'poll_answer' => [ 'poll_id' => 'p1' ],
		] ) ) );
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
