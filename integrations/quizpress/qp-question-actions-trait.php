<?php

namespace Zaplane\Integrations\Quizpress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait QpQuestionActionsTrait {

	protected static function qp_execute_question_action( string $event, array $config ): ?array {
		switch ( $event ) {
			case 'create_question':
			case 'update_question':
				return static::qp_action_save_question( $config, 'update_question' === $event );

			case 'delete_question':
				return static::qp_action_delete_question( $config );

			case 'import_questions':
				return static::qp_action_import_questions( $config );

			case 'export_questions':
				return static::qp_action_export_questions( $config );
		}//end switch

		return null;
	}

	private static function qp_action_save_question( array $config, bool $is_update ): array {
		global $wpdb;

		if ( ! class_exists( '\QuizPress\API\Query\Questions' ) ) {
			return static::qp_action_error( 'The QuizPress question API is not available.' );
		}

		$question_id = absint( $config['question_id'] ?? 0 );

		if ( $is_update && ! $question_id ) {
			return static::qp_action_error( 'A question ID is required to update a question.' );
		}

		if ( ! $is_update && '' === trim( (string) ( $config['question_title'] ?? '' ) ) ) {
			return static::qp_action_error( 'A question title is required.' );
		}

		if ( $is_update && ! static::qp_get_question( $question_id ) ) {
			return static::qp_action_error( 'Question not found.' );
		}

		$postarr = [
			'question_title' => (string) ( $config['question_title'] ?? '' ),
			'question_type'  => sanitize_key( (string) ( $config['question_type'] ?? '' ) ),
		];

		if ( $is_update ) {
			$postarr['question_id'] = $question_id;

			// Blank fields mean "keep": only pass what the config carries.
			foreach ( [ 'question_title', 'question_type' ] as $key ) {
				if ( '' === trim( (string) $postarr[ $key ] ) ) {
					unset( $postarr[ $key ] );
				}
			}

			foreach ( [ 'question_content', 'question_explanation' ] as $key ) {
				if ( isset( $config[ $key ] ) && '' !== (string) $config[ $key ] ) {
					$postarr[ $key ] = (string) $config[ $key ];
				}
			}
		} else {
			$postarr['question_content']     = (string) ( $config['question_content'] ?? '' );
			$postarr['question_explanation'] = (string) ( $config['question_explanation'] ?? '' );
			$postarr['question_status']      = sanitize_key( (string) ( $config['question_status'] ?? 'publish' ) );
		}

		if ( isset( $config['question_score'] ) && '' !== (string) $config['question_score'] ) {
			$postarr['question_score'] = (float) $config['question_score'];
		}

		if ( isset( $config['question_negative_score'] ) && '' !== (string) $config['question_negative_score'] ) {
			$postarr['question_negative_score'] = (float) $config['question_negative_score'];
		}

		$question_id = (int) \QuizPress\API\Query\Questions::quiz_question_insert( $postarr );

		if ( ! $question_id ) {
			return static::qp_action_error( 'The question could not be saved.' );
		}

		$answers = static::qp_parse_answer_lines( (string) ( $config['answers_text'] ?? '' ) );
		$saved_answers = [];

		if ( ! empty( $answers ) ) {
			// A new answer set replaces the old one — the textarea is the
			// single source of truth for the question's answers.
			if ( class_exists( '\QuizPress\API\Query\Answers' ) && method_exists( '\QuizPress\API\Query\Answers', 'delete_answer' ) ) {
				\QuizPress\API\Query\Answers::delete_answer( $question_id );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, no cache API.
				$wpdb->delete( static::qp_answers_table(), [ 'question_id' => $question_id ], [ '%d' ] );
			}

			foreach ( $answers as $index => $answer ) {
				$answer_id = (int) \QuizPress\API\Query\Answers::quiz_answer_insert(
					[
						'question_id'   => $question_id,
						'answer_title'  => $answer['title'],
						'is_correct'    => $answer['is_correct'],
						'answer_points' => $answer['is_correct'] ? 1 : 0,
						'answer_order'  => $index,
					]
				);

				$saved_answers[] = [
					'answer_id'    => $answer_id,
					'answer_title' => $answer['title'],
					'is_correct'   => $answer['is_correct'],
				];
			}
		}

		$row = static::qp_get_question( $question_id );

		return static::qp_success(
			[
				'question_id'     => $question_id,
				'question_title'  => (string) ( $row->question_title ?? $postarr['question_title'] ?? '' ),
				'question_type'   => (string) ( $row->question_type ?? $postarr['question_type'] ?? '' ),
				'question_score'  => (float) ( $row->question_score ?? $postarr['question_score'] ?? 0 ),
				'question_status' => (string) ( $row->question_status ?? '' ),
				'answers'         => $saved_answers,
			]
		);
	}

