<?php
/**
 * Test doubles for the GameEngine free plugin and its Pro APIs.
 *
 * Only the surfaces the Zaplane Gameengine integration calls exist here: the
 * points / achievement / level / reward managers, the wrapper functions and
 * the two Pro delegates. State lives in static storage, which resets with each
 * (isolated) test process.
 */

namespace GameEngine\Classes {

	if ( ! class_exists( 'GameEngine\Classes\PointsManager' ) ) {

		class PointsManager {

			/** @var array<int,array<int,int>> user id => point type id => balance */
			public static $balances = [];

			/** @var array<int,array<string,mixed>> log id => log row */
			public static $logs = [];

			/** @var int */
			public static $log_seq = 1000;

			/** @var array<int,array<string,string>> the types the selects list */
			public static $point_types = [
				[
					'id'          => 1,
					'slug'        => 'points',
					'name'        => 'Points',
					'plural_name' => 'Points',
				],
				[
					'id'          => 2,
					'slug'        => 'coins',
					'name'        => 'Coin',
					'plural_name' => 'Coins',
				],
			];

			public function add( $user_id, $points, $context = '', $args = [] ) {
				return self::write( (int) $user_id, (int) $points, (string) $context, (array) $args );
			}

			public function deduct( $user_id, $points, $context = '', $args = [] ) {
				$args     = (array) $args;
				$type     = (int) ( $args['point_type_id'] ?? 0 );
				$balance  = $type > 0 ? $this->get_total( $user_id, $type ) : $this->get_grand_total( $user_id );

				if ( $balance < (int) $points ) {
					return false;
				}

				return self::write( (int) $user_id, -1 * (int) $points, (string) $context, $args );
			}

			public function get_total( $user_id, $point_type_id = 0 ) {
				return (int) ( self::$balances[ (int) $user_id ][ (int) $point_type_id ] ?? 0 );
			}

			public function get_grand_total( $user_id ) {
				$total = 0;
				foreach ( self::$balances[ (int) $user_id ] ?? [] as $balance ) {
					$total += (int) $balance;
				}

				return $total;
			}

			public static function get_point_types() {
				return self::$point_types;
			}

			public static function get_point_type_label( $point_type_id ) {
				foreach ( self::$point_types as $type ) {
					if ( (int) $type['id'] === (int) $point_type_id ) {
						return (string) $type['plural_name'];
					}
				}

				return '';
			}

			/** The default point type when none is named. */
			public static function resolve_point_type_id( $point_type_id = 0 ) {
				return (int) $point_type_id > 0 ? (int) $point_type_id : 1;
			}

			/** Test hook: park a starting balance for a user. */
			public static function set_total( $user_id, $points, $point_type_id = 0 ) {
				self::$balances[ (int) $user_id ][ (int) $point_type_id ] = (int) $points;
			}

			/** Test hook: every log row written so far. */
			public static function get_logs() {
				return self::$logs;
			}

			private static function write( $user_id, $signed_points, $context, $args ) {
				$type   = (int) ( $args['point_type_id'] ?? 0 );
				$log_id = ++self::$log_seq;

				self::$logs[ $log_id ] = [
					'id'            => $log_id,
					'user_id'       => $user_id,
					'points'        => $signed_points,
					'point_type_id' => $type,
					'context'       => $context,
					'description'   => (string) ( $args['description'] ?? '' ),
					'created_at'    => date( 'Y-m-d H:i:s' ),
				];

				self::$balances[ $user_id ][ $type ] = (int) ( self::$balances[ $user_id ][ $type ] ?? 0 ) + $signed_points;

				return $log_id;
			}
		}
	}

	if ( ! class_exists( 'GameEngine\Classes\AchievementsManager' ) ) {

		class AchievementsManager {

			/** @var array<int,string> achievement id => title */
			public static $catalogue = [
				3 => 'First Steps',
				7 => 'Top Contributor',
			];

			/** @var array<string,int> "user:achievement" => user achievement id */
			private static $awards = [];

			/** @var int */
			private static $seq = 500;

			public function award( $user_id, $achievement_id, $context = '', $args = [] ) {
				$key = self::key( $user_id, $achievement_id );
				if ( isset( self::$awards[ $key ] ) ) {
					return false; // already held, like the real manager
				}

				return self::$awards[ $key ] = ++self::$seq;
			}

			public function revoke( $user_id, $achievement_id ) {
				$key = self::key( $user_id, $achievement_id );
				if ( ! isset( self::$awards[ $key ] ) ) {
					return false;
				}

				unset( self::$awards[ $key ] );

				return true;
			}

			public function has_achievement( $user_id, $achievement_id ) {
				return isset( self::$awards[ self::key( $user_id, $achievement_id ) ] );
			}

			public function get_user_achievements( $user_id ) {
				$rows = [];
				foreach ( self::$awards as $key => $user_achievement_id ) {
					list( $holder, $achievement_id ) = array_map( 'intval', explode( ':', $key ) );
					if ( $holder !== (int) $user_id ) {
						continue;
					}

					$rows[] = [
						'achievement_id' => $achievement_id,
						'title'          => self::$catalogue[ $achievement_id ] ?? '',
						'achieved_at'    => date( 'Y-m-d H:i:s' ),
						'user_ach_id'    => $user_achievement_id,
					];
				}

				return $rows;
			}

			private static function key( $user_id, $achievement_id ): string {
				return (int) $user_id . ':' . (int) $achievement_id;
			}
		}
	}

