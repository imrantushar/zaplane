<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Hooks verified against Academy's REST, AJAX and form handlers. */
trait AcademyEvents {
	public static function get_triggers(): array {
		$finished = 'academy_quizzes/api/after_quiz_attempt_finished';
		$reviewed = 'academy_quizzes/after_quiz_attempt_manual_review';
		$definitions = [
			'user_enroll_course' => [ 'User enrolled in a course', 'academy/course/after_enroll' ],
			'course_complete' => [ 'User completed a course', 'academy/admin/course_complete_after' ],
			'lesson_complete' => [ 'User completed a lesson', 'academy/frontend/after_mark_topic_complete' ],
			'lesson_incomplete' => [ 'User marked a lesson incomplete', 'academy/frontend/mark_topic_incomplete' ],
			'academy_quiz_course_attempt' => [ 'User submitted a graded quiz', $finished ],
			'quiz_target' => [ 'User achieved target percentage on a quiz', [ $finished, $reviewed ] ],
			'quiz_started' => [ 'User started a quiz', 'academy_quizzes/api/after_quiz_attempt_start' ],
			'quiz_passed' => [ 'User passed a quiz', [ $finished, $reviewed ] ],
			'quiz_failed' => [ 'User failed a quiz', [ $finished, $reviewed ] ],
			'quiz_pending' => [ 'Quiz submitted for manual review', $finished ],
			'quiz_reviewed' => [ 'Quiz attempt manually reviewed', $reviewed ],
			// insert_student/instructor also serve AJAX registration: listening to
			// their outer after_*_registration hook as well would fire twice.
			'student_registered' => [ 'Student registered', [ 'academy/admin/after_register_student', 'academy/api/auth/after_student_registration' ] ],
			'instructor_registered' => [ 'Instructor registered', [ 'academy/admin/after_register_instructor', 'academy/api/auth/after_instructor_registration' ] ],
			'instructor_status_changed' => [ 'Instructor status changed', 'academy/admin/update_instructor_status' ],
			'question_asked' => [ 'Course question asked', 'academy/frontend/insert_course_qa' ],
			'question_answered' => [ 'Course question answered', 'academy/frontend/insert_course_qa_answered' ],
			'lesson_comment_added' => [ 'Lesson comment added', 'academy/lesson_comment/inserted' ],
			// Ratings are saved via multiple APIs; metadata hooks cover all paths.
			'course_review_added' => [ 'Course review added', 'added_comment_meta' ],
			'course_review_updated' => [ 'Course rating updated', 'updated_comment_meta' ],
			'course_published' => [ 'Course published', 'transition_post_status' ],
		];
		$result = [];
		foreach ( $definitions as $key => $definition ) {
			$result[ $key ] = [ 'label' => $definition[0], 'hook' => $definition[1] ];
		}
		return $result;
	}

