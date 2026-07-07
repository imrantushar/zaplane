<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateKnowledgeTable extends Migration {

	public function up(): void {
		Schema::create('knowledge', function ( Blueprint $table ) {
			$table->id();
			// Per-business bucket, e.g. "business_a". Retrieval is always scoped to it.
			$table->string( 'business_key', 191 );
			$table->string( 'title', 255 )->nullable();
			$table->longText( 'content' )->nullable();
			// Reserved for a future semantic layer (OpenAI embeddings) — keyword
			// retrieval ignores it today.
			$table->longText( 'embedding' )->nullable();
			$table->string( 'source', 50 )->default( 'manual' );
			$table->string( 'ref_id', 191 )->nullable();
			$table->datetime( 'updated_at' )->nullable()->useCurrent();

			$table->index( 'business_key' );
		});
	}

	public function down(): void {
		Schema::drop( 'knowledge' );
	}
}
