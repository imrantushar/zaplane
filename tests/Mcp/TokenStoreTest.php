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
	 * A token from before scoping was stored in plain text, held every scope and
	 * belonged to nobody. It is refused, and deleted the first time it is shown.
	 *
	 * @test
	 */
	public function a_pre_scoping_token_is_refused_and_removed(): void {
		update_option( 'zaplane_mcp_token', 'legacy-secret-value' );

		$this->assertNull( TokenStore::resolve( 'not-the-legacy-value' ) );
		$this->assertSame( 'legacy-secret-value', get_option( 'zaplane_mcp_token', '' ), 'A wrong guess must not delete it' );

		$this->assertNull( TokenStore::resolve( 'legacy-secret-value' ) );
		$this->assertSame( '', (string) get_option( 'zaplane_mcp_token', '' ) );
		$this->assertFalse( TokenStore::has_any() );
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

	/**
	 * Holding `run` used to mean holding it over every workflow on the site.
	 *
	 * @test
	 */
	public function a_token_can_be_limited_to_particular_workflows(): void {
		$issued = TokenStore::issue( 'Scoped', [ 'read', 'run' ], 1, [ 'workflows' => [ 3, 1, 3 ] ] );

		$this->assertSame( [ 1, 3 ], $issued['workflows'], 'deduplicated and ordered' );

		$record = TokenStore::resolve( $issued['token'] );
		$this->assertTrue( TokenStore::may_run( $record, 1 ) );
		$this->assertTrue( TokenStore::may_run( $record, 3 ) );
		$this->assertFalse( TokenStore::may_run( $record, 2 ) );
		$this->assertFalse( TokenStore::may_run( $record, 0 ), 'a missing workflow_id is not a wildcard' );
	}

	/**
	 * Naming none keeps the old meaning, so nothing issued before this narrows
	 * underneath whoever was using it.
	 *
	 * @test
	 */
	public function naming_no_workflows_still_means_all_of_them(): void {
		$issued = TokenStore::issue( 'Unscoped', [ 'read', 'run' ], 1 );
		$record = TokenStore::resolve( $issued['token'] );

		$this->assertSame( [], $issued['workflows'] );
		$this->assertTrue( TokenStore::may_run( $record, 1 ) );
		$this->assertTrue( TokenStore::may_run( $record, 9999 ) );

		// A record predating the field behaves the same.
		$this->assertTrue( TokenStore::may_run( [ 'scopes' => [ 'run' ] ], 42 ) );
	}

	/**
	 * @test
	 */
	public function workflow_ids_are_cleaned_before_they_are_stored(): void {
		$this->assertSame( [ 2, 7 ], TokenStore::sanitize_workflows( [ '7', 2, 0, -4, 'x', 7 ] ) );
		$this->assertSame( [], TokenStore::sanitize_workflows( [] ) );
	}

	/**
	 * The stamp goes on the token that was used, not on whatever now sits where
	 * it used to. This took an array index captured while verifying, and a
	 * revoke in between shifts every later record down one.
	 *
	 * @test
	 */
	public function last_used_lands_on_the_token_that_was_used(): void {
		$first  = TokenStore::issue( 'First' );
		$second = TokenStore::issue( 'Second' );
		$third  = TokenStore::issue( 'Third' );

		// The record in front of it goes away, shifting the rest down.
		TokenStore::revoke( $first['id'] );

		TokenStore::resolve( $third['token'] );

		foreach ( TokenStore::all() as $row ) {
			if ( $third['id'] === $row['id'] ) {
				$this->assertNotNull( $row['last_used_at'], 'The used token should be stamped' );
			}

			if ( $second['id'] === $row['id'] ) {
				$this->assertNull( $row['last_used_at'], 'A token nobody used must not be stamped' );
			}
		}
	}

	/**
	 * A token that expired with no refresh token can never be used again, so it
	 * goes. One still holding a refresh token is not dead — it is waiting to be
	 * renewed — and dropping it would end a working connection.
	 *
	 * @test
	 */
	public function issuing_clears_out_tokens_that_can_never_be_used_again(): void {
		$dead  = TokenStore::issue( 'Lapsed', TokenStore::DEFAULT_SCOPES, 0, [ 'expires_in' => 1 ] );
		$alive = TokenStore::issue( 'Lapsed but renewable', TokenStore::DEFAULT_SCOPES, 0, [ 'expires_in' => 1, 'with_refresh' => true ] );

		$this->ageOut( $dead['id'] );
		$this->ageOut( $alive['id'] );

		TokenStore::issue( 'Something new' );

		$ids = array_column( TokenStore::all(), 'id' );

		$this->assertNotContains( $dead['id'], $ids );
		$this->assertContains( $alive['id'], $ids, 'A refresh token is a way back; the record has to stay' );
	}

	/** Push a token's expiry well past the grace period. */
	private function ageOut( string $id ): void {
		$records = get_option( 'zaplane_mcp_tokens', [] );

		foreach ( $records as $i => $record ) {
			if ( $id === ( $record['id'] ?? '' ) ) {
				$records[ $i ]['expires_at'] = time() - ( 30 * DAY_IN_SECONDS );
			}
		}

		update_option( 'zaplane_mcp_tokens', $records, false );
	}
}
