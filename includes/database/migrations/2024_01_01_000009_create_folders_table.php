<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateFoldersTable extends Migration {

	public function up(): void {
		Schema::create( 'recipe_folders', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'title', 255 );
			$table->unsignedBigInteger( 'parent_id' )->nullable();
			$table->unsignedBigInteger( 'created_by' );
			$table->timestamps();

			$table->index( 'parent_id' );
			$table->index( 'created_by' );
		} );
	}

	public function down(): void {
		Schema::drop( 'recipe_folders' );
	}
}
