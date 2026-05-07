<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Mailchimp;

class MailchimpTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Mailchimp::class;
	}

	protected function getTriggerTests(): array {
		return [
			'subscribed'      => [ [ 'mailchimp_email' => 'subscribed@example.com' ] ],
			'unsubscribed'    => [ [ 'mailchimp_email' => 'unsubscribed@example.com' ] ],
			'profile_updated' => [ [ 'mailchimp_email' => 'profile@example.com' ] ],
			'cleaned'         => [ [ 'mailchimp_email' => 'cleaned@example.com' ] ],
			'email_changed'   => [ [ 'mailchimp_email' => 'changed@example.com' ] ],
			'campaign_sent'   => [ [ 'mailchimp_campaign_id' => 'campaign-1' ] ],
		];
	}

	/** @test */
	public function parse_webhook_event_handles_form_encoded_mailchimp_payload(): void {
		$request = new \WP_REST_Request( 'POST', '/zaplane/v1/incoming/mailchimp' );
		$request->set_body(
			'type=subscribe&fired_at=2026-03-18+12%3A00%3A00&data%5Bid%5D=abc123&data%5Blist_id%5D=list123&data%5Bemail%5D=test%40example.com&data%5Bemail_type%5D=html&data%5Bmerges%5D%5BFNAME%5D=Test'
		);

		$parsed = Mailchimp::parse_webhook_event( $request );

		$this->assertIsArray( $parsed );
		$this->assertSame( 'subscribed', $parsed['event'] );
		$this->assertSame( 'subscribe', $parsed['payload']['mailchimp_event'] );
		$this->assertSame( 'test@example.com', $parsed['payload']['mailchimp_email'] );
		$this->assertSame( 'list123', $parsed['payload']['mailchimp_list_id'] );
		$this->assertSame( 'Test', $parsed['payload']['mailchimp_merges']['FNAME'] );
	}

	/** @test */
	public function verify_webhook_signature_accepts_matching_secret(): void {
		update_option( 'zaplane_mailchimp_webhook_secret', 'secret-123' );

		$request = new \WP_REST_Request( 'POST', '/zaplane/v1/incoming/mailchimp' );
		$request->set_param( 'secret', 'secret-123' );

		$this->assertTrue( Mailchimp::verify_webhook_signature( $request ) );
	}

	/** @test */
	public function verify_webhook_signature_rejects_wrong_secret(): void {
		update_option( 'zaplane_mailchimp_webhook_secret', 'secret-123' );

		$request = new \WP_REST_Request( 'POST', '/zaplane/v1/incoming/mailchimp' );
		$request->set_param( 'secret', 'secret-456' );

		$this->assertFalse( Mailchimp::verify_webhook_signature( $request ) );
	}
}
