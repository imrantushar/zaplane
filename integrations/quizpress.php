<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Integrations\Quizpress\QpConfigTrait;
use Zaplane\Integrations\Quizpress\QpTriggerResolverTrait;
use Zaplane\Integrations\Quizpress\QpSchemasTrait;
use Zaplane\Integrations\Quizpress\QpQuizActionsTrait;
use Zaplane\Integrations\Quizpress\QpQuestionActionsTrait;
use Zaplane\Integrations\Quizpress\QpUserActionsTrait;
use Zaplane\Integrations\Quizpress\QpCertificateActionsTrait;

class Quizpress extends IntegrationBase {

	use QpConfigTrait;
	use QpTriggerResolverTrait;
	use QpSchemasTrait;
	use QpQuizActionsTrait;
	use QpQuestionActionsTrait;
	use QpUserActionsTrait;
	use QpCertificateActionsTrait;

	public static function get_slug(): string {
		return 'quizpress';
	}

	public static function get_name(): string {
		return 'QuizPress';
	}

	public static function get_category(): string {
		return 'app';
	}

	public static function get_icon(): string {
		return 'quizpress.svg';
	}

	public static function get_docs_url(): array {
		return [
			'trigger' => 'https://zaplane.app/docs/trigger-quizpress/',
			'action'  => 'https://zaplane.app/docs/action-quizpress/',
		];
	}

	public static function get_triggers(): array {
		return [
			'quiz_created'       => [
				'label' => 'Create Quiz',
				'hook'  => 'save_post_quizpress_quiz',
			],
			'quiz_updated'       => [
				'label' => 'Update Quiz',
				'hook'  => 'save_post_quizpress_quiz',
			],
			'quiz_deleted'       => [
				'label' => 'Delete Quiz',
				'hook'  => [
					'before_delete_post',
					'trashed_post',
				],
			],
			'quiz_started'       => [
				'label' => 'Quiz Started',
				'hook'  => 'quizpress/api/before_quiz_attempt_start',
			],
			'quiz_attempted'     => [
				'label' => 'Quiz Attempted',
				'hook'  => 'quizpress/api/after_quiz_attempt_start',
			],
			'quiz_reattempted'   => [
				'label' => 'Quiz Reattempted',
				'hook'  => 'quizpress/api/after_quiz_attempt_start',
			],
			'quiz_submitted'     => [
				'label' => 'Quiz Submitted',
				'hook'  => 'quizpress/api/after_quiz_attempt_finished',
			],
			'quiz_completed'     => [
				'label' => 'Quiz Completed',
				'hook'  => 'quizpress/api/after_quiz_attempt_finished',
			],
			'quiz_score_reached' => [
				'label' => 'Quiz Score Reached',
				'hook'  => 'quizpress/api/after_quiz_attempt_finished',
			],
			'quiz_passed'        => [
				'label' => 'Quiz Passed',
				'hook'  => [
					'quizpress/frontend/quiz_attempt_status_passed',
					'quizpress/quiz_attempt_status_passed',
				],
			],
			'quiz_failed'        => [
				'label' => 'Quiz Failed',
				'hook'  => [
					'quizpress/frontend/quiz_attempt_status_failed',
					'quizpress/quiz_attempt_status_failed',
				],
			],
			'poll_submitted'     => [
				'label' => 'Poll Submitted',
				'hook'  => 'quizpress/api/after_quiz_attempt_finished',
			],
			'poll_answer_selected' => [
				// No QuizPress hook fires for a single answer insert (admin-ajax
				// only), so the trigger listens on shutdown and reconstructs the
				// selection from the request + the attempt-answers table.
				'label' => 'Poll Answer Selected',
				'hook'  => 'shutdown',
			],
			'poll_result_updated' => [
				// Poll results change with every finished poll attempt — there is
				// no separate results hook to listen on.
				'label' => 'Poll Result Updated',
				'hook'  => 'quizpress/api/after_quiz_attempt_finished',
			],
			'survey_submitted'   => [
				'label' => 'Survey Submitted',
				'hook'  => 'quizpress/api/after_quiz_attempt_finished',
			],
			'survey_completed'   => [
				'label' => 'Survey Completed',
				'hook'  => 'quizpress/api/after_quiz_attempt_finished',
			],
			'certificate_issued' => [
				// QuizPress renders certificates on demand and keeps no issuance
				// record, so "issued" means a passed attempt while the Certificates
				// addon is enabled — the moment the certificate becomes available.
				'label' => 'Certificate Issued',
				'hook'  => 'quizpress/frontend/quiz_attempt_status_passed',
			],
			'certificate_downloaded' => [
				'label' => 'Certificate Downloaded',
				'hook'  => 'template_include',
			],
			'lead_captured'      => [
				'label' => 'Lead Captured',
				'hook'  => 'quizpress/after_create_magic_link',
			],
			'user_registered'    => [
				'label' => 'User Registered',
				'hook'  => 'user_register',
			],
			'question_added'     => [
				// Questions live in a custom table with no save hooks, so the
				// triggers read the REST request itself on rest_pre_dispatch —
				// the same pattern the ECM claim triggers use.
				'label' => 'Create Question',
				'hook'  => 'rest_pre_dispatch',
			],
			'question_updated'   => [
				'label' => 'Update Question',
				'hook'  => 'rest_pre_dispatch',
			],
			'question_deleted'   => [
				'label' => 'Delete Question',
				'hook'  => 'rest_pre_dispatch',
			],
			'question_imported'  => [
				// QuizPress imports questions through admin-ajax, which never
				// reaches REST and fires no hook, so the trigger listens on
				// shutdown and picks up the questions created during the request.
				'label' => 'Question Imported',
				'hook'  => 'shutdown',
			],
		];
	}

