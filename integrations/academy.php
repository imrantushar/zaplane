<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class Academy extends IntegrationBase {

    public static function get_slug(): string {
        return 'academy';
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
        if ( in_array( $trigger, ['user_enroll_course','course_complete'], true ) ) {
            $options = [ 
                ['label' => 'Any course', 'value' => 'any'],
            ];
            if ( ! function_exists( 'Academy' ) ) {
                $courses = get_posts([
                    'post_type'      => 'academy_courses',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                ]);
                
                foreach ( $courses as $course ) {
                    $options[] = [
                        'label' => $course->post_title,
                        'value' => $course->ID
                    ];
                }
            }
            return [
                [
                    'key'      => 'course_id',
                    'label'    => 'course',
                    'type'     => 'select',
                    'options'  => $options,
                    'required' => true,
                ],
            ];
        }

        if ( in_array( $trigger, ['academy_quiz_course_attempt'], true ) ) {
            $options = [ 
                ['label' => 'Any Quiz', 'value' => 'any'],
            ];
            if ( ! function_exists( 'Academy' ) ) {
                $quizzes = get_posts([
                    'post_type'      => 'academy_quiz',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                ]);
                
                foreach ( $quizzes as $quiz ) {
                    $options[] = [
                        'label' => $quiz->post_title,
                        'value' => $quiz->ID,
                    ];
                }
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

        if ( $trigger === 'quiz_target' ) {

            $options = [
                ['label' => 'Any Quiz', 'value' => 'any'],
            ];

            if ( ! function_exists( 'Academy' ) ) {
                $quizzes = get_posts([
                    'post_type'      => 'academy_quiz',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                ]);

                foreach ( $quizzes as $quiz ) {
                    $options[] = [
                        'label' => $quiz->post_title,
                        'value' => $quiz->ID,
                    ];
                }
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
                    'label'    => 'Target Percentage (%)',
                    'type'     => 'number',
                    'required' => true,
                ],
            ];
        }

        if ( in_array( $trigger, ['lesson_complete'], true ) ) {
            $options = [ 
                ['label' => 'Any lesson', 'value' => 'any'],
            ];
            if ( !function_exists( 'Academy' ) ) {
                global $wpdb;
                $table = $wpdb->prefix . 'academy_lessons';

                $lessons = $wpdb->get_results(
                    "SELECT ID, lesson_title
                    FROM {$table}
                    WHERE lesson_status = 'publish'
                    ORDER BY lesson_title ASC"
                );

                $options = [
                    ['label' => 'Any lesson', 'value' => 'any'],
                ];

                foreach ( $lessons as $lesson ) {
                    $options[] = [
                        'label' => $lesson->lesson_title,  // lesson_title use করতে হবে
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
    }

    public static function resolve_trigger( array $node, array $args ) {
        switch ( $node['event'] ) {
            case 'user_enroll_course':
                $course_id = $args[0] ?? null;
                $enroll_id = $args[1] ?? null;

                if ( ! $course_id || ! $enroll_id ) return false;

                $selected_course = $node['data']['config']['course_id'] ?? 'any';

                if ( $selected_course !== 'any' && (int)$selected_course !== (int)$course_id ) {
                    return false;
                }

                return [
                    'success'   => true,
                    'course_id' => $course_id,
                    'enroll_id' => $enroll_id,
                ];
                                
            case 'course_complete':
                $course_id = $args[0] ?? null;
                $user_id   = $args[1] ?? get_current_user_id();

                if ( ! $course_id || ! $user_id ) return false;

                $selected_course = $node['data']['config']['course_id'] ?? 'any';

                if ( $selected_course !== 'any' && (int)$selected_course !== (int)$course_id ) {
                    return false;
                }

                $course = get_post( $course_id );
                $user   = get_userdata( $user_id );

                if ( ! $course || ! $user ) return false;

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

                if ( ! $lesson_id || ! $user_id ) return false;

                $selected_lesson = $node['data']['config']['lesson_id'] ?? 'any';

                if ( $selected_lesson !== 'any' && (int)$selected_lesson !== (int)$lesson_id ) {
                    return false;
                }

                return [
                    'success'   => true,
                    'lesson_id' => $lesson_id,
                    'user_id'   => $user_id,
                ];

            case 'tutor_quiz_course_attempt':
                $attempt_id = $args[0] ?? null;
                if ( ! $attempt_id ) return false;

                $attempt = tutor_utils()->get_attempt( $attempt_id );
                if ( ! $attempt ) return false;

                $quiz_id = $attempt->quiz_id ?? null;
                $user_id = $attempt->user_id ?? null;

                if ( ! $quiz_id || ! $user_id ) return false;

                $selected_quiz = $node['data']['config']['quiz_id'] ?? 'any';

                if ( $selected_quiz !== 'any' && (int) $selected_quiz !== (int) $quiz_id ) {
                    return false;
                }

                return [
                    'success' => true,
                    'quiz_id' => $quiz_id,
                    'user_id' => $user_id,
                ];


            case 'quiz_target':
               $attempt_id = $args[0] ?? null;
                if ( ! $attempt_id ) return false;

                $attempt = tutor_utils()->get_attempt( $attempt_id );
                if ( ! $attempt ) return false;

                $quiz_id = $attempt->quiz_id ?? null;
                $user_id = $attempt->user_id ?? null;
                $earned  = $attempt->earned_marks ?? 0;
                $total   = $attempt->total_marks ?? 0;

                if ( ! $quiz_id || ! $user_id || ! $total ) return false;

                $selected_quiz = $node['data']['config']['quiz_id'] ?? 'any';

                if ( $selected_quiz !== 'any' && (int) $selected_quiz !== (int) $quiz_id ) {
                    return false;
                }

                $percentage = ( $earned / $total ) * 100;
                $target     = (float) ( $node['data']['config']['target_percentage'] ?? 0 );

                if ( $percentage < $target ) return false;

                return [
                    'success'     => true,
                    'quiz_id'     => $quiz_id,
                    'user_id'     => $user_id,
                    'score'       => $earned,
                    'total_marks' => $total,
                    'percentage'  => round( $percentage, 2 ),
                ];
        }
        return false;
    }

    public static function get_actions(): array {
        return [
        ];
    }

    public static function get_action_config_schema( string $action ): array {

        $schemas = [];

        return $schemas[$action] ?? [];
    }

    public static function execute_node( array $node, array $input ): array {

        $config = $node['data']['config'] ?? [];

        switch ( $node['data']['event'] ?? '' ) {
        }
        return ['port'=>'main','data'=>$input];
    }
}
