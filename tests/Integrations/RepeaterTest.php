<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Repeater;

/**
 * Repeater: loops a count or a number range, one item per pass.
 */
class RepeaterTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Repeater::class;
	}

	/** Drive the loop the way the engine does, collecting each pass's `i`. */
	private function drain( array $config ): array {
		$seen  = [];
		$input = [];
		for ( $guard = 0; $guard < 20001; $guard++ ) {
			$out = Repeater::execute_node( $this->makeActionNode( 'repeat', $config ), $input );
			if ( 'done' === $out['port'] ) {
				return [ $seen, $out['data']['total'] ];
			}
			$seen[] = $out['data']['i'];
			$input  = array_merge( [ '_is_iterating' => true, '_remaining' => $out['remaining'] ], $out['iterator_state'] );
		}
		$this->fail( 'the loop never finished' );
	}

	public function test_count_mode_runs_n_times_with_first_and_last_flags(): void {
		$first = Repeater::execute_node( $this->makeActionNode( 'repeat', [ 'times' => 3 ] ), [] );
		$this->assertSame( 'loop', $first['port'] );
		$this->assertTrue( $first['data']['is_first'] );
		$this->assertFalse( $first['data']['is_last'] );
		$this->assertSame( [ [ 1, 2, 3 ], 3 ], $this->drain( [ 'times' => 3 ] ) );
	}

	public function test_range_mode_counts_up_or_down_by_step(): void {
		$this->assertSame( [ 0, 5, 10 ], $this->drain( [ 'mode' => 'range', 'from' => 0, 'to' => 10, 'step' => 5 ] )[0] );
		$this->assertSame( [ 3, 2, 1 ], $this->drain( [ 'mode' => 'range', 'from' => 3, 'to' => 1, 'step' => 0 ] )[0] );
	}

	public function test_nothing_to_do_goes_straight_to_done(): void {
		$out = Repeater::execute_node( $this->makeActionNode( 'repeat', [ 'times' => 0 ] ), [] );
		$this->assertSame( 'done', $out['port'] );
	}

	public function test_a_huge_range_is_capped_without_building_it_all(): void {
		$start = microtime( true );
		$out   = Repeater::execute_node( $this->makeActionNode( 'repeat', [ 'mode' => 'range', 'from' => 1, 'to' => 1000000000 ] ), [] );
		$this->assertSame( 10000, $out['data']['total'] );
		$this->assertLessThan( 2.0, microtime( true ) - $start );
	}
}
