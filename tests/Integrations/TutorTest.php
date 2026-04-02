<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Tutor;
use Zaplane\Tests\WPMocks;

/**
 * Test suite for the Tutor LMS integration.
 */
class TutorTest extends IntegrationTestCase {

    // -------------------------------------------------------------------------
    // Contract
    // -------------------------------------------------------------------------

    protected function getIntegrationClass(): string {
        return Tutor::class;
    }

    // -------------------------------------------------------------------------
    // Setup & Helpers
    // -------------------------------------------------------------------------

    private $tutorUtilsMock;

    protected function setUp(): void {
        parent::setUp();
        $this->mockTutorUtils();
    }

    protected function tearDown(): void {
        parent::tearDown();
        // Clean up the mock
        $this->tutorUtilsMock = null;
    }

    /**
     * Mock tutor_utils() function in global namespace
     */
    private function mockTutorUtils($attempt = null): void {
        // Create a mock utils object
        $this->tutorUtilsMock = new class {
            public $attempt;
            public function get_attempt($id) {
                return $this->attempt;
            }
        };

        if ($attempt) {
            $this->tutorUtilsMock->attempt = $attempt;
        }

        // Define the function in global namespace if not already defined
        if (!function_exists('tutor_utils')) {
            eval('
                namespace {
                    function tutor_utils() {
                        return \Zaplane\Tests\Integrations\TutorTest::getTutorUtilsMock();
                    }
                }
            ');
        }
    }

    /**
     * Static getter for the mock (used by the global function)
     */
    public static function getTutorUtilsMock() {
        $test = null;
        foreach (debug_backtrace() as $trace) {
            if (isset($trace['object']) && $trace['object'] instanceof self) {
                $test = $trace['object'];
                break;
            }
        }
        return $test ? $test->tutorUtilsMock : null;
    }

    private function makeAttempt(array $overrides = []): object {
        return (object) array_merge([
            'attempt_id'    => 100,
            'quiz_id'       => 10,
            'user_id'       => 1,
            'earned_marks'  => 8,
            'total_marks'   => 10,
            'attempt_status' => 'attempt_ended',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // CONTRACT TESTS (Auto-run by parent)
    // -------------------------------------------------------------------------

    protected function getTriggerTests(): array {
        return [
            'user_enroll_course'       => [42, 7],          // course_id, enroll_id
            'lesson_complete'          => [5, 1],           // lesson_id, user_id
            'tutor_quiz_course_attempt' => [100],           // attempt_id
            'quiz_target'               => [100],           // attempt_id (with config)
        ];
    }

    protected function getActionTests(): array {
        return []; // No actions
    }

    // -------------------------------------------------------------------------
    // TRIGGER: user_enroll_course
    // -------------------------------------------------------------------------

    public function test_trigger_user_enroll_course_success(): void {
        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('user_enroll_course'),
            [42, 7]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(42, $result['course_id']);
        $this->assertEquals(7, $result['enroll_id']);
    }

    public function test_trigger_user_enroll_course_returns_false_without_course(): void {
        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('user_enroll_course'),
            [0, 7]
        );
        $this->assertFalse($result);
    }

    public function test_trigger_user_enroll_course_returns_false_without_enroll(): void {
        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('user_enroll_course'),
            [42, null]
        );
        $this->assertFalse($result);
    }

    public function test_trigger_user_enroll_course_filters_by_selected_course(): void {
        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('user_enroll_course', ['course_id' => '99']),
            [42, 7]
        );
        $this->assertFalse($result);
    }

    public function test_trigger_user_enroll_course_passes_when_course_matches(): void {
        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('user_enroll_course', ['course_id' => '42']),
            [42, 7]
        );

        $this->assertIsArray($result);
        $this->assertEquals(42, $result['course_id']);
    }

