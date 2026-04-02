<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Spectra;

class SpectraTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Spectra::class;
	}

	protected function getTriggerTests(): array {
		return [
			'uagb_form_success' => [ [
				'id'         => 'form-1',
				'First Name' => 'John',
				'Last Name'  => 'Doe',
				'Email'      => 'john@example.com',
				'Message'    => 'Hello',
			] ],
		];
	}

	public function test_trigger_returns_all_expected_fields(): void {
		$result = Spectra::resolve_trigger(
			$this->makeTriggerNode( 'uagb_form_success' ),
			[ [
				'id'         => 'form-42',
				'First Name' => 'Jane',
				'Last Name'  => 'Smith',
				'Email'      => 'jane@example.com',
				'Message'    => 'Hi there',
			] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'form-42', $result['form_id'] );
		$this->assertEquals( 'Jane', $result['form_fname'] );
		$this->assertEquals( 'Smith', $result['form_lname'] );
		$this->assertEquals( 'jane@example.com', $result['form_email'] );
		$this->assertEquals( 'Hi there', $result['form_message'] );
		$this->assertArrayHasKey( 'submitted_time', $result );
	}

	public function test_trigger_returns_defaults_when_fields_missing(): void {
		$result = Spectra::resolve_trigger(
			$this->makeTriggerNode( 'uagb_form_success' ),
			[ [] ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( '', $result['form_id'] );
		$this->assertEquals( '', $result['form_fname'] );
		$this->assertEquals( '', $result['form_email'] );
	}

	public function test_trigger_returns_false_for_unknown_event(): void {
		$node          = $this->makeTriggerNode( 'uagb_form_success' );
		$node['event'] = '__unknown__';

		$this->assertFalse( Spectra::resolve_trigger( $node, [] ) );
	}

	public function test_execute_node_is_passthrough(): void {
		$input  = [ 'form_email' => 'test@example.com' ];
		$result = Spectra::execute_node( $this->makeActionNode( '__any__', [] ), $input );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $input, $result['data'] );
	}

	public function test_get_slug_returns_spectra(): void {
		$this->assertEquals( 'spectra', Spectra::get_slug() );
	}
}
