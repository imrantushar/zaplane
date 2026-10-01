<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\EasyContentManager;
use Zaplane\Tests\WPMocks;

// The integration gates everything on ECM being loaded; the mocked test
// environment has no real plugin to define this.
if ( ! defined( 'EASY_CONTENT_MANAGER_VERSION' ) ) {
	define( 'EASY_CONTENT_MANAGER_VERSION', '1.2.8-test' );
}

class EasyContentManagerTest extends IntegrationTestCase {

	/**
	 * A minimal stand-in for WP_REST_Request, enough for the claim triggers
	 * that read the route/params on rest_pre_dispatch.
	 */
	private function makeRestRequest( string $route, array $params = [], string $method = 'POST' ): object {
		return new class( $route, $params, $method ) {
			private $route;
			private $params;
			private $method;

			public function __construct( string $route, array $params, string $method ) {
				$this->route  = $route;
				$this->params = $params;
				$this->method = $method;
			}

			public function get_route(): string {
				return $this->route;
			}

			public function get_method(): string {
				return $this->method;
			}

			public function get_param( string $key ) {
				return $this->params[ $key ] ?? null;
			}
		};
	}

	protected function tearDown(): void {
		unset( $GLOBALS['zaplane_wp_posts'], $GLOBALS['zaplane_wp_posts_strict'], $GLOBALS['zaplane_post_meta'] );
		parent::tearDown();
	}

	protected function setupMockData(): void {
		parent::setupMockData();

		// Keep the same item in the WPMocks store too, for the functions that
		// read that store (wp_trash_post, wp_delete_post, ...).
		WPMocks::setPost(
			42,
			[
				'post_title'   => 'Sample Listing',
				'post_type'    => 'listing',
				'post_status'  => 'publish',
				'post_content' => 'Listing body',
			]
		);

		// The review the review tests address.
		WPMocks::setComment(
			77,
			[
				'comment_post_ID'      => 42,
				'comment_type'         => 'ecm_review',
				'comment_author'       => 'Jane Doe',
				'comment_author_email' => 'jane@example.com',
				'comment_content'      => 'Great experience!',
			]
		);

		// Addon state: every ECM addon switched on.
		WPMocks::setOption(
			'easy_content_manager_addons_settings',
			json_encode(
				[
					'bookmark'            => true,
					'claim'               => true,
					'frontend-submission' => true,
					'reactions'           => true,
					'review-and-ratings'  => true,
					'upvote'              => true,
					'view-counter'        => true,
				]
			)
		);

		// ECM's config CPTs are not registered post types; the integration
		// reads them through get_posts(), which the mock lets us override.
		$GLOBALS['zaplane_get_posts'] = function ( array $args ) {
			$type = $args['post_type'] ?? '';

			if ( 'ecm_post_types' === $type ) {
				return [
					(object) [
						'ID'           => 101,
						'post_content' => json_encode(
							[
								'post_type'     => 'listing',
								'post_slug'     => 'listing',
								'labels'        => [ 'title' => 'Listings' ],
								'custom_fields' => [
									[ 'meta_key' => 'price' ],
									[ 'meta_key' => 'location' ],
								],
							]
						),
					],
				];
			}

			if ( 'ecm_taxonomy' === $type ) {
				return [
					(object) [
						'ID'           => 102,
						'post_content' => json_encode(
							[
								'taxonomy_slug' => 'ecm_tag',
								'labels'        => [ 'title' => 'Listing Tags' ],
							]
						),
					],
				];
			}

			if ( 'ecm_field_group' === $type ) {
				return [
					(object) [
						'ID'           => 103,
						'post_content' => json_encode(
							[ 'custom_fields' => [ [ 'meta_key' => 'price' ] ] ]
						),
					],
					(object) [
						'ID'           => 104,
						'post_content' => json_encode(
							[ 'custom_fields' => [ [ 'meta_key' => 'phone' ] ] ]
						),
					],
				];
			}

			return [];
		};

		// Field group 103 sits on the 'listing' post type; 104 on user profiles.
		// Read back through get_post_meta → the zaplane_post_meta global.
		$GLOBALS['zaplane_post_meta'][103] = [ 'ecm_field_group_location' => [ [ 'listing' ] ] ];
		$GLOBALS['zaplane_post_meta'][104] = [ 'ecm_field_group_location' => [ [ 'current_user' => '1' ] ] ];

		// The item the trigger tests address: an ECM-managed post type. The
		// winning get_post() reads zaplane_wp_posts; single-meta reads return
		// $value[0], so list-shaped meta is wrapped one level deeper.
		$GLOBALS['zaplane_wp_posts'][42] = (object) [
			'ID'            => 42,
			'post_title'    => 'Sample Listing',
			'post_type'     => 'listing',
			'post_status'   => 'publish',
			'post_content'  => 'Listing body',
			'post_excerpt'  => '',
			'post_name'     => 'sample-listing',
			'post_author'   => 1,
			'post_date'     => '2026-01-01 12:00:00',
			'post_modified' => '2026-01-01 12:00:00',
		];

		$GLOBALS['zaplane_post_meta'][42] = [
			'ecm_bookmark_users' => [ [ 5, 6 ] ],
			'_ecm_upvoted_users' => [ [ 5, 6 ] ],
			'ecm_upvote_count'   => [ 2 ],
			'_ecm_reactions'     => [ [ 'like' => [ 5 ] ] ],
		];

		WPMocks::setTerm(
			9,
			[
				'name'        => 'Featured',
				'slug'        => 'featured',
				'taxonomy'    => 'ecm_tag',
				'description' => '',
			]
		);
	}

