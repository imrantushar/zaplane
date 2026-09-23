<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Brevo;

/**
 * Brevo (contacts and lists over its REST API, with an API key).
 */
class BrevoTest extends IntegrationTestCase {

	/** @var array<string,string> */
	private $credentials = [ 'api_key' => 'xkeysib-test' ];

	protected function getIntegrationClass(): string {
		return Brevo::class;
	}

	public function test_requires_an_api_key_connection(): void {
		$this->assertTrue( Brevo::requires_connection() );
		$this->assertArrayHasKey( 'api_key', Brevo::get_auth_fields() );
	}

	public function test_offers_the_four_contact_actions(): void {
		$this->assertSame(
			[ 'create_contact', 'add_contact_to_list', 'delete_contact', 'remove_contact_from_list' ],
			array_keys( Brevo::get_actions() )
		);
	}

	public function test_action_without_credentials_fails_loudly(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/credentials/i' );
		Brevo::execute_node( $this->makeActionNode( 'create_contact', [ 'email' => 'jane@example.com' ] ), [] );
	}

	public function test_action_with_an_empty_key_fails_loudly(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/API key/i' );
		Brevo::execute_node( $this->makeActionNode( 'create_contact', [ 'email' => 'jane@example.com' ], [ 'api_key' => '' ] ), [] );
	}

	public function test_create_contact_returns_the_new_contact_and_keeps_the_input(): void {
		$this->mockHttp( [ 'id' => 321 ], 201 );

		$result = Brevo::execute_node(
			$this->makeActionNode( 'create_contact', [ 'email' => 'jane@example.com', 'first_name' => 'Jane', 'list_id' => 7 ], $this->credentials ),
			[ 'from_trigger' => 1 ]
		);

		$this->assertSame( 'main', $result['port'] );
		$this->assertSame( 321, $result['data']['brevo_contact_id'] );
		$this->assertSame( 'jane@example.com', $result['data']['brevo_email'] );
		$this->assertSame( 1, $result['data']['from_trigger'] );
	}

	public function test_create_contact_error_carries_brevos_message(): void {
		$this->mockHttp( [ 'message' => 'Invalid email address' ], 400 );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/Invalid email address/' );
		Brevo::execute_node( $this->makeActionNode( 'create_contact', [ 'email' => 'nope' ], $this->credentials ), [] );
	}

	public function test_add_to_list_needs_a_list(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/list_id is required/' );
		Brevo::execute_node( $this->makeActionNode( 'add_contact_to_list', [ 'emails' => 'jane@example.com' ], $this->credentials ), [] );
	}
}
