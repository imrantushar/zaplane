<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class Masterstudy extends IntegrationBase {

    public static function get_slug(): string {
        return 'masterstudy';
    }

    public static function get_triggers(): array {
        return [
            'user_enroll_course' => [
                'label' => 'User Is Enrolled In A Course',
                'hook'  => 'add_user_course'
            ],
            'course_complete' => [
                'label' => 'User Completed A Course', 
                'hook'  => 'stm_lms_progress_updated'
            ],
            'lesson_complete' => [
                'label' => 'User Completed A Lesson', 
                'hook'  => 'stm_lms_lesson_passed'
            ],
        ]; 
    }

    private static function resolve_all_course_payload() {
        $all_course = [ 
            ['label' => 'Any course', 'value' => 'any'],
        ];

        if ( ! function_exists( 'MasterStudy' ) ) {
            $courses = get_posts([
                'post_type'      => 'stm-courses',
                'post_status'    => 'publish',
                'orderby'        => 'label',
                'order'          => 'ASC',
                'posts_per_page' => 999,
            ]);
            
            foreach ( $courses as $course ) {
                $all_course[] = [
                    'label' => $course->post_title,
                    'value' => $course->ID
                ];
            }
        }

        return $all_course;
    }

    private static function resolve_all_lesson_payload() {
        $all_lesson = [ 
            ['label' => 'Any lesson', 'value' => 'any'],
        ];

        if ( ! function_exists( 'MasterStudy' ) ) {
            $lessons = get_posts([
                'post_type'      => 'stm-lessons',
                'post_status'    => 'publish',
                'orderby'        => 'label',
                'order'          => 'ASC',
                'posts_per_page' => 999,
            ]);
            
            foreach ( $lessons as $lesson ) {
                $all_lesson[] = [
                    'label' => $lesson->post_title,
                    'value' => $lesson->ID
                ];
            }
        }

        return $all_lesson;
    }

    public static function get_trigger_config_schema( string $trigger ): array {
        if ( in_array( $trigger, ['user_enroll_course','course_complete'], true ) ) {
            return [
                [
                    'key'      => 'course_id',
                    'label'    => 'Course',
                    'type'     => 'select',
                    'options'  => self::resolve_all_course_payload(),
                    'required' => true,
                ],
            ];
        }

        if ( in_array( $trigger, ['lesson_complete','lesson_assignment'], true ) ) {
            return [
                [
                    'key'      => 'course_id',
                    'label'    => 'Course',
                    'type'     => 'select',
                    'options'  => self::resolve_all_course_payload(),
                    'required' => true,
                ],
                [
                    'key'      => 'lesson_id',
                    'label'    => 'Lesson',
                    'type'     => 'select',
                    'options'  => self::resolve_all_lesson_payload(),
                    'required' => true,
                ],
            ];
        }

        return [];
    }

    private static function resolve_course_payload( int $user_id ,$course_id ) {
        $user_id   = (int) $user_id;
        $course_id = (int) $course_id;
        $user = get_user_by('id', $user_id);
        if ( ! $user) return false;
        return [
            'course_id'          => (int) $course_id,
            'course_title'       => get_the_title( $course_id ),
            'course_description' => get_post_field( 'post_content', $course_id ),
            'course_url'         => get_permalink( $course_id ),
            'user_id'            => (int) $user_id,
            'first_name'         => $user->first_name,
            'last_name'          => $user->last_name,
            'user_login'         => $user->user_login,
            'user_email'         => $user->user_email,
            'nickname'           => $user->nickname,
            'display_name'       => $user->display_name,
            'avatar_url'         => get_avatar_url( $user_id ),
            'user_roles'         => $user->roles,
            'completed_at'       => current_time('mysql'),
        ];
    }

    public static function resolve_trigger( array $node, array $args ) {
        switch ( $node['event'] ) {

            case 'user_enroll_course':

    $user_id   = $args[0] ?? 0;
    $course_id = $args[1] ?? 0;

    if ( ! $user_id || ! $course_id ) return false;

    $selected_course = $node['data']['config']['course_id'] ?? 'any';

    if ( $selected_course !== 'any' && (int)$selected_course !== (int)$course_id ) {
        return false;
    }

    return [
        'success'   => true,
        'timestamp' => current_time('mysql'),
        'data'      => self::resolve_course_payload($user_id, $course_id),
    ];
                // $user_id   = $args[0] ?? 0;
                // $course_id = $args[1] ?? 0;
                // $assess_list = $args[2] ?? 0;
                // $remove    = $args[3] ?? null; 

                // if (!$user_id || !$course_id) return false;

                // if (!empty($remove)) return false;

                // $selected_course = $node['data']['config']['course_id'] ?? 'any';

                // if ($selected_course !== 'any' && (int)$selected_course !== (int)$course_id) return false;

                // return [
                //     'success'   => true,
                //     'timestamp' => current_time('mysql'),
                //     'data'      => self::resolve_course_payload($user_id, $course_id),
                // ];

            case 'course_complete':
                $course_id = $args[0] ?? 0;
                $user_id   = $args[1] ?? 0;

                if ( ! $course_id || ! $user_id ) return false;

                $selected_course = $node['data']['config']['course_id'] ?? 'any';

                if ( $selected_course !== 'any' && (int) $selected_course !== (int) $course_id ) return false;

                return [
                    'success'   => true,
                    'timestamp' => current_time('mysql'),
                    'data' => self::resolve_course_payload( $user_id, $course_id ),
                ];

            case 'lesson_complete':
                $lesson_id = $args[0] ?? null;
                $user_id   = $args[1] ?? get_current_user_id();

                if ( ! $lesson_id || ! $user_id ) return false;

                $selected_lesson = $node['data']['config']['lesson_id'] ?? 'any';

                if ( $selected_lesson !== 'any' && (int) $selected_lesson !== (int) $lesson_id ) return false;

                $lesson = get_post( $lesson_id );
                $user   = get_user_by('id', $user_id );

                if ( ! $user) return false;

                return [
                    'success' => true,
                    'timestamp' => current_time('mysql'),
                    'data' => [
                        'lesson_id'          => $lesson->ID,
                        'lesson_title'       => $lesson->post_title,
                        'lesson_description' => $lesson->description,
                        'lesson_url'         => get_permalink($lesson->ID),
                        'user_id'            => $user_id,
                        'first_name'         => $user->first_name,
                        'last_name'          => $user->last_name,
                        'user_login'         => $user->user_login,
                        'user_email'         => $user->user_email,
                        'nickname'           => $user->nickname,
                        'display_name'       => $user->display_name,
                        'avatar_url'         => get_avatar_url( $user_id ),
                        'user_roles'         => $user->roles,
                        'completed_at'       => current_time('mysql'),
                    ]
                ];
        }
        return false;
    }
}