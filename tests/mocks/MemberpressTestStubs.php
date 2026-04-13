<?php

namespace {
	if ( ! function_exists( 'sanitize_user' ) ) {
		function sanitize_user( $username, $strict = false ) {
			unset( $strict );
			return preg_replace( '/[^a-z0-9_\-@.]/i', '', (string) $username );
		}
	}

	if ( ! class_exists( 'MeprUser' ) ) {
		class MeprUser {
			private static int $next_id = 200;
			private static array $store = [];

			public int $ID = 0;
			public string $user_email = '';
			public string $user_login = '';
			public string $user_pass = '';
			public string $first_name = '';
			public string $last_name = '';
			public string $display_name = '';

			public function __construct( int $id = 0 ) {
				if ( $id > 0 && isset( self::$store[ $id ] ) ) {
					foreach ( self::$store[ $id ] as $key => $value ) {
						$this->$key = $value;
					}
				} elseif ( $id > 0 ) {
					$this->ID = $id;
				}
			}

			public static function seed( int $id, array $data ): void {
				self::$store[ $id ] = array_merge( [
					'ID'           => $id,
					'user_email'   => 'member' . $id . '@example.com',
					'user_login'   => 'member' . $id,
					'first_name'   => 'Member',
					'last_name'    => 'User',
					'display_name' => 'Member User',
				], $data );
			}

			public static function reset_store(): void {
				self::$next_id = 200;
				self::$store = [];
			}

			public function store(): int {
				if ( 0 === $this->ID ) {
					$this->ID = ++self::$next_id;
				}

				self::$store[ $this->ID ] = [
					'ID'           => $this->ID,
					'user_email'   => $this->user_email,
					'user_login'   => $this->user_login,
					'user_pass'    => $this->user_pass,
					'first_name'   => $this->first_name,
					'last_name'    => $this->last_name,
					'display_name' => $this->display_name ?: trim( $this->first_name . ' ' . $this->last_name ),
				];

				return $this->ID;
			}
		}
	}

	if ( ! class_exists( 'MeprProduct' ) ) {
		class MeprProduct {
			private static int $next_id = 300;
			private static array $store = [];
			public static string $cpt = 'memberpressproduct';

			public int $ID = 0;
			public string $post_title = '';
			public string $post_content = '';
			public string $post_status = 'publish';
			public float $price = 0.0;
			public int $period = 1;
			public string $period_type = 'months';
			public bool $trial = false;
			public int $trial_days = 0;
			public float $trial_amount = 0.0;
			public string $expire_type = 'none';
			public int $expire_after = 0;
			public string $expire_unit = 'days';
			public string $expire_fixed = '';
			public bool $allow_renewal = false;
			public string $pricing_display = 'auto';
			public string $pricing_title = '';
			public string $pricing_heading_txt = '';
			public string $pricing_footer_txt = '';
			public string $pricing_button_txt = '';
			public string $pricing_button_position = '';

			public function __construct( int $id = 0 ) {
				if ( $id > 0 && isset( self::$store[ $id ] ) ) {
					foreach ( self::$store[ $id ] as $key => $value ) {
						$this->$key = $value;
					}
				} elseif ( $id > 0 ) {
					$this->ID = $id;
				}
			}

			public static function seed( int $id, array $data ): void {
				self::$store[ $id ] = array_merge( [
					'ID'          => $id,
					'post_title'  => 'Membership ' . $id,
					'post_status' => 'publish',
					'price'       => 19.99,
				], $data );
			}

			public static function reset_store(): void {
				self::$next_id = 300;
				self::$store = [];
			}

			public function store(): int {
				if ( 0 === $this->ID ) {
					$this->ID = ++self::$next_id;
				}
				self::$store[ $this->ID ] = get_object_vars( $this );
				return $this->ID;
			}
		}
	}

	if ( ! class_exists( 'MeprTransaction' ) ) {
		class MeprTransaction {
			private static int $next_id = 400;
			private static array $store = [];
			public static string $complete_str = 'complete';
			public static string $manual_gateway_str = 'manual';

			public int $id = 0;
			public int $user_id = 0;
			public int $product_id = 0;
			public int $subscription_id = 0;
			public float $amount = 0.0;
			public float $total = 0.0;
			public string $status = 'complete';
			public string $gateway = 'manual';
			public string $created_at = '2024-01-01 00:00:00';
			public ?string $expires_at = null;

			public function __construct( int $id = 0 ) {
				if ( $id > 0 && isset( self::$store[ $id ] ) ) {
					foreach ( self::$store[ $id ] as $key => $value ) {
						$this->$key = $value;
					}
				} elseif ( $id > 0 ) {
					$this->id = $id;
				}
			}

			public static function seed( int $id, array $data ): void {
				self::$store[ $id ] = array_merge( [
					'id'              => $id,
					'user_id'         => 1,
					'product_id'      => 12,
					'subscription_id' => 31,
					'amount'          => 19.99,
					'total'           => 19.99,
					'status'          => self::$complete_str,
					'gateway'         => self::$manual_gateway_str,
					'created_at'      => '2024-01-01 00:00:00',
					'expires_at'      => null,
				], $data );
			}

			public static function reset_store(): void {
				self::$next_id = 400;
				self::$store = [];
			}

			public function store(): int {
				if ( 0 === $this->id ) {
					$this->id = ++self::$next_id;
				}
				self::$store[ $this->id ] = get_object_vars( $this );
				return $this->id;
			}

			public function refund(): bool {
				$this->status = 'refunded';
				$this->store();
				return true;
			}
		}
	}

	if ( ! class_exists( 'MeprSubscription' ) ) {
		class MeprSubscription {
			private static int $next_id = 500;
			private static array $store = [];
			public static string $active_str = 'active';

			public int $id = 0;
			public int $user_id = 0;
			public int $product_id = 0;
			public float $price = 0.0;
			public int $period = 1;
			public string $period_type = 'months';
			public string $status = 'active';
			public string $gateway = 'manual';
			public string $created_at = '2024-01-01 00:00:00';

			public function __construct( int $id = 0 ) {
				if ( $id > 0 && isset( self::$store[ $id ] ) ) {
					foreach ( self::$store[ $id ] as $key => $value ) {
						$this->$key = $value;
					}
				} elseif ( $id > 0 ) {
					$this->id = $id;
				}
			}

			public static function seed( int $id, array $data ): void {
				self::$store[ $id ] = array_merge( [
					'id'          => $id,
					'user_id'     => 1,
					'product_id'  => 12,
					'price'       => 29.99,
					'period'      => 1,
					'period_type' => 'months',
					'status'      => self::$active_str,
					'gateway'     => 'manual',
					'created_at'  => '2024-01-01 00:00:00',
				], $data );
			}

			public static function reset_store(): void {
				self::$next_id = 500;
				self::$store = [];
			}

			public function store(): int {
				if ( 0 === $this->id ) {
					$this->id = ++self::$next_id;
				}
				self::$store[ $this->id ] = get_object_vars( $this );
				return $this->id;
			}

			public function cancel(): bool {
				$this->status = 'cancelled';
				$this->store();
				return true;
			}

			public function suspend(): bool {
				$this->status = 'suspended';
				$this->store();
				return true;
			}

			public function resume(): bool {
				$this->status = self::$active_str;
				$this->store();
				return true;
			}
		}
	}
}