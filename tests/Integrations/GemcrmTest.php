<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Gemcrm;

require_once __DIR__ . '/Support/GemcrmTestStubs.php';

/**
 * Test suite for the GemCRM integration.
 *
 * ── Structure ────────────────────────────────────────────────────────────────
 *
 * 1. CONTRACT (inherited from IntegrationTestCase)
 *    Bulk checks: slug, labels, hook names, schema formats, output ports.
 *
 * 2. TRIGGER TESTS — happy + sad paths for every trigger.
 *    contact_created          — full contact payload from hook args
 *    contact_tag_attached     — tag IDs attached; optional tag filter
 *    contact_tag_removed      — tag IDs removed; optional tag filter
 *    contact_list_attached    — list IDs attached; optional list filter
 *    contact_list_removed     — list IDs removed; optional list filter
 *
 * 3. ACTION TESTS — happy + sad paths for every action.
 *    create_contact           — valid email creates; missing email errors
 *    update_contact           — valid contact_id updates; missing ID errors
 *    delete_contact           — valid contact_id deletes; missing ID errors
 *    apply_tag                — attaches tag; missing IDs error
 *    apply_list               — attaches list; missing IDs error
 *    remove_from_tag          — detaches tag; missing IDs error
 *    remove_from_list         — detaches list; missing IDs error
 *
 * 4. SCHEMA & REGISTRATION TESTS — spot-checks on schemas and dynamic queries.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class GemcrmTest extends IntegrationTestCase {

	// -------------------------------------------------------------------------
	// Contract
	// -------------------------------------------------------------------------

	protected function getIntegrationClass(): string {
		return Gemcrm::class;
	}

	// -------------------------------------------------------------------------
	// Bulk runner data
	// -------------------------------------------------------------------------

	protected function getTriggerTests(): array {
		return [
			'contact_created'       => [ 1, $this->sampleContactData() ],
			'contact_tag_attached'  => [ 5, [ 1, 2 ] ],
			'contact_tag_removed'   => [ 5, [ 1, 2 ] ],
			'contact_list_attached' => [ 5, [ 10, 20 ] ],
			'contact_list_removed'  => [ 5, [ 10, 20 ] ],
		];
	}

	protected function getActionTests(): array {
		return [
			'create_contact'   => [ 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com' ],
			'update_contact'   => [ 'contact_id' => 42, 'first_name' => 'Jane' ],
			'delete_contact'   => [ 'contact_id' => 42 ],
			'apply_tag'        => [ 'contact_id' => 42, 'tag_id' => 1 ],
			'apply_list'       => [ 'contact_id' => 42, 'list_id' => 10 ],
			'remove_from_tag'  => [ 'contact_id' => 42, 'tag_id' => 1 ],
			'remove_from_list' => [ 'contact_id' => 42, 'list_id' => 10 ],
		];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function sampleContactData( array $overrides = [] ): array {
		return array_merge( [
			'id'              => 1,
			'user_id'         => null,
			'first_name'      => 'John',
			'last_name'       => 'Doe',
			'email'           => 'john@example.com',
			'phone'           => '+1234567890',
			'status'          => 'active',
			'type'            => 'customer',
			'clicks'          => 0,
			'total_mail_sent' => 0,
			'email_open_rate' => 0,
			'meta'            => [],
			'lists'           => [],
			'tags'            => [],
			'companies'       => [],
			'creator'         => [],
			'created_at'      => '2024-01-01 00:00:00',
			'updated_at'      => '2024-01-01 00:00:00',
		], $overrides );
	}

	// =========================================================================
	// TRIGGER: contact_created
	// =========================================================================

	public function test_trigger_contact_created_returns_full_payload(): void {
		$data   = $this->sampleContactData();
		$result = Gemcrm::resolve_trigger(
			$this->makeTriggerNode( 'contact_created' ),
			[ 1, $data ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 1,                  $result['contact_id'] );
		$this->assertEquals( 'john@example.com', $result['email'] );
		$this->assertEquals( 'John',             $result['first_name'] );
		$this->assertEquals( 'Doe',              $result['last_name'] );
		$this->assertEquals( 'active',           $result['status'] );
		$this->assertEquals( 'customer',         $result['type'] );
	}

	public function test_trigger_contact_created_returns_false_without_contact_id(): void {
		$result = Gemcrm::resolve_trigger(
			$this->makeTriggerNode( 'contact_created' ),
			[ null, $this->sampleContactData() ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_contact_created_returns_false_without_data(): void {
		$result = Gemcrm::resolve_trigger(
			$this->makeTriggerNode( 'contact_created' ),
			[ 1, [] ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_contact_created_casts_contact_id_to_int(): void {
		$result = Gemcrm::resolve_trigger(
			$this->makeTriggerNode( 'contact_created' ),
			[ '7', $this->sampleContactData() ]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 7, $result['contact_id'] );
	}

	// =========================================================================
	// TRIGGER: contact_tag_attached
	// =========================================================================

	public function test_trigger_contact_tag_attached_returns_contact_and_tag_ids(): void {
		$result = Gemcrm::resolve_trigger(
			$this->makeTriggerNode( 'contact_tag_attached' ),
			[ 5, [ 1, 2, 3 ] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 5,           $result['contact_id'] );
		$this->assertEquals( [ 1, 2, 3 ], $result['tag_ids'] );
	}

	public function test_trigger_contact_tag_attached_returns_false_without_contact_id(): void {
		$result = Gemcrm::resolve_trigger(
			$this->makeTriggerNode( 'contact_tag_attached' ),
			[ null, [ 1 ] ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_contact_tag_attached_returns_false_without_tag_ids(): void {
		$result = Gemcrm::resolve_trigger(
			$this->makeTriggerNode( 'contact_tag_attached' ),
			[ 5, [] ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_contact_tag_attached_filters_by_configured_tag(): void {
		// Node is configured for tag 99 but the event fires for tags [1, 2].
		$node   = $this->makeTriggerNode( 'contact_tag_attached', [ 'tag_id' => '99' ] );
		$result = Gemcrm::resolve_trigger( $node, [ 5, [ 1, 2 ] ] );
		$this->assertFalse( $result );
	}

	public function test_trigger_contact_tag_attached_passes_when_configured_tag_matches(): void {
		$node   = $this->makeTriggerNode( 'contact_tag_attached', [ 'tag_id' => '2' ] );
		$result = Gemcrm::resolve_trigger( $node, [ 5, [ 1, 2 ] ] );

		$this->assertIsArray( $result );
		$this->assertContains( 2, $result['tag_ids'] );
	}

	public function test_trigger_contact_tag_attached_passes_without_tag_filter(): void {
		// No config set — all tags should pass through.
		$result = Gemcrm::resolve_trigger(
			$this->makeTriggerNode( 'contact_tag_attached' ),
			[ 5, [ 1, 2 ] ]
		);
		$this->assertIsArray( $result );
	}

	// =========================================================================
	// TRIGGER: contact_tag_removed
	// =========================================================================

	public function test_trigger_contact_tag_removed_returns_contact_and_tag_ids(): void {
		$result = Gemcrm::resolve_trigger(
			$this->makeTriggerNode( 'contact_tag_removed' ),
			[ 5, [ 3 ] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 5,   $result['contact_id'] );
		$this->assertEquals( [ 3 ], $result['tag_ids'] );
	}

	public function test_trigger_contact_tag_removed_filters_by_configured_tag(): void {
		$node   = $this->makeTriggerNode( 'contact_tag_removed', [ 'tag_id' => '99' ] );
		$result = Gemcrm::resolve_trigger( $node, [ 5, [ 3 ] ] );
		$this->assertFalse( $result );
	}

	public function test_trigger_contact_tag_removed_returns_false_without_tag_ids(): void {
		$result = Gemcrm::resolve_trigger(
			$this->makeTriggerNode( 'contact_tag_removed' ),
			[ 5, [] ]
		);
		$this->assertFalse( $result );
	}

	// =========================================================================
	// TRIGGER: contact_list_attached
	// =========================================================================

	public function test_trigger_contact_list_attached_returns_contact_and_list_ids(): void {
		$result = Gemcrm::resolve_trigger(
			$this->makeTriggerNode( 'contact_list_attached' ),
			[ 5, [ 10, 20 ] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 5,        $result['contact_id'] );
		$this->assertEquals( [ 10, 20 ], $result['list_ids'] );
	}

	public function test_trigger_contact_list_attached_returns_false_without_contact_id(): void {
		$result = Gemcrm::resolve_trigger(
			$this->makeTriggerNode( 'contact_list_attached' ),
			[ null, [ 10 ] ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_contact_list_attached_filters_by_configured_list(): void {
		$node   = $this->makeTriggerNode( 'contact_list_attached', [ 'list_id' => '99' ] );
		$result = Gemcrm::resolve_trigger( $node, [ 5, [ 10, 20 ] ] );
		$this->assertFalse( $result );
	}

	public function test_trigger_contact_list_attached_passes_when_configured_list_matches(): void {
		$node   = $this->makeTriggerNode( 'contact_list_attached', [ 'list_id' => '10' ] );
		$result = Gemcrm::resolve_trigger( $node, [ 5, [ 10, 20 ] ] );

		$this->assertIsArray( $result );
		$this->assertContains( 10, $result['list_ids'] );
	}

	// =========================================================================
	// TRIGGER: contact_list_removed
	// =========================================================================

	public function test_trigger_contact_list_removed_returns_contact_and_list_ids(): void {
		$result = Gemcrm::resolve_trigger(
			$this->makeTriggerNode( 'contact_list_removed' ),
			[ 5, [ 10 ] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 5,    $result['contact_id'] );
		$this->assertEquals( [ 10 ], $result['list_ids'] );
	}

	public function test_trigger_contact_list_removed_filters_by_configured_list(): void {
		$node   = $this->makeTriggerNode( 'contact_list_removed', [ 'list_id' => '99' ] );
		$result = Gemcrm::resolve_trigger( $node, [ 5, [ 10 ] ] );
		$this->assertFalse( $result );
	}

	public function test_trigger_contact_list_removed_returns_false_without_list_ids(): void {
		$result = Gemcrm::resolve_trigger(
			$this->makeTriggerNode( 'contact_list_removed' ),
			[ 5, [] ]
		);
		$this->assertFalse( $result );
	}

	// =========================================================================
	// ACTION: create_contact
	// =========================================================================

	public function test_action_create_contact_returns_contact_on_success(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'create_contact', [
				'first_name' => 'John',
				'last_name'  => 'Doe',
				'email'      => 'john@example.com',
				'status'     => 'active',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertArrayHasKey( 'contact', $result['data'] );
		$this->assertArrayNotHasKey( 'error', $result['data'] );
		$this->assertEquals( 42, $result['data']['contact']['id'] );
	}

	public function test_action_create_contact_errors_without_email(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'create_contact', [ 'first_name' => 'John' ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertArrayHasKey( 'error', $result['data'] );
	}

	public function test_action_create_contact_errors_with_invalid_email(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'create_contact', [ 'email' => 'not-an-email' ] ),
			[]
		);

		$this->assertArrayHasKey( 'error', $result['data'] );
	}

	public function test_action_create_contact_builds_address_meta(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'create_contact', [
				'email'      => 'john@example.com',
				'addr_line_1' => '123 Main St',
				'city'       => 'New York',
				'country'    => 'USA',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$contact = $result['data']['contact'];
		$this->assertArrayHasKey( 'meta', $contact );
		$this->assertTrue( $contact['meta']['add_addr_info'] );
		$this->assertEquals( '123 Main St', $contact['meta']['addr_line_1'] );
	}

	public function test_action_create_contact_parses_comma_separated_tag_ids(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'create_contact', [
				'email'   => 'john@example.com',
				'tag_ids' => '1, 2, 3',
			] ),
			[]
		);

		$contact = $result['data']['contact'];
		$this->assertEquals( [ 1, 2, 3 ], $contact['tag_ids'] );
	}

	public function test_action_create_contact_merges_input_data(): void {
		$input  = [ 'workflow_run_id' => 99 ];
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'create_contact', [ 'email' => 'john@example.com' ] ),
			$input
		);

		$this->assertEquals( 99, $result['data']['workflow_run_id'] );
	}

	// =========================================================================
	// ACTION: update_contact
	// =========================================================================

	public function test_action_update_contact_returns_updated_contact(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'update_contact', [
				'contact_id' => 42,
				'first_name' => 'Jane',
				'email'      => 'jane@example.com',
			] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertArrayHasKey( 'contact', $result['data'] );
		$this->assertEquals( 42, $result['data']['contact']['id'] );
	}

	public function test_action_update_contact_errors_without_contact_id(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'update_contact', [ 'first_name' => 'Jane' ] ),
			[]
		);

		$this->assertArrayHasKey( 'error', $result['data'] );
	}

	public function test_action_update_contact_errors_with_zero_contact_id(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'update_contact', [ 'contact_id' => 0 ] ),
			[]
		);

		$this->assertArrayHasKey( 'error', $result['data'] );
	}

	// =========================================================================
	// ACTION: delete_contact
	// =========================================================================

	public function test_action_delete_contact_returns_deleted_id(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'delete_contact', [ 'contact_id' => 42 ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 42, $result['data']['deleted_contact_id'] );
		$this->assertArrayNotHasKey( 'error', $result['data'] );
	}

	public function test_action_delete_contact_errors_without_contact_id(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'delete_contact', [] ),
			[]
		);

		$this->assertArrayHasKey( 'error', $result['data'] );
	}

	// =========================================================================
	// ACTION: apply_tag
	// =========================================================================

	public function test_action_apply_tag_returns_contact_and_tag_ids(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'apply_tag', [ 'contact_id' => 42, 'tag_id' => 1 ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 42, $result['data']['contact_id'] );
		$this->assertEquals( 1,  $result['data']['tag_id'] );
		$this->assertArrayNotHasKey( 'error', $result['data'] );
	}

	public function test_action_apply_tag_errors_without_contact_id(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'apply_tag', [ 'tag_id' => 1 ] ),
			[]
		);
		$this->assertArrayHasKey( 'error', $result['data'] );
	}

	public function test_action_apply_tag_errors_without_tag_id(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'apply_tag', [ 'contact_id' => 42 ] ),
			[]
		);
		$this->assertArrayHasKey( 'error', $result['data'] );
	}

	// =========================================================================
	// ACTION: apply_list
	// =========================================================================

	public function test_action_apply_list_returns_contact_and_list_ids(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'apply_list', [ 'contact_id' => 42, 'list_id' => 10 ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 42, $result['data']['contact_id'] );
		$this->assertEquals( 10, $result['data']['list_id'] );
		$this->assertArrayNotHasKey( 'error', $result['data'] );
	}

	public function test_action_apply_list_errors_without_contact_id(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'apply_list', [ 'list_id' => 10 ] ),
			[]
		);
		$this->assertArrayHasKey( 'error', $result['data'] );
	}

	public function test_action_apply_list_errors_without_list_id(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'apply_list', [ 'contact_id' => 42 ] ),
			[]
		);
		$this->assertArrayHasKey( 'error', $result['data'] );
	}

	// =========================================================================
	// ACTION: remove_from_tag
	// =========================================================================

	public function test_action_remove_from_tag_returns_contact_and_tag_ids(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'remove_from_tag', [ 'contact_id' => 42, 'tag_id' => 1 ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 42, $result['data']['contact_id'] );
		$this->assertEquals( 1,  $result['data']['tag_id'] );
		$this->assertArrayNotHasKey( 'error', $result['data'] );
	}

	public function test_action_remove_from_tag_errors_without_contact_id(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'remove_from_tag', [ 'tag_id' => 1 ] ),
			[]
		);
		$this->assertArrayHasKey( 'error', $result['data'] );
	}

	public function test_action_remove_from_tag_errors_without_tag_id(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'remove_from_tag', [ 'contact_id' => 42 ] ),
			[]
		);
		$this->assertArrayHasKey( 'error', $result['data'] );
	}

	// =========================================================================
	// ACTION: remove_from_list
	// =========================================================================

	public function test_action_remove_from_list_returns_contact_and_list_ids(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'remove_from_list', [ 'contact_id' => 42, 'list_id' => 10 ] ),
			[]
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( 42, $result['data']['contact_id'] );
		$this->assertEquals( 10, $result['data']['list_id'] );
		$this->assertArrayNotHasKey( 'error', $result['data'] );
	}

	public function test_action_remove_from_list_errors_without_contact_id(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'remove_from_list', [ 'list_id' => 10 ] ),
			[]
		);
		$this->assertArrayHasKey( 'error', $result['data'] );
	}

	public function test_action_remove_from_list_errors_without_list_id(): void {
		$result = Gemcrm::execute_node(
			$this->makeActionNode( 'remove_from_list', [ 'contact_id' => 42 ] ),
			[]
		);
		$this->assertArrayHasKey( 'error', $result['data'] );
	}

	// =========================================================================
	// SCHEMA & REGISTRATION
	// =========================================================================

	public function test_get_slug_returns_gemcrm(): void {
		$this->assertEquals( 'gemcrm', Gemcrm::get_slug() );
	}

	public function test_get_name_returns_gemcrm(): void {
		$this->assertEquals( 'GemCRM', Gemcrm::get_name() );
	}

	public function test_all_triggers_are_registered(): void {
		$triggers = Gemcrm::get_triggers();
		foreach ( [
			'contact_created',
			'contact_tag_attached',
			'contact_tag_removed',
			'contact_list_attached',
			'contact_list_removed',
		] as $event ) {
			$this->assertArrayHasKey( $event, $triggers, "Trigger '$event' not registered" );
		}
	}

	public function test_all_actions_are_registered(): void {
		$actions = Gemcrm::get_actions();
		foreach ( [
			'create_contact',
			'update_contact',
			'delete_contact',
			'apply_tag',
			'apply_list',
			'remove_from_tag',
			'remove_from_list',
		] as $event ) {
			$this->assertArrayHasKey( $event, $actions, "Action '$event' not registered" );
		}
	}

	public function test_trigger_config_schema_for_tag_triggers_has_tag_id_field(): void {
		foreach ( [ 'contact_tag_attached', 'contact_tag_removed' ] as $trigger ) {
			$schema = Gemcrm::get_trigger_config_schema( $trigger );
			$this->assertCount( 1, $schema );
			$this->assertEquals( 'tag_id', $schema[0]['key'] );
			$this->assertEquals( 'select', $schema[0]['type'] );
		}
	}

	public function test_trigger_config_schema_for_list_triggers_has_list_id_field(): void {
		foreach ( [ 'contact_list_attached', 'contact_list_removed' ] as $trigger ) {
			$schema = Gemcrm::get_trigger_config_schema( $trigger );
			$this->assertCount( 1, $schema );
			$this->assertEquals( 'list_id', $schema[0]['key'] );
			$this->assertEquals( 'select',  $schema[0]['type'] );
		}
	}

	public function test_trigger_config_schema_for_contact_created_is_empty(): void {
		$schema = Gemcrm::get_trigger_config_schema( 'contact_created' );
		$this->assertSame( [], $schema );
	}

	public function test_action_schema_create_contact_has_required_email(): void {
		$schema   = Gemcrm::get_action_config_schema( 'create_contact' );
		$emailField = array_filter( $schema, fn( $f ) => $f['key'] === 'email' );
		$emailField = array_values( $emailField )[0];

		$this->assertTrue( $emailField['required'] );
	}

	public function test_action_schema_update_contact_starts_with_contact_id(): void {
		$schema = Gemcrm::get_action_config_schema( 'update_contact' );
		$this->assertEquals( 'contact_id', $schema[0]['key'] );
		$this->assertTrue( $schema[0]['required'] );
	}

	public function test_action_schema_delete_contact_has_only_contact_id(): void {
		$schema = Gemcrm::get_action_config_schema( 'delete_contact' );
		$this->assertCount( 1, $schema );
		$this->assertEquals( 'contact_id', $schema[0]['key'] );
	}

	public function test_action_schema_apply_tag_has_contact_id_and_tag_id(): void {
		$schema = Gemcrm::get_action_config_schema( 'apply_tag' );
		$keys   = array_column( $schema, 'key' );
		$this->assertContains( 'contact_id', $keys );
		$this->assertContains( 'tag_id',     $keys );
	}

	public function test_action_schema_apply_list_has_contact_id_and_list_id(): void {
		$schema = Gemcrm::get_action_config_schema( 'apply_list' );
		$keys   = array_column( $schema, 'key' );
		$this->assertContains( 'contact_id', $keys );
		$this->assertContains( 'list_id',    $keys );
	}

	public function test_dynamic_queries_registered(): void {
		$queries = Gemcrm::get_dynamic_queries();
		$this->assertArrayHasKey( 'gemcrm_tag_query',  $queries );
		$this->assertArrayHasKey( 'gemcrm_list_query', $queries );
		$this->assertIsCallable( $queries['gemcrm_tag_query'] );
		$this->assertIsCallable( $queries['gemcrm_list_query'] );
	}

	public function test_query_tags_returns_all_tags_from_stub(): void {
		$tags = Gemcrm::query_tags();
		$this->assertIsArray( $tags );
		$this->assertCount( 2, $tags );
		$this->assertEquals( 1,     $tags[0]['value'] );
		$this->assertEquals( 'VIP', $tags[0]['label'] );
	}

	public function test_query_lists_returns_all_lists_from_stub(): void {
		$lists = Gemcrm::query_lists();
		$this->assertIsArray( $lists );
		$this->assertCount( 2, $lists );
		$this->assertEquals( 10,            $lists[0]['value'] );
		$this->assertEquals( 'Subscribers', $lists[0]['label'] );
	}

	public function test_execute_node_unknown_action_is_passthrough(): void {
		$input  = [ 'foo' => 'bar' ];
		$result = Gemcrm::execute_node(
			$this->makeActionNode( '__unknown__', [] ),
			$input
		);

		$this->assertEquals( 'main',  $result['port'] );
		$this->assertEquals( $input,  $result['data'] );
	}
}
