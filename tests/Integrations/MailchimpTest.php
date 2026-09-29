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

	public function test_email_changed_webhook_resolves_the_selected_trigger(): void {
		$request = new WP_REST_Request( 'POST', '/zaplane/v1/incoming/mailchimp' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				[
					'type' => 'upemail',
					'data' => [
						'list_id'   => 'a6b5da1054',
						'new_id'    => 'newid789',
						'new_email' => 'jane.new@example.com',
						'old_email' => 'jane@example.com',
					],
				]
			)
		);

		$parsed = Mailchimp::parse_webhook_event( $request );

		$this->assertSame( 'email_changed', $parsed['event'] );
		$this->assertSame( 'jane@example.com', $parsed['payload']['mailchimp_old_email'] );
		$this->assertSame( 'jane.new@example.com', $parsed['payload']['mailchimp_new_email'] );
		$this->assertSame( 'newid789', $parsed['payload']['mailchimp_member_id'] );
		$this->assertIsArray(
			Mailchimp::resolve_trigger(
				$this->makeTriggerNode( 'email_changed' ),
				[ $parsed['payload'] ]
			)
		);
	}

	public function test_campaign_webhook_resolves_without_an_audience_filter(): void {
		$request = new WP_REST_Request( 'POST', '/zaplane/v1/incoming/mailchimp' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				[
					'type' => 'campaign',
					'data' => [
						'id'     => 'campaign_abc123',
						'subject' => 'Our July Newsletter',
						'status' => 'sent',
					],
				]
			)
		);

		$parsed = Mailchimp::parse_webhook_event( $request );

		$this->assertSame( 'campaign_sent', $parsed['event'] );
		$this->assertSame( 'campaign_abc123', $parsed['payload']['mailchimp_campaign_id'] );
		$this->assertIsArray(
			Mailchimp::resolve_trigger(
				$this->makeTriggerNode( 'campaign_sent' ),
				[ $parsed['payload'] ]
			)
		);
	}

	public function test_all_mailchimp_webhook_types_resolve_to_triggers(): void {
		$types = [
			'subscribe'   => 'subscribed',
			'unsubscribe' => 'unsubscribed',
			'profile'     => 'profile_updated',
			'cleaned'     => 'cleaned',
			'upemail'     => 'email_changed',
			'campaign'    => 'campaign_sent',
		];

		foreach ( $types as $type => $event ) {
			$request = new WP_REST_Request( 'POST', '/zaplane/v1/incoming/mailchimp' );
			$request->set_body( http_build_query( [
				'type' => $type,
				'data' => [ 'list_id' => 'a6b5da1054' ],
			] ) );

			$parsed = Mailchimp::parse_webhook_event( $request );

			$this->assertSame( $event, $parsed['event'], $type );
			$this->assertIsArray(
				Mailchimp::resolve_trigger(
					$this->makeTriggerNode( $event ),
					[ $parsed['payload'] ]
				)
			);
		}
	}

	// ========== TRIGGER ==========

	public function test_trigger_does_not_require_an_audience_filter(): void {
		$payload = [
			'mailchimp_list_id' => 'a6b5da1054',
			'mailchimp_email'   => 'jane@example.com',
		];

		$this->assertIsArray(
			Mailchimp::resolve_trigger(
				$this->makeTriggerNode( 'subscribed' ),
				[ $payload ]
			)
		);
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
