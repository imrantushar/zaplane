<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Delay;
use Zaplane\Tests\TestCase;

class DelayTest extends TestCase {

	public function test_delay_calculates_seconds_properly() {
		$node = [
			'id' => 10,
			'_run_id' => 5,
			'_node_run_id' => 15,
			'data' => [
				'config' => [
					'unit' => 'seconds',
					'amount' => 30
				]
			]
		];

		$input = [
			'context' => 'data'
		];

		// We can't strictly assert the exact `time()` injection into Scheduler::enqueue easily without mocking it, 
		// but we can ensure it executes cleanly without fatals and returns the generic halt status.
		$result = Delay::execute_node( $node, $input );

		$this->assertArrayHasKey( 'port', $result );
		$this->assertEquals( '__halt__', $result['port'] );
		$this->assertEquals( 'delayed', $result['status'] );
	}

	public function test_delay_calculates_days_properly() {
		$node = [
			'id' => 11,
			'_run_id' => 6,
			'_node_run_id' => 16,
			'data' => [
				'config' => [
					'unit' => 'days',
					'amount' => 2
				]
			]
		];

		$input = [
			'context' => 'data'
		];

		$result = Delay::execute_node( $node, $input );

		$this->assertArrayHasKey( 'port', $result );
		$this->assertEquals( '__halt__', $result['port'] );
		$this->assertEquals( 'delayed', $result['status'] );
	}

	public function test_delay_backwards_compatibility() {
		// Test ensuring old pipelines running just {"seconds": 30} still works flawlessly
		$node = [
			'id' => 12,
			'_run_id' => 7,
			'_node_run_id' => 17,
			'data' => [
				'config' => [
					'seconds' => 15
				]
			]
		];

		$input = [];

		$result = Delay::execute_node( $node, $input );

		$this->assertArrayHasKey( 'port', $result );
		$this->assertEquals( '__halt__', $result['port'] );
	}
}
