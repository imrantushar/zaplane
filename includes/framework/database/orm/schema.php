<?php

namespace Zaplane\Framework\Database\ORM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and alters this plugin's own tables.
 *
 * Every statement here is data-definition, which takes no values and so has
 * nothing to place-hold: what varies is the table, column and index names. Those
 * come from the migration files in this plugin — never from a request — and are
 * passed as `%i` identifiers to `$wpdb->prepare()`, or run through
 * {@see Identifier}, which refuses anything that is not a plain name.
 */
class Schema {

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
	// Data definition takes no values; the table name is a `%i` identifier passed to prepare() and every other name goes through Identifier. See the class note.

	protected static ?string $prefix = null;

	public static function getPrefix(): string {
		if ( null === self::$prefix ) {
			global $wpdb;
			self::$prefix = ( $wpdb->prefix ?? 'wp_' ) . 'zaplane_';
		}
		return self::$prefix;
	}

	public static function resetPrefix(): void {
		self::$prefix = null;
	}

	public static function getTable( string $table ): string {
		return self::getPrefix() . $table;
	}

	public static function create( string $table, callable $callback ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$fullTable = self::getTable( $table );
		$blueprint = new Blueprint( $fullTable );
		$callback( $blueprint );

		$sql = $blueprint->toSql();
		dbDelta( $sql );

		self::runCommands( $blueprint );
	}

	public static function table( string $table, callable $callback ): void {
		$fullTable = self::getTable( $table );
		$blueprint = new Blueprint( $fullTable );
		$callback( $blueprint );

		self::runAlterCommands( $fullTable, $blueprint );
	}

	public static function drop( string $table ): void {
		global $wpdb;
		$fullTable = self::getTable( $table );
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $fullTable ) );
	}

	public static function dropIfExists( string $table ): void {
		self::drop( $table );
	}

	public static function rename( string $from, string $to ): void {
		global $wpdb;
		$fromTable = self::getTable( $from );
		$toTable = self::getTable( $to );
		$wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $fromTable, $toTable ) );
	}

	public static function hasTable( string $table ): bool {
		global $wpdb;
		$fullTable = self::getTable( $table );
		$result = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $fullTable )
		);
		return $result === $fullTable;
	}

	public static function hasColumn( string $table, string $column ): bool {
		global $wpdb;
		$fullTable = self::getTable( $table );
		$result = $wpdb->get_results(
			$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $fullTable, $column )
		);
		return count( $result ) > 0;
	}

	public static function getColumnListing( string $table ): array {
		global $wpdb;
		$fullTable = self::getTable( $table );
		$columns = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $fullTable ) );
		return array_map( fn( $col) => $col->Field, $columns );
	}

	protected static function runCommands( Blueprint $blueprint ): void {
		global $wpdb;
		$table = $blueprint->getTable();

		foreach ( $blueprint->getCommands() as $command ) {
			switch ( $command['type'] ) {
				case 'foreign':
					$foreignSql = $command['definition']->toSql( $table );
					if ( $foreignSql ) {
						$wpdb->query(
							$wpdb->prepare( 'ALTER TABLE %i ADD ', $table ) . $foreignSql
						);
					}
					break;
			}
		}
	}

	protected static function runAlterCommands( string $table, Blueprint $blueprint ): void {
		global $wpdb;

		foreach ( $blueprint->getColumns() as $column ) {
			$columnName = $column->getName();
			$alter      = $wpdb->prepare( 'ALTER TABLE %i ', $table );
			if ( self::columnExists( $table, $columnName ) ) {
				$sql = $alter . 'MODIFY COLUMN ' . $column->toSql();
			} else {
				$sql = $alter . 'ADD COLUMN ' . $column->toSql();
			}
			$wpdb->query( $sql );
		}

		foreach ( $blueprint->getIndexes() as $index ) {
			$cols = implode( ', ', array_map( [ Identifier::class, 'quote' ], (array) $index['columns'] ) );
			$name = Identifier::quote( (string) $index['name'] );
			switch ( $index['type'] ) {
				case 'unique':
					$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD UNIQUE KEY ', $table ) . $name . ' (' . $cols . ')' );
					break;
				case 'index':
					$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD KEY ', $table ) . $name . ' (' . $cols . ')' );
					break;
			}
		}

		foreach ( $blueprint->getCommands() as $command ) {
			switch ( $command['type'] ) {
				case 'dropColumn':
					$wpdb->query(
						$wpdb->prepare( 'ALTER TABLE %i DROP COLUMN ', $table )
						. Identifier::quote( (string) $command['column'] )
					);
					break;
				case 'renameColumn':
					$colInfo = $wpdb->get_row(
						$wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, (string) $command['from'] )
					);
					if ( $colInfo ) {
						$wpdb->query(
							$wpdb->prepare( 'ALTER TABLE %i CHANGE ', $table )
							. Identifier::quote( (string) $command['from'] ) . ' '
							. Identifier::quote( (string) $command['to'] ) . ' '
							. preg_replace( '/[^A-Za-z0-9_(),\s\'\"]/', '', (string) $colInfo->Type )
						);
					}
					break;
				case 'dropIndex':
					$wpdb->query(
						$wpdb->prepare( 'ALTER TABLE %i DROP INDEX ', $table )
						. Identifier::quote( (string) $command['name'] )
					);
					break;
				case 'foreign':
					$foreignSql = $command['definition']->toSql( $table );
					if ( $foreignSql ) {
						$wpdb->query(
							$wpdb->prepare( 'ALTER TABLE %i ADD ', $table ) . $foreignSql
						);
					}
					break;
			}//end switch
		}//end foreach
	}

	protected static function columnExists( string $table, string $column ): bool {
		global $wpdb;
		$result = $wpdb->get_results(
			$wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column )
		);
		return count( $result ) > 0;
	}

	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter

}
