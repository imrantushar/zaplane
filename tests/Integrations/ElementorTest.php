<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Elementor;

class ElementorTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Elementor::class;
	}

	protected function getTriggerTests(): array {
		return [
			'elementor_pro/forms/new_record' => [ $this->makeFormRecord( [ 'email' => 'test@example.com' ] ) ],
		];
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	private function makeFormRecord( array $data ): object {
		return new class( $data ) {
			private array $data;
			public function __construct( array $data ) {
				$this->data = $data;
			}
			public function get( string $key ) {
				return $this->data[ $key ] ?? [];
			}
		};
	}

	// =========================================================================
	// TRIGGER: elementor_pro/forms/new_record
	// =========================================================================

	public function test_trigger_returns_form_fields_from_record(): void {
		$record = $this->makeFormRecord( [ 'sent_data' => [ 'email' => 'jane@example.com', 'name' => 'Jane' ] ] );

		$result = Elementor::resolve_trigger(
			$this->makeTriggerNode( 'elementor_pro/forms/new_record' ),
			[ $record ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'jane@example.com', $result['email'] );
		$this->assertEquals( 'Jane', $result['name'] );
	}

	public function test_trigger_returns_empty_array_when_sent_data_empty(): void {
		$record = $this->makeFormRecord( [ 'sent_data' => [] ] );

		$result = Elementor::resolve_trigger(
			$this->makeTriggerNode( 'elementor_pro/forms/new_record' ),
			[ $record ]
		);

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_trigger_returns_empty_array_when_form_data_falsy(): void {
		$result = Elementor::resolve_trigger(
			$this->makeTriggerNode( 'elementor_pro/forms/new_record' ),
			[ null ]
		);

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_trigger_returns_false_for_unknown_event(): void {
		$node          = $this->makeTriggerNode( 'elementor_pro/forms/new_record' );
		$node['event'] = '__unknown__';

		$this->assertFalse( Elementor::resolve_trigger( $node, [] ) );
	}

	public function test_execute_node_is_passthrough(): void {
		$input  = [ 'email' => 'test@example.com' ];
		$result = Elementor::execute_node( $this->makeActionNode( '__any__', [] ), $input );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $input, $result['data'] );
	}

	public function test_get_slug_returns_elementor(): void {
		$this->assertEquals( 'elementor', Elementor::get_slug() );
	}
}
