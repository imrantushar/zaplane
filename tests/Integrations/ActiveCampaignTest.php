<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\ActiveCampaign;
use WP_REST_Request;

/**
 * Covers the move off the wp_loaded form-scrape onto real ActiveCampaign
 * webhooks, and the connection flag that left every action credential-less.
 */
class ActiveCampaignTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return ActiveCampaign::class;
	}

	// ========== CONNECTION ==========

	public function test_requires_connection(): void {
		$this->assertTrue( ActiveCampaign::requires_connection() );
		$this->assertSame( 'api_key', ActiveCampaign::get_auth_type() );
	}

	public function test_auth_fields_declare_url_and_key(): void {
		$fields = ActiveCampaign::get_auth_fields();

		$this->assertArrayHasKey( 'api_url', $fields );
		$this->assertArrayHasKey( 'api_key', $fields );
	}

	public function test_action_without_credentials_fails_loudly(): void {
		$node = $this->makeActionNode( 'create_or_update_contact', [ 'email' => 'jane@example.com' ] );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/credentials|API/i' );

		ActiveCampaign::execute_node( $node, [] );
	}

	// ========== TRIGGER DELIVERY ==========

	/**
	 * All three triggers used to hang off wp_loaded, which fires on every single
	 * request. Nothing here may bind to it again.
	 */
	public function test_no_trigger_binds_to_wp_loaded(): void {
		foreach ( ActiveCampaign::get_triggers() as $key => $trigger ) {
			$this->assertNotSame(
				'wp_loaded',
				$trigger['hook'] ?? '',
				"Trigger '{$key}' is bound to wp_loaded, which runs a handler on every request."
			);
		}
	}

	public function test_supports_incoming_webhooks(): void {
		$this->assertTrue( ActiveCampaign::supports_webhook() );
		$this->assertStringContainsString( '/wp-json/zaplane/v1/incoming/activecampaign', ActiveCampaign::get_webhook_url() );
	}

	public function test_parse_webhook_event_maps_subscribe(): void {
		$parsed = ActiveCampaign::parse_webhook_event( $this->makeWebhookRequest( [
			'type'    => 'subscribe',
			'list'    => [ '12' ],
			'contact' => [
				'id'         => '318',
				'email'      => 'jane.doe@example.com',
				'first_name' => 'Jane',
				'last_name'  => 'Doe',
			],
		] ) );

		$this->assertSame( 'contact_subscribed', $parsed['event'] );
		$this->assertSame( 'jane.doe@example.com', $parsed['payload']['contact']['email'] );
		$this->assertSame( [ [ 'id' => '12' ] ], $parsed['payload']['lists'] );
	}

	public function test_parse_webhook_event_maps_unsubscribe(): void {
		$parsed = ActiveCampaign::parse_webhook_event( $this->makeWebhookRequest( [
			'type'    => 'unsubscribe',
			'contact' => [ 'email' => 'jane.doe@example.com' ],
		] ) );

		$this->assertSame( 'contact_unsubscribed', $parsed['event'] );
		$this->assertSame( 'unsub', $parsed['payload']['action'] );
	}

	/**
	 * A subscribe that carries a form id is a form submission too — emit the
	 * more specific event so the Form Submitted trigger stays usable.
	 */
	public function test_subscribe_with_a_form_id_becomes_form_submitted(): void {
		$parsed = ActiveCampaign::parse_webhook_event( $this->makeWebhookRequest( [
			'type'    => 'subscribe',
			'form'    => '7',
			'contact' => [ 'email' => 'jane.doe@example.com' ],
		] ) );

		$this->assertSame( 'form_submitted', $parsed['event'] );
		$this->assertSame( '7', $parsed['payload']['form']['id'] );
	}

	public function test_parse_webhook_event_ignores_unmapped_types(): void {
		$this->assertNull( ActiveCampaign::parse_webhook_event( $this->makeWebhookRequest( [ 'type' => 'open' ] ) ) );
		$this->assertNull( ActiveCampaign::parse_webhook_event( $this->makeWebhookRequest( [] ) ) );
	}

	/**
	 * The webhook payload has to flow straight into resolve_trigger.
	 */
	public function test_parsed_payload_resolves_into_a_trigger(): void {
		$parsed = ActiveCampaign::parse_webhook_event( $this->makeWebhookRequest( [
			'type'    => 'subscribe',
			'list'    => [ '12' ],
			'contact' => [ 'email' => 'jane.doe@example.com', 'first_name' => 'Jane' ],
		] ) );

		$node   = $this->makeTriggerNode( 'contact_subscribed' );
		$result = ActiveCampaign::resolve_trigger( $node, [ $parsed['payload'] ] );

		$this->assertIsArray( $result );
		$this->assertSame( 'contact_subscribed', $result['event'] );
		$this->assertSame( 'jane.doe@example.com', $result['contact']['email'] );
	}

	public function test_trigger_rejects_empty_payload(): void {
		$this->assertFalse( ActiveCampaign::resolve_trigger( $this->makeTriggerNode( 'contact_subscribed' ), [] ) );
	}

	private function makeWebhookRequest( array $body ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/zaplane/v1/incoming/activecampaign' );
		$request->set_body_params( $body );

		return $request;
	}
}
