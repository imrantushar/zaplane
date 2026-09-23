<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\JsonParser;

/**
 * JSON Parser: text → data, data → text.
 */
class JsonParserTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return JsonParser::class;
	}

	public function test_parse_returns_the_data(): void {
		$out = JsonParser::execute_node( $this->makeActionNode( 'parse', [ 'json' => '{"order":{"id":7,"items":[1,2]}}' ] ), [] );
		$this->assertTrue( $out['data']['success'] );
		$this->assertSame( [ 'order' => [ 'id' => 7, 'items' => [ 1, 2 ] ] ], $out['data']['data'] );
	}

	public function test_invalid_json_is_a_failed_result_with_the_reason(): void {
		$out = JsonParser::execute_node( $this->makeActionNode( 'parse', [ 'json' => '{"broken":' ] ), [] );
		$this->assertFalse( $out['data']['success'] );
		$this->assertNull( $out['data']['data'] );
		$this->assertNotEmpty( $out['data']['error'] );
	}

	public function test_stringify_decodes_json_text_first_and_can_pretty_print(): void {
		$out = JsonParser::execute_node( $this->makeActionNode( 'stringify', [ 'data' => '{"a":1}' ] ), [] );
		$this->assertSame( '{"a":1}', $out['data']['json'] );

		$out = JsonParser::execute_node( $this->makeActionNode( 'stringify', [ 'data' => [ 'a' => 1 ], 'pretty' => true ] ), [] );
		$this->assertStringContainsString( "\n", $out['data']['json'] );

		$out = JsonParser::execute_node( $this->makeActionNode( 'stringify', [ 'data' => 'plain words' ] ), [] );
		$this->assertSame( '"plain words"', $out['data']['json'] );
	}
}
