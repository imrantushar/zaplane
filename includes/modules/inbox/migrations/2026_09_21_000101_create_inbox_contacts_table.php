<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One row per person, whatever channel they wrote from. Visitors are not
 * WordPress users: a store with thousands of social contacts must not end up
 * with thousands of accounts. `wp_user_id` links one when the visitor is
 * signed in.
 */
class CreateInboxContactsTable extends Migration {

	public function up(): void {
		Schema::create( 'inbox_contacts', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'name', 191 )->nullable();
			$table->string( 'email', 191 )->nullable();
			$table->string( 'phone', 50 )->nullable();
			$table->string( 'avatar_url', 500 )->nullable();
			$table->unsignedBigInteger( 'wp_user_id' )->nullable();
			$table->longText( 'meta' )->nullable();
			$table->timestamps();

			$table->index( 'email', 'inbox_contacts_email' );
			$table->index( 'wp_user_id', 'inbox_contacts_wp_user' );
		} );
	}

	public function down(): void {
		Schema::drop( 'inbox_contacts' );
	}
}
