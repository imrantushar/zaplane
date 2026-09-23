<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Router;

/**
 * Router: the first matching route's path, otherwise Fallback.
 */
class RouterTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Router::class;
	}

	/** @param array<int,array<string,string>> $routes */
	private function route( $value, array $routes ): array {
		return Router::execute_node( $this->makeActionNode( 'route', [ 'value' => $value, 'routes' => $routes ] ), [ 'keep' => 1 ] );
	}

	public function test_first_match_wins(): void {
		$out = $this->route( 'paid', [ [ 'operator' => '==', 'value' => 'pending' ], [ 'operator' => 'contains', 'value' => 'ai' ], [ 'operator' => '==', 'value' => 'paid' ] ] );
		$this->assertSame( 'path_2', $out['port'] );
		$this->assertSame( 'path_2', $out['data']['matched_path'] );
		$this->assertSame( 1, $out['data']['keep'] );
	}

	public function test_no_match_goes_to_fallback(): void {
		$this->assertSame( 'fallback', $this->route( 'refunded', [ [ 'operator' => '==', 'value' => 'paid' ] ] )['port'] );
		$this->assertSame( 'fallback', $this->route( 'x', [] )['port'] );
	}

	public function test_empty_route_values_are_skipped_and_numbers_compare(): void {
		$out = $this->route( 150, [ [ 'operator' => '==', 'value' => '' ], [ 'operator' => '>', 'value' => '100' ] ] );
		$this->assertSame( 'path_2', $out['port'] );
		$this->assertSame( '150', $out['data']['routed_value'] );
	}

	public function test_every_returned_port_exists(): void {
		foreach ( [ 'a', 'b', 'z' ] as $v ) {
			$this->assertContains( $this->route( $v, [ [ 'operator' => '==', 'value' => 'a' ], [ 'operator' => '==', 'value' => 'b' ] ] )['port'], Router::get_output_ports() );
		}
	}
}
