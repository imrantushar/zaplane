<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Traits\ActionResponseTrait;

class Gamipress extends IntegrationBase {

	use ActionResponseTrait;

	public static function get_slug(): string {
		return 'gamipress';
	}

	public static function get_name(): string {
		return 'GamiPress';
	}

	public static function get_icon(): string {
		return 'gamipress.svg';
	}

	public static function get_triggers(): array {
		return [
			'user_earns_rank' => [
				'label' => 'User Earns Rank',
				'hook'  => 'gamipress_update_user_rank',
			],
			'user_earns_points' => [
				'label' => 'User Earns Points',
				'hook'  => 'gamipress_update_user_points',
			],
			'user_earns_specific_achievement_type' => [
				'label' => 'User Earns Specific Achievement Type',
				'hook'  => 'gamipress_award_achievement',
			],
			'user_gains_achievement' => [
				'label' => 'User Gains Achievement',
				'hook'  => 'gamipress_award_achievement',
			],
			'user_achievement_revoked' => [
				'label' => 'User Achievement Was Revoked',
				'hook'  => 'gamipress_revoke_achievement_to_user',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		$achievement_type = [
			'key'      => 'achievement_type',
			'label'    => 'Achievement Type',
			'type'     => 'select',
			'dynamic'  => [
				'integration' => 'gamipress',
				'query'       => 'achievement_type_query',
				'select'      => [ 'value', 'label' ],
			],
			'required' => true,
		];

		if ( 'user_earns_rank' === $trigger ) {
			return [
				[
					'key'      => 'rank_type',
					'label'    => 'Rank Type',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'gamipress',
						'query'       => 'rank_type_query',
						'select'      => [ 'value', 'label' ],
					],
					'required' => true,
				],
				[
					'key'      => 'rank_id',
					'label'    => 'Rank',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'gamipress',
						'query'       => 'rank_query',
						'select'      => [ 'value', 'label' ],
						'depends_on'  => [ 'rank_type' ],
					],
					'required' => true,
				],
			];
		}//end if

		if ( in_array( $trigger, [ 'user_earns_points', 'user_earns_specific_achievement_type' ], true ) ) {
			return [
				$achievement_type,
			];
		}//end if

		if ( 'user_gains_achievement' === $trigger ) {
			return [
				$achievement_type,
				[
					'key'      => 'achievement_id',
					'label'    => 'Achievement',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'gamipress',
						'query'       => 'achievement_query',
						'select'      => [ 'value', 'label' ],
						'depends_on'  => [ 'achievement_type' ],
					],
					'required' => true,
				],
			];
		}//end if

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$config = $node['config'] ?? [];

