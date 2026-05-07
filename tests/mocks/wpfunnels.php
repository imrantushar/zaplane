<?php

namespace {
	if ( ! defined( 'WPFNL_FUNNELS_POST_TYPE' ) ) {
		define( 'WPFNL_FUNNELS_POST_TYPE', 'wpfunnels' );
	}

	if ( ! defined( 'WPFNL_STEPS_POST_TYPE' ) ) {
		define( 'WPFNL_STEPS_POST_TYPE', 'wpfunnel_steps' );
	}

	if ( ! class_exists( 'WpfunnelsTestOrder' ) ) {
		class WpfunnelsTestOrder {
			private int $id = 0;
			private string $status = 'processing';
			private float $total = 0.0;
			private string $currency = 'USD';
			private int $customer_id = 0;
			private string $billing_email = '';

			public function __construct( int $id, array $data = [] ) {
				$this->id = $id;
				$this->status = (string) ( $data['status'] ?? 'processing' );
				$this->total = (float) ( $data['total'] ?? 0 );
				$this->currency = (string) ( $data['currency'] ?? 'USD' );
				$this->customer_id = (int) ( $data['customer_id'] ?? 0 );
				$this->billing_email = (string) ( $data['billing_email'] ?? '' );
			}

			public function get_id(): int {
				return $this->id;
			}

			public function get_status(): string {
				return $this->status;
			}

			public function get_total(): float {
				return $this->total;
			}

			public function get_currency(): string {
				return $this->currency;
			}

			public function get_customer_id(): int {
				return $this->customer_id;
			}

			public function get_billing_email(): string {
				return $this->billing_email;
			}
		}
	}

	if ( ! class_exists( 'WpfunnelsTestStore' ) ) {
		class WpfunnelsTestStore {
			private static array $funnels = [];
			private static array $steps = [];
			private static array $next_step_map = [];
			private static array $orders = [];

			public static function reset(): void {
				self::$funnels = [
					601 => [
						'id'     => 601,
						'title'  => 'Main Funnel',
						'status' => 'publish',
					],
				];

				self::$steps = [
					701 => [
						'id'        => 701,
						'title'     => 'Landing Step',
						'status'    => 'publish',
						'step_type' => 'landing',
						'funnel_id' => 601,
					],
					702 => [
						'id'        => 702,
						'title'     => 'Checkout Step',
						'status'    => 'publish',
						'step_type' => 'checkout',
						'funnel_id' => 601,
					],
					703 => [
						'id'        => 703,
						'title'     => 'Thankyou Step',
						'status'    => 'publish',
						'step_type' => 'thankyou',
						'funnel_id' => 601,
					],
				];

				self::$next_step_map = [
					701 => 702,
					702 => 703,
				];

				self::$orders = [
					9001 => new \WpfunnelsTestOrder(
						9001,
						[
							'status'        => 'processing',
							'total'         => 129.50,
							'currency'      => 'USD',
							'customer_id'   => 1001,
							'billing_email' => 'buyer@example.com',
						]
					),
					9002 => new \WpfunnelsTestOrder(
						9002,
						[
							'status'        => 'completed',
							'total'         => 59.00,
							'currency'      => 'USD',
							'customer_id'   => 1002,
							'billing_email' => 'upsell@example.com',
						]
					),
				];
			}

			public static function getFunnels(): array {
				return array_values( self::$funnels );
			}

			public static function getSteps( ?int $funnel_id = null ): array {
				$steps = self::$steps;
				if ( null !== $funnel_id ) {
					$steps = array_filter(
						$steps,
						static function ( array $step ) use ( $funnel_id ): bool {
							return (int) ( $step['funnel_id'] ?? 0 ) === $funnel_id;
						}
					);
				}

				return array_values( $steps );
			}

			public static function getFunnelIdFromStep( int $step_id ): int {
				return (int) ( self::$steps[ $step_id ]['funnel_id'] ?? 0 );
			}

			public static function getNextStepId( int $step_id ): int {
				return (int) ( self::$next_step_map[ $step_id ] ?? 0 );
			}

			public static function getStepType( int $step_id ): string {
				return (string) ( self::$steps[ $step_id ]['step_type'] ?? '' );
			}

			public static function getOrder( int $order_id ) {
				return self::$orders[ $order_id ] ?? null;
			}
		}

		WpfunnelsTestStore::reset();
	}
}

namespace WPFunnels {
	if ( ! class_exists( 'WPFunnels\\Wpfnl_functions' ) ) {
		class Wpfnl_functions {

			public static function get_steps( $funnel_id ): array {
				return \WpfunnelsTestStore::getSteps( (int) $funnel_id );
			}

			public static function get_funnel_id_from_step( $step_id ): int {
				return \WpfunnelsTestStore::getFunnelIdFromStep( (int) $step_id );
			}

			public static function get_next_step( $funnel_id, $step_id ): array {
				unset( $funnel_id );

				$next_step_id = \WpfunnelsTestStore::getNextStepId( (int) $step_id );
				if ( $next_step_id <= 0 ) {
					return [];
				}

				return [
					'step_id'   => $next_step_id,
					'step_type' => \WpfunnelsTestStore::getStepType( $next_step_id ),
				];
			}
		}
	}
}

