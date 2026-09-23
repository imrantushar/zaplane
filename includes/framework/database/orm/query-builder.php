<?php

namespace Zaplane\Framework\Database\ORM;

use Zaplane\Framework\Exceptions\DatabaseException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assembles statements for the models in this plugin.
 *
 * Two rules hold everywhere in this class, and together they are what keeps the
 * statements it produces safe:
 *
 * 1. Every *value* is a `%s`/`%d` placeholder filled in by `$wpdb->prepare()`.
 *    Values are collected in `$this->bindings` as the query is built and handed
 *    to prepare() in the same method that runs the statement.
 * 2. Every *identifier and keyword* — table, column, operator, sort direction,
 *    join type, aggregate — goes through {@see Identifier}, which accepts plain
 *    names and a fixed list of keywords and throws on anything else. Nothing is
 *    interpolated into SQL without passing it.
 *
 * `selectRaw()` and `whereRaw()` are the deliberate exceptions: they take SQL
 * written in this plugin's own code (with their values still placeheld), and
 * are never given anything that arrived with a request.
 */
class QueryBuilder {

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
	// The statement is assembled by this class: identifiers through Identifier, values as placeholders filled in by prepare() in the same call. See the class note.

	protected string $table;
	protected array $columns = [ '*' ];
	protected array $wheres = [];
	protected array $bindings = [];
	protected array $orders = [];
	protected ?int $limitValue = null;
	protected ?int $offsetValue = null;
	protected array $joins = [];
	protected array $groups = [];
	protected array $havings = [];
	protected ?string $modelClass = null;
	protected static array $queryCache = [];
	protected static int $maxCacheSize = 100;
	protected bool $skipCache = false;

	public function __construct( string $table ) {
		$this->table = $table;
	}

	public function setModel( string $modelClass ): self {
		$this->modelClass = $modelClass;
		return $this;
	}

	/**
	 * Columns to select, as arguments or as one array of them.
	 *
	 * Both spellings are in use — `select( 'id', 'status' )` and
	 * `select( [ 'id', 'status' ] )` — so the arguments are flattened rather
	 * than one nested array being passed on to the compiler as a column.
	 *
	 * @param mixed ...$columns Column names, or arrays of them.
	 */
	public function select( ...$columns ): self {
		$columns = self::flatten( $columns );
		$this->columns = $columns ? $columns : [ '*' ];
		return $this;
	}

	/**
	 * @param mixed ...$columns Column names, or arrays of them.
	 */
	public function addSelect( ...$columns ): self {
		if ( [ '*' ] === $this->columns ) {
			$this->columns = [];
		}
		$this->columns = array_merge( $this->columns, self::flatten( $columns ) );
		return $this;
	}

	/**
	 * One level of nesting removed from a variadic column list.
	 *
	 * @param array<int,mixed> $columns Arguments as received.
	 * @return array<int,mixed>
	 */
	private static function flatten( array $columns ): array {
		$flat = [];

		foreach ( $columns as $column ) {
			if ( is_array( $column ) ) {
				$flat = array_merge( $flat, array_values( $column ) );
				continue;
			}

			$flat[] = $column;
		}

		return $flat;
	}

	public function selectRaw( string $expression ): self {
		if ( [ '*' ] === $this->columns ) {
			$this->columns = [];
		}
		$this->columns[] = new RawExpression( $expression );
		return $this;
	}

	public function where( $column, $operator = null, $value = null ): self {
		// Only a closure is a nested group. A column name can also be the
		// name of a function ("comment_ID" is a WordPress template tag), and
		// is_callable() would call it instead of filtering on the column.
		if ( $column instanceof \Closure ) {
			return $this->whereNested( $column );
		}

		if ( null === $value ) {
			$value = $operator;
			$operator = '=';
		}

		$this->wheres[] = [
			'type' => 'basic',
			'column' => $column,
			'operator' => $operator,
			'value' => $value,
			'boolean' => 'AND',
		];

		$this->bindings[] = $value;

		return $this;
	}

