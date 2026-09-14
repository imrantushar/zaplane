<?php
namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The `{{ … }}` merge tags in a workflow's node configuration.
 *
 * Almost every one of these is a path — `{{trigger.email}}`, `{{1.order_id}}` —
 * read out of the data the run has gathered so far. A few do arithmetic or a
 * comparison. Condition and Filter nodes keep their operator separately and
 * compare in PHP, so nothing here ever needs to be more than one value.
 *
 * Tags are parsed, never compiled to PHP. Every node's configuration is resolved
 * against the run's data, and for a webhook trigger that data is a JSON body
 * posted by a stranger, so the language has to be closed. The grammar below is
 * the whole language:
 * values, the usual operators, and parentheses for grouping. There is no
 * production for a function call, which is why one cannot be written.
 */
class Expression {

	/** Binding power, loosest first. Higher binds tighter. */
	private const PRECEDENCE = [
		'||'  => 1,
		'&&'  => 2,
		'=='  => 3,
		'===' => 3,
		'!='  => 3,
		'!==' => 3,
		'<'   => 4,
		'<='  => 4,
		'>'   => 4,
		'>='  => 4,
		'+'   => 5,
		'-'   => 5,
		'*'   => 6,
		'/'   => 6,
		'%'   => 6,
	];

	/** Longest first, so `===` is not read as `==` followed by `=`. */
	private const OPERATORS = [ '===', '!==', '==', '!=', '<=', '>=', '&&', '||', '<', '>', '+', '-', '*', '/', '%' ];

	/**
	 * A tag that is the whole string resolves to a value of its own type; one
	 * embedded in a sentence is stringified in place.
	 *
	 * @param mixed               $expr The configured value.
	 * @param array<string,mixed> $data Everything the run has gathered.
	 * @return mixed
	 */
	public static function evaluate( $expr, array $data ) {

		if ( null === $expr || '' === $expr ) {
			return null;
		}

		if ( ! is_string( $expr ) || false === strpos( $expr, '{{' ) ) {
			return $expr;
		}

		if ( preg_match( '/^\{\{([^}]+)\}\}$/', trim( $expr ), $m ) ) {
			$code = trim( $m[1] );
			// Reserved tokens (e.g. {{contact.*}}) belong to an integration's own
			// per-recipient merge engine — pass them through untouched.
			if ( self::is_reserved( $code ) ) {
				return '{{' . $code . '}}';
			}
			return self::compute( $code, $data );
		}

		return preg_replace_callback('/\{\{(.*?)\}\}/', function ( $m ) use ( $data ) {
			$code = trim( $m[1] );
			if ( self::is_reserved( $code ) ) {
				return $m[0];
			}
			$val = self::compute( $code, $data );
			if ( is_array( $val ) ) {
				return implode( ', ', array_filter( $val, 'is_scalar' ) );
			}
			return null !== $val ? (string) $val : '';
		}, $expr);
	}

	/**
	 * Whether a token's root segment is reserved by an integration's own merge
	 * engine and must be left literal by Zaplane's resolver. Extensible via the
	 * `zaplane_reserved_merge_tag_roots` filter.
	 *
	 * @param string $code The tag's contents.
	 */
	private static function is_reserved( string $code ): bool {
		$roots = [ 'contact', 'unsubscribe_link', 'update_preferences_link' ];

		if ( function_exists( 'apply_filters' ) ) {
			$roots = (array) apply_filters( 'zaplane_reserved_merge_tag_roots', $roots );
		}

		$root = explode( '.', trim( $code ) )[0];

		return in_array( $root, $roots, true );
	}

