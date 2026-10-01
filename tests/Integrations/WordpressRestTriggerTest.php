<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Wordpress;

/**
 * Triggers watched over the core REST API, for a connection whose site runs
 * no Zaplane.
 *
 * The polling diffs are pure — items in, state in, events out — so what
 * follows needs no connected site: the first poll only records a baseline,
 * and every poll after that turns what changed into the same events a paired
 * site would have sent.
 */
class WordpressRestTriggerTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Wordpress::class;
	}

	private function spec( string $event, array $config = [] ): array {
		$class = $this->getIntegrationClass();
		$triggers = $class::get_triggers();

		return [
			'app'    => 'wordpress',
			'event'  => $event,
			'hook'   => $triggers[ $event ]['hook'] ?? $event,
			'config' => $config,
		];
	}

	private function rest_post( int $id, string $status, string $date_gmt, string $modified_gmt ): array {
		return [
			'id'            => $id,
			'status'        => $status,
			'type'          => 'post',
			'title'         => [ 'raw' => 'Rest Post ' . $id ],
			'content'       => [ 'raw' => 'Body' ],
			'date_gmt'      => str_replace( ' ', 'T', $date_gmt ),
			'modified_gmt'  => str_replace( ' ', 'T', $modified_gmt ),
			'author'        => 1,
			'slug'          => 'rest-post-' . $id,
			'link'          => 'https://other.test/?p=' . $id,
		];
	}

	public function test_the_first_poll_records_a_baseline_and_fires_nothing(): void {
		$spec = $this->spec( 'publish_post' );

		[ $events, $state ] = Wordpress::rest_diff_posts(
			[ $this->rest_post( 10, 'publish', '2026-09-30 09:00:00', '2026-09-30 09:00:00' ) ],
			[ $spec ],
			[],
			''
		);

		$this->assertSame( [], $events );
		$this->assertSame( [ 10 => 'publish' ], $state['statuses'] );
	}

	public function test_a_new_published_post_fires_publish_post(): void {
		$spec = $this->spec( 'publish_post' );

		$state = [ 'statuses' => [ 10 => 'publish' ] ];

		[ $events ] = Wordpress::rest_diff_posts(
			[ $this->rest_post( 11, 'publish', '2026-09-30 10:30:00', '2026-09-30 10:30:00' ) ],
			[ $spec ],
			$state,
			'2026-09-30 10:00:00'
		);

		$this->assertCount( 1, $events );
		$this->assertSame( 'publish_post', $events[0]['event'] );
		$this->assertSame( 'publish_post', $events[0]['hook'] );
		$this->assertSame( 11, $events[0]['payload']['ID'] );
		$this->assertSame( 'Rest Post 11', $events[0]['payload']['post_title'] );
	}

	public function test_a_new_draft_fires_save_post_but_not_publish_post(): void {
		$publish = $this->spec( 'publish_post' );
		$save    = $this->spec( 'save_post' );

		[ $events ] = Wordpress::rest_diff_posts(
			[ $this->rest_post( 12, 'draft', '2026-09-30 10:30:00', '2026-09-30 10:30:00' ) ],
			[ $publish, $save ],
			[ 'statuses' => [] ],
			'2026-09-30 10:00:00'
		);

		$this->assertCount( 1, $events );
		$this->assertSame( 'save_post', $events[0]['event'] );
	}

	public function test_a_status_change_to_publish_fires_the_transition_and_publish(): void {
		$transition = $this->spec( 'transition_post_status' );
		$publish    = $this->spec( 'publish_post' );

		[ $events ] = Wordpress::rest_diff_posts(
			[ $this->rest_post( 10, 'publish', '2026-09-01 09:00:00', '2026-09-30 10:30:00' ) ],
			[ $transition, $publish ],
			[ 'statuses' => [ 10 => 'draft' ] ],
			'2026-09-30 10:00:00'
		);

		$this->assertCount( 2, $events );

		$this->assertSame( 'transition_post_status', $events[0]['event'] );
		$this->assertSame( 'draft', $events[0]['payload']['old_status'] );
		$this->assertSame( 'publish', $events[0]['payload']['new_status'] );

		$this->assertSame( 'publish_post', $events[1]['event'] );
	}

	public function test_moving_a_post_to_trash_fires_wp_trash_post(): void {
		$spec = $this->spec( 'wp_trash_post' );

		[ $events ] = Wordpress::rest_diff_posts(
			[ $this->rest_post( 10, 'trash', '2026-09-01 09:00:00', '2026-09-30 10:30:00' ) ],
			[ $spec ],
			[ 'statuses' => [ 10 => 'publish' ] ],
			'2026-09-30 10:00:00'
		);

		$this->assertCount( 1, $events );
		$this->assertSame( 'wp_trash_post', $events[0]['event'] );
	}

	public function test_an_edited_post_fires_post_updated_without_a_status_change(): void {
		$spec = $this->spec( 'post_updated' );

		[ $events ] = Wordpress::rest_diff_posts(
			[ $this->rest_post( 10, 'publish', '2026-09-01 09:00:00', '2026-09-30 10:30:00' ) ],
			[ $spec ],
			[ 'statuses' => [ 10 => 'publish' ] ],
			'2026-09-30 10:00:00'
		);

		$this->assertCount( 1, $events );
		$this->assertSame( 'post_updated', $events[0]['event'] );
		$this->assertSame( 10, $events[0]['payload']['ID'] );
	}

	public function test_a_post_bound_to_one_id_does_not_fire_for_another(): void {
		$spec = $this->spec( 'publish_post', [ 'post' => '10' ] );

		[ $events ] = Wordpress::rest_diff_posts(
			[ $this->rest_post( 11, 'publish', '2026-09-30 10:30:00', '2026-09-30 10:30:00' ) ],
			[ $spec ],
			[ 'statuses' => [] ],
			'2026-09-30 10:00:00'
		);

		$this->assertSame( [], $events );
	}

	public function test_the_status_map_is_capped_so_it_cannot_grow_without_limit(): void {
		$spec  = $this->spec( 'publish_post' );
		$items = [];

		for ( $id = 1; $id <= 600; $id++ ) {
			$items[] = $this->rest_post( $id, 'publish', '2026-09-30 10:30:00', '2026-09-30 10:30:00' );
		}

		[ $events, $state ] = Wordpress::rest_diff_posts( $items, [ $spec ], [], '2026-09-30 10:00:00' );

		$this->assertCount( 500, $state['statuses'] );
		$this->assertArrayNotHasKey( 1, $state['statuses'] );
		$this->assertArrayHasKey( 600, $state['statuses'] );
	}

	public function test_a_new_comment_fires_comment_post_and_carries_the_local_shape(): void {
		$spec = $this->spec( 'comment_post' );

		$item = [
			'id'                => 55,
			'post'              => 10,
			'author_name'       => 'Jane Reader',
			'author_email'      => 'jane@example.com',
			'author_user_agent' => 'Mozilla/5.0',
			'date'              => '2026-09-30T10:30:00',
			'date_gmt'          => '2026-09-30T10:30:00',
			'content'           => [ 'rendered' => '<p>Great post!</p>' ],
			'status'            => 'approved',
			'type'              => 'comment',
			'parent'            => 0,
			'author'            => 0,
		];

		[ $events, $last_id ] = Wordpress::rest_diff_ids( [ $item ], [ $spec ], 50, [ Wordpress::class, 'rest_map_comment' ] );

		$this->assertCount( 1, $events );
		$this->assertSame( 55, $last_id );
		$this->assertSame( 'comment_post', $events[0]['event'] );

		$payload = $events[0]['payload'];

		$this->assertSame( 55, $payload['comment_ID'] );
		$this->assertSame( 10, $payload['comment_post_ID'] );
		$this->assertSame( 'Jane Reader', $payload['comment_author'] );
		$this->assertSame( '1', $payload['comment_approved'] );
		$this->assertSame( '2026-09-30 10:30:00', $payload['comment_date_gmt'] );
	}

	public function test_the_first_comment_poll_is_a_baseline_and_fires_nothing(): void {
		$spec = $this->spec( 'comment_post' );

		[ $events, $last_id ] = Wordpress::rest_diff_ids(
			[ [ 'id' => 55, 'post' => 10, 'author_name' => 'Old', 'status' => 'approved', 'content' => [ 'rendered' => 'x' ] ] ],
			[ $spec ],
			0,
			[ Wordpress::class, 'rest_map_comment' ]
		);

		$this->assertSame( [], $events );
		$this->assertSame( 55, $last_id );
	}

	public function test_a_new_user_fires_user_register_with_the_local_shape(): void {
		$spec = $this->spec( 'user_register' );

		$item = [
			'id'              => 6,
			'username'        => 'johndoe',
			'slug'            => 'johndoe',
			'email'           => 'john@example.com',
			'url'             => 'https://example.com',
			'registered_date' => '2026-09-30 10:30:00',
			'name'            => 'John Doe',
			'roles'           => [ 'subscriber' ],
		];

		[ $events, $last_id ] = Wordpress::rest_diff_ids( [ $item ], [ $spec ], 5, [ Wordpress::class, 'rest_map_user_row' ] );

		$this->assertCount( 1, $events );
		$this->assertSame( 6, $last_id );

		$payload = $events[0]['payload'];

		$this->assertSame( 6, $payload['ID'] );
		$this->assertSame( 'johndoe', $payload['user_login'] );
		$this->assertSame( 'john@example.com', $payload['user_email'] );
		$this->assertSame( 'John Doe', $payload['display_name'] );
		$this->assertSame( [ 'subscriber' ], $payload['roles'] );
	}

	public function test_a_new_attachment_fires_add_attachment_and_an_edited_one_fires_edit_attachment(): void {
		$add = $this->spec( 'add_attachment' );
		$edit = $this->spec( 'edit_attachment' );

		$items = [
			[
				'id'           => 202,
				'status'       => 'inherit',
				'type'         => 'attachment',
				'title'        => [ 'raw' => 'New Image' ],
				'date_gmt'     => '2026-09-30T10:30:00',
				'modified_gmt' => '2026-09-30T10:30:00',
				'mime_type'    => 'image/png',
				'source_url'   => 'https://other.test/wp-content/uploads/new.png',
			],
			[
				'id'           => 201,
				'status'       => 'inherit',
				'type'         => 'attachment',
				'title'        => [ 'raw' => 'Old Image' ],
				'date_gmt'     => '2026-09-01T10:00:00',
				'modified_gmt' => '2026-09-30T10:20:00',
				'mime_type'    => 'image/png',
				'source_url'   => 'https://other.test/wp-content/uploads/old.png',
			],
		];

		[ $events, $state ] = Wordpress::rest_diff_media(
			$items,
			[ $add, $edit ],
			[ 'id' => 201 ],
			'2026-09-30 10:00:00'
		);

		$this->assertCount( 2, $events );
		$this->assertSame( 202, $state['id'] );

		$this->assertSame( 'add_attachment', $events[0]['event'] );
		$this->assertSame( 202, $events[0]['payload']['ID'] );
		$this->assertSame( 'https://other.test/wp-content/uploads/new.png', $events[0]['payload']['url'] );

		$this->assertSame( 'edit_attachment', $events[1]['event'] );
		$this->assertSame( 201, $events[1]['payload']['ID'] );
	}
}
