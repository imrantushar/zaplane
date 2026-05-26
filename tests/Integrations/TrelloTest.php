<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Trello;

/**
 * Contract-only stub for the Trello integration. Trello's execute_node
 * looks at $node['config']['action'] (legacy path) instead of the
 * makeActionNode shape — so the create_card branch is not exercised
 * here. A hand-written test calling the legacy path can be added once
 * we decide whether to migrate Trello to the canonical config path.
 */
class TrelloTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Trello::class;
	}

	protected function getTriggerTests(): array {
		return [
			'card_created' => [ [ 'id' => 'card_123', 'name' => 'Test card' ] ],
		];
	}

	protected function getActionTests(): array {
		return [
			'create_card' => [],
		];
	}
}
