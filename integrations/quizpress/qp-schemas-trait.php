<?php

namespace Zaplane\Integrations\Quizpress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait QpSchemasTrait {

	protected static function qp_trigger_config_schema( string $trigger ): array {
		$quiz_id = [
			'key'      => 'quiz_id',
			'label'    => 'Quiz ID (leave empty for any quiz)',
			'type'     => 'expression',
			'required' => false,
		];

		switch ( $trigger ) {
			case 'quiz_created':
			case 'quiz_updated':
			case 'quiz_deleted':
			case 'quiz_started':
			case 'quiz_attempted':
			case 'quiz_reattempted':
			case 'quiz_submitted':
			case 'quiz_completed':
			case 'quiz_passed':
			case 'quiz_failed':
			case 'poll_submitted':
			case 'poll_result_updated':
			case 'poll_answer_selected':
			case 'survey_submitted':
			case 'survey_completed':
			case 'certificate_issued':
			case 'certificate_downloaded':
				return [ $quiz_id ];

			case 'quiz_score_reached':
				return [
					$quiz_id,
					[
						'key'      => 'score_percent',
						'label'    => 'Score Percentage (fire at or above, e.g. 80)',
						'type'     => 'expression',
						'required' => true,
					],
				];

			case 'question_added':
			case 'question_updated':
			case 'question_deleted':
				return [
					[
						'key'      => 'question_type',
						'label'    => 'Question Type (leave empty for any type)',
						'type'     => 'select',
						'required' => false,
						'dynamic'  => [
							'integration' => 'quizpress',
							'query'       => 'question_types',
							'select'      => [ 'value', 'label' ],
						],
					],
				];
		}//end switch

		return [];
	}

