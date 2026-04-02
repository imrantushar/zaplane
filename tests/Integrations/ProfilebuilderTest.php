<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Profilebuilder;

/**
 * Test suite for the Profile Builder integration.
 *
 * ── How this file is structured ──────────────────────────────────────────────
 *
 * 1. CONTRACT (inherited)
 *    IntegrationTestCase auto-runs:
 *      - all_tested_triggers_are_registered   — every key in getTriggerTests()
 *                                               must exist in get_triggers()
 *      - triggers_fire_and_return_payload      — bulk: each trigger returns array|false
 *      - actions_execute_and_return_valid_format — bulk: each action returns {port,data}
 *
 * 2. TRIGGER TESTS  (hand-written, one per trigger × happy + sad path)
 *    Each trigger has:
 *      - A happy-path test: valid args → assert key fields in the returned array.
 *      - A sad-path test:   invalid/missing args → assert empty array ([]).
 *      - Any edge-case tests specific to that trigger's logic.
 *
 * 3. ACTION TESTS
 *    Profile Builder has no actions — execute_node() is a passthrough.
 *    We verify the contract and passthrough behaviour only.
 *
 * ── Triggers covered ─────────────────────────────────────────────────────────
 *
 *   user_registration            — fired after a user registers
 *   user_profile_update          — fired after a user updates their profile
 *   user_email_confirmation      — fired when a user confirms their email
 *   email_send_by_profile_builder— fired after Profile Builder sends an email
 *   user_approved_by_admin       — fired when an admin approves a user
 *   user_unapproved_by_admin     — fired when an admin unapproves a user
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ProfilebuilderTest extends IntegrationTestCase {

	// -------------------------------------------------------------------------
	// Contract
	// -------------------------------------------------------------------------

	protected function getIntegrationClass(): string {
		return Profilebuilder::class;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a sample form data array.
	 *
	 * @param array $overrides Override any default field.
	 */
	private function makeFormData( array $overrides = [] ): array {
		return array_merge( [
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'email'      => 'john@example.com',
			'pass1'      => 'secret',
			'pass2'      => 'secret',
			'password'   => 'secret',
		], $overrides );
	}

	/**
	 * Setup common WordPress function mocks.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Mock WordPress functions that may not be available in the test environment.
		if ( ! function_exists( 'wp_trim_words' ) ) {
			eval( 'function wp_trim_words( $text, $num_words = 55, $more = null ) { return substr( $text, 0, 100 ); }' );
		}
		if ( ! function_exists( 'wp_strip_all_tags' ) ) {
			eval( 'function wp_strip_all_tags( $string, $remove_breaks = false ) { return strip_tags( $string ); }' );
		}
		if ( ! function_exists( 'current_time' ) ) {
			eval( 'function current_time( $type, $gmt = 0 ) { return "2026-01-01 00:00:00"; }' );
		}
	}

	// -------------------------------------------------------------------------
	// Bulk runner data
	// -------------------------------------------------------------------------

	protected function getTriggerTests(): array {
		return [
			// user_registration: expects [form_data, form_id, user_id]
			'user_registration' => [
				$this->makeFormData(),
				1,
				123,
			],

			// user_profile_update: same args as registration
			'user_profile_update' => [
				$this->makeFormData(),
				1,
				123,
			],

			// user_email_confirmation: expects [user_id, activation_key, meta]
			'user_email_confirmation' => [
				123,
				'activation_key_123',
				[ 'some_meta' => 'value' ],
			],

			// email_send_by_profile_builder: expects [sent, to, subject, message]
			'email_send_by_profile_builder' => [
				true,
				'user@example.com',
				'Welcome to the site',
				'<p>Hello, thank you for registering.</p>',
			],

			// user_approved_by_admin: expects [user_id]
			'user_approved_by_admin' => [ 123 ],

			// user_unapproved_by_admin: expects [user_id]
			'user_unapproved_by_admin' => [ 123 ],
		];
	}

	// =========================================================================
	// TRIGGER: user_registration
	// =========================================================================

	public function test_trigger_user_registration_returns_form_data_and_user_id(): void {
		$form_data = $this->makeFormData();
		$user_id = 123;

		$result = Profilebuilder::resolve_trigger(
			$this->makeTriggerNode( 'user_registration' ),
			[ $form_data, 1, $user_id ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( $user_id, $result['user_id'] );
		$this->assertArrayHasKey( 'form_data', $result );
		$this->assertArrayHasKey( 'submitted_at', $result );

		// Sensitive fields must be removed
		$this->assertArrayNotHasKey( 'pass1', $result['form_data'] );
		$this->assertArrayNotHasKey( 'pass2', $result['form_data'] );
		$this->assertArrayNotHasKey( 'password', $result['form_data'] );
	}

	public function test_trigger_user_registration_returns_empty_array_without_form_data(): void {
		$result = Profilebuilder::resolve_trigger(
			$this->makeTriggerNode( 'user_registration' ),
			[ null, 1, 123 ]
		);
		$this->assertSame( [], $result );
	}

	public function test_trigger_user_registration_returns_array_with_user_id_zero_when_no_user_id(): void {
		// The implementation returns an array with user_id = 0, not false.
		$result = Profilebuilder::resolve_trigger(
			$this->makeTriggerNode( 'user_registration' ),
			[ $this->makeFormData(), 1, 0 ]
		);
		$this->assertIsArray( $result );
		$this->assertEquals( 0, $result['user_id'] );
		$this->assertArrayHasKey( 'form_data', $result );
	}

	// =========================================================================
	// TRIGGER: user_profile_update
	// =========================================================================

	public function test_trigger_user_profile_update_returns_form_data_and_user_id(): void {
		$form_data = $this->makeFormData();
		$user_id = 123;

		$result = Profilebuilder::resolve_trigger(
			$this->makeTriggerNode( 'user_profile_update' ),
			[ $form_data, 1, $user_id ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( $user_id, $result['user_id'] );
		$this->assertArrayHasKey( 'form_data', $result );
		$this->assertArrayHasKey( 'submitted_at', $result );

		// Sensitive fields must be removed
		$this->assertArrayNotHasKey( 'pass1', $result['form_data'] );
		$this->assertArrayNotHasKey( 'pass2', $result['form_data'] );
		$this->assertArrayNotHasKey( 'password', $result['form_data'] );
	}

	public function test_trigger_user_profile_update_returns_empty_array_without_form_data(): void {
		$result = Profilebuilder::resolve_trigger(
			$this->makeTriggerNode( 'user_profile_update' ),
			[ null, 1, 123 ]
		);
		$this->assertSame( [], $result );
	}

	public function test_trigger_user_profile_update_returns_array_with_user_id_zero_when_no_user_id(): void {
		$result = Profilebuilder::resolve_trigger(
			$this->makeTriggerNode( 'user_profile_update' ),
			[ $this->makeFormData(), 1, 0 ]
		);
		$this->assertIsArray( $result );
		$this->assertEquals( 0, $result['user_id'] );
		$this->assertArrayHasKey( 'form_data', $result );
	}

	// =========================================================================
	// TRIGGER: user_email_confirmation
	// =========================================================================

	public function test_trigger_user_email_confirmation_returns_user_id_and_meta(): void {
		$user_id = 123;
		$meta = [ 'key' => 'value' ];

		$result = Profilebuilder::resolve_trigger(
			$this->makeTriggerNode( 'user_email_confirmation' ),
			[ $user_id, 'activation_key', $meta ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( $user_id, $result['user_id'] );
		$this->assertEquals( $meta, $result['meta'] );
		$this->assertArrayHasKey( 'submitted_at', $result );
	}

	public function test_trigger_user_email_confirmation_returns_empty_array_without_user_id(): void {
		$result = Profilebuilder::resolve_trigger(
			$this->makeTriggerNode( 'user_email_confirmation' ),
			[ 0, 'key', [] ]
		);
		$this->assertSame( [], $result );
	}

	// =========================================================================
	// TRIGGER: email_send_by_profile_builder
	// =========================================================================

	public function test_trigger_email_send_returns_email_details(): void {
		$sent = true;
		$to = 'user@example.com';
		$subject = 'Welcome!';
		$message = '<p>Hello, welcome to our site. This is a long message that will be truncated.</p>';

		$result = Profilebuilder::resolve_trigger(
			$this->makeTriggerNode( 'email_send_by_profile_builder' ),
			[ $sent, $to, $subject, $message ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( $to, $result['mail_to'] );
		$this->assertEquals( $subject, $result['subject'] );
		$this->assertEquals( $sent, $result['sent'] );
		$this->assertArrayHasKey( 'message_preview', $result );
		$this->assertArrayHasKey( 'submitted_at', $result );
	}

	public function test_trigger_email_send_returns_empty_array_without_recipient(): void {
		$result = Profilebuilder::resolve_trigger(
			$this->makeTriggerNode( 'email_send_by_profile_builder' ),
			[ true, '', 'Subject', 'Message' ]
		);
		$this->assertSame( [], $result );
	}

	// =========================================================================
	// TRIGGER: user_approved_by_admin
	// =========================================================================

	public function test_trigger_user_approved_returns_user_id(): void {
		$user_id = 123;

		$result = Profilebuilder::resolve_trigger(
			$this->makeTriggerNode( 'user_approved_by_admin' ),
			[ $user_id ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( $user_id, $result['user_id'] );
		$this->assertArrayHasKey( 'submitted_at', $result );
	}

	public function test_trigger_user_approved_returns_empty_array_without_user_id(): void {
		$result = Profilebuilder::resolve_trigger(
			$this->makeTriggerNode( 'user_approved_by_admin' ),
			[ 0 ]
		);
		$this->assertSame( [], $result );
	}

	// =========================================================================
	// TRIGGER: user_unapproved_by_admin
	// =========================================================================

	public function test_trigger_user_unapproved_returns_user_id(): void {
		$user_id = 123;

		$result = Profilebuilder::resolve_trigger(
			$this->makeTriggerNode( 'user_unapproved_by_admin' ),
			[ $user_id ]
		);

		$this->assertIsArray( $result );
		$this->assertEquals( $user_id, $result['user_id'] );
		$this->assertArrayHasKey( 'submitted_at', $result );
	}

	public function test_trigger_user_unapproved_returns_empty_array_without_user_id(): void {
		$result = Profilebuilder::resolve_trigger(
			$this->makeTriggerNode( 'user_unapproved_by_admin' ),
			[ 0 ]
		);
		$this->assertSame( [], $result );
	}

	// =========================================================================
	// ACTIONS
	// =========================================================================

	/**
	 * Profile Builder has no actions — execute_node() is a passthrough that returns
	 * the input data unchanged on the 'main' port.
	 */
	public function test_execute_node_is_passthrough(): void {
		$input = [ 'user_id' => 1, 'form_data' => [ 'email' => 'test@example.com' ] ];
		$result = Profilebuilder::execute_node(
			$this->makeActionNode( '__any__', [] ),
			$input
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $input, $result['data'] );
	}

	// =========================================================================
	// SCHEMA & CONTRACT
	// =========================================================================

	public function test_get_slug_returns_profilebuilder(): void {
		$this->assertEquals( 'profilebuilder', Profilebuilder::get_slug() );
	}

	public function test_get_name_returns_profile_builder(): void {
		$this->assertEquals( 'Profile Builder', Profilebuilder::get_name() );
	}

	public function test_get_icon_returns_string(): void {
		$this->assertIsString( Profilebuilder::get_icon() );
	}

	public function test_all_triggers_registered(): void {
		$triggers = Profilebuilder::get_triggers();
		$expected = [
			'user_registration',
			'user_profile_update',
			'user_email_confirmation',
			'email_send_by_profile_builder',
			'user_approved_by_admin',
			'user_unapproved_by_admin',
		];
		foreach ( $expected as $event ) {
			$this->assertArrayHasKey( $event, $triggers, "Trigger '$event' missing from get_triggers()" );
		}
	}

	public function test_trigger_config_schema_returns_empty_array(): void {
		// Profile Builder does not implement trigger configuration.
		$this->assertSame( [], Profilebuilder::get_trigger_config_schema( 'user_registration' ) );
		$this->assertSame( [], Profilebuilder::get_trigger_config_schema( 'unknown_trigger' ) );
	}

	public function test_get_actions_returns_empty_array(): void {
		$this->assertSame( [], Profilebuilder::get_actions() );
	}
}
