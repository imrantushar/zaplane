<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Iterator;

/**
 * Contract-only stub for the Iterator control-flow integration.
 * Empty source routes to the `done` port; both `loop` and `done`
 * are declared in get_output_ports.
 */
class IteratorTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Iterator::class;
	}

	protected function getActionTests(): array {
		return [
			'iterator' => [ 'source' => [] ],
		];
	}
}
