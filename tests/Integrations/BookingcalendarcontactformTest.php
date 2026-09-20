<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Bookingcalendarcontactform;

class BookingcalendarcontactformTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Bookingcalendarcontactform::class;
	}

	protected function getTriggerTests(): array {
		return [
			'form_submitted' => [
				[
					[
						'startdate' => '04/05/2026',
						'enddate'   => '05/05/2026',
						'email'     => 'test@example.com',
						'subject'   => 'Test Subject',
						'message'   => 'Test Message',
						'totalcost' => 'USD 25.00',
					],
				],
			],
		];
	}

	public function test_trigger_returns_booking_fields(): void {

		$args = [
			[
				'formid'     => 1,
				'itemnumber' => '3',
				'startdate'  => '04/05/2026',
				'enddate'    => '05/05/2026',
				'totalcost'  => 'USD 25.00',
				'discount'   => '5',
				'coupon'     => 'SAVE5',
				'service'    => 'Room Booking',
				'email'      => 'test@example.com',
				'subject'    => 'Test Subject',
				'message'    => 'Test Message',
			],
		];

		$result = Bookingcalendarcontactform::resolve_trigger(
			$this->makeTriggerNode( 'form_submitted' ),
			$args
		);

		$this->assertIsArray( $result );

		$this->assertEquals( 1, $result['form_id'] );
		$this->assertEquals( '3', $result['item_number'] );
		$this->assertEquals( '04/05/2026', $result['start_date'] );
		$this->assertEquals( '05/05/2026', $result['end_date'] );
		$this->assertEquals( 'USD 25.00', $result['total_cost'] );
		$this->assertEquals( '5', $result['discount'] );
		$this->assertEquals( 'SAVE5', $result['coupon'] );
		$this->assertEquals( 'Room Booking', $result['service'] );
		$this->assertEquals( 'test@example.com', $result['email'] );
		$this->assertEquals( 'Test Subject', $result['subject'] );
		$this->assertEquals( 'Test Message', $result['message'] );
	}

	public function test_trigger_returns_false_when_args_missing(): void {

		$result = Bookingcalendarcontactform::resolve_trigger(
			$this->makeTriggerNode( 'form_submitted' ),
			[]
		);

		$this->assertFalse( $result );
	}

	public function test_trigger_returns_false_for_unknown_event(): void {

		$node          = $this->makeTriggerNode( 'form_submitted' );
		$node['event'] = '__unknown__';

		$this->assertFalse(
			Bookingcalendarcontactform::resolve_trigger( $node, [] )
		);
	}

	public function test_execute_node_is_passthrough(): void {

		$input = [
			'email' => 'test@example.com',
		];

		$result = Bookingcalendarcontactform::execute_node(
			$this->makeActionNode( '__any__', [] ),
			$input
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $input, $result['data'] );
	}

	public function test_get_slug_returns_bookingcalendarcontactform(): void {

		$this->assertEquals(
			'bookingcalendarcontactform',
			Bookingcalendarcontactform::get_slug()
		);
	}

	public function test_get_name_returns_booking_calendar_contact_form(): void {

		$this->assertEquals(
			'Booking Calendar Contact Form',
			Bookingcalendarcontactform::get_name()
		);
	}

	public function test_get_icon_returns_svg_icon(): void {

		$this->assertEquals(
			'bookingcalendarcontactform.svg',
			Bookingcalendarcontactform::get_icon()
		);
	}
}
