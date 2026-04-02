<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Essentialblocks;

class EssentialblocksTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Essentialblocks::class;
	}

	protected function getTriggerTests(): array {
		return [
			'eb_form_submit_before_email' => [ 'My Form', [ 'email' => 'test@example.com', 'name' => 'John' ] ],
		];
	}

	public function test_trigger_returns_form_name_and_fields(): void {
		$result = Essentialblocks::resolve_trigger(
			$this->makeTriggerNode( 'eb_form_submit_before_email' ),
			[ 'Contact Form', [ 'email' => 'test@example.com', 'name' => 'Jane' ] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'Contact Form', $result['form_name'] );
		$this->assertEquals( 'test@example.com', $result['email'] );
		$this->assertEquals( 'Jane', $result['name'] );
	}

	public function test_trigger_returns_form_name_with_empty_fields(): void {
		$result = Essentialblocks::resolve_trigger(
			$this->makeTriggerNode( 'eb_form_submit_before_email' ),
			[ 'My Form', [] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'My Form', $result['form_name'] );
	}

	public function test_trigger_returns_false_for_unknown_event(): void {
		$node          = $this->makeTriggerNode( 'eb_form_submit_before_email' );
		$node['event'] = '__unknown__';

		$this->assertFalse( Essentialblocks::resolve_trigger( $node, [] ) );
	}

	public function test_execute_node_is_passthrough(): void {
		$input  = [ 'email' => 'test@example.com' ];
		$result = Essentialblocks::execute_node( $this->makeActionNode( '__any__', [] ), $input );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $input, $result['data'] );
	}

	public function test_get_slug_returns_essentialblocks(): void {
		$this->assertEquals( 'essentialblocks', Essentialblocks::get_slug() );
	}
}
