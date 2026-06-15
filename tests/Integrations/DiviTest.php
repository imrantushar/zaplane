<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Divi;

class DiviTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Divi::class;
	}

	protected function getTriggerTests(): array {
		return [
			'contact_form_submit' => [
				[
					'email'   => [ 'value' => 'test@example.com' ],
					'name'    => [ 'value' => 'John Doe' ],
					'message' => [ 'value' => 'Hello World' ],
				],
				null,
				[
					'contact_form_unique_id' => 'cf_1',
					'post_id'                => 1,
				],
			],
		];
	}

	public function test_trigger_returns_all_expected_fields(): void {

		$form_fields = [
			'email'   => [ 'value' => 'test@example.com' ],
			'name'    => [ 'value' => 'Jane Doe' ],
			'message' => [ 'value' => 'Hi there' ],
		];

		$form_meta = [
			'contact_form_unique_id' => 'cf_1',
			'post_id'                => 42,
		];

		$result = Divi::resolve_trigger(
			$this->makeTriggerNode( 'contact_form_submit' ),
			[ $form_fields, null, $form_meta ]
		);

		$this->assertIsArray( $result );

		$this->assertEquals( 'cf_1', $result['id'] );
		$this->assertEquals( 42, $result['post_id'] );

		$this->assertEquals( 'test@example.com', $result['email'] );
		$this->assertEquals( 'Jane Doe', $result['name'] );
		$this->assertEquals( 'Hi there', $result['message'] );

		$this->assertArrayHasKey( 'submitted_at', $result );
	}

	public function test_trigger_returns_defaults_when_fields_missing(): void {

		$result = Divi::resolve_trigger(
			$this->makeTriggerNode( 'contact_form_submit' ),
			[ [], null, [] ]
		);

		$this->assertIsArray( $result );

		$this->assertEquals( '', $result['id'] );
		$this->assertEquals( '', $result['post_id'] );

		$this->assertArrayHasKey( 'submitted_at', $result );
	}

	public function test_trigger_returns_false_for_unknown_event(): void {

		$node = $this->makeTriggerNode( 'contact_form_submit' );
		$node['event'] = '__unknown__';

		$result = Divi::resolve_trigger(
			$node,
			[ [], null, [] ]
		);

		$this->assertFalse( $result );
	}

	public function test_execute_node_is_passthrough(): void {

		$input = [
			'email' => 'test@example.com',
			'name'  => 'Jane Doe',
		];

		$result = Divi::execute_node(
			$this->makeActionNode( '__any__', [] ),
			$input
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $input, $result['data'] );
	}

	public function test_get_slug_returns_divi(): void {
		$this->assertEquals( 'divi', Divi::get_slug() );
	}

	public function test_get_name_returns_divi_builder(): void {
		$this->assertEquals( 'Divi Builder', Divi::get_name() );
	}

	public function test_trigger_is_registered_with_label_and_hook(): void {

		$triggers = Divi::get_triggers();

		$this->assertArrayHasKey( 'contact_form_submit', $triggers );

		$this->assertEquals(
			'Contact Form Submitted',
			$triggers['contact_form_submit']['label']
		);

		$this->assertEquals(
			'et_pb_contact_form_submit',
			$triggers['contact_form_submit']['hook']
		);
	}
}
