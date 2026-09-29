<?php

namespace Zaplane\Integrations\Gameengine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ActionsTrait {

    private static function action_award_points( array $config, array $input ): array {
		$user_id = self::user_from_config( $config );
		$points  = (int) ( $config['points'] ?? 0 );

		if ( $user_id <= 0 || $points <= 0 ) {
			return self::action_error( 'A valid user and a positive point amount are required.' );
		}

		$context = sanitize_key( (string) ( $config['context'] ?? '' ) );
		$context = '' !== $context ? $context : 'manual_adjustment';
		$log_id  = gameengine_add_points( $user_id, $points, $context, [
			'point_type_id' => self::point_type_id_from_config( $config ),
			'description'   => (string) ( $config['reason'] ?? '' ),
		] );

		if ( ! $log_id ) {
			return self::action_error( 'GameEngine could not award those points.' );
		}

		return self::action_success( [
			'user_id'       => $user_id,
			'points'        => $points,
			'balance'       => self::balance( $user_id, self::point_type_id_from_config( $config ) ),
			'log_id'        => (int) $log_id,
			'point_type_id' => self::point_type_id_from_config( $config ),
			'context'       => $context,
			'description'   => (string) ( $config['reason'] ?? '' ),
		] );
	}

	private static function action_deduct_points( array $config, array $input ): array {
		$user_id = self::user_from_config( $config );
		$points  = (int) ( $config['points'] ?? 0 );

		if ( $user_id <= 0 || $points <= 0 ) {
			return self::action_error( 'A valid user and a positive point amount are required.' );
		}

		$context = sanitize_key( (string) ( $config['context'] ?? '' ) );
		$context = '' !== $context ? $context : 'manual_adjustment';
		$log_id  = gameengine_deduct_points( $user_id, $points, $context, [
			'point_type_id' => self::point_type_id_from_config( $config ),
			'description'   => (string) ( $config['reason'] ?? '' ),
		] );

		if ( ! $log_id ) {
			return self::action_error( 'GameEngine could not deduct those points.' );
		}

		return self::action_success( [
			'user_id'       => $user_id,
			'points'        => $points,
			'balance'       => self::balance( $user_id, self::point_type_id_from_config( $config ) ),
			'log_id'        => (int) $log_id,
			'point_type_id' => self::point_type_id_from_config( $config ),
			'context'       => $context,
			'description'   => (string) ( $config['reason'] ?? '' ),
		] );
	}

	private static function action_set_balance( array $config, array $input ): array {
		$user_id      = self::user_from_config( $config );
		$target       = (int) ( $config['points'] ?? 0 );
		$point_type_id = self::point_type_id_from_config( $config );

		if ( $user_id <= 0 || $target < 0 ) {
			return self::action_error( 'A valid user and a balance are required.' );
		}

		$previous = self::balance( $user_id, $point_type_id );
		$delta    = $target - $previous;

		if ( $delta > 0 ) {
			$result = gameengine_add_points( $user_id, $delta, 'manual_adjustment', [
				'point_type_id' => $point_type_id,
				'description'   => 'Balance set by Zaplane',
			] );
		} elseif ( $delta < 0 ) {
			$result = gameengine_deduct_points( $user_id, abs( $delta ), 'manual_adjustment', [
				'point_type_id' => $point_type_id,
				'description'   => 'Balance set by Zaplane',
			] );
		} else {
			$result = 1;
		}

		if ( ! $result ) {
			return self::action_error( 'GameEngine could not adjust the balance.' );
		}

		return self::action_success( [
			'user_id'       => $user_id,
			'previous'      => $previous,
			'adjusted'      => $delta,
			'balance'       => self::balance( $user_id, $point_type_id ),
			'point_type_id' => $point_type_id,
		] );
	}

	private static function action_get_balance( array $config, array $input ): array {
		$user_id = self::user_from_config( $config );
		if ( $user_id <= 0 ) {
			return self::action_error( 'A valid user is required.' );
		}

		$point_type_id = self::point_type_id_from_config( $config );

		return self::action_success( [
			'user_id'       => $user_id,
			'balance'       => self::balance( $user_id, $point_type_id ),
			'point_type_id' => $point_type_id,
		] );
	}