	protected function getIntegrationClass(): string {
		return EasyContentManager::class;
	}

	protected function getTriggerTests(): array {
		$item = (object) [ 'ID' => 42, 'post_type' => 'listing', 'post_status' => 'publish' ];

		return [
			'item_created'                        => [ 42, null, false ],
			'item_updated'                        => [ 42, null, null ],
			'item_deleted'                        => [ 42, null ],
			'item_published'                      => [ 'publish', 'draft', $item ],
			'item_status_changed'                 => [ 'pending', 'publish', $item ],
			'specific_item_created'               => [ 42, null, false ],
			'specific_item_updated'               => [ 42, null, null ],
			'specific_item_published'             => [ 'publish', 'draft', $item ],
			'custom_field_updated'                => [ 999, 42, 'price', '199' ],
			'user_created'                        => [ 1 ],
			'user_meta_updated'                   => [ 888, 1, 'phone', '+8801700000000' ],
			'term_created'                        => [ 9, 9, 'ecm_tag' ],
			'term_updated'                        => [ 9, 9, 'ecm_tag' ],
			'term_deleted'                        => [ 9, 9, 'ecm_tag', WPMocks::getTerm( 9 ), [] ],
			// did_action() does not exist in the mocks, so the frontend
			// submission trigger correctly reports false here.
			'frontend_submission_created'         => [ 42, null, false ],
			'frontend_submission_status_changed'  => [ 'publish', 'draft', $item ],
			'review_submitted'                    => [ 77, WPMocks::getComment( 77 ) ],
			'review_approved'                     => [ 'approved', 'hold', WPMocks::getComment( 77 ) ],
			'review_rejected'                     => [ 'spam', 'approved', WPMocks::getComment( 77 ) ],
			'rating_submitted'                    => [ 999, 77, '_ecm_rating', 4.5 ],
			'claim_submitted'                     => [ null, null, $this->makeRestRequest( '/easy-content-manager/v1/claim/submit', [ 'post_id' => 42, 'name' => 'Jane' ] ) ],
			'claim_approved'                      => [ null, null, $this->makeRestRequest( '/easy-content-manager/v1/claims/12', [ 'status' => 'approved' ], 'PATCH' ) ],
			'claim_rejected'                      => [ null, null, $this->makeRestRequest( '/easy-content-manager/v1/claims/12', [ 'status' => 'rejected' ], 'PATCH' ) ],
			'bookmark_added'                      => [ 999, 42, 'ecm_bookmark_users', serialize( [ 5, 6, 7 ] ) ],
			'bookmark_removed'                    => [ 999, 42, 'ecm_bookmark_users', serialize( [ 5 ] ) ],
			'upvote_added'                        => [ 999, 42, '_ecm_upvoted_users', [ 5, 6, 7 ] ],
			'reaction_added'                      => [ 999, 42, '_ecm_reactions', [ 'like' => [ 5, 6 ] ] ],
			'reaction_removed'                    => [ 999, 42, '_ecm_reactions', [ 'love' => [ 5 ] ] ],
		];
	}

