<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\Quizpress;
use Zaplane\Tests\WPMocks;

// The integration gates everything on QuizPress being loaded; the mocked test
// environment has no real plugin to define this.
if ( ! defined( 'QUIZPRESS_VERSION' ) ) {
	define( 'QUIZPRESS_VERSION', '1.5.3-test' );
}

class QuizPressTest extends IntegrationTestCase {

	/**
	 * A minimal stand-in for WP_REST_Request, enough for the question triggers
	 * that read the route/params on rest_pre_dispatch.
	 */
	private function makeRestRequest( string $route, array $params = [], string $method = 'POST' ): object {
		return new class( $route, $params, $method ) {
			private $route;
			private $params;
			private $method;

			public function __construct( string $route, array $params, string $method ) {
				$this->route  = $route;
				$this->params = $params;
				$this->method = $method;
			}

			public function get_route(): string {
				return $this->route;
			}

			public function get_method(): string {
				return $this->method;
			}

			public function get_param( string $key ) {
				return $this->params[ $key ] ?? null;
			}
		};
	}

	protected function setupMockData(): void {
		parent::setupMockData();

		// The environment's winning get_post()/get_post_meta() mocks read these
		// globals rather than the WPMocks stores, so quizzes and their types are
		// seeded here (same pattern the Gamipress test uses).
		$this->seedQuiz( 12, 'WordPress Basics', 'quiz' );
		$this->seedQuiz( 13, 'Quick Poll', 'poll' );
		$this->seedQuiz( 14, 'Feedback Survey', 'survey' );

		WPMocks::setUser(
			5,
			[
				'user_login'   => 'jane',
				'user_email'   => 'jane@example.com',
				'display_name' => 'Jane Doe',
			]
		);
	}

	private function seedQuiz( int $id, string $title, string $type ): void {
		global $zaplane_wp_posts;

		$zaplane_wp_posts           = $zaplane_wp_posts ?? [];
		$zaplane_wp_posts[ $id ]    = (object) [
			'ID'           => $id,
			'post_title'   => $title,
			'post_type'    => 'quizpress_quiz',
			'post_status'  => 'publish',
			'post_content' => '',
			'post_name'    => sanitize_title( $title ),
		];

		$GLOBALS['zaplane_post_meta']           = $GLOBALS['zaplane_post_meta'] ?? [];
		$GLOBALS['zaplane_post_meta'][ $id ]    = [ 'quizpress_quiz_type' => [ $type ] ];
	}

	protected function tearDown(): void {
		unset( $_GET['source'], $_GET['quiz_id'], $_POST['quiz_id'], $_POST['attempt_id'], $_POST['question_id'], $GLOBALS['zaplane_post_meta'], $GLOBALS['zaplane_wp_posts'], $GLOBALS['zaplane_wp_posts_strict'] );
		parent::tearDown();
	}

	protected function getIntegrationClass(): string {
		return Quizpress::class;
	}

