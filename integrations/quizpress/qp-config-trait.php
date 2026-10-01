<?php

namespace Zaplane\Integrations\Quizpress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait QpConfigTrait {

	/**
	 * Whether the QuizPress plugin (free or premium) is active.
	 */
	protected static function qp_active(): bool {
		return \defined( 'QUIZPRESS_VERSION' );
	}

	/**
	 * QuizPress quiz types as slug => label (mirrors the quiz builder's wizard).
	 *
	 * @return array<string,string>
	 */
	protected static function qp_quiz_types(): array {
		return [
			'quiz'   => 'Quiz',
			'exam'   => 'Exam',
			'survey' => 'Survey',
			'poll'   => 'Poll',
		];
	}

	/**
	 * The type of a quiz (quiz, exam, survey or poll) from its post meta.
	 */
	protected static function qp_quiz_type( int $quiz_id ): string {
		if ( ! $quiz_id ) {
			return '';
		}

		return (string) get_post_meta( $quiz_id, 'quizpress_quiz_type', true );
	}

	/**
	 * Question types QuizPress ships. The premium-only types are listed too:
	 * the integration is built against the full build and the list only feeds
	 * select fields.
	 *
	 * @return array<string,string>
	 */
	protected static function qp_question_types(): array {
		return [
			'single_choice'              => 'Single Choice',
			'single_choice_horizontal'   => 'Single Choice (Horizontal)',
			'multiple_choice'            => 'Multiple Choice',
			'multiple_choice_horizontal' => 'Multiple Choice (Horizontal)',
			'true_false'                 => 'True / False',
			'drop_down'                  => 'Drop Down',
			'short_answer'               => 'Short Answer',
			'paragraph'                  => 'Paragraph',
			'number'                     => 'Number',
			'date'                       => 'Date',
			'fill_in_the_blanks'         => 'Fill In The Blanks',
			'file_upload'                => 'File Upload',
			'pin'                        => 'Pin',
			'range'                      => 'Range',
			'image_marking'              => 'Image Marking',
			'graph'                      => 'Graph',
			'ordering'                   => 'Ordering',
			'puzzle'                     => 'Puzzle',
			'matching'                   => 'Matching',
		];
	}

