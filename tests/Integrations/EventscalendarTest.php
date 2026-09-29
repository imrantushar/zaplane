<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Eventscalendar;

class EventscalendarTest extends IntegrationTestCase {
	protected function getIntegrationClass(): string { return Eventscalendar::class; }

	protected function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'Tribe__Events__Main' ) ) {
			eval( 'class Tribe__Events__Main {}' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}
		$GLOBALS['zaplane_wp_posts'] = [];
		$GLOBALS['zaplane_wp_posts_strict'] = true;
		$GLOBALS['zaplane_post_meta'] = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['zaplane_wp_posts'], $GLOBALS['zaplane_wp_posts_strict'], $GLOBALS['zaplane_post_meta'] );
		parent::tearDown();
	}

	private function seedPost( int $id, string $type, string $title = 'Test', string $status = 'publish' ): \WP_Post {
		$post = new \WP_Post( [ 'ID' => $id, 'post_type' => $type, 'post_title' => $title, 'post_status' => $status ] );
		$GLOBALS['zaplane_wp_posts'][ $id ] = $post;
		return $post;
	}

	private function seedMeta( int $id, string $key, $value ): void {
		$GLOBALS['zaplane_post_meta'][ $id ][ $key ] = [ $value ];
	}

	public function test_exact_requested_registry_is_present(): void {
		$this->assertSame( [
			'event_created', 'event_published', 'event_updated', 'event_deleted', 'event_status_changed',
			'venue_created', 'venue_updated', 'venue_deleted',
			'organizer_created', 'organizer_updated', 'organizer_deleted',
			'event_linked_related_post', 'event_unlinked_related_post',
		], array_keys( Eventscalendar::get_triggers() ) );

		$this->assertSame( [
			'create_event', 'update_event', 'delete_event', 'get_event_details', 'search_events',
			'create_venue', 'update_venue', 'delete_venue', 'get_venue_details', 'search_venues',
			'create_organizer', 'update_organizer', 'delete_organizer', 'get_organizer_details', 'search_organizers',
			'add_venue_to_event', 'remove_venue_from_event', 'add_organizer_to_event', 'remove_organizer_from_event',
		], array_keys( Eventscalendar::get_actions() ) );
	}

	public function test_event_create_and_update_use_real_wp_after_insert_post_signature(): void {
		$post = $this->seedPost( 42, 'tribe_events', 'Conference' );
		$this->seedMeta( 42, '_EventStartDate', '2026-10-03 10:00:00' );
		$this->seedMeta( 42, '_EventEndDate', '2026-10-03 11:00:00' );

		$created = Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'event_created' ), [ 42, $post, false, null ] );
		$updated = Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'event_updated' ), [ 42, $post, true, null ] );

		$this->assertSame( 42, $created['event_id'] );
		$this->assertSame( '2026-10-03 10:00:00', $created['event_start'] );
		$this->assertSame( 42, $updated['event_id'] );
		$this->assertFalse( Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'event_created' ), [ 42, $post, true, null ] ) );
	}

	public function test_publish_and_status_changed_use_transition_signature(): void {
		$post = $this->seedPost( 42, 'tribe_events', 'Conference', 'publish' );

		$published = Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'event_published' ), [ 'publish', 'draft', $post ] );
		$changed = Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'event_status_changed' ), [ 'publish', 'draft', $post ] );

		$this->assertSame( 'draft', $published['previous_status'] );
		$this->assertSame( 'publish', $changed['new_status'] );
		$this->assertFalse( Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'event_published' ), [ 'draft', 'publish', $post ] ) );
	}

	public function test_venue_and_organizer_lifecycle_filters_post_types(): void {
		$venue = $this->seedPost( 12, 'tribe_venue', 'Venue' );
		$organizer = $this->seedPost( 15, 'tribe_organizer', 'Organizer' );

		$this->assertSame( 12, Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'venue_created' ), [ 12, $venue, false, null ] )['venue_id'] );
		$this->assertSame( 15, Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'organizer_updated' ), [ 15, $organizer, true, null ] )['organizer_id'] );
		$this->assertFalse( Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'venue_created' ), [ 15, $organizer, false, null ] ) );
	}

	public function test_related_post_link_and_unlink_accept_only_tec_relationship_meta(): void {
		$this->seedPost( 42, 'tribe_events', 'Conference' );
		$this->seedPost( 12, 'tribe_venue', 'Venue' );

		$link = Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'event_linked_related_post' ), [ 100, 42, '_EventVenueID', 12 ] );
		$unlink = Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'event_unlinked_related_post' ), [ [ 100 ], 42, '_EventVenueID', 12 ] );

		$this->assertSame( 'venue', $link['related_type'] );
		$this->assertSame( 12, $unlink['related_post_id'] );
		$this->assertFalse( Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'event_linked_related_post' ), [ 100, 42, '_thumbnail_id', 12 ] ) );
	}

	public function test_event_create_is_deferred_until_tec_dates_are_saved(): void {
		$post = $this->seedPost( 53, 'tribe_events', 'Deferred Event' );
		Eventscalendar::remember_new_event( 53, $post, false );
		$this->assertFalse( Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'event_created' ), [ 53, $post, false, null ] ) );
		$this->seedMeta( 53, '_EventStartDate', '2026-10-23 10:30:00' );
		$this->seedMeta( 53, '_EventEndDate', '2026-10-23 11:30:00' );
		$payload = Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'event_created' ), [ 53, [], $post ] );
		$this->assertSame( '2026-10-23 10:30:00', $payload['event_start'] );
		$this->assertSame( 53, Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'event_created' ), [ 53, [], $post ] )['event_id'] );
		Eventscalendar::forget_new_event( 53 );
		$this->assertFalse( Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'event_created' ), [ 53, [], $post ] ) );
	}

	public function test_replacing_venue_emits_unlink_for_old_relation(): void {
		$this->seedPost( 54, 'tribe_events', 'Venue Replacement' );
		$this->seedPost( 21, 'tribe_venue', 'Old Venue' );
		$this->seedPost( 22, 'tribe_venue', 'New Venue' );
		$this->seedMeta( 54, '_EventVenueID', 21 );
		$this->assertNull( Eventscalendar::remember_previous_relation( null, 54, '_EventVenueID', 22, '' ) );
		$payload = Eventscalendar::resolve_trigger(
			$this->makeTriggerNode( 'event_unlinked_related_post' ),
			[ 101, 54, '_EventVenueID', 22 ]
		);
		$this->assertSame( 21, $payload['related_post_id'] );
		$this->assertSame( 'venue', $payload['related_type'] );
		$this->assertFalse( Eventscalendar::resolve_trigger(
			$this->makeTriggerNode( 'event_unlinked_related_post' ),
			[ 101, 54, '_EventVenueID', 21 ]
		) );
	}

	public function test_optional_trigger_filters_and_manifest_match(): void {
		$event = $this->seedPost( 70, 'tribe_events', 'Filtered Event', 'publish' );
		$this->assertFalse( Eventscalendar::resolve_trigger(
			$this->makeTriggerNode( 'event_status_changed', [ 'event_id' => '71' ] ),
			[ 'publish', 'draft', $event ]
		) );
		$this->assertFalse( Eventscalendar::resolve_trigger(
			$this->makeTriggerNode( 'event_status_changed', [ 'new_status' => 'draft' ] ),
			[ 'publish', 'draft', $event ]
		) );
		$this->assertSame( 70, Eventscalendar::resolve_trigger(
			$this->makeTriggerNode( 'event_status_changed', [ 'event_id' => '70', 'new_status' => 'publish' ] ),
			[ 'publish', 'draft', $event ]
		)['event_id'] );

		$manifest = json_decode( file_get_contents( ZAPLANE_ROOT_DIR_PATH . 'assets/json/integrations.json' ), true );
		$this->assertCount( 13, $manifest['apps']['eventscalendar']['triggers'] );
		$this->assertCount( 19, $manifest['apps']['eventscalendar']['actions'] );
		foreach ( Eventscalendar::get_triggers() as $key => $trigger ) {
			$this->assertSame( $trigger['hook'], $manifest['apps']['eventscalendar']['triggers'][ $key ]['hook'] );
			$this->assertSame( Eventscalendar::get_trigger_config_schema( $key ), $manifest['apps']['eventscalendar']['triggers'][ $key ]['schema'] );
		}
	}

	public function test_trigger_samples_match_resolved_event_shapes(): void {
		$post = $this->seedPost( 42, 'tribe_events', 'Conference', 'publish' );
		$result = Eventscalendar::resolve_trigger( $this->makeTriggerNode( 'event_status_changed' ), [ 'publish', 'draft', $post ] );

		$this->assertSame( array_keys( Eventscalendar::get_trigger_sample_output( 'event_status_changed' ) ), array_keys( $result ) );
	}

	public function test_create_event_rejects_invalid_date_range_before_writing(): void {
		$this->expectException( \InvalidArgumentException::class );
		Eventscalendar::execute_node( $this->makeActionNode( 'create_event', [
			'title' => 'Invalid', 'start' => '2026-10-03 12:00:00', 'end' => '2026-10-03 11:00:00',
		] ), [] );
	}

	public function test_unknown_trigger_returns_false(): void {
		$this->assertFalse( Eventscalendar::resolve_trigger( [ 'event' => 'unknown' ], [] ) );
	}
}
