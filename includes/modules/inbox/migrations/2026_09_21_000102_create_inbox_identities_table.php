<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How a contact is addressed on one channel account — a page-scoped sender id,
 * a phone number, a widget visitor id. The unique key is what lets a second
 * message from the same sender find the same contact.
 */
class CreateInboxIdentitiesTable extends Migration {

	public function up(): void {
		Schema::create( 'inbox_identities', function ( Blueprint $table ) {
			$table->id();
			$table->unsignedBigInteger( 'contact_id' );
			$table->string( 'channel', 30 );
			$table->string( 'account_id', 191 )->default( '' );
			$table->string( 'external_id', 191 );
			$table->timestamps();

			$table->unique( [ 'channel', 'account_id', 'external_id' ], 'inbox_identity_address' );
			$table->index( 'contact_id', 'inbox_identity_contact' );
		} );
	}

	public function down(): void {
		Schema::drop( 'inbox_identities' );
	}
}