	protected static function qp_questions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'quizpress_questions';
	}

	protected static function qp_answers_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'quizpress_answers';
	}

	protected static function qp_attempts_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'quizpress_attempts';
	}

	protected static function qp_attempt_answers_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'quizpress_attempt_answers';
	}

	/**
	 * Read one field off an attempt row, which QuizPress hands over as either
	 * an object (REST paths) or an array (admin-ajax paths) depending on the
	 * caller.
	 *
	 * @param object|array $attempt
	 */
	protected static function qp_attempt_get( $attempt, string $key ) {
		if ( is_object( $attempt ) ) {
			return $attempt->{$key} ?? null;
		}

		if ( is_array( $attempt ) ) {
			return $attempt[ $key ] ?? null;
		}

		return null;
	}

	/**
	 * A quiz's earned-percentage, guarding against a zero total.
	 */
	protected static function qp_percent( $earned, $total ): float {
		$earned = (float) $earned;
		$total  = (float) $total;

		if ( $total <= 0 ) {
			return 0.0;
		}

		return round( ( $earned / $total ) * 100, 2 );
	}

	/**
	 * Standardized quiz payload, merged with any extras.
	 */
	protected static function qp_quiz_payload( int $quiz_id, array $extra = [] ): array {
		$post = $quiz_id ? get_post( $quiz_id ) : null;

		if ( ! $post || 'quizpress_quiz' !== (string) ( $post->post_type ?? '' ) ) {
			return array_merge(
				[
					'quiz_id'      => $quiz_id,
					'quiz_title'   => '',
					'quiz_type'    => '',
					'permalink'    => '',
					'quiz_status'  => '',
				],
				$extra
			);
		}

		return static::qp_quiz_payload_from_post( $post, $extra );
	}

	/**
	 * Standardized quiz payload from a post object, including during deletion.
	 *
	 * @param object $post  Quiz post.
	 * @param array  $extra Additional payload fields.
	 * @return array<string,mixed>
	 */
	protected static function qp_quiz_payload_from_post( object $post, array $extra = [] ): array {
		$quiz_id = absint( $post->ID ?? 0 );
		$payload = [
			'quiz_id'      => (int) $quiz_id,
			'quiz_title'   => (string) ( $post->post_title ?? '' ),
			'quiz_type'    => static::qp_quiz_type( $quiz_id ),
			'permalink'    => (string) get_permalink( $quiz_id ),
			'quiz_status'  => (string) ( $post->post_status ?? '' ),
		];

		return array_merge( $payload, $extra );
	}

	/**
	 * Standardized attempt payload from an attempt row, merged with any extras.
	 *
	 * @param object|array $attempt
	 */
	protected static function qp_attempt_payload( $attempt, array $extra = [] ): array {
		$quiz_id    = absint( static::qp_attempt_get( $attempt, 'quiz_id' ) );
		$attempt_id = absint( static::qp_attempt_get( $attempt, 'attempt_id' ) );
		$user_id    = absint( static::qp_attempt_get( $attempt, 'user_id' ) );
		$earned     = (float) static::qp_attempt_get( $attempt, 'earned_marks' );
		$total      = (float) static::qp_attempt_get( $attempt, 'total_marks' );

		$payload = [
			'attempt_id'      => $attempt_id,
			'quiz_id'         => $quiz_id,
			'user_id'         => $user_id,
			'guest_id'        => (string) static::qp_attempt_get( $attempt, 'guest_id' ),
			'status'          => (string) static::qp_attempt_get( $attempt, 'attempt_status' ),
			'earned_marks'    => $earned,
			'total_marks'     => $total,
			'percentage'      => static::qp_percent( $earned, $total ),
			'total_questions' => (int) static::qp_attempt_get( $attempt, 'total_questions' ),
			'answered_questions' => (int) static::qp_attempt_get( $attempt, 'total_answered_questions' ),
			'started_at'      => (string) static::qp_attempt_get( $attempt, 'attempt_started_at' ),
			'ended_at'        => (string) static::qp_attempt_get( $attempt, 'attempt_ended_at' ),
		];

		$user = $user_id ? get_userdata( $user_id ) : false;

		if ( $user ) {
			$payload['user_email'] = (string) ( $user->user_email ?? '' );
			$payload['user_name']  = (string) ( $user->display_name ?? '' );
		}

		return array_merge(
			$payload,
			static::qp_quiz_payload( $quiz_id ),
			$extra
		);
	}

	/**
	 * How many attempts a user (or guest) already has on a quiz — used to tell
	 * a first attempt from a reattempt.
	 */
	protected static function qp_attempt_count( int $quiz_id, int $user_id, string $guest_id = '' ): int {
		global $wpdb;

		if ( ! $quiz_id ) {
			return 0;
		}

		if ( $user_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE quiz_id = %d AND user_id = %d',
					static::qp_attempts_table(),
					$quiz_id,
					$user_id
				)
			);
		}

		if ( '' === $guest_id ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE quiz_id = %d AND guest_id = %s',
				static::qp_attempts_table(),
				$quiz_id,
				$guest_id
			)
		);
	}

	/**
	 * Whether the Certificates addon is switched on and installed. The option
	 * outlives the addon code (the free build keeps it), so mirror QuizPress's
	 * own status check: the toggle AND the addon directory.
	 */
	protected static function qp_certificates_addon_enabled(): bool {
		if ( ! static::qp_active() ) {
			return false;
		}

		if ( class_exists( '\QuizPress\Helper' ) && method_exists( '\QuizPress\Helper', 'get_addon_active_status' ) ) {
			return (bool) \QuizPress\Helper::get_addon_active_status( 'certificates' );
		}

		$settings = json_decode( (string) get_option( 'quizpress_addons_settings', '{}' ) );

		if ( empty( $settings->certificates ) ) {
			return false;
		}

		// The directory guard only applies where the plugin defined the constant
		// (production): the mocked tests run without QuizPress loaded.
		if ( \defined( 'QUIZPRESS_ADDONS_DIR_PATH' ) && ! is_dir( QUIZPRESS_ADDONS_DIR_PATH . 'certificates' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * The on-demand certificate URL QuizPress itself hands to its frontend:
	 * the quiz permalink with the source query var the addon's
	 * template_include listener reacts to.
	 */
	protected static function qp_certificate_url( int $quiz_id ): string {
		$permalink = get_permalink( $quiz_id );

		if ( ! $permalink ) {
			return '';
		}

		return add_query_arg(
			[
				'source'  => 'qp-certificate',
				'quiz_id' => $quiz_id,
			],
			$permalink
		);
	}

	/**
	 * did_action with a guard: the mocked test environment doesn't define it,
	 * so the shutdown-based resolvers must be able to run there too.
	 */
	protected static function qp_did_action( string $hook ): bool {
		return function_exists( 'did_action' ) && (int) did_action( $hook ) > 0;
	}

	protected static function qp_success( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	protected static function qp_action_error( string $message ): array {
		return static::qp_success(
			[
				'success' => false,
				'error'   => $message,
			]
		);
	}

	protected static function qp_unknown_action( string $event ): array {
		return static::qp_success( [ 'error' => sprintf( 'Unknown QuizPress action: %s', $event ) ] );
	}
}
