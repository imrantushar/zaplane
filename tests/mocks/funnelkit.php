<?php

if ( ! class_exists( 'FunnelkitTestStore' ) ) {
	class FunnelkitTestStore {
		private static array $funnels = [];
		private static array $steps = [];
		private static array $next_step_map = [];

		public static function reset(): void {
			self::$funnels = [
				801 => [
					'id'          => 801,
					'title'       => 'Main Funnel',
					'description' => 'Primary sales funnel',
					'status'      => 'live',
					'created_at'  => '2026-04-01 10:00:00',
					'updated_at'  => '2026-04-01 11:00:00',
				],
			];

			self::$steps = [
				901 => [
					'id'        => 901,
					'title'     => 'Landing Step',
					'status'    => 'publish',
					'post_type' => 'wffn_landing',
					'step_type' => 'landing',
					'funnel_id' => 801,
					'created_at'=> '2026-04-01 10:05:00',
					'updated_at'=> '2026-04-01 10:06:00',
				],
				902 => [
					'id'        => 902,
					'title'     => 'Checkout Step',
					'status'    => 'publish',
					'post_type' => 'wfacp_checkout',
					'step_type' => 'wc_checkout',
					'funnel_id' => 801,
					'created_at'=> '2026-04-01 10:07:00',
					'updated_at'=> '2026-04-01 10:08:00',
				],
				903 => [
					'id'        => 903,
					'title'     => 'Thank You Step',
					'status'    => 'publish',
					'post_type' => 'wffn_ty',
					'step_type' => 'wc_thankyou',
					'funnel_id' => 801,
					'created_at'=> '2026-04-01 10:09:00',
					'updated_at'=> '2026-04-01 10:10:00',
				],
			];

			self::$next_step_map = [
				901 => 902,
				902 => 903,
			];
		}

		public static function getFunnels(): array {
			return array_values( self::$funnels );
		}

		public static function getFunnel( int $funnel_id ): ?array {
			return self::$funnels[ $funnel_id ] ?? null;
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

		public static function getStep( int $step_id ): ?array {
			return self::$steps[ $step_id ] ?? null;
		}

		public static function getFunnelIdFromStep( int $step_id ): int {
			return (int) ( self::$steps[ $step_id ]['funnel_id'] ?? 0 );
		}

		public static function getStepType( int $step_id ): string {
			return (string) ( self::$steps[ $step_id ]['step_type'] ?? '' );
		}

		public static function getNextStepId( int $step_id ): int {
			return (int) ( self::$next_step_map[ $step_id ] ?? 0 );
		}

		public static function getStepPayload( int $step_id ): ?array {
			$step = self::getStep( $step_id );
			if ( ! is_array( $step ) ) {
				return null;
			}

			$funnel_id   = (int) ( $step['funnel_id'] ?? 0 );
			$next_step_id = self::getNextStepId( $step_id );
			$url         = function_exists( 'get_permalink' ) ? (string) get_permalink( $step_id ) : ( 'https://example.com/?p=' . $step_id );

			return [
				'step_id'      => $step_id,
				'funnel_id'    => $funnel_id,
				'step_type'    => (string) ( $step['step_type'] ?? '' ),
				'step_title'   => (string) ( $step['title'] ?? '' ),
				'step_status'  => (string) ( $step['status'] ?? '' ),
				'step_url'     => $url,
				'next_step_id' => $next_step_id,
				'created_at'   => (string) ( $step['created_at'] ?? '' ),
				'updated_at'   => (string) ( $step['updated_at'] ?? '' ),
			];
		}

		public static function getFunnelPayload( int $funnel_id ): ?array {
			$funnel = self::getFunnel( $funnel_id );
			if ( ! is_array( $funnel ) ) {
				return null;
			}

			$steps = [];
			foreach ( self::getSteps( $funnel_id ) as $step ) {
				$step_payload = self::getStepPayload( (int) ( $step['id'] ?? 0 ) );
				if ( $step_payload ) {
					$steps[] = $step_payload;
				}
			}

			return [
				'funnel_id'          => $funnel_id,
				'funnel_title'       => (string) ( $funnel['title'] ?? '' ),
				'funnel_description' => (string) ( $funnel['description'] ?? '' ),
				'funnel_status'      => (string) ( $funnel['status'] ?? '' ),
				'total_steps'        => count( $steps ),
				'steps'              => $steps,
				'created_at'         => (string) ( $funnel['created_at'] ?? '' ),
				'updated_at'         => (string) ( $funnel['updated_at'] ?? '' ),
			];
		}
	}

	FunnelkitTestStore::reset();
}

if ( ! class_exists( 'WFFN_Funnel' ) ) {
	class WFFN_Funnel {
		private int $id = 0;
		private array $data = [];

		public function __construct( int $id = 0 ) {
			$this->id   = $id;
			$this->data = FunnelkitTestStore::getFunnel( $id ) ?? [];
			if ( empty( $this->data ) ) {
				$this->id = 0;
			}
		}

		public function get_id(): int {
			return $this->id;
		}

		public function get_title(): string {
			return (string) ( $this->data['title'] ?? '' );
		}

		public function get_desc(): string {
			return (string) ( $this->data['description'] ?? '' );
		}

		public function get_date_added(): string {
			return (string) ( $this->data['created_at'] ?? '' );
		}

		public function get_last_update_date(): string {
			return (string) ( $this->data['updated_at'] ?? '' );
		}

		public function get_steps( bool $populated = false ): array {
			unset( $populated );

			$steps = FunnelkitTestStore::getSteps( $this->id );
			$result = [];
			foreach ( $steps as $step ) {
				$result[] = [
					'id'   => (int) ( $step['id'] ?? 0 ),
					'type' => (string) ( $step['step_type'] ?? '' ),
				];
			}

			return $result;
		}

		public function get_next_step_id( int $current_step_id ) {
			$next_id = FunnelkitTestStore::getNextStepId( $current_step_id );
			if ( $next_id <= 0 ) {
				return false;
			}

			return [
				'id'   => $next_id,
				'type' => FunnelkitTestStore::getStepType( $next_id ),
			];
		}
	}
}

if ( ! class_exists( 'WFFN_Test_DB' ) ) {
	class WFFN_Test_DB {
		public function get_meta( int $funnel_id, string $meta_key = '' ) {
			if ( 'status' !== $meta_key ) {
				return '';
			}

			$funnel = FunnelkitTestStore::getFunnel( $funnel_id );
			return (string) ( $funnel['status'] ?? '' );
		}
	}
}

if ( ! class_exists( 'WFFN_Test_Admin' ) ) {
	class WFFN_Test_Admin {
		public function get_funnels( array $args = [] ) {
			$funnels = FunnelkitTestStore::getFunnels();
			$search  = strtolower( trim( (string) ( $args['s'] ?? '' ) ) );

			if ( '' !== $search ) {
				$funnels = array_values(
					array_filter(
						$funnels,
						static function ( array $funnel ) use ( $search ): bool {
							$title = strtolower( (string) ( $funnel['title'] ?? '' ) );
							return false !== strpos( $title, $search );
						}
					)
				);
			}

			if ( ! empty( $args['search_filter'] ) ) {
				return array_map(
					static function ( array $funnel ): array {
						return [
							'id'   => (int) ( $funnel['id'] ?? 0 ),
							'name' => (string) ( $funnel['title'] ?? '' ),
						];
					},
					$funnels
				);
			}

			$items = array_map(
				static function ( array $funnel ): array {
					$steps = array_map(
						static function ( array $step ): array {
							return [
								'id'   => (int) ( $step['id'] ?? 0 ),
								'type' => (string) ( $step['step_type'] ?? '' ),
							];
						},
						FunnelkitTestStore::getSteps( (int) ( $funnel['id'] ?? 0 ) )
					);

					return [
						'id'         => (int) ( $funnel['id'] ?? 0 ),
						'title'      => (string) ( $funnel['title'] ?? '' ),
						'desc'       => (string) ( $funnel['description'] ?? '' ),
						'date_added' => (string) ( $funnel['created_at'] ?? '' ),
						'last_update'=> (string) ( $funnel['updated_at'] ?? '' ),
						'steps_data' => $steps,
						'steps'      => count( $steps ),
					];
				},
				$funnels
			);

			return [
				'found_posts' => count( $items ),
				'items'       => $items,
			];
		}
	}
}

if ( ! class_exists( 'WFFN_Test_Core' ) ) {
	class WFFN_Test_Core {
		public WFFN_Test_Admin $admin;
		private WFFN_Test_DB $db;

		public function __construct() {
			$this->admin = new WFFN_Test_Admin();
			$this->db    = new WFFN_Test_DB();
		}

		public function get_dB(): WFFN_Test_DB {
			return $this->db;
		}
	}
}

if ( ! function_exists( 'WFFN_Core' ) ) {
	function WFFN_Core(): WFFN_Test_Core { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		static $instance = null;
		if ( null === $instance ) {
			$instance = new WFFN_Test_Core();
		}

		return $instance;
	}
}

if ( ! class_exists( 'WFFN_Common' ) ) {
	class WFFN_Common {
		public static function get_step_type( string $post_type ): string {
			$map = [
				'wfacp_checkout' => 'wc_checkout',
				'wfocu_offer'    => 'wc_upsells',
				'wffn_landing'   => 'landing',
				'wffn_ty'        => 'wc_thankyou',
				'wffn_optin'     => 'optin',
				'wffn_oty'       => 'optin_ty',
				'cartflows_step' => 'checkout',
			];

			return (string) ( $map[ $post_type ] ?? $post_type );
		}
	}
}

