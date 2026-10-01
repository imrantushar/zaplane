<?php

namespace Zaplane\Tests\Features;

use Zaplane\Services\RemoteTriggerBridge;
use Zaplane\Tests\WPMocks;

/**
 * The schedule the trigger bridge keeps, and how the site's chosen interval
 * replaces the schedule when it changes.
 */
class RemoteTriggerScheduleTest extends \Zaplane\Tests\TestCase {

	protected function setUp(): void {
		parent::setUp();
		WPMocks::reset();
	}

	protected function tearDown(): void {
		WPMocks::reset();
		parent::tearDown();
	}

	public function test_the_default_interval_is_sixty_seconds(): void {
		RemoteTriggerBridge::schedule();

		$scheduled = WPMocks::getScheduledAction( RemoteTriggerBridge::HOOK );

		$this->assertNotFalse( $scheduled );
		$this->assertGreaterThanOrEqual( time() + 59, $scheduled );
		$this->assertSame( 60, get_option( 'zaplane_remote_poll_interval' ) );
	}

	public function test_the_interval_filter_changes_the_schedule(): void {
		$five = static fn (): int => 5;
		add_filter( 'zaplane_remote_trigger_poll_interval', $five );

		try {
			RemoteTriggerBridge::schedule();

			$scheduled = WPMocks::getScheduledAction( RemoteTriggerBridge::HOOK );

			$this->assertNotFalse( $scheduled );
			$this->assertLessThanOrEqual( time() + 5, $scheduled );
			$this->assertSame( 5, get_option( 'zaplane_remote_poll_interval' ) );
		} finally {
			remove_filter( 'zaplane_remote_trigger_poll_interval', $five );
		}
	}

	public function test_changing_the_interval_replaces_the_old_schedule(): void {
		$five = static fn (): int => 5;
		add_filter( 'zaplane_remote_trigger_poll_interval', $five );

		try {
			RemoteTriggerBridge::schedule();

			$first = WPMocks::getScheduledAction( RemoteTriggerBridge::HOOK );
			$this->assertNotFalse( $first );

			$ten = static fn (): int => 10;
			add_filter( 'zaplane_remote_trigger_poll_interval', $ten );

			RemoteTriggerBridge::schedule();

			$second = WPMocks::getScheduledAction( RemoteTriggerBridge::HOOK );

			$this->assertNotFalse( $second );
			$this->assertLessThanOrEqual( time() + 10, $second );
			$this->assertSame( 10, get_option( 'zaplane_remote_poll_interval' ) );
		} finally {
			remove_filter( 'zaplane_remote_trigger_poll_interval', $five );
			remove_filter( 'zaplane_remote_trigger_poll_interval', $ten );
		}
	}

	public function test_an_interval_below_one_second_falls_back_to_the_default(): void {
		$zero = static fn (): int => 0;
		add_filter( 'zaplane_remote_trigger_poll_interval', $zero );

		try {
			RemoteTriggerBridge::schedule();

			$this->assertSame( 60, get_option( 'zaplane_remote_poll_interval' ) );
			$this->assertGreaterThanOrEqual( time() + 59, WPMocks::getScheduledAction( RemoteTriggerBridge::HOOK ) );
		} finally {
			remove_filter( 'zaplane_remote_trigger_poll_interval', $zero );
		}
	}
}
