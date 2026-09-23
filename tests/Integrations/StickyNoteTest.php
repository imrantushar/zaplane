<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\StickyNote;

/**
 * Sticky Note: a canvas annotation that never changes the data.
 */
class StickyNoteTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return StickyNote::class;
	}

	public function test_passes_the_input_through_untouched(): void {
		$out = StickyNote::execute_node( $this->makeActionNode( 'note', [ 'text' => 'hi' ] ), [ 'a' => [ 'b' => 1 ] ] );
		$this->assertSame( 'main', $out['port'] );
		$this->assertSame( [ 'a' => [ 'b' => 1 ] ], $out['data'] );
	}
}
