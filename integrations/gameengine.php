<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Gameengine\ActionsTrait;
use Zaplane\Integrations\Gameengine\CrudTrait;
use Zaplane\Integrations\Gameengine\QueryTrait;
use Zaplane\Integrations\Gameengine\Helper;

class Gameengine extends IntegrationBase {

	use ActionsTrait;
	use CrudTrait;
	use QueryTrait;
	use Helper;

	public static function get_slug(): string {
		return 'gameengine';
	}

	public static function get_name(): string {
		return 'GameEngine';
	}

	public static function get_icon(): string {
		return 'gameengine.svg';
	}

	public static function get_docs_url(): array {
		return [
			'trigger' => 'https://zaplane.app/docs/gameengine/',
			'action'  => 'https://zaplane.app/docs/gameengine/',
		];
	}

	private static function gameengine_active(): bool {
		return function_exists( 'gameengine_add_points' ) || class_exists( '\GameEngine\Classes\PointsManager' );
	}

	private static function pro_active(): bool {
		return defined( 'GAMEENGINE_PRO_VERSION' ) || class_exists( '\GameEngine\Pro\Pro_Helper' );
	}

	private static function pro_meta( array $item ): array {
		$item['requires_addon'] = 'GameEngine Pro';

		if ( ! self::pro_active() ) {
			$item['disabled']        = true;
			$item['disabled_reason'] = 'Requires the GameEngine Pro plugin to be active.';
		}

		return $item;
	}

