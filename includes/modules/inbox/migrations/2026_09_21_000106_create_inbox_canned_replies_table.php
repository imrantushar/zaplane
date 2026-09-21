<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateInboxCannedRepliesTable extends Migration {

	public function up(): void {
		Schema::create( 'inbox_canned_replies', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'title', 191 );
			$table->string( 'shortcut', 50 )->nullable();
			$table->longText( 'body' );
			$table->timestamps();
		} );
	}

	public function down(): void {
		Schema::drop( 'inbox_canned_replies' );
	}
}
