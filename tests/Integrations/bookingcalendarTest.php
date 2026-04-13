<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Bookingcalendar;

class BookingcalendarTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Bookingcalendar::class;
	}

	protected function getTriggerTests(): array {
		return [
			'dexbccf_process_data' => [
				[
					'startdate' => '04/05/2026',
					'enddate'   => '05/05/2026',
					'email'     => 'test@example.com',
					'subject'   => 'Test Subject',
					'message'   => 'Test Message',
					'totalcost' => 'USD 25.00',
				],
			],
		];
	}

	// =========================================================================
	// TRIGGER: dexbccf_process_data
	// =========================================================================

	public function test_trigger_returns_booking_fields(): void {
		$args = [
			[
				'startdate' => '04/05/2026',
				'enddate'   => '05/05/2026',
				'email'     => 'test@example.com',
				'subject'   => 'Test Subject',
				'message'   => 'Test Message',
				'totalcost' => 'USD 25.00',
				'formid'    => 1,
				'itemnumber' => '3',
			],
		];

		$result = Bookingcalendar::resolve_trigger(
			$this->makeTriggerNode( 'dexbccf_process_data' ),
			$args
		);

		$this->assertIsArray( $result );
		$this->assertEquals( '04/05/2026', $result['startdate'] );
		$this->assertEquals( '05/05/2026', $result['enddate'] );
		$this->assertEquals( 'test@example.com', $result['email'] );
		$this->assertEquals( 'Test Subject', $result['subject'] );
		$this->assertEquals( 'Test Message', $result['message'] );
		$this->assertEquals( 'USD 25.00', $result['totalcost'] );
		$this->assertEquals( 1, $result['formid'] );
		$this->assertEquals( '3', $result['itemnumber'] );
	}

	public function test_trigger_returns_empty_array_when_args_missing(): void {
		$result = Bookingcalendar::resolve_trigger(
			$this->makeTriggerNode( 'dexbccf_process_data' ),
			[]
		);

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	// =========================================================================
	// COMMON
	// =========================================================================

	public function test_trigger_returns_false_for_unknown_event(): void {
		$node          = $this->makeTriggerNode( 'dexbccf_process_data' );
		$node['event'] = '__unknown__';

		$this->assertFalse( Bookingcalendar::resolve_trigger( $node, [] ) );
	}

	public function test_execute_node_is_passthrough(): void {
		$input  = [ 'email' => 'test@example.com' ];
		$result = Bookingcalendar::execute_node( $this->makeActionNode( '__any__', [] ), $input );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $input, $result['data'] );
	}

	public function test_get_slug_returns_bookingcalendar(): void {
		$this->assertEquals( 'bookingcalendar', Bookingcalendar::get_slug() );
	}
}
