<?php

namespace {
	if ( ! class_exists( 'SureCart' ) ) {
		class SureCart {}
	}

	if ( ! class_exists( 'SureCartTestStore' ) ) {
		class SureCartTestStore {
			private static array $items = [];

			public static function reset(): void {
				self::$items = [
					'SureCart\\Models\\Order' => [
						'ord_1' => [ 'id' => 'ord_1', 'status' => 'paid', 'number' => '1001' ],
					],
					'SureCart\\Models\\Customer' => [
						'cus_1' => [ 'id' => 'cus_1', 'email' => 'customer@example.com' ],
					],
					'SureCart\\Models\\Product' => [
						'prod_1' => [ 'id' => 'prod_1', 'name' => 'SureCart Product' ],
					],
					'SureCart\\Models\\Coupon' => [
						'cpn_1' => [ 'id' => 'cpn_1', 'name' => 'WELCOME' ],
					],
					'SureCart\\Models\\Subscription' => [
						'sub_1' => [ 'id' => 'sub_1', 'status' => 'active' ],
					],
				];
			}

			public static function all( string $class ): array {
				return array_values( self::$items[ $class ] ?? [] );
			}

			public static function find( string $class, string $id ): ?array {
				return self::$items[ $class ][ $id ] ?? null;
			}

			public static function save( string $class, array $data, ?string $id = null ): array {
				$id = $id ?: (string) ( $data['id'] ?? strtolower( preg_replace( '/^.*\\\\/', '', $class ) ) . '_' . ( count( self::$items[ $class ] ?? [] ) + 1 ) );
				$data['id'] = $id;
				self::$items[ $class ][ $id ] = $data;
				return $data;
			}

			public static function delete( string $class, string $id ): bool {
				if ( ! isset( self::$items[ $class ][ $id ] ) ) {
					return false;
				}
				unset( self::$items[ $class ][ $id ] );
				return true;
			}
		}

		SureCartTestStore::reset();
	}
}

namespace SureCart\Models {
	class Collection {
		public array $data;

		public function __construct( array $data ) {
			$this->data = $data;
		}

		public function total(): int {
			return count( $this->data );
		}
	}

	abstract class BaseModel {
		protected array $data = [];
		protected array $query = [];

		public function __construct( $id = null ) {
			if ( null !== $id ) {
				$found = \SureCartTestStore::find( static::class, (string) $id );
				if ( $found ) {
					$this->data = $found;
				}
			}
		}

		public function setMode( string $mode ): self {
			$this->data['mode'] = $mode;
			return $this;
		}

		public function with( array $expand ): self {
			$this->data['expand'] = $expand;
			return $this;
		}

		public function where( array $query ): self {
			$this->query = $query;
			return $this;
		}

		public function create( array $data ) {
			$this->data = \SureCartTestStore::save( static::class, $data );
			return $this;
		}

		public function update( array $data ) {
			$current = $this->data;
			$current = array_merge( $current, $data );
			$this->data = \SureCartTestStore::save( static::class, $current, (string) ( $current['id'] ?? '' ) );
			return $this;
		}

		public function delete( $id ) {
			return \SureCartTestStore::delete( static::class, (string) $id );
		}

		public function find( $id ) {
			$found = \SureCartTestStore::find( static::class, (string) $id );
			if ( ! $found ) {
				return false;
			}
			$model = new static();
			$model->data = $found;
			return $model;
		}

		public function paginate( array $args ) {
			unset( $args );
			$items = array_map( function ( array $row ) {
				$model = new static();
				$model->data = $row;
				return $model;
			}, \SureCartTestStore::all( static::class ) );
			return new Collection( $items );
		}

		public function toArray(): array {
			return $this->data;
		}
	}

	class Order extends BaseModel {}
	class Customer extends BaseModel {}
	class Product extends BaseModel {}
	class Coupon extends BaseModel {}
	class Subscription extends BaseModel {}
}
