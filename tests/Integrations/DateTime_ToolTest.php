<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\DateTime_Tool;

/**
 * The Date & Time tool: format, add/subtract, difference, parse, boundaries.
 * Inputs are fixed dates in UTC, so results don't depend on the clock.
 */
class DateTime_ToolTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return DateTime_Tool::class;
	}

	/** @return array<string,mixed> */
	private function run_action( string $event, array $config ): array {
		return DateTime_Tool::execute_node( $this->makeActionNode( $event, $config + [ 'timezone' => 'UTC' ] ), [] )['data'];
	}

	public function test_format_uses_the_given_format(): void {
		$out = $this->run_action( 'format', [ 'input' => '2026-03-05 14:07:00', 'format' => 'd/m/Y H:i' ] );
		$this->assertSame( '05/03/2026 14:07', $out['formatted'] );
		$this->assertSame( '2026-03-05T14:07:00+00:00', $out['iso8601'] );
	}

	public function test_input_format_reads_non_standard_dates(): void {
		$out = $this->run_action( 'format', [ 'input' => '05/03/2026', 'input_format' => 'd/m/Y', 'format' => 'Y-m-d' ] );
		$this->assertSame( '2026-03-05', $out['formatted'] );
	}

	public function test_modify_adds_and_subtracts(): void {
		$add = $this->run_action( 'modify', [ 'input' => '2026-01-31 10:00:00', 'amount' => 2, 'unit' => 'days', 'format' => 'Y-m-d' ] );
		$sub = $this->run_action( 'modify', [ 'input' => '2026-01-31 10:00:00', 'amount' => 3, 'unit' => 'hours', 'operation' => 'subtract', 'format' => 'H:i' ] );
		$this->assertSame( '2026-02-02', $add['formatted'] );
		$this->assertSame( '07:00', $sub['formatted'] );
	}

	public function test_modify_ignores_an_unknown_unit(): void {
		$out = $this->run_action( 'modify', [ 'input' => '2026-01-01', 'amount' => 1, 'unit' => 'fortnights', 'format' => 'Y-m-d' ] );
		$this->assertSame( '2026-01-02', $out['formatted'] ); // Falls back to days.
	}

	public function test_diff_in_days_and_hours(): void {
		$days  = $this->run_action( 'diff', [ 'start' => '2026-01-01', 'end' => '2026-01-11', 'unit' => 'days' ] );
		$hours = $this->run_action( 'diff', [ 'start' => '2026-01-01 00:00', 'end' => '2026-01-01 06:30', 'unit' => 'hours' ] );
		$this->assertEquals( 10, $days['difference'] );
		$this->assertEquals( 6.5, $hours['difference'] );
		$this->assertSame( 23400, $hours['seconds'] );
	}

	public function test_parse_extracts_parts(): void {
		$out = $this->run_action( 'parse', [ 'input' => '2026-08-14 17:05:09' ] );
		$this->assertSame( 2026, $out['year'] );
		$this->assertSame( 8, $out['month'] );
		$this->assertSame( 'August', $out['month_name'] );
		$this->assertSame( 14, $out['day'] );
		$this->assertSame( 17, $out['hour'] );
		$this->assertSame( 'Friday', $out['weekday'] );
		$this->assertSame( 3, $out['quarter'] );
		$this->assertSame( 'PM', $out['am_pm'] );
	}

	public function test_boundaries_of_week_month_and_year(): void {
		$week  = $this->run_action( 'boundary', [ 'input' => '2026-08-14 12:00', 'unit' => 'week', 'boundary' => 'start', 'format' => 'Y-m-d H:i:s' ] );
		$month = $this->run_action( 'boundary', [ 'input' => '2026-02-10', 'unit' => 'month', 'boundary' => 'end', 'format' => 'Y-m-d H:i:s' ] );
		$year  = $this->run_action( 'boundary', [ 'input' => '2026-06-01', 'unit' => 'year', 'boundary' => 'end', 'format' => 'Y-m-d' ] );
		$this->assertSame( '2026-08-10 00:00:00', $week['formatted'] ); // Monday.
		$this->assertSame( '2026-02-28 23:59:59', $month['formatted'] );
		$this->assertSame( '2026-12-31', $year['formatted'] );
	}

	public function test_timezone_converts_the_time(): void {
		$out = DateTime_Tool::execute_node( $this->makeActionNode( 'format', [ 'input' => '2026-01-01 00:00:00 UTC', 'timezone' => 'Asia/Dhaka', 'format' => 'Y-m-d H:i' ] ), [] )['data'];
		$this->assertSame( '2026-01-01 06:00', $out['formatted'] );
	}
}
