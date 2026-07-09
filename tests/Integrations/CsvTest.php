<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Csv;

/**
 * Build CSV now offers an output destination: plain text (default) or a saved
 * Media Library file. Parsing and the text path are unchanged.
 */
class CsvTest extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return Csv::class;
	}

	protected function getActionTests(): array {
		return [
			'parse' => [ 'csv' => "name,age\nAlice,30" ],
			'build' => [ 'items' => [ [ 'name' => 'Alice', 'age' => 30 ] ] ],
		];
	}

	/** The build schema exposes the destination + filename controls. */
	public function test_build_schema_has_destination_and_filename() {
		$keys = array_column( Csv::get_action_config_schema( 'build' ), 'key' );

		$this->assertContains( 'destination', $keys );
		$this->assertContains( 'filename', $keys );
	}

	/** filename only shows when destination is "file". */
	public function test_filename_depends_on_file_destination() {
		$schema   = Csv::get_action_config_schema( 'build' );
		$filename = null;
		foreach ( $schema as $field ) {
			if ( 'filename' === $field['key'] ) {
				$filename = $field;
			}
		}

		$this->assertNotNull( $filename );
		$this->assertSame( [ 'destination' => 'file' ], $filename['depends_on'] );
	}

	/** Default (text) destination returns just the CSV string — no file side effects. */
	public function test_build_text_destination_returns_csv_only() {
		$node = [
			'data' => [
				'event'  => 'build',
				'config' => [ 'items' => [ [ 'name' => 'Alice', 'age' => 30 ], [ 'name' => 'Bob', 'age' => 25 ] ] ],
			],
		];

		$out = Csv::execute_node( $node, [] );

		$this->assertSame( 'main', $out['port'] );
		$this->assertSame( "name,age\nAlice,30\nBob,25\n", $out['data']['csv'] );
		$this->assertArrayNotHasKey( 'file_url', $out['data'] );
	}

	/** Parse now offers a source toggle plus an upload/file field. */
	public function test_parse_schema_has_source_and_file_fields() {
		$schema = Csv::get_action_config_schema( 'parse' );
		$byKey  = [];
		foreach ( $schema as $field ) {
			$byKey[ $field['key'] ] = $field;
		}

		$this->assertArrayHasKey( 'source', $byKey );
		$this->assertArrayHasKey( 'file_url', $byKey );
		$this->assertSame( 'file', $byKey['file_url']['type'] );
		$this->assertSame( [ 'source' => 'file' ], $byKey['file_url']['depends_on'] );
		$this->assertSame( [ 'source' => 'text' ], $byKey['csv']['depends_on'] );
	}

	/** File source with no location resolves to an empty result (no fatals). */
	public function test_file_source_without_location_yields_no_rows() {
		$node = [
			'data' => [
				'event'  => 'parse',
				'config' => [ 'source' => 'file', 'file_url' => '' ],
			],
		];

		$out = Csv::execute_node( $node, [] );

		$this->assertSame( 0, $out['data']['count'] );
		$this->assertSame( [], $out['data']['rows'] );
	}

	/** Parsing a header CSV yields keyed rows. */
	public function test_parse_maps_header_to_rows() {
		$node = [
			'data' => [
				'event'  => 'parse',
				'config' => [ 'csv' => "name,age\nAlice,30" ],
			],
		];

		$out = Csv::execute_node( $node, [] );

		$this->assertSame( [ [ 'name' => 'Alice', 'age' => '30' ] ], $out['data']['rows'] );
		$this->assertSame( 1, $out['data']['count'] );
	}
}