	protected static function qp_action_config_schema( string $action ): array {
		$quiz_id = [
			'key'      => 'quiz_id',
			'label'    => 'Quiz ID',
			'type'     => 'expression',
			'required' => true,
		];
		$question_id = [
			'key'      => 'question_id',
			'label'    => 'Question ID',
			'type'     => 'expression',
			'required' => true,
		];
		$quiz_type = [
			'key'      => 'quiz_type',
			'label'    => 'Quiz Type',
			'type'     => 'select',
			'required' => false,
			'default'  => 'quiz',
			'options'  => static::qp_quiz_type_options(),
		];
		$question_type = [
			'key'      => 'question_type',
			'label'    => 'Question Type',
			'type'     => 'select',
			'required' => false,
			'dynamic'  => [
				'integration' => 'quizpress',
				'query'       => 'question_types',
				'select'      => [ 'value', 'label' ],
			],
		];
		$answers_text = [
			'key'      => 'answers_text',
			'label'    => 'Answers (one per line, prefix * for the correct one)',
			'type'     => 'textarea',
			'required' => false,
		];

		switch ( $action ) {
			case 'create_quiz':
				return [
					[
						'key'      => 'quiz_title',
						'label'    => 'Quiz Title',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'quiz_description',
						'label'    => 'Description',
						'type'     => 'textarea',
						'required' => false,
					],
					$quiz_type,
					[
						'key'      => 'quiz_status',
						'label'    => 'Status',
						'type'     => 'select',
						'required' => false,
						'default'  => 'draft',
						'options'  => [
							[ 'label' => 'Draft', 'value' => 'draft' ],
							[ 'label' => 'Publish', 'value' => 'publish' ],
						],
					],
					[
						'key'      => 'time_limit',
						'label'    => 'Time Limit (0 for no limit)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'time_unit',
						'label'    => 'Time Unit',
						'type'     => 'select',
						'required' => false,
						'default'  => 'minutes',
						'options'  => [
							[ 'label' => 'Seconds', 'value' => 'seconds' ],
							[ 'label' => 'Minutes', 'value' => 'minutes' ],
							[ 'label' => 'Hours', 'value' => 'hours' ],
						],
					],
					[
						'key'      => 'passing_grade',
						'label'    => 'Passing Grade (%)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'max_attempts',
						'label'    => 'Max Attempts Allowed (0 for unlimited)',
						'type'     => 'expression',
						'required' => false,
					],
				];

			case 'update_quiz':
				return [
					$quiz_id,
					[
						'key'      => 'quiz_title',
						'label'    => 'New Title (leave empty to keep)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'quiz_description',
						'label'    => 'New Description (leave empty to keep)',
						'type'     => 'textarea',
						'required' => false,
					],
					$quiz_type,
					[
						'key'      => 'passing_grade',
						'label'    => 'New Passing Grade (%) (leave empty to keep)',
						'type'     => 'expression',
						'required' => false,
					],
				];

			case 'delete_quiz':
				return [
					$quiz_id,
					[
						'key'      => 'force_delete',
						'label'    => 'Delete Permanently',
						'type'     => 'select',
						'required' => false,
						'default'  => 'no',
						'options'  => [
							[ 'label' => 'No — move to trash', 'value' => 'no' ],
							[ 'label' => 'Yes — delete permanently', 'value' => 'yes' ],
						],
					],
				];

			case 'publish_quiz':
			case 'draft_quiz':
				return [ $quiz_id ];

			case 'reset_quiz_attempt':
				return [
					$quiz_id,
					[
						'key'      => 'user_id',
						'label'    => 'User ID (leave empty for the run user)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'scope',
						'label'    => 'Reset Scope',
						'type'     => 'select',
						'required' => false,
						'default'  => 'all',
						'options'  => [
							[ 'label' => 'All attempts of this user', 'value' => 'all' ],
							[ 'label' => 'Only the latest attempt', 'value' => 'last' ],
						],
					],
				];

			case 'set_quiz_score':
				return [
					[
						'key'      => 'attempt_id',
						'label'    => 'Attempt ID',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'earned_marks',
						'label'    => 'Earned Marks',
						'type'     => 'expression',
						'required' => true,
					],
				];

			case 'set_quiz_result':
				return [
					[
						'key'      => 'attempt_id',
						'label'    => 'Attempt ID',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'result',
						'label'    => 'Result',
						'type'     => 'select',
						'required' => true,
						'default'  => 'passed',
						'options'  => [
							[ 'label' => 'Passed', 'value' => 'passed' ],
							[ 'label' => 'Failed', 'value' => 'failed' ],
							[ 'label' => 'Pending Review', 'value' => 'pending' ],
							[ 'label' => 'Expired', 'value' => 'expired' ],
						],
					],
				];

			case 'get_quiz_analytics':
				return [ $quiz_id ];

			case 'create_question':
				return [
					[
						'key'      => 'question_title',
						'label'    => 'Question Title',
						'type'     => 'expression',
						'required' => true,
					],
					$question_type,
					$answers_text,
					[
						'key'      => 'question_score',
						'label'    => 'Points',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'question_negative_score',
						'label'    => 'Negative Marks',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'question_content',
						'label'    => 'Description',
						'type'     => 'textarea',
						'required' => false,
					],
					[
						'key'      => 'question_explanation',
						'label'    => 'Explanation',
						'type'     => 'textarea',
						'required' => false,
					],
					[
						'key'      => 'question_status',
						'label'    => 'Status',
						'type'     => 'select',
						'required' => false,
						'default'  => 'publish',
						'options'  => [
							[ 'label' => 'Publish', 'value' => 'publish' ],
							[ 'label' => 'Draft', 'value' => 'draft' ],
						],
					],
				];

			case 'update_question':
				return [
					$question_id,
					[
						'key'      => 'question_title',
						'label'    => 'New Title (leave empty to keep)',
						'type'     => 'expression',
						'required' => false,
					],
					$question_type,
					$answers_text,
					[
						'key'      => 'question_score',
						'label'    => 'New Points (leave empty to keep)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'question_explanation',
						'label'    => 'New Explanation (leave empty to keep)',
						'type'     => 'textarea',
						'required' => false,
					],
				];

			case 'delete_question':
				return [ $question_id ];

			case 'import_questions':
				return [
					[
						'key'      => 'source',
						'label'    => 'CSV Source',
						'type'     => 'select',
						'required' => true,
						'default'  => 'csv_url',
						'options'  => [
							[ 'label' => 'From a URL', 'value' => 'csv_url' ],
							[ 'label' => 'Inline CSV text', 'value' => 'csv_text' ],
						],
					],
					[
						'key'      => 'csv_url',
						'label'    => 'CSV File URL',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'csv_text',
						'label'    => 'CSV Content',
						'type'     => 'textarea',
						'required' => false,
					],
				];

			case 'export_questions':
				return [
					[
						'key'      => 'quiz_id',
						'label'    => 'Quiz ID (leave empty to export all questions)',
						'type'     => 'expression',
						'required' => false,
					],
				];

			case 'generate_certificate':
			case 'send_certificate':
				return [
					$quiz_id,
					[
						'key'      => 'user_id',
						'label'    => 'User ID',
						'type'     => 'expression',
						'required' => true,
					],
				];

			case 'create_user':
				return [
					[
						'key'      => 'user_email',
						'label'    => 'Email',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'user_login',
						'label'    => 'Username (leave empty to derive from email)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'first_name',
						'label'    => 'First Name',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'last_name',
						'label'    => 'Last Name',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'role',
						'label'    => 'Role',
						'type'     => 'select',
						'required' => false,
						'default'  => 'subscriber',
						'options'  => [
							[ 'label' => 'Subscriber', 'value' => 'subscriber' ],
							[ 'label' => 'Contributor', 'value' => 'contributor' ],
							[ 'label' => 'Author', 'value' => 'author' ],
							[ 'label' => 'Editor', 'value' => 'editor' ],
						],
					],
					[
						'key'      => 'password',
						'label'    => 'Password (leave empty to generate)',
						'type'     => 'expression',
						'required' => false,
					],
				];

			case 'update_user':
				return [
					[
						'key'      => 'user_id',
						'label'    => 'User ID',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'user_email',
						'label'    => 'New Email (leave empty to keep)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'first_name',
						'label'    => 'New First Name (leave empty to keep)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'last_name',
						'label'    => 'New Last Name (leave empty to keep)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'role',
						'label'    => 'New Role (leave empty to keep)',
						'type'     => 'select',
						'required' => false,
						'default'  => '',
						'options'  => array_merge(
							[ [ 'label' => '— Keep current —', 'value' => '' ] ],
							[
								[ 'label' => 'Subscriber', 'value' => 'subscriber' ],
								[ 'label' => 'Contributor', 'value' => 'contributor' ],
								[ 'label' => 'Author', 'value' => 'author' ],
								[ 'label' => 'Editor', 'value' => 'editor' ],
							]
						),
					],
				];

			case 'send_email':
				return [
					[
						'key'      => 'to',
						'label'    => 'To (email address)',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'subject',
						'label'    => 'Subject',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'message',
						'label'    => 'Message (HTML allowed)',
						'type'     => 'textarea',
						'required' => true,
					],
				];

			case 'create_post':
				return [
					[
						'key'      => 'post_type',
						'label'    => 'Post Type',
						'type'     => 'select',
						'required' => false,
						'default'  => 'post',
						'options'  => [
							[ 'label' => 'Post', 'value' => 'post' ],
							[ 'label' => 'Page', 'value' => 'page' ],
						],
					],
					[
						'key'      => 'post_title',
						'label'    => 'Title',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'post_content',
						'label'    => 'Content',
						'type'     => 'textarea',
						'required' => false,
					],
					[
						'key'      => 'post_status',
						'label'    => 'Status',
						'type'     => 'select',
						'required' => false,
						'default'  => 'draft',
						'options'  => [
							[ 'label' => 'Draft', 'value' => 'draft' ],
							[ 'label' => 'Publish', 'value' => 'publish' ],
							[ 'label' => 'Pending Review', 'value' => 'pending' ],
						],
					],
				];

			case 'update_post':
				return [
					[
						'key'      => 'post_id',
						'label'    => 'Post ID',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'post_title',
						'label'    => 'New Title (leave empty to keep)',
						'type'     => 'expression',
						'required' => false,
					],
					[
						'key'      => 'post_content',
						'label'    => 'New Content (leave empty to keep)',
						'type'     => 'textarea',
						'required' => false,
					],
					[
						'key'      => 'post_status',
						'label'    => 'New Status (leave empty to keep)',
						'type'     => 'select',
						'required' => false,
						'default'  => '',
						'options'  => array_merge(
							[ [ 'label' => '— Keep current —', 'value' => '' ] ],
							[
								[ 'label' => 'Draft', 'value' => 'draft' ],
								[ 'label' => 'Publish', 'value' => 'publish' ],
								[ 'label' => 'Pending Review', 'value' => 'pending' ],
							]
						),
					],
				];
		}//end switch

		return [];
	}

