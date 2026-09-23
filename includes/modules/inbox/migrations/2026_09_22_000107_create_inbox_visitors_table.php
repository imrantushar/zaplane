<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Who is on the site right now, for the inbox's Visitors list: one row per
 * browser, updated by the chat widget while a page is open. Rows unseen for a
 * day are removed, so this never grows into an analytics log.
 */
class CreateInboxVisitorsTable extends Migration {

	public function up(): void {
		Schema::create( 'inbox_visitors', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'visitor_id', 40 );
			$table->unsignedBigInteger( 'wp_user_id' )->nullable();
			$table->string( 'page_url', 500 )->nullable();
			$table->string( 'page_title', 255 )->nullable();
			$table->string( 'referrer', 500 )->nullable();
			$table->string( 'device', 20 )->nullable();
			$table->unsignedInteger( 'page_views' )->default( 1 );
			$table->datetime( 'first_seen_at' )->nullable();
			$table->datetime( 'last_seen_at' )->nullable();

			$table->unique( 'visitor_id', 'inbox_visitors_visitor' );
			$table->index( 'last_seen_at', 'inbox_visitors_seen' );
		} );
	}

	public function down(): void {
		Schema::drop( 'inbox_visitors' );
	}
}
