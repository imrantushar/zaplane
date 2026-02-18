<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class Tutor extends IntegrationBase {

    public static function get_slug(): string {
        return 'tutor';
    }

    public static function get_triggers(): array {
        return [
            'user_enroll_course' => [
                'label' => 'User enrolled in a course', 
                'hook'  => 'tutor_after_enroll'
            ],
            'course_complete' => [
                'label' => 'User completed a course', 
                'hook'  => 'tutor_course_complete_after'
            ],
            'tutor_quiz_course_attempt' => [
                'label' => 'User attempted (submitted) a quiz', 
                'hook'  => 'tutor_quiz/attempt_ended'
            ],
            'lesson_complete' => [
                'label' => 'User completed a lesson', 
                'hook'  => 'tutor_lesson_completed_after'
            ],
            'quiz_target' => [
                'label' => 'User achieved target percentage on a quiz', 
                'hook'  => 'tutor_quiz/attempt_ended'
            ],
        ]; 
    }

    public static function get_trigger_config_schema( string $trigger ): array {
        if ( in_array( $trigger, ['user_enroll_course','course_complete'], true ) ) {
            $options = [ 
                ['label' => 'Any course', 'value' => 'any'],
            ];
            if ( function_exists( 'tutor' ) ) {
                $courses = get_posts([
                    'post_type'      => 'courses',
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

        if ( in_array( $trigger, ['tutor_quiz_course_attempt','quiz_target'], true ) ) {
            $options = [ 
                ['label' => 'Any Quiz', 'value' => 'any'],
            ];
            if ( function_exists( 'tutor' ) ) {
                $quizzes = get_posts([
                    'post_type'      => 'tutor_quiz',
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

        if ( in_array( $trigger, ['lesson_complete'], true ) ) {
            $options = [ 
                ['label' => 'Any lesson', 'value' => 'any'],
            ];
            if ( function_exists( 'tutor' ) ) {
                $lessons = get_posts([
                    'post_type'      => 'lesson',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                ]);
                
                foreach ( $lessons as $lesson ) {
                    $options[] = [
                        'label' => $lesson->post_title,
                        'value' => $lesson->ID,
                    ];
                }
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
        error_log(print_r($node , true));

        switch ( $node['event'] ) {
            case 'user_enroll_course':
            case 'course_complete':
                $course_id = $args[0] ?? null;
                $enroll_id = $args[1] ?? null;
                
                if ( !$course_id && !$enroll_id ) return false;
                return [
                    'success' => true,
                    'course_id' => $course_id,
                    'enroll_id' => $enroll_id,
                ];

            case 'lesson_complete':
                $lesson_id  = $args[0] ?? null;
                $user_id    = $args[1] ?? get_current_user_id();
                
                if ( !$lesson_id && !$user_id ) return false;
                return [
                    'success' => true,
                    'lesson_id' => $lesson_id,
                    'user_id'   => $user_id,
                ];
            case 'tutor_quiz_course_attempt':
            case 'quiz_target':

                $quiz_id = $attempt['quiz_id'] ?? $attempt['quizId'] ;
                $user_id = $user_id ?: get_current_user_id();
                
                if ( !$quiz_id && !$user_id ) return false;
                return [
                    'success' => true,
                    'quiz_id' => $quiz_id,
                    'user_id'   => $user_id,
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
