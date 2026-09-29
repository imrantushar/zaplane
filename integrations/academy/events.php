<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Academy's real action signatures, not labels from its Webhooks UI. */
trait AcademyEvents {
    public static function get_triggers(): array {
        $finish = 'academy_quizzes/api/after_quiz_attempt_finished';
        $review = 'academy_quizzes/after_quiz_attempt_manual_review';
        $topic  = 'academy/frontend/after_mark_topic_complete';
        $defs = [
            'user_enroll_course' => [ 'User Enrolled in Course', 'academy/course/after_enroll' ],
            'lesson_complete' => [ 'Lesson Completed', $topic ],
            'course_complete' => [ 'Course Completed', 'academy/admin/course_complete_after' ],
            'quiz_attempt_submitted' => [ 'Quiz Attempt Submitted', $finish ],
            'quiz_passed' => [ 'Quiz Passed', [ $finish, $review ] ],
            'quiz_failed' => [ 'Quiz Failed', [ $finish, $review ] ],
            'quiz_target' => [ 'Quiz Score Threshold Reached', [ $finish, $review ] ],
            'course_published' => [ 'Course Published', 'transition_post_status' ],
            'lesson_published' => [ 'Lesson Published', 'academy_new_lesson_published' ],
            'quiz_published' => [ 'Quiz Published', 'transition_post_status' ],
            'student_registered' => [ 'Student Registered', [ 'academy/admin/after_register_student', 'academy/api/auth/after_student_registration', 'academy/shortcode/after_student_registration', 'academy/admin/after_student_registration' ] ],
            'instructor_registered' => [ 'Instructor Registered', [ 'academy/admin/after_register_instructor', 'academy/api/auth/after_instructor_registration', 'academy/shortcode/after_instructor_registration' ] ],
            'course_review_submitted' => [ 'Course Review Submitted', 'academy/frontend/after_course_rating' ],
            'course_question_asked' => [ 'Course Question Asked', 'academy/frontend/insert_course_qa' ],
            'course_question_replied' => [ 'Course Question Replied', 'academy/frontend/insert_course_qa_answered' ],
            'announcement_published' => [ 'Announcement Published', 'transition_post_status' ],
            'assignment_published' => [ 'Assignment Published', 'transition_post_status' ],
            'assignment_submitted' => [ 'Assignment Submitted', 'academy_pro/frontend/submitted_assignment' ],
            'assignment_evaluated' => [ 'Assignment Evaluated', 'academy_pro/frontend/evaluate_submitted_assignment' ],
            'assignment_completed' => [ 'Assignment Completed', $topic ],
            'tutor_booking_published' => [ 'Tutor Booking Published', 'transition_post_status' ],
            'tutor_booking_booked' => [ 'Tutor Booking Booked', 'academy_pro/booking/after_booked' ],
            'tutor_booking_completed' => [ 'Tutor Booking Completed', $topic ],
            'tutor_booking_review_submitted' => [ 'Tutor Booking Review Submitted', 'academy_pro/fronted/academy_booking_review' ],
            'zoom_meeting_published' => [ 'Zoom Meeting Published', 'academy_pro/frontend/after_zoom_publish' ],
            'zoom_meeting_completed' => [ 'Zoom Meeting Completed', $topic ],
            'course_bundle_published' => [ 'Course Bundle Published', 'transition_post_status' ],
        ];
        $result = [];
        foreach ( $defs as $id => $def ) {
            $result[ $id ] = [ 'label' => $def[0], 'hook' => $def[1] ];
        }
        return $result;
    }

