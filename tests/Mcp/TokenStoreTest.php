<?php

namespace Zaplane\Tests\Mcp;

use Zaplane\Mcp\TokenStore;
use Zaplane\Tests\TestCase;

class TokenStoreTest extends TestCase {

	/**
	 * @test
	 */
	public function it_issues_a_token_whose_secret_is_returned_once_and_never_stored(): void {
		$issued = TokenStore::issue( 'Desktop client' );

		$this->assertArrayHasKey( 'token', $issued );
		$this->assertStringStartsWith( 'zpl_', $issued['token'] );

		$stored = TokenStore::all();
		$this->assertCount( 1, $stored );
		$this->assertSame( 'Desktop client', $stored[0]['name'] );

		// The plaintext must not survive anywhere in the record.
		$this->assertArrayNotHasKey( 'hash', $stored[0] );
		$this->assertStringNotContainsString(
			$issued['token'],
			(string) wp_json_encode( get_option( 'zaplane_mcp_tokens', [] ) )
		);
	}

	/**
	 * @test
	 */
	public function it_resolves_a_token_it_issued(): void {
		$issued   = TokenStore::issue( 'Client A' );
		$resolved = TokenStore::resolve( $issued['token'] );

		$this->assertNotNull( $resolved );
		$this->assertSame( $issued['id'], $resolved['id'] );
		$this->assertSame( 'Client A', $resolved['name'] );
	}

	/**
	 * @test
	 */
	public function it_rejects_a_tampered_secret(): void {
		$issued = TokenStore::issue( 'Client A' );

		[ $prefix ] = explode( '.', $issued['token'], 2 );

		$this->assertNull( TokenStore::resolve( $prefix . '.wrong-secret' ) );
		$this->assertNull( TokenStore::resolve( 'zpl_nosuchid.whatever' ) );
		$this->assertNull( TokenStore::resolve( '' ) );
		$this->assertNull( TokenStore::resolve( 'garbage' ) );
	}

	/**
	 * @test
	 */
	public function it_records_last_used_on_a_successful_resolve(): void {
		$issued = TokenStore::issue( 'Client A' );

		$this->assertNull( TokenStore::all()[0]['last_used_at'] );

		TokenStore::resolve( $issued['token'] );

		$this->assertNotNull( TokenStore::all()[0]['last_used_at'] );
	}

	/**
	 * @test
	 */
	public function it_defaults_to_read_and_write_but_never_run(): void {
		$issued = TokenStore::issue( 'Client A' );

		$this->assertContains( TokenStore::SCOPE_READ, $issued['scopes'] );
		$this->assertContains( TokenStore::SCOPE_WRITE, $issued['scopes'] );
		$this->assertNotContains( TokenStore::SCOPE_RUN, $issued['scopes'] );
	}

	/**
	 * @test
	 */
	public function write_implies_read(): void {
		$issued = TokenStore::issue( 'Client A', [ TokenStore::SCOPE_WRITE ] );

		$this->assertContains( TokenStore::SCOPE_READ, $issued['scopes'] );
	}

	/**
	 * @test
	 */
	public function it_drops_unknown_scopes_and_falls_back_when_none_are_valid(): void {
		$issued = TokenStore::issue( 'Client A', [ 'read', 'superuser', 'delete-everything' ] );
		$this->assertSame( [ 'read' ], $issued['scopes'] );

		$fallback = TokenStore::issue( 'Client B', [ 'nonsense' ] );
		$this->assertSame( TokenStore::DEFAULT_SCOPES, $fallback['scopes'] );
	}

	/**
	 * @test
	 */
	public function has_scope_reflects_what_was_granted(): void {
		$record = TokenStore::resolve(
			TokenStore::issue( 'Read only', [ TokenStore::SCOPE_READ ] )['token']
		);

		$this->assertTrue( TokenStore::has_scope( $record, TokenStore::SCOPE_READ ) );
		$this->assertFalse( TokenStore::has_scope( $record, TokenStore::SCOPE_WRITE ) );
		$this->assertFalse( TokenStore::has_scope( $record, TokenStore::SCOPE_RUN ) );
	}

	/**
	 * @test
	 */
	public function revoking_one_token_leaves_the_others_working(): void {
		$a = TokenStore::issue( 'Client A' );
		$b = TokenStore::issue( 'Client B' );

		$this->assertTrue( TokenStore::revoke( $a['id'] ) );

		$this->assertNull( TokenStore::resolve( $a['token'] ) );
		$this->assertNotNull( TokenStore::resolve( $b['token'] ) );

		$this->assertFalse( TokenStore::revoke( 'not-a-real-id' ) );
	}

	/**
	 * @test
	 */
	public function a_pre_scoping_token_keeps_working_with_every_scope(): void {
		update_option( 'zaplane_mcp_token', 'legacy-secret-value' );

		$resolved = TokenStore::resolve( 'legacy-secret-value' );

		$this->assertNotNull( $resolved );
		$this->assertSame( TokenStore::ALL_SCOPES, $resolved['scopes'] );
		$this->assertTrue( $resolved['legacy'] );

		$this->assertNull( TokenStore::resolve( 'not-the-legacy-value' ) );
	}

	/**
	 * @test
	 */
	public function has_any_reports_whether_the_endpoint_is_reachable_at_all(): void {
		$this->assertFalse( TokenStore::has_any() );

		TokenStore::issue( 'Client A' );

		$this->assertTrue( TokenStore::has_any() );
	}

	/**
	 * @test
	 */
	public function an_unnamed_token_still_gets_a_label(): void {
		$this->assertSame( 'MCP client', TokenStore::issue( '   ' )['name'] );
	}
}
