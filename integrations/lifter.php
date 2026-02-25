<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class Lifter extends IntegrationBase {

    public static function get_slug(): string {
        return 'lifter';
    }

    public static function get_triggers(): array {
        return [
            'user_enroll_course' => [
                'label' => 'User enrolled in a course', 
                'hook'  => 'llms_user_enrolled_in_course'
            ],
            'lifter_quiz_course_attempt' => [
                'label' => 'User attempted (submitted) a quiz', 
                'hook'  => 'lifterlms_quiz_completed'
            ],
            'lesson_complete' => [
                'label' => 'User completed a lesson', 
                'hook'  => 'lifterlms_lesson_completed'
            ],
            'course_complete' => [
                'label' => 'User completed a course', 
                'hook'  => 'lifterlms_course_completed'
            ],
        ]; 
    }

    public static function get_trigger_config_schema( string $trigger ): array {
        if ( in_array( $trigger, ['user_enroll_course','course_complete'], true ) ) {
            $options = [ 
                ['label' => 'Any course', 'value' => 'any'],
            ];

                global $wpdb;

                $courses =  $wpdb->get_results(
                    "SELECT ID, post_title 
                    FROM {$wpdb->posts}
                    WHERE post_status = 'publish'
                    AND post_type = 'course'"
                );

                
                foreach ( $courses as $course ) {
                    $options[] = [
                        'label' => $course->post_title,
                        'value' => $course->ID
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

        if ( in_array( $trigger, ['lifter_quiz_course_attempt'], true ) ) {
        $options = [
                ['label' => 'Any Quiz', 'value' => 'any'],
            ];

            global $wpdb;

            $quizzes = $wpdb->get_results(
                "SELECT ID, post_title
                FROM {$wpdb->posts}
                WHERE post_status = 'publish'
                AND post_type = 'llms_quiz'
                ORDER BY post_title ASC"
            );

            if ( ! empty( $quizzes ) ) {
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

        if ( in_array( $trigger, ['lesson_complete'], true ) ) {
            $options = [ 
                ['label' => 'Any lesson', 'value' => 'any'],
            ];

                global $wpdb;

                $lessons = $wpdb->get_results(
                    "SELECT ID, post_title 
                    FROM {$wpdb->posts}
                    WHERE post_status = 'publish'
                    AND post_type = 'lesson'
                    ORDER BY post_title ASC"
                );

                foreach ( $lessons as $lesson ) {
                    $options[] = [
                        'label' => $lesson->post_title,
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

           case 'lifter_quiz_course_attempt':

                $attempt = $args[0] ?? null;
                if ( ! $attempt ) return false;

                if ( $attempt->attempt_status === 'pending' ) return false;

                $quiz_id = $attempt->quiz_id ?? null;
                $user_id = $attempt->user_id ?? null;

                if ( ! $quiz_id || ! $user_id ) return false;

                $selected_quiz = $node['data']['config']['quiz_id'] ?? 'any';

                if ( $selected_quiz !== 'any' && (int)$selected_quiz !== (int)$quiz_id ) {
                    return false;
                }

                return [
                    'success'  => true,
                    'quiz_id'  => $quiz_id,
                    'user_id'  => $user_id,
                    'score'    => $attempt->earned_marks ?? 0,
                    'total'    => $attempt->total_marks ?? 0,
                ];
        }
        return false;
    }

    public static function get_actions(): array {
        return [];
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