	private static function quiz_events(): array {
		return [ 'academy_quiz_course_attempt', 'quiz_target', 'quiz_started', 'quiz_passed', 'quiz_failed', 'quiz_pending', 'quiz_reviewed' ];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( ! isset( self::get_triggers()[ $trigger ] ) ) {
			return [];
		}
		if ( in_array( $trigger, [ 'student_registered', 'instructor_registered' ], true ) ) {
			return [];
		}
		if ( 'instructor_status_changed' === $trigger ) {
			return [ [
				'key' => 'status', 'label' => 'Status', 'type' => 'select',
				'options' => [
					[ 'value' => 'any', 'label' => 'Any status' ],
					[ 'value' => 'approved', 'label' => 'Approved' ],
					[ 'value' => 'pending', 'label' => 'Pending' ],
					[ 'value' => 'remove', 'label' => 'Removed' ],
				],
			] ];
		}
		if ( in_array( $trigger, self::quiz_events(), true ) ) {
			$fields = [ self::selection( 'quiz_id', 'Quiz', 'quiz' ) ];
			if ( 'quiz_target' === $trigger ) {
				$fields[] = [ 'key' => 'target_percentage', 'label' => 'Minimum percentage (0–100)', 'type' => 'number', 'required' => true ];
			}
			$fields[] = self::selection( 'course_id', 'Course', 'acourse' );
			return $fields;
		}
		if ( in_array( $trigger, [ 'lesson_complete', 'lesson_incomplete', 'lesson_comment_added' ], true ) ) {
			return [ self::selection( 'lesson_id', 'Lesson', 'lesson' ), self::selection( 'course_id', 'Course', 'acourse' ) ];
		}
		return [ self::selection( 'course_id', 'Course', 'acourse' ) ];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$event = self::event( $node );
		$config = self::config( $node );
		if ( ! isset( self::get_triggers()[ $event ] ) ) {
			return false;
		}
		if ( in_array( $event, self::quiz_events(), true ) ) {
			return self::resolve_quiz( $event, $config, $args );
		}
		$data = [ 'success' => true ];
		$user_id = 0;
		$course_id = 0;
		switch ( $event ) {
			case 'student_registered':
			case 'instructor_registered':
			case 'instructor_status_changed':
				$user_id = (int) ( $args[0] ?? 0 );
				if ( ! $user_id || ! get_userdata( $user_id ) ) {
					return false;
				}
				if ( 'instructor_status_changed' === $event ) {
					$status = $args[1] ?? '';
					if ( ! in_array( $status, [ 'approved', 'pending', 'remove' ], true ) || ! self::matches( $config, 'status', $status ) ) {
						return false;
					}
					$data['status'] = $status;
				}
				return array_merge( $data, self::user_data( $user_id ) );
			case 'user_enroll_course':
				$course_id = (int) ( $args[0] ?? 0 );
				$data['enroll_id'] = (int) ( $args[1] ?? 0 );
				$user_id = (int) ( $args[2] ?? 0 );
				if ( ! $data['enroll_id'] ) {
					return false;
				}
				break;
			case 'course_complete':
				$course_id = (int) ( $args[0] ?? 0 );
				$user_id = (int) ( $args[1] ?? 0 );
				break;
			case 'lesson_complete':
			case 'lesson_incomplete':
				// Actual signature: topic_type, course_id, topic_id, user_id.
				if ( 'lesson' !== ( $args[0] ?? '' ) ) {
					return false;
				}
				$course_id = (int) ( $args[1] ?? 0 );
				$data['lesson_id'] = (int) ( $args[2] ?? 0 );
				$user_id = (int) ( $args[3] ?? 0 );
				if ( ! $data['lesson_id'] || ! self::matches( $config, 'lesson_id', $data['lesson_id'] ) ) {
					return false;
				}
				break;
			case 'course_published':
				$post = $args[2] ?? null;
				if ( 'publish' !== ( $args[0] ?? '' ) || 'publish' === ( $args[1] ?? '' ) || ! is_object( $post ) || 'academy_courses' !== ( $post->post_type ?? '' ) ) {
					return false;
				}
				$course_id = (int) $post->ID;
				$user_id = (int) $post->post_author;
				$data['previous_status'] = (string) ( $args[1] ?? '' );
				break;
			case 'question_asked':
			case 'question_answered':
				$raw = $args[0] ?? null;
				if ( ! is_array( $raw ) && ! is_object( $raw ) ) {
					return false;
				}
				$raw = (array) $raw;
				$comment = get_comment( (int) ( $raw['comment_ID'] ?? $raw['id'] ?? 0 ) );
				if ( ! $comment || 'academy_qa' !== $comment->comment_type ) {
					return false;
				}
				$data['question_title'] = (string) get_comment_meta( $comment->comment_ID, 'academy_question_title', true );
				break;
			case 'lesson_comment_added':
				$comment = get_comment( (int) ( $args[0] ?? 0 ) );
				$data['lesson_id'] = (int) ( $args[2] ?? 0 );
				if ( ! $comment || ! $data['lesson_id'] || ! self::matches( $config, 'lesson_id', $data['lesson_id'] ) ) {
					return false;
				}
				break;
			case 'course_review_added':
			case 'course_review_updated':
				if ( 'academy_rating' !== ( $args[2] ?? '' ) ) {
					return false;
				}
				$comment = get_comment( (int) ( $args[1] ?? 0 ) );
				$rating = (float) ( $args[3] ?? 0 );
				if ( ! $comment || 'academy_courses' !== $comment->comment_type || $rating < 1 || $rating > 5 ) {
					return false;
				}
				$data['rating'] = $rating;
				break;
		}
		if ( isset( $comment ) ) {
			$course_id = 'lesson_comment_added' === $event ? (int) ( $args[1] ?? 0 ) : (int) $comment->comment_post_ID;
			$user_id = (int) $comment->user_id;
			$data['comment_id'] = (int) $comment->comment_ID;
			$data['parent_id'] = (int) $comment->comment_parent;
			$data['content'] = (string) $comment->comment_content;
		}
		if ( $course_id <= 0 || $user_id <= 0 || ! self::matches( $config, 'course_id', $course_id ) ) {
			return false;
		}
		return array_merge( $data, self::course_data( $course_id ), self::user_data( $user_id ) );
	}

