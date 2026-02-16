<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class Tutor extends IntegrationBase {

    /**
     * Integration slug
     */
    public static function get_slug(): string {
        return 'tutorlms';
    }

    /**
     * Register triggers
     */
    public static function get_triggers(): array {
        return [
            'user_enroll_course' => [
                'label' => 'User enrolled in a course',
                'hook'  => ['tutor_after_enroll', 10, 2], // ($courseId, $enrollmentId)
            ],
            'course_complete' => [
                'label' => 'User completed a course',
                'hook'  => ['tutor_course_complete_after', 10, 1], // ($courseId)
            ],
            'tutor_quiz_course_attempt' => [
                'label' => 'User attempted (submitted) a quiz',
                'hook'  => ['tutor_quiz/attempt_ended', 10, 1], // ($attemptId) OR (attempt array) depending implementation
            ],
            'lesson_complete' => [
                'label' => 'User completed a lesson',
                'hook'  => ['tutor_lesson_completed_after', 10, 4],
            ],
            'quiz_target' => [
                'label' => 'User achieved target percentage on a quiz',
                'hook'  => ['tutor_quiz/attempt_ended', 10, 1],
            ],
        ];
    }

    /**
     * Trigger config schema
     */
    public static function get_trigger_config_schema( string $trigger ): array {

        // Course-based triggers
        if ( $trigger === 'user_enroll_course' || $trigger === 'course_complete' ) {

            $options = [
                ['label' => 'Any Course', 'value' => 'any'],
            ];

            $courses = get_posts([
                'post_type'      => 'courses',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]);

            foreach ( $courses as $course ) {
                $options[] = [
                    'label' => $course->post_title,
                    'value' => (string) $course->ID,
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

        // Quiz Attempt trigger
        if ( $trigger === 'tutor_quiz_course_attempt' ) {

            $options = [
                ['label' => 'Any Quiz', 'value' => 'any'],
            ];

            $quizzes = get_posts([
                'post_type'      => 'tutor_quiz',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]);

            foreach ( $quizzes as $q ) {
                $options[] = [
                    'label' => $q->post_title,
                    'value' => (string) $q->ID,
                ];
            }

            return [
                [
                    'key'      => 'quiz_id',
                    'label'    => 'Quiz',
                    'type'     => 'select',
                    'options'  => $options,
                    'required' => true,
                ],
            ];
        }

        // Quiz Target trigger
        if ( $trigger === 'quiz_target' ) {

            $options = [
                ['label' => 'Any Quiz', 'value' => 'any'],
            ];

            $quizzes = get_posts([
                'post_type'      => 'tutor_quiz',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]);

            foreach ( $quizzes as $q ) {
                $options[] = [
                    'label' => $q->post_title,
                    'value' => (string) $q->ID,
                ];
            }

            return [
                [
                    'key'      => 'quiz_id',
                    'label'    => 'Quiz',
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

        // Lesson complete trigger
        if ( $trigger === 'lesson_complete' ) {

            $options = [
                ['label' => 'Any Lesson', 'value' => 'any'],
            ];

            $lessons = get_posts([
                'post_type'      => 'lesson',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'fields'         => 'ids',
            ]);

            foreach ( $lessons as $lesson_id ) {
                $options[] = [
                    'label' => get_the_title( $lesson_id ),
                    'value' => (string) $lesson_id,
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
     * Helper: normalize attempt (support attemptId OR array)
     */
    private static function normalize_attempt_from_args( array $args ): array {
        $first = $args[0] ?? null;

        // If it's already an array
        if ( is_array( $first ) ) {
            return $first;
        }

        // If it's an attempt id (numeric)
        if ( is_numeric( $first ) && function_exists('tutor_utils') ) {
            $attempt_id = (int) $first;

            try {
                $attempt_obj = tutor_utils()->get_attempt( $attempt_id );
            } catch ( \Throwable $e ) {
                return [];
            }

            if ( ! $attempt_obj ) return [];

            // Convert object to array safely
            $attempt_arr = [];
            foreach ( (array) $attempt_obj as $k => $v ) {
                $attempt_arr[$k] = $v;
            }

            // Try to add attempt_id for reference
            if ( empty($attempt_arr['attempt_id']) ) {
                $attempt_arr['attempt_id'] = $attempt_id;
            }

            // Try to map quiz_id -> quizId and score -> percentage if possible
            // (Keeping raw values; your pipeline can use them.)
            return $attempt_arr;
        }

        return [];
    }

    /**
     * Resolve trigger
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

                if ( ! $course_id || ! $enroll_id ) return false;

                // BitApps style: enrollment post author is student/user
                $student_id = (int) get_post_field('post_author', $enroll_id);
                $user_id    = $student_id ?: (int) get_current_user_id();

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

            case 'course_complete': {

                $course_id = (int) ($args[0] ?? 0);
                if ( ! $course_id ) return false;

                $user_id = (int) get_current_user_id(); // TutorLMS hook usually for current user; keep fallback

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

            case 'tutor_quiz_course_attempt': {

                $attempt = self::normalize_attempt_from_args( $args );
                error_log(print_r($attempt,true));

                if ( empty($attempt) ) return false;

                // quiz id could be stored as quiz_id
                $quiz_id = (int) ($attempt['quiz_id'] ?? 0);

                // If your system stores quiz as 'quiz' CPT, quiz_id should be valid post id
                if ( ! $quiz_id ) {
                    // Sometimes attempt has 'quizId' or similar keys
                    $quiz_id = (int) ($attempt['quizId'] ?? 0);
                }

                $user_id = (int) ($attempt['user_id'] ?? 0);
                if ( ! $user_id ) {
                    $user_id = (int) ($attempt['userId'] ?? 0);
                }

                // Score can vary by TutorLMS version. Keep raw.
                $score = $attempt['earned_marks'] ?? ($attempt['score'] ?? 0);

                if ( ! empty( $config['quiz_id'] ) && $config['quiz_id'] !== 'any' ) {
                    if ( (int) $config['quiz_id'] !== $quiz_id ) {
                        return false;
                    }
                }

                return [
                    'quiz_id'  => $quiz_id,
                    'user_id'  => $user_id ?: (int) get_current_user_id(),
                    'score'    => is_numeric($score) ? (float) $score : $score,
                    'attempt'  => $attempt,
                ];
            }

            case 'lesson_complete': {

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
                    'user_id'   => $user_id ?: (int) get_current_user_id(),
                ];
            }

            case 'quiz_target': {

                $attempt = self::normalize_attempt_from_args( $args );
                if ( empty($attempt) ) return false;

                $quiz_id = (int) ($attempt['quiz_id'] ?? 0);
                if ( ! $quiz_id ) {
                    $quiz_id = (int) ($attempt['quizId'] ?? 0);
                }

                $user_id = (int) ($attempt['user_id'] ?? 0);
                if ( ! $user_id ) {
                    $user_id = (int) ($attempt['userId'] ?? 0);
                }

                // Target check: prefer percentage if available, else derive from earned/total marks
                $percentage = null;

                if ( isset($attempt['earned_marks'], $attempt['total_marks']) && is_numeric($attempt['earned_marks']) && is_numeric($attempt['total_marks']) && (float)$attempt['total_marks'] > 0 ) {
                    $percentage = ((float)$attempt['earned_marks'] / (float)$attempt['total_marks']) * 100.0;
                } elseif ( isset($attempt['score']) && is_numeric($attempt['score']) ) {
                    // Some versions store score as percentage
                    $percentage = (float) $attempt['score'];
                }

                if ( ! empty( $config['quiz_id'] ) && $config['quiz_id'] !== 'any' ) {
                    if ( (int) $config['quiz_id'] !== $quiz_id ) {
                        return false;
                    }
                }

                $target = isset($config['target_percentage']) && $config['target_percentage'] !== ''
                    ? (float) $config['target_percentage']
                    : null;

                if ( $target !== null && $percentage !== null && $percentage < $target ) {
                    return false;
                }

                return [
                    'quiz_id'            => $quiz_id,
                    'user_id'            => $user_id ?: (int) get_current_user_id(),
                    'percentage'         => $percentage,
                    'attempt'            => $attempt,
                    'target_percentage'  => $target !== null ? (float)$target : null,
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
