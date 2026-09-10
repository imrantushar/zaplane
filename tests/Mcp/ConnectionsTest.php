<?php

namespace Zaplane\Tests\Mcp;

use Zaplane\Mcp\Connections;
use Zaplane\Tests\TestCase;

class ConnectionsTest extends TestCase {

	/**
	 * Core's own screen does the asking and the issuing; this only decides what
	 * it is called and where it comes back to.
	 *
	 * @test
	 */
	public function the_authorize_url_points_at_core_and_returns_here(): void {
		$url = Connections::authorize_url( 'Zaplane – Claude desktop', 'https://example.com/wp-admin/admin.php?page=zaplane-settings&tab=mcp' );

		$this->assertStringContainsString( 'authorize-application.php', $url );
		$this->assertStringContainsString( 'app_name=Zaplane', $url );
		$this->assertStringContainsString( 'app_id=', $url );
		$this->assertStringContainsString( 'success_url=', $url );
		$this->assertStringContainsString( 'reject_url=', $url );
	}

	/**
	 * Approving and declining have to arrive back distinguishable, because core
	 * says nothing about which one happened.
	 *
	 * @test
	 */
	public function the_two_return_addresses_are_told_apart(): void {
		$url  = Connections::authorize_url( 'X', 'https://example.com/wp-admin/admin.php?page=zaplane-settings' );
		$open = rawurldecode( $url );

		$this->assertStringContainsString( 'zaplane_connect=done', $open );
		$this->assertStringContainsString( 'zaplane_connect=cancelled', $open );
	}

	/**
	 * Core sends to any domain on purpose. This screen does not need that, and a
	 * freshly minted password is what follows the address.
	 *
	 * @test
	 */
	public function a_return_address_off_this_site_is_replaced(): void {
		$url  = Connections::authorize_url( 'X', 'https://somewhere-else.example/collect' );
		$open = rawurldecode( $url );

		$this->assertStringNotContainsString( 'somewhere-else.example', $open );
		$this->assertStringContainsString( 'page=zaplane-settings', $open );
	}

	/**
	 * A credential made for something else is not this screen's to revoke.
	 *
	 * @test
	 */
	public function it_will_not_revoke_a_password_made_elsewhere(): void {
		$this->assertFalse( Connections::forget( 'not-a-real-uuid' ) );
	}

	/**
	 * Nothing here should keep a credential — core does that, and keeping a
	 * second copy would defeat the point of using core at all.
	 *
	 * @test
	 */
	public function it_stores_nothing_of_its_own(): void {
		$source = file_get_contents( ZAPLANE_INCLUDES_DIR_PATH . 'mcp/connections.php' );

		foreach ( [ 'update_option', 'add_option', 'set_transient', 'INSERT' ] as $writer ) {
			$this->assertStringNotContainsString( $writer, $source, 'Connections must not write a store of its own' );
		}
	}
}
