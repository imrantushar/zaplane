<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Csv extends IntegrationBase {

	public static function get_slug(): string {
		return 'csv';
	}

	public static function get_name(): string {
		return 'CSV';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_icon(): string {
		return 'csv.svg';
	}

	public static function get_actions(): array {
		return [
			'parse' => [ 'label' => 'Parse CSV' ],
			'build' => [ 'label' => 'Build CSV' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		if ( 'build' === $action ) {
			return [
				[
					'key' => 'items',
					'label' => 'Rows (array of objects)',
					'type' => 'textarea',
					'required' => true
				],
				[
					'key' => 'delimiter',
					'label' => 'Delimiter',
					'type' => 'text',
					'default' => ','
				],
				[
					'key' => 'include_header',
					'label' => 'Include header row',
					'type' => 'checkbox',
					'default' => true
				],
			];
		}//end if

		return [
			[
				'key' => 'csv',
				'label' => 'CSV text',
				'type' => 'textarea',
				'required' => true
			],
			[
				'key' => 'delimiter',
				'label' => 'Delimiter',
				'type' => 'text',
				'default' => ','
			],
			[
				'key' => 'has_header',
				'label' => 'First row is a header',
				'type' => 'checkbox',
				'default' => true
			],
		];
	}

	public static function get_action_sample_output( string $action ): array {
		if ( 'build' === $action ) {
			return [ 'csv' => "name,age\nAlice,30" ];
		}
		return [
			'rows'  => [ [ 'column1' => 'value1', 'column2' => 'value2' ] ],
			'count' => 2,
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$action = $node['data']['event'] ?? 'parse';
		$config = $node['data']['config'] ?? [];

		$data = 'build' === $action ? self::build( $config ) : self::parse( $config );

		return [
			'port' => 'main',
			'data' => $data
		];
	}

	protected static function parse( array $config ): array {
		$text       = (string) ( $config['csv'] ?? '' );
		$delimiter  = self::delimiter( $config );
		$has_header = ! isset( $config['has_header'] ) || ! empty( $config['has_header'] );

		$lines = self::read_rows( $text, $delimiter );
		if ( empty( $lines ) ) {
			return [
				'rows' => [],
				'count' => 0
			];
		}

		if ( ! $has_header ) {
			return [
				'rows' => $lines,
				'count' => count( $lines )
			];
		}

		$header = array_map( 'strval', array_shift( $lines ) );
		$rows   = [];
		foreach ( $lines as $line ) {
			$row = [];
			foreach ( $header as $i => $key ) {
				$row[ $key ] = $line[ $i ] ?? '';
			}
			$rows[] = $row;
		}

		return [
			'rows' => $rows,
			'count' => count( $rows )
		];
	}

	protected static function build( array $config ): array {
		$items     = $config['items'] ?? [];
		$delimiter = self::delimiter( $config );
		$header    = ! isset( $config['include_header'] ) || ! empty( $config['include_header'] );

		if ( is_string( $items ) ) {
			$decoded = json_decode( $items, true );
			$items   = is_array( $decoded ) ? $decoded : [];
		}
		if ( ! is_array( $items ) ) {
			$items = [];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen -- In-memory stream, not filesystem.
		$fh = fopen( 'php://temp', 'r+' );

		$first = reset( $items );
		if ( $header && is_array( $first ) ) {
			fputcsv( $fh, array_keys( $first ), $delimiter );
		}
		foreach ( $items as $item ) {
			$row = is_array( $item ) ? array_values( $item ) : [ $item ];
			fputcsv( $fh, $row, $delimiter );
		}

		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose -- In-memory stream, not filesystem.

		return [ 'csv' => $csv ];
	}

	protected static function read_rows( string $text, string $delimiter ): array {
		if ( '' === trim( $text ) ) {
			return [];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen -- In-memory stream, not filesystem.
		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite -- In-memory stream, not filesystem.
		rewind( $fh );

		$rows = [];
		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Standard fgetcsv iteration pattern.
		while ( ( $row = fgetcsv( $fh, 0, $delimiter ) ) !== false ) {
			if ( [ null ] === $row || ( 1 === count( $row ) && '' === (string) $row[0] ) ) {
				continue;
			}
			$rows[] = array_map( 'strval', $row );
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose -- In-memory stream, not filesystem.

		return $rows;
	}

	protected static function delimiter( array $config ): string {
		$delimiter = (string) ( $config['delimiter'] ?? ',' );
		return '' === $delimiter ? ',' : substr( $delimiter, 0, 1 );
	}
}
