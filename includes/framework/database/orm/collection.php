<?php

namespace Zaplane\Framework\Database\ORM;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable {

	protected array $items = [];

	public function __construct( array $items = [] ) {
		$this->items = $items;
	}

	public static function make( array $items = [] ): self {
		return new static( $items );
	}

	public static function wrap( $value ): self {
		if ( $value instanceof self ) {
			return $value;
		}

		return new static( is_array( $value ) ? $value : [ $value ] );
	}

	public function all(): array {
		return $this->items;
	}

	public function toArray(): array {
		return array_map(function ( $item ) {
			if ( $item instanceof Model ) {
				return $item->toArray();
			}
			if ( $item instanceof self ) {
				return $item->toArray();
			}
			if ( is_object( $item ) && method_exists( $item, 'toArray' ) ) {
				return $item->toArray();
			}
			return $item;
		}, $this->items);
	}

	public function toJson( int $options = 0 ): string {
		return wp_json_encode( $this->toArray(), $options );
	}

	public function jsonSerialize(): array {
		return $this->toArray();
	}

	public function count(): int {
		return count( $this->items );
	}

	public function isEmpty(): bool {
		return empty( $this->items );
	}

	public function isNotEmpty(): bool {
		return ! $this->isEmpty();
	}

	public function first( ?callable $callback = null, $default = null ) {
		if ( null === $callback ) {
			return $this->items[0] ?? $default;
		}

		foreach ( $this->items as $key => $item ) {
			if ( $callback( $item, $key ) ) {
				return $item;
			}
		}

		return $default;
	}

	public function last( ?callable $callback = null, $default = null ) {
		if ( null === $callback ) {
			return ! empty( $this->items ) ? $this->items[ array_key_last( $this->items ) ] : $default;
		}

		return $this->reverse()->first( $callback, $default );
	}

	public function get( $key, $default = null ) {
		return $this->items[ $key ] ?? $default;
	}

	public function has( $key ): bool {
		return array_key_exists( $key, $this->items );
	}

	public function keys(): self {
		return new static( array_keys( $this->items ) );
	}

	public function values(): self {
		return new static( array_values( $this->items ) );
	}

	public function map( callable $callback ): self {
		$result = [];
		foreach ( $this->items as $key => $item ) {
			$result[ $key ] = $callback( $item, $key );
		}
		return new static( $result );
	}

	public function mapWithKeys( callable $callback ): self {
		$result = [];
		foreach ( $this->items as $key => $item ) {
			$mapped = $callback( $item, $key );
			foreach ( $mapped as $mapKey => $mapValue ) {
				$result[ $mapKey ] = $mapValue;
			}
		}
		return new static( $result );
	}

	public function each( callable $callback ): self {
		foreach ( $this->items as $key => $item ) {
			if ( $callback( $item, $key ) === false ) {
				break;
			}
		}
		return $this;
	}

	public function filter( ?callable $callback = null ): self {
		if ( null === $callback ) {
			return new static( array_filter( $this->items ) );
		}

		return new static( array_filter( $this->items, $callback, ARRAY_FILTER_USE_BOTH ) );
	}

	public function reject( callable $callback ): self {
		return $this->filter(function ( $item, $key ) use ( $callback ) {
			return ! $callback( $item, $key );
		});
	}

	public function where( string $key, $operator = null, $value = null ): self {
		if ( func_num_args() === 2 ) {
			$value = $operator;
			$operator = '=';
		}

		return $this->filter(function ( $item ) use ( $key, $operator, $value ) {
			$itemValue = $this->getItemValue( $item, $key );

			switch ( $operator ) {
				case '=':
				case '==':
					// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- Intentional loose comparison for flexible where().
					return $itemValue == $value;
				case '===':
					return $itemValue === $value;
				case '!=':
				case '<>':
					// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- Intentional loose comparison for flexible where().
					return $itemValue != $value;
				case '!==':
					return $itemValue !== $value;
				case '<':
					return $itemValue < $value;
				case '<=':
					return $itemValue <= $value;
				case '>':
					return $itemValue > $value;
				case '>=':
					return $itemValue >= $value;
				default:
					// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- Intentional fallback uses loose comparison.
					return $itemValue == $value;
			}//end switch
		});
	}

