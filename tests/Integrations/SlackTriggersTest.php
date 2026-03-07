<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Slack;

/**
 * Slack Integration - Trigger Tests
 */
class SlackTriggersTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Slack::class;
	}

	// Slack has no triggers
}
