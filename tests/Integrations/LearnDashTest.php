<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Learndash;

class LearnDashTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Learndash::class;
    }

    public function test_user_enroll_course(): void
    {
        $node = $this->makeTriggerNode('user_enroll_course', [
            'config' => ['course_id' => 'any']
        ] );

        $user_id = 1;
        $course_id = 10;

        $result = Learndash::resolve_trigger( $node, [ $user_id, $course_id ] );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertEquals( $course_id, $result['data']['course_id'] );
        $this->assertEquals( $user_id, $result['data']['user_id'] );
    }

    public function test_course_complete(): void
    {
        $node = $this->makeTriggerNode('course_complete', [
            'config' => ['course_id' => 'any']
        ]);

        $user   = new \LD_User(2);
        $course = new \LD_Course(20);
        $result = Learndash::resolve_trigger( $node, [ ['user' => $user, 'course' => $course ] ] );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertEquals( 20, $result['data']['course_id'] );
        $this->assertEquals( 2, $result['data']['user_id'] );
    }

    public function test_lesson_complete(): void
    {
        $node = $this->makeTriggerNode('lesson_complete', [
            'config' => ['lesson_id' => 'any']
        ]);

        $lesson_id = 30;
        $user_id   = 3;
        $result    = Learndash::resolve_trigger( $node, [ $lesson_id, $user_id ] );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertEquals( $lesson_id, $result['data']['lesson_id'] );
        $this->assertEquals( $user_id, $result['data']['user_id'] );
    }

    public function test_topic_complete(): void
    {
        $node = $this->makeTriggerNode('topic_complete', [
            'config' => ['topic_id' => 'any']
        ]);

        $user   = new \LD_User(4);
        $course = new \LD_Course(40);
        $lesson = new \LD_Lesson(50);
        $topic  = new \LD_Topic(60);
        $data   = ['user' => $user, 'course' => $course, 'lesson' => $lesson, 'topic' => $topic ];
        $result = Learndash::resolve_trigger($node, [$data]);

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertEquals( 60, $result['data']['topic_id'] );
        $this->assertEquals( 4, $result['data']['user_id'] );
    }

    public function test_quiz_attempt(): void
    {
        $node = $this->makeTriggerNode('quiz_attempt', [
            'config' => ['quiz_id' => 'any']
        ]);

        $user   = new \LD_User(5);
        $data   = ['course' => 70, 'lesson' => 80, 'quiz' => 90, 'score' => 95, 'pass' => true ];
        $result = Learndash::resolve_trigger( $node, [ $data, $user ] );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertEquals( 90, $result['data']['quiz_id'] );
        $this->assertEquals( 5, $result['data']['user_id'] );
    }

    public function test_added_removed_group(): void
    {
        $triggers = ['added_group', 'removed_group'];

        foreach ( $triggers as $trigger ) {
            $node     = $this->makeTriggerNode( $trigger, ['config' => ['group_id' => 'any'] ] );
            $user_id  = 6;
            $group_id = 100;
            $result   = Learndash::resolve_trigger( $node, [ $user_id, $group_id ] );

            $this->assertIsArray( $result );
            $this->assertTrue( $result['success'] );
            $this->assertEquals( $group_id, $result['data']['group_id'] );
            $this->assertEquals( $user_id, $result['data']['user_id'] );
        }
    }

    public function test_lesson_assignment(): void
    {
        $node = $this->makeTriggerNode('lesson_assignment', [
            'config' => ['lesson_id' => 'any']
        ]);

        $assignment = [
            'user_id'   => 7,
            'course_id' => 200,
            'lesson_id' => 300,
            'file_name' => 'assignment.pdf',
            'file_link' => 'https://example.com/assignment.pdf',
            'file_path' => '/tmp/assignment.pdf',
        ];

        $result = Learndash::resolve_trigger( $node, [ $assignment, 1 ] );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertEquals( 200, $result['data']['course_id'] );
        $this->assertEquals( 300, $result['data']['lesson_id'] );
        $this->assertEquals( 7, $result['data']['user_id'] );
    }
}