	public static function get_triggers(): array {
		$triggers = [
			'points_awarded'          => [
				'label'       => 'Points Awarded',
				'hook'        => 'gameengine_points_added',
				'description' => 'When a user earns points from any source.',
			],
			'points_deducted'         => [
				'label'       => 'Points Deducted',
				'hook'        => 'gameengine_points_deducted',
				'description' => 'When points are taken from a user balance.',
			],
			'points_balance_changed'  => [
				'label'       => 'User Points Balance Changed',
				'hook'        => [ 'gameengine_points_added', 'gameengine_points_deducted' ],
				'description' => 'When a balance moves in either direction.',
			],
			'achievement_earned'      => [
				'label'       => 'Achievement Earned',
				'hook'        => 'gameengine_achievement_unlocked',
				'description' => 'When a user unlocks any achievement.',
			],
			'specific_achievement'    => [
				'label'       => 'Specific Achievement Earned',
				'hook'        => 'gameengine_achievement_unlocked',
				'description' => 'When one chosen achievement is unlocked.',
			],
			'level_reached'           => [
				'label'       => 'Level Reached',
				'hook'        => 'gameengine_level_awarded',
				'description' => 'When a user is awarded any level.',
			],
			'specific_level'          => [
				'label'       => 'Specific Level Reached',
				'hook'        => 'gameengine_level_awarded',
				'description' => 'When one chosen level is awarded.',
			],
			'rank_changed'            => self::pro_meta( [
				'label'       => 'Rank Changed (Loyalty Tier)',
				'hook'        => 'gameengine_pro_tier_changed',
				'description' => 'When a user moves to another loyalty tier — GameEngine keeps ranks as tiers/levels.',
			] ),
			'reward_redeemed'         => [
				'label'       => 'Reward Redeemed',
				'hook'        => 'gameengine_reward_redeemed',
				'description' => 'When a user redeems a reward from the Rewards Store.',
			],
			'coupon_generated'        => self::pro_meta( [
				'label'       => 'Coupon Generated',
				'hook'        => 'save_post_shop_coupon',
				'description' => 'When the Rewards Marketplace issues a GE- coupon (needs WooCommerce).',
			] ),
			'points_used_at_checkout' => [
				'label'       => 'Points Used at Checkout',
				'hook'        => 'gameengine_points_deducted',
				'description' => 'When an order is paid fully or partly with points.',
			],
			'points_transferred'      => self::pro_meta( [
				'label'       => 'Points Transferred',
				'hook'        => [ 'gameengine_points_added', 'gameengine_points_deducted' ],
				'description' => 'When a user sends or receives points from another member.',
			] ),
			'points_expired'          => self::pro_meta( [
				'label'       => 'Points Expired',
				'hook'        => 'gameengine_points_deducted',
				'description' => 'When expiring points are removed from a balance.',
			] ),
			'payout_requested'        => self::pro_meta( [
				'label'       => 'Payout Requested',
				'hook'        => 'gameengine_points_deducted',
				'description' => 'When a member submits a withdrawal request (detected from the payout deduction).',
			] ),
			'payout_rejected'         => self::pro_meta( [
				'label'       => 'Payout Rejected',
				'hook'        => 'gameengine_points_added',
				'description' => 'When an admin rejects a withdrawal and the points are refunded.',
			] ),
			'referral_completed'      => self::pro_meta( [
				'label'       => 'Referral Completed',
				'hook'        => 'gameengine_referral_signup',
				'description' => 'When a referred member signs up.',
			] ),
			'affiliate_reward'        => self::pro_meta( [
				'label'       => 'Affiliate Reward Earned',
				'hook'        => 'gameengine_points_added',
				'description' => 'When a referrer is paid their commission points.',
			] ),
		];

		return array_merge( $triggers, self::crud_triggers() );
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		$point_type_field = [
			'key'      => 'point_type',
			'label'    => 'Point Type',
			'type'     => 'select',
			'dynamic'  => [
				'integration' => 'gameengine',
				'query'       => 'point_types_query',
				'select'      => [ 'value', 'label' ],
			],
			'required' => false,
			'default'  => '',
			'help'     => 'Leave empty to accept every point type.',
		];

		$min_points_field = [
			'key'      => 'min_points',
			'label'    => 'Minimum Points',
			'type'     => 'number',
			'required' => false,
			'default'  => 0,
			'help'     => 'Only fire when at least this many points moved.',
		];

		switch ( $trigger ) {
			case 'points_awarded':
			case 'points_deducted':
			case 'points_balance_changed':
			case 'points_used_at_checkout':
			case 'points_expired':
			case 'payout_rejected':
			case 'affiliate_reward':
				return [
					$point_type_field,
					$min_points_field,
				];

			case 'specific_achievement':
				return [
					[
						'key'      => 'achievement_id',
						'label'    => 'Achievement',
						'type'     => 'select',
						'dynamic'  => [
							'integration' => 'gameengine',
							'query'       => 'achievements_query',
							'select'      => [ 'value', 'label' ],
						],
						'required' => true,
					],
				];

			case 'specific_level':
				return [
					[
						'key'      => 'level_id',
						'label'    => 'Level',
						'type'     => 'select',
						'dynamic'  => [
							'integration' => 'gameengine',
							'query'       => 'levels_query',
							'select'      => [ 'value', 'label' ],
						],
						'required' => true,
					],
				];

			case 'reward_redeemed':
				return [
					[
						'key'      => 'reward_id',
						'label'    => 'Reward',
						'type'     => 'select',
						'dynamic'  => [
							'integration' => 'gameengine',
							'query'       => 'rewards_query',
							'select'      => [ 'value', 'label' ],
						],
						'required' => false,
						'default'  => '',
						'help'     => 'Leave empty to accept every reward.',
					],
				];

			case 'points_transferred':
				return [
					[
						'key'      => 'direction',
						'label'    => 'Direction',
						'type'     => 'select',
						'required' => false,
						'default'  => 'any',
						'options'  => [
							[
								'value' => 'any',
								'label' => 'Any direction'
							],
							[
								'value' => 'sent',
								'label' => 'Sent by the user'
							],
							[
								'value' => 'received',
								'label' => 'Received by the user'
							],
						],
					],
					$point_type_field,
					$min_points_field,
				];

			case 'payout_requested':
				return [
					[
						'key'      => 'min_points',
						'label'    => 'Minimum Points',
						'type'     => 'number',
						'required' => false,
						'default'  => 0,
						'help'     => 'Only fire for withdrawals of at least this many points.',
					],
				];
		}//end switch

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$event  = (string) ( $node['event'] ?? ( $node['data']['event'] ?? '' ) );
		$config = (array) ( $node['config'] ?? ( $node['data']['config'] ?? [] ) );

		// GameEngine's own admin API writes its content tables without firing
		// a hook, so the CRUD triggers watch that API dispatch instead.
		if ( null !== self::crud_event_entity( $event ) ) {
			return self::resolve_crud_event( $event, $config, $args );
		}

		switch ( $event ) {

			case 'points_awarded':
			case 'points_deducted':
			case 'points_balance_changed':
			case 'points_used_at_checkout':
			case 'points_transferred':
			case 'points_expired':
			case 'payout_requested':
			case 'payout_rejected':
			case 'affiliate_reward':
				return self::resolve_points_event( $event, $config, $args );

			case 'achievement_earned':
			case 'specific_achievement':
				$user_id        = (int) ( $args[0] ?? 0 );
				$achievement_id = (int) ( $args[1] ?? 0 );

				if ( $user_id <= 0 || $achievement_id <= 0 ) {
					return false;
				}

				if ( 'specific_achievement' === $event && (int) ( $config['achievement_id'] ?? 0 ) !== $achievement_id ) {
					return false;
				}

				$user = self::user_payload( $user_id );
				if ( ! $user ) {
					return false;
				}

				return [
					'success'             => true,
					'user'                => $user,
					'achievement_id'      => $achievement_id,
					'achievement'         => self::achievement_title( $achievement_id ),
					'user_achievement_id' => (int) ( $args[2] ?? 0 ),
					'context'             => 'achievement_unlock',
					'timestamp'           => current_time( 'mysql' ),
				];

			case 'level_reached':
			case 'specific_level':
				$user_id  = (int) ( $args[0] ?? 0 );
				$level_id = (int) ( $args[1] ?? 0 );

				if ( $user_id <= 0 || $level_id <= 0 ) {
					return false;
				}

				if ( 'specific_level' === $event && (int) ( $config['level_id'] ?? 0 ) !== $level_id ) {
					return false;
				}

				$user = self::user_payload( $user_id );
				if ( ! $user ) {
					return false;
				}

				return [
					'success'        => true,
					'user'           => $user,
					'level_id'       => $level_id,
					'level'          => self::level_title( $level_id ),
					'user_level_id'  => (int) ( $args[2] ?? 0 ),
					'level_number'   => self::level_number( $level_id ),
					'timestamp'      => current_time( 'mysql' ),
				];

			case 'rank_changed':
				$user_id = (int) ( $args[0] ?? 0 );
				$old     = (string) ( $args[1] ?? '' );
				$new     = (string) ( $args[2] ?? '' );

				if ( $user_id <= 0 || '' === $new ) {
					return false;
				}

				$user = self::user_payload( $user_id );
				if ( ! $user ) {
					return false;
				}

				return [
					'success'    => true,
					'user'       => $user,
					'old_rank'   => $old,
					'new_rank'   => $new,
					'rank'       => $new,
					'timestamp'  => current_time( 'mysql' ),
				];

			case 'reward_redeemed':
				$user_id   = (int) ( $args[0] ?? 0 );
				$reward_id = (int) ( $args[1] ?? 0 );

				if ( $user_id <= 0 || $reward_id <= 0 ) {
					return false;
				}

				if ( ! empty( $config['reward_id'] ) && (int) $config['reward_id'] !== $reward_id ) {
					return false;
				}

				$user = self::user_payload( $user_id );
				if ( ! $user ) {
					return false;
				}

				return [
					'success'     => true,
					'user'        => $user,
					'reward_id'   => $reward_id,
					'reward'      => self::reward_title( $reward_id ),
					'points_spent' => (int) ( $args[2] ?? 0 ),
					'balance'     => self::balance( $user_id ),
					'timestamp'   => current_time( 'mysql' ),
				];

			case 'coupon_generated':
				$post_id = (int) ( $args[0] ?? 0 );
				$post    = isset( $args[1] ) && is_object( $args[1] ) ? $args[1] : get_post( $post_id );

				if ( ! $post || 'shop_coupon' !== ( $post->post_type ?? '' ) ) {
					return false;
				}

				$prefix = (string) ( $config['prefix'] ?? 'GE-' );
				$code   = (string) ( $post->post_title ?? '' );
				if ( '' !== $prefix && 0 !== strpos( $code, $prefix ) ) {
					return false;
				}

				return [
					'success'        => true,
					'post_id'        => $post_id,
					'code'           => $code,
					'discount_type'  => (string) get_post_meta( $post_id, 'discount_type', true ),
					'discount_amount' => (string) get_post_meta( $post_id, 'coupon_amount', true ),
					'user_id'        => (int) ( $post->post_author ?? 0 ),
					'timestamp'      => current_time( 'mysql' ),
				];

			case 'referral_completed':
				$referrer_id = (int) ( $args[0] ?? 0 );
				$referee_id  = (int) ( $args[1] ?? 0 );

				if ( $referrer_id <= 0 || $referee_id <= 0 ) {
					return false;
				}

				$referrer = self::user_payload( $referrer_id );
				$referee  = self::user_payload( $referee_id );
				if ( ! $referrer || ! $referee ) {
					return false;
				}

				return [
					'success'  => true,
					'user'     => $referrer,
					'referrer' => $referrer,
					'referee'  => $referee,
					'timestamp' => current_time( 'mysql' ),
				];
		}//end switch

		return false;
	}

