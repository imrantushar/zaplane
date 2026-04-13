<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Weforms;

class WeformsTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Weforms::class;
	}

	protected function getTriggerTests(): array {
		return [
			'weforms_entry_submission' => [ [
				'form_id'         => 14,
				'First Name' => 'John',
				'entry_id'  => 122,
				'Email'      => 'john@example.com',
				'Message'    => 'Hello',
			] ],
		];
	}

	public function test_trigger_returns_form_fields(): void {
		$result = Weforms::resolve_trigger(
			$this->makeTriggerNode( 'weforms_entry_submission' ),
			[ [
				'form_id'      => 14,
				'First Name'   => 'John',
				'entry_id'     => 122,
				'Email'        => 'john@example.com',
				'Message'      => 'Hello',
			] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 14, $result['form_id'] );
		$this->assertEquals( 122, $result['entry_id'] );
		$this->assertEquals( 'John', $result['First Name'] );
		$this->assertEquals( 'john@example.com', $result['Email'] );
		$this->assertEquals( 'Hello', $result['Message'] );
	}

	public function test_trigger_returns_false_for_unknown_event(): void {
		$node          = $this->makeTriggerNode( 'weforms_entry_submission' );
		$node['event'] = '__unknown__';

		$this->assertFalse( Weforms::resolve_trigger( $node, [] ) );
	}

	public function test_execute_node_is_passthrough(): void {
		$input  = [ 'email' => 'test@example.com' ];
		$result = Weforms::execute_node( $this->makeActionNode( '__any__', [] ), $input );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $input, $result['data'] );
	}

	public function test_get_slug_returns_weforms(): void {
		$this->assertEquals( 'weforms', Weforms::get_slug() );
	}
}
