<?php

namespace Zaplane\Database\Seeders;

use Zaplane\Models\Recipe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saves a recipe that ships with the plugin.
 *
 * A shipped recipe is known by its slug rather than its title. Renaming it no
 * longer brings a second copy back on the next update, and deleting it keeps it
 * deleted. Its blueprint is written again each time the seeders run, so a fix to
 * a shipped recipe reaches sites that already have it. Its title and description
 * are left as they are, since those can be edited.
 */
class RecipeSeeding {

	/** Slugs of shipped recipes that were deleted, so they aren't seeded again. */
	const DISMISSED_OPTION = 'zaplane_dismissed_recipes';

	/**
	 * @param array<string,mixed> $attributes Recipe columns: title, description, blueprint, integration_icons, type.
	 */
	public static function save( string $slug, array $attributes ): void {
		if ( self::is_dismissed( $slug ) ) {
			return;
		}

		$recipe = Recipe::where( 'slug', $slug )->fresh()->first();

		// Seeded before recipes had slugs, when the title was all that named it.
		// Only a shipped one (created_by 0): someone's own recipe may share the title.
		if ( ! $recipe && ! empty( $attributes['title'] ) ) {
			$recipe = Recipe::where( 'title', (string) $attributes['title'] )
				->where( 'created_by', 0 )
				->whereNull( 'slug' )
				->fresh()
				->first();
		}

		if ( $recipe ) {
			$recipe->slug      = $slug;
			$recipe->type      = (string) ( $attributes['type'] ?? 'workflow' );
			$recipe->blueprint = $attributes['blueprint'];

			if ( array_key_exists( 'integration_icons', $attributes ) ) {
				$recipe->integration_icons = $attributes['integration_icons'];
			}

			$recipe->save();
			return;
		}

		Recipe::create(
			array_merge(
				[
					'type'       => 'workflow',
					'created_by' => 0,
				],
				$attributes,
				[ 'slug' => $slug ]
			)
		);
	}

	/**
	 * Keeps a deleted shipped recipe from being seeded again.
	 */
	public static function dismiss( string $slug ): void {
		$dismissed = self::dismissed();

		if ( ! in_array( $slug, $dismissed, true ) ) {
			$dismissed[] = $slug;
			update_option( self::DISMISSED_OPTION, $dismissed, false );
		}
	}

	public static function is_dismissed( string $slug ): bool {
		return in_array( $slug, self::dismissed(), true );
	}

	/**
	 * @return array<int,string>
	 */
	private static function dismissed(): array {
		return array_values( array_filter( array_map( 'strval', (array) get_option( self::DISMISSED_OPTION, [] ) ) ) );
	}
}
