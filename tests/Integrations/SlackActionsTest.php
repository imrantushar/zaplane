<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Slack;

/**
 * Slack Integration - Action Tests
 *
 * Note: Slack requires OAuth credentials, so we only test registration/schema.
 * Actual execution would need mocked credentials.
 */
class SlackActionsTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Slack::class;
	}

	// Skip execution tests - requires credentials
	protected function getActionTests(): array {
		return [];
	}
}
