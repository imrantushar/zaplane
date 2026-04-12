<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Metform;

class MetformTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Metform::class;
	}

	protected function getTriggerTests(): array {
		return [
			'metform_after_store_form_data' => [ null, [ 'mf-email' => 'test@example.com', 'mf-name' => 'John' ] ],
		];
	}

	public function test_trigger_returns_form_fields_as_flat_array(): void {
		$result = Metform::resolve_trigger(
			$this->makeTriggerNode( 'metform_after_store_form_data' ),
			[ null, [ 'mf-email' => 'jane@example.com', 'mf-name' => 'Jane', 'mf-message' => 'Hi' ] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'jane@example.com', $result['mf-email'] );
		$this->assertEquals( 'Jane', $result['mf-name'] );
		$this->assertEquals( 'Hi', $result['mf-message'] );
	}

	public function test_trigger_returns_empty_array_when_form_data_empty(): void {
		$result = Metform::resolve_trigger(
			$this->makeTriggerNode( 'metform_after_store_form_data' ),
			[ null, '' ]
		);

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_trigger_returns_false_for_unknown_event(): void {
		$node          = $this->makeTriggerNode( 'metform_after_store_form_data' );
		$node['event'] = '__unknown__';

		$this->assertFalse( Metform::resolve_trigger( $node, [] ) );
	}

	public function test_execute_node_is_passthrough(): void {
		$input  = [ 'mf-email' => 'test@example.com' ];
		$result = Metform::execute_node( $this->makeActionNode( '__any__', [] ), $input );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $input, $result['data'] );
	}

	public function test_get_slug_returns_metform(): void {
		$this->assertEquals( 'metform', Metform::get_slug() );
	}
}