	protected static function qp_quiz_type_options(): array {
		$options = [];

		foreach ( static::qp_quiz_types() as $value => $label ) {
			$options[] = [ 'label' => $label, 'value' => $value ];
		}

		return $options;
	}

	protected static function qp_dynamic_queries(): array {
		return [
			'quizzes'        => static function (): array {
				$rows = [];

				foreach ( static::qp_published_quizzes() as $quiz_id => $title ) {
					$rows[] = [ 'value' => (string) $quiz_id, 'label' => $title ];
				}

				return $rows;
			},
			'quiz_types'     => static function (): array {
				return array_map(
					static fn( $label, $value ) => [ 'value' => $value, 'label' => $label ],
					array_values( static::qp_quiz_types() ),
					array_keys( static::qp_quiz_types() )
				);
			},
			'question_types' => static function (): array {
				$rows = [];

				foreach ( static::qp_question_types() as $value => $label ) {
					$rows[] = [ 'value' => $value, 'label' => $label ];
				}

				return $rows;
			},
		];
	}

	protected static function qp_published_quizzes(): array {
		$posts = get_posts(
			[
				'post_type'        => 'quizpress_quiz',
				'post_status'      => [ 'publish', 'draft', 'future', 'pending' ],
				'numberposts'      => 200,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			]
		);

		$quizzes = [];

		foreach ( (array) $posts as $post ) {
			$quizzes[ (int) $post->ID ] = sprintf( '#%d %s', $post->ID, $post->post_title );
		}

		return $quizzes;
	}

