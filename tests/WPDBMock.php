<?php

namespace Zaplane\Tests;

class WPDBMock {

	public string $prefix = 'wp_';
	public ?int $insert_id = null;
	public array $tables = [];
	public array $preparedQueries = [];
	private array $lastQuery = [];
	private int $resultsIndex = 0;

	public function __construct() {
		$this->reset();
	}

	public function reset(): void {
		$this->tables         = [];
		$this->insert_id      = null;
		$this->lastQuery      = [];
		$this->resultsIndex   = 0;
		$this->preparedQueries = [];
	}

	public function setTable( string $name, array $rows ): void {
		$this->tables[ $name ] = $rows;
	}

	public function addRow( string $table, array $row ): void {
		if ( ! isset( $this->tables[ $table ] ) ) {
			$this->tables[ $table ] = [];
		}
		$this->tables[ $table ][] = $row;
	}

	public function prepare( string $query, ...$args ): string {
		$this->lastQuery = [
			'query' => $query,
			'args'  => $args,
		];

		// Like core, a single array argument holds every value.
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$i      = 0;
		$result = preg_replace_callback( '/%[sdi]/', function ( $m ) use ( $args, &$i ) {
			$val = $args[ $i ] ?? '';
			$i++;
			if ( '%i' === $m[0] ) {
				return '`' . str_replace( '`', '``', (string) $val ) . '`';
			}
			return is_string( $val ) ? "'" . addslashes( $val ) . "'" : $val;
		}, $query );

		$this->preparedQueries[] = $result;
		return $result;
	}

	public function get_results( string $query, $output = OBJECT ): array {
		// Support sequential results for tests with multiple queries
		if ( isset( $this->tables['results_sequence'] ) && is_array( $this->tables['results_sequence'] ) ) {
			$results = $this->tables['results_sequence'][ $this->resultsIndex ] ?? [];
			$this->resultsIndex++;
		} else {
			$results = $this->tables['results'] ?? [];
		}

		if ( $output === ARRAY_A ) {
			return array_map(function ( $row ) {
				return is_object( $row ) ? (array) $row : $row;
			}, $results);
		}

		return $results;
	}

	public function get_row( string $query, $output = OBJECT, int $offset = 0 ) {
		$results = $this->tables['row'] ?? null;
		if ( $output === ARRAY_A && is_object( $results ) ) {
			return (array) $results;
		}
		return $results;
	}

	public function get_var( string $query, int $column = 0, int $row = 0 ) {
		return $this->tables['var'] ?? null;
	}

	public function insert( string $table, array $data, $format = null ): int {
		$this->insert_id = $this->tables['next_insert_id'] ?? rand( 1, 1000 );
		$this->addRow( $table, array_merge( [ 'id' => $this->insert_id ], $data ) );
		return 1;
	}

	public function update( string $table, array $data, array $where, $format = null, $where_format = null ) {
		return $this->tables['update_result'] ?? 1;
	}

	public function delete( string $table, array $where, $format = null ): int {
		return $this->tables['delete_result'] ?? 1;
	}

	public function query( string $query ) {
		return $this->tables['query_result'] ?? 1;
	}

	public function get_charset_collate(): string {
		return 'utf8mb4_unicode_520_ci';
	}

	public function get_col( string $query ): array {
		return $this->tables['col'] ?? [];
	}
}
