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
                'hook'  => 'academy/course/after_enroll',
            ],
            'academy_quiz_course_attempt' => [
                'label' => 'User attempted (submitted) a quiz',
                'hook'  => 'academy_quizzes/api/after_quiz_attempt_finished',
            ],
            'lesson_complete' => [
                'label' => 'User completed a lesson',
                'hook'  => 'academy/frontend/after_mark_topic_complete',
            ],
            'course_complete' => [
                'label' => 'User completed a course',
                'hook'  => 'academy/admin/course_complete_after',
            ],
            'quiz_target' => [
                'label' => 'User achieved target percentage on a quiz',
                'hook'  => 'academy_quizzes/api/after_quiz_attempt_finished',
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


    public static function resolve_trigger( array $node, array $args ) {

        // 1) Normalize event key (some builders store it as "trigger")
        $event = $node['event'] ?? ($node['trigger'] ?? '');

        // 2) Sometimes UI sends event as array like ['value'=>'user_enroll_course', ...]
        if (is_array($event)) {
            $event = $event['value'] ?? ($event['key'] ?? ($event['slug'] ?? ''));
        }

        $event  = is_string($event) ? trim($event) : '';
        $config = is_array($node['config'] ?? null) ? ($node['config'] ?? []) : [];

        // args always array
        $args = is_array($args) ? $args : [];

        if (empty($event)) {
            return false;
        }

        switch ( $event ) {

            case 'user_enroll_course': {
                $course_id = (int) ($args[0] ?? 0);
                $enroll_id = (int) ($args[1] ?? 0);

                if ( ! $course_id ) return false;

                // current user sometimes 0 (cron/admin)
                $user_id = (int) get_current_user_id();
                if (!$user_id && !empty($args[2])) {
                    $user_id = (int) $args[2];
                }

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
                $payload = $args[0] ?? null;

                // attempt can be array OR attemptId (int)
                $attempt = is_array($payload) ? $payload : [];
                $attempt_id = is_numeric($payload) ? (int) $payload : 0;

                // If you only get attemptId and you can't fetch full attempt,
                // still return something usable (workflow can use attempt_id)
                if (empty($attempt) && $attempt_id) {
                    return [
                        'attempt_id' => $attempt_id,
                    ];
                }

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
                // Hook: academy/frontend/after_mark_topic_complete (10, 4)
                // Different versions can send args in different order.
                // We'll detect the most likely user_id as last arg if numeric.
                $a0 = $args[0] ?? 0;
                $a1 = $args[1] ?? 0;
                $a2 = $args[2] ?? 0;
                $a3 = $args[3] ?? 0;

                // user_id usually last
                $user_id = is_numeric($a3) ? (int)$a3 : (is_numeric($a1) ? (int)$a1 : (int)get_current_user_id());
                if (!$user_id) $user_id = (int)get_current_user_id();

                // lesson_id often comes as 3rd arg, but fallback to first
                $lesson_id = is_numeric($a2) ? (int)$a2 : (int)$a0;

                if ( ! $lesson_id ) return false;

                if ( ! empty( $config['lesson_id'] ) && $config['lesson_id'] !== 'any' ) {
                    if ( (int) $config['lesson_id'] !== $lesson_id ) {
                        return false;
                    }
                }

                return [
                    'lesson_id' => $lesson_id,
                    'user_id'   => $user_id,
                    'raw_args'  => $args, // debug friendly
                ];
            }

            case 'course_complete': {
                $course_id = (int) ($args[0] ?? 0);
                if ( ! $course_id ) return false;

                $user_id = (int) get_current_user_id();
                if (!$user_id && !empty($args[1])) {
                    $user_id = (int) $args[1];
                }

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
                $payload = $args[0] ?? null;

                $attempt = is_array($payload) ? $payload : [];
                $attempt_id = is_numeric($payload) ? (int) $payload : 0;

                // attemptId only -> can't calculate score/target unless you fetch attempt
                if (empty($attempt) && $attempt_id) {
                    return [
                        'attempt_id' => $attempt_id,
                        'target_percentage' => (int) ($config['target_percentage'] ?? 0),
                    ];
                }

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
                    'course_id'          => $course_id,
                    'user_id'            => $user_id,
                    'score'              => $score,
                    'attempt'            => $attempt,
                    'target_percentage'  => (int) ($config['target_percentage'] ?? 0),
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
