<?php

namespace Zaplane\Integrations;

if (! defined('ABSPATH')) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class Lifter extends IntegrationBase
{

    public static function get_slug(): string
    {
        return 'lifter';
    }

    public static function get_name(): string
    {
        return 'Lifter LMS';
    }

    public static function get_icon(): string
    {
        return 'lifter.svg';
    }

    /**
     * Define triggers with hook names and accepted arguments count.
     * Your IntegrationBase must use 'accepted_args' when registering hooks.
     */
    public static function get_triggers(): array
    {
        return [
            'user_enroll_course' => [
                'label'         => 'User enrolled in a course',
                'hook'          => 'llms_user_enrolled_in_course',
            ],
            'lifter_quiz_course_attempt' => [
                'label'         => 'User attempted (submitted) a quiz',
                'hook'          => 'lifterlms_quiz_completed',
            ],
            'lesson_complete' => [
                'label'         => 'User completed a lesson',
                'hook'          => 'lifterlms_lesson_completed',
            ],
            'course_complete' => [
                'label'         => 'User completed a course',
                'hook'          => 'lifterlms_course_completed',
            ],
        ];
    }


    public static function get_trigger_accepted_args(string $trigger): int
    {
        $triggers = self::get_triggers();
        return $triggers[$trigger]['accepted_args'] ?? 1;
    }

    public static function get_trigger_config_schema(string $trigger): array
    {
        if (in_array($trigger, ['user_enroll_course', 'course_complete'], true)) {
            return [
                [
                    'key'      => 'course_id',
                    'label'    => 'Course',
                    'type'     => 'select',
                    'dynamic' => [
                            'integration' => 'lifter',
                            'query'       => 'acourse',
                            'select'      => ['name', 'label'],
                        ],
                    'required' => true,
                ],
            ];
        }

        if ($trigger === 'lifter_quiz_course_attempt') {
            return [
                [
                    'key'      => 'quiz_id',
                    'label'    => 'Quiz',
                    'type'     => 'select',
                    'dynamic' => [
                            'integration' => 'lifter',
                            'query'       => 'quiz',
                            'select'      => ['name', 'label'],
                        ],
                    'required' => true,
                ],
            ];
        }

        if ($trigger === 'lesson_complete') {
            return [
                [
                    'key'      => 'lesson_id',
                    'label'    => 'Lesson',
                    'type'     => 'select',
                    'dynamic' => [
                            'integration' => 'lifter',
                            'query'       => 'quiz',
                            'select'      => ['name', 'label'],
                        ],
                    'required' => true,
                ],
            ];
        }

        return [];
    }

    public static function resolve_trigger(array $node, array $args)
    {
        switch ($node['event']) {
            case 'user_enroll_course':

                $user_id    = $args[0] ?? null;
                $course_id  = $args[1] ?? null;

                if (! $user_id || ! $course_id) {
                    return false;
                }

                if (!in_array(get_post_type($course_id), ['course', 'llms_course'], true)) {
                    return false;
                }

                // Filter by selected course
                $selected_course = $node['data']['config']['course_id'] ?? 'any';
                if ($selected_course !== 'any' && (int) $selected_course !== (int) $course_id) {
                    return false;
                }

                $user   = get_userdata($user_id);
                $course = get_post($course_id);

                if (! $user || ! $course) {
                    return false;
                }

                return [
                    'success'      => true,
                    'user_id'      => (int) $user_id,
                    'course_id'    => (int) $course_id,
                    'course_title' => $course->post_title,
                    'course_url'   => get_permalink($course->ID),

                    // user data
                    'user_email'   => $user->user_email,
                    'first_name'   => $user->first_name,
                    'last_name'    => $user->last_name,
                    'display_name' => $user->display_name,
                ];
            case 'course_complete':
                $user_id   = $args[0] ?? null;
                $course_id = $args[1] ?? null;

                if (! $user_id || ! $course_id) {
                    return false;
                }

                $selected_course = $node['data']['config']['course_id'] ?? 'any';
                if ($selected_course !== 'any' && (int) $selected_course !== (int) $course_id) {
                    return false;
                }

                $course = get_post($course_id);
                $user   = get_userdata($user_id);

                if (! $course || ! $user) {
                    return false;
                }

                return [
                    'success'      => true,
                    'course_id'    => $course->ID,
                    'course_title' => $course->post_title,
                    'course_url'   => get_permalink($course->ID),
                    'user_id'      => (int) $user_id,
                    'user_email'   => $user->user_email,
                    'first_name'   => $user->first_name,
                    'last_name'    => $user->last_name,
                    'display_name' => $user->display_name,
                ];

            case 'lesson_complete':
                $user_id   = $args[0] ?? null;
                $lesson_id = $args[1] ?? null;

                if (! $user_id || ! $lesson_id) {
                    return false;
                }

                $selected_lesson = $node['data']['config']['lesson_id'] ?? 'any';
                if ($selected_lesson !== 'any' && (int) $selected_lesson !== (int) $lesson_id) {
                    return false;
                }

                $user = get_userdata($user_id);
                if (! $user) {
                    return false;
                }

                $lesson       = get_post($lesson_id);
                $lesson_title = $lesson ? $lesson->post_title : '';

                return [
                    'success'      => true,
                    'lesson_id'    => (int) $lesson_id,
                    'lesson_title' => $lesson_title,
                    'user_id'      => (int) $user_id,
                    'user_email'   => $user->user_email,
                    'first_name'   => $user->first_name,
                    'last_name'    => $user->last_name,
                    'display_name' => $user->display_name,
                ];

            case 'lifter_quiz_course_attempt':
                $user_id = $args[0] ?? null;
                $quiz_id = $args[1] ?? null;
                $attempt = $args[2] ?? null;

                if (! $user_id || ! $quiz_id || ! is_object($attempt)) {
                    return false;
                }

                $attempt_status = is_callable([$attempt, 'get_status'])
                    ? $attempt->get_status()
                    : ($attempt->attempt_status ?? null);

                if ($attempt_status === 'pending') {
                    return false;
                }

                $selected_quiz = $node['data']['config']['quiz_id'] ?? 'any';
                if ($selected_quiz !== 'any' && (int) $selected_quiz !== (int) $quiz_id) {
                    return false;
                }

                $user       = get_userdata($user_id);
                $quiz       = get_post($quiz_id);
                $quiz_title = $quiz ? $quiz->post_title : '';

                if (is_callable([$attempt, 'get_earned_percentage'])) {
                    $percentage = $attempt->get_earned_percentage();
                } else {
                    $total      = $attempt->total_marks ?? 0;
                    $earned     = $attempt->earned_marks ?? 0;
                    $percentage = ($total > 0) ? round(($earned / $total) * 100, 2) : 0;
                }

                return [
                    'success'      => true,
                    'quiz_id'      => (int) $quiz_id,
                    'quiz_title'   => $quiz_title,
                    'user_id'      => (int) $user_id,
                    'user_email'   => $user ? $user->user_email : '',
                    'first_name'   => $user ? $user->first_name : '',
                    'last_name'    => $user ? $user->last_name : '',
                    'display_name' => $user ? $user->display_name : '',
                    'score'        => $attempt->earned_marks ?? 0,
                    'total'        => $attempt->total_marks ?? 0,
                    'percentage'   => $percentage,
                ];
        }

        return false;
    }

    public static function get_actions(): array
    {
        return [];
    }

    public static function get_action_config_schema(string $action): array
    {
        return [];
    }

    public static function execute_node(array $node, array $input): array
    {
        return ['port' => 'main', 'data' => $input];
    }

    public static function get_dynamic_queries(): array
    {
        return [
            'acourse' => [self::class, 'query_courses'],
            'quiz' => [self::class, 'query_quiz'],
            'lesson' => [self::class, 'query_lesson'],
        ];
    }

    public static function query_courses()
    {
        $options = [['label' => 'Any course', 'value' => 'any']];

        global $wpdb;
        $courses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title FROM {$wpdb->posts}
                    WHERE {$wpdb->posts}.post_status = 'publish'
                    AND {$wpdb->posts}.post_type = 'course'
                    ORDER BY post_title"
            )
        );

        if (! empty($courses)) {
            foreach ($courses as $course) {
                $options[] = [
                    'label' => $course->post_title,
                    'value' => $course->ID
                ];
            }
        }
        return $options;
    }

    public static function query_quiz()
    {
        $options = [['label' => 'Any Quiz', 'value' => 'any']];

        global $wpdb;
        $quizzes = $wpdb->get_results(
            "SELECT ID, post_title
                FROM {$wpdb->posts}
                WHERE post_status = 'publish'
                AND post_type = 'llms_quiz'
                ORDER BY post_title ASC"
        );

        if (! empty($quizzes)) {
            foreach ($quizzes as $quiz) {
                $options[] = [
                    'label' => $quiz->post_title,
                    'value' => $quiz->ID,
                ];
            }
        }
        return $options;
    }
    public static function query_lesson()
    {
        $options = [['label' => 'Any lesson', 'value' => 'any']];

        global $wpdb;
        $lessons = $wpdb->get_results(
            "SELECT ID, post_title
                FROM {$wpdb->posts}
                WHERE post_status = 'publish'
                AND post_type = 'lesson'
                ORDER BY post_title ASC"
        );

        if (! empty($lessons)) {
            foreach ($lessons as $lesson) {
                $options[] = [
                    'label' => $lesson->post_title,
                    'value' => $lesson->ID,
                ];
            }
        }
        return $options;
    }
}
