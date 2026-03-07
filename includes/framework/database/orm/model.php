<?php

namespace Zaplane\Framework\Database\ORM;

use Zaplane\Framework\Exceptions\DatabaseException;
use JsonSerializable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Model implements JsonSerializable {

	protected static string $table = '';
	protected static string $primaryKey = 'id';
	protected static array $fillable = [];
	protected static array $guarded = [ 'id' ];
	protected static array $hidden = [];
	protected static array $casts = [];
	protected static bool $timestamps = true;
	protected static string $createdAt = 'created_at';
	protected static string $updatedAt = 'updated_at';

	protected array $attributes = [];
	protected array $original = [];
	protected bool $exists = false;

	public function __construct( array $attributes = [] ) {
		$this->fill( $attributes );
	}

	public static function getTable(): string {
		if ( empty( static::$table ) ) {
			$className = ( new \ReflectionClass( static::class ) )->getShortName();
			$snakeCase = strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $className ) );
			static::$table = $snakeCase . 's';
		}
		return Schema::getTable( static::$table );
	}

	public static function getCreatedAtColumn(): string {
		return static::$createdAt;
	}

	public static function getUpdatedAtColumn(): string {
		return static::$updatedAt;
	}

	public static function query(): QueryBuilder {
		$query = new QueryBuilder( static::getTable() );
		$query->setModel( static::class );
		return $query;
	}

	public static function all(): Collection {
		return static::query()->get();
	}



	public static function collect(): Collection {
		return static::all();
	}


	public static function find( int $id ): ?self {
		$result = static::query()->where( static::$primaryKey, $id )->first();
		return $result;
	}


	public static function findOrFail( int $id ): self {
		$result = static::find( $id );
		if ( $result === null ) {
			throw DatabaseException::recordNotFound( static::getTable(), $id );
		}
		return $result;
	}

	public static function findMany( array $ids ): Collection {
		if ( empty( $ids ) ) {
			return new Collection( [] );
		}
		return static::query()->whereIn( static::$primaryKey, $ids )->get();
	}


	public static function first(): ?self {
		return static::query()->first();
	}


	public static function create( array $attributes ): self {
		$model = new static( $attributes );
		$model->save();
		return $model;
	}


	public static function updateOrCreate( array $attributes, array $values = [] ): self {
		$query = static::query();

		foreach ( $attributes as $key => $value ) {
			$query->where( $key, $value );
		}

		$instance = $query->first();

		if ( $instance ) {
			$instance->fill( $values );
			$instance->save();
			return $instance;
		}

		return static::create( array_merge( $attributes, $values ) );
	}


	public static function firstOrCreate( array $attributes, array $values = [] ): self {
		$query = static::query();

		foreach ( $attributes as $key => $value ) {
			$query->where( $key, $value );
		}

		$instance = $query->first();

		if ( $instance ) {
			return $instance;
		}

		return static::create( array_merge( $attributes, $values ) );
	}


	public static function firstOrNew( array $attributes, array $values = [] ): self {
		$query = static::query();

		foreach ( $attributes as $key => $value ) {
			$query->where( $key, $value );
		}

		$instance = $query->first();

		if ( $instance ) {
			return $instance;
		}

		return new static( array_merge( $attributes, $values ) );
	}

	public static function destroy( $ids ): int {
		$ids = is_array( $ids ) ? $ids : func_get_args();
		$count = 0;

		foreach ( $ids as $id ) {
			$model = static::find( $id );
			if ( $model && $model->delete() ) {
				$count++;
			}
		}

		return $count;
	}

	public static function where( $column, $operator = null, $value = null ): QueryBuilder {
		return static::query()->where( $column, $operator, $value );
	}

	public static function whereIn( string $column, array $values ): QueryBuilder {
		return static::query()->whereIn( $column, $values );
	}

	public static function whereNull( string $column ): QueryBuilder {
		return static::query()->whereNull( $column );
	}

	public static function whereNotNull( string $column ): QueryBuilder {
		return static::query()->whereNotNull( $column );
	}

	public static function orderBy( string $column, string $direction = 'asc' ): QueryBuilder {
		return static::query()->orderBy( $column, $direction );
	}

	public static function latest( string $column = 'created_at' ): QueryBuilder {
		return static::query()->latest( $column );
	}

	public static function oldest( string $column = 'created_at' ): QueryBuilder {
		return static::query()->oldest( $column );
	}

	public static function count(): int {
		return static::query()->count();
	}


	public static function hydrate( array $attributes ): self {
		$model = new static();
		$model->attributes = $attributes;
		$model->original = $attributes;
		$model->exists = true;
		$model->castAttributes();
		return $model;
	}


	public function fill( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			if ( $this->isFillable( $key ) ) {
				$this->setAttribute( $key, $value );
			}
		}
		return $this;
	}


	public function forceFill( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->setAttribute( $key, $value );
		}
		return $this;
	}

	protected function isFillable( string $key ): bool {
		if ( in_array( $key, static::$guarded ) ) {
			return false;
		}

		if ( empty( static::$fillable ) ) {
			return true;
		}

		return in_array( $key, static::$fillable );
	}

	public function save(): bool {
		if ( $this->exists ) {
			return $this->performUpdate();
		}

		return $this->performInsert();
	}

	protected function performInsert(): bool {
		global $wpdb;

		if ( static::$timestamps ) {
			$now = current_time( 'mysql' );
			if ( ! isset( $this->attributes[ static::$createdAt ] ) ) {
				$this->attributes[ static::$createdAt ] = $now;
			}
			if ( ! isset( $this->attributes[ static::$updatedAt ] ) ) {
				$this->attributes[ static::$updatedAt ] = $now;
			}
		}

		$attributes = $this->prepareAttributesForSave();

		$result = $wpdb->insert( static::getTable(), $attributes );

		if ( $result === false ) {
			throw DatabaseException::insertFailed( static::getTable(), $wpdb->last_error );
		}

		$this->attributes[ static::$primaryKey ] = $wpdb->insert_id;
		$this->exists = true;
		$this->original = $this->attributes;

		return true;
	}

	protected function performUpdate(): bool {
		global $wpdb;

		$dirty = $this->getDirty();

		if ( empty( $dirty ) ) {
			return true;
		}

		if ( static::$timestamps ) {
			$dirty[ static::$updatedAt ] = current_time( 'mysql' );
			$this->attributes[ static::$updatedAt ] = $dirty[ static::$updatedAt ];
		}

		$attributes = $this->prepareAttributesForSave( $dirty );

		$result = $wpdb->update(
			static::getTable(),
			$attributes,
			[ static::$primaryKey => $this->getKey() ]
		);

		if ( $result === false ) {
			throw new DatabaseException( 'Failed to update record: ' . $wpdb->last_error );
		}

		$this->original = $this->attributes;

		return true;
	}

	protected function prepareAttributesForSave( ?array $attributes = null ): array {
		$attrs = $attributes ?? $this->attributes;
		$prepared = [];

		foreach ( $attrs as $key => $value ) {
			if ( $key === static::$primaryKey && ! isset( $attributes ) ) {
				continue;
			}
			$prepared[ $key ] = $this->castAttributeForSave( $key, $value );
		}

		return $prepared;
	}

	protected function castAttributeForSave( string $key, $value ) {
		if ( ! isset( static::$casts[ $key ] ) ) {
			return $value;
		}

		$castType = static::$casts[ $key ];

		switch ( $castType ) {
			case 'array':
			case 'json':
				return is_string( $value ) ? $value : json_encode( $value );
			case 'boolean':
			case 'bool':
				return $value ? 1 : 0;
			case 'integer':
			case 'int':
				return (int) $value;
			case 'float':
			case 'double':
				return (float) $value;
			default:
				return $value;
		}
	}

	public function delete(): bool {
		global $wpdb;

		if ( ! $this->exists ) {
			return false;
		}

		$result = $wpdb->delete(
			static::getTable(),
			[ static::$primaryKey => $this->getKey() ]
		);

		if ( $result === false ) {
			return false;
		}

		$this->exists = false;

		return true;
	}


	public function refresh(): self {
		if ( ! $this->exists ) {
			return $this;
		}

		$fresh = static::find( $this->getKey() );

		if ( $fresh ) {
			$this->attributes = $fresh->attributes;
			$this->original = $fresh->original;
		}

		return $this;
	}


	public function replicate( array $except = [] ): self {
		$attributes = $this->attributes;

		unset( $attributes[ static::$primaryKey ] );

		if ( static::$timestamps ) {
			unset( $attributes[ static::$createdAt ] );
			unset( $attributes[ static::$updatedAt ] );
		}

		foreach ( $except as $key ) {
			unset( $attributes[ $key ] );
		}

		$model = new static( $attributes );

		return $model;
	}

	public function getKey() {
		return $this->getAttribute( static::$primaryKey );
	}

	public function getAttribute( string $key ) {
		if ( ! array_key_exists( $key, $this->attributes ) ) {
			return null;
		}

		$value = $this->attributes[ $key ];

		if ( isset( static::$casts[ $key ] ) ) {
			return $this->castAttribute( $key, $value );
		}

		return $value;
	}


	public function setAttribute( string $key, $value ): self {
		$this->attributes[ $key ] = $value;
		return $this;
	}

	protected function castAttribute( string $key, $value ) {
		if ( $value === null ) {
			return null;
		}

		$castType = static::$casts[ $key ];

		switch ( $castType ) {
			case 'int':
			case 'integer':
				return (int) $value;
			case 'float':
			case 'double':
				return (float) $value;
			case 'string':
				return (string) $value;
			case 'bool':
			case 'boolean':
				return (bool) $value;
			case 'array':
			case 'json':
				return is_string( $value ) ? json_decode( $value, true ) : $value;
			case 'datetime':
				return $value;
			default:
				return $value;
		}
	}

	protected function castAttributes(): void {
		foreach ( static::$casts as $key => $type ) {
			if ( isset( $this->attributes[ $key ] ) ) {
				$this->attributes[ $key ] = $this->castAttribute( $key, $this->attributes[ $key ] );
			}
		}
	}

	public function getDirty(): array {
		$dirty = [];

		foreach ( $this->attributes as $key => $value ) {
			if ( ! array_key_exists( $key, $this->original ) ) {
				$dirty[ $key ] = $value;
			} elseif ( $value !== $this->original[ $key ] ) {
				$dirty[ $key ] = $value;
			}
		}

		return $dirty;
	}

	public function isDirty( $attributes = null ): bool {
		$dirty = $this->getDirty();

		if ( $attributes === null ) {
			return ! empty( $dirty );
		}

		$attributes = is_array( $attributes ) ? $attributes : [ $attributes ];

		foreach ( $attributes as $attribute ) {
			if ( array_key_exists( $attribute, $dirty ) ) {
				return true;
			}
		}

		return false;
	}

	public function isClean( $attributes = null ): bool {
		return ! $this->isDirty( $attributes );
	}

	public function wasChanged( $attributes = null ): bool {
		return $this->isDirty( $attributes );
	}

	public function getOriginal( ?string $key = null ) {
		if ( $key === null ) {
			return $this->original;
		}

		return $this->original[ $key ] ?? null;
	}

	public function toArray(): array {
		$array = [];

		foreach ( $this->attributes as $key => $value ) {
			if ( ! in_array( $key, static::$hidden ) ) {
				$array[ $key ] = $this->getAttribute( $key );
			}
		}

		return $array;
	}



	public function only( $keys ): array {
		$keys = is_array( $keys ) ? $keys : func_get_args();
		$array = [];

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $this->attributes ) ) {
				$array[ $key ] = $this->getAttribute( $key );
			}
		}

		return $array;
	}



	public function except( $keys ): array {
		$keys = is_array( $keys ) ? $keys : func_get_args();

		return array_diff_key( $this->toArray(), array_flip( $keys ) );
	}



	public function toCollection(): Collection {
		return new Collection( $this->toArray() );
	}

	public function jsonSerialize(): array {
		return $this->toArray();
	}

	public function toJson( int $options = 0 ): string {
		return json_encode( $this->jsonSerialize(), $options );
	}

	public function __get( string $key ) {
		return $this->getAttribute( $key );
	}

	public function __set( string $key, $value ): void {
		$this->setAttribute( $key, $value );
	}

	public function __isset( string $key ): bool {
		return isset( $this->attributes[ $key ] );
	}

	public function __unset( string $key ): void {
		unset( $this->attributes[ $key ] );
	}

	public function exists(): bool {
		return $this->exists;
	}
}