	public function whereIn( string $key, array $values ): self {
		return $this->filter(function ( $item ) use ( $key, $values ) {
			return in_array( $this->getItemValue( $item, $key ), $values, true );
		});
	}

	public function whereNotIn( string $key, array $values ): self {
		return $this->filter(function ( $item ) use ( $key, $values ) {
			return ! in_array( $this->getItemValue( $item, $key ), $values, true );
		});
	}

	public function whereNull( string $key ): self {
		return $this->where( $key, '===', null );
	}

	public function whereNotNull( string $key ): self {
		return $this->where( $key, '!==', null );
	}

	public function whereBetween( string $key, array $values ): self {
		return $this->filter(function ( $item ) use ( $key, $values ) {
			$itemValue = $this->getItemValue( $item, $key );
			return $itemValue >= $values[0] && $itemValue <= $values[1];
		});
	}

	protected function getItemValue( $item, string $key ) {
		if ( $item instanceof Model ) {
			return $item->{$key};
		}

		if ( is_array( $item ) ) {
			return $item[ $key ] ?? null;
		}

		if ( is_object( $item ) ) {
			return $item->{$key} ?? null;
		}

		return null;
	}

	public function pluck( string $value, ?string $key = null ): self {
		$results = [];

		foreach ( $this->items as $item ) {
			$itemValue = $this->getItemValue( $item, $value );

			if ( null !== $key ) {
				$itemKey = $this->getItemValue( $item, $key );
				$results[ $itemKey ] = $itemValue;
			} else {
				$results[] = $itemValue;
			}
		}

		return new static( $results );
	}

	public function groupBy( string $key ): self {
		$results = [];

		foreach ( $this->items as $item ) {
			$groupKey = $this->getItemValue( $item, $key );
			$results[ $groupKey ][] = $item;
		}

		return new static( array_map( fn( $items) => new static( $items ), $results ) );
	}

	public function keyBy( string $key ): self {
		$results = [];

		foreach ( $this->items as $item ) {
			$itemKey = $this->getItemValue( $item, $key );
			$results[ $itemKey ] = $item;
		}

		return new static( $results );
	}

	public function sortBy( $callback, bool $descending = false ): self {
		$results = $this->items;

		if ( is_string( $callback ) ) {
			$key = $callback;
			$callback = fn( $item) => $this->getItemValue( $item, $key );
		}

		uasort($results, function ( $a, $b ) use ( $callback, $descending ) {
			$aVal = $callback( $a );
			$bVal = $callback( $b );

			// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- Generic sort comparison.
			if ( $aVal == $bVal ) {
				return 0;
			}

			$result = $aVal < $bVal ? -1 : 1;

			return $descending ? -$result : $result;
		});

		return new static( array_values( $results ) );
	}

	public function sortByDesc( $callback ): self {
		return $this->sortBy( $callback, true );
	}

	public function sort( ?callable $callback = null ): self {
		$items = $this->items;

		if ( $callback ) {
			uasort( $items, $callback );
		} else {
			asort( $items );
		}

		return new static( array_values( $items ) );
	}

	public function reverse(): self {
		return new static( array_reverse( $this->items, true ) );
	}

	public function unique( ?string $key = null ): self {
		if ( null === $key ) {
			return new static( array_unique( $this->items, SORT_REGULAR ) );
		}

		$seen = [];
		$result = [];

		foreach ( $this->items as $item ) {
			$itemKey = $this->getItemValue( $item, $key );

			if ( ! in_array( $itemKey, $seen, true ) ) {
				$seen[] = $itemKey;
				$result[] = $item;
			}
		}

		return new static( $result );
	}

	public function take( int $limit ): self {
		if ( $limit < 0 ) {
			return new static( array_slice( $this->items, $limit ) );
		}

		return new static( array_slice( $this->items, 0, $limit ) );
	}

	public function skip( int $count ): self {
		return new static( array_slice( $this->items, $count ) );
	}

