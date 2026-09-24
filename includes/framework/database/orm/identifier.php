<?php

namespace Zaplane\Framework\Database\ORM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Guards the parts of a statement that no placeholder can carry.
 *
 * `$wpdb->prepare()` covers values, and every value in this ORM goes through it.
 * What it cannot carry is the shape of the statement — table and column names,
 * comparison operators, sort direction — so those are checked here instead, and
 * anything that is not a plain identifier or a known keyword is refused rather
 * than escaped. A name that fails is a bug in the calling code, never a value
 * that arrived with a request, so refusing is safe and makes the guarantee
 * checkable: after this, nothing reaching the database can be read as SQL.
 */
class Identifier {

	/** Operators a where/having clause may use. */
	private const OPERATORS = [
		'=',
		'!=',
		'<>',
		'<',
		'<=',
		'>',
		'>=',
		'LIKE',
		'NOT LIKE',
		'IN',
		'NOT IN',
		'IS',
		'IS NOT',
		'REGEXP',
		'NOT REGEXP',
	];

	/**
	 * A column or table name, backtick-quoted.
	 *
	 * Accepts `column`, `table.column`, `table.*` and `*`, plus an optional
	 * `AS alias`. Anything else throws.
	 *
	 * @param string $name Identifier to quote.
	 * @throws \InvalidArgumentException When the name is not a plain identifier.
	 */
	public static function quote( string $name ): string {
		$name = trim( $name );

		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'Empty SQL identifier.' );
		}

		// `col AS alias` / `col alias`.
		if ( preg_match( '/^(?<ref>[^\s]+)\s+(?:as\s+)?(?<alias>[A-Za-z_][A-Za-z0-9_]*)$/i', $name, $m ) ) {
			return self::quote( $m['ref'] ) . ' AS `' . $m['alias'] . '`';
		}

		if ( '*' === $name ) {
			return '*';
		}

		$parts  = explode( '.', $name );
		$quoted = [];

		foreach ( $parts as $part ) {
			if ( '*' === $part ) {
				$quoted[] = '*';
				continue;
			}

			if ( ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $part ) ) {
				throw new \InvalidArgumentException( 'Unsupported SQL identifier: ' . esc_html( $part ) );
			}

			$quoted[] = '`' . $part . '`';
		}

		if ( count( $quoted ) > 2 ) {
			throw new \InvalidArgumentException( 'Unsupported SQL identifier: ' . esc_html( $name ) );
		}

		return implode( '.', $quoted );
	}

	/**
	 * A comparison operator, from the fixed list above.
	 *
	 * @param string $operator Operator to check.
	 * @throws \InvalidArgumentException When the operator is not one of ours.
	 */
	public static function operator( string $operator ): string {
		$operator = strtoupper( trim( $operator ) );

		if ( ! in_array( $operator, self::OPERATORS, true ) ) {
			throw new \InvalidArgumentException( 'Unsupported SQL operator: ' . esc_html( $operator ) );
		}

		return $operator;
	}

	/**
	 * ASC or DESC. Anything else sorts ascending rather than throwing, because a
	 * sort order is a presentation detail and never worth failing a page over.
	 *
	 * @param string $direction Requested direction.
	 */
	public static function direction( string $direction ): string {
		return 'DESC' === strtoupper( trim( $direction ) ) ? 'DESC' : 'ASC';
	}

	/**
	 * A JOIN type keyword.
	 *
	 * @param string $type Requested join type.
	 */
	public static function join_type( string $type ): string {
		$type = strtoupper( trim( $type ) );

		return in_array( $type, [ 'INNER', 'LEFT', 'RIGHT', 'CROSS' ], true ) ? $type : 'INNER';
	}

	/**
	 * An aggregate function name.
	 *
	 * @param string $function Requested function.
	 * @throws \InvalidArgumentException When the function is not one of ours.
	 */
	public static function aggregate( string $function ): string {
		$function = strtoupper( trim( $function ) );

		if ( ! in_array( $function, [ 'COUNT', 'SUM', 'AVG', 'MIN', 'MAX' ], true ) ) {
			throw new \InvalidArgumentException( 'Unsupported aggregate: ' . esc_html( $function ) );
		}

		return $function;
	}
}
