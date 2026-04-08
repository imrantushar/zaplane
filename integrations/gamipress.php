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
				'hook'  => 'gamipress_update_user_rank'
			],
            'user_earns_points' => [
				'label' => 'User Earns Points',
				'hook'  => 'gamipress_update_user_points'
			],
			'user_earns_specific_achievement_type' => [
				'label' => 'User Earns Specific Achievement Type',
				'hook'  => 'gamipress_award_achievement'
			],
            'user_gains_achievement' => [
				'label' => 'User Gains Achievement',
				'hook'  => 'gamipress_award_achievement'
			],
			'user_achievement_revoked' => [
				'label' => 'User Achievement Was Revoked.',
				'hook'  => 'gamipress_revoke_achievement_to_user'
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
        $achievement_type = [
            'key'   => 'achievement_type',
            'label' => 'Achievement Type',
            'type'  => 'select',
            'dynamic' => [
                'integration' => 'gamipress',
                'query'       => 'achievement_type_query',
                'select'      => [ 'value', 'label' ],
            ],
            'required' => true
        ];

		if ( 'user_earns_rank' === $trigger ) {
			return [
				[
					'key'   => 'rank_type',
					'label' => 'Rank Type',
					'type'  => 'select',
					'dynamic' => [
						'integration' => 'gamipress',
						'query'       => 'rank_type_query',
						'select'      => [ 'value', 'label' ],
					],
					'required' => true
				],
				[
					'key'   => 'rank_id',
					'label' => 'Rank',
					'type'  => 'select',
					'dynamic' => [
						'integration' => 'gamipress',
						'query'       => 'rank_query',
						'select'      => [ 'value', 'label' ],
                        'depends_on'  => ['rank_type'],
					],
					'required' => true
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
					'key'   => 'achievement_id',
					'label' => 'Achievement',
					'type'  => 'select',
					'dynamic' => [
						'integration' => 'gamipress',
						'query'       => 'achievement_query',
						'select'      => [ 'value', 'label' ],
                        'depends_on'  => ['achievement_type'],
					],
					'required' => true
				],
			];
		}//end if

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'user_earns_rank':
			case 'user_earns_points':
			case 'user_earns_specific_achievement_type':
			case 'user_gains_achievement':
			case 'user_achievement_revoked':

		}//end switch
		return false;
	}

	public static function get_actions(): array {
		return [
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [

		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];
		$event  = $node['data']['event'] ?? '';

		switch ( $event ) {


		}//end switch

		return [
			'port' => 'main',
			'data' => $input
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

	public static function query_rank_type( $query ) {
		$all_rank_type = [
			[
				'value' => 'any',
				'label' => 'Any Rank Type'
			],
		];

		global $wpdb;

        $rank_types = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_name, post_title, post_type FROM {$wpdb->posts} where post_type like %s AND post_status = %s",
                ['rank_type', 'publish']
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

	public static function query_rank( $query ) {

        global $wpdb;

        $rank_type = isset($query['rank_type']) 
            ? sanitize_text_field($query['rank_type']) 
            : '';

        $all_rank = [
            [
                'value' => 'any',
                'label' => 'Any Rank'
            ],
        ];

        $ranks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_name, post_title, post_type FROM {$wpdb->posts} where post_type like %s AND post_status = %s",
                [$rank_type, 'publish']
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

    public static function query_achievement_type( $query ) {
		$all_achievement_type = [
			[
				'value' => 'any',
				'label' => 'Any Achievement Type'
			],
		];

		global $wpdb;

        $achievement_types = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_name, post_title, post_type FROM {$wpdb->posts} WHERE post_type LIKE %s AND post_status = %s ORDER BY post_title ASC",
                ['achievement-type', 'publish']
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

    public static function query_achievement( $query ) {
		global $wpdb;

        $achievement_type = isset($query['achievement_type']) 
            ? sanitize_text_field($query['achievement_type']) 
            : '';

        $all_achievement = [
            [
                'value' => 'any',
                'label' => 'Any Achievement'
            ],
        ];

        $achievements = $wpdb->get_results(
            $wpdb->prepare("SELECT ID, post_name, post_title, post_type FROM {$wpdb->posts} where post_type like %s AND post_status = %s", [$achievement_type, 'publish'])
        );

		foreach ( $achievements as $achievement ) {
			$all_achievement[] = [
				'value' => $achievement->post_name,
				'label' => $achievement->ID,
			];
		}

		return $all_achievement;
	}
}
