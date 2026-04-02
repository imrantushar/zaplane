<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Beaverbuilder;

class BeaverbuilderTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Beaverbuilder::class;
	}

	protected function getTriggerTests(): array {
		return [
			'contact_form_submission'   => [ null, null, "Name: John\nEmail: john@example.com\nMessage: Hello" ],
			'login_form_submission'     => [ null, 'secret', 'admin' ],
			'subscribe_form_submission' => [ null, null, 'sub@example.com', 'Subscriber' ],
		];
	}

	// =========================================================================
	// TRIGGER: contact_form_submission
	// =========================================================================

	public function test_trigger_contact_form_parses_name_email_message(): void {
		$message = "Name: Jane Smith\nEmail: jane@example.com\nMessage: Hello there";

		$result = Beaverbuilder::resolve_trigger(
			$this->makeTriggerNode( 'contact_form_submission' ),
			[ null, null, $message ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'Jane Smith', $result['name'] );
		$this->assertEquals( 'jane@example.com', $result['email'] );
		$this->assertEquals( 'Hello there', $result['message'] );
	}

	public function test_trigger_contact_form_returns_empty_strings_when_message_empty(): void {
		$result = Beaverbuilder::resolve_trigger(
			$this->makeTriggerNode( 'contact_form_submission' ),
			[ null, null, '' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( '', $result['name'] );
		$this->assertEquals( '', $result['email'] );
		$this->assertEquals( '', $result['message'] );
	}

	// =========================================================================
	// TRIGGER: login_form_submission
	// =========================================================================

	public function test_trigger_login_form_returns_username_and_password(): void {
		$result = Beaverbuilder::resolve_trigger(
			$this->makeTriggerNode( 'login_form_submission' ),
			[ null, 'mypassword', 'myuser' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'mypassword', $result['user_pass'] );
		$this->assertEquals( 'myuser', $result['username'] );
	}

	public function test_trigger_login_form_returns_defaults_when_args_missing(): void {
		$result = Beaverbuilder::resolve_trigger(
			$this->makeTriggerNode( 'login_form_submission' ),
			[]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( '', $result['user_pass'] );
		$this->assertEquals( '', $result['username'] );
	}

	// =========================================================================
	// TRIGGER: subscribe_form_submission
	// =========================================================================

	public function test_trigger_subscribe_form_returns_email_and_name(): void {
		$result = Beaverbuilder::resolve_trigger(
			$this->makeTriggerNode( 'subscribe_form_submission' ),
			[ null, null, 'sub@example.com', 'Subscriber Name' ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'sub@example.com', $result['email'] );
		$this->assertEquals( 'Subscriber Name', $result['name'] );
	}

	public function test_trigger_subscribe_form_returns_defaults_when_args_missing(): void {
		$result = Beaverbuilder::resolve_trigger(
			$this->makeTriggerNode( 'subscribe_form_submission' ),
			[]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( '', $result['email'] );
		$this->assertEquals( '', $result['name'] );
	}

	// =========================================================================
	// COMMON
	// =========================================================================

	public function test_trigger_returns_false_for_unknown_event(): void {
		$node          = $this->makeTriggerNode( 'contact_form_submission' );
		$node['event'] = '__unknown__';

		$this->assertFalse( Beaverbuilder::resolve_trigger( $node, [] ) );
	}

	public function test_execute_node_is_passthrough(): void {
		$input  = [ 'email' => 'test@example.com' ];
		$result = Beaverbuilder::execute_node( $this->makeActionNode( '__any__', [] ), $input );

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $input, $result['data'] );
	}

	public function test_get_slug_returns_beaverbuilder(): void {
		$this->assertEquals( 'beaverbuilder', Beaverbuilder::get_slug() );
	}
}
