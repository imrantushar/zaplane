<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Schedule;

/**
 * Schedule trigger: fired by the scheduler tick.
 */
class ScheduleTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Schedule::class;
	}

	public function test_listens_to_the_scheduler_tick(): void {
		$this->assertSame( 'zaplane/schedule/tick', Schedule::get_triggers()['interval']['hook'] );
	}

	public function test_payload_is_the_tick_data_or_the_time(): void {
		$node = $this->makeTriggerNode( 'interval' );
		$this->assertSame( [ 'slot' => 'daily' ], Schedule::resolve_trigger( $node, [ [ 'slot' => 'daily' ] ] ) );
		$now = Schedule::resolve_trigger( $node, [] );
		$this->assertArrayHasKey( 'timestamp', $now );
		$this->assertEqualsWithDelta( time(), $now['unix'], 5 );
	}
}