	/**
	 * Resolve one tag's contents.
	 *
	 * Anything the grammar does not cover is null rather than an error: a merge
	 * tag that cannot be resolved has always meant "nothing here", and a run must
	 * not stop because somebody typed something odd into a field.
	 *
	 * @param string              $code The tag's contents.
	 * @param array<string,mixed> $data Everything the run has gathered.
	 * @return mixed
	 */
	private static function compute( string $code, array $data ) {
		try {
			$tokens = self::tokenize( $code, $data );

			if ( empty( $tokens ) ) {
				return null;
			}

			$position = 0;
			$value    = self::parse( $tokens, $position, 0, $data );

			// Trailing tokens mean it did not parse as one expression. Rather than
			// guess at half of it, treat the tag as unresolvable.
			return count( $tokens ) === $position ? $value : null;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Split into values and operators.
	 *
	 * @param string              $code The tag's contents.
	 * @param array<string,mixed> $data Everything the run has gathered. The keys in it tell
	 *                                  a hyphen in a name from a minus sign.
	 * @return array<int,array{0:string,1:mixed}>
	 * @throws \InvalidArgumentException On a character the grammar has no place for.
	 */
	private static function tokenize( string $code, array $data ): array {
		$tokens = [];
		$length = strlen( $code );
		$i      = 0;

		while ( $i < $length ) {
			$char = $code[ $i ];

			if ( ' ' === $char || "\t" === $char || "\n" === $char || "\r" === $char ) {
				++$i;
				continue;
			}

			if ( '(' === $char || ')' === $char ) {
				$tokens[] = [ $char, null ];
				++$i;
				continue;
			}

			// Unary not, but only where it cannot be the front of `!=`.
			if ( '!' === $char && ( $i + 1 >= $length || '=' !== $code[ $i + 1 ] ) ) {
				$tokens[] = [ '!', null ];
				++$i;
				continue;
			}

			$operator = null;
			foreach ( self::OPERATORS as $candidate ) {
				if ( 0 === substr_compare( $code, $candidate, $i, strlen( $candidate ) ) ) {
					$operator = $candidate;
					break;
				}
			}

			if ( null !== $operator ) {
				$tokens[] = [ 'op', $operator ];
				$i       += strlen( $operator );
				continue;
			}

			if ( '"' === $char || "'" === $char ) {
				$tokens[] = [ 'value', self::read_string( $code, $i ) ];
				continue;
			}

			if ( ctype_digit( $char ) ) {
				$tokens[] = self::read_number( $code, $i, $data );
				continue;
			}

			if ( ctype_alpha( $char ) || '_' === $char ) {
				$tokens[] = self::read_word( $code, $i, $data );
				continue;
			}

			// A `$`, a brace, a backtick, a semicolon: nothing in this language.
			throw new \InvalidArgumentException( 'Unexpected character in expression.' );
		}//end while

		return $tokens;
	}

	/**
	 * @param string $code The tag's contents.
	 * @param int    $i    Advanced past the closing quote.
	 * @return string
	 * @throws \InvalidArgumentException When the quote is never closed.
	 */
	private static function read_string( string $code, int &$i ): string {
		$quote = $code[ $i ];
		$out   = '';
		$len   = strlen( $code );
		++$i;

		while ( $i < $len ) {
			if ( '\\' === $code[ $i ] && $i + 1 < $len ) {
				$out .= $code[ $i + 1 ];
				$i   += 2;
				continue;
			}

			if ( $code[ $i ] === $quote ) {
				++$i;
				return $out;
			}

			$out .= $code[ $i ];
			++$i;
		}

		throw new \InvalidArgumentException( 'Unterminated string in expression.' );
	}

	/**
	 * A number — or a path that merely starts with one.
	 *
	 * `{{1.first_name}}` is the commonest tag in the whole product: a step's
	 * output addressed by its number. It starts with a digit and is not a number.
	 *
	 * @param string              $code The tag's contents.
	 * @param int                 $i    Advanced past the token.
	 * @param array<string,mixed> $data Everything the run has gathered.
	 * @return array{0:string,1:mixed}
	 */
	private static function read_number( string $code, int &$i, array $data ): array {
		$start = $i;
		$len   = strlen( $code );

		while ( $i < $len && ( ctype_digit( $code[ $i ] ) || '.' === $code[ $i ] ) ) {
			// A dot only belongs to the number when a digit follows it; otherwise
			// it is the path separator in something like `1.first_name`.
			if ( '.' === $code[ $i ] && ( $i + 1 >= $len || ! ctype_digit( $code[ $i + 1 ] ) ) ) {
				break;
			}
			++$i;
		}

		$raw = substr( $code, $start, $i - $start );

		// `1.first_name` starts as a digit but is a path, not a number.
		if ( $i < $len && ( ctype_alpha( $code[ $i ] ) || '_' === $code[ $i ] || '.' === $code[ $i ] ) ) {
			$i = $start;
			return self::read_word( $code, $i, $data );
		}

		return [ 'value', false !== strpos( $raw, '.' ) ? (float) $raw : (int) $raw ];
	}

	/**
	 * A bare word: one of the three literals, or a dotted path into the data.
	 *
	 * Form fields are often named with hyphens, like Contact Form 7's `your-email`.
	 * A hyphen inside a word belongs to the path when the data has that path, and
	 * is a minus sign otherwise, so `{{1.total-1.discount}}` still subtracts.
	 *
	 * @param string              $code The tag's contents.
	 * @param int                 $i    Advanced past the word.
	 * @param array<string,mixed> $data Everything the run has gathered.
	 * @return array{0:string,1:mixed}
	 */
	private static function read_word( string $code, int &$i, array $data ): array {
		$start = $i;
		$len   = strlen( $code );

		while ( $i < $len && self::is_path_char( $code[ $i ] ) ) {
			++$i;
		}

		// Try every hyphen and keep the longest word that is a path in the data. A name
		// like `billing-first-name` has no `billing-first`, so a miss doesn't end the search.
		$scan = $i;
		while ( $scan + 1 < $len && '-' === $code[ $scan ] && '.' !== $code[ $scan - 1 ] && '.' !== $code[ $scan + 1 ] && self::is_path_char( $code[ $scan + 1 ] ) ) {
			++$scan;

			while ( $scan < $len && self::is_path_char( $code[ $scan ] ) ) {
				++$scan;
			}

			if ( self::find( substr( $code, $start, $scan - $start ), $data )[0] ) {
				$i = $scan;
			}
		}

		$word = substr( $code, $start, $i - $start );

		switch ( strtolower( $word ) ) {
			case 'true':
				return [ 'value', true ];
			case 'false':
				return [ 'value', false ];
			case 'null':
				return [ 'value', null ];
		}

		return [ 'path', $word ];
	}

	/**
	 * Precedence climbing over the token list.
	 *
	 * @param array<int,array{0:string,1:mixed}> $tokens
	 * @param int                                $position Where to read from; left after the expression.
	 * @param int                                $minimum  Lowest binding power this call will accept.
	 * @param array<string,mixed>                $data
	 * @return mixed
	 * @throws \InvalidArgumentException When the tokens are not an expression.
	 */
	private static function parse( array $tokens, int &$position, int $minimum, array $data ) {
		$left  = self::parse_unary( $tokens, $position, $data );
		$count = count( $tokens );

		while ( $position < $count ) {
			$token = $tokens[ $position ];

			if ( 'op' !== $token[0] ) {
				break;
			}

			$power = self::PRECEDENCE[ $token[1] ] ?? 0;

			if ( $power < $minimum || 0 === $power ) {
				break;
			}

			++$position;

			// All of these are left-associative, so the right-hand side stops at
			// anything binding as loosely as this operator.
			$right = self::parse( $tokens, $position, $power + 1, $data );
			$left  = self::apply( $token[1], $left, $right );
		}

		return $left;
	}

	/**
	 * @param array<int,array{0:string,1:mixed}> $tokens   The whole token list.
	 * @param int                                $position Where to read from; left after the value.
	 * @param array<string,mixed>                $data     Everything the run has gathered.
	 * @return mixed
	 * @throws \InvalidArgumentException When a value was expected and not found.
	 */
	private static function parse_unary( array $tokens, int &$position, array $data ) {
		if ( ! isset( $tokens[ $position ] ) ) {
			throw new \InvalidArgumentException( 'Expression ended early.' );
		}

		$token = $tokens[ $position ];

		if ( '!' === $token[0] ) {
			++$position;
			return ! self::parse_unary( $tokens, $position, $data );
		}

		if ( 'op' === $token[0] && '-' === $token[1] ) {
			++$position;
			$value = self::parse_unary( $tokens, $position, $data );
			return is_numeric( $value ) ? -$value : null;
		}

		if ( '(' === $token[0] ) {
			++$position;
			$value = self::parse( $tokens, $position, 0, $data );

			if ( ! isset( $tokens[ $position ] ) || ')' !== $tokens[ $position ][0] ) {
				throw new \InvalidArgumentException( 'Unclosed parenthesis in expression.' );
			}

			++$position;
			return $value;
		}

		if ( 'value' === $token[0] ) {
			++$position;
			return $token[1];
		}

		if ( 'path' === $token[0] ) {
			++$position;

			// A path followed by `(` used to be a call. It is not one now, and
			// saying so is better than resolving half the expression.
			if ( isset( $tokens[ $position ] ) && '(' === $tokens[ $position ][0] ) {
				throw new \InvalidArgumentException( 'Expressions cannot call functions.' );
			}

			return self::lookup( (string) $token[1], $data );
		}

		throw new \InvalidArgumentException( 'Expected a value in expression.' );
	}

	/**
	 * Walk a dotted path into the run's data.
	 *
	 * @param string              $path A dotted path.
	 * @param array<string,mixed> $data Everything the run has gathered.
	 * @return mixed Null when any segment is missing.
	 */
	private static function lookup( string $path, array $data ) {
		return self::find( $path, $data )[1];
	}

	/**
	 * Walk a dotted path into the run's data, telling a path that isn't there from
	 * one whose value is null.
	 *
	 * @param string              $path A dotted path.
	 * @param array<string,mixed> $data Everything the run has gathered.
	 * @return array{0:bool,1:mixed} Whether every segment was there, and the value.
	 */
	private static function find( string $path, array $data ): array {
		$value = $data;

		foreach ( explode( '.', $path ) as $segment ) {
			if ( is_array( $value ) && array_key_exists( $segment, $value ) ) {
				$value = $value[ $segment ];
				continue;
			}

			if ( is_object( $value ) && isset( $value->$segment ) ) {
				$value = $value->$segment;
				continue;
			}

			return [ false, null ];
		}

		return [ true, $value ];
	}

	/**
	 * A character a path can hold: a letter, a digit, `_`, or the `.` between
	 * segments.
	 */
	private static function is_path_char( string $char ): bool {
		return ctype_alnum( $char ) || '_' === $char || '.' === $char;
	}

	/**
	 * @param string $operator One of the operators above.
	 * @param mixed  $left      Left-hand value.
	 * @param mixed  $right     Right-hand value.
	 * @return mixed
	 */
	private static function apply( string $operator, $left, $right ) {
		switch ( $operator ) {
			case '||':
				return (bool) $left || (bool) $right;
			case '&&':
				return (bool) $left && (bool) $right;
			case '==':
				// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison, Universal.Operators.StrictComparisons.LooseEqual -- This IS the language's loose `==`; `===` is a separate operator above. Making it strict would change what every existing condition decides.
				return $left == $right;
			case '!=':
				// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison, Universal.Operators.StrictComparisons.LooseNotEqual -- As above.
				return $left != $right;
			case '===':
				return $left === $right;
			case '!==':
				return $left !== $right;
			case '<':
				return $left < $right;
			case '<=':
				return $left <= $right;
			case '>':
				return $left > $right;
			case '>=':
				return $left >= $right;
		}//end switch

		// Arithmetic on something that is not a number is nothing, not a warning
		// and a zero — a missing merge tag must not quietly become 0 in a total.
		if ( ! is_numeric( $left ) || ! is_numeric( $right ) ) {
			return null;
		}

		switch ( $operator ) {
			case '+':
				return $left + $right;
			case '-':
				return $left - $right;
			case '*':
				return $left * $right;
			case '/':
				return 0.0 === (float) $right ? null : $left / $right;
			case '%':
				return 0 === (int) $right ? null : $left % $right;
		}

		return null;
	}
}