	public function orWhere( $column, $operator = null, $value = null ): self {
		if ( null === $value ) {
			$value = $operator;
			$operator = '=';
		}

		$this->wheres[] = [
			'type' => 'basic',
			'column' => $column,
			'operator' => $operator,
			'value' => $value,
			'boolean' => 'OR',
		];

		$this->bindings[] = $value;

		return $this;
	}

	protected function whereNested( callable $callback ): self {
		$query = new static( $this->table );
		$callback( $query );

		if ( ! empty( $query->wheres ) ) {
			$this->wheres[] = [
				'type' => 'nested',
				'query' => $query,
				'boolean' => 'AND',
			];
			$this->bindings = array_merge( $this->bindings, $query->bindings );
		}

		return $this;
	}

	public function whereIn( string $column, array $values ): self {
		$this->wheres[] = [
			'type' => 'in',
			'column' => $column,
			'values' => $values,
			'boolean' => 'AND',
			'not' => false,
		];

		$this->bindings = array_merge( $this->bindings, $values );

		return $this;
	}

	public function whereNotIn( string $column, array $values ): self {
		$this->wheres[] = [
			'type' => 'in',
			'column' => $column,
			'values' => $values,
			'boolean' => 'AND',
			'not' => true,
		];

		$this->bindings = array_merge( $this->bindings, $values );

		return $this;
	}

	public function whereNull( string $column ): self {
		$this->wheres[] = [
			'type' => 'null',
			'column' => $column,
			'boolean' => 'AND',
			'not' => false,
		];

		return $this;
	}

	public function whereNotNull( string $column ): self {
		$this->wheres[] = [
			'type' => 'null',
			'column' => $column,
			'boolean' => 'AND',
			'not' => true,
		];

		return $this;
	}

	public function whereBetween( string $column, array $values ): self {
		$this->wheres[] = [
			'type' => 'between',
			'column' => $column,
			'values' => $values,
			'boolean' => 'AND',
			'not' => false,
		];

		$this->bindings = array_merge( $this->bindings, $values );

		return $this;
	}

	public function whereRaw( string $sql, array $bindings = [] ): self {
		$this->wheres[] = [
			'type' => 'raw',
			'sql' => $sql,
			'boolean' => 'AND',
		];

		$this->bindings = array_merge( $this->bindings, $bindings );

		return $this;
	}

	public function orderBy( string $column, string $direction = 'asc' ): self {
		$this->orders[] = [
			'column' => $column,
			'direction' => strtoupper( $direction ),
		];

		return $this;
	}

	public function orderByDesc( string $column ): self {
		return $this->orderBy( $column, 'desc' );
	}

	public function latest( ?string $column = null ): self {
		if ( null === $column && $this->modelClass ) {
			$column = call_user_func( [ $this->modelClass, 'getCreatedAtColumn' ] );
		}
		$column = $column ?? 'created_at';
		return $this->orderByDesc( $column );
	}

	public function oldest( ?string $column = null ): self {
		if ( null === $column && $this->modelClass ) {
			$column = call_user_func( [ $this->modelClass, 'getCreatedAtColumn' ] );
		}
		$column = $column ?? 'created_at';
		return $this->orderBy( $column, 'asc' );
	}

	public function limit( int $value ): self {
		$this->limitValue = $value;
		return $this;
	}

	public function take( int $value ): self {
		return $this->limit( $value );
	}

	public function offset( int $value ): self {
		$this->offsetValue = $value;
		return $this;
	}

	public function skip( int $value ): self {
		return $this->offset( $value );
	}

	public function forPage( int $page, int $perPage = 15 ): self {
		return $this->offset( ( $page - 1 ) * $perPage )->limit( $perPage );
	}

