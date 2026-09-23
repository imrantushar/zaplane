<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every message, note and system line in a conversation.
 *
 * The unique (channel, external_id) key is the idempotency guard: providers
 * retry a webhook until they get a 200, and the retry must not become a second
 * message. Locally created rows leave external_id NULL, which a unique key
 * allows any number of.
 */
class CreateInboxMessagesTable extends Migration {

	public function up(): void {
		Schema::create( 'inbox_messages', function ( Blueprint $table ) {
			$table->id();
			$table->unsignedBigInteger( 'conversation_id' );
			$table->string( 'direction', 10 )->default( 'in' );
			$table->string( 'sender_type', 20 )->default( 'contact' );
			$table->unsignedBigInteger( 'sender_id' )->nullable();
			$table->longText( 'body' )->nullable();
			$table->longText( 'attachments' )->nullable();
			$table->tinyInteger( 'is_note' )->default( 0 );
			$table->tinyInteger( 'is_ai_generated' )->default( 0 );
			$table->string( 'channel', 30 );
			$table->string( 'external_id', 191 )->nullable();
			$table->string( 'delivery_status', 20 )->default( 'sent' );
			$table->text( 'error' )->nullable();
			$table->longText( 'meta' )->nullable();
			$table->datetime( 'created_at' )->nullable()->useCurrent();

			$table->unique( [ 'channel', 'external_id' ], 'inbox_msg_external' );
			$table->index( [ 'conversation_id', 'id' ], 'inbox_msg_thread' );
		} );
	}

	public function down(): void {
		Schema::drop( 'inbox_messages' );
	}
}
