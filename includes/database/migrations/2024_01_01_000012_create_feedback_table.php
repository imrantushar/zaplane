<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateFeedbackTable extends Migration {

	public function up(): void {
		Schema::create( 'feedback', function ( Blueprint $table ) {
			$table->id();
			$table->unsignedBigInteger( 'order_id' );
			$table->tinyInteger( 'rating' );
			$table->text( 'comment' )->nullable();
			$table->string( 'email', 192 )->nullable();
			$table->timestamps();

			$table->index( 'order_id' );
		} );
	}

	public function down(): void {
		Schema::drop( 'feedback' );
	}
}
