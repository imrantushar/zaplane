<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Kadenceblocks;

class KadenceblocksTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Kadenceblocks::class;
	}

	protected function getTriggerTests(): array {
		return [
			'kadence_blocks_advanced_form_submission' => [
				[],
				[
					[ 'label' => 'Email', 'name' => 'field1', 'type' => 'email', 'value' => 'test@example.com' ],
					[ 'label' => 'Name',  'name' => 'field2', 'type' => 'text',  'value' => 'John' ],
				],
				1,
			],
		];
	}

	public function test_trigger_maps_fields_by_label(): void {
		$result = Kadenceblocks::resolve_trigger(
			$this->makeTriggerNode( 'kadence_blocks_advanced_form_submission' ),
			[
				[],
				[
					[ 'label' => 'Email',   'name' => 'field1', 'type' => 'email',    'value' => 'jane@example.com' ],
					[ 'label' => 'Name',    'name' => 'field2', 'type' => 'text',     'value' => 'Jane' ],
					[ 'label' => 'Message', 'name' => 'field3', 'type' => 'textarea', 'value' => 'Hello' ],
				],
				1,
			]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'jane@example.com', $result['Email'] );
		$this->assertEquals( 'Jane', $result['Name'] );
		$this->assertEquals( 'Hello', $result['Message'] );
	}

	public function test_trigger_falls_back_to_name_when_label_empty(): void {
		$result = Kadenceblocks::resolve_trigger(
			$this->makeTriggerNode( 'kadence_blocks_advanced_form_submission' ),
			[
				[],
				[
					[ 'label' => '', 'name' => 'field_email', 'type' => 'email', 'value' => 'no-label@example.com' ],
				],
				1,
			]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'no-label@example.com', $result['field_email'] );
	}

	public function test_trigger_returns_empty_array_when_no_fields(): void {
		$result = Kadenceblocks::resolve_trigger(
			$this->makeTriggerNode( 'kadence_blocks_advanced_form_submission' ),
			[ [], [], 1 ]
		);

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_trigger_returns_false_for_unknown_event(): void {
		$node          = $this->makeTriggerNode( 'kadence_blocks_advanced_form_submission' );
		$node['event'] = '__unknown__';

		$this->assertFalse( Kadenceblocks::resolve_trigger( $node, [] ) );
	}

	public function test_execute_node_is_passthrough(): void {
		$input  = [ 'Email' => 'test@example.com', 'Name' => 'John' ];
		$result = Kadenceblocks::execute_node( $this->makeActionNode( '__any__', [] ), $input );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $input, $result['data'] );
	}

	public function test_get_slug_returns_kadenceblocks(): void {
		$this->assertEquals( 'kadenceblocks', Kadenceblocks::get_slug() );
	}
}