    public function test_trigger_user_enroll_course_passes_when_config_any(): void {
        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('user_enroll_course', ['course_id' => 'any']),
            [42, 7]
        );
        $this->assertIsArray($result);
        $this->assertEquals(42, $result['course_id']);
    }

    // -------------------------------------------------------------------------
    // TRIGGER: lesson_complete
    // -------------------------------------------------------------------------

    public function test_trigger_lesson_complete_success(): void {
        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('lesson_complete'),
            [5, 1]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['lesson_id']);
        $this->assertEquals(1, $result['user_id']);
    }

    public function test_trigger_lesson_complete_returns_false_without_lesson(): void {
        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('lesson_complete'),
            [0, 1]
        );
        $this->assertFalse($result);
    }

    public function test_trigger_lesson_complete_returns_false_without_user(): void {
        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('lesson_complete'),
            [5, 0]
        );
        $this->assertFalse($result);
    }

    public function test_trigger_lesson_complete_filters_by_selected_lesson(): void {
        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('lesson_complete', ['lesson_id' => '99']),
            [5, 1]
        );
        $this->assertFalse($result);
    }

    public function test_trigger_lesson_complete_passes_when_lesson_matches(): void {
        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('lesson_complete', ['lesson_id' => '5']),
            [5, 1]
        );

        $this->assertIsArray($result);
        $this->assertEquals(5, $result['lesson_id']);
    }

    // -------------------------------------------------------------------------
    // TRIGGER: tutor_quiz_course_attempt
    // -------------------------------------------------------------------------

    public function test_trigger_quiz_attempt_success(): void {
        $attempt = $this->makeAttempt();
        $this->tutorUtilsMock->attempt = $attempt;

        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('tutor_quiz_course_attempt'),
            [100]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['quiz_id']);
        $this->assertEquals(1, $result['user_id']);
    }

    public function test_trigger_quiz_attempt_returns_false_without_attempt_id(): void {
        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('tutor_quiz_course_attempt'),
            [null]
        );
        $this->assertFalse($result);
    }

    public function test_trigger_quiz_attempt_returns_false_when_attempt_not_found(): void {
        $this->tutorUtilsMock->attempt = null;

        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('tutor_quiz_course_attempt'),
            [999]
        );
        $this->assertFalse($result);
    }

    public function test_trigger_quiz_attempt_filters_by_selected_quiz(): void {
        $attempt = $this->makeAttempt(['quiz_id' => 10]);
        $this->tutorUtilsMock->attempt = $attempt;

        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('tutor_quiz_course_attempt', ['quiz_id' => '99']),
            [100]
        );
        $this->assertFalse($result);
    }

    public function test_trigger_quiz_attempt_passes_when_quiz_matches(): void {
        $attempt = $this->makeAttempt(['quiz_id' => 10]);
        $this->tutorUtilsMock->attempt = $attempt;

        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('tutor_quiz_course_attempt', ['quiz_id' => '10']),
            [100]
        );

        $this->assertIsArray($result);
        $this->assertEquals(10, $result['quiz_id']);
    }

    // -------------------------------------------------------------------------
    // TRIGGER: quiz_target
    // -------------------------------------------------------------------------

    public function test_trigger_quiz_target_success(): void {
        $attempt = $this->makeAttempt([
            'earned_marks' => 8,
            'total_marks'  => 10
        ]);
        $this->tutorUtilsMock->attempt = $attempt;

        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('quiz_target', [
                'quiz_id'           => 'any',
                'target_percentage' => '70'
            ]),
            [100]
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['quiz_id']);
        $this->assertEquals(1, $result['user_id']);
        $this->assertEquals(8, $result['score']);
        $this->assertEquals(10, $result['total_marks']);
        $this->assertEquals(80.0, $result['percentage']);
    }

    public function test_trigger_quiz_target_returns_false_when_target_not_met(): void {
        $attempt = $this->makeAttempt([
            'earned_marks' => 4,
            'total_marks'  => 10
        ]);
        $this->tutorUtilsMock->attempt = $attempt;

        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('quiz_target', [
                'quiz_id'           => 'any',
                'target_percentage' => '70'
            ]),
            [100]
        );

        $this->assertFalse($result);
    }

    public function test_trigger_quiz_target_filters_by_selected_quiz(): void {
        $attempt = $this->makeAttempt([
            'quiz_id' => 10,
            'earned_marks' => 8,
            'total_marks' => 10
        ]);
        $this->tutorUtilsMock->attempt = $attempt;

        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('quiz_target', [
                'quiz_id'           => '99',
                'target_percentage' => '70'
            ]),
            [100]
        );
        $this->assertFalse($result);
    }

    public function test_trigger_quiz_target_returns_false_without_attempt(): void {
        $this->tutorUtilsMock->attempt = null;

        $result = Tutor::resolve_trigger(
            $this->makeTriggerNode('quiz_target', [
                'target_percentage' => '70'
            ]),
            [null]
        );
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // ACTIONS
    // -------------------------------------------------------------------------

    public function test_execute_node_passthrough(): void {
        $input = ['quiz_id' => 10, 'some' => 'data'];

        $result = Tutor::execute_node(
            $this->makeActionNode('__any__'),
            $input
        );

        $this->assertEquals('main', $result['port']);
        $this->assertEquals($input, $result['data']);
    }

    // -------------------------------------------------------------------------
    // SCHEMA TESTS
    // -------------------------------------------------------------------------

    public function test_get_slug(): void {
        $this->assertEquals('tutor', Tutor::get_slug());
    }

    public function test_get_name(): void {
        $this->assertEquals('Tutor LMS', Tutor::get_name());
    }

    public function test_get_icon(): void {
        $this->assertEquals('tutorlms.svg', Tutor::get_icon());
    }

    public function test_get_actions_empty(): void {
        $this->assertSame([], Tutor::get_actions());
    }

    public function test_get_action_config_schema_returns_empty_array(): void {
        $this->assertSame([], Tutor::get_action_config_schema('any_action'));
    }

    public function test_trigger_config_schemas_are_valid(): void {
        $triggers = Tutor::get_triggers();

        foreach (array_keys($triggers) as $trigger) {
            $schema = Tutor::get_trigger_config_schema($trigger);

            // Schema can be empty or array
            $this->assertIsArray($schema);

            // If not empty, check structure
            if (!empty($schema)) {
                foreach ($schema as $field) {
                    $this->assertArrayHasKey('key', $field);
                    $this->assertArrayHasKey('label', $field);
                    $this->assertArrayHasKey('type', $field);
                }
            }
        }
    }

    public function test_trigger_config_schema_unknown_returns_empty(): void {
        $this->assertSame([], Tutor::get_trigger_config_schema('__unknown__'));
    }

    public function test_user_enroll_course_has_course_id_config(): void {
        $schema = Tutor::get_trigger_config_schema('user_enroll_course');
        $this->assertNotEmpty($schema);
        $this->assertEquals('course_id', $schema[0]['key']);
    }

    public function test_lesson_complete_has_lesson_id_config(): void {
        $schema = Tutor::get_trigger_config_schema('lesson_complete');
        $this->assertNotEmpty($schema);
        $this->assertEquals('lesson_id', $schema[0]['key']);
    }

    public function test_quiz_attempt_has_quiz_id_config(): void {
        $schema = Tutor::get_trigger_config_schema('tutor_quiz_course_attempt');
        $this->assertNotEmpty($schema);
        $this->assertEquals('quiz_id', $schema[0]['key']);
    }

    public function test_quiz_target_has_quiz_id_and_target_config(): void {
        $schema = Tutor::get_trigger_config_schema('quiz_target');
        $this->assertCount(2, $schema);
        $this->assertEquals('quiz_id', $schema[0]['key']);
        $this->assertEquals('target_percentage', $schema[1]['key']);
    }

    // -------------------------------------------------------------------------
    // OUTPUT PORTS
    // -------------------------------------------------------------------------

    public function test_get_output_ports_returns_array(): void {
        $ports = Tutor::get_output_ports();
        $this->assertIsArray($ports);
        // At minimum should have 'main' port
        $this->assertArrayHasKey('main', $ports);
    }

    // -------------------------------------------------------------------------
    // DYNAMIC QUERIES
    // -------------------------------------------------------------------------

    public function test_dynamic_queries_registered(): void {
        $queries = Tutor::get_dynamic_queries();

        $expected = ['course', 'quiz', 'lesson'];
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $queries);
            $this->assertIsCallable($queries[$key]);
        }
    }

    public function test_query_courses_returns_at_least_any_option(): void {
        $options = Tutor::query_courses();
        $this->assertIsArray($options);
        $this->assertNotEmpty($options);
        $this->assertEquals('any', $options[0]['name']);
        $this->assertEquals('Any Course', $options[0]['label']);
    }

    public function test_query_quiz_returns_at_least_any_option(): void {
        $options = Tutor::query_quiz();
        $this->assertIsArray($options);
        $this->assertNotEmpty($options);
        $this->assertEquals('any', $options[0]['name']);
        $this->assertEquals('Any Quiz', $options[0]['label']);
    }

    public function test_query_lesson_returns_at_least_any_option(): void {
        $options = Tutor::query_lesson();
        $this->assertIsArray($options);
        $this->assertNotEmpty($options);
        $this->assertEquals('any', $options[0]['name']);
        $this->assertEquals('Any lesson', $options[0]['label']);
    }

    // -------------------------------------------------------------------------
    // HELPER: Test that triggers return array|false (contract)
    // -------------------------------------------------------------------------

    public function test_all_triggers_follow_contract(): void {
        $triggers = Tutor::get_triggers();

        // Test user_enroll_course
        $result1 = Tutor::resolve_trigger(
            $this->makeTriggerNode('user_enroll_course'),
            [42, 7]
        );
        $this->assertThat($result1, $this->logicalOr(
            $this->isType('array'),
            $this->identicalTo(false)
        ));

        // Test lesson_complete
        $result2 = Tutor::resolve_trigger(
            $this->makeTriggerNode('lesson_complete'),
            [5, 1]
        );
        $this->assertThat($result2, $this->logicalOr(
            $this->isType('array'),
            $this->identicalTo(false)
        ));

        // For quiz triggers, we need mock
        $attempt = $this->makeAttempt();
        $this->tutorUtilsMock->attempt = $attempt;

        $result3 = Tutor::resolve_trigger(
            $this->makeTriggerNode('tutor_quiz_course_attempt'),
            [100]
        );
        $this->assertThat($result3, $this->logicalOr(
            $this->isType('array'),
            $this->identicalTo(false)
        ));

        $result4 = Tutor::resolve_trigger(
            $this->makeTriggerNode('quiz_target', ['target_percentage' => '70']),
            [100]
        );
        $this->assertThat($result4, $this->logicalOr(
            $this->isType('array'),
            $this->identicalTo(false)
        ));
    }
}
