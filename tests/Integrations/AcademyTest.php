<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Academy;

/**
 * Test suite for the Academy LMS integration.
 *
 * ── How this file is structured ──────────────────────────────────────────────
 *
 * 1. CONTRACT (inherited)
 *    IntegrationTestCase auto-runs:
 *      - all_tested_triggers_are_registered   — every key in getTriggerTests()
 *                                               must exist in get_triggers()
 *      - triggers_fire_and_return_payload      — bulk: each trigger returns array|false
 *      - actions_execute_and_return_valid_format — bulk: each action returns {port,data}
 *
 * 2. TRIGGER TESTS  (hand-written, one per trigger × happy + sad path)
 *    Each trigger has:
 *      - A happy-path test: valid args → assert key fields in the returned array.
 *      - A sad-path test:   invalid/missing args → assert false.
 *      - Any edge-case tests specific to that trigger's logic.
 *
 * 3. ACTION TESTS
 *    Academy has no actions — execute_node() is a passthrough.
 *    We verify the contract and passthrough behaviour only.
 *
 * ── Reading guide for team members ───────────────────────────────────────────
 *
 *  makeTriggerNode($event, $config)
 *      Builds the $node array that resolve_trigger() receives.
 *      $config maps to $node['data']['config'] — this is how "selected course"
 *      / "selected quiz" / "target percentage" etc. get passed in.
 *
 *  makeAttempt(array $overrides)
 *      Builds the stdClass object that Academy fires for quiz events.
 *      Fields: quiz_id, user_id, earned_marks, total_marks, attempt_status.
 *
 *  getTriggerTests()
 *      Provides minimal valid args for each trigger to satisfy the bulk runner.
 *      The bulk runner only checks array|false — detailed checks are in the
 *      hand-written tests below.
 *
 * ── Triggers covered ─────────────────────────────────────────────────────────
 *
 *   user_enroll_course         — fired when a user enrolls
 *   course_complete            — fired when a user finishes a course
 *   lesson_complete            — fired when a user finishes a lesson
 *   academy_quiz_course_attempt — fired when a quiz attempt is submitted
 *   quiz_target                — fired when a quiz attempt meets a % target
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AcademyTest extends IntegrationTestCase {

	// -------------------------------------------------------------------------
	// Contract
	// -------------------------------------------------------------------------

	protected function getIntegrationClass(): string {
		return Academy::class;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a quiz attempt object — the argument Academy fires for quiz events.
	 *
	 * @param array $overrides Override any default field.
	 */
	private function makeAttempt( array $overrides = [] ): object {
		return (object) array_merge( [
			'quiz_id'        => 10,
			'user_id'        => 1,
			'earned_marks'   => 8,
			'total_marks'    => 10,
			'attempt_status' => 'attempt_ended',
		], $overrides );
	}

	// -------------------------------------------------------------------------
	// Bulk runner data
	// -------------------------------------------------------------------------

	protected function getTriggerTests(): array {
		return [
			// course_id / enroll_id are passed as positional args.
			'user_enroll_course'          => [ 1, 99 ],

			// course_complete: course_id, user_id
			'course_complete'             => [ 1, 1 ],

			// lesson_complete: lesson_id, user_id
			'lesson_complete'             => [ 5, 1 ],

			// quiz attempt object with attempt_ended status
			'academy_quiz_course_attempt' => [ $this->makeAttempt() ],

			// quiz_target: same attempt object; node config sets target=50 (we scored 80%)
			'quiz_target'                 => [ $this->makeAttempt() ],
		];
	}

	// =========================================================================
	// TRIGGER: user_enroll_course
	// =========================================================================

	public function test_trigger_user_enroll_course_returns_course_and_enroll_ids(): void {
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'user_enroll_course' ),
			[ 42, 7 ]   // course_id=42, enroll_id=7
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 42, $result['course_id'] );
		$this->assertEquals( 7,  $result['enroll_id'] );
	}

	public function test_trigger_user_enroll_course_returns_false_without_course_id(): void {
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'user_enroll_course' ),
			[ 0, 7 ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_user_enroll_course_returns_false_without_enroll_id(): void {
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'user_enroll_course' ),
			[ 42, null ]
		);
		$this->assertFalse( $result );
	}

	/**
	 * When the node is configured for a specific course, enrollments in a
	 * different course must be filtered out.
	 */
	public function test_trigger_user_enroll_course_filters_by_selected_course(): void {
		// Node configured for course 99, but enrollment is for course 42.
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'user_enroll_course', [ 'course_id' => '99' ] ),
			[ 42, 7 ]
		);
		$this->assertFalse( $result );
	}

	/**
	 * When node config is 'any', ALL courses should pass through.
	 */
	public function test_trigger_user_enroll_course_passes_any_course_when_config_is_any(): void {
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'user_enroll_course', [ 'course_id' => 'any' ] ),
			[ 42, 7 ]
		);
		$this->assertIsArray( $result );
		$this->assertEquals( 42, $result['course_id'] );
	}

	// =========================================================================
	// TRIGGER: course_complete
	// =========================================================================

	public function test_trigger_course_complete_returns_course_and_user_details(): void {
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'course_complete' ),
			[ 1, 1 ]   // course_id=1, user_id=1 (seeded in WPMocks)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 1, $result['course_id'] );
		$this->assertEquals( 1, $result['user_id'] );
		$this->assertArrayHasKey( 'user_email',    $result );
		$this->assertArrayHasKey( 'course_title',  $result );
		$this->assertArrayHasKey( 'course_url',    $result );
	}

	public function test_trigger_course_complete_returns_false_without_course_id(): void {
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'course_complete' ),
			[ 0, 1 ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_course_complete_returns_false_without_user_id(): void {
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'course_complete' ),
			[ 1, 0 ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_course_complete_filters_by_selected_course(): void {
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'course_complete', [ 'course_id' => '999' ] ),
			[ 1, 1 ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_course_complete_passes_when_course_matches_config(): void {
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'course_complete', [ 'course_id' => '1' ] ),
			[ 1, 1 ]
		);
		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['course_id'] );
	}

	// =========================================================================
	// TRIGGER: lesson_complete
	// =========================================================================

	public function test_trigger_lesson_complete_returns_lesson_and_user_ids(): void {
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'lesson_complete' ),
			[ 5, 1 ]   // lesson_id=5, user_id=1
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 5, $result['lesson_id'] );
		$this->assertEquals( 1, $result['user_id'] );
	}

	public function test_trigger_lesson_complete_returns_false_without_lesson_id(): void {
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'lesson_complete' ),
			[ 0, 1 ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_lesson_complete_returns_false_without_user_id(): void {
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'lesson_complete' ),
			[ 5, 0 ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_lesson_complete_filters_by_selected_lesson(): void {
		// Node configured for lesson 99, but completion is for lesson 5.
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'lesson_complete', [ 'lesson_id' => '99' ] ),
			[ 5, 1 ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_lesson_complete_passes_when_lesson_matches_config(): void {
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'lesson_complete', [ 'lesson_id' => '5' ] ),
			[ 5, 1 ]
		);
		$this->assertIsArray( $result );
		$this->assertEquals( 5, $result['lesson_id'] );
	}

	// =========================================================================
	// TRIGGER: academy_quiz_course_attempt
	// =========================================================================

	public function test_trigger_quiz_attempt_returns_score_fields(): void {
		$attempt = $this->makeAttempt( [ 'quiz_id' => 10, 'user_id' => 1, 'earned_marks' => 7, 'total_marks' => 10 ] );

		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'academy_quiz_course_attempt' ),
			[ $attempt ]
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 10, $result['quiz_id'] );
		$this->assertEquals( 1,  $result['user_id'] );
		$this->assertEquals( 7,  $result['score'] );
		$this->assertEquals( 10, $result['total'] );
	}

	public function test_trigger_quiz_attempt_returns_false_when_pending(): void {
		$attempt = $this->makeAttempt( [ 'attempt_status' => 'pending' ] );

		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'academy_quiz_course_attempt' ),
			[ $attempt ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_quiz_attempt_returns_false_without_attempt(): void {
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'academy_quiz_course_attempt' ),
			[ null ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_quiz_attempt_returns_false_without_quiz_id(): void {
		$attempt = $this->makeAttempt( [ 'quiz_id' => null ] );

		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'academy_quiz_course_attempt' ),
			[ $attempt ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_quiz_attempt_filters_by_selected_quiz(): void {
		// Node configured for quiz 99, but attempt is for quiz 10.
		$attempt = $this->makeAttempt( [ 'quiz_id' => 10 ] );

		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'academy_quiz_course_attempt', [ 'quiz_id' => '99' ] ),
			[ $attempt ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_quiz_attempt_passes_when_quiz_matches_config(): void {
		$attempt = $this->makeAttempt( [ 'quiz_id' => 10 ] );

		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'academy_quiz_course_attempt', [ 'quiz_id' => '10' ] ),
			[ $attempt ]
		);
		$this->assertIsArray( $result );
		$this->assertEquals( 10, $result['quiz_id'] );
	}

	// =========================================================================
	// TRIGGER: quiz_target
	// =========================================================================

	public function test_trigger_quiz_target_returns_percentage_when_target_met(): void {
		// 8/10 = 80% — target is 70%.
		$attempt = $this->makeAttempt( [ 'quiz_id' => 10, 'earned_marks' => 8, 'total_marks' => 10 ] );

		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'quiz_target', [ 'quiz_id' => 'any', 'target_percentage' => '70' ] ),
			[ $attempt ]
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 80.0, $result['percentage'] );
		$this->assertEquals( 8,    $result['score'] );
		$this->assertEquals( 10,   $result['total_marks'] );
	}

	public function test_trigger_quiz_target_returns_false_when_target_not_met(): void {
		// 4/10 = 40% — target is 70%.
		$attempt = $this->makeAttempt( [ 'earned_marks' => 4, 'total_marks' => 10 ] );

		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'quiz_target', [ 'quiz_id' => 'any', 'target_percentage' => '70' ] ),
			[ $attempt ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_quiz_target_returns_false_when_pending(): void {
		$attempt = $this->makeAttempt( [ 'attempt_status' => 'pending' ] );

		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'quiz_target', [ 'target_percentage' => '50' ] ),
			[ $attempt ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_quiz_target_returns_false_when_total_marks_zero(): void {
		// Division by zero guard — total_marks=0 must return false.
		$attempt = $this->makeAttempt( [ 'earned_marks' => 0, 'total_marks' => 0 ] );

		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'quiz_target', [ 'target_percentage' => '50' ] ),
			[ $attempt ]
		);
		$this->assertFalse( $result );
	}

	public function test_trigger_quiz_target_exact_boundary_passes(): void {
		// 7/10 = 70% — target is exactly 70%. Should pass (>=).
		$attempt = $this->makeAttempt( [ 'earned_marks' => 7, 'total_marks' => 10 ] );

		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'quiz_target', [ 'quiz_id' => 'any', 'target_percentage' => '70' ] ),
			[ $attempt ]
		);
		$this->assertIsArray( $result );
		$this->assertEquals( 70.0, $result['percentage'] );
	}

	public function test_trigger_quiz_target_filters_by_selected_quiz(): void {
		$attempt = $this->makeAttempt( [ 'quiz_id' => 10, 'earned_marks' => 9, 'total_marks' => 10 ] );

		// Target met (90%) but quiz doesn't match node config.
		$result = Academy::resolve_trigger(
			$this->makeTriggerNode( 'quiz_target', [ 'quiz_id' => '99', 'target_percentage' => '50' ] ),
			[ $attempt ]
		);
		$this->assertFalse( $result );
	}

	// =========================================================================
	// ACTIONS
	// =========================================================================

	/**
	 * Academy has no actions — execute_node() is a passthrough that returns
	 * the input data unchanged on the 'main' port.
	 */
	public function test_execute_node_is_passthrough(): void {
		$input  = [ 'course_id' => 1, 'user_id' => 2 ];
		$result = Academy::execute_node(
			$this->makeActionNode( '__any__', [] ),
			$input
		);

		$this->assertEquals( 'main', $result['port'] );
		$this->assertEquals( $input, $result['data'] );
	}

	// =========================================================================
	// SCHEMA & CONTRACT
	// =========================================================================

	public function test_get_slug_returns_academy(): void {
		$this->assertEquals( 'academy', Academy::get_slug() );
	}

	public function test_get_name_returns_academy_lms(): void {
		$this->assertEquals( 'Academy LMS', Academy::get_name() );
	}

	public function test_all_triggers_registered(): void {
		$triggers = Academy::get_triggers();
		foreach ( [ 'user_enroll_course', 'course_complete', 'lesson_complete', 'academy_quiz_course_attempt', 'quiz_target' ] as $event ) {
			$this->assertArrayHasKey( $event, $triggers, "Trigger '$event' missing from get_triggers()" );
		}
	}

	public function test_trigger_config_schema_for_course_triggers(): void {
		foreach ( [ 'user_enroll_course', 'course_complete' ] as $trigger ) {
			$schema = Academy::get_trigger_config_schema( $trigger );
			$this->assertCount( 1, $schema );
			$this->assertEquals( 'course_id', $schema[0]['key'] );
			$this->assertEquals( 'select',    $schema[0]['type'] );
		}
	}

	public function test_trigger_config_schema_for_quiz_attempt(): void {
		$schema = Academy::get_trigger_config_schema( 'academy_quiz_course_attempt' );
		$this->assertCount( 1, $schema );
		$this->assertEquals( 'quiz_id', $schema[0]['key'] );
	}

	public function test_trigger_config_schema_for_quiz_target_has_two_fields(): void {
		$schema = Academy::get_trigger_config_schema( 'quiz_target' );
		$this->assertCount( 2, $schema );

		$keys = array_column( $schema, 'key' );
		$this->assertContains( 'quiz_id',           $keys );
		$this->assertContains( 'target_percentage',  $keys );
	}

	public function test_trigger_config_schema_for_lesson_trigger(): void {
		$schema = Academy::get_trigger_config_schema( 'lesson_complete' );
		$this->assertCount( 1, $schema );
		$this->assertEquals( 'lesson_id', $schema[0]['key'] );
	}

	public function test_trigger_config_schema_returns_empty_for_unknown_trigger(): void {
		$this->assertSame( [], Academy::get_trigger_config_schema( '__unknown__' ) );
	}

	public function test_get_actions_returns_empty_array(): void {
		$this->assertSame( [], Academy::get_actions() );
	}

	public function test_get_dynamic_queries_has_all_keys(): void {
		$queries = Academy::get_dynamic_queries();
		foreach ( [ 'acourse', 'quiz', 'lesson' ] as $key ) {
			$this->assertArrayHasKey( $key, $queries, "Dynamic query '$key' missing" );
			$this->assertIsCallable( $queries[ $key ] );
		}
	}

	public function test_query_courses_returns_any_option_when_academy_not_loaded(): void {
		// In unit tests Academy class is not present — result is just the 'any' option.
		$options = Academy::query_courses();
		$this->assertIsArray( $options );
		$this->assertEquals( 'any', $options[0]['name'] );
	}

	public function test_query_quiz_returns_any_option_when_academy_not_loaded(): void {
		$options = Academy::query_quiz();
		$this->assertIsArray( $options );
		$this->assertEquals( 'any', $options[0]['name'] );
	}

	public function test_query_lesson_returns_any_option_when_academy_not_loaded(): void {
		$options = Academy::query_lesson();
		$this->assertIsArray( $options );
		$this->assertEquals( 'any', $options[0]['name'] );
	}
}