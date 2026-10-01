<?php

namespace Zaplane\Integrations\Quizpress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait QpTriggerResolverTrait {

	/**
	 * Resolve one trigger node against raw hook args. Returns the payload
	 * array, or false when the event does not apply to this node.
	 */
	protected static function qp_resolve_trigger( array $node, array $args ) {
		$event = (string) ( $node['event'] ?? '' );

		// Automation hands us graph_node['data'] (config at the top level), but
		// the test helpers pass the full node (config one level deeper). Accept
		// both — reading only one shape silently empties every config gate.
		$config = [];
		if ( isset( $node['config'] ) && is_array( $node['config'] ) ) {
			$config = $node['config'];
		} elseif ( isset( $node['data']['config'] ) && is_array( $node['data']['config'] ) ) {
			$config = $node['data']['config'];
		}

		switch ( $event ) {
			case 'quiz_created':
			case 'quiz_updated':
				return static::qp_resolve_quiz_saved( $config, $args, $event );

			case 'quiz_deleted':
				return static::qp_resolve_quiz_deleted( $config, $args );

			case 'quiz_started':
			case 'quiz_attempted':
			case 'quiz_reattempted':
				return static::qp_resolve_attempt_started( $config, $args, $event );

			case 'quiz_submitted':
			case 'quiz_completed':
			case 'quiz_score_reached':
			case 'poll_submitted':
			case 'poll_result_updated':
			case 'survey_submitted':
			case 'survey_completed':
				return static::qp_resolve_attempt_finished( $config, $args, $event );

			case 'quiz_passed':
			case 'quiz_failed':
			case 'certificate_issued':
				return static::qp_resolve_attempt_status( $config, $args, $event );

			case 'certificate_downloaded':
				return static::qp_resolve_certificate_downloaded( $config );

			case 'lead_captured':
				return static::qp_resolve_lead_captured( $args );

			case 'user_registered':
				return static::qp_resolve_user_registered( $args );

			case 'question_added':
			case 'question_updated':
			case 'question_deleted':
				return static::qp_resolve_question_saved( $config, $args, $event );

			case 'question_imported':
				return static::qp_resolve_question_imported();

			case 'poll_answer_selected':
				return static::qp_resolve_poll_answer_selected( $config );
		}//end switch

		return false;
	}

