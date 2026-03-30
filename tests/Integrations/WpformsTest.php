<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Wpforms;

class WpformsTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Wpforms::class;
	}

	// ========== CONTRACT TESTS (inherited automatically) ==========
	// - integration_has_slug
	// - all_triggers_have_labels_and_hooks
	// - all_actions_have_labels
	// - trigger_config_schemas_are_valid
	// - action_config_schemas_are_valid
	// - output_ports_are_valid

	// ========== HELPERS ==========

	private function makeWpformsNode( string $event, string $formId = 'any' ): array {
		return [
			'type'    => 'trigger',
			'event'   => $event,
			'form_id' => $formId,
			'data'    => [
				'app'   => 'wpforms',
				'event' => $event,
			],
		];
	}

	private function makeFields( array $overrides = [] ): array {
		return array_replace( [
			1 => [ 'type' => 'text',  'value' => 'Jane Doe' ],
			2 => [ 'type' => 'email', 'value' => 'jane@example.com' ],
		], $overrides );
	}

	private function makeEntry( array $overrides = [] ): array {
		return array_replace( [ 'id' => 55, 'post_id' => 0 ], $overrides );
	}

	private function makeFormData( int $id = 10 ): array {
		return [ 'id' => $id ];
	}

	// ========== TRIGGER: form_submitted — success paths ==========

	public function test_trigger_returns_payload_for_any_form(): void {
		$node   = $this->makeWpformsNode( 'form_submitted', 'any' );
		$result = Wpforms::resolve_trigger( $node, [
			$this->makeFields(),
			$this->makeEntry(),
			$this->makeFormData( 10 ),
		] );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 10, $result['form_id'] );
		$this->assertEquals( 55, $result['entry_id'] );
	}

	public function test_trigger_returns_payload_when_form_id_matches(): void {
		$node   = $this->makeWpformsNode( 'form_submitted', '10' );
		$result = Wpforms::resolve_trigger( $node, [
			$this->makeFields(),
			$this->makeEntry( [ 'id' => 99 ] ),
			$this->makeFormData( 10 ),
		] );

		$this->assertIsArray( $result );
		$this->assertEquals( 10, $result['form_id'] );
		$this->assertEquals( 99, $result['entry_id'] );
	}

	public function test_trigger_includes_field_values_in_data(): void {
		$fields = [
			5 => [ 'type' => 'text',  'value' => 'Hello' ],
			6 => [ 'type' => 'email', 'value' => 'hi@example.com' ],
		];

		$node   = $this->makeWpformsNode( 'form_submitted', 'any' );
		$result = Wpforms::resolve_trigger( $node, [
			$fields,
			$this->makeEntry(),
			$this->makeFormData( 10 ),
		] );

		$this->assertEquals( 'Hello', $result['data'][5] );
		$this->assertEquals( 'hi@example.com', $result['data'][6] );
	}

	public function test_trigger_includes_post_id_when_present(): void {
		$node   = $this->makeWpformsNode( 'form_submitted', 'any' );
		$result = Wpforms::resolve_trigger( $node, [
			$this->makeFields(),
			$this->makeEntry( [ 'id' => 1, 'post_id' => 42 ] ),
			$this->makeFormData( 10 ),
		] );

		$this->assertEquals( 42, $result['data']['post_id'] );
	}

	public function test_trigger_omits_post_id_when_zero(): void {
		$node   = $this->makeWpformsNode( 'form_submitted', 'any' );
		$result = Wpforms::resolve_trigger( $node, [
			$this->makeFields(),
			$this->makeEntry( [ 'id' => 1, 'post_id' => 0 ] ),
			$this->makeFormData( 10 ),
		] );

		$this->assertArrayNotHasKey( 'post_id', $result['data'] );
	}

	// ========== TRIGGER: form_submitted — name field ==========

	public function test_trigger_splits_name_field_into_parts(): void {
		$fields = [
			3 => [
				'type'   => 'name',
				'value'  => 'Jane Marie Doe',
				'first'  => 'Jane',
				'middle' => 'Marie',
				'last'   => 'Doe',
			],
		];

		$node   = $this->makeWpformsNode( 'form_submitted', 'any' );
		$result = Wpforms::resolve_trigger( $node, [
			$fields,
			$this->makeEntry(),
			$this->makeFormData( 10 ),
		] );

		$this->assertEquals( 'Jane Marie Doe', $result['data'][3] );
		$this->assertEquals( 'Jane', $result['data']['3:first'] );
		$this->assertEquals( 'Marie', $result['data']['3:middle'] );
		$this->assertEquals( 'Doe', $result['data']['3:last'] );
	}

	// ========== TRIGGER: form_submitted — file upload field ==========

	public function test_trigger_extracts_file_upload_urls(): void {
		$fields = [
			4 => [
				'type'      => 'file-upload',
				'value_raw' => [
					[ 'value' => 'https://example.com/file1.pdf' ],
					[ 'value' => 'https://example.com/file2.pdf' ],
				],
			],
		];

		$node   = $this->makeWpformsNode( 'form_submitted', 'any' );
		$result = Wpforms::resolve_trigger( $node, [
			$fields,
			$this->makeEntry(),
			$this->makeFormData( 10 ),
		] );

		$this->assertIsArray( $result['data'][4] );
		$this->assertCount( 2, $result['data'][4] );
		$this->assertEquals( 'https://example.com/file1.pdf', $result['data'][4][0] );
	}

	// ========== TRIGGER: form_submitted — filter paths (returns false) ==========

	public function test_trigger_returns_false_when_form_id_does_not_match(): void {
		$node   = $this->makeWpformsNode( 'form_submitted', '99' );
		$result = Wpforms::resolve_trigger( $node, [
			$this->makeFields(),
			$this->makeEntry(),
			$this->makeFormData( 10 ),
		] );

		$this->assertFalse( $result );
	}

	public function test_trigger_returns_false_when_form_data_has_no_id(): void {
		$node   = $this->makeWpformsNode( 'form_submitted', 'any' );
		$result = Wpforms::resolve_trigger( $node, [
			$this->makeFields(),
			$this->makeEntry(),
			[],
		] );

		$this->assertFalse( $result );
	}

	public function test_trigger_returns_false_with_empty_args(): void {
		$node   = $this->makeWpformsNode( 'form_submitted', 'any' );
		$result = Wpforms::resolve_trigger( $node, [] );

		$this->assertFalse( $result );
	}

	public function test_trigger_skips_non_array_fields(): void {
		$fields = [
			1 => 'not-an-array',
			2 => [ 'type' => 'text', 'value' => 'valid' ],
		];

		$node   = $this->makeWpformsNode( 'form_submitted', 'any' );
		$result = Wpforms::resolve_trigger( $node, [
			$fields,
			$this->makeEntry(),
			$this->makeFormData( 10 ),
		] );

		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( 1, $result['data'] );
		$this->assertEquals( 'valid', $result['data'][2] );
	}
}
