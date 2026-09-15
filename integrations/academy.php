<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

require_once __DIR__ . '/academy/events.php';
require_once __DIR__ . '/academy/actions.php';

/** Academy LMS automation. Existing event IDs and configuration keys are stable. */
class Academy extends IntegrationBase {
	use AcademyEvents;
	use AcademyActions;

	public static function get_slug(): string {
		return 'academy';
	}

	public static function get_name(): string {
		return 'Academy LMS';
	}

	public static function get_icon(): string {
		return 'academy.svg';
	}

	private static function config( array $node ): array {
		foreach ( [ $node['config'] ?? null, $node['data']['config'] ?? null, $node['flow_details'] ?? null ] as $config ) {
			if ( is_array( $config ) ) {
				return $config;
			}
		}
		return $node;
	}

	private static function event( array $node ): string {
		return (string) ( $node['event'] ?? $node['data']['event'] ?? '' );
	}

	private static function selection( string $key, string $label, string $query, bool $multiple = false ): array {
		return [
			'key' => $key, 'label' => $label, 'type' => 'select', 'required' => true,
			'multiple' => $multiple,
			'dynamic' => [ 'integration' => 'academy', 'query' => $query, 'select' => [ 'name', 'label' ] ],
		];
	}

	private static function user_data( int $user_id ): array {
		$user = get_userdata( $user_id );
		return [
			'user_id' => $user_id,
			'user_email' => $user ? $user->user_email : '',
			'first_name' => $user ? $user->first_name : '',
			'last_name' => $user ? $user->last_name : '',
			'username' => $user ? $user->user_login : '',
		];
	}

	private static function course_data( int $course_id ): array {
		$course = $course_id ? get_post( $course_id ) : null;
		return [
			'course_id' => $course_id,
			'course_title' => $course ? $course->post_title : '',
			'course_url' => $course ? get_permalink( $course_id ) : '',
		];
	}

	private static function matches( array $config, string $key, $actual ): bool {
		$selected = $config[ $key ] ?? 'any';
		return 'any' === $selected || '' === $selected || (string) $selected === (string) $actual;
	}

	public static function get_dynamic_queries(): array {
		return [
			'acourse'        => [ self::class, 'query_courses' ],
			'acourse_no_any' => [ self::class, 'query_courses_no_any' ],
			'quiz'           => [ self::class, 'query_quiz' ],
			'lesson'         => [ self::class, 'query_lesson' ],
			'lesson_no_any'  => [ self::class, 'query_lesson_no_any' ],
			'user'           => [ self::class, 'query_users' ],
		];
	}

	public static function query_courses(): array {
		$options = [
			[
				'label' => 'Any course',
				'name'  => 'any',
			],
		];

		if ( class_exists( '\\Academy\\Helper' ) ) {
			$courses = get_posts([
				'post_type'      => 'academy_courses',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			]);

			foreach ( $courses as $course ) {
				$options[] = [
					'label' => $course->post_title,
					'name'  => $course->ID,
				];
			}
		}

		return $options;
	}

	public static function query_courses_no_any(): array {
		$options = [];

		if ( class_exists( '\\Academy\\Helper' ) ) {
			$courses = get_posts([
				'post_type'      => 'academy_courses',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			]);

			foreach ( $courses as $course ) {
				$options[] = [
					'label' => $course->post_title,
					'name'  => $course->ID,
				];
			}
		}

		return $options;
	}

	public static function query_quiz(): array {
		$options = [
			[
				'label' => 'Any Quiz',
				'name'  => 'any',
			],
		];

		if ( class_exists( '\\Academy\\Helper' ) ) {
			$quizzes = get_posts([
				'post_type'      => 'academy_quiz',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			]);

			foreach ( $quizzes as $quiz ) {
				$options[] = [
					'label' => $quiz->post_title,
					'name'  => $quiz->ID,
				];
			}
		}

		return $options;
	}

	public static function query_lesson(): array {
		$options = [
			[
				'label' => 'Any lesson',
				'name'  => 'any',
			],
		];

		if ( class_exists( '\\Academy\\Lesson\\LessonApi\\Lesson' ) ) {
			$lessons = \Academy\Lesson\LessonApi\Lesson::get( 0, -1, 0, '', '', true );

			if ( ! empty( $lessons ) ) {
				foreach ( $lessons as $lesson ) {
					$lesson_data = (object) $lesson->get_data();
					$options[]   = [
						'label' => $lesson_data->lesson_title,
						'name'  => $lesson_data->ID,
					];
				}
			}
		}

		return $options;
	}

	public static function query_lesson_no_any(): array {
		$options = [];

		if ( class_exists( '\\Academy\\Lesson\\LessonApi\\Lesson' ) ) {
			$lessons = \Academy\Lesson\LessonApi\Lesson::get( 0, -1, 0, '', '', true );

			if ( ! empty( $lessons ) ) {
				foreach ( $lessons as $lesson ) {
					$lesson_data = (object) $lesson->get_data();
					$options[]   = [
						'label' => $lesson_data->lesson_title,
						'name'  => $lesson_data->ID,
					];
				}
			}
		}

		return $options;
	}

	public static function query_users(): array {
		$options = [];

		$users = get_users([
			'fields'  => [ 'ID', 'display_name', 'user_email' ],
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'number'  => 200,
		]);

		foreach ( $users as $user ) {
			$options[] = [
				'label' => $user->display_name . ' (' . $user->user_email . ')',
				'name'  => $user->ID,
			];
		}

		return $options;
	}
}
