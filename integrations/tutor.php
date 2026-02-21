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

        if ( in_array( $trigger, ['tutor_quiz_course_attempt'], true ) ) {
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

        if ( $trigger === 'quiz_target' ) {

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

                $coursePost = get_post( (int) $course_id );
                $userData   = get_userdata( $user_id );

                if ( ! $coursePost || ! $userData ) return false;

                $selected_course = $node['data']['config']['course_id'] ?? 'any';

                if ( $selected_course !== 'any' && (int)$selected_course !== (int)$course_id ) {
                    return false;
                }

                return [
                    'success'       => true,
                    'course_id'     => $coursePost->ID,
                    'course_title'  => $coursePost->post_title,
                    'course_url'    => get_permalink( $coursePost->ID ),
                    'user_id'       => $user_id,
                    'first_name'    => $userData->first_name,
                    'last_name'     => $userData->last_name,
                    'user_email'    => $userData->user_email,
                    'nickname'      => $userData->nickname,
                ];

            case 'lesson_complete':
                $lesson_id  = $args[0] ?? null;
                $user_id    = $args[1] ?? get_current_user_id();
                
                if ( ! $lesson_id || ! $user_id ) return false;

                $selected_lesson = $node['data']['config']['lesson_id'] ?? 'any';

                if ( $selected_lesson !== 'any' && (int)$selected_lesson !== (int)$lesson_id ) {
                    return false;
                }

                return [
                    'success' => true,
                    'lesson_id' => $lesson_id,
                    'user_id'   => $user_id,
                ];
            case 'tutor_quiz_course_attempt':
                $attempt = $args[0] ?? null;

                if ( ! $attempt ) return false;

                $quiz_id = $attempt->quiz_id ?? null;
                $user_id = $attempt->user_id ?? null;

                if ( ! $quiz_id || ! $user_id ) return false;

                $selected_quiz = $node['data']['config']['quiz_id'] ?? 'any';

                if ( $selected_quiz !== 'any' && (int)$selected_quiz !== (int)$quiz_id ) {
                    return false;
                }

                return [
                    'success' => true,
                    'quiz_id' => $quiz_id,
                    'user_id' => $user_id,
                ];

            case 'quiz_target':
                $attempt = $args[0] ?? null;
                if ( ! $attempt ) return false;

                // Support array or object
                $quiz_id = is_array($attempt)
                    ? ($attempt['quiz_id'] ?? null)
                    : ($attempt->quiz_id ?? null);

                $user_id = is_array($attempt)
                    ? ($attempt['user_id'] ?? null)
                    : ($attempt->user_id ?? null);

                $earned = is_array($attempt)
                    ? ($attempt['earned_marks'] ?? 0)
                    : ($attempt->earned_marks ?? 0);

                $total = is_array($attempt)
                    ? ($attempt['total_marks'] ?? 0)
                    : ($attempt->total_marks ?? 0);

                if ( ! $quiz_id || ! $user_id || ! $total ) return false;

                // Quiz filter
                $selected_quiz = $node['data']['config']['quiz_id'] ?? 'any';

                if ( $selected_quiz !== 'any' && (int)$selected_quiz !== (int)$quiz_id ) {
                    return false;
                }

                // Percentage check
                $percentage = ( $earned / $total ) * 100;
                $target     = (float) ($node['data']['config']['target_percentage'] ?? 0);

                if ( $percentage < $target ) {
                    return false;
                }

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
