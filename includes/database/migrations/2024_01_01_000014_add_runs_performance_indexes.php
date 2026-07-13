<?php

namespace Zaplane\Database\Migrations;

use Zaplane\Framework\Database\ORM\Migration;
use Zaplane\Framework\Database\ORM\Schema;
use Zaplane\Framework\Database\ORM\Blueprint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds indexes the run-heavy queries rely on:
 *  - started_at: the dashboard's monthly-executions range query (previously a
 *    full table scan).
 *  - (workflow_id, is_test): every latest-test-output lookup in the editor's
 *    variable picker filters on both columns together.
 */
class AddRunsPerformanceIndexes extends Migration {

	public function up(): void {
		Schema::table('runs', function ( Blueprint $table ) {
			$table->index( 'started_at' );
			$table->index( [ 'workflow_id', 'is_test' ] );
		});
	}

	public function down(): void {
		// Indexes are additive and harmless; leave them in place on rollback
		// rather than drop by a prefix-dependent generated name.
		Schema::table('runs', function ( Blueprint $table ) {
			$name = $table->getTable();
			$table->dropIndex( $name . '_started_at_index' );
			$table->dropIndex( $name . '_workflow_id_is_test_index' );
		});
	}
}
