<?php

namespace Zaplane\Integrations\Quizpress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait QpQuizActionsTrait {

	protected static function qp_execute_quiz_action( string $event, array $config ): ?array {
		switch ( $event ) {
			case 'create_quiz':
				return static::qp_action_create_quiz( $config );

			case 'update_quiz':
				return static::qp_action_update_quiz( $config );

			case 'delete_quiz':
				return static::qp_action_delete_quiz( $config );

			case 'publish_quiz':
				return static::qp_action_change_quiz_status( $config, 'publish' );

			case 'draft_quiz':
				return static::qp_action_change_quiz_status( $config, 'draft' );

			case 'reset_quiz_attempt':
				return static::qp_action_reset_attempt( $config );

			case 'set_quiz_score':
				return static::qp_action_set_score( $config );

			case 'set_quiz_result':
				return static::qp_action_set_result( $config );

			case 'get_quiz_analytics':
				return static::qp_action_get_analytics( $config );
		}//end switch

		return null;
	}

	private static function qp_action_create_quiz( array $config ): array {
		$title = (string) ( $config['quiz_title'] ?? '' );

		if ( '' === trim( $title ) ) {
			return static::qp_action_error( 'A quiz title is required.' );
		}

		$postarr = [
			'post_type'    => 'quizpress_quiz',
			'post_title'   => $title,
			'post_content' => (string) ( $config['quiz_description'] ?? '' ),
			'post_status'  => sanitize_key( (string) ( $config['quiz_status'] ?? 'draft' ) ),
		];

		if ( ! post_type_exists( 'quizpress_quiz' ) ) {
			return static::qp_action_error( 'The quizpress_quiz post type is not registered — is QuizPress fully loaded?' );
		}

		$quiz_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $quiz_id ) ) {
			return static::qp_action_error( $quiz_id->get_error_message() );
		}

		if ( ! $quiz_id ) {
			return static::qp_action_error( 'The quiz could not be created.' );
		}

		static::qp_save_quiz_meta( (int) $quiz_id, $config );

		// Build the payload from what we asked for, not from a re-read: a
		// just-inserted post may not be readable back within the same run.
		return static::qp_success(
			array_merge(
				static::qp_quiz_payload( (int) $quiz_id ),
				[
					'quiz_id'      => (int) $quiz_id,
					'quiz_title'   => $title,
					'quiz_type'    => sanitize_key( (string) ( $config['quiz_type'] ?? 'quiz' ) ),
					'quiz_status'  => $postarr['post_status'],
					'description'  => $postarr['post_content'],
				]
			)
		);
	}

	private static function qp_action_update_quiz( array $config ): array {
		$quiz_id = absint( $config['quiz_id'] ?? 0 );
		$quiz    = static::qp_get_quiz( $quiz_id );

		if ( ! $quiz ) {
			return static::qp_action_error( 'Quiz not found.' );
		}

		$postarr = [ 'ID' => $quiz_id ];

		$title = (string) ( $config['quiz_title'] ?? '' );
		if ( '' !== trim( $title ) ) {
			$postarr['post_title'] = $title;
		}

		$description = (string) ( $config['quiz_description'] ?? '' );
		if ( '' !== $description ) {
			$postarr['post_content'] = $description;
		}

		$result = wp_update_post( $postarr, true );

		if ( is_wp_error( $result ) ) {
			return static::qp_action_error( $result->get_error_message() );
		}

		static::qp_save_quiz_meta( $quiz_id, $config );

		return static::qp_success(
			array_merge(
				static::qp_quiz_payload( $quiz_id ),
				[ 'updated' => true ]
			)
		);
	}

	private static function qp_action_delete_quiz( array $config ): array {
		$quiz_id = absint( $config['quiz_id'] ?? 0 );

		if ( ! static::qp_get_quiz( $quiz_id ) ) {
			return static::qp_action_error( 'Quiz not found.' );
		}

		$force = 'yes' === (string) ( $config['force_delete'] ?? 'no' );

		if ( ! $force ) {
			$deleted = wp_trash_post( $quiz_id );

			if ( ! $deleted ) {
				return static::qp_action_error( 'The quiz could not be moved to trash.' );
			}

			return static::qp_success(
				[
					'quiz_id' => $quiz_id,
					'deleted' => true,
					'forced'  => false,
				]
			);
		}

		// QuizPress keeps attempt data in custom tables keyed by the quiz id —
		// deleting the post alone would orphan it.
		if ( class_exists( '\QuizPress\API\Query\Attempts' ) && method_exists( '\QuizPress\API\Query\Attempts', 'delete_attempts_by_quiz_id' ) ) {
			\QuizPress\API\Query\Attempts::delete_attempts_by_quiz_id( $quiz_id );
		}

		$deleted = wp_delete_post( $quiz_id, true );

		if ( ! $deleted ) {
			return static::qp_action_error( 'The quiz could not be deleted permanently.' );
		}

		return static::qp_success(
			[
				'quiz_id' => $quiz_id,
				'deleted' => true,
				'forced'  => true,
			]
		);
	}

	private static function qp_action_change_quiz_status( array $config, string $status ): array {
		$quiz_id = absint( $config['quiz_id'] ?? 0 );

		if ( ! static::qp_get_quiz( $quiz_id ) ) {
			return static::qp_action_error( 'Quiz not found.' );
		}

		// A scheduled quiz ignores a plain status flip, so clear the schedule
		// meta before publishing — the same intent a manual publish has.
		if ( 'publish' === $status ) {
			update_post_meta( $quiz_id, 'quizpress_quiz_schedule_enabled', false );
		}

		$result = wp_update_post(
			[
				'ID'          => $quiz_id,
				'post_status' => $status,
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			return static::qp_action_error( $result->get_error_message() );
		}

		return static::qp_success(
			array_merge(
				static::qp_quiz_payload( $quiz_id ),
				[ 'quiz_status' => $status ]
			)
		);
	}

	private static function qp_action_reset_attempt( array $config ): array {
		global $wpdb;

		$quiz_id = absint( $config['quiz_id'] ?? 0 );

		if ( ! static::qp_get_quiz( $quiz_id ) ) {
			return static::qp_action_error( 'Quiz not found.' );
		}

		$user_id = absint( $config['user_id'] ?? 0 );

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return static::qp_action_error( 'A user is required to reset an attempt.' );
		}

		$scope = 'last' === (string) ( $config['scope'] ?? 'all' ) ? 'last' : 'all';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
		$attempt_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT attempt_id FROM %i WHERE quiz_id = %d AND user_id = %d ORDER BY attempt_id DESC',
				static::qp_attempts_table(),
				$quiz_id,
				$user_id
			)
		);

		if ( empty( $attempt_ids ) ) {
			return static::qp_action_error( 'No attempts found for this user on this quiz.' );
		}

		if ( 'last' === $scope ) {
			$attempt_ids = [ (int) $attempt_ids[0] ];
		}

		$deleted = 0;

		foreach ( $attempt_ids as $attempt_id ) {
			if ( class_exists( '\QuizPress\API\Query\Attempts' ) && method_exists( '\QuizPress\API\Query\Attempts', 'delete_quiz_attempt' ) ) {
				$ok = \QuizPress\API\Query\Attempts::delete_quiz_attempt( (int) $attempt_id );
			} else {
				$ok = static::qp_delete_attempt_rows( (int) $attempt_id );
			}

			if ( $ok ) {
				++$deleted;
			}
		}//end foreach

		return static::qp_success(
			[
				'quiz_id'          => $quiz_id,
				'user_id'          => $user_id,
				'deleted_count'    => $deleted,
				'deleted_attempts' => array_map( 'absint', $attempt_ids ),
			]
		);
	}

	private static function qp_action_set_score( array $config ): array {
		global $wpdb;

		$attempt_id = absint( $config['attempt_id'] ?? 0 );
		$attempt    = static::qp_get_attempt( $attempt_id );

		if ( ! $attempt ) {
			return static::qp_action_error( 'Attempt not found.' );
		}

		$earned = (float) ( $config['earned_marks'] ?? 0 );
		$total  = (float) ( $attempt->total_marks ?? 0 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
		$updated = $wpdb->update(
			static::qp_attempts_table(),
			[ 'earned_marks' => $earned ],
			[ 'attempt_id' => $attempt_id ],
			[ '%f' ],
			[ '%d' ]
		);

		if ( false === $updated ) {
			return static::qp_action_error( 'The attempt score could not be updated.' );
		}

		return static::qp_success(
			array_merge(
				static::qp_attempt_payload( static::qp_get_attempt( $attempt_id ) ),
				[ 'earned_marks' => $earned, 'percentage' => static::qp_percent( $earned, $total ) ]
			)
		);
	}

	private static function qp_action_set_result( array $config ): array {
		global $wpdb;

		$attempt_id = absint( $config['attempt_id'] ?? 0 );
		$attempt    = static::qp_get_attempt( $attempt_id );

		if ( ! $attempt ) {
			return static::qp_action_error( 'Attempt not found.' );
		}

		$result = sanitize_key( (string) ( $config['result'] ?? '' ) );

		if ( ! in_array( $result, [ 'passed', 'failed', 'pending', 'expired' ], true ) ) {
			return static::qp_action_error( 'Result must be one of: passed, failed, pending, expired.' );
		}

		$update = [ 'attempt_status' => $result ];

		if ( in_array( $result, [ 'passed', 'failed' ], true ) ) {
			$update['is_manually_reviewed'] = 1;
			$update['manually_reviewed_at'] = current_time( 'mysql', true );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
		$updated = $wpdb->update(
			static::qp_attempts_table(),
			$update,
			[ 'attempt_id' => $attempt_id ],
			null,
			[ '%d' ]
		);

		if ( false === $updated ) {
			return static::qp_action_error( 'The attempt result could not be updated.' );
		}

		return static::qp_success(
			array_merge(
				static::qp_attempt_payload( static::qp_get_attempt( $attempt_id ) ),
				[ 'status' => $result ]
			)
		);
	}

	private static function qp_action_get_analytics( array $config ): array {
		global $wpdb;

		$quiz_id = absint( $config['quiz_id'] ?? 0 );

		if ( ! static::qp_get_quiz( $quiz_id ) ) {
			return static::qp_action_error( 'Quiz not found.' );
		}

		$attempts_table = static::qp_attempts_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total_attempts,
					COUNT(DISTINCT user_id) AS unique_users,
					SUM(CASE WHEN attempt_status = 'passed' THEN 1 ELSE 0 END) AS passed,
					SUM(CASE WHEN attempt_status = 'failed' THEN 1 ELSE 0 END) AS failed,
					SUM(CASE WHEN attempt_status = 'pending' THEN 1 ELSE 0 END) AS pending,
					SUM(CASE WHEN attempt_status = 'expired' THEN 1 ELSE 0 END) AS expired,
					AVG(CASE WHEN total_marks > 0 THEN (earned_marks / total_marks) * 100 ELSE NULL END) AS avg_percentage
				 FROM %i WHERE quiz_id = %d",
				$attempts_table,
				$quiz_id
			),
			ARRAY_A
		);

		$total_attempts = (int) ( $totals['total_attempts'] ?? 0 );
		$passed         = (int) ( $totals['passed'] ?? 0 );

		return static::qp_success(
			array_merge(
				static::qp_quiz_payload( $quiz_id ),
				[
					'total_attempts'   => $total_attempts,
					'unique_users'     => (int) ( $totals['unique_users'] ?? 0 ),
					'avg_percentage'   => round( (float) ( $totals['avg_percentage'] ?? 0 ), 2 ),
					'pass_rate'        => $total_attempts ? round( ( $passed / $total_attempts ) * 100, 2 ) : 0.0,
					'status_breakdown' => [
						'passed'  => $passed,
						'failed'  => (int) ( $totals['failed'] ?? 0 ),
						'pending' => (int) ( $totals['pending'] ?? 0 ),
						'expired' => (int) ( $totals['expired'] ?? 0 ),
					],
				]
			)
		);
	}

	/**
	 * Write the quiz meta the config carries. Only the keys the config actually
	 * mentions are written, so update flows leave untouched settings alone.
	 */
	private static function qp_save_quiz_meta( int $quiz_id, array $config ): void {
		if ( '' !== trim( (string) ( $config['quiz_type'] ?? '' ) ) ) {
			update_post_meta( $quiz_id, 'quizpress_quiz_type', sanitize_key( (string) $config['quiz_type'] ) );
		}

		if ( isset( $config['time_limit'] ) && '' !== (string) $config['time_limit'] ) {
			update_post_meta( $quiz_id, 'quizpress_quiz_time', absint( $config['time_limit'] ) );
			update_post_meta( $quiz_id, 'quizpress_quiz_time_unit', sanitize_key( (string) ( $config['time_unit'] ?? 'minutes' ) ) );
		}

		if ( isset( $config['passing_grade'] ) && '' !== (string) $config['passing_grade'] ) {
			update_post_meta( $quiz_id, 'quizpress_quiz_passing_grade', absint( $config['passing_grade'] ) );
		}

		if ( isset( $config['max_attempts'] ) && '' !== (string) $config['max_attempts'] ) {
			update_post_meta( $quiz_id, 'quizpress_quiz_max_attempts_allowed', absint( $config['max_attempts'] ) );
		}
	}

	protected static function qp_get_quiz( int $quiz_id ) {
		$post = $quiz_id ? get_post( $quiz_id ) : null;

		if ( ! $post || 'quizpress_quiz' !== (string) ( $post->post_type ?? '' ) ) {
			return null;
		}

		return $post;
	}

	protected static function qp_get_attempt( int $attempt_id ) {
		global $wpdb;

		if ( ! $attempt_id ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE attempt_id = %d',
				static::qp_attempts_table(),
				$attempt_id
			)
		);
	}

	private static function qp_delete_attempt_rows( int $attempt_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
		$answers = $wpdb->delete(
			static::qp_attempt_answers_table(),
			[ 'attempt_id' => $attempt_id ],
			[ '%d' ]
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
		$attempt = $wpdb->delete(
			static::qp_attempts_table(),
			[ 'attempt_id' => $attempt_id ],
			[ '%d' ]
		);

		return false !== $attempt;
	}
}
