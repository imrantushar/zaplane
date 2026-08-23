<?php

namespace Zaplane\Database\Seeders;

use Zaplane\Models\Recipe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seeds 9 draft Recipes wiring Academy LMS's student-facing events straight
 * to GemCRM's `send_email` action — no GemCRM Pro dependency (no Sequence /
 * Recurring Campaign involved, `send_email` + `filter` are both free-tier
 * GemCRM/Zaplane features). Each Recipe is independent and single-step
 * (trigger -> optional filter -> send email, sourced from a saved Zaplane
 * Email Template — see AcademyLmsEmailTemplateSeeder, which must run first);
 * admins activate whichever ones they want from Zaplane's Recipes gallery.
 */
class AcademyLmsRecipeSeeder {

	public function run(): void {
		if ( ! class_exists( 'Academy' ) ) {
			return;
		}

		$this->seed_two_node(
			'Academy LMS: Welcome Email',
			'Send a welcome email via GemCRM when a new student registers on Academy LMS.',
			[
				'event' => 'student_registered',
				'hook'  => 'academy/api/auth/after_student_registration',
				'name'  => 'Student Registered',
			],
			'Academy LMS: Welcome Email'
		);

		$this->seed_two_node(
			'Academy LMS: Course Enrollment',
			'Confirm a student\'s course enrollment via GemCRM.',
			[
				'event' => 'user_enroll_course',
				'hook'  => 'academy/course/after_enroll',
				'name'  => 'Course Enrolled',
			],
			'Academy LMS: Course Enrollment'
		);

		$this->seed_two_node(
			'Academy LMS: Course Completed',
			'Congratulate a student when they finish a course, via GemCRM.',
			[
				'event' => 'course_complete',
				'hook'  => 'academy/admin/course_complete_after',
				'name'  => 'Course Completed',
			],
			'Academy LMS: Course Completed'
		);

		$this->seed_two_node(
			'Academy LMS: Quiz Result',
			'Let a student know their quiz has been evaluated, via GemCRM.',
			[
				'event' => 'quiz_manually_evaluated',
				'hook'  => 'academy_quizzes/after_quiz_attempt_manual_review',
				'name'  => 'Quiz Manually Evaluated',
			],
			'Academy LMS: Quiz Result'
		);

		$this->seed_two_node(
			'Academy LMS: Assignment Evaluated',
			'Let a student know their assignment has been graded, via GemCRM.',
			[
				'event' => 'assignment_evaluated',
				'hook'  => 'academy_pro/frontend/evaluate_submitted_assignment',
				'name'  => 'Assignment Evaluated',
			],
			'Academy LMS: Assignment Evaluated'
		);

		$this->seed_two_node(
			'Academy LMS: Question Answered',
			'Notify a student that their course question got a reply, via GemCRM.',
			[
				'event' => 'qa_answered',
				'hook'  => 'academy/frontend/insert_course_qa_answered',
				'name'  => 'Question Answered',
			],
			'Academy LMS: Question Answered'
		);

		$this->seed_two_node(
			'Academy LMS: Booking Confirmed',
			'Confirm a 1:1 tutor booking session, via GemCRM.',
			[
				'event' => 'booking_confirmed',
				'hook'  => 'academy_pro/booking/after_booked',
				'name'  => 'Booking Confirmed',
			],
			'Academy LMS: Booking Confirmed'
		);

		$this->seed_filtered(
			'Academy LMS: Instructor Request Accepted',
			'Notify an applicant their instructor request was approved, via GemCRM.',
			'approved',
			'Academy LMS: Instructor Request Accepted'
		);

		$this->seed_filtered(
			'Academy LMS: Instructor Request Denied',
			'Notify an applicant their instructor request was not approved, via GemCRM.',
			'remove',
			'Academy LMS: Instructor Request Denied'
		);
	}

