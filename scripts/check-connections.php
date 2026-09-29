<?php
/**
 * Report on saved connections and re-test them against the live API.
 *
 * Reads what is stored, then calls each integration's own test_connection() so
 * the answer is "Meta accepted this token just now", not "it worked in September".
 * Credentials are never printed.
 *
 * Usage:  wp eval-file wp-content/plugins/zaplane/scripts/check-connections.php [app-slug]
 */

use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Models\Connection;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$filter = $args[0] ?? '';

$rows = Connection::query()->get();
if ( '' !== $filter ) {
	$rows = array_values( array_filter( $rows, fn( $row ) => $filter === $row->app ) );
}

if ( ! $rows ) {
	WP_CLI::warning( '' === $filter ? 'No connections saved on this site.' : sprintf( 'No "%s" connection saved on this site.', $filter ) );
	return;
}

$manager = new ConnectionManager();

foreach ( $rows as $row ) {
	WP_CLI::log( sprintf( '#%d  %s  (%s)', $row->id, $row->name, $row->app ) );
	WP_CLI::log( sprintf( '    stored: status=%s  last tested=%s (%s)',
		$row->status,
		$row->last_tested_at ?: 'never',
		$row->last_test_status ?: 'n/a'
	) );

	try {
		$result = $manager->test( (int) $row->id );
		$line   = $result['success'] ? 'PASS' : 'FAIL';
		WP_CLI::log( sprintf( '    live:   %s — %s', $line, $result['message'] ?? '' ) );

		foreach ( (array) ( $result['details'] ?? [] ) as $key => $value ) {
			if ( is_scalar( $value ) ) {
				WP_CLI::log( sprintf( '            %s: %s', $key, $value ) );
			}
		}
	} catch ( \Throwable $e ) {
		WP_CLI::log( '    live:   ERROR — ' . $e->getMessage() );
	}
}
