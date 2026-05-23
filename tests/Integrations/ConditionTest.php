<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Condition;

/**
 * Contract-only stub for the Condition control-flow integration. The
 * default-action-config for `if` in IntegrationTestCase uses an
 * `expression` shape; Condition's real schema uses `conditions`, so we
 * override here with an empty AND group (which evaluates to true and
 * routes to the `true` port).
 */
class ConditionTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Condition::class;
	}

	protected function getActionTests(): array {
		return [
			'if' => [
				'conditions' => [
					'logic'      => 'AND',
					'conditions' => [],
				],
			],
		];
	}
}