	if ( ! class_exists( 'GameEngine\Classes\LevelsManager' ) ) {

		class LevelsManager {

			/** @var array<int,string> level id => title */
			public static $catalogue = [
				1 => 'Rookie',
				2 => 'Apprentice',
			];

			/** @var array<int,array<int,int>> user id => level id => user level id */
			private static $awards = [];

			/** @var int */
			private static $seq = 300;

			public function award( $user_id, $level_id, $context = '', $args = [] ) {
				$user_id  = (int) $user_id;
				$level_id = (int) $level_id;

				if ( isset( self::$awards[ $user_id ][ $level_id ] ) ) {
					return false; // already held
				}

				return self::$awards[ $user_id ][ $level_id ] = ++self::$seq;
			}

			public function has_level( $user_id, $level_id ) {
				return isset( self::$awards[ (int) $user_id ][ (int) $level_id ] );
			}

			/** The highest level held, shaped like the real manager's row. */
			public function get_current_level( $user_id ) {
				$held = self::$awards[ (int) $user_id ] ?? [];
				if ( empty( $held ) ) {
					return null;
				}

				$level_id = max( array_keys( $held ) );

				return (object) [
					'id'           => $level_id,
					'title'        => self::$catalogue[ $level_id ] ?? ( 'Level ' . $level_id ),
					'user_level_id' => $held[ $level_id ],
				];
			}
		}
	}

	if ( ! class_exists( 'GameEngine\Classes\LeaderboardManager' ) ) {

		class LeaderboardManager {

			/** @var array<int,array<string,mixed>> the rows get_rows() answers with */
			public static $rows = [];

			/** @var array{position:int,total_points:int}|null a member's standing */
			public static $position = null;

			public static function resolve_range( $slug ) {
				return [
					'start' => null,
					'end'   => null,
				];
			}

			public static function get_rows( $args = [] ) {
				return self::$rows;
			}

			public static function get_user_position( $user_id, $args = [] ) {
				return self::$position;
			}
		}
	}

	if ( ! class_exists( 'GameEngine\Classes\TriggerRegistry' ) ) {

		class TriggerRegistry {

			/** @var array<string,array<string,mixed>> event key => registry config */
			public static $triggers = [
				'user_register' => [
					'label' => 'User Registration',
					'hook'  => 'user_register',
				],
			];

			public static function get( $key ) {
				return self::$triggers[ (string) $key ] ?? null;
			}

			public static function get_all_triggers() {
				return self::$triggers;
			}

			public static function get_actions( $config ) {
				return [ 'award', 'deduct' ];
			}
		}
	}

	if ( ! class_exists( 'GameEngine\Classes\Triggers' ) ) {

		/**
		 * Stand-in for the rule engine. It records each run instead of reading
		 * the requirements table, so a test can assert the action handed it the
		 * right event and member.
		 */
		class Triggers {

			/** @var array<int,array<string,mixed>> every execute() call so far */
			public static $runs = [];

			public function execute( $trigger_key, $config, $hook_args ) {
				self::$runs[] = [
					'trigger_key' => (string) $trigger_key,
					'user_id'     => (int) call_user_func_array( $config['get_user_id'], (array) $hook_args ),
					'hook_args'   => array_values( (array) $hook_args ),
				];
			}
		}
	}
}

namespace GameEngine\Addons\RewardsStore {