    private static function quiz_events(): array {
        return [ 'quiz_attempt_submitted', 'quiz_passed', 'quiz_failed', 'quiz_target' ];
    }
    private static function topic_events(): array {
        return [ 'lesson_complete' => 'lesson', 'assignment_completed' => 'assignment', 'tutor_booking_completed' => 'booking', 'zoom_meeting_completed' => 'zoom' ];
    }
    private static function publication_types(): array {
        return [
            'course_published' => 'academy_courses', 'quiz_published' => 'academy_quiz',
            'announcement_published' => 'academy_announcement',
            'assignment_published' => 'academy_assignments',
            'tutor_booking_published' => 'academy_booking',
            'course_bundle_published' => 'alms_course_bundle',
        ];
    }
    public static function get_trigger_config_schema( string $trigger ): array {
        if ( ! isset( self::get_triggers()[ $trigger ] ) ) { return []; }
        if ( in_array( $trigger, [ 'student_registered', 'instructor_registered' ], true ) ) { return []; }
        if ( in_array( $trigger, self::quiz_events(), true ) ) {
            $fields = [ self::selection( 'quiz_id', 'Quiz', 'quiz' ) ];
            if ( 'quiz_target' === $trigger ) {
                $fields[] = [ 'key' => 'target_percentage', 'label' => 'Minimum percentage (0-100)', 'type' => 'number', 'required' => true ];
            }
            $fields[] = self::selection( 'course_id', 'Course', 'acourse' );
            return $fields;
        }
        if ( 'lesson_complete' === $trigger || 'lesson_published' === $trigger ) {
            return [ self::selection( 'lesson_id', 'Lesson', 'lesson' ), self::selection( 'course_id', 'Course', 'acourse' ) ];
        }
        if ( isset( self::topic_events()[ $trigger ] ) ) {
            return [
                [ 'key' => 'topic_id', 'label' => 'Topic ID (optional; empty means any)', 'type' => 'text', 'required' => false ],
                self::selection( 'course_id', 'Course', 'acourse' ),
            ];
        }
        // Publications/booking/Q&A can operate independently of a course.
        if ( in_array( $trigger, [ 'course_published', 'quiz_published', 'assignment_published', 'announcement_published', 'tutor_booking_published', 'tutor_booking_booked', 'tutor_booking_review_submitted', 'zoom_meeting_published', 'course_bundle_published' ], true ) ) {
            return [];
        }
        return [ self::selection( 'course_id', 'Course', 'acourse' ) ];
    }

    private static function raw( $value ): array {
        return is_array( $value ) || is_object( $value ) ? (array) $value : [];
    }
    private static function comment_payload( $arg ): array {
        $raw = self::raw( $arg );
        if ( ! $raw && is_numeric( $arg ) ) { $raw = [ 'comment_ID' => (int) $arg ]; }
        $id = (int) ( $raw['comment_ID'] ?? $raw['id'] ?? $raw['ID'] ?? 0 );
        $comment = $id ? get_comment( $id ) : null;
        if ( ! $comment ) { return []; }
        $course_id = (int) ( $raw['post'] ?? $raw['comment_parent_course'] ?? $comment->comment_post_ID );
        return array_merge( [
            'comment_id' => $id, 'parent_id' => (int) $comment->comment_parent,
            'content' => (string) $comment->comment_content,
            'course_id' => $course_id,
        ], self::user_data( (int) $comment->user_id ), self::course_data( $course_id ) );
    }
    private static function resolve_quiz( string $event, array $config, array $args ) {
        $raw = self::raw( $args[0] ?? null );
        $quiz_id = (int) ( $raw['quiz_id'] ?? 0 );
        $user_id = (int) ( $raw['user_id'] ?? 0 );
        $course_id = (int) ( $raw['course_id'] ?? 0 );
        $status = (string) ( $raw['attempt_status'] ?? '' );
        if ( ! $quiz_id || ! $user_id || ! self::matches( $config, 'quiz_id', $quiz_id ) || ! self::matches( $config, 'course_id', $course_id ) ) { return false; }
        if ( 'quiz_passed' === $event && 'passed' !== $status ) { return false; }
        if ( 'quiz_failed' === $event && 'failed' !== $status ) { return false; }
        if ( 'quiz_target' === $event && ! in_array( $status, [ 'passed', 'failed', 'attempt_ended' ], true ) ) { return false; }
        if ( 'quiz_attempt_submitted' === $event && in_array( $status, [ '', 'started', 'in_progress' ], true ) ) { return false; }
        $score = (float) ( $raw['earned_marks'] ?? 0 );
        $total = (float) ( $raw['total_marks'] ?? 0 );
        $pct = $total > 0 ? round( 100 * $score / $total, 2 ) : 0.0;
        if ( 'quiz_target' === $event ) {
            $target = $config['target_percentage'] ?? null;
            if ( ! is_numeric( $target ) || (float) $target < 0 || (float) $target > 100 || $total <= 0 || $pct < (float) $target ) { return false; }
        }
        // Do not suppress repeated resolve_trigger calls: separate Zaplane workflows
        // can legitimately consume the same Academy attempt.
        $attempt_id = (int) ( $raw['attempt_id'] ?? $raw['ID'] ?? 0 );
        return array_merge( [
            'success' => true, 'attempt_id' => $attempt_id, 'quiz_id' => $quiz_id,
            'quiz_title' => (string) get_the_title( $quiz_id ), 'attempt_status' => $status,
            'score' => $score, 'total' => $total, 'total_marks' => $total, 'percentage' => $pct,
        ], self::course_data( $course_id ), self::user_data( $user_id ) );
    }

