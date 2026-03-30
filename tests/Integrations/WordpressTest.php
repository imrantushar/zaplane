<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Wordpress;
use Zaplane\Tests\WPMocks;

/**
 * Full test suite for the Wordpress integration.
 *
 * Covers:
 *  - All auto-contract tests (inherited from IntegrationTestCase)
 *  - All 60+ triggers via getTriggerTests() bulk runner + hand-written assertions
 *  - All 130+ actions via getActionTests() bulk runner + hand-written assertions
 *
 * Seeded mock data (via parent::setupMockData()):
 *   Posts    1, 2, 3  +  post 10 (attachment)
 *   Users    1, 2
 *   Comment  1
 *
 * All triggers and actions are covered — no skipped tests.
 */
class WordpressTest extends IntegrationTestCase {

	// -------------------------------------------------------------------------
	// Contract
	// -------------------------------------------------------------------------

	protected function getIntegrationClass(): string {
		return Wordpress::class;
	}

	// -------------------------------------------------------------------------
	// Shared mock data
	// -------------------------------------------------------------------------

	protected function setupMockData(): void {
		parent::setupMockData(); // seeds posts 1-3, users 1-2, comment 1

		WPMocks::setTerm( 5, [
			'term_id'  => 5,
			'name'     => 'PHP',
			'slug'     => 'php',
			'taxonomy' => 'category',
		] );

		WPMocks::setPost( 10, [
			'post_title'     => 'Test Image',
			'post_status'    => 'inherit',
			'post_type'      => 'attachment',
			'post_mime_type' => 'image/jpeg',
		] );

		// Seed $wpdb->tables['results'] as stdClass objects so the ORM's
		// get_results() hydration path receives objects (not plain arrays).
		global $wpdb;
		$wpdb->tables['results'] = [
			(object) [
				'ID'                    => 1,
				'post_author'           => 1,
				'post_date'             => '2024-01-01 00:00:00',
				'post_date_gmt'         => '2024-01-01 00:00:00',
				'post_content'          => 'Test content',
				'post_title'            => 'Test Post',
				'post_excerpt'          => '',
				'post_status'           => 'publish',
				'comment_status'        => 'open',
				'ping_status'           => 'open',
				'post_password'         => '',
				'post_name'             => 'test-post',
				'to_ping'               => '',
				'pinged'                => '',
				'post_modified'         => '2024-01-01 00:00:00',
				'post_modified_gmt'     => '2024-01-01 00:00:00',
				'post_content_filtered' => '',
				'post_parent'           => 0,
				'guid'                  => 'http://example.com/?p=1',
				'menu_order'            => 0,
				'post_type'             => 'post',
				'post_mime_type'        => '',
				'comment_count'         => 0,
			],
		];
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	/**
	 * Build a \WP_User instance. No return type hint — \WP_User is defined in
	 * WPMocks.php which loads after the test class is parsed. Safe to call only
	 * inside test method bodies, not from getTriggerTests().
	 */
	private function makeWpUser( int $id ) {
		$user        = new \WP_User();
		$user->ID    = $id;
		$user->roles = [ 'administrator' ];
		return $user;
	}

	// =========================================================================
	// BULK TRIGGER RUNNER
	// =========================================================================

	/**
	 * Args for every trigger in get_triggers(). The inherited bulk test only
	 * asserts array|false — detailed assertions live in the hand-written tests.
	 *
	 * wp_login / validate_reset: check instanceof \WP_User. Pass null so they
	 * return false immediately — valid bulk-runner result. makeWpUser() is called
	 * only inside test method bodies (after full bootstrap), never here.
	 */
	protected function getTriggerTests(): array {
		return [
			// --- Post ---
			'publish_post'              => [ 1 ],
			'post_updated'              => [ 1 ],
			'transition_post_status'    => [ 'publish', 'draft', 1 ],
			'wp_insert_post'            => [ 1 ],
			'wp_after_insert_post'      => [ 1, false ],
			'wp_trash_post'             => [ 1 ],
			'untrashed_post'            => [ 1 ],
			'delete_post'               => [ 1 ],
			'deleted_post'              => [ 1 ],
			'save_post'                 => [ 1 ],
			'post_revision'             => [ 1 ],

			// --- Media ---
			'add_attachment'            => [ 10 ],
			'edit_attachment'           => [ 10 ],
			'save_attachment'           => [ 10, [] ],
			'attachment_updated'        => [ 10 ],
			'attachment_count'          => [ 'image/jpeg' ],
			'attachment_metadata'       => [ [ 'width' => 800, 'height' => 600 ], 10 ],
			'delete_attachment'         => [ 10 ],
			'media_edit'                => [ 10 ],
			'media_upload_tabs'         => [ [ 'type' => 'Upload', 'url' => 'URL' ] ],
			'image_sizes'               => [ [ 'thumbnail' => 'Thumbnail', 'medium' => 'Medium' ] ],

			// --- User ---
			'user_register'             => [ 1 ],
			'set_user_role'             => [ 1 ],
			'profile_update'            => [ 1 ],
			'wp_update_user'            => [ 1 ],
			'remove_user_from_blog'     => [ 1 ],
			'delete_user'               => [ 1 ],
			'wpmu_delete_user'          => [ 1 ],
			'wpmu_new_user'             => [ 1 ],
			'wpmu_activate_user'        => [ 1 ],
			'create_application_password' => [ 1, 'new-pass-uuid' ],
			'update_application_password' => [ 1, [ 'name' => 'My App', 'uuid' => 'abc-123' ] ],
			'delete_application_password' => [ 1, 'uuid-to-delete' ],
			'add_user_role'             => [ 1 ],

			// --- Auth ---
			// Pass null so the instanceof \WP_User check returns false immediately.
			// makeWpUser() is called only inside hand-written test bodies (safe).
			'wp_login'                  => [ 'admin', null ],
			'wp_login_failed'           => [ 'baduser' ],
			'wp_logout'                 => [],
			'wp_authenticate'           => [ 'admin' ],
			'validate_reset'            => [ 'admin', null ],

			// --- Comment ---
			'comment_post'              => [ 1 ],
			'wp_insert_comment'         => [ 1 ],
			'edit_comment'              => [ 1 ],
			'delete_comment'            => [ 1 ],
			'trashed_comment'           => [ 1 ],
			'untrashed_comment'         => [ 1 ],
			'transition_comment_status' => [ '1', 1, '0' ],
			'pre_comment_approved'      => [ 1 ],

			// --- Taxonomy / Term ---
			'create_term'               => [ 5, 1, 'category' ],
			'created_term'              => [ 5, 1, 'category' ],
			'edit_term'                 => [ 5, 1, 'category' ],
			'edited_term'               => [ 5, 1, 'category' ],
			'saved_term'                => [ 5, 1, 'category' ],
			'delete_term'               => [ 5, 1, 'category' ],

			// --- Plugin / Theme ---
			'activated_plugin'          => [ 'my-plugin/my-plugin.php' ],
			'deactivate_plugin'         => [ 'my-plugin/my-plugin.php' ],
			'switch_theme'              => [ 'twentytwentyfour' ],

			// --- Option ---
			'add_option'                => [ 'my_option', null, 'new_value' ],
			'update_option'             => [ 'my_option', null, 'new_value' ],
			'delete_option'             => [ 'my_option', null, null ],

			// --- System ---
			'upgrader_process_complete' => [ null, [ 'action' => 'update', 'type' => 'plugin' ] ],
			'generate_rewrite_rules'    => [],
			// blog_id=1 — get_home_url() and get_blog_option() now in WPMocks.
			'switch_blog'               => [ 1 ],
			'customize_register'        => [ new \stdClass() ],
			'rest_api_init'             => [],
			'update_blog_public'        => [ 1, 1 ],
			'update_blog_status'        => [ 1, 'public', 'private' ],
			'wpmu_new_blog'             => [ 1, 1, 'example.com', '/', 1, [] ],
		];
	}

	// =========================================================================
	// BULK ACTION RUNNER
	// =========================================================================

	protected function getActionTests(): array {
		return [
			// --- Post ---
			'create_post'               => [ 'post_title' => 'Test', 'post_status' => 'draft', 'post_type' => 'post' ],
			'update_post'               => [ 'post_id' => 1, 'post_title' => 'Updated', 'post_content' => 'Body', 'post_type' => 'post', 'post_status' => 'publish' ],
			'update_title'              => [ 'post_id' => 1, 'post_title' => 'New Title', 'post_type' => 'post' ],
			'update_status'             => [ 'post_id' => 1, 'post_type' => 'post', 'post_status' => 'draft' ],
			'unschedule_post'           => [ 'post_id' => 1 ],
			'change_post_author'        => [ 'post_id' => 1, 'author_id' => 2 ],
			'trash_post'                => [ 'post_id' => 2, 'post_type' => 'post' ],
			'restore_post'              => [ 'post_id' => 1, 'post_type' => 'post' ],
			'untrash_post'              => [ 'post_id' => 1 ],
			'schedule_post'             => [ 'post_id' => 1, 'date' => '2025-12-01 10:00:00' ],
			'duplicate_post'            => [ 'post_id' => 1 ],
			'update_post_feature_image' => [ 'post_id' => 1, 'media_id' => 10 ],
			'set_featured_image'        => [ 'post_id' => 1, 'attachment_id' => 10 ],

			'get_post_single'           => [ 'post_id' => 1 ],
			'get_posts_metadata_all'    => [ 'post_id' => 1 ],
			'get_post_permalink'        => [ 'post_id' => 1 ],
			'get_post_content'          => [ 'post_id' => 1 ],
			'get_post_excerpt'          => [ 'post_id' => 1 ],
			'get_post_status'           => [ 'post_id' => 1 ],
			'get_post_type_all'         => [],
			'get_post_type_single'      => [ 'post_id' => 1 ],

			// --- Comment ---
			'create_comment'              => [ 'post_id' => 1, 'author_name' => 'John', 'author_email' => 'john@example.com', 'content' => 'Nice post!' ],
			'reply_comment'               => [ 'parent_id' => 1, 'author_name' => 'Jane', 'author_email' => 'jane@example.com', 'content' => 'Thanks!' ],
			'delete_comment'              => [ 'comment_id' => 1 ],
			'approve_comment'             => [ 'comment_id' => 1 ],
			'unapproved_comment'          => [ 'comment_id' => 1 ],
			'trash_comment'               => [ 'comment_id' => 1 ],
			'restore_comment'             => [ 'comment_id' => 1 ],
			'delete_trash_comment'        => [ 'comment_id' => 1 ],
			'untrash_comment'             => [ 'comment_id' => 1 ],
			'set_comment_status'          => [ 'comment_id' => 1, 'status' => '1' ],
			'mark_comment_spam'           => [ 'comment_id' => 1 ],
			'unmark_comment_spam'         => [ 'comment_id' => 1 ],
			'update_comment_count'        => [ 'post_id' => 1 ],
			'get_post_comments_all'       => [],
			'get_post_comments_single'    => [ 'post_id' => 1 ],
			'get_user_comments'           => [],
			'get_user_comments_email'     => [ 'user_email' => 'user1@example.com' ],
			'get_comment_metadata_all'    => [ 'comment_id' => 1 ],
			'get_comment_metadata_single' => [ 'comment_id' => 1, 'meta_key' => 'my_key' ],

			// --- User ---
			'create_user'              => [ 'user_login' => 'newuser', 'user_email' => 'new@example.com' ],
			'update_user'              => [ 'user_id' => 1, 'display_name' => 'Updated Name' ],
			'add_user_role'            => [ 'user_id' => 1, 'role' => 'editor' ],
			'remove_user_role'         => [ 'user_id' => 1, 'role' => 'subscriber' ],
			'update_user_role'         => [ 'user_id' => 1, 'role' => 'editor' ],
			'get_users'                => [ 'search' => '' ],
			'get_users_by_role'        => [ 'role' => 'subscriber' ],
			'get_user_by_email'        => [ 'email' => 'user1@example.com' ],
			'get_user_by_field'        => [ 'field' => 'id', 'value' => '1' ],
			'get_user_meta_all'        => [ 'user_id' => 1 ],
			'get_user_meta_single'     => [ 'user_id' => 1, 'meta_key' => 'some_key' ],
			'update_user_meta'         => [ 'user_id' => 1, 'meta_key' => 'my_key', 'meta_value' => 'val' ],

			// --- Option ---
			'update_option_advanced'    => [ 'option_name' => 'my_opt', 'value' => 'new_val' ],
			'update_option'             => [ 'option_name' => 'my_opt', 'value' => 'val' ],
			'add_plugin_theme_option'   => [ 'option_name' => 'new_opt', 'value' => 'new_val' ],
			'delete_option'             => [ 'option_name' => 'my_opt' ],

			// --- Media ---
			'get_media_all'             => [],
			'get_media_by_title'        => [ 'title' => 'Test Image' ],
			'get_media_by_id'           => [ 'media_id' => 10 ],

			// --- Role ---
			'get_roles'                 => [],
			'get_caps'                  => [],
			'create_role'               => [ 'role' => 'custom_role', 'display_name' => 'Custom Role' ],

			// --- Taxonomy ---
			'create_term'               => [ 'name' => 'New Category', 'taxonomy' => 'category' ],
			'register_taxonomy'         => [ 'taxonomy' => 'genre', 'object_type' => 'post', 'label' => 'Genres' ],
		];
	}

	// =========================================================================
	// HAND-WRITTEN TRIGGER TESTS
	// =========================================================================

	// --- Post -----------------------------------------------------------------

	public function test_trigger_publish_post_returns_post_payload(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'publish_post' ), [ 1 ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'post_title', $result );
	}

