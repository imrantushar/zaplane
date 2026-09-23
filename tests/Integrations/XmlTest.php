<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Xml;

/**
 * XML: parse text into data, build text from data.
 */
class XmlTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Xml::class;
	}

	public function test_parse_turns_xml_into_arrays(): void {
		$out = Xml::execute_node( $this->makeActionNode( 'parse', [ 'xml' => '<order><id>7</id><note><![CDATA[Leave at door]]></note></order>' ] ), [] );
		$this->assertTrue( $out['data']['success'] );
		$this->assertSame( '7', $out['data']['data']['id'] );
		$this->assertSame( 'Leave at door', $out['data']['data']['note'] );
	}

	public function test_empty_or_invalid_xml_fails_without_warnings(): void {
		$empty = Xml::execute_node( $this->makeActionNode( 'parse', [ 'xml' => '  ' ] ), [] );
		$this->assertFalse( $empty['data']['success'] );
		$bad = Xml::execute_node( $this->makeActionNode( 'parse', [ 'xml' => '<a><b></a>' ] ), [] );
		$this->assertFalse( $bad['data']['success'] );
		$this->assertSame( 'Invalid XML.', $bad['data']['error'] );
	}

	public function test_external_entities_are_not_expanded(): void {
		$secret = tempnam( sys_get_temp_dir(), 'zxml' );
		file_put_contents( $secret, 'TOP-SECRET' );
		$xml = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY x SYSTEM "file://' . $secret . '">]><r><v>&x;</v></r>';
		$out = Xml::execute_node( $this->makeActionNode( 'parse', [ 'xml' => $xml ] ), [] );
		unlink( $secret );
		$this->assertStringNotContainsString( 'TOP-SECRET', (string) wp_json_encode( $out['data'] ) );
	}

	public function test_build_makes_safe_tags_and_escapes_values(): void {
		$out = Xml::execute_node( $this->makeActionNode( 'build', [ 'root' => 'order', 'data' => [ 'first name' => 'A & B', '2nd' => 'x', 'items' => [ 'one', 'two' ] ] ] ), [] );
		$xml = $out['data']['xml'];
		$this->assertStringContainsString( '<order>', $xml );
		$this->assertStringContainsString( '<first_name>A &amp; B</first_name>', $xml );
		$this->assertStringContainsString( '<node2nd>x</node2nd>', $xml );
		$this->assertStringContainsString( '<items><item>one</item><item>two</item></items>', $xml );
	}
}
