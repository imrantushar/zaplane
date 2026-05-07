<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Coblocks;

class CoblocksTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Coblocks::class;
	}

	protected function getTriggerTests(): array {
		return [
			'coblocks_form_submit' => [ [ 'email' => 'test@example.com', 'name' => 'John' ] ],
		];
	}

	public function test_trigger_returns_form_fields(): void {
		$result = Coblocks::resolve_trigger(
			$this->makeTriggerNode( 'coblocks_form_submit' ),
			[ [ 'email' => 'test@example.com', 'name' => 'Jane', 'message' => 'Hello' ] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'test@example.com', $result['email'] );
		$this->assertEquals( 'Jane', $result['name'] );
		$this->assertEquals( 'Hello', $result['message'] );
	}

	public function test_trigger_returns_false_for_unknown_event(): void {
		$node          = $this->makeTriggerNode( 'coblocks_form_submit' );
		$node['event'] = '__unknown__';

		$this->assertFalse( Coblocks::resolve_trigger( $node, [] ) );
	}

	public function test_execute_node_is_passthrough(): void {
		$input  = [ 'email' => 'test@example.com' ];
		$result = Coblocks::execute_node( $this->makeActionNode( '__any__', [] ), $input );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $input, $result['data'] );
	}

	public function test_get_slug_returns_coblocks(): void {
		$this->assertEquals( 'coblocks', Coblocks::get_slug() );
	}
}