	/**
	 * Two-node recipe: [Academy trigger] -> [GemCRM: Send Email (template)].
	 */
	private function seed_two_node( string $title, string $description, array $trigger, string $template_title ): void {
		if ( Recipe::where( 'title', $title )->first() ) {
			return;
		}

		$template_id = AcademyLmsEmailTemplateSeeder::find_id( $template_title );
		if ( ! $template_id ) {
			return;
		}

		$graph = [
			'nodes' => [
				$this->trigger_node( $trigger['event'], $trigger['hook'], $trigger['name'] ),
				$this->send_email_node( '2', $template_id ),
			],
			'edges' => [
				[ 'id' => 'e1-2', 'source' => '1', 'target' => '2' ],
			],
		];

		$this->save_recipe( $title, $description, $graph );
	}

	/**
	 * Three-node recipe: [Academy trigger] -> [Filter: status == X] -> [GemCRM: Send Email (template)].
	 * Used for instructor_status_updated, which fires for both accept and deny.
	 */
	private function seed_filtered( string $title, string $description, string $status, string $template_title ): void {
		if ( Recipe::where( 'title', $title )->first() ) {
			return;
		}

		$template_id = AcademyLmsEmailTemplateSeeder::find_id( $template_title );
		if ( ! $template_id ) {
			return;
		}

		$graph = [
			'nodes' => [
				$this->trigger_node( 'instructor_status_updated', 'academy/admin/update_instructor_status', 'Instructor Status Updated' ),
				[
					'id'       => '2',
					'type'     => 'action',
					'position' => [ 'x' => 250, 'y' => 200 ],
					'data'     => [
						'app'    => 'filter',
						'event'  => 'filter',
						'label'  => 'Filter',
						'icon'   => 'filter',
						'name'   => 'Only ' . $status,
						'config' => [
							'conditions' => [
								'logic'      => 'AND',
								'conditions' => [
									[
										'left'     => '{{1.status}}',
										'operator' => '==',
										'right'    => $status,
									],
								],
							],
						],
					],
				],
				$this->send_email_node( '3', $template_id ),
			],
			'edges' => [
				[ 'id' => 'e1-2', 'source' => '1', 'target' => '2' ],
				[ 'id' => 'e2-3', 'source' => '2', 'target' => '3' ],
			],
		];

		$this->save_recipe( $title, $description, $graph );
	}

	private function trigger_node( string $event, string $hook, string $name ): array {
		return [
			'id'       => '1',
			'type'     => 'trigger',
			'position' => [ 'x' => 250, 'y' => 50 ],
			'data'     => [
				'app'   => 'academy',
				'event' => $event,
				'hook'  => $hook,
				'label' => 'Academy LMS',
				'icon'  => 'academy.svg',
				'name'  => $name,
			],
		];
	}

	/**
	 * The email action sources its subject/body from the given Zaplane Email
	 * Template (seeded by AcademyLmsEmailTemplateSeeder) — leaving `subject`
	 * blank lets resolve_email_content() fall back to the template's own
	 * stored subject.
	 */
	private function send_email_node( string $id, int $template_id ): array {
		return [
			'id'       => $id,
			'type'     => 'action',
			'position' => [ 'x' => 250, 'y' => 200 * ( (int) $id - 1 ) ],
			'data'     => [
				'app'    => 'gemcrm',
				'event'  => 'send_email',
				'label'  => 'GemCRM',
				'icon'   => 'crm.svg',
				'name'   => 'Send Email',
				'config' => [
					'recipient_type' => 'custom',
					'custom_email'   => '{{1.user_email}}',
					'subject'        => '',
					'content_source' => 'template',
					'template_id'    => $template_id,
				],
			],
		];
	}

	private function save_recipe( string $title, string $description, array $graph ): void {
		$blueprint = [
			'title'       => $title,
			'status'      => 'draft',
			'layout'      => 'LR',
			'versions'    => [
				[
					'graph_json'     => $graph,
					'graph_hash'     => hash( 'sha256', wp_json_encode( $graph ) ),
					'is_active'      => true,
					'version_number' => 1,
				],
			],
			'connections' => [],
		];

		Recipe::create( [
			'title'             => $title,
			'description'       => $description,
			'blueprint'         => wp_json_encode( $blueprint ),
			'integration_icons' => wp_json_encode( [ 'academy.svg', 'crm.svg' ] ),
			'created_by'        => 0,
		] );
	}
}