	public static function get_trigger_sample_output( string $event ): array {
		$user = [
			'user_id'      => 42,
			'user_login'   => 'janedoe',
			'user_email'   => 'jane.doe@example.com',
			'display_name' => 'Jane Doe',
			'roles'        => [ 'subscriber' ],
			'avatar_url'   => 'https://example.com/avatar.png',
		];

		$points_base = [
			'success'     => true,
			'user'        => $user,
			'points'      => 50,
			'balance'     => 1250,
			'context'     => 'achievement_unlock',
			'direction'   => 'increase',
			'point_type_id' => 1,
			'point_type'  => 'points',
			'log_id'      => 128,
			'description' => 'Unlocked: First Steps',
			'created_at'  => current_time( 'mysql' ),
		];

		$samples = [
			'points_awarded'          => $points_base,
			'points_deducted'         => array_merge( $points_base, [
				'points'      => 25,
				'balance'     => 1225,
				'context'     => 'reward_redeem',
				'direction'   => 'decrease',
				'description' => 'Redeemed reward: Sticker Pack',
			] ),
			'points_balance_changed'  => $points_base,
			'points_used_at_checkout' => array_merge( $points_base, [
				'points'      => 100,
				'context'     => 'wc_points_payment',
				'direction'   => 'decrease',
				'description' => 'Paid with points',
			] ),
			'points_transferred'      => array_merge( $points_base, [
				'points'        => 100,
				'context'       => 'transfer_sent',
				'direction'     => 'sent',
				'other_user_id' => 7,
				'description'   => 'Transferred 100 points to user #7',
			] ),
			'points_expired'          => array_merge( $points_base, [
				'points'      => 75,
				'context'     => 'expired',
				'direction'   => 'decrease',
				'description' => 'Points expired',
			] ),
			'payout_requested'        => array_merge( $points_base, [
				'points'      => 1000,
				'context'     => 'payout_request',
				'direction'   => 'decrease',
				'method'      => 'paypal',
				'description' => 'Payout request for 10.00 $ via paypal.',
			] ),
			'payout_rejected'         => array_merge( $points_base, [
				'points'      => 1000,
				'context'     => 'payout_refund',
				'direction'   => 'increase',
				'description' => 'Payout rejected by admin.',
			] ),
			'affiliate_reward'        => array_merge( $points_base, [
				'points'      => 50,
				'context'     => 'referral_signup',
				'description' => 'Referral signup reward',
			] ),
			'achievement_earned'      => [
				'success'             => true,
				'user'                => $user,
				'achievement_id'      => 3,
				'achievement'         => 'First Steps',
				'user_achievement_id' => 17,
				'context'             => 'achievement_unlock',
				'timestamp'           => current_time( 'mysql' ),
			],
			'specific_achievement'    => [
				'success'             => true,
				'user'                => $user,
				'achievement_id'      => 3,
				'achievement'         => 'First Steps',
				'user_achievement_id' => 17,
				'context'             => 'achievement_unlock',
				'timestamp'           => current_time( 'mysql' ),
			],
			'level_reached'           => [
				'success'       => true,
				'user'          => $user,
				'level_id'      => 2,
				'level'         => 'Apprentice',
				'user_level_id' => 8,
				'level_number'  => 2,
				'timestamp'     => current_time( 'mysql' ),
			],
			'specific_level'          => [
				'success'       => true,
				'user'          => $user,
				'level_id'      => 2,
				'level'         => 'Apprentice',
				'user_level_id' => 8,
				'level_number'  => 2,
				'timestamp'     => current_time( 'mysql' ),
			],
			'rank_changed'            => [
				'success'   => true,
				'user'      => $user,
				'old_rank'  => 'Bronze',
				'new_rank'  => 'Silver',
				'rank'      => 'Silver',
				'timestamp' => current_time( 'mysql' ),
			],
			'reward_redeemed'         => [
				'success'      => true,
				'user'         => $user,
				'reward_id'    => 4,
				'reward'       => 'Sticker Pack',
				'points_spent' => 500,
				'balance'      => 1250,
				'timestamp'    => current_time( 'mysql' ),
			],
			'coupon_generated'        => [
				'success'         => true,
				'post_id'         => 99,
				'code'            => 'GE-ABCD2345',
				'discount_type'   => 'percent',
				'discount_amount' => '10',
				'user_id'         => 42,
				'timestamp'       => current_time( 'mysql' ),
			],
			'referral_completed'      => [
				'success'   => true,
				'user'      => $user,
				'referrer'  => $user,
				'referee'   => [
					'user_id'      => 43,
					'user_login'   => 'newmember',
					'user_email'   => 'new.member@example.com',
					'display_name' => 'New Member',
					'roles'        => [ 'subscriber' ],
					'avatar_url'   => 'https://example.com/avatar2.png',
				],
				'timestamp' => current_time( 'mysql' ),
			],
		];

		if ( isset( $samples[ $event ] ) ) {
			return $samples[ $event ];
		}

		return self::crud_sample( $event ) ?? [];
	}