		switch ( $node['event'] ) {

			case 'user_earns_rank':
				$user_id  = $args[0] ?? 0;
				$new_rank = $args[1] ?? null;

				if ( empty( $user_id ) || ! ( $new_rank instanceof \WP_Post ) ) {
					return false;
				}

				$config_rank_type = $config['rank_type'] ?? 'any';
				$config_rank_id   = $config['rank_id'] ?? 'any';

				if ( 'any' !== $config_rank_type && $new_rank->post_type !== $config_rank_type ) {
					return false;
				}

				if ( 'any' !== $config_rank_id && $new_rank->post_name !== $config_rank_id ) {
					return false;
				}

				return [
					'success'   => true,
					'user'      => self::resolve_user_payload( (int) $user_id ),
					'rank_type' => $new_rank->post_type,
					'rank'      => $new_rank->post_name,
					'rank_id'   => (int) $new_rank->ID,
				];

			case 'user_earns_points':
				$user_id        = $args[0] ?? 0;
				$new_points     = $args[1] ?? 0;
				$total_points   = $args[2] ?? 0;
				$admin_id       = $args[3] ?? 0;
				$achievement_id = $args[4] ?? 0;
				$points_type    = $args[5] ?? '';
				if ( empty( $user_id ) ) {
					return false;
				}

				$config_achievement_type = $config['achievement_type'] ?? 'any';

				if ( 'any' !== $config_achievement_type && $points_type !== $config_achievement_type ) {
					return false;
				}

				return [
					'success'        => true,
					'user'           => self::resolve_user_payload( (int) $user_id ),
					'new_points'     => (int) $new_points,
					'total_points'   => (int) $total_points,
					'points_type'    => $points_type,
					'admin_id'       => (int) $admin_id,
					'achievement_id' => (int) $achievement_id,
				];

			case 'user_earns_specific_achievement_type':
				$user_id        = $args[0] ?? 0;
				$achievement_id = $args[1] ?? 0;

				if ( empty( $user_id ) || empty( $achievement_id ) ) {
					return false;
				}

				$post = get_post( (int) $achievement_id );

				if ( ! $post ) {
					return false;
				}

				$config_achievement_type = $config['achievement_type'] ?? 'any';

				if ( 'any' !== $config_achievement_type && $post->post_type !== $config_achievement_type ) {
					return false;
				}

				return [
					'success'          => true,
					'user'             => self::resolve_user_payload( (int) $user_id ),
					'achievement_type' => $post->post_type,
					'achievement'      => $post->post_name,
					'achievement_id'   => (int) $post->ID,
				];

			case 'user_gains_achievement':
				$user_id        = $args[0] ?? 0;
				$achievement_id = $args[1] ?? 0;

				if ( empty( $user_id ) || empty( $achievement_id ) ) {
					return false;
				}

				$post = get_post( (int) $achievement_id );

				if ( ! $post ) {
					return false;
				}

				$config_achievement_type = $config['achievement_type'] ?? 'any';
				$config_achievement_id   = $config['achievement_id'] ?? 'any';

				if ( 'any' !== $config_achievement_type && $post->post_type !== $config_achievement_type ) {
					return false;
				}

				if ( 'any' !== $config_achievement_id && (string) $post->ID !== (string) $config_achievement_id ) {
					return false;
				}

				return [
					'success'          => true,
					'user'             => self::resolve_user_payload( (int) $user_id ),
					'achievement_type' => $post->post_type,
					'achievement'      => $post->post_name,
					'achievement_id'   => (int) $post->ID,
				];

			case 'user_achievement_revoked':
				$user_id        = $args[0] ?? 0;
				$achievement_id = $args[1] ?? 0;

				if ( empty( $user_id ) || empty( $achievement_id ) ) {
					return false;
				}

				$post = get_post( (int) $achievement_id );

				if ( ! $post ) {
					return false;
				}

				$parent = ! empty( $post->post_parent ) ? get_post( $post->post_parent ) : null;

				return [
					'success'        => true,
					'user'           => self::resolve_user_payload( (int) $user_id ),
					'post_id'        => (int) $achievement_id,
					'post_title'     => $parent ? $parent->post_title : '',
					'post_type'      => $parent ? $parent->post_type : $post->post_type,
					'post_author_id' => $parent ? (int) $parent->post_author : (int) $post->post_author,
					'post_content'   => $parent ? $parent->post_content : '',
					'post_parent_id' => $parent ? (int) $parent->post_parent : 0,
				];

		}//end switch