	public function join( string $table, string $first, string $operator, string $second, string $type = 'inner' ): self {
		$this->joins[] = [
			'table' => $table,
			'first' => $first,
			'operator' => $operator,
			'second' => $second,
			'type' => $type,
		];

		return $this;
	}

	public function leftJoin( string $table, string $first, string $operator, string $second ): self {
		return $this->join( $table, $first, $operator, $second, 'left' );
	}

	public function rightJoin( string $table, string $first, string $operator, string $second ): self {
		return $this->join( $table, $first, $operator, $second, 'right' );
	}

	/**
	 * @param mixed ...$columns Column names, or arrays of them.
	 */
	public function groupBy( ...$columns ): self {
		$this->groups = array_merge( $this->groups, self::flatten( $columns ) );
		return $this;
	}

	public function having( string $column, string $operator, $value ): self {
		$this->havings[] = [
			'column' => $column,
			'operator' => $operator,
			'value' => $value,
			'boolean' => 'AND',
		];

		$this->bindings[] = $value;

		return $this;
	}



	public function fresh(): self {
		$this->skipCache = true;
		return $this;
	}

	public function get(): Collection {
		global $wpdb;

		$sql = $this->toSql();
		$bindings = $this->getBindings();

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Used for cache key generation only.
		$cacheKey = md5( $sql . serialize( $bindings ) );

		if ( ! $this->skipCache && isset( self::$queryCache[ $cacheKey ] ) ) {
			return clone self::$queryCache[ $cacheKey ];
		}

		$results = $wpdb->get_results(
			empty( $bindings ) ? $sql : $wpdb->prepare( $sql, ...$bindings ),
			ARRAY_A
		);

		if ( $this->modelClass && $results ) {
			$collection = new Collection( array_map( fn( $row) => $this->modelClass::hydrate( $row ), $results ) );
		} else {
			$collection = new Collection( $results ? $results : [] );
		}

		if ( ! $this->skipCache ) {
			if ( count( self::$queryCache ) >= self::$maxCacheSize ) {
				array_shift( self::$queryCache );
			}
			self::$queryCache[ $cacheKey ] = clone $collection;
		}

		return $collection;
	}



	public function collect(): Collection {
		return $this->get();
	}

	public function first() {
		$this->limit( 1 );
		$results = $this->get();
		return $results->first();
	}

	public function find( int $id ) {
		return $this->where( 'id', $id )->first();
	}

	public function findOrFail( int $id ) {
		$result = $this->find( $id );
		if ( null === $result ) {
			throw DatabaseException::recordNotFound( esc_html( $this->table ), esc_html( (string) $id ) );
		}
		return $result;
	}

	public function value( string $column ) {

		$originalModel = $this->modelClass;
		$this->modelClass = null;
		$result = $this->select( $column )->first();
		$this->modelClass = $originalModel;
		return $result[ $column ] ?? null;
	}

	public function pluck( string $column, ?string $key = null ): Collection {

		$originalModel = $this->modelClass;
		$this->modelClass = null;
		$results = $this->select( $key ? [ $column, $key ] : [ $column ] )->get();
		$this->modelClass = $originalModel;

		if ( $key ) {
			$plucked = [];
			foreach ( $results->all() as $row ) {
				$plucked[ $row[ $key ] ] = $row[ $column ];
			}
			return new Collection( $plucked );
		}

		return new Collection( array_column( $results->all(), $column ) );
	}

	public function count( string $column = '*' ): int {
		global $wpdb;

		$this->columns = [ new RawExpression( 'COUNT(' . Identifier::quote( $column ) . ') as aggregate' ) ];
		$sql = $this->toSql();
		$bindings = $this->getBindings();

		return (int) $wpdb->get_var( empty( $bindings ) ? $sql : $wpdb->prepare( $sql, ...$bindings ) );
	}

	public function sum( string $column ): float {
		return $this->aggregate( 'SUM', $column );
	}

	public function avg( string $column ): float {
		return $this->aggregate( 'AVG', $column );
	}

