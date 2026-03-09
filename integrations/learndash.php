<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Learndash extends IntegrationBase {


	public static function get_slug(): string {
		return 'learndash';
	}

	public static function get_triggers(): array {
		return [
			'user_enroll_course' => [
				'label' => 'User Is Enrolled In A Course',
				'hook'  => 'learndash_update_course_access'
			],
			'course_complete' => [
				'label' => 'User Completed A Course',
				'hook'  => 'learndash_course_completed'
			],
			'lesson_complete' => [
				'label' => 'User Completed A Lesson',
				'hook'  => 'learndash_lesson_completed'
			],
			'topic_complete' => [
				'label' => 'User Completed A Topic',
				'hook'  => 'learndash_topic_completed'
			],
			'quiz_attempt' => [
				'label' => 'User Attempt A Quiz',
				'hook'  => 'learndash_quiz_submitted'
			],
			'added_group' => [
				'label' => 'User Added A Group',
				'hook'  => 'ld_added_group_access'
			],
			'removed_group' => [
				'label' => 'User Removed From A Group',
				'hook'  => 'ld_removed_group_access'
			],
			'lesson_assignment' => [
				'label' => 'User Submit An Assignment For A Lesson',
				'hook'  => 'learndash_assignment_uploaded'
			],
		];
	}

	private static function resolve_all_course_payload() {
		$all_course = [
			[
				'label' => 'Any course',
				'value' => 'any'
			],
		];

		if ( ! function_exists( 'LearnDash' ) ) {
			$courses = get_posts([
				'post_type'      => 'sfwd-courses',
				'post_status'    => 'publish',
				'orderby'        => 'post_title',
				'order'          => 'ASC',
				'posts_per_page' => -1,
			]);

			foreach ( $courses as $course ) {
				$all_course[] = [
					'label' => $course->post_title,
					'value' => $course->ID
				];
			}
		}

		return $all_course;
	}

	private static function resolve_all_lesson_payload() {
		$all_lesson = [
			[
				'label' => 'Any lesson',
				'value' => 'any'
			],
		];

		if ( ! function_exists( 'LearnDash' ) ) {
			$lessons = get_posts([
				'post_type'      => 'sfwd-lessons',
				'post_status'    => 'publish',
				'orderby'        => 'post_title',
				'order'          => 'ASC',
				'posts_per_page' => -1,
			]);

			foreach ( $lessons as $lesson ) {
				$all_lesson[] = [
					'label' => $lesson->post_title,
					'value' => $lesson->ID
				];
			}
		}

		return $all_lesson;
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( in_array( $trigger, [ 'user_enroll_course', 'course_complete' ], true ) ) {
			return [
				[
					'key'      => 'course_id',
					'label'    => 'Course',
					'type'     => 'select',
					'options'  => self::resolve_all_course_payload(),
					'required' => true,
				],
			];
		}

		if ( in_array( $trigger, [ 'lesson_complete', 'lesson_assignment' ], true ) ) {
			return [
				[
					'key'      => 'course_id',
					'label'    => 'Course',
					'type'     => 'select',
					'options'  => self::resolve_all_course_payload(),
					'required' => true,
				],
				[
					'key'      => 'lesson_id',
					'label'    => 'Lesson',
					'type'     => 'select',
					'options'  => self::resolve_all_lesson_payload(),
					'required' => true,
				],
			];
		}

		if ( $trigger === 'topic_complete' ) {
			$all_topic = [
				[
					'label' => 'Any Topic',
					'value' => 'any'
				],
			];

			if ( ! function_exists( 'LearnDash' ) ) {
				$topics = get_posts([
					'post_type'      => 'sfwd-topic',
					'post_status'    => 'publish',
					'orderby'        => 'post_title',
					'order'          => 'ASC',
					'posts_per_page' => -1,
				]);

				foreach ( $topics as $topic ) {
					$all_topic[] = [
						'label' => $topic->post_title,
						'value' => $topic->ID
					];
				}
			}

			return [
				[
					'key'      => 'course_id',
					'label'    => 'Course',
					'type'     => 'select',
					'options'  => self::resolve_all_course_payload(),
					'required' => true,
				],
				[
					'key'      => 'lesson_id',
					'label'    => 'Lesson',
					'type'     => 'select',
					'options'  => self::resolve_all_lesson_payload(),
					'required' => true,
				],
				[
					'key'      => 'topic_id',
					'label'    => 'Topic',
					'type'     => 'select',
					'options'  => $all_topic,
					'required' => true,
				],
			];
		}//end if

		if ( $trigger === 'quiz_attempt' ) {
			$all_quiz = [
				[
					'label' => 'Any Quiz',
					'value' => 'any'
				],
			];

			if ( ! function_exists( 'LearnDash' ) ) {
				$quizes = get_posts([
					'post_type'      => 'sfwd-quiz',
					'post_status'    => 'publish',
					'orderby'        => 'post_title',
					'order'          => 'ASC',
					'posts_per_page' => -1,
				]);

				foreach ( $quizes as $quiz ) {
					$all_quiz[] = [
						'label' => $quiz->post_title,
						'value' => $quiz->ID
					];
				}
			}

			return [
				[
					'key'      => 'quiz_id',
					'label'    => 'Quiz',
					'type'     => 'select',
					'options'  => $all_quiz,
					'required' => true,
				],
			];
		}//end if

		if ( in_array( $trigger, [ 'added_group', 'removed_group' ], true ) ) {
			$all_group = [
				[
					'label' => 'Any Group',
					'value' => 'any'
				],
			];

			if ( ! function_exists( 'LearnDash' ) ) {
				$groups = get_posts([
					'post_type'      => 'groups',
					'post_status'    => 'publish',
					'orderby'        => 'post_title',
					'order'          => 'ASC',
					'posts_per_page' => -1,
				]);

				foreach ( $groups as $group ) {
					$all_group[] = [
						'label' => $group->post_title,
						'value' => $group->ID
					];
				}
			}
			return [
				[
					'key'      => 'group_id',
					'label'    => 'Group',
					'type'     => 'select',
					'options'  => $all_group,
					'required' => true,
				],
			];
		}//end if

		return [];
	}

	private static function resolve_course_payload( int $user_id, $course_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}
		return [
			'course_id'    => (int) $course_id,
			'course_title' => get_the_title( $course_id ),
			'course_url'   => get_permalink( $course_id ),
			'user_id'      => (int) $user_id,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'nickname'     => $user->nickname,
			'display_name' => $user->display_name,
			'avatar_url'   => get_avatar_url( $user_id ),
			'user_roles'   => $user->roles,
			'completed_at' => current_time( 'mysql' ),
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'user_enroll_course':
				$user_id   = $args[0] ?? 0;
				$course_id = $args[1] ?? 0;
				$assess_list = $args[2] ?? 0;
				$remove    = $args[3] ?? null;

				if ( ! $user_id || ! $course_id ) {
					return false;
				}

				if ( ! empty( $remove ) ) {
					return false;
				}

				$selected_course = $node['data']['config']['course_id'] ?? 'any';

				if ( $selected_course !== 'any' && (int) $selected_course !== (int) $course_id ) {
					return false;
				}

				return [
					'success'   => true,
					'timestamp' => current_time( 'mysql' ),
					'data'      => self::resolve_course_payload( $user_id, $course_id ),
				];

			case 'course_complete':
				$data = $args[0] ?? [];

				if ( empty( $data['user'] ) || empty( $data['course'] ) ) {
					return false;
				}

				$user_id   = $data['user']->ID;
				$course_id = $data['course']->ID;
				$selected_course = $node['data']['config']['course_id'] ?? 'any';

				if ( $selected_course !== 'any' && (int) $selected_course !== (int) $course_id ) {
					return false;
				}

				return [
					'success'   => true,
					'timestamp' => current_time( 'mysql' ),
					'data' => self::resolve_course_payload( $user_id, $course_id ),
				];

			case 'lesson_complete':
				$lesson_id = $args[0] ?? null;
				$user_id   = $args[1] ?? get_current_user_id();

				if ( ! $lesson_id || ! $user_id ) {
					return false;
				}

				$selected_lesson = $node['data']['config']['lesson_id'] ?? 'any';

				if ( $selected_lesson !== 'any' && (int) $selected_lesson !== (int) $lesson_id ) {
					return false;
				}

				$lesson = get_post( $lesson_id );

				return [
					'success' => true,
					'timestamp' => current_time( 'mysql' ),
					'data' => [
						'lesson_id' => $lesson->ID,
						'lesson_title' => $lesson->post_title,
						'lesson_url' => get_permalink( $lesson->ID ),
						'user_id' => $user_id,
					]
				];

			case 'topic_complete':
				$data = $args[0] ?? null;

				if ( empty( $data ) || empty( $data['user'] ) || empty( $data['course'] ) || empty( $data['lesson'] ) || empty( $data['topic'] ) ) {
					return false;
				}

				$user   = $data['user'];
				$course = $data['course'];
				$lesson = $data['lesson'];
				$topic  = $data['topic'];
				$selected_topic = $node['data']['config']['topic_id'] ?? 'any';

				if ( $selected_topic !== 'any' && (int) $selected_topic !== (int) $topic->ID ) {
					return false;
				}

				return [
					'success'   => true,
					'timestamp' => current_time( 'mysql' ),
					'data' => [
						'course_id'    => $course->ID,
						'course_title' => $course->post_title,
						'course_url'   => get_permalink( $course->ID ),
						'lesson_id'    => $lesson->ID,
						'lesson_title' => $lesson->post_title,
						'lesson_url'   => get_permalink( $lesson->ID ),
						'topic_id'     => $topic->ID,
						'topic_title'  => $topic->post_title,
						'topic_url'    => get_permalink( $topic->ID ),
						'user_id'      => $user->ID,
						'user_email'   => $user->user_email,
						'display_name' => $user->display_name,
					],
				];

			case 'quiz_attempt':
				$data = $args[0] ?? null;
				$user = $args[1] ?? null;

				if ( empty( $data ) || empty( $user ) || empty( $user->ID ) ) {
					return false;
				}

				$course_id = $data['course'];
				$lesson_id = $data['lesson'];
				$quiz_id   = $data['quiz'];

				if ( ! $quiz_id ) {
					return false;
				}

				$course = $course_id ? get_post( (int) $course_id ) : null;
				$lesson = $lesson_id ? get_post( (int) $lesson_id ) : null;
				$quiz   = get_post( (int) $quiz_id );
				$selected_quiz = $node['data']['config']['quiz_id'] ?? 'any';

				if ( $selected_quiz !== 'any' && (int) $selected_quiz !== (int) $quiz->ID ) {
					return false;
				}

				$score        = $data['score'] ?? 0;
				$pass         = ( (bool) $data['pass'] ) ?? false;
				$total_points = $data['total_points'] ?? 0;
				$points       = $data['points'] ?? 0;
				$percentage   = $data['percentage'] ?? 0;

				return [
					'success'   => true,
					'timestamp' => current_time( 'mysql' ),
					'data' => [
						'course_id'    => $course->ID,
						'course_title' => $course->post_title,
						'course_url'   => get_permalink( $course->ID ),
						'lesson_id'    => $lesson->ID,
						'lesson_title' => $lesson->post_title,
						'lesson_url'   => get_permalink( $lesson->ID ),
						'quiz_id'      => $quiz->ID,
						'quiz_title'   => $quiz->post_title,
						'quiz_url'     => get_permalink( $quiz->ID ),
						'score'        => $score,
						'pass'         => $pass,
						'total_points' => $total_points,
						'points'       => $points,
						'percentage'   => $percentage,
						'user_id'      => $user->ID,
						'user_email'   => $user->user_email,
						'display_name' => $user->display_name,
					],
				];

			case 'added_group':
			case 'removed_group':
				$user_id  = $args[0] ?? 0;
				$group_id = $args[1] ?? 0;

				if ( ! $user_id || ! $group_id ) {
					return false;
				}

				$selected_group = $node['data']['config']['group_id'] ?? 'any';

				if ( $selected_group !== 'any' && (int) $selected_group !== (int) $group_id ) {
					return false;
				}

				$group = get_post( $group_id );

				if ( ! $group ) {
					return false;
				}

				$user = get_user_by( 'id', $user_id );

				if ( ! $user ) {
					return false;
				}

				return [
					'success'   => true,
					'timestamp' => current_time( 'mysql' ),
					'data' => [
						'group_id'     => $group->ID,
						'group_title'  => $group->post_title,
						'group_url'    => get_permalink( $group->ID ),
						'user_id'      => (int) $user_id,
						'first_name'   => $user->first_name,
						'last_name'    => $user->last_name,
						'user_login'   => $user->user_login,
						'user_email'   => $user->user_email,
						'nickname'     => $user->nickname,
						'display_name' => $user->display_name,
						'avatar_url'   => get_avatar_url( $user_id ),
						'user_roles'   => $user->roles,
						'completed_at' => current_time( 'mysql' ),
					],
				];

			case 'lesson_assignment':
				$assign    = $args[0] ?? 0;
				$assign_id = $args[1] ?? 0;

				if ( is_array( $assign ) ) {
					$assignment    = $assign;
					$assignment_id = $assign_id ?? 0;
				} else {
					$assignment_id = $assign ?? 0;
					$assignment    = is_array( $assign_id ) ? $assign_id : [];
				}

				if ( empty( $assignment ) || ! $assignment_id ) {
					return false;
				}
				if ( ! is_array( $assignment ) ) {
					return false;
				}

				$user_id   = $assignment['user_id'] ?? 0;
				$course_id = $assignment['course_id'] ?? 0;
				$lesson_id = $assignment['lesson_id'] ?? 0;

				if ( ! $user_id || ! $course_id || ! $lesson_id ) {
					return false;
				}

				$course = get_post( (int) $course_id );
				$lesson = get_post( (int) $lesson_id );
				$user   = get_user_by( 'id', $user_id );

				if ( ! $user || ! $course || ! $lesson ) {
					return false;
				}

				return [
					'success'   => true,
					'timestamp' => current_time( 'mysql' ),
					'data' => [
						'course_id'     => $course->ID,
						'course_title'  => $course->post_title,
						'course_url'    => get_permalink( $course->ID ),
						'lesson_id'     => $lesson->ID,
						'lesson_title'  => $lesson->post_title,
						'lesson_url'    => get_permalink( $lesson->ID ),
						'assignment_id' => $assignment_id,
						'file_name'     => $assignment['file_name'] ?? '',
						'file_link'     => $assignment['file_link'] ?? '',
						'file_path'     => $assignment['file_path'] ?? '',
						'user_id'       => (int) $user_id,
						'first_name'    => $user->first_name,
						'last_name'     => $user->last_name,
						'user_login'    => $user->user_login,
						'user_email'    => $user->user_email,
						'nickname'      => $user->nickname,
						'display_name'  => $user->display_name,
						'avatar_url'    => get_avatar_url( $user_id ),
						'user_roles'    => $user->roles,
						'completed_at'  => current_time( 'mysql' ),
					],
				];
		}//end switch
		return false;
	}
}