	public function slice( int $offset, ?int $length = null ): self {
		return new static( array_slice( $this->items, $offset, $length, true ) );
	}

	public function chunk( int $size ): self {
		if ( $size <= 0 ) {
			return new static( [] );
		}

		return new static(array_map(
			fn( $chunk) => new static( $chunk ),
			array_chunk( $this->items, $size, true )
		));
	}

	public function collapse(): self {
		$results = [];

		foreach ( $this->items as $item ) {
			if ( $item instanceof self ) {
				$item = $item->all();
			}

			if ( is_array( $item ) ) {
				$results = array_merge( $results, $item );
			} else {
				$results[] = $item;
			}
		}

		return new static( $results );
	}

	public function flatten( int $depth = INF ): self {
		return new static( $this->flattenArray( $this->items, $depth ) );
	}

	protected function flattenArray( array $array, int $depth ): array {
		$result = [];

		foreach ( $array as $item ) {
			$item = $item instanceof self ? $item->all() : $item;

			if ( ! is_array( $item ) ) {
				$result[] = $item;
			} elseif ( 1 === $depth ) {
				$result = array_merge( $result, array_values( $item ) );
			} else {
				$result = array_merge( $result, $this->flattenArray( $item, $depth - 1 ) );
			}
		}

		return $result;
	}

	public function merge( $items ): self {
		if ( $items instanceof self ) {
			$items = $items->all();
		}

		return new static( array_merge( $this->items, $items ) );
	}

	public function concat( $items ): self {
		if ( $items instanceof self ) {
			$items = $items->all();
		}

		$result = $this->items;

		foreach ( $items as $item ) {
			$result[] = $item;
		}

		return new static( $result );
	}

	public function push( ...$values ): self {
		foreach ( $values as $value ) {
			$this->items[] = $value;
		}

		return $this;
	}

	public function pop() {
		return array_pop( $this->items );
	}

	public function prepend( $value, $key = null ): self {
		if ( null !== $key ) {
			$this->items = [ $key => $value ] + $this->items;
		} else {
			array_unshift( $this->items, $value );
		}

		return $this;
	}

	public function shift() {
		return array_shift( $this->items );
	}

	public function put( $key, $value ): self {
		$this->items[ $key ] = $value;

		return $this;
	}

	public function forget( $keys ): self {
		$keys = is_array( $keys ) ? $keys : [ $keys ];

		foreach ( $keys as $key ) {
			unset( $this->items[ $key ] );
		}

		return $this;
	}

	public function pull( $key, $default = null ) {
		$value = $this->items[ $key ] ?? $default;

		unset( $this->items[ $key ] );

		return $value;
	}

	public function contains( $key, $operator = null, $value = null ): bool {
		if ( func_num_args() === 1 ) {
			if ( is_callable( $key ) ) {
				foreach ( $this->items as $k => $item ) {
					if ( $key( $item, $k ) ) {
						return true;
					}
				}
				return false;
			}

			return in_array( $key, $this->items, true );
		}

		return $this->where( $key, $operator, $value )->isNotEmpty();
	}

	public function search( $value, bool $strict = false ) {
		if ( is_callable( $value ) ) {
			foreach ( $this->items as $key => $item ) {
				if ( $value( $item, $key ) ) {
					return $key;
				}
			}

			return false;
		}

		// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- $strict param controls this explicitly.
		return array_search( $value, $this->items, $strict );
	}

	public function sum( $callback = null ) {
		if ( null === $callback ) {
			return array_sum( $this->items );
		}

		if ( is_string( $callback ) ) {
			$key = $callback;
			$callback = fn( $item) => $this->getItemValue( $item, $key );
		}

		return $this->reduce(function ( $carry, $item ) use ( $callback ) {
			return $carry + $callback( $item );
		}, 0);
	}

	public function avg( $callback = null ) {
		$count = $this->count();

		if ( 0 === $count ) {
			return null;
		}

		return $this->sum( $callback ) / $count;
	}

	public function average( $callback = null ) {
		return $this->avg( $callback );
	}

	public function min( $callback = null ) {
		if ( null === $callback ) {
			return min( $this->items );
		}

		if ( is_string( $callback ) ) {
			return $this->pluck( $callback )->min();
		}

		return $this->map( $callback )->min();
	}