    public static function resolve_trigger( array $node, array $args ) {
        $event = self::event( $node );
        $config = self::config( $node );
        if ( ! isset( self::get_triggers()[ $event ] ) ) { return false; }
        if ( in_array( $event, self::quiz_events(), true ) ) { return self::resolve_quiz( $event, $config, $args ); }
        $course_id = 0; $user_id = 0; $data = [ 'success' => true ];
        if ( in_array( $event, [ 'student_registered', 'instructor_registered' ], true ) ) {
            $user_id = (int) ( $args[0] ?? 0 );
            if ( ! $user_id || ! get_userdata( $user_id ) ) { return false; }
            return array_merge( $data, self::user_data( $user_id ) );
        }
        if ( isset( self::publication_types()[ $event ] ) ) {
            $p = $args[2] ?? null;
            if ( 'publish' !== ( $args[0] ?? '' ) || 'publish' === ( $args[1] ?? '' ) || ! is_object( $p ) || self::publication_types()[ $event ] !== ( $p->post_type ?? '' ) ) { return false; }
            $id = (int) $p->ID;
            $data += [ 'post_id' => $id, 'post_title' => (string) $p->post_title, 'post_url' => (string) get_permalink( $id ), 'post_type' => (string) $p->post_type, 'previous_status' => (string) $args[1] ];
            $user_id = (int) $p->post_author;
            $data = array_merge( $data, self::user_data( $user_id ) );
            if ( 'course_published' === $event ) { $data = array_merge( $data, self::course_data( $id ) ); }
            if ( 'quiz_published' === $event ) { $data['quiz_id'] = $id; $data['quiz_title'] = (string) $p->post_title; }
            return $data;
        }
        switch ( $event ) {
            case 'user_enroll_course':
                $course_id = (int) ( $args[0] ?? 0 );
                $data['enroll_id'] = (int) ( $args[1] ?? 0 );
                $user_id = (int) ( $args[2] ?? 0 );
                if ( ! $data['enroll_id'] ) { return false; }
                break;
            case 'course_complete':
                $course_id = (int) ( $args[0] ?? 0 ); $user_id = (int) ( $args[1] ?? 0 ); break;
            case 'lesson_published':
                $raw = self::raw( $args[0] ?? null );
                $id = (int) ( $raw['ID'] ?? $raw['id'] ?? 0 );
                if ( ! $id || 'publish' !== ( $raw['lesson_status'] ?? '' ) ) { return false; }
                // The Academy hook also runs on UPDATE; only accept creation timestamp.
                if ( isset( $raw['lesson_date'], $raw['lesson_modified'] ) && $raw['lesson_date'] !== $raw['lesson_modified'] ) { return false; }
                if ( ! self::matches( $config, 'lesson_id', $id ) ) { return false; }
                $data['lesson_id'] = $id;
                $data['lesson_title'] = (string) ( $raw['lesson_title'] ?? '' );
                $user_id = (int) ( $raw['lesson_author'] ?? 0 );
                $course_id = (int) ( $raw['course_id'] ?? 0 );
                if ( ! self::matches( $config, 'course_id', $course_id ) ) { return false; }
                return array_merge( $data, self::user_data( $user_id ), self::course_data( $course_id ) );
            case 'lesson_complete': case 'assignment_completed': case 'tutor_booking_completed': case 'zoom_meeting_completed':
                if ( ( $args[0] ?? '' ) !== self::topic_events()[ $event ] ) { return false; }
                $course_id = (int) ( $args[1] ?? 0 );
                $id = (int) ( $args[2] ?? 0 );
                $user_id = (int) ( $args[3] ?? 0 );
                if ( ! $id ) { return false; }
                if ( 'lesson_complete' === $event ) {
                    if ( ! self::matches( $config, 'lesson_id', $id ) ) { return false; }
                    $data['lesson_id'] = $id;
                } else {
                    if ( ! self::matches( $config, 'topic_id', $id ) ) { return false; }
                    $data['topic_id'] = $id;
                    $data['topic_type'] = self::topic_events()[ $event ];
                }
                break;
            case 'course_review_submitted':
                $id = (int) ( $args[0] ?? 0 );
                $course_id = (int) ( $args[1] ?? 0 );
                $c = $id ? get_comment( $id ) : null;
                if ( ! $c || ! $course_id ) { return false; }
                $user_id = (int) $c->user_id;
                $data += [ 'comment_id' => $id, 'content' => (string) $c->comment_content, 'rating' => (float) ( $args[2] ?? 0 ) ];
                break;
            case 'course_question_asked': case 'course_question_replied':
                $raw = self::raw( $args[0] ?? null );
                $expected = 'course_question_asked' === $event ? 'waiting_for_answer' : 'answered';
                if ( ( $raw['status'] ?? '' ) !== $expected ) { return false; }
                $payload = self::comment_payload( $raw );
                if ( ! $payload ) { return false; }
                $course_id = (int) $payload['course_id']; $user_id = (int) $payload['user_id'];
                $data = array_merge( $data, $payload );
                $data['question_title'] = (string) get_comment_meta( $payload['comment_id'], 'academy_question_title', true );
                break;
            case 'assignment_submitted': case 'assignment_evaluated':
                $raw = self::raw( $args[0] ?? null );
                $id = (int) ( $raw['comment_ID'] ?? 0 );
                $assignment_id = (int) ( $raw['comment_post_ID'] ?? 0 );
                $user_id = (int) ( $raw['user_id'] ?? 0 );
                $course_id = (int) ( $raw['comment_parent'] ?? 0 );
                if ( ! $id || ! $assignment_id || ! $user_id ) { return false; }
                $meta = self::raw( $raw['meta'] ?? [] );
                $data += [
                    'submission_id' => $id, 'assignment_id' => $assignment_id,
                    'content' => (string) ( $raw['comment_content'] ?? '' ),
                    'status' => (string) ( $raw['comment_approved'] ?? '' ),
                ];
                if ( 'assignment_evaluated' === $event ) {
                    $data['evaluate_point'] = $meta['academy_pro_assignment_evaluate_point'] ?? get_comment_meta( $id, 'academy_pro_assignment_evaluate_point', true );
                    $data['evaluate_feedback'] = $meta['academy_pro_assignment_evaluate_feedback'] ?? get_comment_meta( $id, 'academy_pro_assignment_evaluate_feedback', true );
                }
                break;
            case 'tutor_booking_booked':
                $id = (int) ( $args[0] ?? 0 ); $booked = (int) ( $args[1] ?? 0 ); $user_id = (int) ( $args[2] ?? 0 );
                if ( ! $id || ! $booked || ! $user_id ) { return false; }
                $data += [ 'booking_id' => $id, 'booked_id' => $booked,
                    'booked_time' => get_post_meta( $booked, '_academy_booked_schedule_time', true ) ];
                return array_merge( $data, self::user_data( $user_id ) );
            case 'tutor_booking_review_submitted':
                $id = (int) ( $args[0] ?? 0 );
                $c = $id ? get_comment( $id ) : null;
                if ( ! $c ) { return false; }
                $data += [ 'comment_id' => $id, 'booking_id' => (int) $c->comment_post_ID,
                    'rating' => (float) ( $args[1] ?? 0 ), 'content' => (string) $c->comment_content ];
                return array_merge( $data, self::user_data( (int) $c->user_id ) );
            case 'zoom_meeting_published':
                $id = (int) ( $args[0] ?? 0 );
                if ( ! $id ) { return false; }
                $data += [ 'zoom_id' => $id, 'zoom_title' => (string) get_the_title( $id ) ];
                return $data; // Do not expose Zoom join/start URLs or host credentials.
        }
        if ( $course_id <= 0 || $user_id <= 0 || ! self::matches( $config, 'course_id', $course_id ) ) { return false; }
        return array_merge( $data, self::course_data( $course_id ), self::user_data( $user_id ) );
    }

