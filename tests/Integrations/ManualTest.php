<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Manual;

/**
 * Manual trigger: runs on "Run" or zaplane_run_workflow( id, data ).
 */
class ManualTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Manual::class;
	}

	public function test_the_data_passed_in_becomes_the_payload(): void {
		$node = $this->makeTriggerNode( 'run_manually' );
		$this->assertSame( [ 'order_id' => 5 ], Manual::resolve_trigger( $node, [ [ 'order_id' => 5 ] ] ) );
		$this->assertSame( [ 'value' => 'hello' ], Manual::resolve_trigger( $node, [ 'hello' ] ) );
		$this->assertSame( [], Manual::resolve_trigger( $node, [] ) );
	}
}
