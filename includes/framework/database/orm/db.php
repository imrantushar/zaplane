<?php

namespace Zaplane\Framework\Database\ORM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin helpers over `$wpdb` for the few statements the query builder cannot
 * express.
 *
 * Each one takes the statement with `%s`/`%d` placeholders already in it and the
 * values separately, and hands both to `$wpdb->prepare()` in the same call that
 * runs the statement — the values never reach the SQL as text. Callers inside
 * this plugin write the statement text themselves; it is never assembled from
 * anything that arrived with a request.
 */
class DB {

	public static function table( string $table ): QueryBuilder {
		return new QueryBuilder( Schema::getTable( $table ) );
	}

	public static function raw( string $value ): RawExpression {
		return new RawExpression( $value );
	}

	public static function select( string $query, array $bindings = [] ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared in this same call; the statement is written in this plugin's own code. See the class note.
		$results = $wpdb->get_results(
			empty( $bindings ) ? $query : $wpdb->prepare( $query, ...$bindings ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return $results ? $results : [];
	}

	public static function selectOne( string $query, array $bindings = [] ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared in this same call; the statement is written in this plugin's own code. See the class note.
		return $wpdb->get_row(
			empty( $bindings ) ? $query : $wpdb->prepare( $query, ...$bindings ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	public static function insert( string $query, array $bindings = [] ): bool {
		return self::statement( $query, $bindings );
	}

	public static function update( string $query, array $bindings = [] ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared in this same call; the statement is written in this plugin's own code. See the class note.
		return (int) $wpdb->query( empty( $bindings ) ? $query : $wpdb->prepare( $query, ...$bindings ) );
	}

	public static function delete( string $query, array $bindings = [] ): int {
		return self::update( $query, $bindings );
	}

	public static function statement( string $query, array $bindings = [] ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared in this same call; the statement is written in this plugin's own code. See the class note.
		return $wpdb->query( empty( $bindings ) ? $query : $wpdb->prepare( $query, ...$bindings ) ) !== false;
	}

	public static function lastInsertId(): int {
		global $wpdb;
		return (int) $wpdb->insert_id;
	}

	public static function getLastError(): string {
		global $wpdb;
		return $wpdb->last_error;
	}

	public static function beginTransaction(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );
	}

	public static function commit(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'COMMIT' );
	}

	public static function rollBack(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'ROLLBACK' );
	}

	public static function transaction( callable $callback ) {
		self::beginTransaction();

		try {
			$result = $callback();
			self::commit();
			return $result;
		} catch ( \Exception $e ) {
			self::rollBack();
			throw $e;
		}
	}

	public static function getPrefix(): string {
		return Schema::getPrefix();
	}
}
