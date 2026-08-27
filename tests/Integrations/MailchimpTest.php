<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Mailchimp;
use WP_REST_Request;

/**
 * Covers the two things that stopped Mailchimp working end to end: actions ran
 * with no credentials because the connection UI was hidden, and the trigger
 * webhook could never be registered because Mailchimp's validation GET was
 * answered with a 403.
 */
class MailchimpTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Mailchimp::class;
	}

	// ========== CONNECTION ==========

	/**
	 * Every action authenticates with an API key. The dashboard hides both the
	 * Connections entry and the node's connection picker unless this is true, so
	 * declaring false left the credentials with no way in.
	 */
	public function test_requires_connection(): void {
		$this->assertTrue( Mailchimp::requires_connection() );
		$this->assertSame( 'api_key', Mailchimp::get_auth_type() );
	}

	public function test_auth_fields_declare_an_api_key(): void {
		$fields = Mailchimp::get_auth_fields();

		$this->assertArrayHasKey( 'api_key', $fields );
		$this->assertTrue( $fields['api_key']['required'] );
	}

	public function test_action_without_credentials_fails_loudly(): void {
		$node = $this->makeActionNode( 'upsert_subscriber', [
			'list_id' => 'a6b5da1054',
			'email'   => 'jane@example.com',
		] );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/API key/i' );

		Mailchimp::execute_node( $node, [] );
	}

	// ========== WEBHOOK DELIVERY ==========

	/**
	 * Mailchimp GETs the callback URL once and needs a 200 before it will save
	 * the webhook. It sends none of Meta's hub.* params, so the inherited
	 * handler rejected it and the webhook could never be created.
	 */
	public function test_validation_ping_is_accepted(): void {
		$request = new WP_REST_Request( 'GET', '/zaplane/v1/incoming/mailchimp' );

		$this->assertNotNull(
			Mailchimp::verify_webhook_challenge( $request ),
			'Mailchimp\'s validation GET must not be rejected, or the webhook cannot be saved.'
		);
	}

	public function test_webhook_url_is_absolute(): void {
		$this->assertStringContainsString( '/wp-json/zaplane/v1/incoming/mailchimp', Mailchimp::get_webhook_url() );
	}

	public function test_parse_webhook_event_maps_subscribe(): void {
		$request = new WP_REST_Request( 'POST', '/zaplane/v1/incoming/mailchimp' );
		$request->set_body( http_build_query( [
			'type'     => 'subscribe',
			'fired_at' => '2026-07-09 10:15:00',
			'data'     => [
				'id'      => 'abc123',
				'list_id' => 'a6b5da1054',
				'email'   => 'jane@example.com',
				'merges'  => [ 'FNAME' => 'Jane' ],
			],
		] ) );

		$parsed = Mailchimp::parse_webhook_event( $request );

		$this->assertSame( 'subscribed', $parsed['event'] );
		$this->assertSame( 'jane@example.com', $parsed['payload']['mailchimp_email'] );
		$this->assertSame( 'a6b5da1054', $parsed['payload']['mailchimp_list_id'] );
	}

	public function test_parse_webhook_event_ignores_unknown_types(): void {
		$request = new WP_REST_Request( 'POST', '/zaplane/v1/incoming/mailchimp' );
		$request->set_body( http_build_query( [ 'type' => 'something_else' ] ) );

		$this->assertNull( Mailchimp::parse_webhook_event( $request ) );
	}

	// ========== TRIGGER ==========

	public function test_trigger_applies_the_audience_filter(): void {
		$payload = [
			'mailchimp_list_id' => 'a6b5da1054',
			'mailchimp_email'   => 'jane@example.com',
		];

		$match = $this->makeTriggerNode( 'subscribed', [ 'list_id' => 'a6b5da1054' ] );
		$this->assertIsArray( Mailchimp::resolve_trigger( $match, [ $payload ] ) );

		$other = $this->makeTriggerNode( 'subscribed', [ 'list_id' => 'zzzzzzzzzz' ] );
		$this->assertFalse( Mailchimp::resolve_trigger( $other, [ $payload ] ) );
	}

	public function test_trigger_rejects_empty_payload(): void {
		$this->assertFalse( Mailchimp::resolve_trigger( $this->makeTriggerNode( 'subscribed' ), [] ) );
	}

	// ========== SETUP PANEL ==========

	public function test_declares_webhook_setup_fields(): void {
		$keys = array_column( Mailchimp::get_webhook_setup_fields(), 'key' );

		$this->assertContains( 'shared_secret', $keys );
	}
}
