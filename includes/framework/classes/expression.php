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

		if ( ! is_string( $expr ) || ! str_contains( $expr, '{{' ) ) {
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
	 */
	private static function is_reserved( string $code ): bool {
		$roots = [ 'contact', 'unsubscribe_link', 'update_preferences_link' ];

		if ( function_exists( 'apply_filters' ) ) {
			$roots = (array) apply_filters( 'zaplane_reserved_merge_tag_roots', $roots );
		}

		$root = explode( '.', trim( $code ) )[0];

		return in_array( $root, $roots, true );
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

		// Prevent fatal compilation errors if the user's expression has trailing empty brackets (e.g `array[]`)
		$php = str_replace( '[]', '', $php );

		try {
			// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- eval() is intentional for expression evaluation engine.
			return eval( "return {$php};" ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
