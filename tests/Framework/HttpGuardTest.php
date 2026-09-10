<?php

namespace Zaplane\Tests\Framework;

use Zaplane\HttpGuard;
use Zaplane\Tests\TestCase;

/**
 * The Webhook action, the HTTP Request action and every Custom App ask this
 * before making a request. Nothing answered it, so the only check that ran was
 * the scheme.
 */
class HttpGuardTest extends TestCase {

	/**
	 * @param string $url
	 */
	private function blocked( $url ): bool {
		return HttpGuard::block( false, $url, wp_parse_url( $url ) );
	}

	/**
	 * The address a workflow reaches is often chosen at run time, and for a
	 * webhook trigger it is chosen by whoever posted the body.
	 *
	 * @test
	 */
	public function it_refuses_addresses_only_this_machine_can_reach(): void {
		foreach ( [
			'http://169.254.169.254/latest/meta-data/',
			'http://127.0.0.1:3306/',
			'http://10.0.0.5/admin',
			'http://192.168.1.1/',
			'http://172.16.0.1/',
			'http://100.64.0.1/',
			'http://0.0.0.0/',
			'http://[::1]/',
			'http://[fe80::1]/',
		] as $url ) {
			$this->assertTrue( $this->blocked( $url ), $url . ' should be refused' );
		}
	}

	/**
	 * A name that resolves to nothing is refused rather than passed on: it would
	 * only fail to connect, and a lookup failing here but succeeding a moment
	 * later is the shape this guard exists to catch.
	 *
	 * @test
	 */
	public function a_host_that_resolves_to_nothing_is_refused(): void {
		$this->assertTrue( $this->blocked( 'http://no-such-host.invalid/' ) );
		$this->assertTrue( $this->blocked( 'http:///no-host-at-all' ) );
	}

	/**
	 * The point is to keep ordinary webhooks working.
	 *
	 * @test
	 */
	public function an_ordinary_public_address_is_allowed(): void {
		$this->assertFalse( $this->blocked( 'https://example.com/hook' ) );
	}

	/**
	 * A refusal already made stands.
	 *
	 * @test
	 */
	public function it_does_not_overturn_an_earlier_refusal(): void {
		$this->assertTrue( HttpGuard::block( true, 'https://example.com/', wp_parse_url( 'https://example.com/' ) ) );
	}

	/**
	 * A site with an internal service a workflow legitimately calls can say so —
	 * by whole hostname, so one entry cannot admit a lookalike.
	 *
	 * @test
	 */
	public function a_named_private_host_can_be_allowed_without_admitting_its_neighbours(): void {
		add_filter( 'zaplane_http_allowed_private_hosts', fn() => [ '10.0.0.5' ] );

		$this->assertFalse( $this->blocked( 'http://10.0.0.5/service' ) );
		$this->assertTrue( $this->blocked( 'http://10.0.0.50/service' ) );
		$this->assertTrue( $this->blocked( 'http://10.0.0.6/service' ) );
	}
}
