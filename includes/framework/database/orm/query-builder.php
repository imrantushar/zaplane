<?php

namespace Zaplane\Framework\Database\ORM;

use Zaplane\Framework\Exceptions\DatabaseException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QueryBuilder {

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

	public function select( ...$columns ): self {
		$this->columns = $columns ?: [ '*' ];
		return $this;
	}

	public function addSelect( ...$columns ): self {
		if ( $this->columns === [ '*' ] ) {
			$this->columns = [];
		}
		$this->columns = array_merge( $this->columns, $columns );
		return $this;
	}

	public function selectRaw( string $expression ): self {
		if ( $this->columns === [ '*' ] ) {
			$this->columns = [];
		}
		$this->columns[] = new RawExpression( $expression );
		return $this;
	}

	public function where( $column, $operator = null, $value = null ): self {
		if ( is_callable( $column ) ) {
			return $this->whereNested( $column );
		}

		if ( $value === null ) {
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
		if ( $value === null ) {
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
		if ( $column === null && $this->modelClass ) {
			$column = call_user_func( [ $this->modelClass, 'getCreatedAtColumn' ] );
		}
		$column = $column ?? 'created_at';
		return $this->orderByDesc( $column );
	}

	public function oldest( ?string $column = null ): self {
		if ( $column === null && $this->modelClass ) {
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

	public function groupBy( ...$columns ): self {
		$this->groups = array_merge( $this->groups, $columns );
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

		$cacheKey = md5( $sql . serialize( $bindings ) );

		if ( ! $this->skipCache && isset( self::$queryCache[ $cacheKey ] ) ) {
			return clone self::$queryCache[ $cacheKey ];
		}

		if ( ! empty( $bindings ) ) {
			$sql = $wpdb->prepare( $sql, ...$bindings );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $sql, ARRAY_A );

		if ( $this->modelClass && $results ) {
			$collection = new Collection( array_map( fn( $row) => $this->modelClass::hydrate( $row ), $results ) );
		} else {
			$collection = new Collection( $results ?: [] );
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
		if ( $result === null ) {
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

		$this->columns = [ new RawExpression( "COUNT({$column}) as aggregate" ) ];
		$sql = $this->toSql();
		$bindings = $this->getBindings();

		if ( ! empty( $bindings ) ) {
			$sql = $wpdb->prepare( $sql, ...$bindings );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
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

		$this->columns = [ new RawExpression( "{$function}({$column}) as aggregate" ) ];
		$sql = $this->toSql();
		$bindings = $this->getBindings();

		if ( ! empty( $bindings ) ) {
			$sql = $wpdb->prepare( $sql, ...$bindings );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (float) $wpdb->get_var( $sql );
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

		if ( $result === false ) {
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

		$sql = "UPDATE {$this->table} SET ";
		$setParts = [];
		$bindings = [];

		foreach ( $values as $column => $value ) {
			$setParts[] = "{$column} = %s";
			$bindings[] = $value;
		}

		$sql .= implode( ', ', $setParts );
		$sql .= $this->compileWheres();

		$bindings = array_merge( $bindings, $this->bindings );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->query( $wpdb->prepare( $sql, ...$bindings ) );
	}

	public function delete(): int {
		global $wpdb;

		if ( empty( $this->wheres ) ) {
			throw new DatabaseException( 'Cannot delete without where clause' );
		}

		$sql = "DELETE FROM {$this->table}" . $this->compileWheres();
		$bindings = $this->getBindings();

		if ( ! empty( $bindings ) ) {
			$sql = $wpdb->prepare( $sql, ...$bindings );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->query( $sql );
	}

	public function increment( string $column, int $amount = 1, array $extra = [] ): int {
		global $wpdb;

		$sql = "UPDATE {$this->table} SET {$column} = {$column} + %d";
		$bindings = [ $amount ];

		foreach ( $extra as $col => $value ) {
			$sql .= ", {$col} = %s";
			$bindings[] = $value;
		}

		$sql .= $this->compileWheres();
		$bindings = array_merge( $bindings, $this->bindings );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->query( $wpdb->prepare( $sql, ...$bindings ) );
	}

	public function decrement( string $column, int $amount = 1, array $extra = [] ): int {
		return $this->increment( $column, -$amount, $extra );
	}

	public function truncate(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$this->table}" );
	}

	public function toSql(): string {
		$sql = 'SELECT ' . $this->compileColumns() . ' FROM ' . $this->table;

		if ( ! empty( $this->joins ) ) {
			$sql .= $this->compileJoins();
		}

		if ( ! empty( $this->wheres ) ) {
			$sql .= $this->compileWheres();
		}

		if ( ! empty( $this->groups ) ) {
			$sql .= ' GROUP BY ' . implode( ', ', $this->groups );
		}

		if ( ! empty( $this->havings ) ) {
			$sql .= $this->compileHavings();
		}

		if ( ! empty( $this->orders ) ) {
			$sql .= $this->compileOrders();
		}

		if ( $this->limitValue !== null ) {
			$sql .= ' LIMIT ' . $this->limitValue;
		}

		if ( $this->offsetValue !== null ) {
			$sql .= ' OFFSET ' . $this->offsetValue;
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
				$compiled[] = $column;
			}
		}
		return implode( ', ', $compiled );
	}

	protected function compileJoins(): string {
		$sql = '';
		foreach ( $this->joins as $join ) {
			$sql .= ' ' . strtoupper( $join['type'] ) . ' JOIN ' . $join['table'];
			$sql .= ' ON ' . $join['first'] . ' ' . $join['operator'] . ' ' . $join['second'];
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
				$part .= ' ' . $where['boolean'] . ' ';
			}

			switch ( $where['type'] ) {
				case 'basic':
					$part .= $where['column'] . ' ' . $where['operator'] . ' %s';
					break;

				case 'in':
					$placeholders = implode( ', ', array_fill( 0, count( $where['values'] ), '%s' ) );
					$notStr = $where['not'] ? 'NOT ' : '';
					$part .= $where['column'] . ' ' . $notStr . 'IN (' . $placeholders . ')';
					break;

				case 'null':
					$notStr = $where['not'] ? 'NOT ' : '';
					$part .= $where['column'] . ' IS ' . $notStr . 'NULL';
					break;

				case 'between':
					$part .= $where['column'] . ' BETWEEN %s AND %s';
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
			$parts[] = $order['column'] . ' ' . $order['direction'];
		}
		return ' ORDER BY ' . implode( ', ', $parts );
	}

	protected function compileHavings(): string {
		$sql = ' HAVING ';
		$parts = [];

		foreach ( $this->havings as $i => $having ) {
			$part = '';
			if ( $i > 0 ) {
				$part .= ' ' . $having['boolean'] . ' ';
			}
			$part .= $having['column'] . ' ' . $having['operator'] . ' %s';
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
}