	private static function qp_action_delete_question( array $config ): array {
		$question_id = absint( $config['question_id'] ?? 0 );

		if ( ! static::qp_get_question( $question_id ) ) {
			return static::qp_action_error( 'Question not found.' );
		}

		if ( class_exists( '\QuizPress\API\Query\Questions' ) && method_exists( '\QuizPress\API\Query\Questions', 'delete_question' ) ) {
			// QuizPress's own delete cascades through answers and attempt answers.
			\QuizPress\API\Query\Questions::delete_question( $question_id );
		} else {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, no cache API.
			$wpdb->delete( static::qp_answers_table(), [ 'question_id' => $question_id ], [ '%d' ] );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, no cache API.
			$wpdb->delete( static::qp_questions_table(), [ 'question_id' => $question_id ], [ '%d' ] );
		}

		return static::qp_success(
			[
				'question_id' => $question_id,
				'deleted'     => true,
			]
		);
	}

	private static function qp_action_import_questions( array $config ): array {
		if ( ! class_exists( '\QuizPress\API\Query\Questions' ) ) {
			return static::qp_action_error( 'The QuizPress question API is not available.' );
		}

		$source = (string) ( $config['source'] ?? 'csv_url' );

		if ( 'csv_text' === $source ) {
			$csv = (string) ( $config['csv_text'] ?? '' );
		} else {
			$url = trim( (string) ( $config['csv_url'] ?? '' ) );

			if ( '' === $url ) {
				return static::qp_action_error( 'A CSV file URL is required.' );
			}

			if ( ! wp_http_validate_url( $url ) ) {
				return static::qp_action_error( 'The CSV URL is not valid.' );
			}

			$response = wp_remote_get( $url, [ 'timeout' => 30 ] );

			if ( is_wp_error( $response ) ) {
				return static::qp_action_error( $response->get_error_message() );
			}

			$csv = (string) wp_remote_retrieve_body( $response );
		}

		if ( '' === trim( $csv ) ) {
			return static::qp_action_error( 'The CSV content is empty.' );
		}

		$rows    = static::qp_parse_csv( $csv );
		$imported = 0;
		$ids      = [];
		$current  = null;

		foreach ( $rows as $row ) {
			$title = trim( (string) ( $row['question_title'] ?? '' ) );
			$answer_title = trim( (string) ( $row['answer_title'] ?? '' ) );

			if ( '' !== $title ) {
				$question_id = (int) \QuizPress\API\Query\Questions::quiz_question_insert(
					[
						'question_title' => $title,
						'question_type'  => sanitize_key( (string) ( $row['question_type'] ?? 'single_choice' ) ),
						'question_score' => (float) ( $row['question_score'] ?? 0 ),
						'question_status' => 'publish',
					]
				);

				if ( $question_id ) {
					++$imported;
					$ids[] = $question_id;
					$current = $question_id;
				}

				continue;
			}

			if ( '' !== $answer_title && $current ) {
				\QuizPress\API\Query\Answers::quiz_answer_insert(
					[
						'question_id'   => $current,
						'answer_title'  => $answer_title,
						'is_correct'    => static::qp_truthy( $row['is_correct'] ?? '' ),
						'answer_points' => absint( $row['answer_points'] ?? 0 ),
					]
				);
			}
		}//end foreach

		if ( ! $imported ) {
			return static::qp_action_error( 'No questions could be imported from the CSV. Expected a question_title column.' );
		}

		return static::qp_success(
			[
				'imported_count' => $imported,
				'question_ids'   => $ids,
			]
		);
	}

