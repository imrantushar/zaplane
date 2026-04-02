<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Divi;

class DiviTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Divi::class;
	}

	protected function getTriggerTests(): array {
		return [
			'divi_contact_form_submitted' => [
				[
					'email'   => [ 'value' => 'test@example.com' ],
					'name'    => [ 'value' => 'John' ],
					'message' => [ 'value' => 'Hello' ],
				],
				null,
				[
					'contact_form_id' => 'cf_1',
					'post_id'         => 1,
				],
			],
		];
	}

	// =========================================================================
	// TRIGGER: divi_contact_form_submitted
	// =========================================================================

	public function test_trigger_returns_all_expected_fields(): void {
		$form_fields = [
			'email'   => [ 'value' => 'test@example.com' ],
			'name'    => [ 'value' => 'Jane' ],
			'message' => [ 'value' => 'Hi there' ],
		];
		$form_meta = [
			'contact_form_id' => 'cf_1',
			'post_id'         => 42,
		];

		$result = Divi::resolve_trigger(
			$this->makeTriggerNode( 'divi_contact_form_submitted' ),
			[ $form_fields, null, $form_meta ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'cf_1', $result['form_id'] );
		$this->assertEquals( 42, $result['post_id'] );
		$this->assertEquals( 'test@example.com', $result['email'] );
		$this->assertEquals( 'Jane', $result['name'] );
		$this->assertEquals( 'Hi there', $result['message'] );
		$this->assertArrayHasKey( 'submitted_at', $result );
	}

	public function test_trigger_returns_defaults_when_fields_missing(): void {
		$result = Divi::resolve_trigger(
			$this->makeTriggerNode( 'divi_contact_form_submitted' ),
			[ [], null, [] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( '', $result['form_id'] );
		$this->assertEquals( 0, $result['post_id'] );
		$this->assertEquals( '', $result['email'] );
		$this->assertEquals( '', $result['name'] );
		$this->assertEquals( '', $result['message'] );
	}

	public function test_trigger_returns_false_for_unknown_event(): void {
		$node   = $this->makeTriggerNode( 'divi_contact_form_submitted' );
		$node['event'] = '__unknown__';

		$result = Divi::resolve_trigger( $node, [ [], null, [] ] );

		$this->assertFalse( $result );
	}

	// =========================================================================
	// ACTIONS
	// =========================================================================

	public function test_execute_node_is_passthrough(): void {
		$input  = [ 'email' => 'test@example.com', 'name' => 'Jane' ];
		$result = Divi::execute_node(
			$this->makeActionNode( '__any__', [] ),
			$input
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $input, $result['data'] );
	}

	// =========================================================================
	// SCHEMA & CONTRACT
	// =========================================================================

	public function test_get_slug_returns_divi(): void {
		$this->assertEquals( 'divi', Divi::get_slug() );
	}

	public function test_trigger_is_registered_with_label_and_hook(): void {
		$triggers = Divi::get_triggers();
		$this->assertArrayHasKey( 'divi_contact_form_submitted', $triggers );
		$this->assertNotEmpty( $triggers['divi_contact_form_submitted']['label'] );
		$this->assertNotEmpty( $triggers['divi_contact_form_submitted']['hook'] );
	}
}