	protected function getActionTests(): array {
		return [
			'create_item'                   => [ 'post_type' => 'listing', 'post_title' => 'New Listing', 'post_status' => 'draft' ],
			'update_item'                   => [ 'post_id' => 42, 'post_title' => 'Renamed' ],
			'delete_item'                   => [ 'post_id' => 42, 'force_delete' => 'no' ],
			'get_item'                      => [ 'post_id' => 42 ],
			'find_item'                     => [ 'post_type' => 'listing', 'search_field' => 'title', 'search_value' => 'Sample' ],
			'change_item_status'            => [ 'post_id' => 42, 'status' => 'publish' ],
			'publish_item'                  => [ 'post_id' => 42 ],
			'duplicate_item'                => [ 'post_id' => 42, 'new_status' => 'draft' ],
			'update_custom_field_value'     => [ 'post_id' => 42, 'meta_key' => 'price', 'value' => '199' ],
			'get_custom_field_value'        => [ 'post_id' => 42, 'meta_key' => 'price' ],
			'update_multiple_custom_fields' => [ 'post_id' => 42, 'fields' => [ [ 'key' => 'price', 'value' => '199' ], [ 'key' => 'location', 'value' => 'Dhaka' ] ] ],
			'create_user'                   => [ 'user_login' => 'newuser', 'user_email' => 'new@example.com', 'display_name' => 'New User' ],
			'update_user'                   => [ 'user_id' => 2, 'display_name' => 'Renamed User' ],
			'update_user_custom_meta'       => [ 'user_id' => 2, 'meta_key' => 'phone', 'value' => '123' ],
			'get_user_custom_meta'          => [ 'user_id' => 2, 'meta_key' => 'phone' ],
			'create_taxonomy_term'          => [ 'taxonomy' => 'ecm_tag', 'name' => 'Fresh Term' ],
			'update_taxonomy_term'          => [ 'term_id' => 9, 'name' => 'Renamed Term' ],
			'delete_taxonomy_term'          => [ 'term_id' => 9, 'taxonomy' => 'ecm_tag' ],
			'assign_term_to_item'           => [ 'post_id' => 42, 'taxonomy' => 'ecm_tag', 'terms' => 'Featured' ],
			'remove_term_from_item'         => [ 'post_id' => 42, 'taxonomy' => 'ecm_tag', 'terms' => 'Featured' ],
			'approve_frontend_submission'   => [ 'post_id' => 42 ],
			'reject_frontend_submission'    => [ 'post_id' => 42, 'status' => 'draft' ],
			'approve_review'                => [ 'review_id' => 77 ],
			'reject_review'                 => [ 'review_id' => 77 ],
			'delete_review'                 => [ 'review_id' => 77 ],
			'approve_claim'                 => [ 'claim_id' => 12 ],
			'reject_claim'                  => [ 'claim_id' => 12 ],
			'delete_claim'                  => [ 'claim_id' => 12 ],
			'add_bookmark'                  => [ 'post_id' => 42, 'user_id' => 2 ],
			'remove_bookmark'               => [ 'post_id' => 42, 'user_id' => 2 ],
			'add_upvote'                    => [ 'post_id' => 42, 'user_id' => 2 ],
			'remove_upvote'                 => [ 'post_id' => 42, 'user_id' => 2 ],
			'add_reaction'                  => [ 'post_id' => 42, 'user_id' => 2, 'reaction' => 'love' ],
			'remove_reaction'               => [ 'post_id' => 42, 'user_id' => 2 ],
		];
	}

	// =========================================================================
	// Trigger specifics
	// =========================================================================