	public static function get_actions(): array {
		$actions = [
			'award_points'       => [
				'label'       => 'Award Points',
				'description' => 'Add points to a user balance.',
			],
			'deduct_points'      => [
				'label'       => 'Deduct Points',
				'description' => 'Take points from a user balance.',
			],
			'set_balance'        => [
				'label'       => 'Set Balance',
				'description' => 'Adjust a balance to an exact value.',
			],
			'get_balance'        => [
				'label'       => 'Get Balance',
				'description' => 'Read a user points balance.',
			],
			'get_transactions'   => [
				'label'       => 'Get Transactions',
				'description' => 'List the latest points transactions.',
			],
			'award_achievement'  => [
				'label'       => 'Award Achievement',
				'description' => 'Grant an achievement to a user.',
			],
			'revoke_achievement' => [
				'label'       => 'Revoke Achievement',
				'description' => 'Take an achievement back from a user.',
			],
			'get_achievements'   => [
				'label'       => 'Get Achievements',
				'description' => 'List the achievements a user earned.',
			],
			'assign_level'       => [
				'label'       => 'Assign Level',
				'description' => 'Grant a level to a user.',
			],
			'change_level'       => [
				'label'       => 'Change Level',
				'description' => 'Move a user to another level.',
			],
			'get_level'          => [
				'label'       => 'Get Level',
				'description' => 'Read the current level of a user.',
			],
			'assign_rank'        => [
				'label'       => 'Assign Rank',
				'description' => 'Grant a rank — GameEngine keeps ranks as levels.',
			],
			'get_rank'           => [
				'label'       => 'Get Rank',
				'description' => 'Read the current rank of a user (level based).',
			],
			'unlock_content'     => [
				'label'       => 'Unlock Content',
				'description' => 'Remove GameEngine restrictions from a post or page.',
			],
			'lock_content'       => [
				'label'       => 'Lock Content',
				'description' => 'Restrict a post or page behind points, an achievement or a level.',
			],
			'generate_coupon'    => self::pro_meta( [
				'label'       => 'Generate Coupon',
				'description' => 'Issue a marketplace discount coupon (spends the reward cost).',
			] ),
			'redeem_reward'      => [
				'label'       => 'Redeem Reward',
				'description' => 'Redeem a Rewards Store item for a user.',
			],
			'transfer_points'    => self::pro_meta( [
				'label'       => 'Transfer Points',
				'description' => 'Move points from one member to another.',
			] ),
			'create_payout'      => self::pro_meta( [
				'label'       => 'Create Payout Request',
				'description' => 'Open a withdrawal request for a user.',
			] ),
			'update_payout'      => self::pro_meta( [
				'label'       => 'Update Payout Status',
				'description' => 'Approve, complete or reject a withdrawal.',
			] ),
			'approve_payout'     => self::pro_meta( [
				'label'       => 'Approve Payout Request',
				'description' => 'Approve a pending withdrawal.',
			] ),
			'reject_payout'      => self::pro_meta( [
				'label'       => 'Reject Payout Request',
				'description' => 'Reject a withdrawal and refund the deducted points.',
			] ),
			'get_payout'         => self::pro_meta( [
				'label'       => 'Get Payout Request',
				'description' => 'Read one withdrawal request back.',
			] ),
			'get_profile'        => [
				'label'       => 'Get Gamification Profile',
				'description' => 'Read a member balances, level, rank and achievement tally.',
			],
			'get_leaderboard'    => [
				'label'       => 'Get Leaderboard',
				'description' => 'List a ranked page of members for a point type and window.',
			],
			'get_leaderboard_position' => [
				'label'       => 'Get Leaderboard Position',
				'description' => 'Read where one member stands on the leaderboard.',
			],
			'trigger_event'      => [
				'label'       => 'Trigger GameEngine Event',
				'description' => 'Run a registered GameEngine event and its saved rules for a member.',
			],
			'get_activity_logs'  => [
				'label'       => 'Get Activity Logs',
				'description' => 'List the GameEngine activity log entries.',
			],
		];

		return array_merge( $actions, self::crud_actions() );
	}

