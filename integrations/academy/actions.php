<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait AcademyActions {
    public static function get_actions(): array {
        $defs = [
            'enroll-course' => [ 'Enroll User in Course', 'Enroll an existing WordPress user in a course.' ],
            'unenroll-course' => [ 'Unenroll User from Course', 'Cancel the selected enrollment.' ],
            'complete-course' => [ 'Mark Course Complete for User', 'Record Academy course completion.' ],
            'complete-lesson' => [ 'Mark Lesson Complete for User', 'Mark a course curriculum lesson complete.' ],
            'reset-course-progress' => [ 'Reset User Course Progress', 'Remove completed-topic progress and course-completion record without cancelling enrollment or deleting quiz attempt history.' ],
            'add-to-wishlist' => [ 'Add Course to Wishlist', 'Add selected courses to Academy wishlist.' ],
            'remove-from-wishlist' => [ 'Remove Course from Wishlist', 'Remove selected courses from Academy wishlist.' ],
            'assign-instructor' => [ 'Assign Instructor to Course', 'Assign approved instructor to selected courses.' ],
            'remove-instructor' => [ 'Remove Instructor from Course', 'Remove an instructor assignment; cannot remove the author.' ],
        ];
        $result = [];
        foreach ( $defs as $key => $d ) { $result[ $key ] = [ 'label' => $d[0], 'description' => $d[1] ]; }
        return $result;
    }

    public static function get_action_config_schema( string $action ): array {
        if ( ! isset( self::get_actions()[ $action ] ) ) { return []; }
        $single = in_array( $action, [ 'complete-lesson', 'complete-course', 'reset-course-progress' ], true );
        $fields = [
            [ 'key' => 'user_id', 'label' => 'User ID or email', 'type' => 'text', 'required' => false,
                'help' => 'If blank, uses user_id or user_email from the trigger. Enter an explicit user for scheduled workflows.' ],
            self::selection( 'selectedCourse', $single ? 'Course' : 'Courses', 'acourse_no_any', ! $single ),
        ];
        if ( in_array( $action, [ 'enroll-course', 'unenroll-course' ], true ) ) {
            $fields[1]['required'] = false;
            $fields[] = [ 'key' => 'all_courses', 'label' => 'All courses', 'type' => 'checkbox', 'required' => false,
                'help' => 'Enroll in all published courses or cancel all active enrollments for this user.' ];
        }
        if ( 'complete-lesson' === $action ) { $fields[] = self::selection( 'selectedLesson', 'Lesson', 'lesson_no_any' ); }
        if ( 'complete-course' === $action ) {
            $fields[] = [ 'key' => 'force_completion', 'label' => 'Allow completion with unfinished topics', 'type' => 'checkbox', 'required' => false ];
        }
        if ( 'reset-course-progress' === $action ) {
            $fields[] = [ 'key' => 'confirm_reset', 'label' => 'I understand completed topics and course completion will be deleted', 'type' => 'checkbox', 'required' => true ];
        }
        return $fields;
    }

    private static function action_name( array $node ): string {
        foreach ( [ $node, $node['data'] ?? [], $node['flow_details'] ?? [], $node['config'] ?? [] ] as $scope ) {
            if ( ! is_array( $scope ) ) { continue; }
            foreach ( [ 'event', 'action', 'actionName', 'name', 'type' ] as $key ) {
                $name = $scope[ $key ] ?? '';
                if ( is_string( $name ) && isset( self::get_actions()[ str_replace( '_', '-', $name ) ] ) ) {
                    return str_replace( '_', '-', $name );
                }
            }
        }
        throw new \InvalidArgumentException( 'Unknown Academy action.' );
    }
    private static function resolve_user_id( $value, array $input ): int {
        if ( '' === $value || null === $value ) {
            foreach ( [ $input, $input['data'] ?? [] ] as $scope ) {
                if ( ! is_array( $scope ) ) { continue; }
                foreach ( [ 'user_id', 'user_email' ] as $key ) {
                    if ( ! empty( $scope[ $key ] ) ) { return self::resolve_user_id( $scope[ $key ], [] ); }
                }
            }
            $value = get_current_user_id();
        }
        $user = false;
        if ( is_scalar( $value ) && ctype_digit( (string) $value ) && (int) $value > 0 ) {
            $user = get_userdata( (int) $value );
        } elseif ( is_string( $value ) && is_email( trim( $value ) ) ) {
            $user = get_user_by( 'email', trim( $value ) );
        }
        if ( ! $user ) { throw new \InvalidArgumentException( 'A valid existing user ID or email is required.' ); }
        return (int) $user->ID;
    }
    private static function selected_ids( array $config, array $keys ): array {
        foreach ( $keys as $key ) {
            if ( ! isset( $config[ $key ] ) || '' === $config[ $key ] || [] === $config[ $key ] ) { continue; }
            $values = $config[ $key ];
            if ( is_object( $values ) ) { $values = (array) $values; }
            if ( is_array( $values ) && ( isset( $values['id'] ) || isset( $values['value'] ) || isset( $values['name'] ) || isset( $values['courseId'] ) || isset( $values['lessonId'] ) ) ) { $values = [ $values ]; }
            $ids = [];
            foreach ( (array) $values as $value ) {
                if ( is_object( $value ) || is_array( $value ) ) {
                    $value = (array) $value;
                    $value = $value['courseId'] ?? $value['lessonId'] ?? $value['id'] ?? $value['value'] ?? $value['name'] ?? null;
                }
                if ( ! is_scalar( $value ) || ! ctype_digit( (string) $value ) || (int) $value <= 0 ) {
                    throw new \InvalidArgumentException( 'Selections must contain positive numeric IDs.' );
                }
                $ids[] = (int) $value;
            }
            return array_values( array_unique( $ids ) );
        }
        return [];
    }
    private static function enrollment( int $course_id, int $user_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT ID, post_status AS enrolled_status FROM {$wpdb->posts} WHERE post_type = %s AND post_parent = %d AND post_author = %d ORDER BY ID DESC LIMIT 1",
            'academy_enrolled', $course_id, $user_id
        ) );
    }
    private static function completion( int $course_id, int $user_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT comment_ID, comment_content, comment_date FROM {$wpdb->comments} WHERE comment_agent = %s AND comment_type = %s AND comment_post_ID = %d AND user_id = %d LIMIT 1",
            'academy', 'course_completed', $course_id, $user_id
        ) );
    }
    private static function completed_topics( int $course_id, int $user_id ): array {
        $raw = get_user_meta( $user_id, 'academy_course_' . $course_id . '_completed_topics', true );
        if ( '' === $raw || null === $raw ) { return []; }
        $topics = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
        if ( ! is_array( $topics ) ) { throw new \RuntimeException( 'Stored Academy progress is invalid; it was not overwritten.' ); }
        return $topics;
    }
    private static function curriculum_topics( int $course_id ): array {
        $walk = static function ( array $items ) use ( &$walk ): array {
            $result = [];
            foreach ( $items as $item ) {
                if ( ! is_array( $item ) ) { continue; }
                if ( ! empty( $item['topics'] ) && is_array( $item['topics'] ) ) { $result += $walk( $item['topics'] ); }
                if ( ! empty( $item['id'] ) && ! empty( $item['type'] ) && 'sub-curriculum' !== $item['type'] ) {
                    $result[ $item['type'] . ':' . $item['id'] ] = $item;
                }
            }
            return $result;
        };
        return $walk( (array) get_post_meta( $course_id, 'academy_course_curriculum', true ) );
    }
    private static function progress( int $course_id, int $user_id ): array {
        $enrolled = self::enrollment( $course_id, $user_id );
        $complete = self::completion( $course_id, $user_id );
        $all = self::curriculum_topics( $course_id );
        $saved = self::completed_topics( $course_id, $user_id );
        $count = 0;
        foreach ( $all as $topic ) {
            if ( isset( $saved[ $topic['type'] ][ $topic['id'] ] ) ) { ++$count; }
        }
        return [
            'enrollment_id' => $enrolled ? (int) $enrolled->ID : 0,
            'enrollment_status' => $enrolled ? (string) $enrolled->enrolled_status : '',
            'is_enrolled' => $enrolled && 'completed' === $enrolled->enrolled_status,
            'is_completed' => (bool) $complete, 'completion_id' => $complete ? (int) $complete->comment_ID : 0,
            'total_topics' => count( $all ), 'completed_topics' => $count,
            'progress_percentage' => count( $all ) ? round( $count / count( $all ) * 100, 2 ) : 0,
        ];
    }

    public static function execute_node( array $node, array $input ): array {
        $action = self::action_name( $node );
        if ( ! class_exists( '\\Academy\\Helper' ) ) { throw new \RuntimeException( 'Academy LMS plugin is not active.' ); }
        $config = self::config( $node );
        $user_id = self::resolve_user_id( $config['user_id'] ?? '', $input );
        $courses = self::selected_ids( $config, [ 'selectedCourse', 'selected_course', 'courses', 'course_id', 'courseIds' ] );
        $all = in_array( $action, [ 'enroll-course', 'unenroll-course' ], true ) && filter_var( $config['all_courses'] ?? false, FILTER_VALIDATE_BOOLEAN );
        if ( $all && 'enroll-course' === $action ) {
            $courses = array_map( 'intval', get_posts( [ 'post_type' => 'academy_courses', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1 ] ) );
        } elseif ( $all && 'unenroll-course' === $action ) {
            global $wpdb;
            $courses = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT post_parent FROM {$wpdb->posts} WHERE post_type = %s AND post_author = %d AND post_status = %s",
                'academy_enrolled', $user_id, 'completed'
            ) ) );
        }
        if ( ! $courses && ! $all ) { throw new \InvalidArgumentException( 'Select at least one course.' ); }
        $single = in_array( $action, [ 'complete-lesson', 'complete-course', 'reset-course-progress' ], true );
        if ( $single && 1 !== count( $courses ) ) { throw new \InvalidArgumentException( 'This action requires exactly one course.' ); }
        foreach ( $courses as $course_id ) {
            $p = get_post( $course_id );
            if ( ! $p || 'academy_courses' !== $p->post_type || in_array( $p->post_status, [ 'trash', 'auto-draft' ], true ) ) {
                throw new \InvalidArgumentException( 'Selected Academy course unavailable: ' . $course_id );
            }
            if ( 'enroll-course' === $action && ! in_array( $p->post_status, [ 'publish', 'private' ], true ) ) {
                throw new \InvalidArgumentException( 'Enrollment requires a published or private course.' );
            }
            if ( 'remove-instructor' === $action && (int) $p->post_author === $user_id ) {
                throw new \InvalidArgumentException( 'Cannot remove the course author from instructor assignments.' );
            }
        }
        if ( 'assign-instructor' === $action && 'approved' !== get_user_meta( $user_id, 'academy_instructor_status', true ) ) {
            throw new \InvalidArgumentException( 'The user must be an approved Academy instructor.' );
        }
        if ( 'reset-course-progress' === $action && ! filter_var( $config['confirm_reset'] ?? false, FILTER_VALIDATE_BOOLEAN ) ) {
            throw new \InvalidArgumentException( 'Confirm the progress reset explicitly.' );
        }
        $lesson_id = 0;
        if ( 'complete-lesson' === $action ) {
            $ids = self::selected_ids( $config, [ 'selectedLesson', 'selected_lesson', 'lesson_id', 'lessonIds' ] );
            if ( 1 !== count( $ids ) || ! isset( self::curriculum_topics( $courses[0] )[ 'lesson:' . $ids[0] ] ) ) {
                throw new \InvalidArgumentException( 'Choose exactly one lesson in the selected course curriculum.' );
            }
            $lesson_id = $ids[0];
        }
        $results = []; $changed = false;
        foreach ( $courses as $course_id ) {
            $r = self::run_course_action( $action, $course_id, $user_id, $lesson_id, $config );
            $changed = $changed || $r['changed'];
            $results[] = array_merge( self::course_data( $course_id ), $r );
        }
        $data = array_merge( [
            'success' => true, 'action' => $action, 'message' => $changed ? 'Academy action completed.' : 'Academy action completed; no change required.',
            'changed' => $changed, 'course_ids' => $courses, 'results' => $results,
        ], self::user_data( $user_id ) );
        if ( $single ) { $data = array_merge( $data, self::course_data( $courses[0] ) ); }
        if ( $lesson_id ) { $data['lesson_id'] = $lesson_id; }
        if ( 'reset-course-progress' === $action ) {
            $data = array_merge( $data, self::progress( $courses[0], $user_id ) );
            $data['changed'] = $changed;
        }
        return [ 'port' => 'main', 'data' => $data ];
    }

    private static function run_course_action( string $action, int $course_id, int $user_id, int $lesson_id, array $config ): array {
        if ( in_array( $action, [ 'enroll-course', 'unenroll-course' ], true ) ) {
            $enrollment = self::enrollment( $course_id, $user_id );
            $active = $enrollment && 'completed' === $enrollment->enrolled_status;
            if ( ( 'enroll-course' === $action && $active ) || ( 'unenroll-course' === $action && ! $active ) ) { return [ 'changed' => false ]; }
            if ( $enrollment && ! $active && 'cancel' !== $enrollment->enrolled_status ) {
                throw new \RuntimeException( 'Enrollment is pending or order-managed.' );
            }
            $old_user = get_current_user_id();
            try {
                wp_set_current_user( $user_id );
                if ( 'enroll-course' === $action ) {
                    $id = \Academy\Helper::do_enroll( $course_id, $user_id );
                    if ( ! $id || is_wp_error( $id ) ) { throw new \RuntimeException( 'Academy enrollment failed.' ); }
                } else {
                    \Academy\Helper::cancel_course_enroll( $course_id, $user_id );
                }
            } finally {
                wp_set_current_user( $old_user );
            }
            $after = self::enrollment( $course_id, $user_id );
            if ( ( 'enroll-course' === $action && ( ! $after || 'completed' !== $after->enrolled_status ) ) ||
                 ( 'unenroll-course' === $action && $after && 'completed' === $after->enrolled_status ) ) {
                throw new \RuntimeException( 'Enrollment change could not be verified.' );
            }
            return [ 'changed' => true ];
        }
        if ( in_array( $action, [ 'complete-lesson', 'complete-course', 'reset-course-progress' ], true ) ) {
            $e = self::enrollment( $course_id, $user_id );
            if ( ! $e || 'completed' !== $e->enrolled_status ) {
                throw new \RuntimeException( 'Active Academy course enrollment is required.' );
            }
            if ( 'complete-course' === $action ) {
                return [ 'changed' => self::mark_course_complete( $course_id, $user_id, filter_var( $config['force_completion'] ?? false, FILTER_VALIDATE_BOOLEAN ) ) ];
            }
            if ( 'reset-course-progress' === $action ) { return [ 'changed' => self::reset_progress( $course_id, $user_id ) ]; }
            $saved = self::completed_topics( $course_id, $user_id );
            if ( isset( $saved['lesson'][ $lesson_id ] ) ) { return [ 'changed' => false ]; }
            do_action( 'academy/frontend/before_mark_topic_complete', 'lesson', $course_id, $lesson_id, $user_id );
            $saved['lesson'][ $lesson_id ] = \Academy\Helper::get_time();
            if ( ! update_user_meta( $user_id, 'academy_course_' . $course_id . '_completed_topics', wp_json_encode( $saved ) ) ) {
                throw new \RuntimeException( 'Failed to save Academy lesson completion.' );
            }
            do_action( 'academy/frontend/after_mark_topic_complete', 'lesson', $course_id, $lesson_id, $user_id );
            return [ 'changed' => true ];
        }
        $key = in_array( $action, [ 'assign-instructor', 'remove-instructor' ], true ) ? 'academy_instructor_course_id' : 'academy_course_wishlist';
        $add = in_array( $action, [ 'assign-instructor', 'add-to-wishlist' ], true );
        $exists = in_array( $course_id, array_map( 'intval', (array) get_user_meta( $user_id, $key, false ) ), true );
        if ( $exists === $add ) { return [ 'changed' => false ]; }
        $saved = $add ? add_user_meta( $user_id, $key, $course_id ) : delete_user_meta( $user_id, $key, $course_id );
        if ( ! $saved ) { throw new \RuntimeException( 'Failed to save Academy user course metadata.' ); }
        return [ 'changed' => true ];
    }

    private static function mark_course_complete( int $course_id, int $user_id, bool $force ): bool {
        if ( self::completion( $course_id, $user_id ) ) { return false; }
        $p = self::progress( $course_id, $user_id );
        if ( ! $force && $p['completed_topics'] < $p['total_topics'] ) {
            throw new \RuntimeException( 'Course has unfinished topics; complete them or explicitly enable force_completion.' );
        }
        do_action( 'academy/admin/course_complete_before', $course_id );
        $date = gmdate( 'Y-m-d H:i:s', \Academy\Helper::get_time() );
        $id = wp_insert_comment( [
            'comment_post_ID' => $course_id, 'comment_author' => $user_id,
            'comment_date' => $date, 'comment_date_gmt' => get_gmt_from_date( $date ),
            'comment_content' => substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 16 ),
            'comment_approved' => 'approved', 'comment_agent' => 'academy',
            'comment_type' => 'course_completed', 'user_id' => $user_id,
        ] );
        if ( ! $id || is_wp_error( $id ) ) { throw new \RuntimeException( 'Failed to save Academy course completion.' ); }
        do_action( 'academy/admin/course_complete_after', $course_id, $user_id );
        return true;
    }

    private static function reset_progress( int $course_id, int $user_id ): bool {
        // Non-destructive with respect to enrollment, grades and quiz attempt history.
        // Explicit confirmation is checked in execute_node before this method is called.
        $key = 'academy_course_' . $course_id . '_completed_topics';
        $old = self::completed_topics( $course_id, $user_id );
        $complete = self::completion( $course_id, $user_id );
        if ( ! $old && ! $complete ) { return false; }
        if ( $old ) {
            if ( ! delete_user_meta( $user_id, $key ) ) {
                throw new \RuntimeException( 'Failed to delete Academy completed-topic data; course-completion record was preserved.' );
            }
        }
        if ( $complete ) {
            if ( ! wp_delete_comment( (int) $complete->comment_ID, true ) ) {
                throw new \RuntimeException( 'Failed to delete Academy course-completion record.' );
            }
        }
        if ( self::completed_topics( $course_id, $user_id ) || self::completion( $course_id, $user_id ) ) {
            throw new \RuntimeException( 'Academy course progress reset could not be verified.' );
        }
        return true;
    }

    public static function get_action_sample_output( string $action ): array {
        if ( ! isset( self::get_actions()[ $action ] ) ) { return []; }
        $sample = [
            'success' => true, 'action' => $action, 'message' => 'Academy action completed.',
            'changed' => true, 'course_ids' => [ 42 ],
            'results' => [ [ 'course_id' => 42, 'course_title' => 'Sample Course', 'course_url' => 'https://example.com/course/', 'changed' => true ] ],
            'user_id' => 1, 'user_email' => 'student@example.com', 'first_name' => 'Jane', 'last_name' => 'Doe', 'username' => 'jane',
        ];
        if ( in_array( $action, [ 'complete-lesson', 'complete-course', 'reset-course-progress' ], true ) ) {
            $sample += [ 'course_id' => 42, 'course_title' => 'Sample Course', 'course_url' => 'https://example.com/course/' ];
        }
        if ( 'complete-lesson' === $action ) { $sample['lesson_id'] = 5; }
        if ( 'reset-course-progress' === $action ) {
            $sample += [ 'is_enrolled' => true, 'is_completed' => false, 'completed_topics' => 0, 'progress_percentage' => 0 ];
        }
        return $sample;
    }
}
