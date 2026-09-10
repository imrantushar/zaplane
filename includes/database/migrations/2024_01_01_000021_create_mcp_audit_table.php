<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What an AI client did, and on whose authority.
 *
 * The MCP endpoint hands out credentials that can create workflows and fire
 * them for real. Without this there is no answer to "who ran that" — only that
 * some token did, and tokens get revoked and reissued.
 *
 * Deliberately not a copy of the request: arguments can carry customer data and
 * anything a model was told, none of which belongs in a table nobody thinks to
 * prune. What is kept is who, what, and how it went.
 */
class CreateMcpAuditTable extends Migration {

	public function up(): void {
		Schema::create( 'mcp_audit', function ( Blueprint $table ) {
			$table->id();
			// The token id, not the token. Kept even after revocation, so the
			// trail survives the credential.
			$table->string( 'token_id', 64 );
			$table->string( 'token_name', 191 );
			$table->string( 'client_id', 64 )->nullable();
			$table->unsignedBigInteger( 'user_id' );
			$table->string( 'tool', 191 );
			$table->string( 'outcome', 20 );
			// A short reason when it failed; never the arguments.
			$table->text( 'detail' )->nullable();
			$table->unsignedInteger( 'duration_ms' )->nullable();
			$table->timestamps();

			$table->index( 'token_id' );
			$table->index( 'user_id' );
			$table->index( 'tool' );
			$table->index( 'created_at' );
		} );
	}

	public function down(): void {
		Schema::drop( 'mcp_audit' );
	}
}
