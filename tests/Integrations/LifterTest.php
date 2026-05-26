<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Lifter;

/**
 * Contract-only stub for the Lifter LMS integration. Quiz attempt
 * trigger needs an object arg, so it's omitted from the bulk list —
 * a hand-written quiz test can go here later.
 */
class LifterTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Lifter::class;
	}

	// Trigger-fire bulk tests are skipped here because Lifter's resolve_trigger
	// reads $user->first_name / $user->last_name and the global get_userdata
	// mock used by sibling tests (tests/mocks/wpuserfrontend.php) returns a
	// stdClass without those fields. The remaining contract tests
	// (label+hook+schema+slug+output_ports) still run.
}
