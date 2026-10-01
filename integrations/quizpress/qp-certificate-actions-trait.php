<?php

namespace Zaplane\Integrations\Quizpress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait QpCertificateActionsTrait {

	protected static function qp_execute_certificate_action( string $event, array $config ): ?array {
		switch ( $event ) {
			case 'generate_certificate':
				return static::qp_action_generate_certificate( $config );

			case 'send_certificate':
				return static::qp_action_send_certificate( $config );
		}//end switch

		return null;
	}

	/**
	 * QuizPress renders certificates on demand — there is no issuance step to
	 * call, so "generate" resolves and verifies the printable certificate URL
	 * the same way QuizPress's own frontend does.
	 */
	private static function qp_action_generate_certificate( array $config ): array {
		$quiz_id = absint( $config['quiz_id'] ?? 0 );

		if ( ! static::qp_get_quiz( $quiz_id ) ) {
			return static::qp_action_error( 'Quiz not found.' );
		}

		if ( ! static::qp_certificates_addon_enabled() ) {
			return static::qp_action_error( 'The QuizPress Certificates addon is not enabled.' );
		}

		$user_id = absint( $config['user_id'] ?? 0 );

		if ( ! get_userdata( $user_id ) ) {
			return static::qp_action_error( 'User not found.' );
		}

		$passed = static::qp_get_passed_attempt( $quiz_id, $user_id );

		if ( ! $passed ) {
			return static::qp_action_error( 'No passed attempt found for this user on this quiz.' );
		}

		$template_id = absint( static::qp_primary_certificate_id() );

		return static::qp_success(
			array_merge(
				static::qp_quiz_payload( $quiz_id ),
				[
					'user_id'         => $user_id,
					'certificate_url' => static::qp_certificate_url( $quiz_id ),
					'template_id'     => $template_id,
					'attempt_id'      => (int) $passed->attempt_id,
					'completed_at'    => (string) ( $passed->attempt_ended_at ?? '' ),
				]
			)
		);
	}

	private static function qp_action_send_certificate( array $config ): array {
		$quiz_id = absint( $config['quiz_id'] ?? 0 );

		if ( ! static::qp_get_quiz( $quiz_id ) ) {
			return static::qp_action_error( 'Quiz not found.' );
		}

		if ( ! static::qp_certificates_addon_enabled() ) {
			return static::qp_action_error( 'The QuizPress Certificates addon is not enabled.' );
		}

		$user_id = absint( $config['user_id'] ?? 0 );
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return static::qp_action_error( 'User not found.' );
		}

		if ( ! static::qp_get_passed_attempt( $quiz_id, $user_id ) ) {
			return static::qp_action_error( 'No passed attempt found for this user on this quiz.' );
		}

		$to = sanitize_email( (string) ( $config['email'] ?? '' ) );

		if ( '' === $to ) {
			$to = (string) ( $user->user_email ?? '' );
		}

		if ( ! is_email( $to ) ) {
			return static::qp_action_error( 'A valid recipient email address is required.' );
		}

		$certificate_url = static::qp_certificate_url( $quiz_id );
		$quiz_title      = (string) ( static::qp_get_quiz( $quiz_id )->post_title ?? '' );

		$subject = sprintf( 'Your certificate for %s', $quiz_title );
		$message = sprintf(
			'<p>Hi %s,</p><p>Congratulations! Your certificate for <strong>%s</strong> is ready:</p><p><a href="%s">Download your certificate</a></p>',
			esc_html( (string) ( $user->display_name ?? '' ) ),
			esc_html( $quiz_title ),
			esc_url( $certificate_url )
		);

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		if ( ! wp_mail( $to, $subject, $message, $headers ) ) {
			return static::qp_action_error( 'The certificate email could not be sent.' );
		}

		return static::qp_success(
			[
				'sent_to'         => $to,
				'quiz_id'         => $quiz_id,
				'user_id'         => $user_id,
				'certificate_url' => $certificate_url,
			]
		);
	}

	protected static function qp_get_passed_attempt( int $quiz_id, int $user_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT attempt_id, attempt_ended_at, earned_marks, total_marks
				 FROM %i WHERE quiz_id = %d AND user_id = %d AND attempt_status = %s
				 ORDER BY attempt_id DESC LIMIT 1",
				static::qp_attempts_table(),
				$quiz_id,
				$user_id,
				'passed'
			)
		);
	}

	protected static function qp_primary_certificate_id(): int {
		$settings = json_decode( (string) get_option( 'quizpress_settings', '{}' ) );

		return absint( $settings->quizpress_primary_certificate_id ?? 0 );
	}
}
