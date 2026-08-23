<?php

namespace Zaplane\Database\Seeders;

use Zaplane\Models\Recipe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seeds 9 draft Recipes wiring Academy LMS's student-facing events straight
 * to GemCRM's `send_email` action — no GemCRM Pro dependency (no Sequence /
 * Recurring Campaign involved, `send_email` + `apply_tag` are both free-tier
 * GemCRM features). Each Recipe is independent and single-step (trigger ->
 * optional filter -> send email); admins activate whichever ones they want
 * from Zaplane's Recipes gallery.
 */
class AcademyLmsRecipeSeeder {

	public function run(): void {
		if ( ! class_exists( 'Academy' ) ) {
			return;
		}

		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url( '/' );

		$this->seed_welcome( $site_name, $site_url );
		$this->seed_course_enrollment();
		$this->seed_course_completed();
		$this->seed_quiz_result();
		$this->seed_assignment_evaluated();
		$this->seed_qa_answered();
		$this->seed_booking_confirmed();
		$this->seed_instructor_accepted( $site_name, $site_url );
		$this->seed_instructor_denied( $site_name );
	}

	private function seed_welcome( string $site_name, string $site_url ): void {
		$this->create_two_node_recipe(
			'Academy LMS: Welcome Email',
			'Send a welcome email via GemCRM when a new student registers on Academy LMS.',
			[
				'event' => 'student_registered',
				'hook'  => 'academy/api/auth/after_student_registration',
				'name'  => 'Student Registered',
			],
			'Welcome, {{1.first_name}}!',
			'<p>Hi {{1.first_name}},</p><p>Thanks for creating a student account on ' . esc_html( $site_name ) . '. You are all set — start exploring courses and pick up right where you left off any time.</p><p><a href="' . esc_url( $site_url ) . '">Go to Dashboard</a></p>'
		);
	}

	private function seed_course_enrollment(): void {
		$this->create_two_node_recipe(
			'Academy LMS: Course Enrollment',
			'Confirm a student\'s course enrollment via GemCRM.',
			[
				'event' => 'user_enroll_course',
				'hook'  => 'academy/course/after_enroll',
				'name'  => 'Course Enrolled',
			],
			"You're enrolled! 🎓",
			'<p>Hi {{1.first_name}},</p><p>You have successfully enrolled in <strong>{{1.course_title}}</strong>. Jump back in any time to continue learning.</p><p><a href="{{1.course_url}}">Start Course</a></p>'
		);
	}

	private function seed_course_completed(): void {
		$this->create_two_node_recipe(
			'Academy LMS: Course Completed',
			'Congratulate a student when they finish a course, via GemCRM.',
			[
				'event' => 'course_complete',
				'hook'  => 'academy/admin/course_complete_after',
				'name'  => 'Course Completed',
			],
			'Congratulations, {{1.first_name}}! 🏆',
			'<p>You have successfully completed <strong>{{1.course_title}}</strong>. Great work — see what else you can learn next.</p><p><a href="{{1.course_url}}">View Certificate</a></p>'
		);
	}

	private function seed_quiz_result(): void {
		$this->create_two_node_recipe(
			'Academy LMS: Quiz Result',
			'Let a student know their quiz has been evaluated, via GemCRM.',
			[
				'event' => 'quiz_manually_evaluated',
				'hook'  => 'academy_quizzes/after_quiz_attempt_manual_review',
				'name'  => 'Quiz Manually Evaluated',
			],
			'Your quiz has been evaluated',
			'<p>Hi {{1.first_name}},</p><p>Your quiz attempt in <strong>{{1.course_title}}</strong> has been reviewed.</p><p><a href="{{1.course_url}}">View Result</a></p>'
		);
	}

