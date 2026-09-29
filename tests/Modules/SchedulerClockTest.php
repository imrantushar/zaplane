<?php

namespace Zaplane\Tests\Modules;

use Zaplane\Tests\TestCase;
use Zaplane\Scheduler\Scheduler;

class SchedulerClockTest extends TestCase {

	public function test_a_schedule_that_never_ran_reads_zero(): void {
		$this->assertSame( 0, Scheduler::last_run( 7, '3' ) );
	}

	public function test_each_schedule_trigger_keeps_its_own_clock(): void {
		Scheduler::mark_run( 7, '3', 1000 );
		Scheduler::mark_run( 7, '5', 2000 );

		$this->assertSame( 1000, Scheduler::last_run( 7, '3' ) );
		$this->assertSame( 2000, Scheduler::last_run( 7, '5' ) );
	}

	public function test_the_old_per_workflow_clock_counts_until_the_trigger_has_its_own(): void {
		// Written before each trigger had a clock of its own.
		update_option( 'zaplane_sched_last_7', 1500 );

		$this->assertSame( 1500, Scheduler::last_run( 7, '3' ) );

		Scheduler::mark_run( 7, '3', 3000 );

		$this->assertSame( 3000, Scheduler::last_run( 7, '3' ) );
		$this->assertSame( 0, Scheduler::last_run( 7, '9' ), 'A schedule added later starts fresh.' );
	}
}