	protected function getTriggerTests(): array {
		$quiz_post = (object) [
			'ID'          => 12,
			'post_title'  => 'WordPress Basics',
			'post_type'   => 'quizpress_quiz',
			'post_status' => 'publish',
		];

		return [
			'quiz_created'           => [ 12, $quiz_post, false ],
			'quiz_updated'           => [ 12, $quiz_post, true ],
			'quiz_deleted'           => [ 12, $quiz_post ],
			'quiz_started'           => [ [ 'quiz_id' => 12, 'user_id' => 5 ] ],
			'quiz_attempted'         => [ $this->attemptObject() ],
			'quiz_reattempted'       => [ $this->attemptObject() ],
			'quiz_submitted'         => [ $this->attemptObject() ],
			'quiz_completed'         => [ $this->attemptObject() ],
			'quiz_score_reached'     => [ $this->attemptObject() ],
			'quiz_passed'            => [ $this->attemptArray( 'passed' ) ],
			'quiz_failed'            => [ $this->attemptArray( 'failed' ) ],
			'poll_submitted'         => [ $this->attemptObject( 13 ) ],
			'poll_answer_selected'   => [],
			'poll_result_updated'    => [ $this->attemptObject( 13 ) ],
			'survey_submitted'       => [ $this->attemptObject( 14 ) ],
			'survey_completed'       => [ $this->attemptObject( 14 ) ],
			'certificate_issued'     => [ $this->attemptArray( 'passed' ) ],
			'certificate_downloaded' => [ 'template.php' ],
			'lead_captured'          => [
				'https://example.com/quiz/wordpress-basics/?token=abc&user_id=5',
				(object) [
					'ID'           => 5,
					'user_email'   => 'jane@example.com',
					'display_name' => 'Jane Doe',
				],
			],
			'user_registered'        => [ 5 ],
			'question_added'         => [
				null,
				null,
				$this->makeRestRequest(
					'/quizpress/v1/questions',
					[
						'question_title' => 'What is a hook?',
						'question_type'  => 'single_choice',
						'question_score' => 5,
					]
				),
			],
			'question_updated'       => [
				null,
				null,
				$this->makeRestRequest(
					'/quizpress/v1/questions/34',
					[ 'question_title' => 'Reworded hook question' ],
					'PUT'
				),
			],
			'question_imported'      => [],
		];
	}

	protected function getActionTests(): array {
		return [
			'create_quiz'         => [ 'quiz_title' => 'New Quiz', 'quiz_type' => 'quiz' ],
			'update_quiz'         => [ 'quiz_id' => 12, 'quiz_title' => 'Renamed Quiz' ],
			'delete_quiz'         => [ 'quiz_id' => 12, 'force_delete' => 'no' ],
			'publish_quiz'        => [ 'quiz_id' => 12 ],
			'draft_quiz'          => [ 'quiz_id' => 12 ],
			'reset_quiz_attempt'  => [ 'quiz_id' => 12, 'user_id' => 5 ],
			'set_quiz_score'      => [ 'attempt_id' => 91, 'earned_marks' => 9 ],
			'set_quiz_result'     => [ 'attempt_id' => 91, 'result' => 'passed' ],
			'get_quiz_analytics'  => [ 'quiz_id' => 12 ],
			'create_question'     => [
				'question_title' => 'What is a hook?',
				'question_type'  => 'single_choice',
				'answers_text'   => "* A callback mechanism\nA CSS rule",
			],
			'update_question'     => [ 'question_id' => 34, 'question_title' => 'Reworded' ],
			'delete_question'     => [ 'question_id' => 34 ],
			'import_questions'    => [
				'source'   => 'csv_text',
				'csv_text' => "question_title,question_type,question_score\nWhat is a hook?,single_choice,5",
			],
			'export_questions'    => [ 'quiz_id' => 12 ],
			'generate_certificate' => [ 'quiz_id' => 12, 'user_id' => 5 ],
			'send_certificate'    => [ 'quiz_id' => 12, 'user_id' => 5 ],
			'create_user'         => [ 'user_email' => 'new@example.com', 'first_name' => 'New', 'last_name' => 'User' ],
			'update_user'         => [ 'user_id' => 5, 'first_name' => 'Renamed' ],
			'send_email'          => [ 'to' => 'jane@example.com', 'subject' => 'Hi', 'message' => '<p>Hello</p>' ],
			'create_post'         => [ 'post_title' => 'Sample Post', 'post_status' => 'draft' ],
			'update_post'         => [ 'post_id' => 1, 'post_title' => 'Updated Post' ],
		];
	}

	// ========== TRIGGER BEHAVIOUR ==========

