<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Messenger;
use WP_REST_Request;

/**
 * Meta will not accept a callback URL until the verify-token handshake
 * succeeds, and nothing could set that token before — it was read from an
 * option no code and no screen ever wrote.
 */
class MessengerTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Messenger::class;
	}

	protected function tearDown(): void {
		delete_option( 'zaplane_webhook_config' );
		parent::tearDown();
	}

	public function test_declares_the_setup_fields_meta_requires(): void {
		$keys = array_column( Messenger::get_webhook_setup_fields(), 'key' );

		$this->assertContains( 'verify_token', $keys );
		$this->assertContains( 'app_secret', $keys );
	}

	public function test_handshake_fails_until_a_verify_token_is_saved(): void {
		$request = $this->makeChallengeRequest( 'whatever' );

		$this->assertNull(
			Messenger::verify_webhook_challenge( $request ),
			'An unset verify token must reject, not silently pass.'
		);
	}

	public function test_handshake_echoes_the_challenge_when_the_token_matches(): void {
		update_option( 'zaplane_webhook_config', [ 'messenger' => [ 'verify_token' => 'my-token' ] ] );

		$this->assertSame( '1158201444', Messenger::verify_webhook_challenge( $this->makeChallengeRequest( 'my-token' ) ) );
		$this->assertNull( Messenger::verify_webhook_challenge( $this->makeChallengeRequest( 'wrong-token' ) ) );
	}

	/**
	 * Sites that set the old standalone option by hand must keep working.
	 */
	public function test_handshake_still_reads_the_legacy_option(): void {
		update_option( 'zaplane_webhook_verify_token_messenger', 'legacy-token' );

		$this->assertSame( '1158201444', Messenger::verify_webhook_challenge( $this->makeChallengeRequest( 'legacy-token' ) ) );

		delete_option( 'zaplane_webhook_verify_token_messenger' );
	}

	public function test_app_secret_reads_from_the_shared_config(): void {
		update_option( 'zaplane_webhook_config', [ 'messenger' => [ 'app_secret' => 'sh4red' ] ] );

		$this->assertSame( 'sh4red', Messenger::get_webhook_app_secret() );
	}

	public function test_parse_webhook_event_extracts_an_inbound_message(): void {
		$parsed = Messenger::parse_webhook_event( $this->makeEventRequest( [
			'entry' => [ [ 'messaging' => [ [
				'sender'    => [ 'id' => '24607896878972' ],
				'recipient' => [ 'id' => '102990988765432' ],
				'timestamp' => '1700000000000',
				'message'   => [ 'mid' => 'm_abc123', 'text' => 'Hi, do you have this in stock?' ],
			] ] ] ],
		] ) );

		$messages = $this->messages( $parsed );
		$this->assertCount( 1, $messages );
		$this->assertSame( 'Hi, do you have this in stock?', $messages[0]['payload']['text'] );
		$this->assertSame( '24607896878972', $messages[0]['payload']['sender_id'] );
	}

	public function test_parse_webhook_event_hands_the_whole_delivery_to_webhook_received(): void {
		$request = $this->makeEventRequest( [
			'object' => 'page',
			'entry'  => [ [ 'messaging' => [
				[ 'sender' => [ 'id' => 'a' ], 'message' => [ 'mid' => 'm_whole_1', 'text' => 'one' ] ],
				[ 'sender' => [ 'id' => 'a' ], 'postback' => [ 'title' => 'Delivery times', 'payload' => 'ZAPLANE_STARTER_0' ] ],
			] ] ],
		] );
		$request->set_header( 'x_hub_signature_256', 'sha256=abc' );
		$parsed = Messenger::parse_webhook_event( $request );

		$whole = $parsed['events'][0];
		$this->assertSame( 'webhook_received', $whole['event'] );
		$this->assertSame( $request->get_body(), $whole['payload']['body'] );
		$this->assertSame( 'sha256=abc', $whole['payload']['signature'] );
		$this->assertSame( 2, $whole['payload']['events'] );
		$this->assertSame( $whole['payload'], Messenger::resolve_trigger( $this->makeTriggerNode( 'webhook_received' ), [ $whole['payload'] ] ) );
	}

	/**
	 * The one-per-message events ("Message Received"), without the
	 * whole-delivery one that comes first.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function messages( ?array $parsed ): array {
		return array_values( array_filter( (array) ( $parsed['events'] ?? [] ), static fn( $e ) => 'message_received' === $e['event'] ) );
	}

	public function test_parse_webhook_event_returns_every_message_in_a_batch(): void {
		$parsed = Messenger::parse_webhook_event( $this->makeEventRequest( [
			'entry' => [
				[ 'messaging' => [
					[ 'sender' => [ 'id' => 'a' ], 'message' => [ 'mid' => 'm_batch_1', 'text' => 'one' ] ],
					[ 'sender' => [ 'id' => 'b' ], 'message' => [ 'mid' => 'm_batch_2', 'text' => 'two' ] ],
				] ],
				[ 'messaging' => [
					[ 'sender' => [ 'id' => 'c' ], 'message' => [ 'mid' => 'm_batch_3', 'text' => 'three' ] ],
				] ],
			],
		] ) );

		$this->assertSame( [ 'one', 'two', 'three' ], array_map( static function ( $e ) {
			return $e['payload']['text'];
		}, $this->messages( $parsed ) ) );
	}

	public function test_parse_webhook_event_skips_our_own_echoes(): void {
		// The Inbox still reads it (as the delivery), but no workflow is
		// started as if the customer had written.
		$this->assertSame( [], $this->messages( Messenger::parse_webhook_event( $this->makeEventRequest( [
			'entry' => [ [ 'messaging' => [ [
				'sender'  => [ 'id' => '1' ],
				'message' => [ 'mid' => 'm_echo', 'text' => 'sent by us', 'is_echo' => true ],
			] ] ] ],
		] ) ) ) );
	}

	public function test_trigger_rejects_empty_payload(): void {
		$this->assertFalse( Messenger::resolve_trigger( $this->makeTriggerNode( 'message_received' ), [] ) );
	}

	private function makeChallengeRequest( string $token ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/zaplane/v1/incoming/messenger' );
		$request->set_param( 'hub_mode', 'subscribe' );
		$request->set_param( 'hub_verify_token', $token );
		$request->set_param( 'hub_challenge', '1158201444' );

		return $request;
	}

	private function makeEventRequest( array $body ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/zaplane/v1/incoming/messenger' );
		$request->set_body( (string) wp_json_encode( $body ) );
		$request->set_header( 'content-type', 'application/json' );

		return $request;
	}
}
