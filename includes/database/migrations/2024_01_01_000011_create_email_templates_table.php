<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateEmailTemplatesTable extends Migration {

	public function up(): void {
		Schema::create( 'email_templates', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'title', 255 );
			$table->string( 'subject', 255 )->nullable();
			$table->string( 'pre_header', 255 )->nullable();
			// The EMB builder's JSON tree (single source of truth).
			$table->longText( 'content' )->nullable();
			$table->unsignedBigInteger( 'created_by' );
			$table->timestamps();

			$table->index( 'created_by' );
		} );
	}

	public function down(): void {
		Schema::drop( 'email_templates' );
	}
}
