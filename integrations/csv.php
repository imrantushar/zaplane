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
				[
					'key'     => 'destination',
					'label'   => 'Output as',
					'type'    => 'select',
					'default' => 'text',
					'options' => [
						[ 'value' => 'text', 'label' => 'CSV text (use the value in later steps)' ],
						[ 'value' => 'file', 'label' => 'Save as a file in the Media Library (returns a file URL)' ],
					],
					'help'    => 'Choose “Save as a file” to store the CSV in the Media Library and get a file URL / path you can attach to an email or link to.',
				],
				[
					'key'         => 'filename',
					'label'       => 'File name',
					'type'        => 'expression',
					'default'     => 'export.csv',
					'placeholder' => 'export.csv',
					'depends_on'  => [ 'destination' => 'file' ],
					'help'        => 'Name of the generated file. A “.csv” extension is added automatically if missing.',
				],
			];
		}//end if

		return [
			[
				'key'     => 'source',
				'label'   => 'CSV Source',
				'type'    => 'select',
				'default' => 'text',
				'options' => [
					[ 'value' => 'text', 'label' => 'Paste / map CSV text' ],
					[ 'value' => 'file', 'label' => 'Upload or select a file' ],
				],
			],
			[
				'key'        => 'csv',
				'label'      => 'CSV text',
				'type'       => 'textarea',
				'required'   => false,
				'depends_on' => [ 'source' => 'text' ],
			],
			[
				'key'        => 'file_url',
				'label'      => 'CSV file',
				'type'       => 'file',
				'required'   => false,
				'depends_on' => [ 'source' => 'file' ],
				'help'       => 'Upload a .csv or pick one from the Media Library. You can also pass a file URL from an earlier step with @.',
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
			return [
				'csv'           => "name,age\nAlice,30",
				'file_url'      => 'https://example.com/wp-content/uploads/2026/07/export.csv',
				'file_path'     => '/var/www/html/wp-content/uploads/2026/07/export.csv',
				'filename'      => 'export.csv',
				'attachment_id' => 123,
			];
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
		$text = 'file' === ( $config['source'] ?? 'text' )
			? self::fetch_file_contents( (string) ( $config['file_url'] ?? '' ) )
			: (string) ( $config['csv'] ?? '' );

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
			fputcsv( $fh, array_keys( $first ), $delimiter, '"', '\\' );
		}
		foreach ( $items as $item ) {
			$row = is_array( $item ) ? array_values( $item ) : [ $item ];
			fputcsv( $fh, $row, $delimiter, '"', '\\' );
		}

		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose -- In-memory stream, not filesystem.

		if ( 'file' !== ( $config['destination'] ?? 'text' ) ) {
			return [ 'csv' => $csv ];
		}

		return array_merge( [ 'csv' => $csv ], self::save_file( $csv, $config ) );
	}

	/**
	 * Write the CSV to the uploads directory, register it in the Media Library,
	 * and return the location so later steps can attach or link to it.
	 *
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	protected static function save_file( string $csv, array $config ): array {
		$filename = self::filename( $config['filename'] ?? 'export.csv' );

		$upload = wp_upload_bits( $filename, null, $csv );
		if ( ! empty( $upload['error'] ) ) {
			return [ 'file_error' => (string) $upload['error'] ];
		}

		$attachment_id = 0;
		if ( function_exists( 'wp_insert_attachment' ) ) {
			$attachment_id = (int) wp_insert_attachment(
				[
					'post_mime_type' => 'text/csv',
					'post_title'     => preg_replace( '/\.csv$/i', '', $filename ),
					'post_content'   => '',
					'post_status'    => 'inherit',
				],
				$upload['file']
			);
		}

		return [
			'file_url'      => $upload['url'],
			'file_path'     => $upload['file'],
			'filename'      => $filename,
			'attachment_id' => $attachment_id,
		];
	}

	/**
	 * Sanitise the requested file name and guarantee a .csv extension.
	 *
	 * @param mixed $name
	 */
	protected static function filename( $name ): string {
		$name = sanitize_file_name( (string) $name );
		if ( '' === $name ) {
			$name = 'export.csv';
		}
		if ( ! preg_match( '/\.csv$/i', $name ) ) {
			$name .= '.csv';
		}
		return $name;
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
		while ( ( $row = fgetcsv( $fh, 0, $delimiter, '"', '\\' ) ) !== false ) {
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

	/**
	 * Read CSV contents from a Media Library file, an attachment ID, a local
	 * uploads URL, or a remote URL — whatever the file field resolved to.
	 */
	protected static function fetch_file_contents( string $location ): string {
		$location = trim( $location );
		if ( '' === $location ) {
			return '';
		}

		// Attachment ID.
		if ( ctype_digit( $location ) ) {
			$path = get_attached_file( (int) $location );
			return ( $path && is_readable( $path ) )
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local uploads file.
				? (string) file_get_contents( $path )
				: '';
		}

		// Local uploads URL — read straight from disk, no HTTP round trip.
		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['baseurl'] ) && ! empty( $uploads['basedir'] ) && 0 === strpos( $location, $uploads['baseurl'] ) ) {
			$path = $uploads['basedir'] . substr( $location, strlen( $uploads['baseurl'] ) );
			if ( is_readable( $path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local uploads file.
				return (string) file_get_contents( $path );
			}
		}

		// Remote URL.
		if ( preg_match( '#^https?://#i', $location ) ) {
			$response = wp_remote_get( $location, [ 'timeout' => 20 ] );
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				return (string) wp_remote_retrieve_body( $response );
			}
		}

		return '';
	}
}
