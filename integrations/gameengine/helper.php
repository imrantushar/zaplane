<?php

namespace Zaplane\Integrations\Gameengine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Helper {

	private static function resolve_points_event( string $event, array $config, array $args ) {
		$user_id       = (int) ( $args[0] ?? 0 );
		$points        = (int) ( $args[1] ?? 0 );
		$context       = (string) ( $args[2] ?? '' );
		$log_id        = (int) ( $args[3] ?? 0 );
		$point_type_id = (int) ( $args[4] ?? 0 );

		if ( $user_id <= 0 || $points <= 0 ) {
			return false;
		}

		$log       = self::points_log( $log_id );
		$direction = 'increase';

		if ( isset( $log['points'] ) ) {
			$direction = ( (int) $log['points'] < 0 ) ? 'decrease' : 'increase';
		} elseif ( false !== strpos( current_filter(), 'deducted' ) ) {
			$direction = 'decrease';
		}

		switch ( $event ) {
			case 'points_used_at_checkout':
				$checkout_contexts = [ 'wc_points_payment', 'wc_partial_payment', 'se_points_payment', 'se_partial_payment' ];
				if ( ! in_array( $context, $checkout_contexts, true ) ) {
					return false;
				}
				break;

			case 'points_transferred':
				if ( ! in_array( $context, [ 'transfer_sent', 'transfer_received' ], true ) ) {
					return false;
				}
				$direction = ( 'transfer_sent' === $context ) ? 'sent' : 'received';

				$wanted = (string) ( $config['direction'] ?? 'any' );
				if ( 'any' !== $wanted && $wanted !== $direction ) {
					return false;
				}
				break;

			case 'points_expired':
				if ( 'expired' !== $context ) {
					return false;
				}
				break;

			case 'payout_requested':
				if ( 'payout_request' !== $context ) {
					return false;
				}
				$min = (int) ( $config['min_points'] ?? 0 );
				if ( $min > 0 && $points < $min ) {
					return false;
				}
				break;

			case 'payout_rejected':
				if ( 'payout_refund' !== $context ) {
					return false;
				}
				break;

			case 'affiliate_reward':
				if ( 'referral_signup' !== $context ) {
					return false;
				}
				break;
		}//end switch

		// Whatever context the trigger gated on above, every points-family
		// event accepts the same point type and minimum amount filters.
		$wanted_types = array_filter( array_map( 'absint', explode( ',', (string) ( $config['point_type'] ?? '' ) ) ) );
		if ( $wanted_types && ! in_array( $point_type_id, $wanted_types, true ) ) {
			return false;
		}

		$min = (int) ( $config['min_points'] ?? 0 );
		if ( $min > 0 && $points < $min ) {
			return false;
		}

		$user = self::user_payload( $user_id );
		if ( ! $user ) {
			return false;
		}

		$payload = [
			'success'        => true,
			'user'           => $user,
			'points'         => $points,
			'balance'        => self::balance( $user_id, $point_type_id ),
			'context'        => $context,
			'direction'      => $direction,
			'point_type_id'  => $point_type_id,
			'point_type'     => self::point_type_slug( $point_type_id ),
			'log_id'         => $log_id,
			'description'    => (string) ( $log['description'] ?? '' ),
			'created_at'     => (string) ( $log['created_at'] ?? current_time( 'mysql' ) ),
		];

		if ( 'points_transferred' === $event ) {
			$payload['other_user_id'] = self::transfer_counterparty( $log );
		}

		if ( 'payout_requested' === $event ) {
			$payload['method'] = self::payout_method_from_description( (string) ( $log['description'] ?? '' ) );
		}

		return $payload;
	}