	public function max( string $column ) {
		return $this->aggregate( 'MAX', $column );
	}

	public function min( string $column ) {
		return $this->aggregate( 'MIN', $column );
	}

	protected function aggregate( string $function, string $column ): float {
		global $wpdb;

		$this->columns = [
			new RawExpression(
				Identifier::aggregate( $function ) . '(' . Identifier::quote( $column ) . ') as aggregate'
			),
		];
		$sql = $this->toSql();
		$bindings = $this->getBindings();

		return (float) $wpdb->get_var( empty( $bindings ) ? $sql : $wpdb->prepare( $sql, ...$bindings ) );
	}

	public function exists(): bool {
		return $this->count() > 0;
	}

	public function doesntExist(): bool {
		return ! $this->exists();
	}

	public function insert( array $values ): int {
		global $wpdb;

		$result = $wpdb->insert( $this->table, $values );

		if ( false === $result ) {
			throw DatabaseException::insertFailed( esc_html( $this->table ), esc_html( $wpdb->last_error ) );
		}

		return $wpdb->insert_id;
	}

	public function insertGetId( array $values ): int {
		return $this->insert( $values );
	}

	public function update( array $values ): int {
		global $wpdb;

		if ( empty( $this->wheres ) ) {
			throw new DatabaseException( 'Cannot update without where clause' );
		}

		$sql = 'UPDATE ' . Identifier::quote( $this->table ) . ' SET ';
		$setParts = [];
		$bindings = [];

		foreach ( $values as $column => $value ) {
			$setParts[] = Identifier::quote( (string) $column ) . ' = %s';
			$bindings[] = $value;
		}

		$sql .= implode( ', ', $setParts );
		$sql .= $this->compileWheres();

		$bindings = array_merge( $bindings, $this->bindings );

		return (int) $wpdb->query( $wpdb->prepare( $sql, ...$bindings ) );
	}

	public function delete(): int {
		global $wpdb;

		if ( empty( $this->wheres ) ) {
			throw new DatabaseException( 'Cannot delete without where clause' );
		}

		$sql = 'DELETE FROM ' . Identifier::quote( $this->table ) . $this->compileWheres();
		$bindings = $this->getBindings();

		return (int) $wpdb->query( empty( $bindings ) ? $sql : $wpdb->prepare( $sql, ...$bindings ) );
	}

	public function increment( string $column, int $amount = 1, array $extra = [] ): int {
		global $wpdb;

		$quoted   = Identifier::quote( $column );
		$sql      = 'UPDATE ' . Identifier::quote( $this->table ) . ' SET ' . $quoted . ' = ' . $quoted . ' + %d';
		$bindings = [ $amount ];

		foreach ( $extra as $col => $value ) {
			$sql .= ', ' . Identifier::quote( (string) $col ) . ' = %s';
			$bindings[] = $value;
		}

		$sql .= $this->compileWheres();
		$bindings = array_merge( $bindings, $this->bindings );

		return (int) $wpdb->query( $wpdb->prepare( $sql, ...$bindings ) );
	}

	public function decrement( string $column, int $amount = 1, array $extra = [] ): int {
		return $this->increment( $column, -$amount, $extra );
	}