	public function test_item_created_returns_payload_for_ecm_post_type(): void {
		$result = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'item_created' ),
			[ 42, null, false ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 42, $result['item_id'] );
		$this->assertEquals( 'Sample Listing', $result['title'] );
		$this->assertEquals( 'listing', $result['post_type'] );
	}

	public function test_item_created_ignores_non_ecm_post_types(): void {
		WPMocks::setPost( 2, [ 'post_title' => 'A Page', 'post_type' => 'page' ] );

		$result = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'item_created' ),
			[ 2, null, false ]
		);

		$this->assertFalse( $result );
	}

	public function test_item_created_ignores_post_updates(): void {
		$result = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'item_created' ),
			[ 42, null, true ]
		);

		$this->assertFalse( $result );
	}

	public function test_specific_item_created_filters_by_post_type(): void {
		$matching = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'specific_item_created', [ 'post_type' => 'listing' ] ),
			[ 42, null, false ]
		);
		$this->assertIsArray( $matching );

		$other = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'specific_item_created', [ 'post_type' => 'recipe' ] ),
			[ 42, null, false ]
		);
		$this->assertFalse( $other );
	}

	public function test_item_published_requires_publish_status(): void {
		$item = (object) [ 'ID' => 42, 'post_type' => 'listing' ];

		$published = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'item_published' ),
			[ 'publish', 'draft', $item ]
		);
		$this->assertIsArray( $published );
		$this->assertEquals( 'publish', $published['new_status'] );

		$drafted = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'item_published' ),
			[ 'draft', 'publish', $item ]
		);
		$this->assertFalse( $drafted );
	}

	public function test_custom_field_updated_requires_an_ecm_field_key(): void {
		$ecm_field = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'custom_field_updated' ),
			[ 999, 42, 'price', '199' ]
		);
		$this->assertIsArray( $ecm_field );
		$this->assertEquals( 'price', $ecm_field['meta_key'] );
		$this->assertEquals( '199', $ecm_field['value'] );

		$random = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'custom_field_updated' ),
			[ 999, 42, 'some_random_key', '199' ]
		);
		$this->assertFalse( $random );

		$internal = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'custom_field_updated' ),
			[ 999, 42, '_ecm_upvoted_users', 'x' ]
		);
		$this->assertFalse( $internal );
	}

	public function test_user_meta_updated_requires_an_ecm_user_field_key(): void {
		$ecm_field = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'user_meta_updated' ),
			[ 888, 1, 'phone', '123' ]
		);
		$this->assertIsArray( $ecm_field );
		$this->assertEquals( 'phone', $ecm_field['meta_key'] );

		$random = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'user_meta_updated' ),
			[ 888, 1, 'wp_capabilities', 'x' ]
		);
		$this->assertFalse( $random );
	}

	public function test_review_triggers_only_match_the_ecm_review_comment_type(): void {
		$review = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'review_submitted' ),
			[ 77, WPMocks::getComment( 77 ) ]
		);
		$this->assertIsArray( $review );
		$this->assertEquals( 77, $review['review_id'] );
		$this->assertEquals( 42, $review['post_id'] );

		WPMocks::setComment( 78, [ 'comment_post_ID' => 42, 'comment_type' => 'comment' ] );

		$plain_comment = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'review_submitted' ),
			[ 78, WPMocks::getComment( 78 ) ]
		);
		$this->assertFalse( $plain_comment );
	}

	public function test_review_rejected_requires_a_non_approved_status(): void {
		$rejected = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'review_rejected' ),
			[ 'spam', 'approved', WPMocks::getComment( 77 ) ]
		);
		$this->assertIsArray( $rejected );

		$still_approved = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'review_rejected' ),
			[ 'approved', 'approved', WPMocks::getComment( 77 ) ]
		);
		$this->assertFalse( $still_approved );
	}

	public function test_rating_submitted_carries_the_rating_value(): void {
		$result = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'rating_submitted' ),
			[ 999, 77, '_ecm_rating', 4.5 ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 4.5, $result['rating'] );
		$this->assertEquals( 77, $result['review_id'] );

		$wrong_key = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'rating_submitted' ),
			[ 999, 77, '_ecm_review_title', 'Nice' ]
		);
		$this->assertFalse( $wrong_key );
	}

	public function test_claim_submitted_reads_the_rest_request(): void {
		$result = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'claim_submitted' ),
			[
				null,
				null,
				$this->makeRestRequest(
					'/easy-content-manager/v1/claim/submit',
					[ 'post_id' => 42, 'name' => 'Jane', 'email' => 'jane@example.com' ]
				),
			]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 42, $result['post_id'] );
		$this->assertEquals( 'Jane', $result['name'] );
		$this->assertEquals( 'pending', $result['status'] );

		$other_route = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'claim_submitted' ),
			[ null, null, $this->makeRestRequest( '/wp/v2/posts', [] ) ]
		);
		$this->assertFalse( $other_route );
	}

	public function test_claim_status_triggers_match_route_id_and_status(): void {
		$approved = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'claim_approved' ),
			[ null, null, $this->makeRestRequest( '/easy-content-manager/v1/claims/12', [ 'status' => 'approved' ], 'PATCH' ) ]
		);
		$this->assertIsArray( $approved );
		$this->assertEquals( 12, $approved['claim_id'] );

		$wrong_status = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'claim_approved' ),
			[ null, null, $this->makeRestRequest( '/easy-content-manager/v1/claims/12', [ 'status' => 'rejected' ], 'PATCH' ) ]
		);
		$this->assertFalse( $wrong_status );

		$rejected = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'claim_rejected' ),
			[ null, null, $this->makeRestRequest( '/easy-content-manager/v1/claims/13', [ 'status' => 'rejected' ], 'PATCH' ) ]
		);
		$this->assertIsArray( $rejected );
		$this->assertEquals( 13, $rejected['claim_id'] );
	}

	public function test_bookmark_added_and_removed_diff_the_bookmarker_list(): void {
		$added = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'bookmark_added' ),
			[ 999, 42, 'ecm_bookmark_users', serialize( [ 5, 6, 7 ] ) ]
		);
		$this->assertIsArray( $added );
		$this->assertEquals( [ 7 ], $added['user_ids'] );
		$this->assertEquals( 7, $added['user_id'] );

		$removed = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'bookmark_removed' ),
			[ 999, 42, 'ecm_bookmark_users', serialize( [ 5 ] ) ]
		);
		$this->assertIsArray( $removed );
		$this->assertEquals( [ 6 ], $removed['user_ids'] );

		$no_change = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'bookmark_added' ),
			[ 999, 42, 'ecm_bookmark_users', serialize( [ 5, 6 ] ) ]
		);
		$this->assertFalse( $no_change );
	}

	public function test_upvote_added_detects_new_upvoters_and_guest_count_bumps(): void {
		$logged_in = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'upvote_added' ),
			[ 999, 42, '_ecm_upvoted_users', [ 5, 6, 7 ] ]
		);
		$this->assertIsArray( $logged_in );
		$this->assertEquals( 7, $logged_in['user_id'] );

		$guest = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'upvote_added' ),
			[ 999, 42, 'ecm_upvote_count', 3 ]
		);
		$this->assertIsArray( $guest );
		$this->assertEquals( 0, $guest['user_id'] );
		$this->assertTrue( $guest['is_guest'] );

		$count_drop = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'upvote_added' ),
			[ 999, 42, 'ecm_upvote_count', 1 ]
		);
		$this->assertFalse( $count_drop );
	}

	public function test_reaction_triggers_diff_the_reaction_map(): void {
		$added = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'reaction_added' ),
			[ 999, 42, '_ecm_reactions', [ 'like' => [ 5, 6 ] ] ]
		);
		$this->assertIsArray( $added );
		$this->assertEquals( 'like', $added['reaction'] );
		$this->assertEquals( 6, $added['user_id'] );

		$removed = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'reaction_removed' ),
			[ 999, 42, '_ecm_reactions', [ 'love' => [ 5 ] ] ]
		);
		$this->assertIsArray( $removed );
		$this->assertEquals( 'like', $removed['reaction'] );

		$no_change = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'reaction_added' ),
			[ 999, 42, '_ecm_reactions', [ 'like' => [ 5 ] ] ]
		);
		$this->assertFalse( $no_change );
	}

	public function test_triggers_return_false_without_ecm_config_payloads(): void {
		$result = EasyContentManager::resolve_trigger(
			$this->makeTriggerNode( 'item_created' ),
			[]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_returns_false_for_unknown_event(): void {
		$node          = $this->makeTriggerNode( 'item_created' );
		$node['event'] = '__unknown__';
		$this->assertFalse( EasyContentManager::resolve_trigger( $node, [ 42, null, false ] ) );
	}

	// =========================================================================
	// Action specifics
	// =========================================================================

	public function test_action_create_item_returns_the_new_item(): void {
		$result = EasyContentManager::execute_node(
			$this->makeActionNode( 'create_item', [ 'post_type' => 'listing', 'post_title' => 'Fresh', 'post_status' => 'draft' ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertNotEmpty( $result['data']['item_id'] );
		$this->assertEquals( 'Fresh', $result['data']['title'] );
	}

	public function test_action_create_item_requires_title_and_type(): void {
		$result = EasyContentManager::execute_node(
			$this->makeActionNode( 'create_item', [ 'post_type' => 'listing' ] ),
			[]
		);
		$this->assertFalse( $result['data']['success'] );

		$result = EasyContentManager::execute_node(
			$this->makeActionNode( 'create_item', [ 'post_title' => 'No type' ] ),
			[]
		);
		$this->assertFalse( $result['data']['success'] );
	}

	public function test_action_update_multiple_fields_reads_map_rows(): void {
		$result = EasyContentManager::execute_node(
			$this->makeActionNode(
				'update_multiple_custom_fields',
				[ 'post_id' => 42, 'fields' => [ [ 'key' => 'price', 'value' => '199' ] ] ]
			),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertTrue( $result['data']['success'] );
		$this->assertEquals( '199', $result['data']['updated']['price'] );
	}

	public function test_action_find_item_always_returns_an_items_list(): void {
		$result = EasyContentManager::execute_node(
			$this->makeActionNode( 'find_item', [ 'post_type' => 'listing', 'search_value' => 'Sample' ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertArrayHasKey( 'items', $result['data'] );
		$this->assertArrayHasKey( 'found', $result['data'] );
	}

	public function test_action_unknown_event_reports_an_error(): void {
		$result = EasyContentManager::execute_node(
			$this->makeActionNode( 'not_a_real_action', [] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertArrayHasKey( 'error', $result['data'] );
	}

	// =========================================================================
	// Contract
	// =========================================================================

	public function test_get_slug_matches_the_registry_key(): void {
		$this->assertEquals( 'easycontentmanager', EasyContentManager::get_slug() );
	}

	public function test_all_triggers_registered_with_label_and_hook(): void {
		$triggers = EasyContentManager::get_triggers();

		$this->assertCount( 28, $triggers );

		foreach ( $triggers as $event => $def ) {
			$this->assertArrayHasKey( 'label', $def, "Trigger '$event' missing label" );
			$this->assertArrayHasKey( 'hook', $def, "Trigger '$event' missing hook" );
			$this->assertNotEmpty( $def['label'] );
			$this->assertNotEmpty( $def['hook'] );
		}
	}

	public function test_all_actions_registered_with_label(): void {
		$actions = EasyContentManager::get_actions();

		$this->assertCount( 34, $actions );

		foreach ( $actions as $event => $def ) {
			$this->assertArrayHasKey( 'label', $def, "Action '$event' missing label" );
			$this->assertNotEmpty( $def['label'] );
		}
	}

	public function test_claim_triggers_ride_rest_pre_dispatch(): void {
		$triggers = EasyContentManager::get_triggers();

		foreach ( [ 'claim_submitted', 'claim_approved', 'claim_rejected' ] as $event ) {
			$this->assertEquals( 'rest_pre_dispatch', $triggers[ $event ]['hook'], "$event must use rest_pre_dispatch" );
		}
	}

	public function test_dynamic_queries_return_value_label_rows(): void {
		$queries = EasyContentManager::get_dynamic_queries();

		$this->assertArrayHasKey( 'post_types', $queries );
		$this->assertArrayHasKey( 'taxonomies', $queries );
		$this->assertArrayHasKey( 'post_meta_keys', $queries );
		$this->assertArrayHasKey( 'user_meta_keys', $queries );

		$post_types = $queries['post_types']();
		$this->assertNotEmpty( $post_types );
		$this->assertEquals( 'listing', $post_types[0]['value'] );
	}
}
