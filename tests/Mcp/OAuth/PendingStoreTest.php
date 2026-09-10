<?php

namespace Zaplane\Tests\Mcp\OAuth;

use Zaplane\Mcp\OAuth\PendingStore;
use Zaplane\Tests\TestCase;

class PendingStoreTest extends TestCase {

	/** @var array<string,mixed> */
	private array $client = [
		'client_id'   => 'zpc_teammate',
		'client_name' => 'Teammate laptop',
	];

	private const CHALLENGE = 'aDX_WFrcr3lZAKYjUx63aiZ66O-EbG7qiKI042aaEOE';

	private function request( int $user = 7, string $challenge = self::CHALLENGE, array $scopes = [ 'read', 'write', 'run' ] ): array {
		return PendingStore::request( $this->client, 'https://claude.ai/cb', $challenge, $scopes, $user );
	}

	/**
	 * The wait page reloads every few seconds. Each reload must find the request
	 * it already made, not queue another one.
	 *
	 * @test
	 */
	public function it_does_not_queue_a_second_row_for_the_same_attempt(): void {
		$first = $this->request();

		for ( $i = 0; $i < 5; $i++ ) {
			$again = $this->request();
			$this->assertSame( $first['id'], $again['id'] );
		}

		$this->assertCount( 1, PendingStore::all() );
	}

	/**
	 * @test
	 */
	public function a_different_person_or_a_different_attempt_is_a_different_request(): void {
		$mine = $this->request( 7 );

		$this->assertNotSame( $mine['id'], $this->request( 8 )['id'], 'another user' );
		$this->assertNotSame( $mine['id'], $this->request( 7, 'a-different-challenge-entirely-0123456789' )['id'], 'another attempt' );
		$this->assertCount( 3, PendingStore::all() );
	}

	/**
	 * An administrator can hand over less than was asked for, never more.
	 *
	 * @test
	 */
	public function approving_grants_no_more_than_was_requested(): void {
		$pending = $this->request( 7, self::CHALLENGE, [ 'read', 'write' ] );

		PendingStore::approve( $pending['id'], [ 'read', 'write', 'run' ] );

		$after = PendingStore::find( $pending['id'] );
		$this->assertSame( PendingStore::APPROVED, $after['status'] );
		$this->assertSame( [ 'read', 'write' ], $after['granted'], 'run was never asked for, so it cannot be granted' );
	}

	/**
	 * @test
	 */
	public function approving_can_withhold_the_dangerous_scope(): void {
		$pending = $this->request( 7, self::CHALLENGE, [ 'read', 'write', 'run' ] );

		PendingStore::approve( $pending['id'], [ 'read', 'write' ] );

		$this->assertSame( [ 'read', 'write' ], PendingStore::find( $pending['id'] )['granted'] );
	}

	/**
	 * Ticking nothing must not read as "everything".
	 *
	 * @test
	 */
	public function approving_nothing_falls_back_to_read_only(): void {
		$pending = $this->request();

		PendingStore::approve( $pending['id'], [] );

		$this->assertSame( [ 'read' ], PendingStore::find( $pending['id'] )['granted'] );
	}

	/**
	 * @test
	 */
	public function refusing_removes_it(): void {
		$pending = $this->request();

		$this->assertTrue( PendingStore::forget( $pending['id'] ) );
		$this->assertNull( PendingStore::find( $pending['id'] ) );
		$this->assertSame( 0, PendingStore::waiting_count() );
		$this->assertFalse( PendingStore::forget( $pending['id'] ) );
	}

	/**
	 * Nobody should return to a day-old prompt and approve it by reflex.
	 *
	 * @test
	 */
	public function a_stale_request_expires_on_its_own(): void {
		$pending = $this->request();

		$rows = get_option( 'zaplane_mcp_pending_requests', [] );
		$rows[0]['requested_at'] = time() - HOUR_IN_SECONDS;
		update_option( 'zaplane_mcp_pending_requests', $rows );

		$this->assertNull( PendingStore::find( $pending['id'] ) );
		$this->assertCount( 0, PendingStore::all() );
	}

	/**
	 * @test
	 */
	public function waiting_count_ignores_the_ones_already_decided(): void {
		$a = $this->request( 7 );
		$this->request( 8 );

		$this->assertSame( 2, PendingStore::waiting_count() );

		PendingStore::approve( $a['id'], [ 'read' ] );

		$this->assertSame( 1, PendingStore::waiting_count() );
		$this->assertCount( 2, PendingStore::all() );
	}
}
