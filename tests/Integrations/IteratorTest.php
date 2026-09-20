<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Iterator;

/**
 * Contract-only stub for the Iterator control-flow integration.
 * Empty source routes to the `done` port; both `loop` and `done`
 * are declared in get_output_ports.
 */
class IteratorTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Iterator::class;
	}

	protected function getActionTests(): array {
		return [
			'iterator' => [ 'source' => [] ],
		];
	}

	/** The source field is now an expression so users pick a list with the @ picker. */
	public function test_source_field_is_an_expression() {
		$schema = Iterator::get_action_config_schema( 'iterator' );

		$this->assertSame( 'source', $schema[0]['key'] );
		$this->assertSame( 'expression', $schema[0]['type'] );
	}

	/** An expression that resolved to a real array loops over that array. */
	public function test_iterates_over_a_resolved_array() {
		$node = [ 'data' => [ 'config' => [ 'source' => [ [ 'id' => 1 ], [ 'id' => 2 ] ] ] ] ];

		$out = Iterator::execute_node( $node, [] );

		$this->assertSame( 'loop', $out['port'] );
		$this->assertSame( 'iterate', $out['status'] );
		$this->assertSame( [ 'id' => 1 ], $out['data']['item'] );
		$this->assertSame( [ [ 'id' => 2 ] ], $out['remaining'] );
	}

	/** A JSON-array string is accepted (e.g. an upstream API returned raw JSON). */
	public function test_accepts_a_json_array_string() {
		$node = [ 'data' => [ 'config' => [ 'source' => '[{"a":1},{"a":2}]' ] ] ];

		$out = Iterator::execute_node( $node, [] );

		$this->assertSame( 'loop', $out['port'] );
		$this->assertSame( [ 'a' => 1 ], $out['data']['item'] );
	}

	/** Legacy behaviour: a bare key naming an array in the run context still works. */
	public function test_accepts_a_context_key_referencing_an_array() {
		$node = [ 'data' => [ 'config' => [ 'source' => 'rows' ] ] ];

		$out = Iterator::execute_node( $node, [ 'rows' => [ 'x', 'y' ] ] );

		$this->assertSame( 'loop', $out['port'] );
		$this->assertSame( 'x', $out['data']['item'] );
	}

	/** An unresolvable source produces no items and routes to `done`. */
	public function test_unknown_source_routes_to_done() {
		$node = [ 'data' => [ 'config' => [ 'source' => 'missing' ] ] ];

		$out = Iterator::execute_node( $node, [] );

		$this->assertSame( 'done', $out['port'] );
	}
}
