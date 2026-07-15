<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

/**
 * Formatter — data-transform utilities (text, number, list, date), the way
 * Zapier's Formatter / n8n's Edit Fields work. Pure PHP, no connection.
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
			'format_list'   => [ 'label' => 'Format List' ],
			'format_date'   => [ 'label' => 'Format Date' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		switch ( $action ) {
			case 'format_text':
				return self::text_schema();
			case 'format_number':
				return self::number_schema();
			case 'format_list':
				return self::list_schema();
			case 'format_date':
				return [
					[
						'key' => 'input',
						'label' => 'Input date (blank = now)',
						'type' => 'expression',
						'required' => false
					],
					[
						'key'     => 'format',
						'label'   => 'Output format',
						'type'    => 'expression',
						'required' => true,
						'default' => 'Y-m-d H:i:s',
						'help'    => 'PHP date format, e.g. Y-m-d, "F j, Y", H:i',
					],
				];
		}//end switch

		return [];
	}

	private static function text_schema(): array {
		$ops = [
			'uppercase'      => 'UPPERCASE',
			'lowercase'      => 'lowercase',
			'capitalize'     => 'Capitalize first',
			'title'          => 'Title Case',
			'start_case'     => 'Start Case (each word)',
			'camel_case'     => 'camelCase',
			'snake_case'     => 'snake_case',
			'kebab_case'     => 'kebab-case',
			'trim'           => 'Trim whitespace',
			'ltrim'          => 'Trim left',
			'rtrim'          => 'Trim right',
			'replace'        => 'Find & Replace',
			'regex_replace'  => 'Regex Replace',
			'truncate'       => 'Truncate',
			'substring'      => 'Substring',
			'pad'            => 'Pad',
			'repeat'         => 'Repeat',
			'reverse'        => 'Reverse',
			'slug'           => 'Slugify',
			'strip_html'     => 'Strip HTML tags',
			'escape_html'    => 'Escape HTML',
			'unescape_html'  => 'Unescape HTML',
			'url_encode'     => 'URL encode',
			'url_decode'     => 'URL decode',
			'base64_encode'  => 'Base64 encode',
			'base64_decode'  => 'Base64 decode',
			'hash'           => 'Hash',
			'split'          => 'Split to list',
			'extract'        => 'Extract (regex)',
			'length'         => 'Length',
			'word_count'     => 'Word count',
			'default'        => 'Default if empty',
		];

		return [
			[
				'key' => 'input',
				'label' => 'Input',
				'type' => 'expression',
				'required' => true
			],
			self::op_select( $ops, 'uppercase' ),
			[
				'key' => 'find',
				'label' => 'Find',
				'type' => 'expression',
				'depends_on' => [ 'operation' => 'replace' ]
			],
			[
				'key' => 'replace',
				'label' => 'Replace with',
				'type' => 'expression',
				'depends_on' => [ 'operation' => [ 'replace', 'regex_replace' ] ]
			],
			[
				'key' => 'pattern',
				'label' => 'Regex pattern',
				'type' => 'expression',
				'depends_on' => [ 'operation' => [ 'regex_replace', 'extract' ] ],
				'help' => 'e.g. /\\d+/ — first match is returned for Extract.'
			],
			[
				'key' => 'length',
				'label' => 'Length',
				'type' => 'number',
				'depends_on' => [ 'operation' => [ 'truncate', 'substring', 'pad' ] ]
			],
			[
				'key' => 'start',
				'label' => 'Start index',
				'type' => 'number',
				'default' => 0,
				'depends_on' => [ 'operation' => 'substring' ]
			],
			[
				'key' => 'pad_char',
				'label' => 'Pad character',
				'type' => 'text',
				'default' => ' ',
				'depends_on' => [ 'operation' => 'pad' ]
			],
			[
				'key' => 'pad_side',
				'label' => 'Pad side',
				'type' => 'select',
				'default' => 'left',
				'options' => [
					[
						'value' => 'left',
						'label' => 'Left'
					],
					[
						'value' => 'right',
						'label' => 'Right'
					],
					[
						'value' => 'both',
						'label' => 'Both'
					]
				],
				'depends_on' => [ 'operation' => 'pad' ],
			],
			[
				'key' => 'times',
				'label' => 'Times',
				'type' => 'number',
				'default' => 2,
				'depends_on' => [ 'operation' => 'repeat' ]
			],
			[
				'key' => 'separator',
				'label' => 'Separator',
				'type' => 'text',
				'default' => ',',
				'depends_on' => [ 'operation' => 'split' ]
			],
			[
				'key' => 'algo',
				'label' => 'Algorithm',
				'type' => 'select',
				'default' => 'md5',
				'options' => [
					[
						'value' => 'md5',
						'label' => 'MD5'
					],
					[
						'value' => 'sha1',
						'label' => 'SHA-1'
					],
					[
						'value' => 'sha256',
						'label' => 'SHA-256'
					],
					[
						'value' => 'hmac_sha256',
						'label' => 'HMAC-SHA256'
					],
				],
				'depends_on' => [ 'operation' => 'hash' ],
			],
			[
				'key' => 'hmac_key',
				'label' => 'HMAC secret key',
				'type' => 'expression',
				'depends_on' => [ 'operation' => 'hash' ],
				'help' => 'Used only for HMAC-SHA256.'
			],
			[
				'key' => 'default_value',
				'label' => 'Default value',
				'type' => 'expression',
				'depends_on' => [ 'operation' => 'default' ]
			],
		];
	}

	private static function number_schema(): array {
		$ops = [
			'round'      => 'Round',
			'ceil'       => 'Round up',
			'floor'      => 'Round down',
			'format'     => 'Format (decimals + thousands)',
			'abs'        => 'Absolute value',
			'math'       => 'Math expression',
			'currency'   => 'Currency',
			'clamp'      => 'Clamp (min/max)',
			'percentage' => 'Percentage of total',
		];

		return [
			[
				'key' => 'input',
				'label' => 'Input',
				'type' => 'expression',
				'required' => true,
				'help' => 'A number. For "Math expression" this is ignored — use the expression field.'
			],
			self::op_select( $ops, 'round' ),
			[
				'key' => 'decimals',
				'label' => 'Decimals',
				'type' => 'number',
				'default' => 2,
				'depends_on' => [ 'operation' => [ 'round', 'format', 'currency' ] ]
			],
			[
				'key' => 'expression',
				'label' => 'Expression',
				'type' => 'expression',
				'depends_on' => [ 'operation' => 'math' ],
				'help' => 'e.g. {{5.price}} * 1.2 + 3 — supports + - * / % and parentheses.'
			],
			[
				'key' => 'currency_symbol',
				'label' => 'Currency symbol',
				'type' => 'text',
				'default' => '$',
				'depends_on' => [ 'operation' => 'currency' ]
			],
			[
				'key' => 'min_value',
				'label' => 'Min',
				'type' => 'number',
				'depends_on' => [ 'operation' => 'clamp' ]
			],
			[
				'key' => 'max_value',
				'label' => 'Max',
				'type' => 'number',
				'depends_on' => [ 'operation' => 'clamp' ]
			],
			[
				'key' => 'total',
				'label' => 'Total',
				'type' => 'expression',
				'depends_on' => [ 'operation' => 'percentage' ],
				'help' => 'result = input / total * 100'
			],
		];
	}

	private static function list_schema(): array {
		$ops = [
			'join'    => 'Join to text',
			'count'   => 'Count',
			'unique'  => 'Unique',
			'first'   => 'First',
			'last'    => 'Last',
			'slice'   => 'Slice',
			'sort'    => 'Sort',
			'reverse' => 'Reverse',
			'compact' => 'Remove empty',
			'pluck'   => 'Pluck field',
			'sum'     => 'Sum',
			'min'     => 'Min',
			'max'     => 'Max',
			'average' => 'Average',
		];

		return [
			[
				'key' => 'input',
				'label' => 'List',
				'type' => 'expression',
				'required' => true,
				'help' => 'An array, a JSON array, or a comma-separated string.'
			],
			self::op_select( $ops, 'join' ),
			[
				'key' => 'separator',
				'label' => 'Separator',
				'type' => 'text',
				'default' => ', ',
				'depends_on' => [ 'operation' => 'join' ]
			],
			[
				'key' => 'offset',
				'label' => 'Offset',
				'type' => 'number',
				'default' => 0,
				'depends_on' => [ 'operation' => 'slice' ]
			],
			[
				'key' => 'length',
				'label' => 'Length',
				'type' => 'number',
				'depends_on' => [ 'operation' => 'slice' ]
			],
			[
				'key' => 'direction',
				'label' => 'Direction',
				'type' => 'select',
				'default' => 'asc',
				'options' => [
					[
						'value' => 'asc',
						'label' => 'Ascending'
					],
					[
						'value' => 'desc',
						'label' => 'Descending'
					]
				],
				'depends_on' => [ 'operation' => 'sort' ],
			],
			[
				'key' => 'field',
				'label' => 'Field key',
				'type' => 'text',
				'depends_on' => [ 'operation' => 'pluck' ],
				'help' => 'Extract this key from each object in the list.'
			],
		];
	}

	/**
	 * @param array<string,string> $ops
	 */
	private static function op_select( array $ops, string $default ): array {
		$options = [];
		foreach ( $ops as $value => $label ) {
			$options[] = [
				'value' => $value,
				'label' => $label
			];
		}
		return [
			'key'      => 'operation',
			'label'    => 'Operation',
			'type'     => 'select',
			'required' => true,
			'default'  => $default,
			'options'  => $options,
		];
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
			case 'format_list':
				$result = self::do_list( $config );
				break;
			case 'format_date':
				$result = self::do_date( $config );
				break;
			default:
				return [
					'port' => 'main',
					'data' => $input
				];
		}

		return [
			'port' => 'main',
			'data' => array_merge( $input, [ 'result' => $result ] ),
		];
	}

	private static function do_text( array $c ) {
		$in = (string) ( $c['input'] ?? '' );
		switch ( $c['operation'] ?? 'uppercase' ) {
			case 'lowercase':
				return function_exists( 'mb_strtolower' ) ? mb_strtolower( $in ) : strtolower( $in );
			case 'capitalize':
				return ucfirst( $in );
			case 'title':
			case 'start_case':
				return function_exists( 'mb_convert_case' ) ? mb_convert_case( $in, MB_CASE_TITLE ) : ucwords( $in );
			case 'camel_case':
				return self::to_camel( $in );
			case 'snake_case':
				return self::to_delimited( $in, '_' );
			case 'kebab_case':
				return self::to_delimited( $in, '-' );
			case 'trim':
				return trim( $in );
			case 'ltrim':
				return ltrim( $in );
			case 'rtrim':
				return rtrim( $in );
			case 'replace':
				return str_replace( (string) ( $c['find'] ?? '' ), (string) ( $c['replace'] ?? '' ), $in );
			case 'regex_replace':
				return self::safe_pcre( (string) ( $c['pattern'] ?? '' ), (string) ( $c['replace'] ?? '' ), $in );
			case 'truncate':
				$len = (int) ( $c['length'] ?? 100 );
				return ( strlen( $in ) > $len ) ? ( substr( $in, 0, $len ) . '…' ) : $in;
			case 'substring':
				$sub_start = (int) ( $c['start'] ?? 0 );
				return ( isset( $c['length'] ) && '' !== $c['length'] )
					? (string) substr( $in, $sub_start, (int) $c['length'] )
					: (string) substr( $in, $sub_start );
			case 'pad':
				return self::pad( $in, $c );
			case 'repeat':
				return str_repeat( $in, max( 0, (int) ( $c['times'] ?? 2 ) ) );
			case 'reverse':
				return strrev( $in );
			case 'slug':
				return sanitize_title( $in );
			case 'strip_html':
				return trim( wp_strip_all_tags( $in ) );
			case 'escape_html':
				return htmlspecialchars( $in, ENT_QUOTES, 'UTF-8' );
			case 'unescape_html':
				return htmlspecialchars_decode( $in, ENT_QUOTES );
			case 'url_encode':
				return rawurlencode( $in );
			case 'url_decode':
				return rawurldecode( $in );
			case 'base64_encode':
				return base64_encode( $in );
			case 'base64_decode':
				return (string) base64_decode( $in, false );
			case 'hash':
				return self::hash( $in, $c );
			case 'split':
				return array_map( 'trim', explode( (string) ( $c['separator'] ?? ',' ), $in ) );
			case 'extract':
				return self::extract( (string) ( $c['pattern'] ?? '' ), $in );
			case 'length':
				return function_exists( 'mb_strlen' ) ? mb_strlen( $in ) : strlen( $in );
			case 'word_count':
				return str_word_count( $in );
			case 'default':
				return '' === trim( $in ) ? (string) ( $c['default_value'] ?? '' ) : $in;
			case 'uppercase':
			default:
				return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $in ) : strtoupper( $in );
		}//end switch
	}

	private static function do_number( array $c ) {
		$decimals = (int) ( $c['decimals'] ?? 2 );
		$op       = $c['operation'] ?? 'round';

		if ( 'math' === $op ) {
			return self::eval_math( (string) ( $c['expression'] ?? '' ) );
		}

		$n = (float) ( $c['input'] ?? 0 );
		switch ( $op ) {
			case 'ceil':
				return (int) ceil( $n );
			case 'floor':
				return (int) floor( $n );
			case 'abs':
				return abs( $n );
			case 'format':
				return number_format( $n, $decimals );
			case 'currency':
				return (string) ( $c['currency_symbol'] ?? '$' ) . number_format( $n, $decimals );
			case 'clamp':
				$min = isset( $c['min_value'] ) && '' !== $c['min_value'] ? (float) $c['min_value'] : -INF;
				$max = isset( $c['max_value'] ) && '' !== $c['max_value'] ? (float) $c['max_value'] : INF;
				return max( $min, min( $max, $n ) );
			case 'percentage':
				$total = (float) ( $c['total'] ?? 0 );
				return 0.0 === $total ? 0 : round( $n / $total * 100, $decimals );
			case 'round':
			default:
				return round( $n, $decimals );
		}//end switch
	}

	private static function do_list( array $c ) {
		$list = self::to_list( $c['input'] ?? [] );
		switch ( $c['operation'] ?? 'join' ) {
			case 'join':
				return implode( (string) ( $c['separator'] ?? ', ' ), array_map( 'strval', array_filter( $list, 'is_scalar' ) ) );
			case 'count':
				return count( $list );
			case 'unique':
				return array_values( array_unique( $list, SORT_REGULAR ) );
			case 'first':
				return $list[0] ?? null;
			case 'last':
				return empty( $list ) ? null : end( $list );
			case 'slice':
				return array_slice( $list, (int) ( $c['offset'] ?? 0 ), isset( $c['length'] ) && '' !== $c['length'] ? (int) $c['length'] : null );
			case 'sort':
				$sorted = $list;
				sort( $sorted, SORT_REGULAR );
				return 'desc' === ( $c['direction'] ?? 'asc' ) ? array_reverse( $sorted ) : $sorted;
			case 'reverse':
				return array_reverse( $list );
			case 'compact':
				return array_values( array_filter( $list, static fn( $v ) => '' !== $v && null !== $v ) );
			case 'pluck':
				$key = (string) ( $c['field'] ?? '' );
				return array_values( array_map( static fn( $item ) => is_array( $item ) ? ( $item[ $key ] ?? null ) : null, $list ) );
			case 'sum':
				return array_sum( self::numbers( $list ) );
			case 'min':
				$nums = self::numbers( $list );
				return empty( $nums ) ? 0 : min( $nums );
			case 'max':
				$nums = self::numbers( $list );
				return empty( $nums ) ? 0 : max( $nums );
			case 'average':
				$nums = self::numbers( $list );
				return empty( $nums ) ? 0 : array_sum( $nums ) / count( $nums );
			default:
				return $list;
		}//end switch
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

	// ── helpers ──────────────────────────────────────────────────────────────

	private static function to_camel( string $s ): string {
		$s = str_replace( [ '-', '_' ], ' ', $s );
		$s = ucwords( strtolower( $s ) );
		$s = str_replace( ' ', '', $s );
		return lcfirst( $s );
	}

	private static function to_delimited( string $s, string $delim ): string {
		$s = preg_replace( '/([a-z0-9])([A-Z])/', '$1 $2', $s );
		$s = strtolower( (string) preg_replace( '/[\s\-_]+/', ' ', (string) $s ) );
		return str_replace( ' ', $delim, trim( $s ) );
	}

	private static function pad( string $in, array $c ): string {
		$len  = (int) ( $c['length'] ?? 0 );
		$char = (string) ( $c['pad_char'] ?? ' ' );
		$char = '' === $char ? ' ' : $char;
		$side = $c['pad_side'] ?? 'left';
		$type = 'right' === $side ? STR_PAD_RIGHT : ( 'both' === $side ? STR_PAD_BOTH : STR_PAD_LEFT );
		return str_pad( $in, $len, $char, $type );
	}

	private static function hash( string $in, array $c ): string {
		$algo = $c['algo'] ?? 'md5';
		if ( 'hmac_sha256' === $algo ) {
			return hash_hmac( 'sha256', $in, (string) ( $c['hmac_key'] ?? '' ) );
		}
		$map = [
			'md5' => 'md5',
			'sha1' => 'sha1',
			'sha256' => 'sha256'
		];
		return hash( $map[ $algo ] ?? 'md5', $in );
	}

	private static function safe_pcre( string $pattern, string $replace, string $subject ): string {
		if ( '' === $pattern ) {
			return $subject;
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Bad user regex must not fatal the run.
		$out = @preg_replace( $pattern, $replace, $subject );
		return null === $out ? $subject : $out;
	}

	private static function extract( string $pattern, string $subject ): string {
		if ( '' === $pattern ) {
			return '';
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Bad user regex must not fatal the run.
		if ( @preg_match( $pattern, $subject, $m ) ) {
			return $m[1] ?? $m[0];
		}
		return '';
	}

	/**
	 * @param mixed $v
	 * @return array<int,mixed>
	 */
	private static function to_list( $v ): array {
		if ( is_array( $v ) ) {
			return array_values( $v );
		}
		$s = trim( (string) $v );
		if ( '' === $s ) {
			return [];
		}
		$decoded = json_decode( $s, true );
		if ( is_array( $decoded ) ) {
			return array_values( $decoded );
		}
		return array_map( 'trim', explode( ',', $s ) );
	}

	/**
	 * @param array<int,mixed> $list
	 * @return array<int,float>
	 */
	private static function numbers( array $list ): array {
		$out = [];
		foreach ( $list as $v ) {
			if ( is_numeric( $v ) ) {
				$out[] = (float) $v;
			}
		}
		return $out;
	}

	/**
	 * Safe arithmetic evaluator (shunting-yard). Supports + - * / % and
	 * parentheses on decimal numbers — never uses eval().
	 *
	 * @return float|int
	 */
	private static function eval_math( string $expr ) {
		$expr = trim( $expr );
		if ( '' === $expr || ! preg_match_all( '/\d+\.?\d*|[-+*\/%()]/', $expr, $m ) ) {
			return 0;
		}

		$prec   = [
			'+' => 1,
			'-' => 1,
			'*' => 2,
			'/' => 2,
			'%' => 2
		];
		$output = [];
		$ops    = [];
		$prev   = null;
		$negate = false;

		foreach ( $m[0] as $t ) {
			if ( is_numeric( $t ) ) {
				$val      = (float) $t;
				$output[] = $negate ? -$val : $val;
				$negate   = false;
				$prev     = 'num';
			} elseif ( '(' === $t ) {
				// Unary minus before a group → 0 - (…).
				if ( $negate ) {
					$output[] = 0.0;
					$ops[]    = '-';
					$negate   = false;
				}
				$ops[] = $t;
				$prev  = '(';
			} elseif ( ')' === $t ) {
				while ( ! empty( $ops ) && '(' !== end( $ops ) ) {
					$output[] = array_pop( $ops );
				}
				array_pop( $ops );
				$prev = ')';
			} else {
				// A '-' at the start / after '(' / after another operator is unary:
				// negate the next operand rather than emit a binary op.
				if ( '-' === $t && ( null === $prev || '(' === $prev || isset( $prec[ $prev ] ) ) ) {
					$negate = ! $negate;
					continue;
				}
				while ( ! empty( $ops ) && '(' !== end( $ops ) && $prec[ end( $ops ) ] >= $prec[ $t ] ) {
					$output[] = array_pop( $ops );
				}
				$ops[] = $t;
				$prev  = $t;
			}//end if
		}//end foreach
		while ( ! empty( $ops ) ) {
			$output[] = array_pop( $ops );
		}

		$stack = [];
		foreach ( $output as $t ) {
			if ( is_float( $t ) ) {
				$stack[] = $t;
				continue;
			}
			$b = array_pop( $stack );
			$a = array_pop( $stack );
			if ( null === $a || null === $b ) {
				return 0;
			}
			switch ( $t ) {
				case '+':
					$stack[] = $a + $b;
					break;
				case '-':
					$stack[] = $a - $b;
					break;
				case '*':
					$stack[] = $a * $b;
					break;
				case '/':
					$stack[] = 0.0 !== $b ? $a / $b : 0;
					break;
				case '%':
					$stack[] = 0.0 !== $b ? fmod( $a, $b ) : 0;
					break;
			}
		}//end foreach

		$result = empty( $stack ) ? 0 : end( $stack );
		return ( is_float( $result ) && $result == (int) $result ) ? (int) $result : $result;
	}
}