	public static function get_action_config_schema( string $action ): array {
		$crud_schema = self::crud_action_schema( $action );

		if ( null !== $crud_schema ) {
			return $crud_schema;
		}

		$user_field = [
			'key'         => 'user_id',
			'label'       => 'User ID',
			'type'        => 'expression',
			'required'    => false,
			'placeholder' => '{{trigger.user.user_id}}',
			'help'        => 'A user ID or an expression. Leave empty to use the current user.',
		];

		$point_type_field = [
			'key'      => 'point_type',
			'label'    => 'Point Type',
			'type'     => 'select',
			'dynamic'  => [
				'integration' => 'gameengine',
				'query'       => 'point_types_query',
				'select'      => [ 'value', 'label' ],
			],
			'required' => false,
			'default'  => '',
		];

		$achievement_field = [
			'key'      => 'achievement_id',
			'label'    => 'Achievement',
			'type'     => 'select',
			'dynamic'  => [
				'integration' => 'gameengine',
				'query'       => 'achievements_query',
				'select'      => [ 'value', 'label' ],
			],
			'required' => true,
		];

		$level_field = [
			'key'      => 'level_id',
			'label'    => 'Level',
			'type'     => 'select',
			'dynamic'  => [
				'integration' => 'gameengine',
				'query'       => 'levels_query',
				'select'      => [ 'value', 'label' ],
			],
			'required' => true,
		];

		$post_field = [
			'key'      => 'post_id',
			'label'    => 'Content',
			'type'     => 'select',
			'dynamic'  => [
				'integration' => 'gameengine',
				'query'       => 'posts_query',
				'select'      => [ 'value', 'label' ],
			],
			'required' => true,
		];

		$payout_field = [
			'key'      => 'payout_id',
			'label'    => 'Payout ID',
			'type'     => 'select',
			'dynamic'  => [
				'integration' => 'gameengine',
				'query'       => 'payouts_query',
				'select'      => [ 'value', 'label' ],
			],
			'required' => true,
		];

		$time_range_field = [
			'key'      => 'time_range',
			'label'    => 'Time Window',
			'type'     => 'select',
			'required' => false,
			'default'  => 'all_time',
			'options'  => [
				[
					'value' => 'all_time',
					'label' => 'All time'
				],
				[
					'value' => 'today',
					'label' => 'Today'
				],
				[
					'value' => 'this_week',
					'label' => 'This week'
				],
				[
					'value' => 'this_month',
					'label' => 'This month'
				],
				[
					'value' => 'this_year',
					'label' => 'This year'
				],
				[
					'value' => 'last_30_days',
					'label' => 'Last 30 days'
				],
			],
		];

		switch ( $action ) {
			case 'award_points':
				return [
					$user_field,
					[
						'key'      => 'points',
						'label'    => 'Points',
						'type'     => 'number',
						'required' => true,
					],
					$point_type_field,
					[
						'key'         => 'reason',
						'label'       => 'Reason',
						'type'        => 'text',
						'required'    => false,
						'default'     => '',
						'placeholder' => 'Daily login bonus',
						'help'        => 'Stored on the transaction and shown in the points history.',
					],
					[
						'key'         => 'context',
						'label'       => 'Context',
						'type'        => 'text',
						'required'    => false,
						'default'     => '',
						'placeholder' => 'manual_adjustment',
						'help'        => 'The reason code GameEngine stores with the transaction. Empty uses manual_adjustment.',
					],
				];

			case 'deduct_points':
				return [
					$user_field,
					[
						'key'      => 'points',
						'label'    => 'Points',
						'type'     => 'number',
						'required' => true,
					],
					$point_type_field,
					[
						'key'         => 'reason',
						'label'       => 'Reason',
						'type'        => 'text',
						'required'    => false,
						'default'     => '',
					],
					[
						'key'         => 'context',
						'label'       => 'Context',
						'type'        => 'text',
						'required'    => false,
						'default'     => '',
						'placeholder' => 'manual_adjustment',
					],
				];

			case 'set_balance':
				return [
					$user_field,
					[
						'key'      => 'points',
						'label'    => 'New Balance',
						'type'     => 'number',
						'required' => true,
						'help'     => 'The difference is added or deducted as a manual adjustment.',
					],
					$point_type_field,
				];

			case 'get_balance':
				return [
					$user_field,
					$point_type_field,
				];

			case 'get_transactions':
				return [
					$user_field,
					[
						'key'      => 'limit',
						'label'    => 'Transactions',
						'type'     => 'number',
						'required' => false,
						'default'  => 10,
					],
				];

			case 'award_achievement':
				return [ $user_field, $achievement_field ];

			case 'revoke_achievement':
				return [ $user_field, $achievement_field ];

			case 'get_achievements':
				return [ $user_field ];

			case 'assign_level':
			case 'change_level':
				return [ $user_field, $level_field ];

			case 'get_level':
				return [ $user_field ];

			case 'assign_rank':
				return [
					$user_field,
					[
						'key'      => 'rank_id',
						'label'    => 'Rank',
						'type'     => 'select',
						'dynamic'  => [
							'integration' => 'gameengine',
							'query'       => 'levels_query',
							'select'      => [ 'value', 'label' ],
						],
						'required' => true,
						'help'     => 'GameEngine keeps ranks as levels, so ranks come from the level list.',
					],
				];

			case 'get_rank':
				return [ $user_field ];

			case 'unlock_content':
				return [ $post_field ];

			case 'lock_content':
				return [
					$post_field,
					[
						'key'      => 'restrict_type',
						'label'    => 'Restriction',
						'type'     => 'select',
						'required' => true,
						'default'  => 'points',
						'options'  => [
							[
								'value' => 'points',
								'label' => 'Points'
							],
							[
								'value' => 'achievement',
								'label' => 'Achievement'
							],
							[
								'value' => 'level',
								'label' => 'Level'
							],
						],
					],
					[
						'key'         => 'restrict_value',
						'label'       => 'Required Value',
						'type'        => 'expression',
						'required'    => true,
						'placeholder' => '100',
						'help'        => 'Point amount for Points, or the achievement/level ID for the other types.',
					],
					[
						'key'         => 'message',
						'label'       => 'Lock Message',
						'type'        => 'textarea',
						'required'    => false,
						'default'     => '',
						'help'        => 'Shown instead of the content. Empty keeps the message saved in the editor.',
					],
				];

			case 'generate_coupon':
				return [
					$user_field,
					[
						'key'      => 'amount',
						'label'    => 'Discount',
						'type'     => 'number',
						'required' => true,
						'help'     => 'Coupon amount — 10 with Percent means 10% off.',
					],
					[
						'key'      => 'discount_type',
						'label'    => 'Discount Type',
						'type'     => 'select',
						'required' => true,
						'default'  => 'percent',
						'options'  => [
							[
								'value' => 'percent',
								'label' => 'Percent'
							],
							[
								'value' => 'fixed_cart',
								'label' => 'Fixed cart amount'
							],
						],
					],
					[
						'key'      => 'points_cost',
						'label'    => 'Points Cost',
						'type'     => 'number',
						'required' => true,
						'help'     => 'Points taken from the user for the coupon. 0 issues it free.',
					],
					[
						'key'      => 'expiry_days',
						'label'    => 'Expires In (days)',
						'type'     => 'number',
						'required' => false,
						'default'  => 30,
					],
				];

			case 'redeem_reward':
				return [
					$user_field,
					[
						'key'      => 'reward_id',
						'label'    => 'Reward',
						'type'     => 'select',
						'dynamic'  => [
							'integration' => 'gameengine',
							'query'       => 'rewards_query',
							'select'      => [ 'value', 'label' ],
						],
						'required' => true,
					],
				];

			case 'transfer_points':
				return [
					[
						'key'         => 'sender_id',
						'label'       => 'From User ID',
						'type'        => 'expression',
						'required'    => false,
						'placeholder' => '{{trigger.user.user_id}}',
					],
					[
						'key'         => 'receiver_id',
						'label'       => 'To User ID',
						'type'        => 'expression',
						'required'    => false,
						'placeholder' => '{{trigger.user.user_id}}',
					],
					[
						'key'      => 'points',
						'label'    => 'Points',
						'type'     => 'number',
						'required' => true,
					],
					$point_type_field,
					[
						'key'         => 'message',
						'label'       => 'Message',
						'type'        => 'text',
						'required'    => false,
						'default'     => '',
						'help'        => 'Stored on the transfer row the members see in their history.',
					],
				];

			case 'create_payout':
				return [
					$user_field,
					[
						'key'      => 'points',
						'label'    => 'Points',
						'type'     => 'number',
						'required' => true,
						'help'     => 'Must satisfy the minimum/maximum withdrawal limits in GameEngine settings.',
					],
					[
						'key'      => 'method',
						'label'    => 'Method',
						'type'     => 'select',
						'required' => true,
						'default'  => 'paypal',
						'options'  => [
							[
								'value' => 'paypal',
								'label' => 'PayPal'
							],
							[
								'value' => 'bank',
								'label' => 'Bank transfer'
							],
						],
					],
					[
						'key'         => 'account',
						'label'       => 'Account Details',
						'type'        => 'textarea',
						'required'    => false,
						'default'     => '',
						'help'        => 'PayPal email or bank details stored with the request.',
					],
				];

			case 'update_payout':
				return [
					$payout_field,
					[
						'key'      => 'status',
						'label'    => 'New Status',
						'type'     => 'select',
						'required' => true,
						'default'  => 'approved',
						'options'  => [
							[
								'value' => 'pending',
								'label' => 'Pending'
							],
							[
								'value' => 'approved',
								'label' => 'Approved'
							],
							[
								'value' => 'completed',
								'label' => 'Paid (completed)'
							],
							[
								'value' => 'rejected',
								'label' => 'Rejected (refunds the points)'
							],
						],
					],
				];

			case 'approve_payout':
			case 'reject_payout':
			case 'get_payout':
				return [ $payout_field ];

			case 'get_profile':
				return [ $user_field ];

			case 'get_leaderboard':
				return [
					$point_type_field,
					[
						'key'      => 'limit',
						'label'    => 'Rows',
						'type'     => 'number',
						'required' => false,
						'default'  => 10,
					],
					$time_range_field,
				];

			case 'get_leaderboard_position':
				return [
					$user_field,
					$point_type_field,
					$time_range_field,
				];

			case 'trigger_event':
				return [
					$user_field,
					[
						'key'      => 'trigger_key',
						'label'    => 'GameEngine Event',
						'type'     => 'select',
						'dynamic'  => [
							'integration' => 'gameengine',
							'query'       => 'events_query',
							'select'      => [ 'value', 'label' ],
						],
						'required' => true,
						'help'     => 'The event whose saved rules run for the member.',
					],
				];

			case 'get_activity_logs':
				return [
					[
						'key'         => 'user_id',
						'label'       => 'User ID',
						'type'        => 'expression',
						'required'    => false,
						'placeholder' => '{{trigger.user.user_id}}',
						'help'        => 'Leave empty to include every member.',
					],
					[
						'key'      => 'limit',
						'label'    => 'Entries',
						'type'     => 'number',
						'required' => false,
						'default'  => 20,
					],
					[
						'key'      => 'status',
						'label'    => 'Status',
						'type'     => 'select',
						'required' => false,
						'default'  => '',
						'options'  => [
							[
								'value' => '',
								'label' => 'Any'
							],
							[
								'value' => 'success',
								'label' => 'Success'
							],
							[
								'value' => 'failed',
								'label' => 'Failed'
							],
							[
								'value' => 'skipped',
								'label' => 'Skipped'
							],
						],
					],
				];
		}//end switch

		return [];
	}