	public function truncate(): void {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . Identifier::quote( $this->table ) );
	}

	public function toSql(): string {
		$sql = 'SELECT ' . $this->compileColumns() . ' FROM ' . Identifier::quote( $this->table );

		if ( ! empty( $this->joins ) ) {
			$sql .= $this->compileJoins();
		}

		if ( ! empty( $this->wheres ) ) {
			$sql .= $this->compileWheres();
		}

		if ( ! empty( $this->groups ) ) {
			$sql .= ' GROUP BY ' . implode( ', ', array_map( [ Identifier::class, 'quote' ], $this->groups ) );
		}

		if ( ! empty( $this->havings ) ) {
			$sql .= $this->compileHavings();
		}

		if ( ! empty( $this->orders ) ) {
			$sql .= $this->compileOrders();
		}

		if ( null !== $this->limitValue ) {
			$sql .= ' LIMIT ' . (int) $this->limitValue;
		}

		if ( null !== $this->offsetValue ) {
			$sql .= ' OFFSET ' . (int) $this->offsetValue;
		}

		return $sql;
	}

	public function getBindings(): array {
		return $this->bindings;
	}

	protected function compileColumns(): string {
		$compiled = [];
		foreach ( $this->columns as $column ) {
			if ( $column instanceof RawExpression ) {
				$compiled[] = $column->getValue();
			} else {
				$compiled[] = Identifier::quote( (string) $column );
			}
		}
		return implode( ', ', $compiled );
	}

	protected function compileJoins(): string {
		$sql = '';
		foreach ( $this->joins as $join ) {
			$sql .= ' ' . Identifier::join_type( (string) $join['type'] ) . ' JOIN ' . Identifier::quote( (string) $join['table'] );
			$sql .= ' ON ' . Identifier::quote( (string) $join['first'] )
				. ' ' . Identifier::operator( (string) $join['operator'] )
				. ' ' . Identifier::quote( (string) $join['second'] );
		}
		return $sql;
	}

	protected function compileWheres(): string {
		if ( empty( $this->wheres ) ) {
			return '';
		}

		$sql = ' WHERE ';
		$parts = [];

		foreach ( $this->wheres as $i => $where ) {
			$part = '';

			if ( $i > 0 ) {
				$part .= 'OR' === strtoupper( (string) $where['boolean'] ) ? ' OR ' : ' AND ';
			}

			switch ( $where['type'] ) {
				case 'basic':
					$part .= Identifier::quote( (string) $where['column'] )
						. ' ' . Identifier::operator( (string) $where['operator'] ) . ' %s';
					break;

				case 'in':
					$placeholders = implode( ', ', array_fill( 0, count( $where['values'] ), '%s' ) );
					$notStr = $where['not'] ? 'NOT ' : '';
					$part .= Identifier::quote( (string) $where['column'] ) . ' ' . $notStr . 'IN (' . $placeholders . ')';
					break;

				case 'null':
					$notStr = $where['not'] ? 'NOT ' : '';
					$part .= Identifier::quote( (string) $where['column'] ) . ' IS ' . $notStr . 'NULL';
					break;

				case 'between':
					$part .= Identifier::quote( (string) $where['column'] ) . ' BETWEEN %s AND %s';
					break;

				case 'raw':
					$part .= $where['sql'];
					break;

				case 'nested':
					$nestedSql = $where['query']->compileWheres();
					$nestedSql = preg_replace( '/^\s*WHERE\s*/i', '', $nestedSql );
					$part .= '(' . $nestedSql . ')';
					break;
			}//end switch

			$parts[] = $part;
		}//end foreach

		return $sql . implode( '', $parts );
	}

	protected function compileOrders(): string {
		$parts = [];
		foreach ( $this->orders as $order ) {
			$parts[] = Identifier::quote( (string) $order['column'] ) . ' ' . Identifier::direction( (string) $order['direction'] );
		}
		return ' ORDER BY ' . implode( ', ', $parts );
	}

	protected function compileHavings(): string {
		$sql = ' HAVING ';
		$parts = [];

		foreach ( $this->havings as $i => $having ) {
			$part = '';
			if ( $i > 0 ) {
				$part .= 'OR' === strtoupper( (string) $having['boolean'] ) ? ' OR ' : ' AND ';
			}
			$part .= Identifier::quote( (string) $having['column'] )
				. ' ' . Identifier::operator( (string) $having['operator'] ) . ' %s';
			$parts[] = $part;
		}

		return $sql . implode( '', $parts );
	}

	public function newQuery(): self {
		return new static( $this->table );
	}

	public function clone(): self {
		return clone $this;
	}

	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter

}
