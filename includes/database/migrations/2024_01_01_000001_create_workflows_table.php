<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateWorkflowsTable extends Migration {

	public function up(): void {
		Schema::create('workflows', function ( Blueprint $table ) {
			$table->id();
			$table->unsignedBigInteger( 'user_id' );
			$table->unsignedBigInteger( 'folder_id' )->nullable();
			$table->string( 'title' );
			$table->text( 'integration_icons' )->nullable();
			$table->enum( 'status', [ 'active', 'paused', 'draft' ] )->default( 'draft' );
			$table->string( 'layout', 20 )->default( 'LR' );
			$table->timestamps();

			$table->index( 'user_id' );
			$table->index( 'folder_id' );
			$table->index( 'status' );
		});
	}

	public function down(): void {
		Schema::drop( 'workflows' );
	}
}
