<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use Zaplane\Framework\Classes\IntegrationBase;
require_once __DIR__ . '/academy/events.php';
require_once __DIR__ . '/academy/actions.php';

/** Zaplane Academy LMS: 27 triggers and 9 actions. */
class Academy extends IntegrationBase {
    use AcademyEvents;
    use AcademyActions;

    public static function get_slug(): string { return 'academy'; }
    public static function get_name(): string { return 'Academy LMS'; }
    public static function get_icon(): string { return 'academy.svg'; }

    private static function config( array $node ): array {
        foreach ( [ $node['config'] ?? null, $node['data']['config'] ?? null, $node['flow_details'] ?? null ] as $value ) {
            if ( is_array( $value ) ) { return $value; }
        }
        return $node;
    }
    private static function event( array $node ): string {
        return (string) ( $node['event'] ?? $node['data']['event'] ?? $node['flow_details']['event'] ?? '' );
    }
    private static function selection( string $key, string $label, string $query, bool $multiple = false ): array {
        return [
            'key' => $key, 'label' => $label, 'type' => 'select', 'required' => true,
            'multiple' => $multiple,
            'dynamic' => [ 'integration' => 'academy', 'query' => $query, 'select' => [ 'name', 'label' ] ],
        ];
    }
    private static function user_data( int $user_id ): array {
        $u = $user_id ? get_userdata( $user_id ) : false;
        return [
            'user_id' => $user_id, 'user_email' => $u ? (string) $u->user_email : '',
            'first_name' => $u ? (string) $u->first_name : '',
            'last_name' => $u ? (string) $u->last_name : '',
            'username' => $u ? (string) $u->user_login : '',
        ];
    }
    private static function course_data( int $course_id ): array {
        $p = $course_id ? get_post( $course_id ) : null;
        return [
            'course_id' => $course_id, 'course_title' => $p ? (string) $p->post_title : '',
            'course_url' => $p ? (string) get_permalink( $course_id ) : '',
        ];
    }
    private static function matches( array $config, string $key, $actual ): bool {
        $wanted = $config[ $key ] ?? 'any';
        if ( is_array( $wanted ) ) {
            $wanted = array_map( static function ( $v ) {
                return is_array( $v ) ? (string) ( $v['name'] ?? $v['value'] ?? $v['id'] ?? '' ) : (string) $v;
            }, $wanted );
            return in_array( 'any', $wanted, true ) || in_array( (string) $actual, $wanted, true );
        }
        if ( is_array( $wanted ) || is_object( $wanted ) ) { return false; }
        return 'any' === (string) $wanted || '' === (string) $wanted || (string) $wanted === (string) $actual;
    }
    public static function get_dynamic_queries(): array {
        return [
            'acourse' => [ self::class, 'query_courses' ],
            'acourse_no_any' => [ self::class, 'query_courses_no_any' ],
            'quiz' => [ self::class, 'query_quiz' ],
            'lesson' => [ self::class, 'query_lesson' ],
            'lesson_no_any' => [ self::class, 'query_lesson_no_any' ],
            'user' => [ self::class, 'query_users' ],
        ];
    }
    private static function post_options( string $type, bool $any, string $label ): array {
        $items = $any ? [ [ 'label' => 'Any ' . $label, 'name' => 'any' ] ] : [];
        if ( ! class_exists( '\\Academy\\Helper' ) ) { return $items; }
        foreach ( get_posts( [ 'post_type' => $type, 'post_status' => [ 'publish', 'private' ], 'posts_per_page' => -1 ] ) as $p ) {
            $items[] = [ 'label' => $p->post_title, 'name' => $p->ID ];
        }
        return $items;
    }
    public static function query_courses(): array { return self::post_options( 'academy_courses', true, 'course' ); }
    public static function query_courses_no_any(): array { return self::post_options( 'academy_courses', false, 'course' ); }
    public static function query_quiz(): array { return self::post_options( 'academy_quiz', true, 'quiz' ); }
    private static function lessons( bool $any ): array {
        $items = $any ? [ [ 'label' => 'Any lesson', 'name' => 'any' ] ] : [];
        if ( ! class_exists( '\\Academy\\Lesson\\LessonApi\\Lesson' ) ) { return $items; }
        foreach ( (array) \Academy\Lesson\LessonApi\Lesson::get( 0, -1, 0, '', '', true ) as $lesson ) {
            if ( ! is_object( $lesson ) || ! method_exists( $lesson, 'get_data' ) ) { continue; }
            $d = (array) $lesson->get_data();
            $items[] = [ 'label' => (string) ( $d['lesson_title'] ?? '' ), 'name' => (int) ( $d['ID'] ?? 0 ) ];
        }
        return $items;
    }
    public static function query_lesson(): array { return self::lessons( true ); }
    public static function query_lesson_no_any(): array { return self::lessons( false ); }
    public static function query_users(): array {
        $items = [];
        foreach ( get_users( [ 'fields' => [ 'ID', 'display_name', 'user_email' ], 'number' => 200, 'orderby' => 'display_name' ] ) as $u ) {
            $items[] = [ 'name' => $u->ID, 'label' => $u->display_name . ' (' . $u->user_email . ')' ];
        }
        return $items;
    }
}
