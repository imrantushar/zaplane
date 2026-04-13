<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateRecipesTable extends Migration {

	public function up(): void {
		Schema::create( 'recipes', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'title', 255 );
			$table->text( 'description' )->nullable();
			$table->unsignedBigInteger( 'thumbnail_id' )->nullable();
			$table->longText( 'blueprint' );
			$table->unsignedBigInteger( 'created_by' );
			$table->timestamps();

			$table->index( 'created_by' );
		} );
	}

	public function down(): void {
		Schema::drop( 'recipes' );
	}
}