	/**
	 * Resolve save_post_quizpress_quiz: ( $post_id, $post, $update ).
	 *
	 * @param array  $config Trigger configuration.
	 * @param array  $args   Hook arguments.
	 * @param string $event  Trigger event.
	 */
	protected static function qp_resolve_quiz_saved( array $config, array $args, string $event ) {
		if ( ! static::qp_active() ) {
			return false;
		}

		$post   = $args[1] ?? null;
		$update = (bool) ( $args[2] ?? false );

		if (
			! is_object( $post )
			|| 'quizpress_quiz' !== (string) ( $post->post_type ?? '' )
			|| ( 'quiz_created' === $event && $update )
			|| ( 'quiz_updated' === $event && ! $update )
		) {
			return false;
		}

		$post_id = absint( $args[0] ?? $post->ID ?? 0 );

		if ( ! $post_id ) {
			return false;
		}

		if ( ! static::qp_quiz_gate( $config, $post_id ) ) {
			return false;
		}

		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) {
			return false;
		}

		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) ) {
			return false;
		}

		$timestamp_key = 'quiz_created' === $event ? 'created_at' : 'updated_at';

		return static::qp_quiz_payload_from_post(
			$post,
			[
				$timestamp_key => current_time( 'mysql' ),
			]
		);
	}

	/**
	 * Resolve before_delete_post / trashed_post: ( $post_id, $post ).
	 *
	 * @param array $config Trigger configuration.
	 * @param array $args   Hook arguments.
	 */
	protected static function qp_resolve_quiz_deleted( array $config, array $args ) {
		if ( ! static::qp_active() ) {
			return false;
		}

		$post = $args[1] ?? null;

		if ( ! is_object( $post ) || 'quizpress_quiz' !== (string) ( $post->post_type ?? '' ) ) {
			return false;
		}

		$quiz_id = absint( $args[0] ?? $post->ID ?? 0 );

		if ( ! $quiz_id || ! static::qp_quiz_gate( $config, $quiz_id ) ) {
			return false;
		}

		return static::qp_quiz_payload_from_post( $post, [ 'deleted_at' => current_time( 'mysql' ) ] );
	}

	/**
	 * The trigger's quiz gate: empty config matches every quiz, otherwise the
	 * event's quiz must be the configured one.
	 */
	protected static function qp_quiz_gate( array $config, int $quiz_id ): bool {
		$required = absint( $config['quiz_id'] ?? 0 );

		if ( ! $required ) {
			return true;
		}

		return $quiz_id > 0 && $required === $quiz_id;
	}

	/**
	 * before/after_quiz_attempt_start: ( $prepared_attempt | $attempt ).
	 */
	protected static function qp_resolve_attempt_started( array $config, array $args, string $event ) {
		if ( ! static::qp_active() ) {
			return false;
		}

		$attempt = $args[0] ?? null;
		$quiz_id = absint( static::qp_attempt_get( $attempt, 'quiz_id' ) );

		if ( ! $quiz_id || ! static::qp_quiz_gate( $config, $quiz_id ) ) {
			return false;
		}

		if ( 'quiz_started' === $event ) {
			return static::qp_attempt_payload( $attempt, [ 'started_at' => current_time( 'mysql' ) ] );
		}

		$user_id  = absint( static::qp_attempt_get( $attempt, 'user_id' ) );
		$guest_id = (string) static::qp_attempt_get( $attempt, 'guest_id' );

		if ( ! $user_id && '' === $guest_id ) {
			$guest_id = static::qp_guest_id_for_request();
		}

		$attempt_number = static::qp_attempt_count( $quiz_id, $user_id, $guest_id ) + 1;

		if ( 'quiz_reattempted' === $event && $attempt_number < 2 ) {
			return false;
		}

		return static::qp_attempt_payload(
			$attempt,
			[
				'attempt_number' => $attempt_number,
				'is_reattempt'   => $attempt_number > 1,
			]
		);
	}

	/**
	 * after_quiz_attempt_finished: ( $attempt ). Shared by the submit, complete,
	 * score, poll and survey triggers — each narrows the event differently.
	 */
	protected static function qp_resolve_attempt_finished( array $config, array $args, string $event ) {
		if ( ! static::qp_active() ) {
			return false;
		}

		$attempt = $args[0] ?? null;
		$quiz_id = absint( static::qp_attempt_get( $attempt, 'quiz_id' ) );

		if ( ! $quiz_id || ! static::qp_quiz_gate( $config, $quiz_id ) ) {
			return false;
		}

		$quiz_type = static::qp_quiz_type( $quiz_id );

		switch ( $event ) {
			case 'poll_submitted':
			case 'poll_result_updated':
				if ( 'poll' !== $quiz_type ) {
					return false;
				}
				break;

			case 'survey_submitted':
			case 'survey_completed':
				if ( 'survey' !== $quiz_type ) {
					return false;
				}
				break;
		}//end switch

		$total    = (int) static::qp_attempt_get( $attempt, 'total_questions' );
		$answered = (int) static::qp_attempt_get( $attempt, 'total_answered_questions' );

		// "Completed" means nothing was skipped — the submitted trigger covers
		// every finish, including partial ones.
		if ( in_array( $event, [ 'quiz_completed', 'survey_completed' ], true ) && ( $total <= 0 || $answered < $total ) ) {
			return false;
		}

		$extra = [];

		if ( 'quiz_score_reached' === $event ) {
			$wanted = (float) str_replace( '%', '', (string) ( $config['score_percent'] ?? '' ) );

			if ( $wanted <= 0 ) {
				return false;
			}

			$reached = static::qp_percent( static::qp_attempt_get( $attempt, 'earned_marks' ), static::qp_attempt_get( $attempt, 'total_marks' ) );

			if ( $reached < $wanted ) {
				return false;
			}

			$extra = [
				'score_percent'    => $wanted,
				'achieved_percent' => $reached,
			];
		}

		if ( 'poll_result_updated' === $event ) {
			$extra = [
				'total_responses' => static::qp_attempt_count_for_quiz( $quiz_id ),
				'result_updated_at' => current_time( 'mysql' ),
			];
		}

		return static::qp_attempt_payload( $attempt, $extra );
	}

	/**
	 * quiz_attempt_status_{passed|failed}: ( $attempt ) — fires from the REST
	 * finish path (object) and the admin-ajax review path (array).
	 */
	protected static function qp_resolve_attempt_status( array $config, array $args, string $event ) {
		if ( ! static::qp_active() ) {
			return false;
		}

		$attempt = $args[0] ?? null;
		$quiz_id = absint( static::qp_attempt_get( $attempt, 'quiz_id' ) );

		if ( ! $quiz_id || ! static::qp_quiz_gate( $config, $quiz_id ) ) {
			return false;
		}

		$status = (string) static::qp_attempt_get( $attempt, 'attempt_status' );
		// Only the failed trigger wants a failed attempt; quiz_passed and
		// certificate_issued both ride the passed-status hooks.
		$wanted = 'quiz_failed' === $event ? 'failed' : 'passed';

		if ( $status !== $wanted ) {
			return false;
		}

		$extra = [];

		if ( 'certificate_issued' === $event ) {
			if ( ! static::qp_certificates_addon_enabled() ) {
				return false;
			}

			$extra = [
				'certificate_url' => static::qp_certificate_url( $quiz_id ),
				'issued_at'       => current_time( 'mysql' ),
			];
		}

		return static::qp_attempt_payload( $attempt, $extra );
	}

	/**
	 * template_include: ( $template ) — the certificate view renders the
	 * printable certificate for ?source=qp-certificate&quiz_id=N. Read the
	 * request's source param directly: it is a query-string var, so it reaches
	 * both plain and pretty permalinks, and the mocked tests ship no
	 * get_query_var().
	 */
	protected static function qp_resolve_certificate_downloaded( array $config ) {
		if ( ! static::qp_active() ) {
			return false;
		}

		if ( 'qp-certificate' !== (string) ( $_GET['source'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only gate on a public render route.
			return false;
		}

		$quiz_id = absint( $_GET['quiz_id'] ?? 0 );

		if ( ! $quiz_id || ! static::qp_quiz_gate( $config, $quiz_id ) ) {
			return false;
		}

		return static::qp_quiz_payload(
			$quiz_id,
			[
				'certificate_url' => static::qp_certificate_url( $quiz_id ),
				'downloaded_by'   => get_current_user_id(),
				'downloaded_at'   => current_time( 'mysql' ),
			]
		);
	}

	/**
	 * quizpress/after_create_magic_link: ( $login_link, $user ).
	 */
	protected static function qp_resolve_lead_captured( array $args ) {
		if ( ! static::qp_active() ) {
			return false;
		}

		$user = $args[1] ?? null;

		if ( ! $user || ! isset( $user->ID ) ) {
			return false;
		}

		return [
			'user_id'      => (int) $user->ID,
			'email'        => (string) ( $user->user_email ?? '' ),
			'display_name' => (string) ( $user->display_name ?? '' ),
			'magic_link'   => (string) ( $args[0] ?? '' ),
			'captured_at'  => current_time( 'mysql' ),
		];
	}

	/**
	 * user_register: ( $user_id, $userdata ).
	 */
	protected static function qp_resolve_user_registered( array $args ) {
		$user_id = absint( $args[0] ?? 0 );

		if ( ! $user_id ) {
			return false;
		}

		return static::qp_user_payload( $user_id, [ 'registered_at' => current_time( 'mysql' ) ] );
	}

	/**
	 * rest_pre_dispatch: ( $result, $server, $request ) — QuizPress questions
	 * live in a custom table with no save hooks, so the triggers read the REST
	 * request itself. Like the ECM claim triggers this fires as the request
	 * arrives (the row is written moments later by the request it describes).
	 */
	protected static function qp_resolve_question_saved( array $config, array $args, string $event ) {
		if ( ! static::qp_active() ) {
			return false;
		}

		$request = $args[2] ?? null;

		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
			return false;
		}

		$route  = (string) $request->get_route();
		$method = strtoupper( (string) $request->get_method() );
		$payload = [];
		$question = [];

		if ( 'question_added' === $event ) {
			if ( '/quizpress/v1/questions' !== $route || 'POST' !== $method ) {
				return false;
			}
		} elseif ( 'question_deleted' === $event ) {
			if ( 'DELETE' !== $method || ! preg_match( '#^/quizpress/v1/questions/(\d+)$#', $route, $matches ) ) {
				return false;
			}//end if

			$payload['question_id'] = (int) $matches[1];

			global $wpdb;
			// Read before the REST callback deletes the custom-table row.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- QuizPress stores questions in a custom table.
			$question = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT question_title, question_type, question_score, question_status
					 FROM %i WHERE question_id = %d',
					static::qp_questions_table(),
					$payload['question_id']
				),
				ARRAY_A
			);

			if ( ! is_array( $question ) ) {
				return false;
			}
		} else {
			if ( ! preg_match( '#^/quizpress/v1/questions/(\d+)$#', $route, $matches ) ) {
				return false;
			}

			if ( ! in_array( $method, [ 'PUT', 'PATCH', 'POST' ], true ) ) {
				return false;
			}

			$payload['question_id'] = (int) $matches[1];
		}

		$payload = array_merge(
			$payload,
			[
				'question_title'  => (string) ( $request->get_param( 'question_title' ) ?? $question['question_title'] ?? '' ),
				'question_type'   => (string) ( $request->get_param( 'question_type' ) ?? $question['question_type'] ?? '' ),
				'question_score'  => (float) ( $request->get_param( 'question_score' ) ?? $question['question_score'] ?? 0 ),
				'question_status' => (string) ( $request->get_param( 'question_status' ) ?? $question['question_status'] ?? 'publish' ),
			]
		);

		$wanted_type = (string) ( $config['question_type'] ?? '' );

		if ( '' !== $wanted_type && $wanted_type !== $payload['question_type'] ) {
			return false;
		}
		$timestamp_key = 'question_deleted' === $event ? 'deleted_at' : 'saved_at';
		$timestamp_key = 'question_deleted' === $event ? 'deleted_at' : 'saved_at';
		$payload[ $timestamp_key ] = current_time( 'mysql' );

		return $payload;
	}

	/**
	 * shutdown — QuizPress imports questions through admin-ajax (no hook, no
	 * REST), so this listens for the request that just ran and picks up the
	 * rows it created via question_created_at.
	 */
	protected static function qp_resolve_question_imported() {
		global $wpdb;

		if ( ! static::qp_active() || ! static::qp_did_action( 'wp_ajax_quizpress/import_quiz' ) ) {
			return false;
		}

		// question_created_at is written with current_time('mysql') (site-local),
		// so shift the request-start timestamp into site time. The 5s margin
		// covers clock/rounding noise; stray rows are rarer than missed ones.
		$request_time = (int) ( $_SERVER['REQUEST_TIME'] ?? time() );
		$offset       = (int) ( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );
		$start        = gmdate( 'Y-m-d H:i:s', $request_time + $offset - 5 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT question_id, question_title, question_type, question_score
				 FROM %i WHERE question_created_at >= %s
				 ORDER BY question_id ASC LIMIT 200',
				static::qp_questions_table(),
				$start
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return false;
		}

		$questions = [];

		foreach ( $rows as $row ) {
			$questions[] = [
				'question_id'    => (int) ( $row['question_id'] ?? 0 ),
				'question_title' => (string) ( $row['question_title'] ?? '' ),
				'question_type'  => (string) ( $row['question_type'] ?? '' ),
				'question_score' => (float) ( $row['question_score'] ?? 0 ),
			];
		}

		return [
			'questions'      => $questions,
			'imported_count' => count( $questions ),
			'imported_at'    => current_time( 'mysql' ),
		];
	}

	/**
	 * shutdown — a single poll answer goes through admin-ajax with no hook, so
	 * reconstruct the selection from the request plus the attempt-answers rows.
	 */
	protected static function qp_resolve_poll_answer_selected( array $config ) {
		global $wpdb;

		if ( ! static::qp_active() ) {
			return false;
		}

		if ( ! static::qp_did_action( 'wp_ajax_quizpress/frontend/insert_quiz_answer' ) && ! static::qp_did_action( 'wp_ajax_nopriv_quizpress/frontend/insert_quiz_answer' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- reading the request QuizPress just handled; nothing is written.
		$quiz_id = absint( $_POST['quiz_id'] ?? 0 );

		if ( ! $quiz_id || 'poll' !== static::qp_quiz_type( $quiz_id ) || ! static::qp_quiz_gate( $config, $quiz_id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$attempt_id  = absint( $_POST['attempt_id'] ?? 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$question_id = absint( $_POST['question_id'] ?? 0 );

		$selected = [];

		if ( $attempt_id && $question_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT attempt_answer_id, answer_id, answer, is_correct
					 FROM %i WHERE attempt_id = %d AND question_id = %d
					 ORDER BY attempt_answer_id ASC',
					static::qp_attempt_answers_table(),
					$attempt_id,
					$question_id
				),
				ARRAY_A
			);

			foreach ( (array) $rows as $row ) {
				$selected[] = [
					'answer_id'  => absint( $row['answer_id'] ?? 0 ),
					'answer'     => (string) ( $row['answer'] ?? '' ),
					'is_correct' => (bool) ( $row['is_correct'] ?? false ),
				];
			}
		}

		return static::qp_quiz_payload(
			$quiz_id,
			[
				'attempt_id'       => $attempt_id,
				'question_id'      => $question_id,
				'selected_answers' => $selected,
				'answered_by'      => get_current_user_id(),
				'answered_at'      => current_time( 'mysql' ),
			]
		);
	}

	/**
	 * Total attempts recorded on a quiz (any user) — poll result context.
	 */
	protected static function qp_attempt_count_for_quiz( int $quiz_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE quiz_id = %d',
				static::qp_attempts_table(),
				$quiz_id
			)
		);
	}

	/**
	 * Resolve the guest token cookie into an attempt-row guest id, only when
	 * QuizPress's own helper is available. Used to count attempts for logged-
	 * out visitors.
	 */
	protected static function qp_guest_id_for_request(): string {
		if ( ! class_exists( '\QuizPress\Helper' ) || ! method_exists( '\QuizPress\Helper', 'quizpress_get_guest_token' ) || ! method_exists( '\QuizPress\Helper', 'get_guest_id_by_token' ) ) {
			return '';
		}

		$token = \QuizPress\Helper::quizpress_get_guest_token();

		if ( ! $token ) {
			return '';
		}

		return (string) \QuizPress\Helper::get_guest_id_by_token( $token );
	}
}