	private static function action_get_transactions( array $config, array $input ): array {
		$user_id = self::user_from_config( $config );
		if ( $user_id <= 0 ) {
			return self::action_error( 'A valid user is required.' );
		}

		$limit = max( 1, min( 100, (int) ( $config['limit'] ?? 10 ) ) );
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, points, point_type_id, context, description, created_at
				 FROM {$wpdb->prefix}gameengine_points_log
				 WHERE user_id = %d
				 ORDER BY id DESC
				 LIMIT %d",
				$user_id,
				$limit
			),
			ARRAY_A
		);

		$transactions = [];
		foreach ( (array) $rows as $row ) {
			$row    = (array) $row;
			$transactions[] = [
				'log_id'      => (int) ( $row['id'] ?? 0 ),
				'points'      => (int) ( $row['points'] ?? 0 ),
				'point_type_id' => (int) ( $row['point_type_id'] ?? 0 ),
				'context'     => (string) ( $row['context'] ?? '' ),
				'description' => (string) ( $row['description'] ?? '' ),
				'created_at'  => (string) ( $row['created_at'] ?? '' ),
			];
		}

		return self::action_success( [
			'user_id'      => $user_id,
			'transactions' => $transactions,
		] );
	}

	private static function action_award_achievement( array $config, array $input ): array {
		$user_id        = self::user_from_config( $config );
		$achievement_id = (int) ( $config['achievement_id'] ?? 0 );

		if ( $user_id <= 0 || $achievement_id <= 0 ) {
			return self::action_error( 'A valid user and achievement are required.' );
		}

		$manager = new \GameEngine\Classes\AchievementsManager();
		$result  = $manager->award( $user_id, $achievement_id, 'zaplane' );

		if ( ! $result && $manager->has_achievement( $user_id, $achievement_id ) ) {
			return self::action_success( [
				'user_id'        => $user_id,
				'achievement_id' => $achievement_id,
				'achievement'    => self::achievement_title( $achievement_id ),
				'already_held'   => true,
			] );
		}

		if ( ! $result ) {
			return self::action_error( 'GameEngine could not award that achievement.' );
		}

		return self::action_success( [
			'user_id'        => $user_id,
			'achievement_id' => $achievement_id,
			'achievement'    => self::achievement_title( $achievement_id ),
			'already_held'   => false,
		] );
	}

	private static function action_revoke_achievement( array $config, array $input ): array {
		$user_id        = self::user_from_config( $config );
		$achievement_id = (int) ( $config['achievement_id'] ?? 0 );

		if ( $user_id <= 0 || $achievement_id <= 0 ) {
			return self::action_error( 'A valid user and achievement are required.' );
		}

		$manager = new \GameEngine\Classes\AchievementsManager();
		$result  = $manager->revoke( $user_id, $achievement_id );

		if ( ! $result ) {
			return self::action_error( 'That achievement is not held by this user.' );
		}

		return self::action_success( [
			'user_id'        => $user_id,
			'achievement_id' => $achievement_id,
		] );
	}

	private static function action_get_achievements( array $config, array $input ): array {
		$user_id = self::user_from_config( $config );
		if ( $user_id <= 0 ) {
			return self::action_error( 'A valid user is required.' );
		}

		$manager      = new \GameEngine\Classes\AchievementsManager();
		$achievements = [];
		foreach ( (array) $manager->get_user_achievements( $user_id ) as $row ) {
			$row = (array) $row;
			$achievements[] = [
				'achievement_id' => (int) ( $row['achievement_id'] ?? 0 ),
				'title'          => (string) ( $row['title'] ?? '' ),
				'achieved_at'    => (string) ( $row['achieved_at'] ?? '' ),
			];
		}

		return self::action_success( [
			'user_id'       => $user_id,
			'achievements'  => $achievements,
		] );
	}

	/**
	 * Assign a level — shared by assign_level, change_level and assign_rank
	 * (GameEngine keeps ranks as levels).
	 */
	private static function action_assign_level( array $config, array $input ): array {
		$user_id  = self::user_from_config( $config );
		$level_id = (int) ( $config['level_id'] ?? ( $config['rank_id'] ?? 0 ) );

		if ( $user_id <= 0 || $level_id <= 0 ) {
			return self::action_error( 'A valid user and level are required.' );
		}

		$manager = new \GameEngine\Classes\LevelsManager();
		$result  = $manager->award( $user_id, $level_id, 'zaplane' );

		if ( ! $result && $manager->has_level( $user_id, $level_id ) ) {
			return self::action_success( [
				'user_id'       => $user_id,
				'level_id'      => $level_id,
				'rank_id'       => $level_id,
				'level'         => self::level_title( $level_id ),
				'rank'          => self::level_title( $level_id ),
				'already_held'  => true,
			] );
		}

		if ( ! $result ) {
			return self::action_error( 'GameEngine could not award that level.' );
		}

		return self::action_success( [
			'user_id'       => $user_id,
			'level_id'      => $level_id,
			'rank_id'       => $level_id,
			'level'         => self::level_title( $level_id ),
			'rank'          => self::level_title( $level_id ),
			'user_level_id' => (int) $result,
			'already_held'  => false,
		] );
	}

	/** Moving a user to another level is the same grant GameEngine makes. */
	private static function action_change_level( array $config, array $input ): array {
		return self::action_assign_level( $config, $input );
	}

	/** Ranks are levels in GameEngine, so assigning one reuses the grant. */
	private static function action_assign_rank( array $config, array $input ): array {
		return self::action_assign_level( $config, $input );
	}

	/** The current rank is the current level. */
	private static function action_get_rank( array $config, array $input ): array {
		return self::action_get_level( $config, $input );
	}

	private static function action_get_level( array $config, array $input ): array {
		$user_id = self::user_from_config( $config );
		if ( $user_id <= 0 ) {
			return self::action_error( 'A valid user is required.' );
		}

		$manager = new \GameEngine\Classes\LevelsManager();
		$level   = $manager->get_current_level( $user_id );
		$level_id = isset( $level->id ) ? (int) $level->id : 0;

		return self::action_success( [
			'user_id'      => $user_id,
			'level_id'     => $level_id,
			'rank_id'      => $level_id,
			'level'        => $level_id ? (string) $level->title : '',
			'rank'         => $level_id ? (string) $level->title : '',
			'level_number' => $level_id ? self::level_number( $level_id ) : 0,
			'user_level_id' => 0,
		] );
	}

	private static function action_unlock_content( array $config, array $input ): array {
		$post_id = (int) ( $config['post_id'] ?? 0 );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return self::action_error( 'A valid post or page is required.' );
		}

		update_post_meta( $post_id, '_gameengine_restrict_type', 'none' );
		delete_post_meta( $post_id, '_gameengine_restrict_value' );

		return self::action_success( [
			'post_id'       => $post_id,
			'restrict_type' => 'none',
		] );
	}

	private static function action_lock_content( array $config, array $input ): array {
		$post_id = (int) ( $config['post_id'] ?? 0 );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return self::action_error( 'A valid post or page is required.' );
		}

		$types = [ 'points', 'achievement', 'level' ];
		$type  = (string) ( $config['restrict_type'] ?? 'points' );
		if ( ! in_array( $type, $types, true ) ) {
			$type = 'points';
		}

		$value          = (string) ( $config['restrict_value'] ?? '' );
		$message        = trim( (string) ( $config['message'] ?? '' ) );
		$has_message    = '' !== $message;

		update_post_meta( $post_id, '_gameengine_restrict_type', $type );
		update_post_meta( $post_id, '_gameengine_restrict_value', $value );
		if ( $has_message ) {
			update_post_meta( $post_id, '_gameengine_restrict_message', $message );
		}

		return self::action_success( [
			'post_id'        => $post_id,
			'restrict_type'  => $type,
			'restrict_value' => $value,
			'has_message'    => $has_message,
		] );
	}

	private static function action_generate_coupon( array $config, array $input ): array {
		$user_id      = self::user_from_config( $config );
		$amount       = (float) ( $config['amount'] ?? 0 );
		$discount_type = sanitize_key( (string) ( $config['discount_type'] ?? 'percent' ) );
		$points_cost  = max( 0, (int) ( $config['points_cost'] ?? 0 ) );
		$expiry_days  = max( 1, (int) ( $config['expiry_days'] ?? 30 ) );

		if ( $user_id <= 0 || $amount <= 0 ) {
			return self::action_error( 'A valid user and discount amount are required.' );
		}

		$code = \GameEngine\Pro\Addons\Rewards_Marketplace::generate_coupon(
			$user_id,
			$amount,
			$discount_type,
			$points_cost,
			$expiry_days
		);

		if ( is_wp_error( $code ) ) {
			return self::action_error( $code->get_error_message() );
		}

		return self::action_success( [
			'code'          => (string) $code,
			'discount_type' => $discount_type,
			'amount'        => $amount,
			'points_cost'   => $points_cost,
			'user_id'       => $user_id,
			'balance'       => self::balance( $user_id ),
		] );
	}

	private static function action_redeem_reward( array $config, array $input ): array {
		$user_id   = self::user_from_config( $config );
		$reward_id = (int) ( $config['reward_id'] ?? 0 );

		if ( $user_id <= 0 || $reward_id <= 0 ) {
			return self::action_error( 'A valid user and reward are required.' );
		}

		$manager = new \GameEngine\Addons\RewardsStore\Rewards_Manager();
		$result  = $manager->redeem( $user_id, $reward_id );

		if ( empty( $result['success'] ) ) {
			return self::action_error( (string) ( $result['message'] ?? 'GameEngine could not redeem that reward.' ) );
		}

		return self::action_success( [
			'user_id'          => $user_id,
			'reward_id'        => $reward_id,
			'message'          => (string) ( $result['message'] ?? '' ),
			'remaining_points' => (int) ( $result['remaining_points'] ?? self::balance( $user_id ) ),
			'remaining_stock'  => (int) ( $result['remaining_stock'] ?? 0 ),
		] );
	}

	/**
	 * The same two-leg move the Pro transfer controller makes: take from the
	 * sender, give to the recipient, refund the sender if the second leg fails,
	 * then record the transfer for the daily-limit tables.
	 */
	private static function action_transfer_points( array $config, array $input ): array {
		$sender_id      = (int) ( $config['sender_id'] ?? 0 );
		$receiver_id    = (int) ( $config['receiver_id'] ?? 0 );
		$points         = (int) ( $config['points'] ?? 0 );
		$point_type_id  = self::point_type_id_from_config( $config );
		$message        = (string) ( $config['message'] ?? '' );

		if ( $sender_id <= 0 ) {
			$sender_id = get_current_user_id();
		}

		if ( $sender_id <= 0 || $receiver_id <= 0 || $points <= 0 ) {
			return self::action_error( 'A sender, a recipient and a positive point amount are required.' );
		}

		if ( $sender_id === $receiver_id ) {
			return self::action_error( 'The sender and the recipient must be different users.' );
		}

		if ( ! get_userdata( $receiver_id ) ) {
			return self::action_error( 'The recipient user was not found.' );
		}

		if ( self::balance( $sender_id, $point_type_id ) < $points ) {
			return self::action_error( 'The sender does not have enough points.' );
		}

		$sent = gameengine_deduct_points( $sender_id, $points, 'transfer_sent', [
			'point_type_id' => $point_type_id,
			'description'   => sprintf( 'Transferred %d points to user #%d', $points, $receiver_id ),
		] );

		if ( ! $sent ) {
			return self::action_error( 'GameEngine could not take the points from the sender.' );
		}

		$received = gameengine_add_points( $receiver_id, $points, 'transfer_received', [
			'point_type_id' => $point_type_id,
			'description'   => sprintf( 'Received %d points from user #%d', $points, $sender_id ),
		] );

		if ( ! $received ) {
			gameengine_add_points( $sender_id, $points, 'transfer_refunded', [
				'point_type_id' => $point_type_id,
				'description'   => sprintf( 'Refund: transfer to user #%d could not be completed', $receiver_id ),
			] );

			return self::action_error( 'GameEngine could not credit the recipient; the points were returned.' );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$wpdb->prefix . 'gameengine_pro_transfers',
			[
				'from_user_id'  => $sender_id,
				'to_user_id'    => $receiver_id,
				'point_type_id' => $point_type_id,
				'points'        => $points,
				'message'       => $message,
				'created_at'    => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%d', '%d', '%s', '%s' ]
		);

		return self::action_success( [
			'sender_id'        => $sender_id,
			'receiver_id'      => $receiver_id,
			'points'           => $points,
			'point_type_id'    => $point_type_id,
			'sender_balance'   => self::balance( $sender_id, $point_type_id ),
			'receiver_balance' => self::balance( $receiver_id, $point_type_id ),
			'transfer_row'     => (int) $wpdb->insert_id,
		] );
	}

	private static function action_create_payout( array $config, array $input ): array {
		$user_id = self::user_from_config( $config );
		$points  = (int) ( $config['points'] ?? 0 );
		$method  = sanitize_key( (string) ( $config['method'] ?? 'paypal' ) );
		$details = (string) ( $config['account'] ?? '' );

		if ( $user_id <= 0 || $points <= 0 || '' === $method ) {
			return self::action_error( 'A valid user, points and a payout method are required.' );
		}

		$payout_id = \GameEngine\Pro\Addons\Points_Payouts::create_request( $user_id, $points, $method, $details );

		if ( is_wp_error( $payout_id ) ) {
			return self::action_error( $payout_id->get_error_message() );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT points, amount FROM {$wpdb->prefix}gameengine_pro_payouts WHERE id = %d", (int) $payout_id ),
			ARRAY_A
		);
		$row   = (array) $row;

		return self::action_success( [
			'payout_id' => (int) $payout_id,
			'user_id'   => $user_id,
			'points'    => $points,
			'amount'    => (float) ( $row['amount'] ?? 0 ),
			'method'    => $method,
			'status'    => 'pending',
		] );
	}

	/**
	 * The same rules the Pro payout REST endpoint enforces: only pending or
	 * approved requests move, and rejecting refunds the deducted points.
	 */
	private static function action_update_payout( array $config, array $input ): array {
		$payout_id = (int) ( $config['payout_id'] ?? 0 );
		$status    = sanitize_key( (string) ( $config['status'] ?? '' ) );

		if ( $payout_id <= 0 ) {
			return self::action_error( 'A payout ID is required.' );
		}

		$allowed = [ 'pending', 'approved', 'completed', 'rejected' ];
		if ( ! in_array( $status, $allowed, true ) ) {
			return self::action_error( 'Invalid payout status.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'gameengine_pro_payouts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$payout = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $payout_id ),
			ARRAY_A
		);

		if ( ! $payout ) {
			return self::action_error( 'Payout request not found.' );
		}

		$previous = (string) ( $payout['status'] ?? '' );
		if ( ! in_array( $previous, [ 'pending', 'approved' ], true ) && $previous !== $status ) {
			return self::action_error( 'This payout has already been finalized.' );
		}

		$refunded = false;
		if ( 'rejected' === $status && 'rejected' !== $previous && class_exists( '\GameEngine\Pro\Pro_Helper' ) ) {
			\GameEngine\Pro\Pro_Helper::refund_points(
				(int) ( $payout['user_id'] ?? 0 ),
				(int) ( $payout['points'] ?? 0 ),
				'payout_refund',
				'Payout rejected.'
			);
			$refunded = true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, [ 'status' => $status ], [ 'id' => $payout_id ] );

		return self::action_success( [
			'payout_id'       => $payout_id,
			'previous_status' => $previous,
			'status'          => $status,
			'user_id'         => (int) ( $payout['user_id'] ?? 0 ),
			'points'          => (int) ( $payout['points'] ?? 0 ),
			'refunded'        => $refunded,
		] );
	}

	private static function action_approve_payout( array $config, array $input ): array {
		$config['status'] = 'approved';

		return self::action_update_payout( $config, $input );
	}
}
