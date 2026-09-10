<?php

namespace Zaplane\Tests\Authoring;

use PHPUnit\Framework\TestCase;
use Zaplane\Authoring\Catalog;
use Zaplane\Authoring\GraphValidator;

/**
 * Roughly a third of all capabilities have a required field whose options live
 * on the site. Without resolving them a caller can only guess, and a guessed id
 * used to save clean and then never match anything.
 */
class FieldOptionsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Catalog::flush();
	}

	/**
	 * @test
	 */
	public function a_fixed_list_is_returned_without_a_lookup(): void {
		$r = Catalog::field_options( 'storeengine', 'update_order_status', 'order_status' );

		$this->assertNotNull( $r );
		$this->assertFalse( $r['dynamic'] );
		$this->assertTrue( $r['resolved'] );
		$this->assertContains( 'processing', array_column( $r['options'], 'value' ) );
	}

	/**
	 * @test
	 */
	public function a_free_text_field_has_no_options_and_is_not_dynamic(): void {
		$r = Catalog::field_options( 'storeengine', 'update_order_status', 'order_id' );

		$this->assertNotNull( $r );
		$this->assertFalse( $r['dynamic'] );
		$this->assertSame( [], $r['options'] );
	}

	/**
	 * @test
	 */
	public function a_site_backed_field_is_resolved_through_the_integration(): void {
		$r = Catalog::field_options( 'academy', 'user_enroll_course', 'course_id' );

		$this->assertNotNull( $r );
		$this->assertTrue( $r['dynamic'], 'course_id options come from the site.' );
		$this->assertTrue( $r['resolved'] );
		$this->assertNotEmpty( $r['options'] );

		// Each row is normalised to value/label whatever key the integration used
		// — academy answers with `name`, others with `value`.
		foreach ( $r['options'] as $option ) {
			$this->assertArrayHasKey( 'value', $option );
			$this->assertArrayHasKey( 'label', $option );
		}
	}

	/**
	 * @test
	 */
	public function an_unknown_field_or_capability_returns_null(): void {
		$this->assertNull( Catalog::field_options( 'academy', 'user_enroll_course', 'not_a_field' ) );
		$this->assertNull( Catalog::field_options( 'academy', 'not_an_event', 'course_id' ) );
		$this->assertNull( Catalog::field_options( 'not_an_app', 'x', 'y' ) );
	}

	/* ----------------------------- validation ----------------------------- */

	private function graphWithCourse( $course_id ): array {
		return [
			'nodes' => [
				[
					'id'       => '1',
					'type'     => 'trigger',
					'position' => [
						'x' => 80,
						'y' => 200,
					],
					'data'     => [
						'app'    => 'academy',
						'event'  => 'user_enroll_course',
						'config' => [ 'course_id' => $course_id ],
					],
				],
			],
			'edges' => [],
		];
	}

	/**
	 * @test
	 */
	public function a_value_the_site_does_not_have_is_an_error(): void {
		$report = GraphValidator::check( $this->graphWithCourse( 999999 ) );

		$this->assertFalse( $report['valid'] );
		$this->assertContains( 'unknown_option', array_column( $report['errors'], 'code' ) );

		// The message has to be actionable — it names the tool that answers.
		$joined = implode( ' ', array_column( $report['errors'], 'message' ) );
		$this->assertStringContainsString( 'list_field_options', $joined );
	}

	/**
	 * @test
	 */
	public function a_value_the_site_does_have_passes(): void {
		$allowed = Catalog::field_options( 'academy', 'user_enroll_course', 'course_id' )['options'];
		$report  = GraphValidator::check( $this->graphWithCourse( $allowed[0]['value'] ) );

		$this->assertSame(
			[],
			array_values( array_filter( $report['errors'], fn( $e ) => 'unknown_option' === $e['code'] ) ),
			'A value straight from the lookup must not be rejected.'
		);
	}

	/**
	 * @test
	 */
	public function an_expression_is_left_for_run_time(): void {
		$report = GraphValidator::check( $this->graphWithCourse( '{{1.course_id}}' ) );

		$this->assertSame(
			[],
			array_values( array_filter( $report['errors'], fn( $e ) => 'unknown_option' === $e['code'] ) ),
			'A {{…}} token resolves from upstream output and cannot be checked now.'
		);
	}

	/**
	 * @test
	 */
	public function a_lookup_that_throws_warns_instead_of_taking_validation_down(): void {
		// Contact Form 7's form lookup calls a method that does not exist on the
		// installed version. A broken lookup must not become a fatal, and must not
		// stop someone building a workflow either.
		$r = Catalog::field_options( 'contact-form-7', 'form_submitted', 'form_id' );

		$this->assertNotNull( $r );
		$this->assertTrue( $r['dynamic'] );
		$this->assertFalse( $r['resolved'], 'A throwing lookup is reported, not propagated.' );
		$this->assertNotEmpty( $r['error'] );

		$report = GraphValidator::check( [
			'nodes' => [
				[
					'id'       => '1',
					'type'     => 'trigger',
					'position' => [
						'x' => 80,
						'y' => 200,
					],
					'data'     => [
						'app'    => 'contact-form-7',
						'event'  => 'form_submitted',
						'config' => [ 'form_id' => '42' ],
					],
				],
			],
			'edges' => [],
		] );

		$this->assertContains( 'options_unavailable', array_column( $report['warnings'], 'code' ) );
		$this->assertNotContains( 'options_unavailable', array_column( $report['errors'], 'code' ) );
		$this->assertNotContains( 'unknown_option', array_column( $report['errors'], 'code' ) );
	}

	/**
	 * @test
	 */
	public function an_empty_list_warns_rather_than_blocking(): void {
		// A site with no courses yet still has to be able to author against them.
		$r = Catalog::field_options( 'academy', 'enroll-course', 'selectedCourse' );

		$this->assertNotNull( $r );
		$this->assertTrue( $r['resolved'] );
		$this->assertSame( [], $r['options'] );

		$report = GraphValidator::check( [
			'nodes' => [
				[
					'id'       => '1',
					'type'     => 'trigger',
					'position' => [
						'x' => 80,
						'y' => 200,
					],
					'data'     => [
						'app'    => 'academy',
						'event'  => 'user_enroll_course',
						'config' => [ 'course_id' => 'any' ],
					],
				],
				[
					'id'       => '2',
					'type'     => 'action',
					'position' => [
						'x' => 420,
						'y' => 200,
					],
					'data'     => [
						'app'    => 'academy',
						'event'  => 'enroll-course',
						'config' => [ 'selectedCourse' => '7' ],
					],
				],
			],
			'edges' => [
				[
					'id'     => 'e1-2',
					'source' => '1',
					'target' => '2',
				],
			],
		] );

		$this->assertContains( 'options_empty', array_column( $report['warnings'], 'code' ) );
		$this->assertNotContains( 'unknown_option', array_column( $report['errors'], 'code' ) );
	}
}
