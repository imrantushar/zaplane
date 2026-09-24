<?php

namespace Zaplane\Tests\Mcp\OAuth;

use Zaplane\Mcp\OAuth\ClientIdDocument;
use Zaplane\Tests\TestCase;
use Zaplane\Tests\WPMocks;

class ClientIdDocumentTest extends TestCase {

	private const URL = 'https://client.example/mcp/metadata.json';

	/**
	 * @param array<string,mixed> $document
	 */
	private function serve( array $document, int $status = 200 ): void {
		WPMocks::setHttpResponse( $document, $status );
	}

	/**
	 * https, a path, and no fragment — anything else is one of our own ids.
	 *
	 * @test
	 */
	public function it_recognises_a_client_identifier_url(): void {
		$this->assertTrue( ClientIdDocument::is_client_id_url( self::URL ) );

		$this->assertFalse( ClientIdDocument::is_client_id_url( 'http://client.example/x.json' ), 'not https' );
		$this->assertFalse( ClientIdDocument::is_client_id_url( 'https://client.example' ), 'no path' );
		$this->assertFalse( ClientIdDocument::is_client_id_url( 'https://client.example/' ), 'empty path' );
		$this->assertFalse( ClientIdDocument::is_client_id_url( self::URL . '#frag' ), 'has a fragment' );
		$this->assertFalse( ClientIdDocument::is_client_id_url( 'zpc_registered' ), 'a registered id' );
	}

	/**
	 * @test
	 */
	public function it_accepts_a_document_that_names_itself(): void {
		$this->serve(
			[
				'client_id'     => self::URL,
				'client_name'   => 'URL client',
				'redirect_uris' => [ 'https://client.example/cb' ],
			]
		);

		$client = ClientIdDocument::resolve( self::URL );

		$this->assertNotNull( $client );
		$this->assertSame( self::URL, $client['client_id'] );
		$this->assertSame( 'URL client', $client['client_name'] );
		$this->assertSame( [ 'https://client.example/cb' ], $client['redirect_uris'] );
	}

	/**
	 * Without this, any JSON anywhere could claim any identity.
	 *
	 * @test
	 */
	public function it_refuses_a_document_claiming_another_identity(): void {
		$this->serve(
			[
				'client_id'     => 'https://client.example/some/other.json',
				'redirect_uris' => [ 'https://client.example/cb' ],
			]
		);

		$this->assertNull( ClientIdDocument::resolve( self::URL ) );
	}

	/**
	 * @test
	 */
	public function it_refuses_a_document_with_no_identity_or_no_redirect(): void {
		$this->serve( [ 'redirect_uris' => [ 'https://client.example/cb' ] ] );
		$this->assertNull( ClientIdDocument::resolve( self::URL ), 'no client_id' );

		$this->serve( [ 'client_id' => self::URL ] );
		$this->assertNull( ClientIdDocument::resolve( self::URL ), 'no redirect_uris' );

		$this->serve(
			[
				'client_id'     => self::URL,
				'redirect_uris' => [ 'http://client.example/cb' ],
			]
		);
		$this->assertNull( ClientIdDocument::resolve( self::URL ), 'plain http redirect to a remote host' );
	}

	/**
	 * @test
	 */
	public function it_refuses_anything_that_is_not_a_200(): void {
		$this->serve( [ 'client_id' => self::URL, 'redirect_uris' => [ 'https://client.example/cb' ] ], 404 );
		$this->assertNull( ClientIdDocument::resolve( self::URL ) );

		$this->serve( [ 'client_id' => self::URL, 'redirect_uris' => [ 'https://client.example/cb' ] ], 302 );
		$this->assertNull( ClientIdDocument::resolve( self::URL ) );
	}

	/**
	 * The URL is chosen by a stranger and this server makes the request, so the
	 * addresses it must never reach are the point of the whole guard.
	 *
	 * @test
	 */
	public function it_will_not_fetch_a_private_or_reserved_address(): void {
		foreach (
			[
				'https://127.0.0.1/x.json'       => 'loopback',
				'https://10.0.0.1/x.json'        => 'private 10/8',
				'https://192.168.1.1/x.json'     => 'private LAN',
				'https://169.254.169.254/x.json' => 'cloud metadata',
				'https://localhost/x.json'       => 'localhost by name',
			] as $url => $label
		) {
			// Queue a perfectly valid document; it must never be reached.
			$this->serve( [ 'client_id' => $url, 'redirect_uris' => [ 'https://client.example/cb' ] ] );

			$this->assertNull( ClientIdDocument::resolve( $url ), $label . ' must not be fetched' );
		}
	}

	/**
	 * A failed lookup must not become the client's identity for the next hour.
	 *
	 * @test
	 */
	public function failures_are_not_cached(): void {
		$this->serve( [ 'nonsense' => true ] );
		$this->assertNull( ClientIdDocument::resolve( self::URL ) );

		$this->serve(
			[
				'client_id'     => self::URL,
				'client_name'   => 'Now valid',
				'redirect_uris' => [ 'https://client.example/cb' ],
			]
		);
		$this->assertSame( 'Now valid', ClientIdDocument::resolve( self::URL )['client_name'] );
	}
}
