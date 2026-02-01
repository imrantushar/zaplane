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
                    'help'     => 'Leave empty for any course',
                ],
            ];
        }

        if ( $trigger === 'academy_quiz_course_attempt' || $trigger === 'course_complete' || $trigger === 'quiz_target' ) {

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

            $schema = [
                [
                    'key'      => 'course_id',
                    'label'    => 'Course',
                    'type'     => 'select',
                    'options'  => $options,
                    'required' => true,
                ],
            ];

            if ( $trigger === 'quiz_target' ) {
                $schema[] = [
                    'key'      => 'target_percentage',
                    'label'    => 'Target Percentage',
                    'type'     => 'number',
                    'required' => false,
                    'help'     => 'Trigger only if user score is equal or higher than this percentage',
                ];
            }

            return $schema;
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

        return [];
    }

    /**
     * Resolve trigger (FULL WORKABLE)
     * NOTE: Automation.php passes $trigger['graph_node']['data'] here
     * so $node structure is: ['app'=>..., 'event'=>..., 'config'=>...]
     */
    public static function resolve_trigger( array $node, array $args ) {

        $event  = $node['event'] ?? '';
        $config = $node['config'] ?? [];

        if ( empty($event) || !is_array($args) ) {
            return false;
        }

        switch ( $event ) {

            case 'user_enroll_course': {

                $course_id = (int) ($args[0] ?? 0);
                $enroll_id = (int) ($args[1] ?? 0);

                if ( ! $course_id ) return false;

                // user_id hook থেকে না এলে fallback
                $user_id = (int) get_current_user_id();

                if ( ! empty( $config['course_id'] ) && $config['course_id'] !== 'any' ) {
                    if ( (int) $config['course_id'] !== $course_id ) {
                        return false;
                    }
                }

                return [
                    'course_id' => $course_id,
                    'enroll_id' => $enroll_id,
                    'user_id'   => $user_id,
                ];
            }

            case 'academy_quiz_course_attempt': {

                $attempt = is_array( $args[0] ?? null ) ? $args[0] : [];
                if ( empty($attempt) ) return false;

                $course_id = (int) ($attempt['course_id'] ?? 0);
                $user_id   = (int) ($attempt['user_id'] ?? 0);
                $score     = (int) ($attempt['score'] ?? 0);

                if ( ! empty( $config['course_id'] ) && $config['course_id'] !== 'any' ) {
                    if ( (int) $config['course_id'] !== $course_id ) {
                        return false;
                    }
                }

                return [
                    'course_id' => $course_id,
                    'user_id'   => $user_id,
                    'score'     => $score,
                    'attempt'   => $attempt,
                ];
            }

            case 'lesson_complete': {

                // NOTE: hook accepted_args = 4, কিন্তু আমরা lesson_id/user_id শুধু নিই
                $lesson_id = (int) ($args[0] ?? 0);
                $user_id   = (int) ($args[1] ?? get_current_user_id());

                if ( ! $lesson_id ) return false;

                if ( ! empty( $config['lesson_id'] ) && $config['lesson_id'] !== 'any' ) {
                    if ( (int) $config['lesson_id'] !== $lesson_id ) {
                        return false;
                    }
                }

                return [
                    'lesson_id' => $lesson_id,
                    'user_id'   => $user_id,
                ];
            }

            case 'course_complete': {

                $course_id = (int) ($args[0] ?? 0);
                if ( ! $course_id ) return false;

                $user_id = (int) get_current_user_id();

                if ( ! empty( $config['course_id'] ) && $config['course_id'] !== 'any' ) {
                    if ( (int) $config['course_id'] !== $course_id ) {
                        return false;
                    }
                }

                return [
                    'course_id' => $course_id,
                    'user_id'   => $user_id,
                ];
            }

            case 'quiz_target': {

                $attempt = is_array( $args[0] ?? null ) ? $args[0] : [];
                if ( empty($attempt) ) return false;

                $course_id = (int) ($attempt['course_id'] ?? 0);
                $user_id   = (int) ($attempt['user_id'] ?? 0);
                $score     = (int) ($attempt['score'] ?? 0);

                if ( ! empty( $config['course_id'] ) && $config['course_id'] !== 'any' ) {
                    if ( (int) $config['course_id'] !== $course_id ) {
                        return false;
                    }
                }

                if ( ! empty( $config['target_percentage'] ) && $score < (int) $config['target_percentage'] ) {
                    return false;
                }

                return [
                    'course_id' => $course_id,
                    'user_id'   => $user_id,
                    'score'     => $score,
                    'attempt'   => $attempt,
                    'target_percentage' => (int) ($config['target_percentage'] ?? 0),
                ];
            }
        }

        return false;
    }

    /**
     * Register actions
     */
    public static function get_actions(): array {
        return [];
    }

    public static function get_action_config_schema( string $action ): array {
        return [];
    }

    /**
     * Execute node
     */
    public static function execute_node( array $node, array $input ): array {
        return [
            'port' => 'main',
            'data' => $input,
        ];
    }
}
