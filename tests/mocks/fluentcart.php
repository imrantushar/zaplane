<?php

namespace {
	if ( ! class_exists( 'FluentCartTestStore' ) ) {
		class FluentCartTestStore {
			private static array $orders = [];
			private static array $customers = [];
			private static array $subscriptions = [];
			private static array $products = [];

			public static function reset(): void {
				self::$orders = [
					501 => [
						'id'             => 501,
						'order_id'       => 501,
						'customer_id'    => 401,
						'status'         => 'completed',
						'payment_status' => 'paid',
						'total_amount'   => 129.50,
						'currency'       => 'USD',
						'created_at'     => '2026-04-10 10:00:00',
					],
					502 => [
						'id'             => 502,
						'order_id'       => 502,
						'customer_id'    => 402,
						'status'         => 'pending',
						'payment_status' => 'pending',
						'total_amount'   => 79.00,
						'currency'       => 'USD',
						'created_at'     => '2026-04-11 10:00:00',
					],
				];

				self::$customers = [
					401 => [
						'id'         => 401,
						'email'      => 'customer-one@example.com',
						'first_name' => 'Customer',
						'last_name'  => 'One',
						'full_name'  => 'Customer One',
						'status'     => 'active',
					],
					402 => [
						'id'         => 402,
						'email'      => 'customer-two@example.com',
						'first_name' => 'Customer',
						'last_name'  => 'Two',
						'full_name'  => 'Customer Two',
						'status'     => 'active',
					],
				];

				self::$subscriptions = [
					601 => [
						'id'             => 601,
						'subscription_id'=> 601,
						'customer_id'    => 401,
						'parent_order_id'=> 501,
						'status'         => 'active',
						'recurring_total'=> 20.00,
					],
					602 => [
						'id'             => 602,
						'subscription_id'=> 602,
						'customer_id'    => 402,
						'parent_order_id'=> 502,
						'status'         => 'canceled',
						'recurring_total'=> 10.00,
					],
				];

				self::$products = [
					701 => [
						'ID'         => 701,
						'id'         => 701,
						'post_title' => 'FluentCart Product One',
						'post_status'=> 'publish',
					],
					702 => [
						'ID'         => 702,
						'id'         => 702,
						'post_title' => 'FluentCart Product Two',
						'post_status'=> 'publish',
					],
				];
			}

			public static function getRows( string $dataset, array $filters = [], string $search = '', int $limit = 20, int $offset = 0, string $order_by = 'id', string $order_dir = 'DESC' ): array {
				$rows = array_values( self::dataset( $dataset ) );

				if ( ! empty( $filters ) ) {
					$rows = array_values(
						array_filter(
							$rows,
							static function ( array $row ) use ( $filters ): bool {
								foreach ( $filters as $key => $value ) {
									if ( ! array_key_exists( $key, $row ) ) {
										return false;
									}

									if ( (string) $row[ $key ] !== (string) $value ) {
										return false;
									}
								}

								return true;
							}
						)
					);
				}

				if ( '' !== $search ) {
					$needle = strtolower( $search );
					$rows   = array_values(
						array_filter(
							$rows,
							static function ( array $row ) use ( $needle ): bool {
								$haystack = strtolower(
									implode(
										' ',
										array_map(
											static function ( $value ): string {
												return is_scalar( $value ) ? (string) $value : '';
											},
											$row
										)
									)
								);

								return false !== strpos( $haystack, $needle );
							}
						)
					);
				}

				$order_by = 'ID' === $order_by ? 'ID' : 'id';
				usort(
					$rows,
					static function ( array $a, array $b ) use ( $order_by, $order_dir ): int {
						$left  = (int) ( $a[ $order_by ] ?? 0 );
						$right = (int) ( $b[ $order_by ] ?? 0 );

						if ( $left === $right ) {
							return 0;
						}

						if ( 'ASC' === strtoupper( $order_dir ) ) {
							return $left <=> $right;
						}

						return $right <=> $left;
					}
				);

				return array_slice( $rows, max( 0, $offset ), max( 1, $limit ) );
			}

			public static function findRow( string $dataset, int $id ): ?array {
				$rows = self::dataset( $dataset );
				if ( isset( $rows[ $id ] ) ) {
					return $rows[ $id ];
				}

				return null;
			}

			public static function makeModel( string $dataset, array $row ) {
				switch ( $dataset ) {
					case 'orders':
						return new \FluentCart\App\Models\Order( $row );
					case 'customers':
						return new \FluentCart\App\Models\Customer( $row );
					case 'subscriptions':
						return new \FluentCart\App\Models\Subscription( $row );
					case 'products':
						return new \FluentCart\App\Models\Product( $row );
				}

				return null;
			}

			private static function dataset( string $dataset ): array {
				switch ( $dataset ) {
					case 'orders':
						return self::$orders;
					case 'customers':
						return self::$customers;
					case 'subscriptions':
						return self::$subscriptions;
					case 'products':
						return self::$products;
				}

				return [];
			}
		}

		FluentCartTestStore::reset();
	}

