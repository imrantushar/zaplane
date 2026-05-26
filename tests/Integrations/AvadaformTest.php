<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Avadaform;

/**
 * Contract-only stub for the Avadaform integration. Inherits the full
 * IntegrationTestCase suite (slug + label + schema + trigger payload).
 * Hand-written cases for Avada-specific edge cases can be added here.
 */
class AvadaformTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Avadaform::class;
	}

	protected function getTriggerTests(): array {
		return [
			'submit_form' => [
				[ 'data' => [ 'name' => 'John', 'email' => 'john@example.com' ] ],
				42,
			],
		];
	}
}
