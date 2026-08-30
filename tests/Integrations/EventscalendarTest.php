<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Eventscalendar;

/**
 * Every trigger here is an Event Tickets hook, and three of the four read their
 * arguments out of the wrong positions. These lock the real signatures in.
 *
 * @see https://docs.theeventscalendar.com/ — Event Tickets fires:
 *   event_tickets_checkin( $attendee_id, $qr )
 *   event_tickets_rsvp_attendee_created( $attendee_id, $post_id, $order, $product_id, $status )
 *   event_tickets_rsvp_tickets_generated_for_product( $product_id, $order_id )
 *   tribe_tickets_attendee_repository_create_attendee_for_ticket_after_create(
 *       $attendee, $attendee_data, $ticket, $repository )
 */
class EventscalendarTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Eventscalendar::class;
	}

	protected function setUp(): void {
		parent::setUp();

		// resolve_trigger short-circuits unless Event Tickets is present.
		if ( ! class_exists( 'Tribe__Tickets__Main' ) ) {
			eval( 'class Tribe__Tickets__Main {}' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Stand-in for the absent plugin.
		}

		// The shared get_post/get_post_meta stubs read these fixtures; strict mode
		// makes an unseeded id return null instead of a generic post.
		$GLOBALS['zaplane_wp_posts']        = [];
		$GLOBALS['zaplane_wp_posts_strict'] = true;
		$GLOBALS['zaplane_post_meta']       = [];
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['zaplane_wp_posts'],
			$GLOBALS['zaplane_wp_posts_strict'],
			$GLOBALS['zaplane_post_meta']
		);
		parent::tearDown();
	}

	private function seedPost( int $id, string $title ): void {
		$GLOBALS['zaplane_wp_posts'][ $id ] = new \WP_Post( [ 'ID' => $id, 'post_title' => $title ] );
	}

	private function seedMeta( int $id, array $meta ): void {
		foreach ( $meta as $key => $value ) {
			$GLOBALS['zaplane_post_meta'][ $id ][ $key ] = [ $value ];
		}
	}

	public function test_requires_event_tickets_not_just_the_events_calendar(): void {
		$required = Eventscalendar::get_required_plugins();

		$this->assertContains(
			'event-tickets/event-tickets.php',
			$required,
			'All four triggers are Event Tickets hooks; The Events Calendar alone fires none of them.'
		);
	}

	/**
	 * $args[1] is the QR flag, not the event id. Reading it as one meant a QR
	 * check-in resolved the event to `true` and returned an empty title/url.
	 */
	public function test_checkin_reads_the_event_from_meta_not_the_qr_flag(): void {
		$this->seedPost( 720, 'Sarah Johnson' );
		$this->seedPost( 410, 'Annual Tech Conference 2026' );
		$this->seedMeta( 720, [
			'_tribe_rsvp_event'           => 410,
			'_tribe_tickets_meta_user_id' => 2,
		] );

		$node = $this->makeTriggerNode( 'attendEvent' );

		// Second arg is the QR object, exactly as Event Tickets passes it.
		$result = Eventscalendar::resolve_trigger( $node, [ 720, true ] );

		$this->assertSame( 720, $result['attendee_id'] );
		$this->assertSame( 410, $result['event_id'] );
		$this->assertSame( 'Annual Tech Conference 2026', $result['event_title'] );
		$this->assertTrue( $result['via_qr'] );
	}

	public function test_checkin_still_resolves_for_a_manual_check_in(): void {
		$this->seedPost( 721, 'Manual Attendee' );
		$this->seedPost( 411, 'Workshop' );
		$this->seedMeta( 721, [ '_tribe_wooticket_event' => 411 ] );

		$result = Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'attendEvent' ), [ 721, null ] );

		$this->assertSame( 411, $result['event_id'] );
		$this->assertFalse( $result['via_qr'] );
	}

	public function test_checkin_rejects_an_unknown_attendee(): void {
		$this->assertFalse( Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'attendEvent' ), [ 0 ] ) );
		$this->assertFalse( Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'attendEvent' ), [ 999999 ] ) );
	}

	/**
	 * $args[2] is the order OBJECT for RSVP, so it has to be narrowed to an id
	 * rather than handed through as one.
	 */
	public function test_rsvp_attendee_created_narrows_the_order_object_to_an_id(): void {
		$order = (object) [ 'ID' => 9001 ];

		$result = Eventscalendar::resolve_trigger(
			$this->makeTriggerNode( 'attendeeRegistered' ),
			[ 720, 410, $order, 512, 'yes' ]
		);

		$this->assertSame( 720, $result['attendee_id'] );
		$this->assertSame( 410, $result['post_id'] );
		$this->assertSame( 9001, $result['order_id'] );
		$this->assertSame( 512, $result['product_id'] );
		$this->assertSame( 'yes', $result['status'] );
	}

	/**
	 * The hook passes only two arguments — the attendee list has to be looked
	 * up, not read from a third that never arrives.
	 */
	public function test_tickets_generated_does_not_read_a_third_argument(): void {
		$result = Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'newAttendee' ), [ 512, 9001 ] );

		$this->assertSame( 512, $result['product_id'] );
		$this->assertSame( 9001, $result['order_id'] );
		$this->assertIsArray( $result['attendees'] );
	}

	/**
	 * The old mapping read these as ( attendee_id, ticket, order, attendee_data )
	 * — every field shifted one position and the wrong type.
	 */
	public function test_wc_attendee_created_reads_each_argument_in_its_real_position(): void {
		$attendee      = (object) [ 'ID' => 720 ];
		$attendee_data = [ 'order_id' => 9001, 'full_name' => 'Sarah Johnson', 'email' => 'sarah@example.com' ];
		$ticket        = (object) [ 'ID' => 512, 'name' => 'General Admission', 'price' => 49.00 ];
		$repository    = (object) [ 'irrelevant' => true ];

		$result = Eventscalendar::resolve_trigger(
			$this->makeTriggerNode( 'attendeeRegisteredWc' ),
			[ $attendee, $attendee_data, $ticket, $repository ]
		);

		$this->assertSame( 720, $result['attendee_id'] );
		$this->assertSame( 512, $result['ticket']['ticket_id'] );
		$this->assertSame( 'General Admission', $result['ticket']['name'] );
		$this->assertSame( 9001, $result['order']['order_id'] );
		$this->assertSame( 'Sarah Johnson', $result['attendee_data']['full_name'] );
	}

	/**
	 * Guards against the payload drifting away from what the "@" variable picker
	 * offers before any test run has happened.
	 */
	public function test_wc_payload_keys_match_the_sample_output(): void {
		$result = Eventscalendar::resolve_trigger(
			$this->makeTriggerNode( 'attendeeRegisteredWc' ),
			[ (object) [ 'ID' => 720 ], [ 'order_id' => 9001 ], (object) [ 'ID' => 512 ], null ]
		);

		$this->assertSame(
			array_keys( Eventscalendar::get_trigger_sample_output( 'attendeeRegisteredWc' ) ),
			array_keys( $result )
		);
	}

	public function test_unknown_event_returns_false(): void {
		$this->assertFalse( Eventscalendar::resolve_trigger( [ 'event' => 'nope' ], [ 1 ] ) );
	}
}
