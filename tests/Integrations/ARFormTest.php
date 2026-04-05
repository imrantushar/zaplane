<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\ARForm;

class ARFormTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return ARForm::class;
	}

	// ========== CONTRACT TESTS (inherited automatically) ==========
	// - integration_has_slug
	// - all_triggers_have_labels_and_hooks
	// - all_actions_have_labels
	// - trigger_config_schemas_are_valid
	// - action_config_schemas_are_valid
	// - output_ports_are_valid
	// - triggers_fire_and_return_payload (auto-bulk test if getTriggerTests defined)
	// - actions_execute_and_return_valid_format (auto-bulk test if getActionTests defined)

	// ========== HELPERS ==========

	/**
	 * Create a trigger node for ARForm
	 */
	private function makeARFormTriggerNode( string $event, string $formId = 'any' ): array {
		return [
			'type'    => 'trigger',
			'event'   => $event,
			'data'    => [
				'app'   => 'ar-form',
				'event' => $event,
				'config' => [
					'form_id' => $formId,
				],
			],
		];
	}

	/**
	 * Create a mock ARForm entry object
	 */
	private function makeEntry( array $overrides = [] ): object {
		$defaults = (object) [
			'id'         => 123,
			'form_id'    => 5,
			'form'       => 5,
			'name'       => 'John Doe',
			'email'      => 'john@example.com',
			'phone'      => '1234567890',
			'message'    => 'Test message',
			'date'       => '2025-04-05 10:30:00',
			'ip_address' => '192.168.1.1',
			'user_id'    => 1,
			'post_id'    => 0,
		];

		return (object) array_merge( (array) $defaults, $overrides );
	}

	/**
	 * Create a mock ARForm entry with post_id
	 */
	private function makeEntryWithPostId( int $postId = 42 ): object {
		$entry = $this->makeEntry();
		$entry->post_id = $postId;
		return $entry;
	}

	// ========== TRIGGER: form_submitted — success paths ==========

	/**
	 * Test that trigger returns payload when form_id is "any"
	 */
	public function test_trigger_returns_payload_for_any_form(): void {
		$node   = $this->makeARFormTriggerNode( 'form_submitted', 'any' );
		$entry  = $this->makeEntry( [ 'form_id' => 5 ] );
		$result = ARForm::resolve_trigger( $node, [ $entry ] );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 5, $result['form']['form_id'] );
		$this->assertArrayHasKey( 'form_data', $result['form'] );
		$this->assertGreaterThan( 0, count( $result['form']['form_data'] ) );
	}

	/**
	 * Test that trigger returns payload when specific form_id matches
	 */
	public function test_trigger_returns_payload_when_form_id_matches(): void {
		$node   = $this->makeARFormTriggerNode( 'form_submitted', '5' );
		$entry  = $this->makeEntry( [ 'form_id' => 5, 'id' => 99 ] );
		$result = ARForm::resolve_trigger( $node, [ $entry ] );

		$this->assertIsArray( $result );
		$this->assertEquals( 5, $result['form']['form_id'] );
		$this->assertEquals( 99, $result['form']['form_data']->id ?? null );
	}

	/**
	 * Test that trigger includes all entry fields in form_data
	 */
	public function test_trigger_includes_entry_fields_in_data(): void {
		$node  = $this->makeARFormTriggerNode( 'form_submitted', 'any' );
		$entry = $this->makeEntry( [
			'name'    => 'Jane Smith',
			'email'   => 'jane@test.com',
			'phone'   => '555-1234',
			'subject' => 'Inquiry',
		] );

		$result = ARForm::resolve_trigger( $node, [ $entry ] );

		$this->assertEquals( 'Jane Smith', $result['form']['form_data']->name );
		$this->assertEquals( 'jane@test.com', $result['form']['form_data']->email );
		$this->assertEquals( '555-1234', $result['form']['form_data']->phone );
		$this->assertEquals( 'Inquiry', $result['form']['form_data']->subject );
	}

	/**
	 * Test that trigger includes post_id when present in entry
	 */
	public function test_trigger_includes_post_id_when_present(): void {
		$node   = $this->makeARFormTriggerNode( 'form_submitted', 'any' );
		$entry  = $this->makeEntryWithPostId( 42 );
		$result = ARForm::resolve_trigger( $node, [ $entry ] );

		$this->assertEquals( 42, $result['form']['form_data']->post_id );
	}

	/**
	 * Test that post_id is included when it's 0 (default value)
	 * Note: Since our mock has post_id = 0 by default, it will be included
	 * This test documents current behavior
	 */
	public function test_trigger_includes_zero_post_id(): void {
		$node   = $this->makeARFormTriggerNode( 'form_submitted', 'any' );
		$entry  = $this->makeEntry(); // default post_id = 0
		$result = ARForm::resolve_trigger( $node, [ $entry ] );

		// post_id should be in result even if 0
		$this->assertObjectHasAttribute( 'post_id', $result['form']['form_data'] );
		$this->assertEquals( 0, $result['form']['form_data']->post_id );
	}

	// ========== TRIGGER: form_submitted — filter paths (returns false) ==========

	/**
	 * Test that trigger returns false when form_id does not match
	 */
	public function test_trigger_returns_false_when_form_id_does_not_match(): void {
		$node   = $this->makeARFormTriggerNode( 'form_submitted', '10' );
		$entry  = $this->makeEntry( [ 'form_id' => 5 ] ); // Form 5, but we want 10
		$result = ARForm::resolve_trigger( $node, [ $entry ] );

		$this->assertFalse( $result );
	}

	/**
	 * Test that trigger returns false when entry is null
	 */
	public function test_trigger_returns_false_with_null_entry(): void {
		$node   = $this->makeARFormTriggerNode( 'form_submitted', 'any' );
		$result = ARForm::resolve_trigger( $node, [ null ] );

		$this->assertFalse( $result );
	}

	/**
	 * Test that trigger returns false when entry object has no form_id
	 */
	public function test_trigger_returns_false_when_entry_has_no_form_id(): void {
		$node   = $this->makeARFormTriggerNode( 'form_submitted', 'any' );
		$entry  = (object) [ 'name' => 'Test', 'email' => 'test@test.com' ]; // no form_id
		$result = ARForm::resolve_trigger( $node, [ $entry ] );

		$this->assertFalse( $result );
	}

	/**
	 * Test that trigger returns false when entry has form property instead of form_id
	 * This tests the fallback: $entry->form_id ? $entry->form_id : (isset($entry->form) ? $entry->form : null)
	 */
	public function test_trigger_uses_form_property_when_form_id_missing(): void {
		$node   = $this->makeARFormTriggerNode( 'form_submitted', 'any' );
		$entry  = (object) [
			'form' => 5,
			'name' => 'Test',
			// Note: no form_id property
		];
		$result = ARForm::resolve_trigger( $node, [ $entry ] );

		$this->assertIsArray( $result );
		$this->assertEquals( 5, $result['form']['form_id'] );
	}

	/**
	 * Test that trigger returns false when both form_id and form are missing
	 */
	public function test_trigger_returns_false_when_no_form_properties(): void {
		$node   = $this->makeARFormTriggerNode( 'form_submitted', 'any' );
		$entry  = (object) [ 'name' => 'Test', 'email' => 'test@test.com' ];
		$result = ARForm::resolve_trigger( $node, [ $entry ] );

		$this->assertFalse( $result );
	}

	/**
	 * Test that trigger returns false with empty args array
	 */
	public function test_trigger_returns_false_with_empty_args(): void {
		$node   = $this->makeARFormTriggerNode( 'form_submitted', 'any' );
		$result = ARForm::resolve_trigger( $node, [] );

		$this->assertFalse( $result );
	}

	/**
	 * Test that trigger returns false when hook fires for different event
	 */
	public function test_resolve_trigger_returns_false_for_different_event(): void {
		$node = [
			'event' => 'other_event',
			'data'  => [
				'app'   => 'ar-form',
				'event' => 'other_event',
			],
		];
		$result = ARForm::resolve_trigger( $node, [ $this->makeEntry() ] );

		$this->assertFalse( $result );
	}

	// ========== DYNAMIC QUERIES ==========

	/**
	 * Test that form_query returns options array
	 * Note: This test requires ARForm plugin to be active to get real data
	 * We'll test the structure with a mock
	 */
	public function test_form_query_returns_array_of_options(): void {
		// Skip if ARForm classes not available (expected in test environment)
		if ( ! class_exists( 'ARF_Model_Form' ) ) {
			$this->markTestSkipped( 'ARForm plugin not available' );
		}

		$result = ARForm::form_query_types( null );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result, 'form_query should return at least "Any Form" option' );

		// Check first option is "Any Form"
		$this->assertEquals( 'Any Form', $result[0]['label'] );
		$this->assertEquals( 'any', $result[0]['value'] );
	}

	/**
	 * Test that form_query structure is correct (has label and value keys)
	 */
	public function test_form_query_structure(): void {
		// We'll test the method logic by mocking wpdb directly
		global $wpdb;

		// Mock the database query result
		$mockForms = [
			(object) [ 'id' => 1, 'name' => 'Contact Form' ],
			(object) [ 'id' => 2, 'name' => 'Newsletter Signup' ],
		];

		// Temporarily override wpdb
		$originalGetResults = $wpdb->get_results;
		$wpdb->get_results = function( $query ) use ( $mockForms ) {
			// Verify query structure
			$this->assertStringContainsString( $wpdb->prefix . 'arf_forms', $query );
			$this->assertStringContainsString( 'is_template = 0', $query );
			$this->assertStringContainsString( "status = 'published'", $query );
			return $mockForms;
		};

		// Clear the class_exists cache
		$classExists = class_exists( 'ARF_Model_Form', false );

		// Call the method (it should use our mock)
		$result = ARForm::form_query_types( null );

		// Restore original method
		$wpdb->get_results = $originalGetResults;

		$this->assertCount( 3, $result ); // Any + 2 forms
		$this->assertEquals( 'Contact Form', $result[1]['label'] );
		$this->assertEquals( 1, $result[1]['value'] );
		$this->assertEquals( 'Newsletter Signup', $result[2]['label'] );
		$this->assertEquals( 2, $result[2]['value'] );
	}

	// ========== BULK TESTS ==========
	// Define which triggers/actions to auto-test via IntegrationTestCase

	/**
	 * Specify triggers to test automatically
	 * Format: 'event_name' => [mock_args]
	 */
	protected function getTriggerTests(): array {
		return [
			'form_submitted' => [ $this->makeEntry() ],
		];
	}

	// ARForm has no actions currently
	protected function getActionTests(): array {
		return [];
	}

	// ========== EDGE CASES ==========

	/**
	 * Test form_id comparison with different types (string vs int)
	 */
	public function test_trigger_form_id_type_comparison(): void {
		$node   = $this->makeARFormTriggerNode( 'form_submitted', '5' ); // string
		$entry  = $this->makeEntry( [ 'form_id' => 5 ] ); // int
		$result = ARForm::resolve_trigger( $node, [ $entry ] );

		$this->assertIsArray( $result, 'Should match when string "5" === int 5 after casting' );
		$this->assertEquals( 5, $result['form']['form_id'] );
	}

	/**
	 * Test that entry data is cast to array correctly
	 */
	public function test_form_data_is_array(): void {
		$node   = $this->makeARFormTriggerNode( 'form_submitted', 'any' );
		$entry  = $this->makeEntry();
		$result = ARForm::resolve_trigger( $node, [ $entry ] );

		$this->assertIsArray( $result['form']['form_data'] );
		$this->assertArrayHasKey( 'id', $result['form']['form_data'] );
		$this->assertArrayHasKey( 'form_id', $result['form']['form_data'] );
		$this->assertArrayHasKey( 'name', $result['form']['form_data'] );
	}

	/**
	 * Test that success key is always set in payload
	 */
	public function test_payload_includes_success_key(): void {
		$node   = $this->makeARFormTriggerNode( 'form_submitted', 'any' );
		$entry  = $this->makeEntry();
		$result = ARForm::resolve_trigger( $node, [ $entry ] );

		$this->assertArrayHasKey( 'success', $result );
		$this->assertTrue( $result['success'] );
	}
}
