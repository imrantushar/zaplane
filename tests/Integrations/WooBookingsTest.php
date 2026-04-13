<?php

namespace {
	require_once __DIR__ . '/../mocks/woocommerce.php';
	require_once __DIR__ . '/../mocks/woobookings.php';
}

namespace Zaplane\Tests\Integrations {

use Zaplane\Integrations\WooBookings;

class WooBookingsTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return WooBookings::class;
	}

	protected function setupMockData(): void {
		parent::setupMockData();
		\WooTestStore::reset();
		\WooBookingsTestStore::reset();
	}

	protected function getTriggerTests(): array {
		return [
			'booking_created' => [ new \WC_Booking( 1101 ), new \WC_Order( 501 ) ],
			'booking_confirmed' => [ 1101 ],
			'booking_paid' => [ new \WC_Booking( 1102 ) ],
			'booking_cancelled' => [ new \WC_Booking( 1102 ) ],
			'booking_unpaid' => [ 1101 ],
			'booking_status_changed' => [ new \WC_Booking( 1101 ), 'confirmed', 'unpaid' ],
		];
	}

	protected function getActionTests(): array {
		return [
			'get_bookings_all' => [
				'limit' => 10,
				'page' => 1,
			],
			'get_booking_single' => [
				'booking_id' => 1101,
			],
			'update_booking_status' => [
				'booking_id' => 1102,
				'booking_status' => 'wc-booking-confirmed',
				'note' => 'Updated by automation',
			],
			'cancel_booking' => [
				'booking_id' => 1101,
				'note' => 'Cancelled by automation',
			],
			'confirm_booking' => [
				'booking_id' => 1102,
				'note' => 'Confirmed by automation',
			],
			'add_booking_note' => [
				'booking_id' => 1101,
				'note' => 'Test note',
				'is_customer_note' => true,
			],
		];
	}

	public function test_trigger_booking_created_returns_booking_payload(): void {
		$result = WooBookings::resolve_trigger(
			$this->makeTriggerNode( 'booking_created' ),
			[ new \WC_Booking( 1101 ), new \WC_Order( 501 ) ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 1101, $result['booking_id'] );
		$this->assertEquals( 501, $result['order_id'] );
	}

	public function test_trigger_booking_status_changed_includes_old_and_new_status(): void {
		$result = WooBookings::resolve_trigger(
			$this->makeTriggerNode( 'booking_status_changed' ),
			[ new \WC_Booking( 1101 ), 'confirmed', 'unpaid' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'confirmed', $result['new_status'] );
		$this->assertEquals( 'unpaid', $result['old_status'] );
	}

	public function test_resolve_trigger_supports_legacy_trigger_node_shape(): void {
		$result = WooBookings::resolve_trigger(
			[
				'type' => 'trigger',
				'data' => [
					'app' => 'woobookings',
					'event' => 'booking_paid',
				],
			],
			[ 1101 ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 1101, $result['booking_id'] );
	}

	public function test_resolve_trigger_returns_false_for_invalid_booking(): void {
		$result = WooBookings::resolve_trigger(
			$this->makeTriggerNode( 'booking_paid' ),
			[ 0 ]
		);

		$this->assertFalse( $result );
	}

	public function test_action_get_booking_single_returns_booking_payload(): void {
		$result = WooBookings::execute_node(
			$this->makeActionNode( 'get_booking_single', [ 'booking_id' => 1101 ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 1101, $result['data']['booking']['booking_id'] );
	}

	public function test_action_update_booking_status_updates_status(): void {
		$result = WooBookings::execute_node(
			$this->makeActionNode( 'update_booking_status', [
				'booking_id' => 1102,
				'booking_status' => 'wc-booking-confirmed',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'confirmed', $result['data']['booking']['new_status'] );
	}

	public function test_action_cancel_and_confirm_booking_set_expected_status(): void {
		$cancel_result = WooBookings::execute_node(
			$this->makeActionNode( 'cancel_booking', [ 'booking_id' => 1101 ] ),
			[]
		);

		$this->assertEquals( 'main', $cancel_result['port'] );
		$this->assertEquals( 'cancelled', $cancel_result['data']['booking']['new_status'] );

		$confirm_result = WooBookings::execute_node(
			$this->makeActionNode( 'confirm_booking', [ 'booking_id' => 1102 ] ),
			[]
		);

		$this->assertEquals( 'main', $confirm_result['port'] );
		$this->assertEquals( 'confirmed', $confirm_result['data']['booking']['new_status'] );
	}

	public function test_action_add_booking_note_returns_note_data(): void {
		$result = WooBookings::execute_node(
			$this->makeActionNode( 'add_booking_note', [
				'booking_id' => 1101,
				'note' => 'Manual follow-up',
				'is_customer_note' => true,
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 'Manual follow-up', $result['data']['note'] );
		$this->assertTrue( $result['data']['is_customer_note'] );
	}

	public function test_execute_node_supports_legacy_action_node_shape(): void {
		$result = WooBookings::execute_node(
			[
				'type' => 'action',
				'config' => [
					'action' => 'get_booking_single',
					'data' => [
						'booking_id' => 1101,
					],
				],
			],
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 1101, $result['data']['booking']['booking_id'] );
	}
}
}
