<?php

namespace Zaplane\Tests\Mcp\OAuth;

use Zaplane\Mcp\OAuth\ClientStore;
use Zaplane\Tests\TestCase;

class ClientStoreTest extends TestCase {

	/**
	 * @test
	 */
	public function it_registers_a_client_and_gives_it_back_an_id(): void {
		$client = ClientStore::register(
			[
				'client_name'   => 'Claude',
				'redirect_uris' => [ 'https://claude.ai/api/mcp/auth_callback' ],
			]
		);

		$this->assertStringStartsWith( 'zpc_', $client['client_id'] );
		$this->assertSame( 'Claude', $client['client_name'] );
		$this->assertSame( [ 'https://claude.ai/api/mcp/auth_callback' ], $client['redirect_uris'] );

		$this->assertSame( $client, ClientStore::get( $client['client_id'] ) );
		$this->assertNull( ClientStore::get( 'zpc_nothing' ) );
	}

	/**
	 * @test
	 */
	public function it_accepts_https_loopback_and_private_use_schemes(): void {
		foreach (
			[
				'https://example.com/cb',
				'http://127.0.0.1:8765/cb',
				'http://localhost:1410/oauth/callback',
				'com.example.app:/callback',
			] as $uri
		) {
			$client = ClientStore::register( [ 'redirect_uris' => [ $uri ] ] );
			$this->assertSame( [ $uri ], $client['redirect_uris'], $uri . ' should be accepted' );
		}
	}

	/**
	 * @test
	 */
	public function it_refuses_plain_http_to_a_remote_host_and_fragments(): void {
		foreach (
			[
				'http://evil.example.com/cb',
				'https://example.com/cb#fragment',
				'not a url',
				'',
			] as $uri
		) {
			try {
				ClientStore::register( [ 'redirect_uris' => [ $uri ] ] );
				$this->fail( $uri . ' should have been refused' );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertStringContainsString( 'redirect_uris', $e->getMessage() );
			}
		}
	}

	/**
	 * @test
	 */
	public function it_matches_a_redirect_exactly_and_never_by_prefix(): void {
		$client = ClientStore::register( [ 'redirect_uris' => [ 'https://claude.ai/cb' ] ] );

		$this->assertTrue( ClientStore::redirect_allowed( $client, 'https://claude.ai/cb' ) );

		// Prefix matching here is how an authorization code gets delivered to
		// somebody else's server.
		$this->assertFalse( ClientStore::redirect_allowed( $client, 'https://claude.ai/cb/../steal' ) );
		$this->assertFalse( ClientStore::redirect_allowed( $client, 'https://claude.ai/cb.evil.com' ) );
		$this->assertFalse( ClientStore::redirect_allowed( $client, 'https://claude.ai/cb?x=1' ) );
		$this->assertFalse( ClientStore::redirect_allowed( $client, 'https://evil.com/cb' ) );
	}

	/**
	 * Several connectors re-register on every reconnect. Minting a fresh row each
	 * time would walk the whole cap and evict the registrations still in use.
	 *
	 * @test
	 */
	public function it_hands_back_the_same_registration_for_identical_metadata(): void {
		$metadata = [
			'client_name'   => 'Claude',
			'redirect_uris' => [ 'https://claude.ai/cb', 'https://claude.ai/cb2' ],
		];

		$first  = ClientStore::register( $metadata );
		$second = ClientStore::register( $metadata );

		$this->assertSame( $first['client_id'], $second['client_id'] );
		$this->assertCount( 1, ClientStore::all() );

		// Order of the redirect list is not what makes two registrations different.
		$reordered = ClientStore::register(
			[
				'client_name'   => 'Claude',
				'redirect_uris' => [ 'https://claude.ai/cb2', 'https://claude.ai/cb' ],
			]
		);
		$this->assertSame( $first['client_id'], $reordered['client_id'] );

		// A different redirect list is a different client.
		$other = ClientStore::register(
			[
				'client_name'   => 'Claude',
				'redirect_uris' => [ 'https://claude.ai/somewhere-else' ],
			]
		);
		$this->assertNotSame( $first['client_id'], $other['client_id'] );
		$this->assertCount( 2, ClientStore::all() );
	}

	/**
	 * @test
	 */
	public function it_caps_how_many_clients_an_open_endpoint_can_leave_behind(): void {
		for ( $i = 0; $i < 55; $i++ ) {
			ClientStore::register(
				[
					'client_name'   => 'Client ' . $i,
					'redirect_uris' => [ 'https://example.com/cb/' . $i ],
				]
			);
		}

		$clients = ClientStore::all();
		$this->assertCount( 50, $clients );

		// The newest survive; the oldest are the ones dropped.
		$names = array_column( $clients, 'client_name' );
		$this->assertContains( 'Client 54', $names );
		$this->assertNotContains( 'Client 0', $names );
	}

	/**
	 * @test
	 */
	public function it_forgets_a_client(): void {
		$client = ClientStore::register( [ 'redirect_uris' => [ 'https://example.com/cb' ] ] );

		$this->assertTrue( ClientStore::forget( $client['client_id'] ) );
		$this->assertNull( ClientStore::get( $client['client_id'] ) );
		$this->assertFalse( ClientStore::forget( $client['client_id'] ) );
	}
}