	public static function get_actions(): array {
		return [
			'create_quiz'         => [ 'label' => 'Create Quiz' ],
			'update_quiz'         => [ 'label' => 'Update Quiz' ],
			'delete_quiz'         => [ 'label' => 'Delete Quiz' ],
			'publish_quiz'        => [ 'label' => 'Publish Quiz' ],
			'draft_quiz'          => [ 'label' => 'Draft Quiz' ],
			'reset_quiz_attempt'  => [ 'label' => 'Reset Quiz Attempt' ],
			'set_quiz_score'      => [ 'label' => 'Set Quiz Score' ],
			'set_quiz_result'     => [ 'label' => 'Set Quiz Result' ],
			'get_quiz_analytics'  => [ 'label' => 'Get Quiz Analytics' ],
			'create_question'     => [ 'label' => 'Create Question' ],
			'update_question'     => [ 'label' => 'Update Question' ],
			'delete_question'     => [ 'label' => 'Delete Question' ],
			'import_questions'    => [ 'label' => 'Import Questions' ],
			'export_questions'    => [ 'label' => 'Export Questions' ],
			'generate_certificate' => [ 'label' => 'Generate Certificate' ],
			'send_certificate'    => [ 'label' => 'Send Certificate' ],
			'create_user'         => [ 'label' => 'Create User' ],
			'update_user'         => [ 'label' => 'Update User' ],
			'send_email'          => [ 'label' => 'Send Email' ],
			'create_post'         => [ 'label' => 'Create Post' ],
			'update_post'         => [ 'label' => 'Update Post' ],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		return static::qp_trigger_config_schema( $trigger );
	}

	public static function get_trigger_sample_output( string $event ): array {
		return static::qp_trigger_sample( $event );
	}

	public static function get_action_config_schema( string $action ): array {
		return static::qp_action_config_schema( $action );
	}

	public static function get_action_sample_output( string $action ): array {
		return static::qp_action_sample( $action );
	}

	public static function get_dynamic_queries(): array {
		return static::qp_dynamic_queries();
	}

	public static function resolve_trigger( array $node, array $args ) {
		return static::qp_resolve_trigger( $node, $args );
	}

	public static function execute_node( array $node, array $input ): array {
		$data   = is_array( $node['data'] ?? null ) ? $node['data'] : [];
		$config = is_array( $data['config'] ?? null ) ? $data['config'] : [];
		$event  = (string) ( $data['event'] ?? '' );

		if ( '' === $event ) {
			return static::qp_unknown_action( '(no action configured)' );
		}

		if ( ! static::qp_active() ) {
			return static::qp_action_error( 'The QuizPress plugin is not active on this site.' );
		}

		$result = static::qp_execute_quiz_action( $event, $config );

		if ( null !== $result ) {
			return $result;
		}

		$result = static::qp_execute_question_action( $event, $config );

		if ( null !== $result ) {
			return $result;
		}

		$result = static::qp_execute_user_action( $event, $config );

		if ( null !== $result ) {
			return $result;
		}

		$result = static::qp_execute_certificate_action( $event, $config );

		if ( null !== $result ) {
			return $result;
		}

		return static::qp_unknown_action( $event );
	}
}
