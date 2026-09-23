<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Memory;

/**
 * Memory: a conversation's running history. Reads and writes go to the
 * database, so only the guards that run before that are checked here.
 */
class MemoryTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Memory::class;
	}

	public function test_every_action_needs_a_conversation_key(): void {
		foreach ( array_keys( Memory::get_actions() ) as $event ) {
			$out = Memory::execute_node( $this->makeActionNode( $event, [] ), [ 'keep' => 1 ] );
			$this->assertFalse( $out['data']['success'], "Action '{$event}' ran without a conversation key" );
			$this->assertSame( 'conversation_key is required.', $out['data']['error'] );
			$this->assertSame( 1, $out['data']['keep'], 'the step keeps the data it was given' );
		}
	}
}
