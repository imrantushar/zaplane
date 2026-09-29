<?php
namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Academy;

/** Replace the branch's old AcademyTest: its removed event IDs are no longer supported. */
class AcademyTest extends IntegrationTestCase {
    protected function getIntegrationClass(): string { return Academy::class; }

    public function test_exact_27_triggers(): void {
        $this->assertSame( [
            'user_enroll_course', 'lesson_complete', 'course_complete', 'quiz_attempt_submitted',
            'quiz_passed', 'quiz_failed', 'quiz_target', 'course_published', 'lesson_published',
            'quiz_published', 'student_registered', 'instructor_registered',
            'course_review_submitted', 'course_question_asked', 'course_question_replied',
            'announcement_published', 'assignment_published', 'assignment_submitted',
            'assignment_evaluated', 'assignment_completed', 'tutor_booking_published',
            'tutor_booking_booked', 'tutor_booking_completed', 'tutor_booking_review_submitted',
            'zoom_meeting_published', 'zoom_meeting_completed', 'course_bundle_published',
        ], array_keys( Academy::get_triggers() ) );
    }
    public function test_exact_9_actions(): void {
        $this->assertSame( [
            'enroll-course', 'unenroll-course', 'complete-course', 'complete-lesson',
            'reset-course-progress', 'add-to-wishlist', 'remove-from-wishlist',
            'assign-instructor', 'remove-instructor',
        ], array_keys( Academy::get_actions() ) );
    }
    public function test_actions_and_triggers_have_schema_and_sample_output(): void {
        foreach ( Academy::get_triggers() as $id => $spec ) {
            $this->assertNotEmpty( $spec['hook'], $id );
            $this->assertIsArray( Academy::get_trigger_config_schema( $id ), $id );
            $this->assertTrue( Academy::get_trigger_sample_output( $id )['success'], $id );
        }
        foreach ( Academy::get_actions() as $id => $spec ) {
            $this->assertIsArray( Academy::get_action_config_schema( $id ), $id );
            $this->assertTrue( Academy::get_action_sample_output( $id )['success'], $id );
        }
    }
    public function test_rejects_quiz_threshold_without_total_marks(): void {
        $node = [ 'event' => 'quiz_target', 'config' => [ 'quiz_id' => 'any', 'course_id' => 'any', 'target_percentage' => 70 ] ];
        $attempt = (object) [ 'quiz_id' => 10, 'course_id' => 2, 'user_id' => 1, 'attempt_status' => 'passed', 'earned_marks' => 7, 'total_marks' => 0 ];
        $this->assertFalse( Academy::resolve_trigger( $node, [ $attempt ] ) );
    }
    public function test_rejects_lesson_when_topic_is_not_lesson(): void {
        $node = [ 'event' => 'lesson_complete', 'config' => [ 'lesson_id' => 'any', 'course_id' => 'any' ] ];
        $this->assertFalse( Academy::resolve_trigger( $node, [ 'quiz', 2, 3, 1 ] ) );
    }
    public function test_unrelated_post_publication_does_not_fire(): void {
        $post = (object) [ 'ID' => 3, 'post_type' => 'post', 'post_title' => 'Regular post', 'post_author' => 1 ];
        $this->assertFalse( Academy::resolve_trigger( [ 'event' => 'course_published' ], [ 'publish', 'draft', $post ] ) );
    }
    public function test_only_newly_published_post_fires(): void {
        $post = (object) [ 'ID' => 3, 'post_type' => 'academy_courses', 'post_title' => 'Course', 'post_author' => 1 ];
        $this->assertFalse( Academy::resolve_trigger( [ 'event' => 'course_published' ], [ 'publish', 'publish', $post ] ) );
    }
    public function test_unknown_actions_are_rejected(): void {
        $this->expectException( \InvalidArgumentException::class );
        Academy::execute_node( [ 'event' => 'get-course-progress' ], [] );
    }
}