	if ( ! class_exists( 'FluentCartTestQuery' ) ) {
		class FluentCartTestQuery {
			private string $dataset = '';
			private array $filters = [];
			private int $limit = 20;
			private int $offset = 0;
			private string $search = '';
			private string $order_by = 'id';
			private string $order_dir = 'DESC';

			public function __construct( string $dataset ) {
				$this->dataset = $dataset;
			}

			public function where( $key, $operator_or_value = null, $value = null ) {
				$filter_value = null === $value ? $operator_or_value : $value;
				$this->filters[ (string) $key ] = $filter_value;
				return $this;
			}

			public function searchBy( string $search ) {
				$this->search = $search;
				return $this;
			}

			public function orderBy( string $column, string $direction = 'DESC' ) {
				$this->order_by  = $column;
				$this->order_dir = $direction;
				return $this;
			}

			public function limit( int $limit ) {
				$this->limit = $limit;
				return $this;
			}

			public function offset( int $offset ) {
				$this->offset = $offset;
				return $this;
			}

			public function with( ...$relations ) {
				unset( $relations );
				return $this;
			}

			public function get(): array {
				$rows = FluentCartTestStore::getRows( $this->dataset, $this->filters, $this->search, $this->limit, $this->offset, $this->order_by, $this->order_dir );
				$items = [];

				foreach ( $rows as $row ) {
					$model = FluentCartTestStore::makeModel( $this->dataset, $row );
					if ( $model ) {
						$items[] = $model;
					}
				}

				return $items;
			}

			public function find( int $id ) {
				$row = FluentCartTestStore::findRow( $this->dataset, $id );
				if ( ! $row ) {
					return null;
				}

				return FluentCartTestStore::makeModel( $this->dataset, $row );
			}
		}
	}
}

namespace FluentCart\App\Models {
	abstract class FluentCartTestModel {
		protected array $attributes = [];

		public function __construct( array $attributes = [] ) {
			$this->attributes = $attributes;
		}

		public function __get( $key ) {
			return $this->attributes[ $key ] ?? null;
		}

		public function toArray(): array {
			return $this->attributes;
		}

		public function get_id(): int {
			return (int) ( $this->attributes['id'] ?? ( $this->attributes['ID'] ?? 0 ) );
		}
	}

	class Order extends FluentCartTestModel {
		public static function query() {
			return new \FluentCartTestQuery( 'orders' );
		}
	}

	class Customer extends FluentCartTestModel {
		public static function query() {
			return new \FluentCartTestQuery( 'customers' );
		}
	}

	class Subscription extends FluentCartTestModel {
		public static function query() {
			return new \FluentCartTestQuery( 'subscriptions' );
		}
	}

	class Product extends FluentCartTestModel {
		public static function query() {
			return new \FluentCartTestQuery( 'products' );
		}
	}
}

namespace {
	if ( ! function_exists( 'fluentCart' ) ) {
		function fluentCart( $module = false ) {
			unset( $module );
			return (object) [ 'name' => 'FluentCart' ];
		}
	}
}
