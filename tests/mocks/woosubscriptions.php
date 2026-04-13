<?php

namespace {
	if ( ! class_exists( 'WooSubscriptionsTestStore' ) ) {
		class WooSubscriptionsTestStore {
			private static array $subscriptions = [];
			private static int $next_id = 900;

			public static function reset(): void {
				self::$next_id = 900;
				self::$subscriptions = [
					801 => [
						'id'               => 801,
						'status'           => 'active',
						'customer_id'      => 701,
						'parent_order_id'  => 501,
						'total'            => '19.99',
						'currency'         => 'USD',
						'billing_period'   => 'month',
						'billing_interval' => 1,
						'start_date'       => '2026-01-01 00:00:00',
						'trial_end_date'   => '2026-01-15 00:00:00',
						'next_payment_date'=> '2026-02-01 00:00:00',
						'end_date'         => '',
					],
					802 => [
						'id'               => 802,
						'status'           => 'on-hold',
						'customer_id'      => 702,
						'parent_order_id'  => 502,
						'total'            => '29.99',
						'currency'         => 'USD',
						'billing_period'   => 'month',
						'billing_interval' => 1,
						'start_date'       => '2026-01-10 00:00:00',
						'trial_end_date'   => '',
						'next_payment_date'=> '2026-02-10 00:00:00',
						'end_date'         => '',
					],
				];
			}

			public static function getSubscription( int $id ): ?array {
				return self::$subscriptions[ $id ] ?? null;
			}

			public static function allSubscriptions(): array {
				return array_values( self::$subscriptions );
			}

			public static function saveSubscription( array $data ): int {
				$id = (int) ( $data['id'] ?? 0 );
				if ( $id <= 0 ) {
					$id = ++self::$next_id;
				}

				$data['id'] = $id;
				self::$subscriptions[ $id ] = array_merge( self::$subscriptions[ $id ] ?? [], $data );
				return $id;
			}
		}

		WooSubscriptionsTestStore::reset();
	}

	if ( ! class_exists( 'WC_Subscription' ) ) {
		class WC_Subscription {
			private array $data = [];

			public function __construct( int $id = 0 ) {
				$this->data = $id > 0 ? ( WooSubscriptionsTestStore::getSubscription( $id ) ?? [] ) : [];
			}

			public function get_id(): int { return (int) ( $this->data['id'] ?? 0 ); }
			public function get_status(): string { return (string) ( $this->data['status'] ?? '' ); }
			public function get_customer_id(): int { return (int) ( $this->data['customer_id'] ?? 0 ); }
			public function get_parent_id(): int { return (int) ( $this->data['parent_order_id'] ?? 0 ); }
			public function get_total(): string { return (string) ( $this->data['total'] ?? '' ); }
			public function get_currency(): string { return (string) ( $this->data['currency'] ?? '' ); }
			public function get_billing_period(): string { return (string) ( $this->data['billing_period'] ?? '' ); }
			public function get_billing_interval(): int { return (int) ( $this->data['billing_interval'] ?? 0 ); }
			public function set_total( $total ): void { $this->data['total'] = (string) $total; }
			public function set_currency( string $currency ): void { $this->data['currency'] = $currency; }
			public function set_status( string $status ): void { $this->data['status'] = $status; }
			public function update_status( string $status, string $note = '' ): void {
				$this->data['status'] = $status;
				if ( '' !== $note ) {
					$this->add_order_note( $note );
				}
				$this->save();
			}
			public function add_order_note( string $note, bool $is_customer_note = false ) {
				$this->data['notes'][] = [
					'note' => $note,
					'is_customer_note' => $is_customer_note,
				];
				$this->save();
				return count( $this->data['notes'] );
			}
			public function save(): int {
				$this->data['id'] = WooSubscriptionsTestStore::saveSubscription( $this->data );
				return (int) $this->data['id'];
			}

			public function get_date( string $date_type ) {
				$map = [
					'start'        => 'start_date',
					'trial_end'    => 'trial_end_date',
					'next_payment' => 'next_payment_date',
					'end'          => 'end_date',
				];

				$key = $map[ $date_type ] ?? '';
				if ( '' === $key ) {
					return '';
				}

				return (string) ( $this->data[ $key ] ?? '' );
			}
		}
	}

	if ( ! function_exists( 'wcs_get_subscription' ) ) {
		function wcs_get_subscription( $subscription_id ) {
			$subscription_id = (int) $subscription_id;
			if ( $subscription_id <= 0 ) {
				return null;
			}

			return WooSubscriptionsTestStore::getSubscription( $subscription_id ) ? new WC_Subscription( $subscription_id ) : null;
		}
	}

	if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
		function wcs_get_subscriptions( $args = [] ) {
			$items = array_map(
				static fn( $row ) => new WC_Subscription( (int) ( $row['id'] ?? 0 ) ),
				WooSubscriptionsTestStore::allSubscriptions()
			);

			$status = (string) ( $args['subscription_status'] ?? '' );
			if ( '' !== $status ) {
				$items = array_values( array_filter(
					$items,
					static fn( $subscription ) => $subscription->get_status() === $status
				) );
			}

			$limit = (int) ( $args['subscriptions_per_page'] ?? 20 );
			$paged = max( 1, (int) ( $args['paged'] ?? 1 ) );
			$offset = ( $paged - 1 ) * $limit;
			if ( $limit > 0 ) {
				$items = array_slice( $items, $offset, $limit );
			}

			return $items;
		}
	}

	if ( ! function_exists( 'wcs_create_subscription' ) ) {
		function wcs_create_subscription( $args = [] ) {
			$data = [
				'customer_id' => (int) ( $args['customer_id'] ?? 0 ),
				'parent_order_id' => (int) ( $args['order_id'] ?? 0 ),
				'status' => (string) ( $args['status'] ?? 'active' ),
				'billing_period' => (string) ( $args['billing_period'] ?? 'month' ),
				'billing_interval' => (int) ( $args['billing_interval'] ?? 1 ),
				'total' => '',
				'currency' => '',
				'start_date' => (string) ( $args['start_date'] ?? '' ),
				'trial_end_date' => (string) ( $args['trial_end'] ?? '' ),
				'next_payment_date' => (string) ( $args['next_payment'] ?? '' ),
				'end_date' => (string) ( $args['end'] ?? '' ),
				'notes' => [],
			];

			$subscription_id = WooSubscriptionsTestStore::saveSubscription( $data );
			return new WC_Subscription( $subscription_id );
		}
	}
}
