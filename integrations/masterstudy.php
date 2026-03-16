<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Masterstudy extends IntegrationBase {

	public static function get_slug(): string {
		return 'masterstudy';
	}

	public static function get_name(): string {
		return 'MasterStudy LMS';
	}

	public static function get_icon(): string {
		return '';
	}

	public static function get_triggers(): array {
		return [
			'user_enroll_course' => [
				'label' => 'User Is Enrolled In A Course',
				'hook'  => 'add_user_course'
			],
			'course_complete' => [
				'label' => 'User Completed A Course',
				'hook'  => 'stm_lms_progress_updated'
			],
			'lesson_complete' => [
				'label' => 'User Completed A Lesson',
				'hook'  => 'stm_lms_lesson_passed'
			],
			'quiz_passed' => [
				'label' => 'Quiz Passed',
				'hook'  => 'stm_lms_quiz_passed'
			],
			'quiz_failed' => [
				'label' => 'Quiz Failed',
				'hook'  => 'stm_lms_quiz_failed'
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
						'integration' => 'masterstudy',
						'query'       => 'course_query',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}

		if ( 'lesson_complete' === $trigger ) {
			return [
				[
					'key'      => 'course_id',
					'label'    => 'Course',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'masterstudy',
						'query'       => 'course_query',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
				[
					'key'      => 'lesson_id',
					'label'    => 'Lesson',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'masterstudy',
						'query'       => 'lesson_query',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}//end if

		if ( in_array( $trigger, [ 'quiz_passed', 'quiz_failed' ], true ) ) {
			return [
				[
					'key'      => 'quiz_id',
					'label'    => 'Quiz',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'masterstudy',
						'query'       => 'quiz_query',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}

		return [];
	}

	private static function resolve_user_payload( int $user_id, array $extra = [] ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}
		$data = [
			'user_id'            => (int) $user_id,
			'first_name'         => $user->first_name,
			'last_name'          => $user->last_name,
			'user_login'         => $user->user_login,
			'user_email'         => $user->user_email,
			'nickname'           => $user->nickname,
			'display_name'       => $user->display_name,
			'avatar_url'         => get_avatar_url( $user_id ),
			'user_roles'         => $user->roles,
			'completed_at'       => current_time( 'mysql' ),
		];
		return array_merge( $data, $extra );
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {

			case 'user_enroll_course':
			case 'course_complete':
				$user_id   = $args[0] ?? 0;
				$course_id = $args[1] ?? 0;

				if ( ! $user_id || ! $course_id ) {
					return false;
				}

				$selected_course = $node['data']['config']['course_id'] ?? 'any';

				if ( 'any' !== $selected_course && (int) $selected_course !== (int) $course_id ) {
					return false;
				}

				$course = get_post( $course_id );

				if ( ! $course ) {
					return false;
				}

				$course_data = [
					'course_id'          => $course->ID,
					'course_title'       => $course->post_title,
					'course_description' => $course->post_content,
					'course_url'         => get_permalink( $course_id ),
				];

				return [
					'success'   => true,
					'timestamp' => current_time( 'mysql' ),
					'data' => self::resolve_user_payload( $user_id, $course_data ),
				];

			case 'lesson_complete':
				$user_id   = $args[0] ?? 0;
				$lesson_id = $args[1] ?? 0;

				if ( ! $lesson_id || ! $user_id ) {
					return false;
				}

				$selected_lesson = $node['data']['config']['lesson_id'] ?? 'any';

				if ( 'any' !== $selected_lesson && (int) $selected_lesson !== (int) $lesson_id ) {
					return false;
				}

				$lesson = get_post( $lesson_id );

				if ( ! $lesson ) {
					return false;
				}

				$lesson_data = [
					'lesson_id'          => $lesson->ID,
					'lesson_title'       => $lesson->post_title,
					'lesson_description' => $lesson->post_content,
					'lesson_url'         => get_permalink( $lesson_id ),
				];

				return [
					'success'   => true,
					'timestamp' => current_time( 'mysql' ),
					'data' => self::resolve_user_payload( $user_id, $lesson_data ),
				];

			case 'quiz_passed':
			case 'quiz_failed':
				$user_id    = $args[0] ?? 0;
				$quiz_id    = $args[1] ?? 0;
				$percentage = $args[2] ?? 0;

				if ( ! $user_id || ! $quiz_id ) {
					return false;
				}

				$selected_quiz = $node['data']['config']['quiz_id'] ?? 'any';

				if ( 'any' !== $selected_quiz && (int) $selected_quiz !== (int) $quiz_id ) {
					return false;
				}

				$quiz = get_post( $quiz_id );

				if ( ! $quiz ) {
					return false;
				}

				$quiz_data = [
					'quiz_id'          => $quiz->ID,
					'quiz_title'       => $quiz->post_title,
					'quiz_description' => $quiz->post_content,
					'quiz_url'         => get_permalink( $quiz_id ),
					'score'            => $percentage,
					'status'           => 'quiz_passed' ? 'passed' : 'failed' === $node['event'],
				];

				return [
					'success'   => true,
					'timestamp' => current_time( 'mysql' ),
					'data' => self::resolve_user_payload( $user_id, $quiz_data ),
				];
		}//end switch
		return false;
	}

	public static function get_dynamic_queries(): array {
		return [
			'course_query' => [ self::class, 'course_query_types' ],
			'lesson_query' => [ self::class, 'lesson_query_types' ],
			'quiz_query'   => [ self::class, 'quiz_query_types' ],
		];
	}

	public static function course_query_types( $q ) {
		$all_course = [
			[
				'label' => 'Any course',
				'name' => 'any'
			],
		];

		if ( ! function_exists( 'MasterStudy' ) ) {
			$courses = get_posts([
				'post_type'      => 'stm-courses',
				'post_status'    => 'publish',
				'orderby'        => 'post_title',
				'order'          => 'ASC',
				'posts_per_page' => -1,
			]);

			foreach ( $courses as $course ) {
				$all_course[] = [
					'label' => $course->post_title,
					'name' => $course->ID
				];
			}
		}

		return $all_course;
	}

	public static function lesson_query_types( $q ) {
		$all_lesson = [
			[
				'label' => 'Any lesson',
				'name' => 'any'
			],
		];

		if ( ! function_exists( 'MasterStudy' ) ) {
			$lessons = get_posts([
				'post_type'      => 'stm-lessons',
				'post_status'    => 'publish',
				'orderby'        => 'post_title',
				'order'          => 'ASC',
				'posts_per_page' => -1,
			]);

			foreach ( $lessons as $lesson ) {
				$all_lesson[] = [
					'label' => $lesson->post_title,
					'name' => $lesson->ID
				];
			}
		}

		return $all_lesson;
	}

	public static function quiz_query_types( $q ) {
		global $wpdb;
		$course_id = $q['course_id'] ?? 'any';
		$all_quiz = [
			[
				'label' => 'Any Quiz',
				'name' => 'any'
			],
		];

		if ( 'any' === $course_id ) {

			$quizzes = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title 
                    FROM {$wpdb->posts}
                    WHERE post_type = %s 
                    AND post_status = 'publish'
                    ORDER BY post_title ASC",
					'stm-quizzes'
				)
			);
		} else {
			$quizzes = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->prefix}stm_lms_curriculum_materials cm 
                        ON p.ID = cm.post_id
                    INNER JOIN {$wpdb->prefix}stm_lms_curriculum_sections cs 
                        ON cm.section_id = cs.id
                    WHERE p.post_type = %s
                    AND p.post_status = 'publish'
                    AND cs.course_id = %d
                    ORDER BY p.post_title ASC",
					'stm-quizzes',
					(int) $course_id
				)
			);
		}//end if

		if ( $quizzes ) {
			foreach ( $quizzes as $quiz ) {
				$all_quiz[] = [
					'label' => $quiz->post_title,
					'name' => $quiz->ID
				];
			}
		}

		return $all_quiz;
	}
}
