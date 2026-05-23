<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Webhook;

/**
 * Contract-only stub for the Webhook integration.
 *
 * Note: ParityTest catches that Webhook declares supports_webhook() = true
 * but doesn't override verify_webhook_signature(). That's intentional —
 * the parity test should fail until Webhook ships a real signature check.
 */
class WebhookTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Webhook::class;
	}

	protected function getTriggerTests(): array {
		return [
			'incoming' => [ [ 'payload' => 'sample', 'event' => 'order.created' ] ],
		];
	}
}