	private static function qp_action_export_questions( array $config ): array {
		global $wpdb;

		$quiz_id = absint( $config['quiz_id'] ?? 0 );

		if ( $quiz_id && ! static::qp_get_quiz( $quiz_id ) ) {
			return static::qp_action_error( 'Quiz not found.' );
		}

		$question_ids = [];

		if ( $quiz_id ) {
			$questions_meta = get_post_meta( $quiz_id, 'quizpress_quiz_questions', true );

			if ( is_string( $questions_meta ) && '' !== $questions_meta ) {
				$questions_meta = json_decode( $questions_meta, true );
			}

			foreach ( (array) $questions_meta as $entry ) {
				$id = is_array( $entry ) ? absint( $entry['id'] ?? 0 ) : absint( $entry );

				if ( $id ) {
					$question_ids[] = $id;
				}
			}
		}

		if ( empty( $question_ids ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT question_id, question_title, question_type, question_score, question_content, question_explanation, question_status
					 FROM %i WHERE question_status = %s ORDER BY question_id ASC LIMIT 5000',
					static::qp_questions_table(),
					'publish'
				),
				ARRAY_A
			);
		} else {
			$placeholders = implode( ',', array_fill( 0, count( $question_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT question_id, question_title, question_type, question_score, question_content, question_explanation, question_status
					 FROM %i WHERE question_id IN ( $placeholders ) ORDER BY question_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders only, values bound below.
					array_merge( [ static::qp_questions_table() ], $question_ids )
				),
				ARRAY_A
			);
		}

		if ( empty( $rows ) ) {
			return static::qp_action_error( 'No questions found to export.' );
		}

		$lines = [ 'question_title,question_type,question_score,question_content,question_explanation,question_status,answer_title,is_correct,answer_points' ];

		foreach ( $rows as $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
			$answers = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT answer_title, is_correct, answer_points FROM %i WHERE question_id = %d ORDER BY answer_order ASC',
					static::qp_answers_table(),
					(int) ( $row['question_id'] ?? 0 )
				),
				ARRAY_A
			);

			if ( empty( $answers ) ) {
				$answers = [ [ 'answer_title' => '', 'is_correct' => '', 'answer_points' => '' ] ];
			}

			foreach ( $answers as $answer ) {
				$lines[] = implode(
					',',
					array_map(
						static fn( $value ) => static::qp_csv_field( (string) $value ),
						[
							$row['question_title'] ?? '',
							$row['question_type'] ?? '',
							$row['question_score'] ?? '',
							$row['question_content'] ?? '',
							$row['question_explanation'] ?? '',
							$row['question_status'] ?? '',
							$answer['answer_title'] ?? '',
							$answer['is_correct'] ?? '',
							$answer['answer_points'] ?? '',
						]
					)
				);
			}
		}//end foreach

		$upload = wp_upload_bits(
			sprintf( 'quizpress-questions-%s.csv', gmdate( 'Y-m-d' ) ),
			null,
			implode( "\n", $lines )
		);

		if ( ! empty( $upload['error'] ) ) {
			return static::qp_action_error( (string) $upload['error'] );
		}

		return static::qp_success(
			[
				'exported_count' => count( $rows ),
				'file_url'       => (string) $upload['url'],
				'file_path'      => (string) $upload['file'],
			]
		);
	}

	protected static function qp_get_question( int $question_id ) {
		global $wpdb;

		if ( ! $question_id ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no cache API.
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE question_id = %d',
				static::qp_questions_table(),
				$question_id
			)
		);
	}

	/**
	 * Parse the answers textarea: one answer per line, a leading * marks the
	 * correct one.
	 *
	 * @return array<int,array{title:string,is_correct:bool}>
	 */
	protected static function qp_parse_answer_lines( string $text ): array {
		$answers = [];

		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$is_correct = false;

			if ( 0 === strpos( $line, '*' ) ) {
				$is_correct = true;
				$line       = trim( substr( $line, 1 ) );
			}

			if ( '' !== $line ) {
				$answers[] = [
					'title'      => $line,
					'is_correct' => $is_correct,
				];
			}
		}//end foreach

		return $answers;
	}

	/**
	 * Parse a CSV string into associative rows using the header line.
	 *
	 * @return array<int,array<string,string>>
	 */
	protected static function qp_parse_csv( string $csv ): array {
		$lines = preg_split( '/\r\n|\r|\n/', trim( $csv ) );

		if ( empty( $lines ) ) {
			return [];
		}

		$headers = array_map( 'trim', str_getcsv( array_shift( $lines ) ) );
		$rows    = [];

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}

			$values = str_getcsv( $line );
			$row    = [];

			foreach ( $headers as $index => $header ) {
				$row[ $header ] = (string) ( $values[ $index ] ?? '' );
			}

			$rows[] = $row;
		}//end foreach

		return $rows;
	}

	protected static function qp_csv_field( string $value ): string {
		return ( false !== strpos( $value, '"' ) || false !== strpos( $value, ',' ) || false !== strpos( $value, "\n" ) )
			? '"' . str_replace( '"', '""', $value ) . '"'
			: $value;
	}

	protected static function qp_truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( trim( (string) $value ) ), [ '1', 'true', 'yes', 'y', 'correct' ], true );
	}
}
