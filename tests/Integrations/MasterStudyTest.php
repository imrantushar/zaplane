<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Masterstudy;

class MasterstudyTest extends IntegrationTestCase
{
    protected function getIntegrationClass(): string
    {
        return Masterstudy::class;
    }

    public function test_user_enroll_course(): void
    {
        $node = $this->makeTriggerNode('user_enroll_course', [
            'data' => ['config' => ['course_id' => 101] ],
        ]);

        $result = Masterstudy::resolve_trigger(
            $node,
            [1, 101]
        );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertEquals( 1, $result['data']['user_id'] );
        $this->assertEquals( 101, $result['data']['course_id'] );
    }

    public function test_course_complete(): void
    {
        $node = $this->makeTriggerNode('course_complete', [
            'data' => ['config' => ['course_id' => 101] ],
        ]);

        $result = Masterstudy::resolve_trigger(
            $node,
            [101, 1]
        );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertEquals( 1, $result['data']['user_id'] );
        $this->assertEquals( 101, $result['data']['course_id'] );
    }

    public function test_lesson_complete(): void
    {
        $node = $this->makeTriggerNode('lesson_complete', [
            'data' => ['config' => ['lesson_id' => 201] ],
        ]);

        $result = Masterstudy::resolve_trigger(
            $node,
            [1, 201]
        );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertEquals( 1, $result['data']['user_id'] );
        $this->assertEquals( 201, $result['data']['lesson_id'] );
    }

    public function test_quiz_passed(): void
    {
        $node = $this->makeTriggerNode('quiz_passed', [
            'data' => ['config' => ['quiz_id' => 301] ],
        ]);

        $result = Masterstudy::resolve_trigger(
            $node,
            [1, 301, 90]
        );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertEquals( 'passed', $result['data']['status'] );
        $this->assertEquals( 90, $result['data']['score'] );
    }

    public function test_quiz_failed(): void
    {
        $node = $this->makeTriggerNode('quiz_failed', [
            'data' => ['config' => ['quiz_id' => 302] ],
        ]);

        $result = Masterstudy::resolve_trigger(
            $node,
            [1, 302, 40]
        );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertEquals( 'failed', $result['data']['status'] );
        $this->assertEquals( 40, $result['data']['score'] );
    }
}