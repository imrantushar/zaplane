<?php

namespace Zaplane\Framework\Database\ORM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DB {

	public static function table( string $table ): QueryBuilder {
		return new QueryBuilder( Schema::getTable( $table ) );
	}

	public static function raw( string $value ): RawExpression {
		return new RawExpression( $value );
	}

	public static function select( string $query, array $bindings = [] ): array {
		global $wpdb;

		if ( ! empty( $bindings ) ) {
			$query = $wpdb->prepare( $query, ...$bindings );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $query, ARRAY_A ) ?: [];
	}

	public static function selectOne( string $query, array $bindings = [] ) {
		global $wpdb;

		if ( ! empty( $bindings ) ) {
			$query = $wpdb->prepare( $query, ...$bindings );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_row( $query, ARRAY_A );
	}

	public static function insert( string $query, array $bindings = [] ): bool {
		global $wpdb;

		if ( ! empty( $bindings ) ) {
			$query = $wpdb->prepare( $query, ...$bindings );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->query( $query ) !== false;
	}

	public static function update( string $query, array $bindings = [] ): int {
		global $wpdb;

		if ( ! empty( $bindings ) ) {
			$query = $wpdb->prepare( $query, ...$bindings );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->query( $query );
	}

	public static function delete( string $query, array $bindings = [] ): int {
		return self::update( $query, $bindings );
	}

	public static function statement( string $query, array $bindings = [] ): bool {
		global $wpdb;

		if ( ! empty( $bindings ) ) {
			$query = $wpdb->prepare( $query, ...$bindings );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->query( $query ) !== false;
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
		$wpdb->query( 'START TRANSACTION' );
	}

	public static function commit(): void {
		global $wpdb;
		$wpdb->query( 'COMMIT' );
	}

	public static function rollBack(): void {
		global $wpdb;
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