	/**
	 * @test
	 */
	public function quiz_management_triggers_distinguish_create_update_and_delete(): void {
		$quiz_post = (object) [
			'ID'          => 12,
			'post_title'  => 'WordPress Basics',
			'post_type'   => 'quizpress_quiz',
			'post_status' => 'publish',
		];

		$created = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'quiz_created' ),
			[ 12, $quiz_post, false ]
		);
		$this->assertIsArray( $created );
		$this->assertSame( 'WordPress Basics', $created['quiz_title'] );

		$wrong_create = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'quiz_created' ),
			[ 12, $quiz_post, true ]
		);
		$this->assertFalse( $wrong_create );

		$updated = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'quiz_updated', [ 'quiz_id' => 999 ] ),
			[ 12, $quiz_post, true ]
		);
		$this->assertFalse( $updated );

		$deleted = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'quiz_deleted' ),
			[ 12, $quiz_post ]
		);
		$this->assertIsArray( $deleted );
		$this->assertSame( 12, $deleted['quiz_id'] );
		$this->assertArrayHasKey( 'deleted_at', $deleted );
	}

	/**
	 * @test
	 */
	public function quiz_passed_reads_array_attempts_from_the_admin_ajax_hook(): void {
		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'quiz_passed' ),
			[ $this->attemptArray( 'passed' ) ]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'passed', $result['status'] );
		$this->assertSame( 12, $result['quiz_id'] );
		$this->assertSame( 80.0, $result['percentage'] );
	}

	/**
	 * @test
	 */
	public function quiz_passed_ignores_failed_attempts(): void {
		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'quiz_passed' ),
			[ $this->attemptArray( 'failed' ) ]
		);

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function quiz_reattempted_skips_the_first_attempt(): void {
		// The mocked wpdb returns no prior attempt rows, so this is attempt 1.
		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'quiz_reattempted' ),
			[ $this->attemptObject() ]
		);

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function quiz_attempted_carries_the_attempt_number(): void {
		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'quiz_attempted' ),
			[ $this->attemptObject() ]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 1, $result['attempt_number'] );
		$this->assertFalse( $result['is_reattempt'] );
	}

	/**
	 * @test
	 */
	public function quiz_score_reached_filters_below_the_threshold(): void {
		$below = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'quiz_score_reached', [ 'score_percent' => '90' ] ),
			[ $this->attemptObject() ]
		);
		$this->assertFalse( $below );

		$above = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'quiz_score_reached', [ 'score_percent' => '80' ] ),
			[ $this->attemptObject() ]
		);
		$this->assertIsArray( $above );
		$this->assertSame( 80.0, $above['score_percent'] );
		$this->assertSame( 80.0, $above['achieved_percent'] );
	}

	/**
	 * @test
	 */
	public function quiz_completed_requires_every_question_answered(): void {
		$partial = $this->attemptObject();
		$partial->total_answered_questions = 8;

		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'quiz_completed' ),
			[ $partial ]
		);

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function poll_triggers_only_fire_for_poll_quizzes(): void {
		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'poll_submitted' ),
			[ $this->attemptObject() ]
		);
		$this->assertFalse( $result );

		$poll = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'poll_submitted' ),
			[ $this->attemptObject( 13 ) ]
		);
		$this->assertIsArray( $poll );
		$this->assertSame( 'poll', $poll['quiz_type'] );
	}

	/**
	 * @test
	 */
	public function survey_triggers_only_fire_for_survey_quizzes(): void {
		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'survey_submitted' ),
			[ $this->attemptObject() ]
		);
		$this->assertFalse( $result );

		$survey = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'survey_submitted' ),
			[ $this->attemptObject( 14 ) ]
		);
		$this->assertIsArray( $survey );
		$this->assertSame( 'survey', $survey['quiz_type'] );
	}

	/**
	 * @test
	 */
	public function triggers_honour_the_quiz_id_config_gate(): void {
		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'quiz_submitted', [ 'quiz_id' => 999 ] ),
			[ $this->attemptObject() ]
		);

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function certificate_issued_requires_the_certificates_addon(): void {
		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'certificate_issued' ),
			[ $this->attemptArray( 'passed' ) ]
		);

		// No addon settings in the mocked store, so the addon is off.
		$this->assertFalse( $result );

		WPMocks::setOption(
			'quizpress_addons_settings',
			json_encode( [ 'certificates' => true ] )
		);

		// A passed attempt row for the certificate lookup.
		global $wpdb;
		$wpdb->tables['row'] = (object) [
			'attempt_id'       => 91,
			'attempt_ended_at' => '2026-01-01 12:07:30',
			'earned_marks'     => 8,
			'total_marks'      => 10,
		];

		$issued = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'certificate_issued' ),
			[ $this->attemptArray( 'passed' ) ]
		);

		$this->assertIsArray( $issued );
		$this->assertArrayHasKey( 'certificate_url', $issued );
	}

	/**
	 * @test
	 */
	public function certificate_downloaded_matches_the_certificate_source_param(): void {
		$_GET['source']  = 'other';
		$_GET['quiz_id'] = 12;

		$no = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'certificate_downloaded' ),
			[ 'template.php' ]
		);
		$this->assertFalse( $no );

		$_GET['source'] = 'qp-certificate';

		$yes = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'certificate_downloaded' ),
			[ 'template.php' ]
		);

		$this->assertIsArray( $yes );
		$this->assertSame( 12, $yes['quiz_id'] );
		$this->assertArrayHasKey( 'certificate_url', $yes );
	}

	/**
	 * @test
	 */
	public function question_added_reads_the_rest_request(): void {
		$request = $this->makeRestRequest(
			'/quizpress/v1/questions',
			[
				'question_title' => 'What is a hook?',
				'question_type'  => 'single_choice',
			]
		);

		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'question_added' ),
			[ null, null, $request ]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'What is a hook?', $result['question_title'] );
	}

	/**
	 * @test
	 */
	public function question_added_ignores_other_routes(): void {
		$request = $this->makeRestRequest( '/quizpress/v1/quizpress_quiz', [ 'title' => 'x' ] );

		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'question_added' ),
			[ null, null, $request ]
		);

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function question_updated_reads_the_route_id(): void {
		$request = $this->makeRestRequest( '/quizpress/v1/questions/34', [ 'question_title' => 'Reworded' ], 'PUT' );

		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'question_updated' ),
			[ null, null, $request ]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 34, $result['question_id'] );
	}

	/**
	 * @test
	 */
	public function question_deleted_reads_the_delete_route_id(): void {
		global $wpdb;
		$wpdb->tables['row'] = (object) [
			'question_title'  => 'Deleted question',
			'question_type'   => 'single_choice',
			'question_score'  => 5,
			'question_status' => 'publish',
		];

		$request = $this->makeRestRequest( '/quizpress/v1/questions/34', [], 'DELETE' );

		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'question_deleted', [ 'question_type' => 'single_choice' ] ),
			[ null, null, $request ]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 34, $result['question_id'] );
		$this->assertSame( 'Deleted question', $result['question_title'] );
		$this->assertArrayHasKey( 'deleted_at', $result );
	}

	/**
	 * @test
	 */
	public function question_imported_stays_silent_without_the_import_request(): void {
		// The mock environment has no did_action(), so the resolver's guard
		// keeps it silent — the production path additionally requires the
		// admin-ajax import action to have just run.
		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'question_imported' ),
			[]
		);

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function lead_captured_reads_the_magic_link_hook(): void {
		$user = WPMocks::getUser( 5 );

		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'lead_captured' ),
			[ 'https://example.com/?token=abc&user_id=5', $user ]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 5, $result['user_id'] );
		$this->assertSame( 'jane@example.com', $result['email'] );
	}

	/**
	 * @test
	 */
	public function user_registered_builds_the_user_payload(): void {
		$result = Quizpress::resolve_trigger(
			$this->makeTriggerNode( 'user_registered' ),
			[ 5 ]
		);

		// The environment's get_userdata() mock returns a fixed fixture for
		// every id, so assert on the fields the resolver itself computes.
		$this->assertIsArray( $result );
		$this->assertSame( 5, $result['user_id'] );
		$this->assertSame( [ 'subscriber' ], $result['roles'] );
		$this->assertArrayHasKey( 'registered_at', $result );
	}

	/**
	 * @test
	 */
	public function every_trigger_schema_and_sample_is_consistent(): void {
		foreach ( Quizpress::get_triggers() as $key => $trigger ) {
			$this->assertArrayHasKey( 'label', $trigger );
			$this->assertArrayHasKey( 'hook', $trigger );

			// Every trigger must have sample output so the condition builder
			// can offer fields before any test run.
			$this->assertNotEmpty(
				Quizpress::get_trigger_sample_output( $key ),
				"Trigger '{$key}' has no sample output"
			);
		}//end foreach
	}

	// ========== ACTION BEHAVIOUR ==========

	/**
	 * @test
	 */
	public function create_quiz_requires_a_title(): void {
		$result = Quizpress::execute_node(
			$this->makeActionNode( 'create_quiz', [ 'quiz_title' => '' ] ),
			[]
		);

		$this->assertSame( 'main', $result['port'] );
		$this->assertFalse( $result['data']['success'] );
	}

	/**
	 * @test
	 */
	public function actions_against_missing_entities_report_errors(): void {
		$missing_quiz = Quizpress::execute_node(
			$this->makeActionNode( 'update_quiz', [ 'quiz_id' => 999, 'quiz_title' => 'x' ] ),
			[]
		);
		$this->assertFalse( $missing_quiz['data']['success'] );

		$missing_attempt = Quizpress::execute_node(
			$this->makeActionNode( 'set_quiz_score', [ 'attempt_id' => 999, 'earned_marks' => 5 ] ),
			[]
		);
		$this->assertFalse( $missing_attempt['data']['success'] );
	}

	/**
	 * @test
	 */
	public function set_quiz_result_rejects_unknown_results(): void {
		$result = Quizpress::execute_node(
			$this->makeActionNode( 'set_quiz_result', [ 'attempt_id' => 91, 'result' => 'maybe' ] ),
			[]
		);

		$this->assertFalse( $result['data']['success'] );
	}

	/**
	 * @test
	 */
	public function unknown_actions_are_reported(): void {
		$result = Quizpress::execute_node(
			$this->makeActionNode( 'teleport_quiz', [] ),
			[]
		);

		$this->assertSame( 'main', $result['port'] );
		$this->assertStringContainsString( 'teleport_quiz', (string) ( $result['data']['error'] ?? '' ) );
	}

	/**
	 * @test
	 */
	public function certificate_actions_require_the_addon(): void {
		$result = Quizpress::execute_node(
			$this->makeActionNode( 'generate_certificate', [ 'quiz_id' => 12, 'user_id' => 5 ] ),
			[]
		);

		$this->assertFalse( $result['data']['success'] );
	}

	/**
	 * @test
	 */
	public function every_action_schema_and_sample_is_consistent(): void {
		foreach ( Quizpress::get_actions() as $key => $action ) {
			$this->assertArrayHasKey( 'label', $action );

			$this->assertNotEmpty(
				Quizpress::get_action_sample_output( $key ),
				"Action '{$key}' has no sample output"
			);

			// The manifest merges the schema in itself, but calling it directly
			// keeps the integration honest about every action having one.
			$this->assertIsArray( Quizpress::get_action_config_schema( $key ) );
		}//end foreach
	}

	// ========== HELPERS ==========

	private function attemptObject( int $quiz_id = 12 ): object {
		return (object) [
			'attempt_id'                => 91,
			'quiz_id'                   => $quiz_id,
			'user_id'                   => 5,
			'guest_id'                  => '',
			'total_questions'           => 10,
			'total_answered_questions'  => 10,
			'total_marks'               => 10,
			'earned_marks'              => 8,
			'attempt_status'            => 'passed',
			'attempt_started_at'        => '2026-01-01 12:00:00',
			'attempt_ended_at'          => '2026-01-01 12:07:30',
		];
	}

	private function attemptArray( string $status, int $quiz_id = 12 ): array {
		return [
			'attempt_id'                => 91,
			'quiz_id'                   => $quiz_id,
			'user_id'                   => 5,
			'guest_id'                  => '',
			'total_questions'           => 10,
			'total_answered_questions'  => 10,
			'total_marks'               => 10,
			'earned_marks'              => 8,
			'attempt_status'            => $status,
			'attempt_started_at'        => '2026-01-01 12:00:00',
			'attempt_ended_at'          => '2026-01-01 12:07:30',
		];
	}
}