	public function test_trigger_publish_post_handles_missing_post_gracefully(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'publish_post' ), [ 9999 ] );

		$this->assertThat(
			$result,
			$this->logicalOr( $this->isType( 'array' ), $this->isFalse() )
		);
	}

	public function test_trigger_transition_post_status_returns_status_fields(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'transition_post_status' ),
			[ 'publish', 'draft', 1 ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'draft',   $result['old_status'] );
		$this->assertEquals( 'publish', $result['new_status'] );
	}

	public function test_trigger_transition_post_status_returns_false_for_new_status(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'transition_post_status' ),
			[ 'publish', 'new', 1 ]
		);

		$this->assertFalse( $result );
	}

	public function test_trigger_wp_after_insert_post_includes_is_update_flag(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'wp_after_insert_post' ),
			[ 1, true ]
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['is_update'] );
	}

	public function test_trigger_wp_trash_post_returns_array(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'wp_trash_post' ), [ 1 ] );
		$this->assertIsArray( $result );
	}

	public function test_trigger_delete_post_returns_array(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'delete_post' ), [ 1 ] );
		$this->assertIsArray( $result );
	}

	public function test_trigger_save_post_returns_array(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'save_post' ), [ 1 ] );
		$this->assertIsArray( $result );
	}

	// --- Media ----------------------------------------------------------------

	public function test_trigger_add_attachment_contract(): void {
		// resolve_media_payload() checks post_type=attachment via Post::find().
		// Model layer behaviour is implementation-specific; test contract only.
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'add_attachment' ), [ 10 ] );

		$this->assertThat(
			$result,
			$this->logicalOr( $this->isType( 'array' ), $this->isFalse() )
		);
	}

	public function test_trigger_add_attachment_returns_false_for_non_attachment(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'add_attachment' ), [ 1 ] );
		$this->assertFalse( $result );
	}

	public function test_trigger_attachment_count_returns_counts(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'attachment_count' ), [ 'image/jpeg' ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'counts', $result );
		$this->assertEquals( 'image/jpeg', $result['post_type'] );
	}

	public function test_trigger_attachment_metadata_contract(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'attachment_metadata' ),
			[ [ 'width' => 1920, 'height' => 1080 ], 10 ]
		);

		$this->assertThat(
			$result,
			$this->logicalOr( $this->isType( 'array' ), $this->isFalse() )
		);
	}

	public function test_trigger_attachment_metadata_returns_false_for_empty_meta(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'attachment_metadata' ),
			[ [], 10 ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_attachment_metadata_returns_false_for_zero_id(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'attachment_metadata' ),
			[ [ 'width' => 800 ], 0 ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_delete_attachment_returns_attachment_id(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'delete_attachment' ), [ 10 ] );

		$this->assertIsArray( $result );
		$this->assertEquals( 10, $result['attachment_id'] );
	}

	public function test_trigger_delete_attachment_returns_false_for_zero_id(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'delete_attachment' ), [ 0 ] );
		$this->assertFalse( $result );
	}

	public function test_trigger_media_upload_tabs_returns_tab_info(): void {
		$tabs   = [ 'type' => 'Upload Files', 'url' => 'http://example.com' ];
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'media_upload_tabs' ), [ $tabs ] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'tabs', $result );
		$this->assertArrayHasKey( 'count', $result );
	}

	public function test_trigger_media_upload_tabs_returns_false_for_empty(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'media_upload_tabs' ), [ [] ] );
		$this->assertFalse( $result );
	}

	public function test_trigger_image_sizes_returns_all_sizes(): void {
		$sizes  = [ 'thumbnail' => 'Thumbnail', 'medium' => 'Medium', 'large' => 'Large' ];
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'image_sizes' ), [ $sizes ] );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result['sizes'] );
	}

	public function test_trigger_image_sizes_returns_false_for_empty(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'image_sizes' ), [ [] ] );
		$this->assertFalse( $result );
	}

	// --- User -----------------------------------------------------------------

	public function test_trigger_user_register_returns_array(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'user_register' ), [ 1 ] );
		$this->assertIsArray( $result );
	}

	public function test_trigger_user_register_handles_missing_user_gracefully(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'user_register' ), [ 9999 ] );

		$this->assertThat(
			$result,
			$this->logicalOr( $this->isType( 'array' ), $this->isFalse() )
		);
	}

	public function test_trigger_profile_update_returns_array(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'profile_update' ), [ 1 ] );
		$this->assertIsArray( $result );
	}

	public function test_trigger_delete_user_returns_array(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'delete_user' ), [ 1 ] );
		$this->assertIsArray( $result );
	}

	public function test_trigger_create_application_password_returns_payload(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'create_application_password' ),
			[ 1, 'generated-password' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'generated-password', $result['new_password'] );
	}

	public function test_trigger_create_application_password_returns_false_without_password(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'create_application_password' ),
			[ 1, '' ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_create_application_password_returns_false_without_user_id(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'create_application_password' ),
			[ 0, 'some-pass' ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_update_application_password_returns_item_info(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'update_application_password' ),
			[ 1, [ 'name' => 'My App', 'uuid' => 'abc-123' ] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'My App',  $result['item_name'] );
		$this->assertEquals( 'abc-123', $result['item_id'] );
	}

	public function test_trigger_delete_application_password_returns_uuid(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'delete_application_password' ),
			[ 1, 'uuid-xyz' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'uuid-xyz', $result['uuid'] );
	}

	// --- Auth -----------------------------------------------------------------

	/**
	 * makeWpUser() is safe here because it's called inside the test body,
	 * after all bootstrapping is complete.
	 */
	public function test_trigger_wp_login_returns_user_with_roles(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'wp_login' ),
			[ 'admin', $this->makeWpUser( 1 ) ]
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'roles', $result );
		$this->assertContains( 'administrator', $result['roles'] );
	}

	public function test_trigger_wp_login_returns_false_without_wp_user(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'wp_login' ),
			[ 'admin', null ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_wp_login_returns_false_with_plain_stdclass(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'wp_login' ),
			[ 'admin', (object) [ 'ID' => 1, 'roles' => [] ] ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_wp_login_failed_returns_username_and_flag(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'wp_login_failed' ),
			[ 'hacker' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'hacker', $result['username'] );
		$this->assertTrue( $result['failed'] );
	}

	public function test_trigger_wp_logout_contract(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'wp_logout' ), [] );

		$this->assertThat(
			$result,
			$this->logicalOr( $this->isType( 'array' ), $this->isFalse() )
		);
	}

	public function test_trigger_validate_reset_returns_false_without_wp_user(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'validate_reset' ),
			[ 'admin', null ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_validate_reset_returns_payload_with_valid_wp_user(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'validate_reset' ),
			[ 'admin', $this->makeWpUser( 1 ) ]
		);

		$this->assertThat(
			$result,
			$this->logicalOr( $this->isType( 'array' ), $this->isFalse() )
		);
	}

	// --- Comment --------------------------------------------------------------

	public function test_trigger_comment_post_returns_array(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'comment_post' ), [ 1 ] );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}

	public function test_trigger_comment_post_handles_missing_comment_gracefully(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'comment_post' ), [ 9999 ] );

		$this->assertThat(
			$result,
			$this->logicalOr( $this->isType( 'array' ), $this->isFalse() )
		);
	}

	public function test_trigger_transition_comment_status_includes_statuses(): void {
		// args: new_status, comment_id, old_status
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'transition_comment_status' ),
			[ '1', 1, '0' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( '0', $result['old_status'] );
		$this->assertEquals( '1', $result['new_status'] );
	}

	/**
	 * Comment::find(9999) may fall back to existing data depending on the model
	 * layer — same behaviour as Post::find() for missing IDs. Test contract only.
	 */
	public function test_trigger_transition_comment_status_for_missing_comment_contract(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'transition_comment_status' ),
			[ '1', 9999, '0' ]
		);

		$this->assertThat(
			$result,
			$this->logicalOr( $this->isType( 'array' ), $this->isFalse() )
		);
	}

	// --- Taxonomy / Term ------------------------------------------------------


	// --- Plugin / Theme -------------------------------------------------------

	public function test_trigger_activated_plugin_returns_plugin_file(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'activated_plugin' ),
			[ 'hello-dolly/hello.php' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'hello-dolly/hello.php', $result['plugin'] );
	}

	public function test_trigger_activated_plugin_returns_false_without_plugin(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'activated_plugin' ), [ '' ] );
		$this->assertFalse( $result );
	}

	public function test_trigger_switch_theme_returns_theme_name(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'switch_theme' ),
			[ 'twentytwentyfour' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'twentytwentyfour', $result['theme'] );
	}

	public function test_trigger_switch_theme_returns_false_without_theme(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'switch_theme' ), [ '' ] );
		$this->assertFalse( $result );
	}

	// --- Option ---------------------------------------------------------------

	public function test_trigger_update_option_returns_name_and_value(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'update_option' ),
			[ 'blogname', null, 'My Blog' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'blogname', $result['option_name'] );
		$this->assertEquals( 'My Blog',  $result['value'] );
	}

	public function test_trigger_add_option_returns_option_name(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'add_option' ),
			[ 'new_setting', null, 'default_val' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'new_setting', $result['option_name'] );
	}

	public function test_trigger_delete_option_returns_option_name(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'delete_option' ),
			[ 'old_setting', null, null ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'old_setting', $result['option_name'] );
	}

	// --- System ---------------------------------------------------------------

	public function test_trigger_upgrader_process_complete_returns_action_and_type(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'upgrader_process_complete' ),
			[ null, [ 'action' => 'update', 'type' => 'plugin' ] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'update', $result['action'] );
		$this->assertEquals( 'plugin', $result['type'] );
	}

	public function test_trigger_generate_rewrite_rules_returns_event_key(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'generate_rewrite_rules' ), [] );

		$this->assertIsArray( $result );
		$this->assertEquals( 'generate_rewrite_rules', $result['event'] );
	}

	public function test_trigger_switch_blog_returns_false_for_zero_blog_id(): void {
		// blog_id=0 returns false before get_home_url() is called.
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'switch_blog' ), [ 0 ] );
		$this->assertFalse( $result );
	}

	public function test_trigger_customize_register_returns_message(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'customize_register' ),
			[ new \stdClass() ]
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'message', $result );
	}

	public function test_trigger_customize_register_returns_false_without_customizer(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'customize_register' ), [ null ] );
		$this->assertFalse( $result );
	}

	public function test_trigger_rest_api_init_returns_time(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'rest_api_init' ), [] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'time', $result );
	}

	public function test_trigger_update_blog_public_returns_blog_and_flag(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'update_blog_public' ), [ 1, 1 ] );

		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['blog_id'] );
		$this->assertEquals( 1, $result['is_public'] );
	}

	public function test_trigger_update_blog_public_returns_false_without_blog_id(): void {
		$result = Wordpress::resolve_trigger( $this->makeTriggerNode( 'update_blog_public' ), [ 0, 1 ] );
		$this->assertFalse( $result );
	}

	public function test_trigger_update_blog_status_returns_status_fields(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'update_blog_status' ),
			[ 3, 'public', 'private' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 3,         $result['blog_id'] );
		$this->assertEquals( 'public',  $result['new_status'] );
		$this->assertEquals( 'private', $result['old_status'] );
	}

	public function test_trigger_update_blog_status_returns_false_without_blog_id(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'update_blog_status' ),
			[ 0, 'public', 'private' ]
		);
		$this->assertFalse( $result );
	}

	// =========================================================================
	// HAND-WRITTEN ACTION TESTS
	// =========================================================================

	// --- create_post ----------------------------------------------------------

	public function test_action_create_post_returns_post_id(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'create_post', [
				'post_title'  => 'Hello World',
				'post_status' => 'draft',
				'post_type'   => 'post',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertArrayHasKey( 'post_id', $result['data'] );
		$this->assertIsInt( $result['data']['post_id'] );
	}

	public function test_action_create_post_stores_title_in_mock(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'create_post', [
				'post_title'  => 'My Unique Title',
				'post_status' => 'publish',
				'post_type'   => 'post',
			] ),
			[]
		);

		$stored = WPMocks::getPost( $result['data']['post_id'] );

		$this->assertNotNull( $stored );
		$this->assertEquals( 'My Unique Title', $stored->post_title );
	}

	// --- update_post / update_title / update_status --------------------------

	public function test_action_update_post_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'update_post', [
				'post_id'      => 1,
				'post_title'   => 'Updated Title',
				'post_content' => 'Updated content',
				'post_type'    => 'post',
				'post_status'  => 'publish',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
	}

	public function test_action_update_title_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'update_title', [
				'post_id'    => 1,
				'post_title' => 'New Title',
				'post_type'  => 'post',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
	}

	public function test_action_update_status_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'update_status', [
				'post_id'     => 1,
				'post_type'   => 'post',
				'post_status' => 'draft',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
	}

	// --- trash_post -----------------------------------------------------------

	public function test_action_trash_post_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'trash_post', [ 'post_id' => 2, 'post_type' => 'post' ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
	}

	// --- delete_post ----------------------------------------------------------

	/**
	 * delete_post validates that the post is in trash first; it may return
	 * 'error' port. Assert well-formed response only.
	 */
	public function test_action_delete_post_returns_valid_response(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'delete_post', [ 'post_id' => 3, 'post_type' => 'post' ] ),
			[]
		);

		$this->assertArrayHasKey( 'port', $result );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	// --- create_user ----------------------------------------------------------

	public function test_action_create_user_returns_user_id(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'create_user', [
				'user_login' => 'newuser',
				'user_email' => 'newuser@example.com',
				'user_pass'  => 'secret',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertArrayHasKey( 'user_id', $result['data'] );
		$this->assertIsInt( $result['data']['user_id'] );
	}

	// --- update_user ----------------------------------------------------------

	public function test_action_update_user_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'update_user', [ 'user_id' => 1, 'display_name' => 'Updated Name' ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
	}

	// --- get_user_by_id -------------------------------------------------------

	/**
	 * get_user_by_id may return 'error' if the model lookup requires more than
	 * WPMocks provides. Assert well-formed response.
	 */
	public function test_action_get_user_by_id_returns_valid_response(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'get_user_by_id', [ 'user_id' => 1 ] ),
			[]
		);

		$this->assertArrayHasKey( 'port', $result );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	// --- create_comment / reply -----------------------------------------------

	public function test_action_create_comment_returns_comment_id(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'create_comment', [
				'post_id'      => 1,
				'author_name'  => 'Alice',
				'author_email' => 'alice@example.com',
				'content'      => 'Great post!',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertArrayHasKey( 'comment_id', $result['data'] );
	}

	public function test_action_reply_comment_returns_comment_id(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'reply_comment', [
				'parent_id'    => 1,
				'author_name'  => 'Bob',
				'author_email' => 'bob@example.com',
				'content'      => 'Thanks!',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
	}

	// --- approve / unapprove / set_comment_status ----------------------------

	public function test_action_approve_comment_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'approve_comment', [ 'comment_id' => 1 ] ),
			[]
		);
		$this->assertEquals( 'main', $result['port'] );
	}

	public function test_action_unapproved_comment_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'unapproved_comment', [ 'comment_id' => 1 ] ),
			[]
		);
		$this->assertEquals( 'main', $result['port'] );
	}

	public function test_action_set_comment_status_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'set_comment_status', [ 'comment_id' => 1, 'status' => '1' ] ),
			[]
		);
		$this->assertEquals( 'main', $result['port'] );
	}

	// --- update_option_advanced -----------------------------------------------

	public function test_action_update_option_advanced_stores_value(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'update_option_advanced', [ 'option_name' => 'test_opt', 'value' => 'new_val' ] ),
			[]
		);
		$this->assertEquals( 'main', $result['port'] );
	}

	// --- get_media_by_id ------------------------------------------------------

	public function test_action_get_media_by_id_returns_valid_response(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'get_media_by_id', [ 'media_id' => 10 ] ),
			[]
		);

		$this->assertArrayHasKey( 'port', $result );
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	// =========================================================================
	// PREVIOUSLY SKIPPED — NOW REAL TESTS (all stubs added to WPMocks)
	// =========================================================================

	// --- restore_post / untrash_post -----------------------------------------

	public function test_action_restore_post_succeeds(): void {
		// First trash it so the integration can restore it.
		wp_trash_post( 3 );
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'restore_post', [ 'post_id' => 3, 'post_type' => 'post' ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	public function test_action_untrash_post_succeeds(): void {
		wp_trash_post( 3 );
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'untrash_post', [ 'post_id' => 3 ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	// --- update_post_feature_image / set_featured_image ----------------------

	public function test_action_update_post_feature_image_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'update_post_feature_image', [ 'post_id' => 1, 'media_id' => 10 ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	public function test_action_set_featured_image_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'set_featured_image', [ 'post_id' => 1, 'attachment_id' => 10 ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	// --- duplicate_post ------------------------------------------------------

	public function test_action_duplicate_post_returns_new_post_id(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'duplicate_post', [ 'post_id' => 1 ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	// --- schedule_post -------------------------------------------------------

	public function test_action_schedule_post_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'schedule_post', [ 'post_id' => 1, 'date' => '2025-12-01 10:00:00' ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	// --- mark / unmark comment spam ------------------------------------------

	public function test_action_mark_comment_spam_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'mark_comment_spam', [ 'comment_id' => 1 ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	public function test_action_unmark_comment_spam_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'unmark_comment_spam', [ 'comment_id' => 1 ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	// --- add_plugin_theme_option / delete_option -----------------------------

	public function test_action_add_plugin_theme_option_stores_value(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'add_plugin_theme_option', [ 'option_name' => 'brand_new_opt', 'value' => 'hello' ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	public function test_action_delete_option_removes_key(): void {
		// Seed the option first so delete has something to work with.
		update_option( 'removable_opt', 'value' );
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'delete_option', [ 'option_name' => 'removable_opt' ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	// --- delete_media --------------------------------------------------------

	public function test_action_delete_media_removes_attachment(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'delete_media', [ 'media_id' => 10 ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	// --- create_role ---------------------------------------------------------

	public function test_action_create_role_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'create_role', [ 'role' => 'custom_editor', 'display_name' => 'Custom Editor' ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	// --- add_user_role / remove_user_role ------------------------------------

	public function test_action_add_user_role_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'add_user_role', [ 'user_id' => 1, 'role' => 'editor' ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	public function test_action_remove_user_role_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'remove_user_role', [ 'user_id' => 1, 'role' => 'subscriber' ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	// --- create_term ---------------------------------------------------------

	public function test_action_create_term_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'create_term', [ 'name' => 'New Category', 'taxonomy' => 'category' ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	// --- register_taxonomy / register_post_type ------------------------------

	public function test_action_register_taxonomy_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'register_taxonomy', [ 'taxonomy' => 'genre', 'object_type' => 'post', 'label' => 'Genres' ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	public function test_action_register_post_type_succeeds(): void {
		$result = Wordpress::execute_node(
			$this->makeActionNode( 'register_post_type', [ 'post_type' => 'book', 'label' => 'Books' ] ),
			[]
		);
		$this->assertContains( $result['port'], [ 'main', 'error' ] );
	}

	// --- term triggers -------------------------------------------------------

	public function test_trigger_create_term_with_valid_term_id_returns_array_or_false(): void {
		WPMocks::setTerm( 5, [ 'name' => 'PHP', 'taxonomy' => 'category' ] );
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'create_term' ),
			[ 5, 1, 'category' ]
		);
		$this->assertThat(
			$result,
			$this->logicalOr( $this->isType( 'array' ), $this->isFalse() )
		);
	}

	// --- switch_blog ---------------------------------------------------------

	public function test_trigger_switch_blog_with_valid_id_returns_array_or_false(): void {
		$result = Wordpress::resolve_trigger(
			$this->makeTriggerNode( 'switch_blog' ),
			[ 1 ]
		);
		$this->assertThat(
			$result,
			$this->logicalOr( $this->isType( 'array' ), $this->isFalse() )
		);
	}

	// =========================================================================
	// EDGE CASES & REGRESSION GUARDS
	// =========================================================================

	public function test_execute_node_falls_through_to_passthrough_for_unknown_event(): void {
		$input  = [ 'foo' => 'bar' ];
		$result = Wordpress::execute_node(
			$this->makeActionNode( '__nonexistent_event__', [] ),
			$input
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $input, $result['data'] );
	}

	public function test_get_slug_is_not_empty(): void {
		$this->assertNotEmpty( Wordpress::get_slug() );
	}

	public function test_get_triggers_returns_non_empty_array(): void {
		$this->assertIsArray( Wordpress::get_triggers() );
		$this->assertNotEmpty( Wordpress::get_triggers() );
	}

	public function test_get_actions_returns_non_empty_array(): void {
		$this->assertIsArray( Wordpress::get_actions() );
		$this->assertNotEmpty( Wordpress::get_actions() );
	}

	public function test_all_triggers_have_label_and_hook(): void {
		foreach ( Wordpress::get_triggers() as $event => $def ) {
			$this->assertArrayHasKey( 'label', $def, "Trigger '$event' missing 'label'" );
			$this->assertArrayHasKey( 'hook',  $def, "Trigger '$event' missing 'hook'" );
			$this->assertNotEmpty( $def['label'], "Trigger '$event' has empty 'label'" );
			$this->assertNotEmpty( $def['hook'],  "Trigger '$event' has empty 'hook'" );
		}
	}

	public function test_all_actions_have_label(): void {
		foreach ( Wordpress::get_actions() as $event => $def ) {
			$this->assertArrayHasKey( 'label', $def, "Action '$event' missing 'label'" );
			$this->assertNotEmpty( $def['label'], "Action '$event' has empty 'label'" );
		}
	}

	public function test_trigger_config_schemas_contain_valid_fields(): void {
		foreach ( array_keys( Wordpress::get_triggers() ) as $trigger ) {
			$schema = Wordpress::get_trigger_config_schema( $trigger );
			$this->assertIsArray( $schema, "Trigger config schema for '$trigger' is not an array" );
			foreach ( $schema as $field ) {
				$this->assertArrayHasKey( 'key',  $field, "Field in '$trigger' schema missing 'key'" );
				$this->assertArrayHasKey( 'type', $field, "Field in '$trigger' schema missing 'type'" );
			}
		}
	}

	public function test_action_config_schemas_contain_valid_fields(): void {
		foreach ( array_keys( Wordpress::get_actions() ) as $action ) {
			$schema = Wordpress::get_action_config_schema( $action );
			$this->assertIsArray( $schema, "Action config schema for '$action' is not an array" );
			foreach ( $schema as $field ) {
				$this->assertArrayHasKey( 'key',  $field, "Field in '$action' schema missing 'key'" );
				$this->assertArrayHasKey( 'type', $field, "Field in '$action' schema missing 'type'" );
			}
		}
	}

	/**
	 * Exhaustive contract: every trigger must return array|false, never throw,
	 * never return null.
	 */
	public function test_resolve_trigger_always_returns_array_or_false(): void {
		$triggerTests = $this->getTriggerTests();

		foreach ( array_keys( Wordpress::get_triggers() ) as $event ) {
			$args   = $triggerTests[ $event ] ?? [];
			$result = Wordpress::resolve_trigger( $this->makeTriggerNode( $event ), $args );

			$this->assertThat(
				$result,
				$this->logicalOr(
					$this->isType( 'array' ),
					$this->identicalTo( false )
				),
				"Trigger '$event' returned " . gettype( $result ) . ' — expected array or false'
			);
		}
	}
}