	protected static function qp_trigger_sample( string $event ): array {
		$attempt = [
			'attempt_id'         => 91,
			'quiz_id'            => 12,
			'quiz_title'         => 'WordPress Basics',
			'quiz_type'          => 'quiz',
			'permalink'          => 'https://example.com/quiz/wordpress-basics/',
			'quiz_status'        => 'publish',
			'user_id'            => 5,
			'guest_id'           => '',
			'user_email'         => 'jane@example.com',
			'user_name'          => 'Jane Doe',
			'status'             => 'passed',
			'earned_marks'       => 8,
			'total_marks'        => 10,
			'percentage'         => 80.0,
			'total_questions'    => 10,
			'answered_questions' => 10,
			'started_at'         => '2026-01-01 12:00:00',
			'ended_at'           => '2026-01-01 12:07:30',
		];

		$lead = [
			'user_id'      => 5,
			'email'        => 'jane@example.com',
			'display_name' => 'Jane Doe',
			'magic_link'   => 'https://example.com/quiz/sample/?token=abc&user_id=5',
			'captured_at'  => '2026-01-01 12:00:00',
		];

		$quiz = [
			'quiz_id'     => 12,
			'quiz_title'  => 'WordPress Basics',
			'quiz_type'   => 'quiz',
			'permalink'   => 'https://example.com/quiz/wordpress-basics/',
			'quiz_status' => 'publish',
		];

		$question = [
			'question_id'     => 34,
			'question_title'  => 'What is a hook?',
			'question_type'   => 'single_choice',
			'question_score'  => 5,
			'question_status' => 'publish',
		];

		switch ( $event ) {
			case 'quiz_created':
				return array_merge(
					$quiz,
					[
						'created_at' => '2026-01-01 12:00:00',
					]
				);

			case 'quiz_updated':
				return array_merge(
					$quiz,
					[
						'updated_at' => '2026-01-01 12:00:00',
					]
				);

			case 'quiz_deleted':
				return array_merge(
					$quiz,
					[
						'deleted_at' => '2026-01-01 12:00:00',
					]
				);

			case 'quiz_started':
				return array_merge( $attempt, [ 'attempt_id' => 0, 'status' => '', 'started_at' => '2026-01-01 12:00:00' ] );

			case 'quiz_attempted':
				return array_merge( $attempt, [ 'attempt_number' => 1, 'is_reattempt' => false ] );

			case 'quiz_reattempted':
				return array_merge( $attempt, [ 'attempt_number' => 2, 'is_reattempt' => true ] );

			case 'quiz_submitted':
			case 'poll_submitted':
			case 'survey_submitted':
				return $attempt;

			case 'quiz_completed':
			case 'survey_completed':
				return $attempt;

			case 'quiz_score_reached':
				return array_merge( $attempt, [ 'score_percent' => 80, 'achieved_percent' => 80.0 ] );

			case 'quiz_passed':
				return array_merge( $attempt, [ 'status' => 'passed', 'earned_marks' => 8, 'percentage' => 80.0 ] );

			case 'quiz_failed':
				return array_merge( $attempt, [ 'status' => 'failed', 'earned_marks' => 4, 'percentage' => 40.0 ] );

			case 'poll_result_updated':
				return array_merge(
					$attempt,
					[
						'quiz_type'         => 'poll',
						'total_responses'   => 42,
						'result_updated_at' => '2026-01-01 12:07:30',
					]
				);

			case 'poll_answer_selected':
				return array_merge(
					$attempt,
					[
						'quiz_type'        => 'poll',
						'question_id'      => 34,
						'selected_answers' => [
							[
								'answer_id'  => 9,
								'answer'     => 'Yes',
								'is_correct' => true,
							],
						],
						'answered_by'      => 5,
						'answered_at'      => '2026-01-01 12:07:30',
					]
				);

			case 'certificate_issued':
				return array_merge(
					$attempt,
					[
						'certificate_url' => 'https://example.com/quiz/wordpress-basics/?source=qp-certificate&quiz_id=12',
						'issued_at'       => '2026-01-01 12:07:31',
					]
				);

			case 'certificate_downloaded':
				return [
					'quiz_id'          => 12,
					'quiz_title'       => 'WordPress Basics',
					'quiz_type'        => 'quiz',
					'permalink'        => 'https://example.com/quiz/wordpress-basics/',
					'quiz_status'      => 'publish',
					'certificate_url'  => 'https://example.com/quiz/wordpress-basics/?source=qp-certificate&quiz_id=12',
					'downloaded_by'    => 5,
					'downloaded_at'    => '2026-01-01 12:10:00',
				];

			case 'lead_captured':
				return $lead;

			case 'user_registered':
				return [
					'user_id'       => 5,
					'user_login'    => 'jane',
					'user_email'    => 'jane@example.com',
					'display_name'  => 'Jane Doe',
					'roles'         => [ 'subscriber' ],
					'registered_at' => '2026-01-01 12:00:00',
				];

			case 'question_added':
			case 'question_updated':
				return array_merge(
					$question,
					[
						'saved_at' => '2026-01-01 12:00:00',
					]
				);

			case 'question_deleted':
				return array_merge(
					$question,
					[
						'deleted_at' => '2026-01-01 12:00:00',
					]
				);

			case 'question_imported':
				return [
					'questions'      => [ $question ],
					'imported_count' => 1,
					'imported_at'    => '2026-01-01 12:00:00',
				];
		}//end switch

		return [];
	}

