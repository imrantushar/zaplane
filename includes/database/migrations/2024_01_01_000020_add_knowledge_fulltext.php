<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Models\Knowledge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add a FULLTEXT index on knowledge(title, content) so keyword retrieval can use
 * an indexed MATCH…AGAINST lookup instead of scanning + scoring every row in PHP.
 * Purely an accelerator: if the index is absent (older MySQL, ALTER denied) the
 * retrieval code falls back to the row scan, so this is safe to skip.
 */
class AddKnowledgeFulltext extends Migration {

	public function up(): void {
		global $wpdb;
		$table = Knowledge::getTable();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$exists = $wpdb->get_var( "SHOW INDEX FROM {$table} WHERE Key_name = 'kb_fulltext'" );
		if ( $exists ) {
			return;
		}

		// FULLTEXT requires InnoDB (MySQL 5.6+) or MyISAM. Guarded so a failure
		// (e.g. unsupported engine) doesn't abort the migration batch.
		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "ALTER TABLE {$table} ADD FULLTEXT kb_fulltext (title, content)" );
		$wpdb->suppress_errors( $suppress );
	}

	public function down(): void {
		global $wpdb;
		$table = Knowledge::getTable();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "ALTER TABLE {$table} DROP INDEX kb_fulltext" );
	}
}
