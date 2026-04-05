<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Variable;
use Zaplane\Tests\TestCase;

class VariableTest extends TestCase {

	public function test_variable_sets_value() {
		$node = [
			'data' => [
				'config' => [
					'name' => 'test_var',
					'value' => 'hello world'
				]
			]
		];

		$input = [
			'existing_data' => 123
		];

		$result = Variable::execute_node( $node, $input );

		$this->assertArrayHasKey( 'port', $result );
		$this->assertEquals( 'main', $result['port'] );

		$this->assertArrayHasKey( 'data', $result );
		$this->assertArrayHasKey( 'test_var', $result['data'] );
		$this->assertEquals( 'hello world', $result['data']['test_var'] );
		
		// Input should be preserved
		$this->assertArrayHasKey( 'existing_data', $result['data'] );
		$this->assertEquals( 123, $result['data']['existing_data'] );
	}

	public function test_variable_empty_name() {
		$node = [
			'data' => [
				'config' => [
					'name' => '',
					'value' => 'hello world'
				]
			]
		];

		$input = [
			'existing_data' => 123
		];

		$result = Variable::execute_node( $node, $input );

		$this->assertArrayHasKey( 'data', $result );
		$this->assertEquals( $input, $result['data'] );
	}
}