	public function max( $callback = null ) {
		if ( null === $callback ) {
			return max( $this->items );
		}

		if ( is_string( $callback ) ) {
			return $this->pluck( $callback )->max();
		}

		return $this->map( $callback )->max();
	}

	public function median( $key = null ) {
		$values = $key ? $this->pluck( $key )->all() : $this->items;
		sort( $values );

		$count = count( $values );

		if ( 0 === $count ) {
			return null;
		}

		$middle = (int) ( $count / 2 );

		if ( 0 === $count % 2 ) {
			return ( $values[ $middle - 1 ] + $values[ $middle ] ) / 2;
		}

		return $values[ $middle ];
	}

	public function reduce( callable $callback, $initial = null ) {
		return array_reduce( $this->items, $callback, $initial );
	}

	public function implode( string $value, ?string $glue = null ): string {
		if ( null === $glue ) {
			return implode( $value, $this->items );
		}

		return $this->pluck( $value )->implode( $glue );
	}

	public function join( string $glue, string $finalGlue = '' ): string {
		if ( '' === $finalGlue ) {
			return implode( $glue, $this->items );
		}

		$count = $this->count();

		if ( 0 === $count ) {
			return '';
		}

		if ( 1 === $count ) {
			return (string) $this->first();
		}

		$last = $this->pop();

		return implode( $glue, $this->items ) . $finalGlue . $last;
	}

	public function flip(): self {
		return new static( array_flip( $this->items ) );
	}

	public function only( $keys ): self {
		$keys = is_array( $keys ) ? $keys : func_get_args();

		return new static( array_intersect_key( $this->items, array_flip( $keys ) ) );
	}

	public function except( $keys ): self {
		$keys = is_array( $keys ) ? $keys : func_get_args();

		return new static( array_diff_key( $this->items, array_flip( $keys ) ) );
	}

	public function diff( $items ): self {
		if ( $items instanceof self ) {
			$items = $items->all();
		}

		return new static( array_diff( $this->items, $items ) );
	}

	public function intersect( $items ): self {
		if ( $items instanceof self ) {
			$items = $items->all();
		}

		return new static( array_intersect( $this->items, $items ) );
	}

	public function random( int $number = 1 ) {
		$count = $this->count();

		if ( $number > $count ) {
			$number = $count;
		}

		if ( 1 === $number ) {
			return $this->items[ array_rand( $this->items ) ];
		}

		$keys = array_rand( $this->items, $number );

		return new static( array_intersect_key( $this->items, array_flip( $keys ) ) );
	}

	public function shuffle(): self {
		$items = $this->items;

		shuffle( $items );

		return new static( $items );
	}

	public function tap( callable $callback ): self {
		$callback( $this );

		return $this;
	}

	public function pipe( callable $callback ) {
		return $callback( $this );
	}

	public function when( bool $value, callable $callback, ?callable $default = null ): self {
		if ( $value ) {
			return $callback( $this ) ?? $this;
		}

		if ( $default ) {
			return $default( $this ) ?? $this;
		}

		return $this;
	}

	public function unless( bool $value, callable $callback, ?callable $default = null ): self {
		return $this->when( ! $value, $callback, $default );
	}

	public function dd(): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_dump -- Intentional debug helper.
		var_dump( $this->toArray() );
		die( 1 );
	}

	public function dump(): self {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_dump -- Intentional debug helper.
		var_dump( $this->toArray() );

		return $this;
	}


	public function offsetExists( $offset ): bool {
		return isset( $this->items[ $offset ] );
	}

	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		return $this->items[ $offset ];
	}

	public function offsetSet( $offset, $value ): void {
		if ( null === $offset ) {
			$this->items[] = $value;
		} else {
			$this->items[ $offset ] = $value;
		}
	}

	public function offsetUnset( $offset ): void {
		unset( $this->items[ $offset ] );
	}


	public function getIterator(): ArrayIterator {
		return new ArrayIterator( $this->items );
	}


	public function __toString(): string {
		return $this->toJson();
	}
}