	private static function respond( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	private static function action_error( string $message ): array {
		return self::respond( [
			'success' => false,
			'error'   => $message,
		] );
	}

	private static function user_from_config( array $config ): int {
		$user_id = (int) ( $config['user_id'] ?? 0 );

		return $user_id > 0 ? $user_id : get_current_user_id();
	}

	private static function user_payload( int $user_id ): ?array {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return null;
		}

		return [
			'user_id'      => (int) $user->ID,
			'user_login'   => (string) ( $user->user_login ?? '' ),
			'user_email'   => (string) ( $user->user_email ?? '' ),
			'display_name' => (string) ( $user->display_name ?? '' ),
			'roles'        => (array) ( $user->roles ?? [] ),
			'avatar_url'   => function_exists( 'get_avatar_url' ) ? (string) get_avatar_url( $user_id ) : '',
		];
	}

	private static function points_log( int $log_id ): array {
		if ( $log_id <= 0 ) {
			return [];
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gameengine_points_log WHERE id = %d", $log_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : [];
	}

	private static function balance( int $user_id, int $point_type_id = 0 ): int {
		if ( $user_id <= 0 || ! class_exists( '\GameEngine\Classes\PointsManager' ) ) {
			return 0;
		}

		$manager = new \GameEngine\Classes\PointsManager();

		if ( $point_type_id > 0 ) {
			return (int) $manager->get_total( $user_id, $point_type_id );
		}

		return (int) $manager->get_grand_total( $user_id );
	}

	private static function point_type_id_from_config( array $config ): int {
		$preferred = (int) ( $config['point_type'] ?? 0 );

		if ( ! class_exists( '\GameEngine\Classes\PointsManager' ) ) {
			return $preferred;
		}

		return (int) \GameEngine\Classes\PointsManager::resolve_point_type_id( $preferred );
	}

	private static function point_type_slug( int $point_type_id ): string {
		if ( $point_type_id <= 0 ) {
			return '';
		}

		foreach ( self::point_types() as $row ) {
			if ( (int) ( $row['id'] ?? 0 ) === $point_type_id ) {
				return (string) ( $row['slug'] ?? '' );
			}
		}

		return '';
	}

	private static function point_types(): array {
		if ( ! class_exists( '\GameEngine\Classes\PointsManager' ) ) {
			return [];
		}

		return array_map( 'array_filter', (array) \GameEngine\Classes\PointsManager::get_point_types() );
	}

	private static function transfer_counterparty( array $log ): int {
		$description = (string) ( $log['description'] ?? '' );
		if ( preg_match( '/user #(\d+)/', $description, $matches ) ) {
			return (int) $matches[1];
		}

		return 0;
	}

	private static function payout_method_from_description( string $description ): string {
		if ( preg_match( '/\bvia (.+?)\.?$/', $description, $matches ) ) {
			return trim( $matches[1] );
		}

		return '';
	}

	private static function achievement_title( int $achievement_id ): string {
		return self::table_title( 'gameengine_achievements', $achievement_id );
	}

	private static function level_title( int $level_id ): string {
		return self::table_title( 'gameengine_levels', $level_id );
	}

	private static function reward_title( int $reward_id ): string {
		return self::table_title( 'gameengine_rewards', $reward_id );
	}

	private static function table_title( string $table, int $id ): string {
		if ( $id <= 0 ) {
			return '';
		}

		global $wpdb;
		if ( ! isset( $wpdb->prefix ) ) {
			return '';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$title = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one of three fixed plugin tables, no user input.
			$wpdb->prepare( "SELECT title FROM {$wpdb->prefix}{$table} WHERE id = %d", $id )
		);

		return $title ? (string) $title : '';
	}

	private static function level_number( int $level_id ): int {
		if ( $level_id <= 0 ) {
			return 0;
		}

		global $wpdb;
		if ( ! isset( $wpdb->prefix ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$levels = $wpdb->get_results(
			"SELECT id FROM {$wpdb->prefix}gameengine_levels WHERE status = 'publish' ORDER BY priority ASC, min_points ASC, id ASC",
			ARRAY_A
		);

		$number = 0;
		foreach ( (array) $levels as $level ) {
			++$number;
			if ( (int) ( ( (array) $level )['id'] ?? 0 ) === $level_id ) {
				return $number;
			}
		}

		return 0;
	}
}