		return false;
	}

	public static function get_trigger_sample_output( string $event ): array {
		$user = [
			'user_id'      => 42,
			'first_name'   => 'Jane',
			'last_name'    => 'Doe',
			'user_login'   => 'janedoe',
			'user_email'   => 'jane.doe@example.com',
			'nickname'     => 'jane',
			'display_name' => 'Jane Doe',
			'avatar_url'   => 'https://secure.gravatar.com/avatar/0123456789abcdef?s=96&d=mm&r=g',
			'user_roles'   => [ 'subscriber' ],
			'completed_at' => current_time( 'mysql' ),
		];

		$rank = [
			'success'   => true,
			'user'      => $user,
			'rank_type' => 'rank_type',
			'rank'      => 'gold-member',
			'rank_id'   => 310,
		];

		$points = [
			'success'        => true,
			'user'           => $user,
			'new_points'     => 50,
			'total_points'   => 1250,
			'points_type'    => 'credits',
			'admin_id'       => 0,
			'achievement_id' => 204,
		];

		$achievement = [
			'success'          => true,
			'user'             => $user,
			'achievement_type' => 'badge',
			'achievement'      => 'first-purchase',
			'achievement_id'   => 204,
		];

		$samples = [
			'user_earns_rank'   => $rank,
			'user_earns_points' => $points,

			'user_earns_specific_achievement_type' => $achievement,
			'user_gains_achievement'               => $achievement,

			'user_achievement_revoked' => [
				'success'        => true,
				'user'           => $user,
				'post_id'        => 204,
				'post_title'     => 'First Purchase',
				'post_type'      => 'badge',
				'post_author_id' => 1,
				'post_content'   => 'Awarded for completing your first purchase.',
				'post_parent_id' => 0,
			],
		];

		if ( isset( $samples[ $event ] ) ) {
			return $samples[ $event ];
		}

		if ( 0 === strpos( $event, 'user_earns_rank' ) ) {
			return $rank;
		}

		if ( 0 === strpos( $event, 'user_earns_points' ) ) {
			return $points;
		}

		if ( false !== strpos( $event, 'achievement' ) ) {
			return $achievement;
		}

		return [
			'success' => true,
			'user'    => $user,
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'rank_type_query'        => [ self::class, 'query_rank_type' ],
			'rank_query'             => [ self::class, 'query_rank' ],
			'achievement_type_query' => [ self::class, 'query_achievement_type' ],
			'achievement_query'      => [ self::class, 'query_achievement' ],
		];
	}

	private static function get_value( $query, $key ) {
		foreach ( [ 'where', 'values', 'data' ] as $scope ) {
			if ( isset( $query[ $scope ][ $key ] ) ) {
				return $query[ $scope ][ $key ];
			}
		}

		return $query[ $key ] ?? '';
	}

	public static function query_rank_type( $query ): array {
		global $wpdb;

		$all_rank_type = [
			[
				'value' => 'any',
				'label' => 'Any Rank Type'
			],
		];

		$rank_types = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_name, post_title, post_type FROM {$wpdb->posts} where post_type like %s AND post_status = %s",
				[ 'rank_type', 'publish' ]
			)
		);

		foreach ( $rank_types as $rank_type ) {
			$all_rank_type[] = [
				'value' => $rank_type->post_name,
				'label' => $rank_type->post_title,
			];
		}

		return $all_rank_type;
	}

	public static function query_rank( $query ): array {
		global $wpdb;

		$rank_type = sanitize_text_field( self::get_value( $query, 'rank_type' ) );
		$all_rank  = [];

		if ( empty( $rank_type ) || 'any' === $rank_type ) {
			$all_rank_types = $wpdb->get_results(
				"SELECT post_name FROM {$wpdb->posts} WHERE post_type = 'rank_type' AND post_status = 'publish'"
			);

			foreach ( $all_rank_types as $all_rank_type ) {
				$ranks = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT post_name, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY post_title ASC",
						$all_rank_type->post_name
					)
				);
				foreach ( $ranks as $rank ) {
					$all_rank[] = [
						'value' => $rank->post_name,
						'label' => $rank->post_title,
					];
				}
			}

			return $all_rank;
		}//end if

		$ranks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_name, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY post_title ASC",
				$rank_type
			)
		);

		foreach ( $ranks as $rank ) {
			$all_rank[] = [
				'value' => $rank->post_name,
				'label' => $rank->post_title,
			];
		}

		return $all_rank;
	}

	public static function query_achievement_type( $query ): array {
		global $wpdb;

		$all_achievement_type = [
			[
				'value' => 'any',
				'label' => 'Any Achievement Type'
			],
		];

		$achievement_types = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_name, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s ORDER BY post_title ASC",
				[ 'achievement-type', 'publish' ]
			)
		);

		foreach ( $achievement_types as $achievement_type ) {
			$all_achievement_type[] = [
				'value' => $achievement_type->post_name,
				'label' => $achievement_type->post_title,
			];
		}

		return $all_achievement_type;
	}

	public static function query_achievement( $query ): array {
		global $wpdb;

		$achievement_type = sanitize_text_field( self::get_value( $query, 'achievement_type' ) );
		$all_achievement  = [];

		if ( empty( $achievement_type ) || 'any' === $achievement_type ) {
			$all_types = $wpdb->get_results(
				"SELECT post_name FROM {$wpdb->posts} WHERE post_type = 'achievement-type' AND post_status = 'publish'"
			);

			foreach ( $all_types as $type ) {
				$achievements = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY post_title ASC",
						$type->post_name
					)
				);

				foreach ( $achievements as $achievement ) {
					$all_achievement[] = [
						'value' => (string) $achievement->ID,
						'label' => $achievement->post_title,
					];
				}
			}

			return $all_achievement;
		}//end if

		$achievements = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY post_title ASC",
				$achievement_type
			)
		);

		foreach ( $achievements as $achievement ) {
			$all_achievement[] = [
				'value' => (string) $achievement->ID,
				'label' => $achievement->post_title,
			];
		}

		return $all_achievement;
	}

	public static function resolve_user_payload( $user_id ) {
		$user = get_userdata( (int) $user_id );

		if ( ! $user ) {
			return false;
		}

		return [
			'user_id'      => $user->ID,
			'first_name'   => $user->first_name ?? '',
			'last_name'    => $user->last_name ?? '',
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'nickname'     => $user->nickname,
			'display_name' => $user->display_name,
			'avatar_url'   => get_avatar_url( $user_id ),
			'user_roles'   => $user->roles,
			'completed_at' => current_time( 'mysql' ),
		];
	}
}
