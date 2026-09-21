<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateInboxTagsTable extends Migration {

	public function up(): void {
		Schema::create( 'inbox_tags', function ( Blueprint $table ) {
			$table->id();
			$table->unsignedBigInteger( 'conversation_id' );
			$table->string( 'tag', 100 );

			$table->unique( [ 'conversation_id', 'tag' ], 'inbox_tag_pair' );
			$table->index( 'tag', 'inbox_tag_name' );
		} );
	}

	public function down(): void {
		Schema::drop( 'inbox_tags' );
	}
}