	public static function get_action_sample_output( string $action ): array {
		$samples = [
			'award_points'       => [
				'success'        => true,
				'user_id'        => 42,
				'points'         => 50,
				'balance'        => 1300,
				'log_id'         => 256,
				'point_type_id'  => 1,
				'context'        => 'manual_adjustment',
				'description'    => 'Order reward',
			],
			'deduct_points'      => [
				'success'       => true,
				'user_id'       => 42,
				'points'        => 25,
				'balance'       => 1275,
				'log_id'        => 257,
				'point_type_id' => 1,
				'context'       => 'manual_adjustment',
				'description'   => 'Penalty',
			],
			'set_balance'        => [
				'success'  => true,
				'user_id'  => 42,
				'previous' => 1275,
				'adjusted' => 25,
				'balance'  => 1300,
			],
			'get_balance'        => [
				'success'       => true,
				'user_id'       => 42,
				'balance'       => 1300,
				'point_type_id' => 1,
			],
			'get_transactions'   => [
				'success'      => true,
				'user_id'      => 42,
				'transactions' => [
					[
						'log_id'     => 257,
						'points'     => -25,
						'context'    => 'manual_adjustment',
						'description' => 'Penalty',
						'created_at' => '2026-01-02 10:15:00',
					],
				],
			],
			'award_achievement'  => [
				'success'        => true,
				'user_id'        => 42,
				'achievement_id' => 3,
				'achievement'    => 'First Steps',
				'already_held'   => false,
			],
			'revoke_achievement' => [
				'success'        => true,
				'user_id'        => 42,
				'achievement_id' => 3,
			],
			'get_achievements'   => [
				'success'      => true,
				'user_id'      => 42,
				'achievements' => [
					[
						'achievement_id' => 3,
						'title'          => 'First Steps',
						'achieved_at'    => '2026-01-01 09:00:00',
					],
				],
			],
			'assign_level'       => [
				'success'       => true,
				'user_id'       => 42,
				'level_id'      => 2,
				'level'         => 'Apprentice',
				'user_level_id' => 9,
				'already_held'  => false,
			],
			'change_level'       => [
				'success'       => true,
				'user_id'       => 42,
				'level_id'      => 3,
				'level'         => 'Adept',
				'user_level_id' => 10,
				'already_held'  => false,
			],
			'get_level'          => [
				'success'        => true,
				'user_id'        => 42,
				'level_id'       => 3,
				'level'          => 'Adept',
				'level_number'   => 3,
				'user_level_id'  => 10,
			],
			'assign_rank'        => [
				'success'      => true,
				'user_id'      => 42,
				'rank_id'      => 2,
				'rank'         => 'Apprentice',
				'user_level_id' => 9,
				'already_held' => false,
			],
			'get_rank'           => [
				'success'   => true,
				'user_id'   => 42,
				'rank_id'   => 3,
				'rank'      => 'Adept',
			],
			'unlock_content'     => [
				'success'        => true,
				'post_id'        => 15,
				'restrict_type'  => 'none',
			],
			'lock_content'       => [
				'success'         => true,
				'post_id'         => 15,
				'restrict_type'   => 'points',
				'restrict_value'  => '100',
				'has_message'     => true,
			],
			'generate_coupon'    => [
				'success'        => true,
				'code'           => 'GE-ABCD2345',
				'discount_type'  => 'percent',
				'amount'         => 10,
				'points_cost'    => 500,
				'user_id'        => 42,
				'balance'        => 800,
			],
			'redeem_reward'      => [
				'success'          => true,
				'user_id'          => 42,
				'reward_id'        => 4,
				'message'          => 'You redeemed "Sticker Pack" for 500 points.',
				'remaining_points' => 800,
				'remaining_stock'  => 4,
			],
			'transfer_points'    => [
				'success'          => true,
				'sender_id'        => 42,
				'receiver_id'      => 7,
				'points'           => 100,
				'point_type_id'    => 1,
				'sender_balance'   => 1200,
				'receiver_balance' => 600,
				'transfer_row'     => 12,
			],
			'create_payout'      => [
				'success'   => true,
				'payout_id' => 11,
				'user_id'   => 42,
				'points'    => 1000,
				'amount'    => 10,
				'method'    => 'paypal',
				'status'    => 'pending',
			],
			'update_payout'      => [
				'success'         => true,
				'payout_id'       => 11,
				'previous_status' => 'pending',
				'status'          => 'completed',
				'user_id'         => 42,
				'points'          => 1000,
				'refunded'        => false,
			],
			'approve_payout'     => [
				'success'         => true,
				'payout_id'       => 11,
				'previous_status' => 'pending',
				'status'          => 'approved',
				'user_id'         => 42,
				'points'          => 1000,
				'refunded'        => false,
			],
			'reject_payout'      => [
				'success'         => true,
				'payout_id'       => 11,
				'previous_status' => 'approved',
				'status'          => 'rejected',
				'user_id'         => 42,
				'points'          => 1000,
				'refunded'        => true,
			],
			'get_payout'         => [
				'success'    => true,
				'payout_id'  => 11,
				'user_id'    => 42,
				'points'     => 1000,
				'amount'     => 10,
				'method'     => 'paypal',
				'status'     => 'pending',
				'notes'      => '',
				'created_at' => '2026-01-01 09:00:00',
			],
			'get_profile'        => [
				'success'             => true,
				'user_id'             => 42,
				'balance'             => 1300,
				'point_type_balances' => [
					[
						'point_type_id' => 1,
						'point_type'    => 'points',
						'balance'       => 1300,
					],
				],
				'level_id'          => 3,
				'level'             => 'Adept',
				'rank_id'           => 3,
				'rank'              => 'Adept',
				'achievement_count' => 2,
			],
			'get_leaderboard'    => [
				'success'       => true,
				'point_type_id' => 1,
				'limit'         => 10,
				'count'         => 2,
				'entries'       => [
					[
						'position'           => 1,
						'user_id'            => 42,
						'name'               => 'Jane Doe',
						'total_points'       => 1300,
						'achievements_count' => 2,
						'top_level'          => 'Adept',
					],
				],
			],
			'get_leaderboard_position' => [
				'success'       => true,
				'user_id'       => 42,
				'placed'        => true,
				'position'      => 3,
				'total_points'  => 1300,
				'point_type_id' => 1,
			],
			'trigger_event'      => [
				'success'        => true,
				'trigger_key'    => 'publish_post',
				'label'          => 'Post Published',
				'user_id'        => 42,
				'balance_before' => 1250,
				'balance_after'  => 1300,
				'balance_delta'  => 50,
			],
			'get_activity_logs'  => [
				'success' => true,
				'user_id' => 42,
				'limit'   => 20,
				'count'   => 1,
				'logs'    => [
					[
						'log_id'         => 512,
						'user_id'        => 42,
						'trigger_key'    => 'publish_post',
						'status'         => 'success',
						'points_awarded' => 50,
						'message'        => 'Awarded 50 points.',
						'created_at'     => '2026-01-01 09:00:00',
					],
				],
			],
		];

		if ( isset( $samples[ $action ] ) ) {
			return $samples[ $action ];
		}

		return self::crud_sample( $action ) ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		$event  = (string) ( $node['data']['event'] ?? '' );
		$config = (array) ( $node['data']['config'] ?? [] );
		$method = 'action_' . $event;

		if ( ! method_exists( static::class, $method ) ) {
			return self::respond( $input );
		}

		if ( ! self::gameengine_active() ) {
			return self::action_error( 'The GameEngine plugin is not active.' );
		}

		$pro_actions = array_merge(
			[
				'generate_coupon',
				'transfer_points',
				'create_payout',
				'update_payout',
				'approve_payout',
				'reject_payout',
				'get_payout',
			],
			self::crud_pro_actions()
		);
		if ( in_array( $event, $pro_actions, true ) && ! self::pro_active() ) {
			return self::action_error( 'This GameEngine action requires the GameEngine Pro plugin.' );
		}

		return static::$method( $config, $input );
	}

	public static function get_dynamic_queries(): array {
		return [
			'point_types_query'   => [ self::class, 'query_point_types' ],
			'achievements_query'  => [ self::class, 'query_achievements' ],
			'levels_query'        => [ self::class, 'query_levels' ],
			'rewards_query'       => [ self::class, 'query_rewards' ],
			'payouts_query'       => [ self::class, 'query_payouts' ],
			'posts_query'         => [ self::class, 'query_posts' ],
			'events_query'        => [ self::class, 'query_events' ],
			'badges_query'        => [ self::class, 'query_badges' ],
			'logs_query'          => [ self::class, 'query_logs' ],
			'wheels_query'        => [ self::class, 'query_wheels' ],
			'seasons_query'       => [ self::class, 'query_seasons' ],
			'webhooks_query'      => [ self::class, 'query_webhooks' ],
		];
	}

	private static function action_success( array $data ): array {
		return self::respond( array_merge( [ 'success' => true ], $data ) );
	}
}
