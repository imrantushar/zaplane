<?php

namespace Zaplane\Recipes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The recipes that ship with Zaplane: one file each in `shipped/`, named by the
 * recipe's slug, returning the recipe. Adding a file adds the recipe.
 */
final class ShippedRecipes {

	public static function register( Registry $recipes ): void {
		foreach ( self::files() as $slug => $file ) {
			$recipes->add( $slug, require $file );
		}
	}

	/**
	 * @return array<string,string> Slug => file.
	 */
	public static function files(): array {
		$files = [];

		foreach ( (array) glob( __DIR__ . '/shipped/*.php' ) as $file ) {
			$files[ basename( (string) $file, '.php' ) ] = (string) $file;
		}

		ksort( $files );

		return $files;
	}
}
