<?php

namespace {
	if ( ! class_exists( 'WooMembershipsTestStore' ) ) {
		class WooMembershipsTestStore {
			private static array $memberships = [];
			private static int $next_id = 2200;

			public static function reset(): void {
				self::$next_id = 2200;
				self::$memberships = [
					2101 => [
						'id' => 2101,
						'plan_id' => 901,
						'user_id' => 701,
						'product_id' => 901,
						'order_id' => 501,
						'status' => 'active',
						'start_date' => '2026-04-01 00:00:00',
						'end_date' => '2026-12-31 23:59:59',
						'notes' => [],
					],
					2102 => [
						'id' => 2102,
						'plan_id' => 902,
						'user_id' => 702,
						'product_id' => 902,
						'order_id' => 502,
						'status' => 'paused',
						'start_date' => '2026-03-01 00:00:00',
						'end_date' => '',
						'notes' => [],
					],
				];
			}

			public static function getMembership( int $id ): ?array {
				return self::$memberships[ $id ] ?? null;
			}

			public static function allMemberships(): array {
				return array_values( self::$memberships );
			}

			public static function saveMembership( array $data ): int {
				$id = (int) ( $data['id'] ?? 0 );
				if ( $id <= 0 ) {
					$id = ++self::$next_id;
				}

				$data['id'] = $id;
				self::$memberships[ $id ] = array_merge( self::$memberships[ $id ] ?? [], $data );
				return $id;
			}
		}

		WooMembershipsTestStore::reset();
	}

	if ( ! class_exists( 'WC_Memberships_User_Membership' ) ) {
		class WC_Memberships_User_Membership {
			private array $data = [];

			public function __construct( int $id = 0 ) {
				$this->data = $id > 0 ? ( WooMembershipsTestStore::getMembership( $id ) ?? [] ) : [];
			}

			public function get_id(): int {
				return (int) ( $this->data['id'] ?? 0 );
			}

			public function get_plan_id(): int {
				return (int) ( $this->data['plan_id'] ?? 0 );
			}

			public function get_user_id(): int {
				return (int) ( $this->data['user_id'] ?? 0 );
			}

			public function get_order_id(): int {
				return (int) ( $this->data['order_id'] ?? 0 );
			}

			public function get_status(): string {
				return (string) ( $this->data['status'] ?? '' );
			}

			public function get_start_date() {
				return (string) ( $this->data['start_date'] ?? '' );
			}

			public function get_end_date() {
				return (string) ( $this->data['end_date'] ?? '' );
			}

			public function set_status( string $status ): void {
				$this->data['status'] = $status;
			}

			public function update_status( string $status, string $note = '' ): void {
				$this->data['status'] = $status;
				if ( '' !== $note ) {
					$this->add_note( $note );
				}
				$this->save();
			}

			public function add_note( string $note, bool $notify = false ): void {
				$this->data['notes'][] = [
					'note' => $note,
					'notify' => $notify,
				];
				$this->save();
			}

			public function save(): int {
				$this->data['id'] = WooMembershipsTestStore::saveMembership( $this->data );
				return (int) $this->data['id'];
			}
		}
	}

	if ( ! function_exists( 'wc_memberships_get_user_membership' ) ) {
		function wc_memberships_get_user_membership( $id = null, $plan = null ) {
			unset( $plan );

			if ( is_object( $id ) && method_exists( $id, 'get_id' ) ) {
				return $id;
			}

			$membership_id = (int) $id;
			if ( $membership_id <= 0 ) {
				return false;
			}

			return WooMembershipsTestStore::getMembership( $membership_id )
				? new WC_Memberships_User_Membership( $membership_id )
				: false;
		}
	}

	if ( ! function_exists( 'wc_memberships_get_user_memberships' ) ) {
		function wc_memberships_get_user_memberships( $user_id = 0, $args = [] ) {
			$items = array_map(
				static fn( $row ) => new WC_Memberships_User_Membership( (int) ( $row['id'] ?? 0 ) ),
				WooMembershipsTestStore::allMemberships()
			);

			$user_id = (int) $user_id;
			if ( $user_id > 0 ) {
				$items = array_values( array_filter(
					$items,
					static fn( $membership ) => $membership->get_user_id() === $user_id
				) );
			}

			$statuses = $args['status'] ?? [];
			if ( ! is_array( $statuses ) ) {
				$statuses = [ $statuses ];
			}

			$statuses = array_values( array_filter( array_map(
				static function ( $status ) {
					$status = sanitize_key( (string) $status );
					$status = preg_replace( '/^(wcm-|wcm_)/', '', $status );
					return $status;
				},
				$statuses
			) ) );

			if ( ! empty( $statuses ) ) {
				$items = array_values( array_filter(
					$items,
					static fn( $membership ) => in_array( $membership->get_status(), $statuses, true )
				) );
			}

			return $items;
		}
	}

	if ( ! function_exists( 'wc_memberships_create_user_membership' ) ) {
		function wc_memberships_create_user_membership( $args = [], $action = 'create' ) {
			unset( $action );

			$data = [
				'plan_id' => (int) ( $args['plan_id'] ?? 0 ),
				'user_id' => (int) ( $args['user_id'] ?? 0 ),
				'product_id' => (int) ( $args['product_id'] ?? 0 ),
				'order_id' => (int) ( $args['order_id'] ?? 0 ),
				'status' => preg_replace( '/^(wcm-|wcm_)/', '', sanitize_key( (string) ( $args['status'] ?? 'active' ) ) ),
				'start_date' => (string) ( $args['start_date'] ?? '2026-04-01 00:00:00' ),
				'end_date' => (string) ( $args['end_date'] ?? '' ),
				'notes' => [],
			];

			$id = WooMembershipsTestStore::saveMembership( $data );
			return new WC_Memberships_User_Membership( $id );
		}
	}
}