	protected static function qp_action_sample( string $action ): array {
		$quiz = [
			'quiz_id'     => 12,
			'quiz_title'  => 'WordPress Basics',
			'quiz_type'   => 'quiz',
			'permalink'   => 'https://example.com/quiz/wordpress-basics/',
			'quiz_status' => 'publish',
		];

		$attempt = array_merge(
			$quiz,
			[
				'attempt_id'  => 91,
				'user_id'     => 5,
				'status'      => 'passed',
				'earned_marks' => 8,
				'total_marks' => 10,
				'percentage'  => 80.0,
			]
		);

		switch ( $action ) {
			case 'create_quiz':
			case 'update_quiz':
			case 'publish_quiz':
			case 'draft_quiz':
				return $quiz;

			case 'delete_quiz':
				return [ 'quiz_id' => 12, 'deleted' => true ];

			case 'reset_quiz_attempt':
				return [
					'quiz_id'       => 12,
					'user_id'       => 5,
					'deleted_count' => 2,
					'deleted_attempts' => [ 88, 91 ],
				];

			case 'set_quiz_score':
				return array_merge( $attempt, [ 'earned_marks' => 9, 'percentage' => 90.0 ] );

			case 'set_quiz_result':
				return array_merge( $attempt, [ 'status' => 'passed' ] );

			case 'get_quiz_analytics':
				return array_merge(
					$quiz,
					[
						'total_attempts'   => 120,
						'unique_users'     => 64,
						'avg_percentage'   => 72.4,
						'pass_rate'        => 68.3,
						'status_breakdown' => [ 'passed' => 82, 'failed' => 30, 'pending' => 6, 'expired' => 2 ],
					]
				);

			case 'create_question':
			case 'update_question':
				return [
					'question_id'    => 34,
					'question_title' => 'What is a hook?',
					'question_type'  => 'single_choice',
					'question_score' => 5,
					'answers'        => [
						[ 'answer_id' => 9, 'answer_title' => 'Yes', 'is_correct' => true ],
						[ 'answer_id' => 10, 'answer_title' => 'No', 'is_correct' => false ],
					],
				];

			case 'delete_question':
				return [ 'question_id' => 34, 'deleted' => true ];

			case 'import_questions':
				return [
					'imported_count' => 5,
					'question_ids'   => [ 30, 31, 32, 33, 34 ],
				];

			case 'export_questions':
				return [
					'exported_count' => 42,
					'file_url'       => 'https://example.com/wp-content/uploads/quizpress-questions-2026-01-01.csv',
					'file_path'      => '/uploads/quizpress-questions-2026-01-01.csv',
				];

			case 'generate_certificate':
				return [
					'quiz_id'         => 12,
					'user_id'         => 5,
					'certificate_url' => 'https://example.com/quiz/wordpress-basics/?source=qp-certificate&quiz_id=12',
					'template_id'     => 3,
				];

			case 'send_certificate':
				return [
					'sent_to'         => 'jane@example.com',
					'quiz_id'         => 12,
					'user_id'         => 5,
					'certificate_url' => 'https://example.com/quiz/wordpress-basics/?source=qp-certificate&quiz_id=12',
				];

			case 'create_user':
			case 'update_user':
				return [
					'user_id'      => 5,
					'user_email'   => 'jane@example.com',
					'display_name' => 'Jane Doe',
					'roles'        => [ 'subscriber' ],
				];

			case 'send_email':
				return [
					'sent_to' => 'jane@example.com',
					'subject' => 'Your certificate is ready',
				];

			case 'create_post':
			case 'update_post':
				return [
					'post_id'   => 42,
					'title'     => 'Sample Post',
					'status'    => 'publish',
					'post_type' => 'post',
				];
		}//end switch

		return [];
	}
}
