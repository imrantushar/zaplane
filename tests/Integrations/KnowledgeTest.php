<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Knowledge;

/**
 * Knowledge: the contract only. Retrieval and syncing need the knowledge
 * tables and an embedding provider, so they are exercised against a real
 * site instead of here.
 */
class KnowledgeTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Knowledge::class;
	}

	public function test_every_action_is_implemented_not_a_silent_pass_through(): void {
		$probe = [ '__probe' => 1 ];
		foreach ( array_keys( Knowledge::get_actions() ) as $event ) {
			try {
				$out = Knowledge::execute_node( $this->makeActionNode( $event, [] ), $probe );
			} catch ( \Throwable $e ) {
				continue;
			}
			$this->assertNotSame( $probe, $out['data'] ?? null, "Action '{$event}' silently returns its input: it has no handler." );
		}
	}
}