	if ( ! class_exists( 'GameEngine\Addons\RewardsStore\Rewards_Manager' ) ) {

		class Rewards_Manager {

			/** @var array<int,array<string,mixed>> reward id => reward row */
			public static $catalogue = [
				4 => [
					'title' => 'Sticker Pack',
					'cost'  => 500,
					'stock' => 10,
				],
			];

			public function redeem( $user_id, $reward_id ) {
				$reward = self::$catalogue[ (int) $reward_id ] ?? null;
				if ( ! $reward ) {
					return [
						'success' => false,
						'message' => 'Reward not found.',
					];
				}

				$points = new \GameEngine\Classes\PointsManager();

				if ( $points->get_grand_total( $user_id ) < (int) $reward['cost'] ) {
					return [
						'success' => false,
						'message' => 'Not enough points to redeem this reward.',
					];
				}

				$points->deduct( $user_id, (int) $reward['cost'], 'reward_redeem', [
					'point_type_id' => 1,
					'description'   => 'Redeemed reward: ' . $reward['title'],
				] );

				if ( $reward['stock'] > 0 ) {
					$reward['stock']--;
					self::$catalogue[ (int) $reward_id ] = $reward;
				}

				return [
					'success'          => true,
					'message'          => 'Reward redeemed.',
					'remaining_points' => $points->get_grand_total( $user_id ),
					'remaining_stock'  => $reward['stock'],
				];
			}
		}
	}
}

namespace GameEngine\Pro\Addons {

	if ( ! class_exists( 'GameEngine\Pro\Addons\Rewards_Marketplace' ) ) {

		class Rewards_Marketplace {

			public static function generate_coupon( $user_id, $amount, $discount_type = 'percent', $points_cost = 0, $expiry_days = 30 ) {
				if ( (int) $points_cost > 0 ) {
					$points = new \GameEngine\Classes\PointsManager();
					$taken  = $points->deduct( $user_id, (int) $points_cost, 'marketplace_coupon', [
						'point_type_id' => 1,
						'description'   => 'Marketplace coupon purchase',
					] );

					if ( ! $taken ) {
						return new \WP_Error( 'ge_insufficient_points', 'Not enough points for that coupon.' );
					}
				}

				return 'GE-' . strtoupper( wp_generate_password( 8, false ) );
			}
		}
	}

	if ( ! class_exists( 'GameEngine\Pro\Addons\Points_Payouts' ) ) {

		class Points_Payouts {

			/** @var int */
			public static $request_seq = 50;

			public static function create_request( $user_id, $points, $method = 'paypal', $details = '' ) {
				$manager = new \GameEngine\Classes\PointsManager();
				$taken   = $manager->deduct( $user_id, (int) $points, 'payout_request', [
					'point_type_id' => 1,
					'description'   => sprintf( 'Payout request for %d points via %s.', (int) $points, $method ),
				] );

				if ( ! $taken ) {
					return new \WP_Error( 'ge_insufficient_points', 'Not enough points for that payout.' );
				}

				return ++self::$request_seq;
			}
		}
	}
}

namespace {

	/**
	 * Stand-in for GameEngine\Pro\Pro_Helper. The mock suite keeps Pro "off" by
	 * default (no GAMEENGINE_PRO_VERSION, no Pro_Helper class); a test that
	 * wants the Pro gate open defines the constant and aliases this stub into
	 * the real class name, which is how the payout refund path gets exercised
	 * without every test inheriting a Pro licence.
	 */
	if ( ! class_exists( 'Zaplane_Ge_ProHelper_Stub' ) ) {
		class Zaplane_Ge_ProHelper_Stub {

			/** @var array<int,array<string,int>> recorded refunds for assertions */
			public static $refunds = [];

			public static function refund_points( $user_id, $points, $context = '', $description = '' ) {
				$points_manager = new \GameEngine\Classes\PointsManager();
				$log_id         = $points_manager->add( $user_id, $points, $context, [
					'point_type_id' => 1,
					'description'   => $description,
				] );

				self::$refunds[] = [
					'user_id' => (int) $user_id,
					'points'  => (int) $points,
					'log_id'  => $log_id,
				];

				return (bool) $log_id;
			}
		}
	}

	if ( ! function_exists( 'gameengine_add_points' ) ) {
		function gameengine_add_points( $user_id, $points, $context = '', $args = [] ) {
			return ( new \GameEngine\Classes\PointsManager() )->add( $user_id, $points, $context, $args );
		}
	}

	if ( ! function_exists( 'gameengine_deduct_points' ) ) {
		function gameengine_deduct_points( $user_id, $points, $context = '', $args = [] ) {
			return ( new \GameEngine\Classes\PointsManager() )->deduct( $user_id, $points, $context, $args );
		}
	}

	if ( ! function_exists( 'gameengine_get_total_points' ) ) {
		function gameengine_get_total_points( $user_id, $point_type_id = 0 ) {
			$points = new \GameEngine\Classes\PointsManager();

			return $point_type_id > 0
				? $points->get_total( $user_id, $point_type_id )
				: $points->get_grand_total( $user_id );
		}
	}
}
