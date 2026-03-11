<?php
namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Expression {




	public static function evaluate( $expr, array $data ) {

		if ( null === $expr || '' === $expr ) {
			return null;
		}

		if ( ! str_contains( $expr, '{{' ) ) {
			return $expr;
		}

		if ( preg_match( '/^\{\{([^}]+)\}\}$/', trim( $expr ), $m ) ) {
			return self::compute( trim( $m[1] ), $data );
		}

		return preg_replace_callback('/\{\{(.*?)\}\}/', function ( $m ) use ( $data ) {
			$val = self::compute( trim( $m[1] ), $data );
			if ( is_array( $val ) ) {
				return implode( ', ', array_filter( $val, 'is_scalar' ) );
			}
			return null !== $val ? (string) $val : '';
		}, $expr);
	}



	private static function compute( string $code, array $data ) {
		$php = preg_replace_callback('/[a-zA-Z0-9_][a-zA-Z0-9_.]*/', function ( $m ) use ( $data ) {

			$key = $m[0];

			if ( in_array( $key, [ 'true', 'false', 'null' ], true ) || is_numeric( $key ) ) {
				return $key;
			}

			$parts = explode( '.', $key );
			$php = '$data';

			foreach ( $parts as $p ) {
				$php .= '["' . $p . '"]';
			}

			return $php;
		}, $code);

		try {
			// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- eval() is intentional for expression evaluation engine.
			return eval( "return {$php};" ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
