<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateConversationsTable extends Migration {

	public function up(): void {
		Schema::create('conversations', function ( Blueprint $table ) {
			$table->id();
			// Logical conversation bucket, e.g. "whatsapp:15551234567" or
			// "messenger:<psid>:<workflow_id>". Keyed for fast per-sender reads.
			$table->string( 'conversation_key', 191 );
			$table->string( 'channel', 50 )->nullable();
			$table->string( 'role', 20 )->default( 'user' );
			$table->longText( 'content' )->nullable();
			$table->datetime( 'created_at' )->nullable()->useCurrent();

			$table->index( 'conversation_key' );
		});
	}

	public function down(): void {
		Schema::drop( 'conversations' );
	}
}
