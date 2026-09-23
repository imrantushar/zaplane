<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A thread with one contact on one channel.
 *
 * `handler` and `ai_enabled` are real columns, not JSON meta: every AI reply
 * is gated on them, and a JSON flag compared in SQL is easy to get silently
 * wrong on MariaDB. `last_customer_at` drives the messaging-window rules some
 * channels impose on outbound replies.
 */
class CreateInboxConversationsTable extends Migration {

	public function up(): void {
		Schema::create( 'inbox_conversations', function ( Blueprint $table ) {
			$table->id();
			$table->unsignedBigInteger( 'contact_id' );
			$table->unsignedBigInteger( 'identity_id' )->nullable();
			$table->string( 'channel', 30 );
			$table->string( 'account_id', 191 )->default( '' );
			$table->string( 'status', 20 )->default( 'open' );
			$table->string( 'handler', 20 )->default( 'bot' );
			$table->tinyInteger( 'ai_enabled' )->default( 1 );
			$table->unsignedBigInteger( 'assignee_id' )->nullable();
			$table->unsignedInteger( 'unread_count' )->default( 0 );
			$table->string( 'last_message_preview', 255 )->nullable();
			$table->datetime( 'last_message_at' )->nullable();
			$table->datetime( 'last_customer_at' )->nullable();
			$table->datetime( 'closed_at' )->nullable();
			$table->longText( 'meta' )->nullable();
			$table->timestamps();

			$table->index( [ 'status', 'last_message_at' ], 'inbox_conv_status_recent' );
			$table->index( 'assignee_id', 'inbox_conv_assignee' );
			$table->index( 'contact_id', 'inbox_conv_contact' );
			$table->index( 'updated_at', 'inbox_conv_updated' );
		} );
	}

	public function down(): void {
		Schema::drop( 'inbox_conversations' );
	}
}
