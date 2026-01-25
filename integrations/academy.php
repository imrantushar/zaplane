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
            // Posts
            'user_enroll_course'                    => ['label' => 'User enrolled in a course', 'hook' => 'academy/course/after_enroll'],
            'academy_handle_quiz_attempt'           => ['label' => 'Academy Handle Quiz Attempt', 'hook' => 'academy_quizzes/api/after_quiz_attempt_finished'],
            'user_enroll_course'           => ['label' => 'User enrolled in a course', 'hook' => 'academy/course/after_enroll'],
            'user_enroll_course'           => ['label' => 'User enrolled in a course', 'hook' => 'academy/course/after_enroll'],
            'user_enroll_course'           => ['label' => 'User enrolled in a course', 'hook' => 'academy/course/after_enroll'],
        ];
    }




    
        'academy/course/after_enroll'                     => ['entity' => 'AcademyLms', 'hook' => 'academy/course/after_enroll', 'function' => 'academyHandleCourseEnroll', 'priority' => 10, 'acceptedArgs' => 2, 'isFilterHook' => false],
        'academy_quizzes/api/after_quiz_attempt_finished' => ['entity' => 'AcademyLms', 'hook' => 'academy_quizzes/api/after_quiz_attempt_finished', 'function' => 'academyHandleQuizAttempt', 'priority' => 10, 'acceptedArgs' => 1, 'isFilterHook' => false],
        'academy/frontend/after_mark_topic_complete'      => ['entity' => 'AcademyLms', 'hook' => 'academy/frontend/after_mark_topic_complete', 'function' => 'academyHandleLessonComplete', 'priority' => 10, 'acceptedArgs' => 4, 'isFilterHook' => false],
        'academy/admin/course_complete_after'             => ['entity' => 'AcademyLms', 'hook' => 'academy/admin/course_complete_after', 'function' => 'academyHandleCourseComplete', 'priority' => 10, 'acceptedArgs' => 1, 'isFilterHook' => false],
        'academy_quizzes/api/after_quiz_attempt_finished' => ['entity' => 'AcademyLms', 'hook' => 'academy_quizzes/api/after_quiz_attempt_finished', 'function' => 'academyHandleQuizTarget', 'priority' => 10, 'acceptedArgs' => 1, 'isFilterHook' => false],











    public static function get_trigger_config_schema( string $trigger ): array {
        if ( $trigger === 'user_enroll_course' ) {
            return [
                [
                    'key'      => 'course_id',
                    'label'    => 'Course ID',
                    'type'     => 'number',
                    'required' => true,
                ],
            ];
        }
        return [];
    }

    public static function resolve_trigger( array $node, array $args ) {
        switch ( $node['event'] ) {
            case 'publish_post':
            case 'user_enroll_course':
                        //User info
                        $user = get_user_by( 'ID', $user_id );

                        //Course info
                        $course_title = get_the_title( $course_id );

                        //custom trigger
                            return [
                            'user_id'      => $user_id,
                            'user_email'   => $user->user_email,
                            'course_id'    => $course_id,
                            'course_title'=> $course_title,
                        ];
                        
                
            // return self::resolve_post_payload( $args[0] ?? 0 ); // example code remove before proceed
        }

        return false;
    }

    

    public static function get_actions(): array {
        return [
            'create_post'                   => ['label'=>'Create Post'], // example code
        ];
    }

    public static function get_action_config_schema( string $action ): array {

        $schemas = [
            'action' => ['schema_key' => 'schema_value' ] // example code
        ];

        return $schemas[$action] ?? [];
    }


    public static function execute_node( array $node, array $input ): array {

        $config = $node['data']['config'] ?? [];
        $event = $node['data']['event'];

        $method = 'action_' . $event;

        if (method_exists(static::class, $method)) {
            return static::$method($config, $input);
        }

        return ['port' => 'main', 'data' => $input];
    }
}
