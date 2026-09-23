<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Formatter;

/**
 * Formatter: text, number, list and date transforms on the step's input.
 */
class FormatterTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Formatter::class;
	}

	/** @param array<string,mixed> $config */
	private function run_op( string $event, array $config ) {
		$out = Formatter::execute_node( $this->makeActionNode( $event, $config ), [ 'keep' => 1 ] );
		$this->assertSame( 'main', $out['port'] );
		$this->assertSame( 1, $out['data']['keep'], 'the step keeps the data it was given' );
		return $out['data']['result'];
	}

	public function test_text_case_changes(): void {
		$this->assertSame( 'HELLO', $this->run_op( 'format_text', [ 'input' => 'hello', 'operation' => 'uppercase' ] ) );
		$this->assertSame( 'orderTotalAmount', $this->run_op( 'format_text', [ 'input' => 'order_total amount', 'operation' => 'camel_case' ] ) );
		$this->assertSame( 'order_total_amount', $this->run_op( 'format_text', [ 'input' => 'orderTotal amount', 'operation' => 'snake_case' ] ) );
		$this->assertSame( 'order-total', $this->run_op( 'format_text', [ 'input' => 'Order Total', 'operation' => 'kebab_case' ] ) );
	}

	public function test_truncate_and_substring_count_characters_not_bytes(): void {
		$this->assertSame( 'আমার…', $this->run_op( 'format_text', [ 'input' => 'আমার সোনার', 'operation' => 'truncate', 'length' => 4 ] ) );
		$this->assertSame( 'ñé', $this->run_op( 'format_text', [ 'input' => 'añéz', 'operation' => 'substring', 'start' => 1, 'length' => 2 ] ) );
		$this->assertSame( 4, $this->run_op( 'format_text', [ 'input' => 'añéz', 'operation' => 'length' ] ) );
		$this->assertSame( 'short', $this->run_op( 'format_text', [ 'input' => 'short', 'operation' => 'truncate', 'length' => 10 ] ) );
	}

	public function test_repeat_and_pad_are_capped(): void {
		$this->assertSame( 'abab', $this->run_op( 'format_text', [ 'input' => 'ab', 'operation' => 'repeat', 'times' => 2 ] ) );
		$this->assertLessThanOrEqual( 1048576, strlen( $this->run_op( 'format_text', [ 'input' => 'ab', 'operation' => 'repeat', 'times' => 1000000000 ] ) ) );
		$this->assertLessThanOrEqual( 1048576, strlen( $this->run_op( 'format_text', [ 'input' => 'x', 'operation' => 'pad', 'length' => 1000000000 ] ) ) );
		$this->assertSame( '007', $this->run_op( 'format_text', [ 'input' => '7', 'operation' => 'pad', 'length' => 3, 'pad_char' => '0' ] ) );
	}

	public function test_regex_steps_survive_a_bad_pattern(): void {
		$this->assertSame( 'a-b', $this->run_op( 'format_text', [ 'input' => 'a b', 'operation' => 'regex_replace', 'pattern' => '/\s+/', 'replace' => '-' ] ) );
		$this->assertSame( 'a b', $this->run_op( 'format_text', [ 'input' => 'a b', 'operation' => 'regex_replace', 'pattern' => '/(unclosed', 'replace' => '-' ] ) );
		$this->assertSame( '1234', $this->run_op( 'format_text', [ 'input' => 'Order #1234 placed', 'operation' => 'extract', 'pattern' => '/#(\d+)/' ] ) );
		$this->assertSame( '', $this->run_op( 'format_text', [ 'input' => 'x', 'operation' => 'extract', 'pattern' => '/(bad' ] ) );
	}

	public function test_hashes_and_encodings(): void {
		$this->assertSame( hash_hmac( 'sha256', 'body', 'k' ), $this->run_op( 'format_text', [ 'input' => 'body', 'operation' => 'hash', 'algo' => 'hmac_sha256', 'hmac_key' => 'k' ] ) );
		$this->assertSame( 'a%20b', $this->run_op( 'format_text', [ 'input' => 'a b', 'operation' => 'url_encode' ] ) );
		$this->assertSame( 'fallback', $this->run_op( 'format_text', [ 'input' => '  ', 'operation' => 'default', 'default_value' => 'fallback' ] ) );
	}

	public function test_numbers(): void {
		$this->assertSame( '$1,234.50', $this->run_op( 'format_number', [ 'input' => '1234.5', 'operation' => 'currency' ] ) );
		$this->assertSame( 10.0, $this->run_op( 'format_number', [ 'input' => '42', 'operation' => 'clamp', 'min_value' => '0', 'max_value' => '10' ] ) );
		$this->assertSame( 25.0, $this->run_op( 'format_number', [ 'input' => '5', 'operation' => 'percentage', 'total' => '20' ] ) );
		$this->assertSame( 0, $this->run_op( 'format_number', [ 'input' => '5', 'operation' => 'percentage', 'total' => '0' ] ) );
	}

	public function test_math_follows_precedence_and_never_divides_by_zero(): void {
		$this->assertSame( 14, $this->run_op( 'format_number', [ 'operation' => 'math', 'expression' => '2 + 3 * 4' ] ) );
		$this->assertSame( 20, $this->run_op( 'format_number', [ 'operation' => 'math', 'expression' => '(2 + 3) * 4' ] ) );
		$this->assertSame( -2, $this->run_op( 'format_number', [ 'operation' => 'math', 'expression' => '-(1 + 1)' ] ) );
		$this->assertSame( 0, $this->run_op( 'format_number', [ 'operation' => 'math', 'expression' => '5 / 0' ] ) );
		// An earlier zero result feeding a divisor used to reach a real division.
		$this->assertSame( 0, $this->run_op( 'format_number', [ 'operation' => 'math', 'expression' => '5 / (1 / 0)' ] ) );
		$this->assertSame( 0, $this->run_op( 'format_number', [ 'operation' => 'math', 'expression' => '5 % (3 % 0)' ] ) );
		$this->assertSame( 0, $this->run_op( 'format_number', [ 'operation' => 'math', 'expression' => 'system("id")' ] ) );
	}

	public function test_lists_accept_arrays_json_and_comma_text(): void {
		$this->assertSame( 'a, b', $this->run_op( 'format_list', [ 'input' => [ 'a', 'b' ], 'operation' => 'join' ] ) );
		$this->assertSame( 3, $this->run_op( 'format_list', [ 'input' => '[1,2,3]', 'operation' => 'count' ] ) );
		$this->assertSame( [ 'x', 'y' ], $this->run_op( 'format_list', [ 'input' => 'x, y, x', 'operation' => 'unique' ] ) );
		$this->assertSame( [ 'Jane', 'Joe' ], $this->run_op( 'format_list', [ 'input' => [ [ 'name' => 'Jane' ], [ 'name' => 'Joe' ] ], 'operation' => 'pluck', 'field' => 'name' ] ) );
		$this->assertEquals( 2, $this->run_op( 'format_list', [ 'input' => '1,2,3,x', 'operation' => 'average' ] ) );
		$this->assertSame( [ 3, 2, 1 ], $this->run_op( 'format_list', [ 'input' => [ 2, 3, 1 ], 'operation' => 'sort', 'direction' => 'desc' ] ) );
	}

	public function test_dates(): void {
		$this->assertSame( '2026-09-22', $this->run_op( 'format_date', [ 'input' => '2026-09-22 10:00:00', 'format' => 'Y-m-d' ] ) );
		$this->assertSame( '', $this->run_op( 'format_date', [ 'input' => 'not a date at all', 'format' => 'Y-m-d' ] ) );
	}

	public function test_unknown_action_passes_the_input_through(): void {
		$out = Formatter::execute_node( $this->makeActionNode( 'nope', [] ), [ 'a' => 1 ] );
		$this->assertSame( [ 'a' => 1 ], $out['data'] );
	}
}