	private static function resolve_quiz( string $event, array $config, array $args ) {
		$attempt = $args[0] ?? null;
		if ( ! is_array( $attempt ) && ! is_object( $attempt ) ) {
			return false;
		}
		$attempt = (array) $attempt;
		$quiz_id = (int) ( $attempt['quiz_id'] ?? 0 );
		$user_id = (int) ( $attempt['user_id'] ?? 0 );
		$course_id = (int) ( $attempt['course_id'] ?? 0 );
		$status = (string) ( $attempt['attempt_status'] ?? '' );
		if ( ! $quiz_id || ! $user_id || ! self::matches( $config, 'quiz_id', $quiz_id ) || ! self::matches( $config, 'course_id', $course_id ) ) {
			return false;
		}
		$required = [ 'quiz_passed' => 'passed', 'quiz_failed' => 'failed', 'quiz_pending' => 'pending' ];
		if ( isset( $required[ $event ] ) && $required[ $event ] !== $status ) {
			return false;
		}
		if ( in_array( $event, [ 'academy_quiz_course_attempt', 'quiz_target', 'quiz_reviewed' ], true ) && ! in_array( $status, [ 'passed', 'failed', 'attempt_ended' ], true ) ) {
			return false;
		}
		$score = (float) ( $attempt['earned_marks'] ?? 0 );
		$total = (float) ( $attempt['total_marks'] ?? 0 );
		$percentage = $total > 0 ? $score / $total * 100 : 0;
		if ( 'quiz_target' === $event ) {
			$target = $config['target_percentage'] ?? null;
			if ( ! is_numeric( $target ) || $target < 0 || $target > 100 || $total <= 0 || $percentage < (float) $target ) {
				return false;
			}
		}
		return array_merge( [
			'success' => true, 'attempt_id' => (int) ( $attempt['attempt_id'] ?? 0 ),
			'quiz_id' => $quiz_id, 'quiz_title' => get_the_title( $quiz_id ),
			'attempt_status' => $status, 'score' => $score, 'total' => $total,
			'total_marks' => $total, 'percentage' => round( $percentage, 2 ),
		], self::course_data( $course_id ), self::user_data( $user_id ) );
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		if ( ! isset( self::get_triggers()[ $trigger ] ) ) {
			return [];
		}
		$user = [ 'user_id' => 1, 'user_email' => 'student@example.com', 'first_name' => 'Jane', 'last_name' => 'Smith', 'username' => 'janesmith' ];
		$course = [ 'course_id' => 1, 'course_title' => 'Sample Course', 'course_url' => 'https://example.com/course/sample-course' ];
		$data = array_merge( [ 'success' => true ], $user );
		if ( in_array( $trigger, [ 'student_registered', 'instructor_registered' ], true ) ) {
			return $data;
		}
		if ( 'instructor_status_changed' === $trigger ) {
			return array_merge( $data, [ 'status' => 'approved' ] );
		}
		$data = array_merge( $data, $course );
		if ( in_array( $trigger, self::quiz_events(), true ) ) {
			$status = [ 'quiz_failed' => 'failed', 'quiz_pending' => 'pending', 'quiz_started' => 'started' ];
			return array_merge( $data, [ 'attempt_id' => 1, 'quiz_id' => 1, 'quiz_title' => 'Sample Quiz', 'attempt_status' => $status[ $trigger ] ?? 'passed', 'score' => 8, 'total' => 10, 'total_marks' => 10, 'percentage' => 80.0 ] );
		}
		if ( 'user_enroll_course' === $trigger ) {
			$data['enroll_id'] = 1;
		}
		if ( in_array( $trigger, [ 'lesson_complete', 'lesson_incomplete', 'lesson_comment_added' ], true ) ) {
			$data['lesson_id'] = 1;
		}
		if ( in_array( $trigger, [ 'question_asked', 'question_answered', 'lesson_comment_added', 'course_review_added', 'course_review_updated' ], true ) ) {
			$data = array_merge( $data, [ 'comment_id' => 1, 'parent_id' => 0, 'content' => 'Sample comment' ] );
		}
		if ( in_array( $trigger, [ 'question_asked', 'question_answered' ], true ) ) {
			$data['question_title'] = 'Sample question';
		}
		if ( in_array( $trigger, [ 'course_review_added', 'course_review_updated' ], true ) ) {
			$data['rating'] = 5;
		}
		if ( 'course_published' === $trigger ) {
			$data['previous_status'] = 'draft';
		}
		return $data;
	}
}
