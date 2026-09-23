<?php

namespace Zaplane\Utils;

use Zaplane\Utils\Traits\Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Helper {

	use Menu;

	/**
	 * Substring test that works on PHP 7.4. An empty needle is always found, as in PHP 8.
	 */
	public static function contains( string $haystack, string $needle ): bool {
		return '' === $needle || false !== strpos( $haystack, $needle );
	}

	/**
	 * Prefix test that works on PHP 7.4.
	 */
	public static function starts_with( string $haystack, string $needle ): bool {
		return 0 === strncmp( $haystack, $needle, strlen( $needle ) );
	}

	/**
	 * Suffix test that works on PHP 7.4.
	 */
	public static function ends_with( string $haystack, string $needle ): bool {
		$length = strlen( $needle );

		return 0 === $length || ( strlen( $haystack ) >= $length && substr( $haystack, -$length ) === $needle );
	}
}
