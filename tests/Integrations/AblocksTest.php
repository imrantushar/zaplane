<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Ablocks;

class AblocksTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Ablocks::class;
	}

	private function makeFormInfo(): array {
		return [
			'info' => [
				'type'    => 'contact',
				'postId'  => 10,
				'email'   => 'jane@example.com',
				'actions' => [ 'submission' ],
				'config'  => [
					'block_id' => 'abc123',
					'formName' => 'Newsletter',
				],
			],
			'data' => [
				'name'  => [ 'value' => 'Jane' ],
				'email' => [ 'value' => 'jane@example.com' ],
			],
		];
	}

	public function test_get_slug_returns_ablocks(): void {
		$this->assertEquals( 'ablocks', Ablocks::get_slug() );
	}

	public function test_form_submitted_returns_payload(): void {
		$node   = $this->makeTriggerNode( 'form_submitted', [ 'form_id' => 'any' ] );
		$result = Ablocks::resolve_trigger( $node['data'], [ $this->makeFormInfo(), [], null ] );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'abc123', $result['form']['form_id'] );
		$this->assertEquals( 'Newsletter', $result['form']['form_name'] );
		$this->assertEquals( 'contact', $result['form']['form_type'] );
		$this->assertEquals( 'Jane', $result['form']['form_data']['name'] );
		$this->assertEquals( 'jane@example.com', $result['form']['form_data']['email'] );
	}

	public function test_form_submitted_respects_form_filter(): void {
		$node = $this->makeTriggerNode( 'form_submitted', [ 'form_id' => 'other-form' ] );
		$this->assertFalse( Ablocks::resolve_trigger( $node['data'], [ $this->makeFormInfo(), [], null ] ) );
	}

	public function test_form_submitted_matches_specific_form(): void {
		$node   = $this->makeTriggerNode( 'form_submitted', [ 'form_id' => 'abc123' ] );
		$result = Ablocks::resolve_trigger( $node['data'], [ $this->makeFormInfo(), [], null ] );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
	}

	public function test_returns_false_for_unknown_event(): void {
		$node                  = $this->makeTriggerNode( 'form_submitted' );
		$node['data']['event'] = '__unknown__';
		$this->assertFalse( Ablocks::resolve_trigger( $node['data'], [ $this->makeFormInfo(), [], null ] ) );
	}

	public function test_returns_false_when_form_info_empty(): void {
		$node = $this->makeTriggerNode( 'form_submitted', [ 'form_id' => 'any' ] );
		$this->assertFalse( Ablocks::resolve_trigger( $node['data'], [ [], [], null ] ) );
	}
}
