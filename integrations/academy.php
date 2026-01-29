<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class Academy extends IntegrationBase {

    /**
     * Integration slug
     */
    public static function get_slug(): string {
        return 'academy';
    }

    /**
     * Register triggers
     */
    public static function get_triggers(): array {
        return [
            'user_enroll_course' => [
                'label' => 'User enrolled in a course',
                'hook'  => ['academy/course/after_enroll', 10, 2],
            ],
            'academy_quiz_course_attempt' => [
                'label' => 'User attempted (submitted) a quiz',
                'hook'  => ['academy_quizzes/api/after_quiz_attempt_finished', 10, 1],
            ],
            'lesson_complete' => [
                'label' => 'User completed a lesson',
                'hook'  => ['academy/frontend/after_mark_topic_complete', 10, 4],
            ],
            'course_complete' => [
                'label' => 'User completed a course',
                'hook'  => ['academy/admin/course_complete_after', 10, 1],
            ],
            'quiz_target' => [
                'label' => 'User achieved target percentage on a quiz',
                'hook'  => ['academy_quizzes/api/after_quiz_attempt_finished', 10, 1],
            ],
        ];
    }

    /**
     * Trigger config schema
     */
    public static function get_trigger_config_schema( string $trigger ): array {

        if ( $trigger === 'user_enroll_course' ) {
            return [
                [
                    'key'      => 'course_id',
                    'label'    => 'Course ID',
                    'type'     => 'number',
                    'required' => false,
                ],
            ];
        }

        if ( $trigger === 'academy_quiz_course_attempt' ) {

            $options = [
                ['label' => 'Any Course', 'value' => 'any'],
            ];

            $courses = get_posts([
                'post_type'      => 'academy_courses',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]);

            foreach ( $courses as $course ) {
                $options[] = [
                    'label' => $course->post_title,
                    'value' => $course->ID,
                ];
            }

            return [
                [
                    'key'      => 'course_id',
                    'label'    => 'Course',
                    'type'     => 'select',
                    'options'  => $options,
                    'required' => true,
                ],
            ];
        }

        if ( $trigger === 'lesson_complete' ) {

            global $wpdb;

            $table = $wpdb->prefix . 'academy_lessons';

            $options = [
                ['label' => 'Any Lesson', 'value' => 'any'],
            ];

            $lessons = $wpdb->get_results(
                "SELECT ID, lesson_title 
                 FROM {$table}
                 WHERE lesson_status = 'publish'
                 ORDER BY lesson_title ASC"
            );

            foreach ( $lessons as $lesson ) {
                $options[] = [
                    'label' => $lesson->lesson_title,
                    'value' => $lesson->ID,
                ];
            }

            return [
                [
                    'key'      => 'lesson_id',
                    'label'    => 'Lesson',
                    'type'     => 'select',
                    'options'  => $options,
                    'required' => true,
                ],
            ];
        }

        if ( $trigger === 'course_complete' ) {

            $options = [
                ['label' => 'Any Course', 'value' => 'any'],
            ];

            $courses = get_posts([
                'post_type'      => 'academy_courses',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]);

            foreach ( $courses as $course ) {
                $options[] = [
                    'label' => $course->post_title,
                    'value' => $course->ID,
                ];
            }

            return [
                [
                    'key'      => 'course_id',
                    'label'    => 'Course',
                    'type'     => 'select',
                    'options'  => $options,
                    'required' => true,
                ],
            ];
        }

        if ( $trigger === 'quiz_target' ) {

            $options = [
                ['label' => 'Any Course', 'value' => 'any'],
            ];

            $courses = get_posts([
                'post_type'      => 'academy_courses',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]);

            foreach ( $courses as $course ) {
                $options[] = [
                    'label' => $course->post_title,
                    'value' => $course->ID,
                ];
            }

            return [
                [
                    'key'      => 'course_id',
                    'label'    => 'Course',
                    'type'     => 'select',
                    'options'  => $options,
                    'required' => true,
                ],
                [
                    'key'      => 'target_percentage',
                    'label'    => 'Target Percentage',
                    'type'     => 'number',
                    'required' => false,
                    'help'     => 'Trigger only if user score is equal or higher than this percentage',
                ],
            ];
        }

        return [];
    }

    /**
     * Resolve trigger
     */
    public static function resolve_trigger( array $node, array $args ) {

        if ( empty( $node['event'] ) || ! is_array( $args ) ) {
            return false;
        }

        $config = $node['data']['config'] ?? [];

        switch ( $node['event'] ) {

            case 'user_enroll_course':

                $course_id = $args[0] ?? 0;
                $enroll_id = $args[1] ?? 0;
                $user_id   = get_current_user_id();

                if ( ! empty( $config['course_id'] ) && $config['course_id'] !== 'any' ) {
                    if ( (int) $config['course_id'] !== (int) $course_id ) {
                        return false;
                    }
                }

                return [
                    'course_id' => $course_id,
                    'enroll_id' => $enroll_id,
                    'user_id'   => $user_id,
                ];

            case 'academy_quiz_course_attempt':

                $attempt = is_array( $args[0] ?? null ) ? $args[0] : [];
                if ( empty( $attempt ) ) return false;

                $course_id = $attempt['course_id'] ?? 0;
                $user_id   = $attempt['user_id'] ?? 0;
                $score     = $attempt['score'] ?? 0;

                if ( ! empty( $config['course_id'] ) && $config['course_id'] !== 'any' ) {
                    if ( (int) $config['course_id'] !== (int) $course_id ) {
                        return false;
                    }
                }

                return compact( 'course_id', 'user_id', 'score', 'attempt' );

            case 'lesson_complete':

                $lesson_id = $args[0] ?? 0;
                $user_id   = $args[1] ?? get_current_user_id();

                if ( ! empty( $config['lesson_id'] ) && $config['lesson_id'] !== 'any' ) {
                    if ( (int) $config['lesson_id'] !== (int) $lesson_id ) {
                        return false;
                    }
                }

                return compact( 'lesson_id', 'user_id' );

            case 'course_complete':

                $course_id = $args[0] ?? 0;
                $user_id   = get_current_user_id();

                if ( ! empty( $config['course_id'] ) && $config['course_id'] !== 'any' ) {
                    if ( (int) $config['course_id'] !== (int) $course_id ) {
                        return false;
                    }
                }

                return compact( 'course_id', 'user_id' );

            case 'quiz_target':

                $attempt = is_array( $args[0] ?? null ) ? $args[0] : [];
                if ( empty( $attempt ) ) return false;

                $course_id = $attempt['course_id'] ?? 0;
                $user_id   = $attempt['user_id'] ?? 0;
                $score     = $attempt['score'] ?? 0;

                if ( ! empty( $config['course_id'] ) && $config['course_id'] !== 'any' ) {
                    if ( (int) $config['course_id'] !== (int) $course_id ) {
                        return false;
                    }
                }

                if ( ! empty( $config['target_percentage'] ) && $score < (int) $config['target_percentage'] ) {
                    return false;
                }

                return compact( 'course_id', 'user_id', 'score', 'attempt' );
        }

        return false;
    }

    /**
     * Register actions
     */
    public static function get_actions(): array {
        return [];
    }

    /**
     * Action config schema
     */
    public static function get_action_config_schema( string $action ): array {
        return [];
    }

    /**
     * Execute action
     */
    public static function execute_node( array $node, array $input ): array {
        return [
            'port' => 'main',
            'data' => $input,
        ];
    }
}
