<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Eventscalendar;
use Zaplane\Tests\WPMocks;

class EventscalendarTest extends IntegrationTestCase {

    protected function getIntegrationClass(): string {
        return Eventscalendar::class;
    }

    protected function setupMockData(): void {
        parent::setupMockData();

        WPMocks::setPost( 101, [
            'post_title' => 'John Doe',
            'post_type'  => 'tribe_rsvp_attendees',
        ] );

        WPMocks::setPost( 201, [
            'post_title' => 'Test Event',
            'post_type'  => 'tribe_events',
        ] );

        WPMocks::setUser( 1, [
            'user_login'   => 'johndoe',
            'user_email'   => 'john@example.com',
            'display_name' => 'John Doe',
        ] );
    }

    protected function getTriggerTests(): array {
        return [
            'attendEvent'          => [ 101, 201, 0 ],
            'attendeeRegistered'   => [ 101, 201, 300, 401 ],
            'newAttendee'          => [ 401, 300, [ 101 ] ],
            'attendeeRegisteredWc' => [ 101, (object) [], (object) [], [] ],
        ];
    }

    // ========== attendEvent ==========

    public function test_attend_event_returns_payload(): void {
        $result = Eventscalendar::resolve_trigger(
            $this->makeTriggerNode( 'attendEvent' ),
            [ 101, 201, 0 ]
        );

        $this->assertIsArray( $result );
        $this->assertEquals( 101, $result['attendee_id'] );
        $this->assertEquals( 'John Doe', $result['attendee_name'] );
        $this->assertEquals( 201, $result['event_id'] );  // from $args[1]
        $this->assertEquals( 'Test Event', $result['event_title'] );
        $this->assertArrayHasKey( 'user_id', $result );
        $this->assertArrayHasKey( 'user_email', $result );
        $this->assertArrayHasKey( 'checked_in_at', $result );
    }

    public function test_attend_event_returns_false_without_attendee_id(): void {
        $result = Eventscalendar::resolve_trigger(
            $this->makeTriggerNode( 'attendEvent' ),
            [ 0, 201, 0 ]
        );

        $this->assertFalse( $result );
    }

    public function test_attend_event_returns_false_with_invalid_attendee(): void {
        $result = Eventscalendar::resolve_trigger(
            $this->makeTriggerNode( 'attendEvent' ),
            [ 9999, 201, 0 ]
        );

        $this->assertFalse( $result );
    }

    public function test_attend_event_uses_args_event_id_over_meta(): void {
        $result = Eventscalendar::resolve_trigger(
            $this->makeTriggerNode( 'attendEvent' ),
            [ 101, 999, 0 ]
        );

        $this->assertIsArray( $result );
        $this->assertEquals( 999, $result['event_id'] );
    }

    // ========== attendeeRegistered ==========

    public function test_attendee_registered_returns_payload(): void {
        $result = Eventscalendar::resolve_trigger(
            $this->makeTriggerNode( 'attendeeRegistered' ),
            [ 101, 201, 300, 401 ]
        );

        $this->assertIsArray( $result );
        $this->assertEquals( 101, $result['attendee_id'] );
        $this->assertEquals( 201, $result['post_id'] );
        $this->assertEquals( 300, $result['order_id'] );
        $this->assertEquals( 401, $result['product_id'] );
    }

    public function test_attendee_registered_returns_empty_values_with_no_args(): void {
        $result = Eventscalendar::resolve_trigger(
            $this->makeTriggerNode( 'attendeeRegistered' ),
            []
        );

        $this->assertIsArray( $result );
        $this->assertEquals( '', $result['attendee_id'] );
        $this->assertEquals( '', $result['post_id'] );
        $this->assertEquals( '', $result['order_id'] );
        $this->assertEquals( '', $result['product_id'] );
    }

    // ========== newAttendee ==========

    public function test_new_attendee_returns_payload(): void {
        $result = Eventscalendar::resolve_trigger(
            $this->makeTriggerNode( 'newAttendee' ),
            [ 401, 300, [ 101, 102 ] ]
        );

        $this->assertIsArray( $result );
        $this->assertEquals( 401, $result['product_id'] );
        $this->assertEquals( 300, $result['order_id'] );
        $this->assertEquals( [ 101, 102 ], $result['attendees'] );
    }

    public function test_new_attendee_returns_empty_attendees_with_no_args(): void {
        $result = Eventscalendar::resolve_trigger(
            $this->makeTriggerNode( 'newAttendee' ),
            []
        );

        $this->assertIsArray( $result );
        $this->assertEquals( [], $result['attendees'] );
    }

    // ========== attendeeRegisteredWc ==========

    public function test_attendee_registered_wc_returns_payload(): void {
        $ticket = (object) [ 'ID' => 401 ];
        $order  = (object) [ 'ID' => 300 ];
        $data   = [ 'meta_key' => 'meta_value' ];

        $result = Eventscalendar::resolve_trigger(
            $this->makeTriggerNode( 'attendeeRegisteredWc' ),
            [ 101, $ticket, $order, $data ]
        );

        $this->assertIsArray( $result );
        $this->assertEquals( 101, $result['attendee_id'] );
        $this->assertEquals( $ticket, $result['ticket'] );
        $this->assertEquals( $order, $result['order'] );
        $this->assertEquals( $data, $result['attendee_data'] );
    }

    public function test_attendee_registered_wc_returns_empty_values_with_no_args(): void {
        $result = Eventscalendar::resolve_trigger(
            $this->makeTriggerNode( 'attendeeRegisteredWc' ),
            []
        );

        $this->assertIsArray( $result );
        $this->assertEquals( '', $result['attendee_id'] );
        $this->assertEquals( [], $result['attendee_data'] );
    }

    // ========== execute_node ==========

    public function test_execute_node_returns_main_port(): void {
        $result = Eventscalendar::execute_node(
            $this->makeActionNode( 'attendEvent' ),
            [ 'attendee_id' => 101 ]
        );

        $this->assertIsArray( $result );
        $this->assertEquals( 'main', $result['port'] );
        $this->assertEquals( [ 'attendee_id' => 101 ], $result['data'] );
    }
}
