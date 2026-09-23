<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\FluentCart;

/**
 * FluentCart: the contract only. Its actions all call into the FluentCart plugin,
 * which is not loaded in this suite, so behaviour is covered by the recipe
 * runs against a real site rather than here. These checks still hold the
 * registration surface: slugs, labels, hooks, config schemas and ports.
 */
class FluentCartTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return FluentCart::class;
	}

	public function test_every_action_is_implemented_not_a_silent_pass_through(): void {
		$probe = [ '__probe' => 1 ];
		foreach ( array_keys( FluentCart::get_actions() ) as $event ) {
			try {
				$out = FluentCart::execute_node( $this->makeActionNode( $event, [] ), $probe );
			} catch ( \Throwable $e ) {
				continue; // Reached real code and needed the host plugin or real config.
			}
			$this->assertNotSame( $probe, $out['data'] ?? null, "Action '{$event}' silently returns its input: it has no handler." );
		}
	}
}