    public static function get_trigger_sample_output( string $event ): array {
        if ( ! isset( self::get_triggers()[ $event ] ) ) { return []; }
        $sample = array_merge( [ 'success' => true ], [ 'user_id' => 1, 'user_email' => 'student@example.com', 'first_name' => 'Jane', 'last_name' => 'Doe', 'username' => 'jane' ] );
        if ( in_array( $event, [ 'student_registered', 'instructor_registered' ], true ) ) { return $sample; }
        $sample += [ 'course_id' => 42, 'course_title' => 'Sample course', 'course_url' => 'https://example.com/course/' ];
        if ( in_array( $event, self::quiz_events(), true ) ) {
            $sample += [ 'quiz_id' => 11, 'quiz_title' => 'Quiz', 'attempt_id' => 55, 'attempt_status' => 'passed', 'score' => 8, 'total' => 10, 'total_marks' => 10, 'percentage' => 80.0 ];
        }
        if ( isset( self::topic_events()[ $event ] ) ) { $sample += [ 'topic_id' => 12, 'topic_type' => self::topic_events()[ $event ] ]; }
        if ( 'lesson_complete' === $event || 'lesson_published' === $event ) { $sample['lesson_id'] = 12; }
        if ( 'user_enroll_course' === $event ) { $sample['enroll_id'] = 66; }
        if ( isset( self::publication_types()[ $event ] ) ) { $sample += [ 'post_id' => 42, 'post_title' => 'Sample', 'previous_status' => 'draft' ]; }
        if ( in_array( $event, [ 'course_question_asked', 'course_question_replied', 'course_review_submitted', 'tutor_booking_review_submitted' ], true ) ) { $sample += [ 'comment_id' => 1, 'content' => 'Sample text' ]; }
        if ( in_array( $event, [ 'assignment_submitted', 'assignment_evaluated' ], true ) ) { $sample += [ 'assignment_id' => 22, 'submission_id' => 23 ]; }
        if ( 'assignment_evaluated' === $event ) { $sample += [ 'evaluate_point' => 9, 'evaluate_feedback' => 'Good work' ]; }
        if ( 'tutor_booking_booked' === $event ) { $sample += [ 'booking_id' => 12, 'booked_id' => 24 ]; }
        if ( 'zoom_meeting_published' === $event ) { $sample += [ 'zoom_id' => 21 ]; }
        return $sample;
    }
}
