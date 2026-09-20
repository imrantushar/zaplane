<?php

namespace Zaplane\Tests\Mcp;

use Zaplane\Mcp\Alerts;
use Zaplane\Tests\TestCase;

class AlertsTest extends TestCase {

	/**
	 * update_option() writes nothing when the new value matches the old, and for
	 * an option that does not exist yet get_option() answers false — so storing
	 * a boolean false never created the row, and switching this off did nothing.
	 *
	 * @test
	 */
	public function switching_alerts_off_actually_persists(): void {
		$this->assertTrue( Alerts::enabled(), 'on unless said otherwise' );

		Alerts::set_enabled( false );
		$this->assertFalse( Alerts::enabled() );
		$this->assertSame( '0', get_option( 'zaplane_mcp_alerts' ), 'stored as a string, not a boolean' );

		Alerts::set_enabled( true );
		$this->assertTrue( Alerts::enabled() );
	}

	/**
	 * @test
	 */
	public function nothing_is_considered_while_alerts_are_off(): void {
		Alerts::set_enabled( false );

		// consider() must return without touching transients or mail.
		Alerts::consider( [ 'id' => 'abc', 'name' => 'Client' ], 'run_workflow', 'ok' );

		$this->assertFalse( get_transient( 'zaplane_mcp_ran_' . md5( 'abc' ) ) );
	}
}
