<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Tests\TestCase;
use Zaplane\Tests\WPMocks;

/**
 * Base test case for integration testing.
 *
 * Tests:
 * - Triggers/actions are registered with labels
 * - Config schemas are valid
 * - Triggers fire and return payload
 * - Actions execute and return proper format
 */
abstract class IntegrationTestCase extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		WPMocks::reset();
		$this->setupMockData();
	}

	protected function tearDown(): void {
		WPMocks::reset();
		parent::tearDown();
	}

	/**
	 * Setup mock data for tests - override in subclass if needed
	 */
	protected function setupMockData(): void {
		global $wpdb;

		// Default mock data for WP functions
		WPMocks::setPost( 1, [
			'post_title' => 'Test Post',
			'post_type' => 'post'
		] );
		WPMocks::setPost( 2, [
			'post_title' => 'Test Page',
			'post_type' => 'page'
		] );
		WPMocks::setPost( 3, [
			'post_title' => 'Test Attachment',
			'post_type' => 'attachment',
			'post_mime_type' => 'image/jpeg'
		] );
		WPMocks::setUser( 1, [
			'user_login' => 'admin',
			'user_email' => 'admin@example.com'
		] );
		WPMocks::setUser( 2, [
			'user_login' => 'testuser',
			'user_email' => 'test@example.com'
		] );
		WPMocks::setComment( 1, [
			'comment_content' => 'Test comment',
			'comment_post_ID' => 1
		] );

		// Default mock data for ORM (wpdb->get_results returns array)
		$wpdb->tables['results'] = [
			[
				'ID' => 1,
				'post_author' => 1,
				'post_date' => '2024-01-01 00:00:00',
				'post_date_gmt' => '2024-01-01 00:00:00',
				'post_content' => 'Test content',
				'post_title' => 'Test Post',
				'post_excerpt' => '',
				'post_status' => 'publish',
				'comment_status' => 'open',
				'ping_status' => 'open',
				'post_password' => '',
				'post_name' => 'test-post',
				'to_ping' => '',
				'pinged' => '',
				'post_modified' => '2024-01-01 00:00:00',
				'post_modified_gmt' => '2024-01-01 00:00:00',
				'post_content_filtered' => '',
				'post_parent' => 0,
				'guid' => 'http://example.com/?p=1',
				'menu_order' => 0,
				'post_type' => 'post',
				'post_mime_type' => '',
				'comment_count' => 0,
			],
		];
	}

	/**
	 * Get the integration class to test
	 */
	abstract protected function getIntegrationClass(): string;

	/**
	 * Define triggers to test with their mock args
	 * Format: ['event' => [args], ...] or ['event', ...] for default args
	 */
	protected function getTriggerTests(): array {
		return [];
	}

	/**
	 * Define actions to test with their mock config
	 * Format: ['event' => [config], ...] or ['event', ...] for default config
	 */
	protected function getActionTests(): array {
		return [];
	}

	/**
	 * Get default trigger args for common events
	 */
	protected function getDefaultTriggerArgs( string $event ): array {
		$defaults = [
			// Post triggers
			'publish_post' => [ 1 ],
			'post_updated' => [ 1, null, null ],
			'save_post' => [ 1 ],
			'wp_trash_post' => [ 1 ],
			'delete_post' => [ 1 ],
			'untrashed_post' => [ 1 ],
			'deleted_post' => [ 1 ],
			'transition_post_status' => [ 'publish', 'draft', 1 ],
			'wp_insert_post' => [ 1 ],
			'wp_after_insert_post' => [ 1, false ],

			// User triggers
			'user_register' => [ 1 ],
			'profile_update' => [ 1 ],
			'set_user_role' => [ 1, 'subscriber', [ 'administrator' ] ],
			'delete_user' => [ 1 ],
			'wp_login' => [
				'admin',
				(object) [
					'ID' => 1,
					'user_login' => 'admin',
					'user_email' => 'admin@example.com',
					'roles' => [ 'administrator' ]
				]
			],

			// Comment triggers
			'comment_post' => [ 1, 1 ],
			'edit_comment' => [ 1 ],
			'delete_comment' => [ 1 ],
			'wp_insert_comment' => [ 1, (object) [ 'comment_ID' => 1 ] ],

			// Attachment triggers
			'add_attachment' => [ 3 ],
			'edit_attachment' => [ 3 ],
			'attachment_updated' => [ 3 ],
		];

		return $defaults[ $event ] ?? [ 1 ];
	}

	/**
	 * Get default action config for common events
	 */
	protected function getDefaultActionConfig( string $event ): array {
		$defaults = [
			// Post actions
			'create_post' => [
				'post_title' => 'Test',
				'post_type' => 'post',
				'post_status' => 'draft'
			],
			'update_post' => [
				'post_id' => 1,
				'post_title' => 'Updated',
				'post_content' => 'Content',
				'post_type' => 'post'
			],
			'update_title' => [
				'post_id' => 1,
				'post_title' => 'New Title',
				'post_type' => 'post'
			],
			'trash_post' => [
				'post_id' => 1,
				'post_type' => 'post'
			],
			'delete_post' => [
				'post_id' => 1,
				'post_type' => 'post'
			],
			'get_post_single' => [ 'post_id' => 1 ],

			// User actions
			'create_user' => [
				'user_login' => 'newuser',
				'user_email' => 'new@example.com',
				'user_pass' => 'pass123'
			],
			'update_user' => [
				'user_id' => 1,
				'display_name' => 'Updated Name'
			],
			'delete_user' => [ 'user_id' => 2 ],
			'get_user_by_id' => [ 'user_id' => 1 ],

			// Comment actions
			'create_comment' => [
				'post_id' => 1,
				'author_name' => 'Test',
				'author_email' => 'test@example.com',
				'content' => 'Comment'
			],
			'delete_comment' => [ 'comment_id' => 1 ],

			// Condition
			'if' => [ 'expression' => '1 == 1' ],
		];

		return $defaults[ $event ] ?? [];
	}

	// ========== EXECUTION TESTS ==========

	/**
	 * @test
	 */
	public function triggers_fire_and_return_payload(): void {
		$class = $this->getIntegrationClass();
		$tests = $this->getTriggerTests();

		if ( empty( $tests ) ) {
			$this->assertTrue( true );
			return;
		}

		foreach ( $tests as $key => $value ) {
			// Support both ['event'] and ['event' => [args]]
			if ( is_int( $key ) ) {
				$event = $value;
				$args = $this->getDefaultTriggerArgs( $event );
			} else {
				$event = $key;
				$args = $value;
			}

			$node = $this->makeTriggerNode( $event );
			$result = $class::resolve_trigger( $node, $args );

			// Result should be array (payload) or false (filtered out)
			$this->assertTrue(
				is_array( $result ) || $result === false,
				"Trigger '{$event}' should return array or false, got: " . gettype( $result )
			);

			if ( is_array( $result ) ) {
				$this->assertNotEmpty( $result, "Trigger '{$event}' payload should not be empty" );
			}
		}//end foreach
	}

	/**
	 * @test
	 */
	public function actions_execute_and_return_valid_format(): void {
		$class = $this->getIntegrationClass();
		$tests = $this->getActionTests();

		if ( empty( $tests ) ) {
			$this->assertTrue( true );
			return;
		}

		foreach ( $tests as $key => $value ) {
			// Support both ['event'] and ['event' => [config]]
			if ( is_int( $key ) ) {
				$event = $value;
				$config = $this->getDefaultActionConfig( $event );
			} else {
				$event = $key;
				$config = $value;
			}

			$node = $this->makeActionNode( $event, $config );

			try {
				$result = $class::execute_node( $node, [] );

				$this->assertIsArray( $result, "Action '{$event}' should return array" );
				$this->assertArrayHasKey( 'port', $result, "Action '{$event}' missing 'port'" );
				$this->assertArrayHasKey( 'data', $result, "Action '{$event}' missing 'data'" );
				$this->assertIsString( $result['port'], "Action '{$event}' port should be string" );
				$this->assertIsArray( $result['data'], "Action '{$event}' data should be array" );

				// Check port is valid
				$validPorts = $class::get_output_ports();
				$this->assertContains(
					$result['port'],
					$validPorts,
					"Action '{$event}' returned invalid port '{$result['port']}'"
				);
			} catch ( \Exception $e ) {
				$this->fail( "Action '{$event}' threw exception: " . $e->getMessage() );
			}
		}//end foreach
	}

	// ========== REGISTRATION TESTS ==========

	/**
	 * @test
	 */
	public function integration_has_slug(): void {
		$class = $this->getIntegrationClass();
		$slug = $class::get_slug();

		$this->assertNotEmpty( $slug );
		$this->assertIsString( $slug );
	}

	/**
	 * @test
	 */
	public function all_tested_triggers_are_registered(): void {
		$class = $this->getIntegrationClass();
		$registered = $class::get_triggers();
		$tests = $this->getTriggerTests();

		if ( empty( $tests ) ) {
			$this->assertTrue( true );
			return;
		}

		foreach ( $tests as $key => $value ) {
			$event = is_int( $key ) ? $value : $key;
			$this->assertArrayHasKey( $event, $registered, "Trigger '{$event}' not registered" );
		}
	}

	/**
	 * @test
	 */
	public function all_tested_actions_are_registered(): void {
		$class = $this->getIntegrationClass();
		$registered = $class::get_actions();
		$tests = $this->getActionTests();

		if ( empty( $tests ) ) {
			$this->assertTrue( true );
			return;
		}

		foreach ( $tests as $key => $value ) {
			$event = is_int( $key ) ? $value : $key;
			$this->assertArrayHasKey( $event, $registered, "Action '{$event}' not registered" );
		}
	}

	/**
	 * @test
	 */
	public function all_triggers_have_labels_and_hooks(): void {
		$class = $this->getIntegrationClass();
		$triggers = $class::get_triggers();

		if ( empty( $triggers ) ) {
			$this->assertTrue( true );
			return;
		}

		foreach ( $triggers as $event => $meta ) {
			$this->assertArrayHasKey( 'label', $meta, "Trigger '{$event}' missing label" );
			$this->assertNotEmpty( $meta['label'], "Trigger '{$event}' has empty label" );
			$this->assertArrayHasKey( 'hook', $meta, "Trigger '{$event}' missing hook" );
		}
	}

	/**
	 * @test
	 */
	public function all_actions_have_labels(): void {
		$class = $this->getIntegrationClass();
		$actions = $class::get_actions();

		if ( empty( $actions ) ) {
			$this->assertTrue( true );
			return;
		}

		foreach ( $actions as $event => $meta ) {
			$this->assertArrayHasKey( 'label', $meta, "Action '{$event}' missing label" );
			$this->assertNotEmpty( $meta['label'], "Action '{$event}' has empty label" );
		}
	}

	/**
	 * @test
	 */
	public function trigger_config_schemas_are_valid(): void {
		$class = $this->getIntegrationClass();
		$triggers = $class::get_triggers();

		if ( empty( $triggers ) ) {
			$this->assertTrue( true );
			return;
		}

		foreach ( array_keys( $triggers ) as $event ) {
			$schema = $class::get_trigger_config_schema( $event );
			$this->assertIsArray( $schema, "Trigger '{$event}' schema must be array" );
			$this->validateSchemaFormat( $schema, "trigger:{$event}" );
		}
	}

	/**
	 * @test
	 */
	public function action_config_schemas_are_valid(): void {
		$class = $this->getIntegrationClass();
		$actions = $class::get_actions();

		if ( empty( $actions ) ) {
			$this->assertTrue( true );
			return;
		}

		foreach ( array_keys( $actions ) as $event ) {
			$schema = $class::get_action_config_schema( $event );
			$this->assertIsArray( $schema, "Action '{$event}' schema must be array" );
			$this->validateSchemaFormat( $schema, "action:{$event}" );
		}
	}

	/**
	 * @test
	 */
	public function output_ports_are_valid(): void {
		$class = $this->getIntegrationClass();
		$ports = $class::get_output_ports();

		$this->assertIsArray( $ports );
		$this->assertNotEmpty( $ports, 'Integration must have at least one output port' );
	}

	/**
	 * Validate schema format - supports both formats:
	 * Format 1: [['key' => 'name', 'type' => 'text'], ...]
	 * Format 2: ['name' => ['type' => 'text', 'label' => '...'], ...]
	 */
	protected function validateSchemaFormat( array $schema, string $context ): void {
		if ( empty( $schema ) ) {
			return; // Empty schema is valid
		}

		// Check first item to determine format
		$firstKey = array_key_first( $schema );
		$firstItem = $schema[ $firstKey ];

		if ( is_int( $firstKey ) ) {
			// Format 1: indexed array with 'key' field
			foreach ( $schema as $field ) {
				$this->assertArrayHasKey( 'key', $field, "{$context}: Schema field missing 'key'" );
				$this->assertArrayHasKey( 'type', $field, "{$context}: Schema field missing 'type'" );
			}
		} else {
			// Format 2: associative array where key is field name
			foreach ( $schema as $fieldName => $field ) {
				$this->assertIsString( $fieldName, "{$context}: Field name must be string" );
				$this->assertArrayHasKey( 'type', $field, "{$context}: Field '{$fieldName}' missing 'type'" );
			}
		}
	}

	// ========== HELPERS ==========

	protected function makeTriggerNode( string $event, array $config = [] ): array {
		$class = $this->getIntegrationClass();
		$triggers = $class::get_triggers();
		$hook = $triggers[ $event ]['hook'] ?? $event;

		return [
			'type' => 'trigger',
			'event' => $event,
			'data' => [
				'app' => $class::get_slug(),
				'event' => $event,
				'hook' => $hook,
				'config' => $config,
			],
		];
	}

	protected function makeActionNode( string $event, array $config = [], array $credentials = [] ): array {
		$class = $this->getIntegrationClass();

		$node = [
			'type' => 'action',
			'data' => [
				'app'    => $class::get_slug(),
				'event'  => $event,
				'config' => $config,
			],
		];

		if ( ! empty( $credentials ) ) {
			$node['_connection_credentials'] = $credentials;
		}

		return $node;
	}

	protected function mockHttp( array $body, int $status = 200 ): void {
		WPMocks::setHttpResponse( $body, $status );
	}
}
