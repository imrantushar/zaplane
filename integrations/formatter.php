<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

/**
 * Formatter — data-transform utilities (text, number, date), the way Zapier's
 * Formatter / n8n's Edit Fields work. Pure PHP, no connection.
 */
class Formatter extends IntegrationBase {

	public static function get_slug(): string {
		return 'formatter';
	}

	public static function get_name(): string {
		return 'Formatter';
	}

	public static function get_icon(): string {
		return 'formatter.svg';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_triggers(): array {
		return [];
	}

	public static function requires_connection(): bool {
		return false;
	}

	public static function get_actions(): array {
		return [
			'format_text'   => [ 'label' => 'Format Text' ],
			'format_number' => [ 'label' => 'Format Number' ],
			'format_date'   => [ 'label' => 'Format Date' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		switch ( $action ) {
			case 'format_text':
				return [
					[ 'key' => 'input', 'label' => 'Input', 'type' => 'expression', 'required' => true ],
					[
						'key'      => 'operation',
						'label'    => 'Operation',
						'type'     => 'select',
						'required' => true,
						'default'  => 'uppercase',
						'options'  => [
							[ 'value' => 'uppercase', 'label' => 'Uppercase' ],
							[ 'value' => 'lowercase', 'label' => 'Lowercase' ],
							[ 'value' => 'capitalize', 'label' => 'Capitalize first' ],
							[ 'value' => 'title', 'label' => 'Title Case' ],
							[ 'value' => 'trim', 'label' => 'Trim whitespace' ],
							[ 'value' => 'replace', 'label' => 'Find & Replace' ],
							[ 'value' => 'truncate', 'label' => 'Truncate' ],
							[ 'value' => 'slug', 'label' => 'Slugify' ],
							[ 'value' => 'length', 'label' => 'Length' ],
						],
					],
					[ 'key' => 'find', 'label' => 'Find', 'type' => 'expression', 'required' => false, 'depends_on' => [ 'operation' => 'replace' ] ],
					[ 'key' => 'replace', 'label' => 'Replace with', 'type' => 'expression', 'required' => false, 'depends_on' => [ 'operation' => 'replace' ] ],
					[ 'key' => 'length', 'label' => 'Max length', 'type' => 'number', 'required' => false, 'depends_on' => [ 'operation' => 'truncate' ] ],
				];

			case 'format_number':
				return [
					[ 'key' => 'input', 'label' => 'Input', 'type' => 'expression', 'required' => true ],
					[
						'key'      => 'operation',
						'label'    => 'Operation',
						'type'     => 'select',
						'required' => true,
						'default'  => 'round',
						'options'  => [
							[ 'value' => 'round', 'label' => 'Round' ],
							[ 'value' => 'ceil', 'label' => 'Round up' ],
							[ 'value' => 'floor', 'label' => 'Round down' ],
							[ 'value' => 'format', 'label' => 'Format (decimals + thousands)' ],
							[ 'value' => 'abs', 'label' => 'Absolute value' ],
						],
					],
					[ 'key' => 'decimals', 'label' => 'Decimals', 'type' => 'number', 'required' => false, 'default' => 2 ],
				];

			case 'format_date':
				return [
					[ 'key' => 'input', 'label' => 'Input date (blank = now)', 'type' => 'expression', 'required' => false ],
					[ 'key' => 'format', 'label' => 'Output format', 'type' => 'expression', 'required' => true, 'default' => 'Y-m-d H:i:s', 'help' => 'PHP date format, e.g. Y-m-d, "F j, Y", H:i' ],
				];
		}

		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		$event  = $node['data']['event'] ?? '';
		$config = $node['data']['config'] ?? [];

		switch ( $event ) {
			case 'format_text':
				$result = self::do_text( $config );
				break;
			case 'format_number':
				$result = self::do_number( $config );
				break;
			case 'format_date':
				$result = self::do_date( $config );
				break;
			default:
				return [ 'port' => 'main', 'data' => $input ];
		}

		return [ 'port' => 'main', 'data' => array_merge( $input, [ 'result' => $result ] ) ];
	}

	private static function do_text( array $c ) {
		$in = (string) ( $c['input'] ?? '' );
		switch ( $c['operation'] ?? 'uppercase' ) {
			case 'lowercase':
				return function_exists( 'mb_strtolower' ) ? mb_strtolower( $in ) : strtolower( $in );
			case 'capitalize':
				return ucfirst( $in );
			case 'title':
				return function_exists( 'mb_convert_case' ) ? mb_convert_case( $in, MB_CASE_TITLE ) : ucwords( $in );
			case 'trim':
				return trim( $in );
			case 'replace':
				return str_replace( (string) ( $c['find'] ?? '' ), (string) ( $c['replace'] ?? '' ), $in );
			case 'truncate':
				$len = (int) ( $c['length'] ?? 100 );
				return ( strlen( $in ) > $len ) ? ( substr( $in, 0, $len ) . '…' ) : $in;
			case 'slug':
				return sanitize_title( $in );
			case 'length':
				return function_exists( 'mb_strlen' ) ? mb_strlen( $in ) : strlen( $in );
			case 'uppercase':
			default:
				return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $in ) : strtoupper( $in );
		}
	}

	private static function do_number( array $c ) {
		$n        = (float) ( $c['input'] ?? 0 );
		$decimals = (int) ( $c['decimals'] ?? 2 );
		switch ( $c['operation'] ?? 'round' ) {
			case 'ceil':
				return (int) ceil( $n );
			case 'floor':
				return (int) floor( $n );
			case 'abs':
				return abs( $n );
			case 'format':
				return number_format( $n, $decimals );
			case 'round':
			default:
				return round( $n, $decimals );
		}
	}

	private static function do_date( array $c ) {
		$in     = trim( (string) ( $c['input'] ?? '' ) );
		$format = (string) ( $c['format'] ?? 'Y-m-d H:i:s' );
		$ts     = ( '' === $in ) ? time() : strtotime( $in );
		if ( false === $ts ) {
			return '';
		}
		return wp_date( $format, $ts );
	}
}