	private function seed_assignment_evaluated(): void {
		$this->create_two_node_recipe(
			'Academy LMS: Assignment Evaluated',
			'Let a student know their assignment has been graded, via GemCRM.',
			[
				'event' => 'assignment_evaluated',
				'hook'  => 'academy_pro/frontend/evaluate_submitted_assignment',
				'name'  => 'Assignment Evaluated',
			],
			'Your assignment has been evaluated',
			'<p>Hi {{1.first_name}},</p><p>Your assignment submission for <strong>{{1.course_title}}</strong> has been graded. You scored {{1.achieved_mark}} out of {{1.total_mark}}.</p><p><a href="{{1.course_url}}">View Feedback</a></p>'
		);
	}

	private function seed_qa_answered(): void {
		$this->create_two_node_recipe(
			'Academy LMS: Question Answered',
			'Notify a student that their course question got a reply, via GemCRM.',
			[
				'event' => 'qa_answered',
				'hook'  => 'academy/frontend/insert_course_qa_answered',
				'name'  => 'Question Answered',
			],
			'Your question has an answer',
			'<p>Hi {{1.first_name}},</p><p>Your question on <strong>{{1.course_title}}</strong> has just been answered. Head back to the course to read the reply.</p><p><a href="{{1.course_url}}">View Answer</a></p>'
		);
	}

	private function seed_booking_confirmed(): void {
		$this->create_two_node_recipe(
			'Academy LMS: Booking Confirmed',
			'Confirm a 1:1 tutor booking session, via GemCRM.',
			[
				'event' => 'booking_confirmed',
				'hook'  => 'academy_pro/booking/after_booked',
				'name'  => 'Booking Confirmed',
			],
			'Your booking is confirmed',
			'<p>Hi {{1.first_name}},</p><p>Your session "{{1.tutor_name}}" is confirmed for {{1.session_time}}.</p><p>{{1.location_or_meeting_link}}</p>'
		);
	}

	private function seed_instructor_accepted( string $site_name, string $site_url ): void {
		$this->create_filtered_recipe(
			'Academy LMS: Instructor Request Accepted',
			'Notify an applicant their instructor request was approved, via GemCRM.',
			'approved',
			'You are now an instructor! 🎉',
			'<p>Hi {{1.first_name}},</p><p>Your request to become an instructor on ' . esc_html( $site_name ) . ' has been approved. You can log in and start creating courses right away.</p><p><a href="' . esc_url( $site_url ) . '">Log In</a></p>'
		);
	}

	private function seed_instructor_denied( string $site_name ): void {
		$this->create_filtered_recipe(
			'Academy LMS: Instructor Request Denied',
			'Notify an applicant their instructor request was not approved, via GemCRM.',
			'remove',
			'Update on your instructor request',
			'<p>Hi {{1.first_name}},</p><p>Thank you for your interest in becoming an instructor on ' . esc_html( $site_name ) . '. After review, we are not able to approve your request at this time.</p>'
		);
	}

	/**
	 * Two-node recipe: [Academy trigger] -> [GemCRM: Send Email].
	 */
	private function create_two_node_recipe( string $title, string $description, array $trigger, string $subject, string $body ): void {
		if ( Recipe::where( 'title', $title )->first() ) {
			return;
		}

		$graph = [
			'nodes' => [
				$this->trigger_node( $trigger['event'], $trigger['hook'], $trigger['name'] ),
				$this->send_email_node( '2', $subject, $body ),
			],
			'edges' => [
				[ 'id' => 'e1-2', 'source' => '1', 'target' => '2' ],
			],
		];

		$this->save_recipe( $title, $description, $graph );
	}

	/**
	 * Three-node recipe: [Academy trigger] -> [Filter: status == X] -> [GemCRM: Send Email].
	 * Used for instructor_status_updated, which fires for both accept and deny.
	 */
	private function create_filtered_recipe( string $title, string $description, string $status, string $subject, string $body ): void {
		if ( Recipe::where( 'title', $title )->first() ) {
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
				$this->send_email_node( '3', $subject, $body ),
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

	private function send_email_node( string $id, string $subject, string $body ): array {
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
					'subject'        => $subject,
					'content_source' => 'custom',
					'body'           => $body,
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
