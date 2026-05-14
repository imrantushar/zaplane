<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Academy extends IntegrationBase {

	public static function get_slug(): string {
		return 'academy';
	}

	public static function get_name(): string {
		return 'Academy LMS';
	}

	public static function get_icon(): string {
		return 'academy.svg';
	}

	public static function get_triggers(): array {
		return [
			'user_enroll_course' => [
				'label' => 'User enrolled in a course',
				'hook'  => 'academy/course/after_enroll'
			],
			'academy_quiz_course_attempt' => [
				'label' => 'User attempted (submitted) a quiz',
				'hook'  => 'academy_quizzes/api/after_quiz_attempt_finished'
			],
			'lesson_complete' => [
				'label' => 'User completed a lesson',
				'hook'  => 'academy/frontend/after_mark_topic_complete'
			],
			'course_complete' => [
				'label' => 'User completed a course',
				'hook'  => 'academy/admin/course_complete_after'
			],
			'quiz_target' => [
				'label' => 'User achieved target percentage on a quiz',
				'hook'  => 'academy_quizzes/api/after_quiz_attempt_finished'
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( in_array( $trigger, [ 'user_enroll_course', 'course_complete' ], true ) ) {
			return [
				[
					'key'      => 'course_id',
					'label'    => 'Course',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'academy',
						'query'       => 'acourse',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}

		if ( in_array( $trigger, [ 'academy_quiz_course_attempt' ], true ) ) {

			return [
				[
					'key'      => 'quiz_id',
					'label'    => 'Quiz',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'academy',
						'query'       => 'quiz',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}

		if ( 'quiz_target' === $trigger ) {
			return [
				[
					'key'      => 'quiz_id',
					'label'    => 'Quiz',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'academy',
						'query'       => 'quiz',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
				[
					'key'      => 'target_percentage',
					'label'    => 'Target Percentage (%)',
					'type'     => 'number',
					'required' => true,
				],
			];
		}//end if

		if ( in_array( $trigger, [ 'lesson_complete' ], true ) ) {

			return [
				[
					'key'      => 'lesson_id',
					'label'    => 'Lesson',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'academy',
						'query'       => 'lesson',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}

		return [];
	}


	public static function get_trigger_sample_output( string $trigger ): array {
		$samples = [
			'user_enroll_course'           => [
				'success'    => true,
				'course_id'  => 1,
				'enroll_id'  => 1,
				'user_id'    => 1,
				'user_email' => 'student@example.com',
				'first_name' => 'Jane',
				'last_name'  => 'Smith',
				'username'   => 'janesmith',
			],
			'course_complete'              => [
				'success'      => true,
				'course_id'    => 1,
				'course_title' => 'Sample Course',
				'course_url'   => 'https://example.com/course/sample-course',
				'user_id'      => 1,
				'user_email'   => 'student@example.com',
				'first_name'   => 'Jane',
				'last_name'    => 'Smith',
			],
			'lesson_complete'              => [
				'success'   => true,
				'lesson_id' => 1,
				'user_id'   => 1,
			],
			'academy_quiz_course_attempt'  => [
				'success' => true,
				'quiz_id' => 1,
				'user_id' => 1,
				'score'   => 8,
				'total'   => 10,
			],
			'quiz_target'                  => [
				'success'     => true,
				'quiz_id'     => 1,
				'user_id'     => 1,
				'score'       => 8,
				'total_marks' => 10,
				'percentage'  => 80.00,
			],
		];

		return $samples[ $trigger ] ?? [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'user_enroll_course':
				$course_id = $args[0] ?? null;
				$enroll_id = $args[1] ?? null;
				$user_id   = $args[2] ?? null;

				if ( ! $course_id || ! $enroll_id ) {
					return false;
				}

				$selected_course = $node['data']['config']['course_id'] ?? 'any';

				if ( 'any' !== $selected_course && (int) $selected_course !== (int) $course_id ) {
					return false;
				}

				$user = $user_id ? get_userdata( (int) $user_id ) : null;

				return [
					'success'    => true,
					'course_id'  => (int) $course_id,
					'enroll_id'  => (int) $enroll_id,
					'user_id'    => $user ? (int) $user->ID : null,
					'user_email' => $user ? $user->user_email : null,
					'first_name' => $user ? $user->first_name : null,
					'last_name'  => $user ? $user->last_name : null,
					'username'   => $user ? $user->user_login : null,
				];

			case 'course_complete':
				$course_id = $args[0] ?? null;
				$user_id   = $args[1] ?? get_current_user_id();

				if ( ! $course_id || ! $user_id ) {
					return false;
				}

				$selected_course = $node['data']['config']['course_id'] ?? 'any';

				if ( 'any' !== $selected_course && (int) $selected_course !== (int) $course_id ) {
					return false;
				}

				$course = get_post( $course_id );
				$user   = get_userdata( $user_id );

				if ( ! $course || ! $user ) {
					return false;
				}

				return [
					'success'      => true,
					'course_id'    => $course->ID,
					'course_title' => $course->post_title,
					'course_url'   => get_permalink( $course->ID ),
					'user_id'      => $user_id,
					'user_email'   => $user->user_email,
					'first_name'   => $user->first_name,
					'last_name'    => $user->last_name,
				];

			case 'lesson_complete':
				$lesson_id = $args[0] ?? null;
				$user_id   = $args[1] ?? get_current_user_id();

				if ( ! $lesson_id || ! $user_id ) {
					return false;
				}

				$selected_lesson = $node['data']['config']['lesson_id'] ?? 'any';

				if ( 'any' !== $selected_lesson && (int) $selected_lesson !== (int) $lesson_id ) {
					return false;
				}

				return [
					'success'   => true,
					'lesson_id' => $lesson_id,
					'user_id'   => $user_id,
				];

			case 'academy_quiz_course_attempt':
				$attempt = $args[0] ?? null;
				if ( ! $attempt ) {
					return false;
				}

				if ( 'pending' === $attempt->attempt_status ) {
					return false;
				}

				$quiz_id = $attempt->quiz_id ?? null;
				$user_id = $attempt->user_id ?? null;

				if ( ! $quiz_id || ! $user_id ) {
					return false;
				}

				$selected_quiz = $node['data']['config']['quiz_id'] ?? 'any';

				if ( 'any' !== $selected_quiz && (int) $selected_quiz !== (int) $quiz_id ) {
					return false;
				}

				return [
					'success'  => true,
					'quiz_id'  => $quiz_id,
					'user_id'  => $user_id,
					'score'    => $attempt->earned_marks ?? 0,
					'total'    => $attempt->total_marks ?? 0,
				];

			case 'quiz_target':
				$attempt = $args[0] ?? null;
				if ( ! $attempt ) {
					return false;
				}

				if ( 'pending' === $attempt->attempt_status ) {
					return false;
				}

				$quiz_id = $attempt->quiz_id ?? null;
				$user_id = $attempt->user_id ?? null;
				$earned  = $attempt->earned_marks ?? 0;
				$total   = $attempt->total_marks ?? 0;

				if ( ! $quiz_id || ! $user_id || ! $total ) {
					return false;
				}

				$selected_quiz = $node['data']['config']['quiz_id'] ?? 'any';

				if ( 'any' !== $selected_quiz && (int) $selected_quiz !== (int) $quiz_id ) {
					return false;
				}

				$percentage = ( $earned / $total ) * 100;
				$target     = (float) ( $node['data']['config']['target_percentage'] ?? 0 );

				if ( $percentage < $target ) {
					return false;
				}

				return [
					'success'     => true,
					'quiz_id'     => $quiz_id,
					'user_id'     => $user_id,
					'score'       => $earned,
					'total_marks' => $total,
					'percentage'  => round( $percentage, 2 ),
				];
		}//end switch
		return false;
	}

	public static function get_actions(): array {
		return [];
	}

	public static function get_action_config_schema( string $action ): array {

		$schemas = [];
		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		return [
			'port' => 'main',
			'data' => $input
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'acourse' => [ self::class, 'query_courses' ],
			'quiz' => [ self::class, 'query_quiz' ],
			'lesson' => [ self::class, 'query_lesson' ],
		];
	}

	public static function query_courses() {

			$options = [
				[
					'label' => 'Any course',
					'name' => 'any'
				],
			];

			if ( class_exists( 'Academy' ) ) {
				$courses = get_posts([
					'post_type'      => 'academy_courses',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
				]);

				foreach ( $courses as $course ) {
					$options[] = [
						'label' => $course->post_title,
						'name' => $course->ID
					];
				}
			}

			return $options;
	}

	public static function query_quiz() {

		$options = [
			[
				'label' => 'Any Quiz',
				'name' => 'any'
			],
		];
		if ( class_exists( 'Academy' ) ) {
			$quizzes = get_posts([
				'post_type'      => 'academy_quiz',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			]);

			foreach ( $quizzes as $quiz ) {
				$options[] = [
					'label' => $quiz->post_title,
					'name' => $quiz->ID,
				];
			}
		}

		return $options;
	}

	public static function query_lesson() {

		$options = [
			[
				'label' => 'Any lesson',
				'name'  => 'any'
			],
		];

		if ( class_exists( 'Academy' ) ) {

			$lessons = \Academy\Lesson\LessonApi\Lesson::get(
				0,
				-1,
				0,
				'',
				'',
				true
			);

			if ( ! empty( $lessons ) ) {

				foreach ( $lessons as $lesson ) {

					$lesson_data = (object) $lesson->get_data();

					$options[] = [
						'label' => $lesson_data->lesson_title,
						'name'  => $lesson_data->ID,
					];
				}
			}
		}

		return $options;
	}
}
