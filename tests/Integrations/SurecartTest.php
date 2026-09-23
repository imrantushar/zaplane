<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Surecart;

/**
 * SureCart: the contract only. Its actions all call into the SureCart plugin,
 * which is not loaded in this suite, so behaviour is covered by the recipe
 * runs against a real site rather than here. These checks still hold the
 * registration surface: slugs, labels, hooks, config schemas and ports.
 */
class SurecartTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Surecart::class;
	}

	public function test_every_action_is_implemented_not_a_silent_pass_through(): void {
		$probe = [ '__probe' => 1 ];
		foreach ( array_keys( Surecart::get_actions() ) as $event ) {
			try {
				$out = Surecart::execute_node( $this->makeActionNode( $event, [] ), $probe );
			} catch ( \Throwable $e ) {
				continue; // Reached real code and needed the host plugin or real config.
			}
			$this->assertNotSame( $probe, $out['data'] ?? null, "Action '{$event}' silently returns its input: it has no handler." );
		}
	}
}
