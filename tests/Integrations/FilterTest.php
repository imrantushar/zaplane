<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Filter;

/**
 * Contract-only stub for the Filter control-flow integration.
 *
 * NOTE: this test currently fails actions_execute_and_return_valid_format
 * because Filter::execute_node returns ['pass' => bool, 'data' => ...] —
 * the contract expects ['port' => ..., 'data' => ...]. That is the bug
 * the audit flagged. Fix Filter::execute_node to emit `port` and this
 * test passes.
 */
class FilterTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Filter::class;
	}

	protected function getActionTests(): array {
		return [
			'filter' => [
				'conditions' => [
					'logic'      => 'AND',
					'conditions' => [],
				],
			],
		];
	}
}
