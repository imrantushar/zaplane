<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Group recipes: one recipe that sets up several workflows, in a folder of their own.
 *
 *  - recipes.type: `workflow` (a single workflow, as every recipe was before) or `group`.
 *  - recipes.slug: names a recipe that ships with the plugin, so the seeder finds it
 *    again after it is renamed, and doesn't bring it back once it is deleted.
 *  - folders.recipe_id, folders.setup: the group recipe a folder was set up from,
 *    and the answers given in that setup.
 */
class AddRecipeGroups extends Migration {

	public function up(): void {
		Schema::table( 'recipes', function ( Blueprint $table ) {
			$table->string( 'type', 20 )->default( 'workflow' );
			$table->string( 'slug', 191 )->nullable();

			$table->index( 'type' );
			$table->index( 'slug' );
		} );

		Schema::table( 'folders', function ( Blueprint $table ) {
			$table->unsignedBigInteger( 'recipe_id' )->nullable();
			$table->longText( 'setup' )->nullable();

			$table->index( 'recipe_id' );
		} );
	}

	public function down(): void {
		Schema::table( 'recipes', function ( Blueprint $table ) {
			$table->dropColumn( 'type' );
			$table->dropColumn( 'slug' );
		} );

		Schema::table( 'folders', function ( Blueprint $table ) {
			$table->dropColumn( 'recipe_id' );
			$table->dropColumn( 'setup' );
		} );
	}
}
