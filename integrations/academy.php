<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Academy extends IntegrationBase {

	// -------------------------------------------------------------------------
	// BASIC INTEGRATION INFO
	// -------------------------------------------------------------------------

	public static function get_slug(): string {
		return 'academy';
	}

	public static function get_name(): string {
		return 'Academy LMS';
	}

	public static function get_icon(): string {
		return 'academy.svg';
	}

	// -------------------------------------------------------------------------
	// TRIGGERS
	// -------------------------------------------------------------------------

	public static function get_triggers(): array {
		return [
			'user_enroll_course' => [
				'label' => 'User enrolled in a course',
				'hook'  => 'academy/course/after_enroll'
			],
			'academy_quiz_course_attempt' => [
				'label' => 'User attempted (submitted) a quiz',
				'hook'  => 'academy_quizzes/api/after_quiz_attempt_finished'
			],
			'lesson_complete' => [
				'label' => 'User completed a lesson',
				'hook'  => 'academy/frontend/after_mark_topic_complete'
			],
			'course_complete' => [
				'label' => 'User completed a course',
				'hook'  => 'academy/admin/course_complete_after'
			],
			'quiz_target' => [
				'label' => 'User achieved target percentage on a quiz',
				'hook'  => 'academy_quizzes/api/after_quiz_attempt_finished'
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( in_array( $trigger, [ 'user_enroll_course', 'course_complete' ], true ) ) {
			return [
				[
					'key'      => 'course_id',
					'label'    => 'Course',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'academy',
						'query'       => 'acourse',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}

		if ( in_array( $trigger, [ 'academy_quiz_course_attempt' ], true ) ) {
			return [
				[
					'key'      => 'quiz_id',
					'label'    => 'Quiz',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'academy',
						'query'       => 'quiz',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}

		if ( 'quiz_target' === $trigger ) {
			return [
				[
					'key'      => 'quiz_id',
					'label'    => 'Quiz',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'academy',
						'query'       => 'quiz',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
				[
					'key'      => 'target_percentage',
					'label'    => 'Target Percentage (%)',
					'type'     => 'number',
					'required' => true,
				],
			];
		}//end if

		if ( in_array( $trigger, [ 'lesson_complete' ], true ) ) {
			return [
				[
					'key'      => 'lesson_id',
					'label'    => 'Lesson',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'academy',
						'query'       => 'lesson',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}

		return [];
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		$samples = [
			'user_enroll_course'           => [
				'success'    => true,
				'course_id'  => 1,
				'enroll_id'  => 1,
				'user_id'    => 1,
				'user_email' => 'student@example.com',
				'first_name' => 'Jane',
				'last_name'  => 'Smith',
				'username'   => 'janesmith',
			],
			'course_complete'              => [
				'success'      => true,
				'course_id'    => 1,
				'course_title' => 'Sample Course',
				'course_url'   => 'https://example.com/course/sample-course',
				'user_id'      => 1,
				'user_email'   => 'student@example.com',
				'first_name'   => 'Jane',
				'last_name'    => 'Smith',
			],
			'lesson_complete'              => [
				'success'   => true,
				'lesson_id' => 1,
				'user_id'   => 1,
			],
			'academy_quiz_course_attempt'  => [
				'success' => true,
				'quiz_id' => 1,
				'user_id' => 1,
				'score'   => 8,
				'total'   => 10,
			],
			'quiz_target'                  => [
				'success'     => true,
				'quiz_id'     => 1,
				'user_id'     => 1,
				'score'       => 8,
				'total_marks' => 10,
				'percentage'  => 80.00,
			],
		];

		return $samples[ $trigger ] ?? [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'user_enroll_course':
				$course_id = $args[0] ?? null;
				$enroll_id = $args[1] ?? null;
				$user_id   = $args[2] ?? null;

				if ( ! $course_id || ! $enroll_id ) {
					return false;
				}

				$selected_course = $node['data']['config']['course_id'] ?? 'any';

				if ( 'any' !== $selected_course && (int) $selected_course !== (int) $course_id ) {
					return false;
				}

				$user = $user_id ? get_userdata( (int) $user_id ) : null;

				return [
					'success'    => true,
					'course_id'  => (int) $course_id,
					'enroll_id'  => (int) $enroll_id,
					'user_id'    => $user ? (int) $user->ID : null,
					'user_email' => $user ? $user->user_email : null,
					'first_name' => $user ? $user->first_name : null,
					'last_name'  => $user ? $user->last_name : null,
					'username'   => $user ? $user->user_login : null,
				];

			case 'course_complete':
				$course_id = $args[0] ?? null;
				$user_id   = $args[1] ?? get_current_user_id();

				if ( ! $course_id || ! $user_id ) {
					return false;
				}

				$selected_course = $node['data']['config']['course_id'] ?? 'any';

				if ( 'any' !== $selected_course && (int) $selected_course !== (int) $course_id ) {
					return false;
				}

				$course = get_post( $course_id );
				$user   = get_userdata( $user_id );

				if ( ! $course || ! $user ) {
					return false;
				}

				return [
					'success'      => true,
					'course_id'    => $course->ID,
					'course_title' => $course->post_title,
					'course_url'   => get_permalink( $course->ID ),
					'user_id'      => $user_id,
					'user_email'   => $user->user_email,
					'first_name'   => $user->first_name,
					'last_name'    => $user->last_name,
				];

			case 'lesson_complete':
				$lesson_id = $args[0] ?? null;
				$user_id   = $args[1] ?? get_current_user_id();

				if ( ! $lesson_id || ! $user_id ) {
					return false;
				}

				$selected_lesson = $node['data']['config']['lesson_id'] ?? 'any';

				if ( 'any' !== $selected_lesson && (int) $selected_lesson !== (int) $lesson_id ) {
					return false;
				}

				return [
					'success'   => true,
					'lesson_id' => $lesson_id,
					'user_id'   => $user_id,
				];

			case 'academy_quiz_course_attempt':
				$attempt = $args[0] ?? null;
				if ( ! $attempt ) {
					return false;
				}

				if ( 'pending' === $attempt->attempt_status ) {
					return false;
				}

				$quiz_id = $attempt->quiz_id ?? null;
				$user_id = $attempt->user_id ?? null;

				if ( ! $quiz_id || ! $user_id ) {
					return false;
				}

				$selected_quiz = $node['data']['config']['quiz_id'] ?? 'any';

				if ( 'any' !== $selected_quiz && (int) $selected_quiz !== (int) $quiz_id ) {
					return false;
				}

				return [
					'success' => true,
					'quiz_id' => $quiz_id,
					'user_id' => $user_id,
					'score'   => $attempt->earned_marks ?? 0,
					'total'   => $attempt->total_marks ?? 0,
				];

			case 'quiz_target':
				$attempt = $args[0] ?? null;
				if ( ! $attempt ) {
					return false;
				}

				if ( 'pending' === $attempt->attempt_status ) {
					return false;
				}

				$quiz_id = $attempt->quiz_id ?? null;
				$user_id = $attempt->user_id ?? null;
				$earned  = $attempt->earned_marks ?? 0;
				$total   = $attempt->total_marks ?? 0;

				if ( ! $quiz_id || ! $user_id || ! $total ) {
					return false;
				}

				$selected_quiz = $node['data']['config']['quiz_id'] ?? 'any';

				if ( 'any' !== $selected_quiz && (int) $selected_quiz !== (int) $quiz_id ) {
					return false;
				}

				$percentage = ( $earned / $total ) * 100;
				$target     = (float) ( $node['data']['config']['target_percentage'] ?? 0 );

				if ( $percentage < $target ) {
					return false;
				}

				return [
					'success'     => true,
					'quiz_id'     => $quiz_id,
					'user_id'     => $user_id,
					'score'       => $earned,
					'total_marks' => $total,
					'percentage'  => round( $percentage, 2 ),
				];
		}//end switch

		return false;
	}

	// -------------------------------------------------------------------------
	// ACTIONS – HELPER METHODS
	// -------------------------------------------------------------------------

	/**
	 * Get the list of allowed action names.
	 */
	private static function get_allowed_actions(): array {
		return [
			'enroll-course',
			'unenroll-course',
			'complete-lesson',
			'complete-course',
		];
	}

	/**
	 * Recursively search for any allowed action string in an array.
	 */
	private static function recursive_search_allowed( $data, array $allowed ): string {
		if ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				if ( is_string( $value ) && in_array( $value, $allowed, true ) ) {
					return $value;
				} elseif ( is_array( $value ) ) {
					$found = self::recursive_search_allowed( $value, $allowed );
					if ( $found ) {
						return $found;
					}
				}
			}
		}
		return '';
	}

	/**
	 * Extract the action name from the node using multiple strategies.
	 */
	private static function extract_action_from_node( array $node ): string {
		$allowed = self::get_allowed_actions();

		// 1. Priority keys (skip literal "action")
		$keys_to_check = [ 'action', 'event', 'actionName', 'name', 'type' ];
		foreach ( $keys_to_check as $key ) {
			if ( isset( $node[ $key ] ) && is_string( $node[ $key ] ) && $node[ $key ] !== 'action' ) {
				return $node[ $key ];
			}
		}

		// 2. Nested arrays
		$nested_keys = [ 'data', 'flow_details', 'config' ];
		foreach ( $nested_keys as $nested ) {
			if ( isset( $node[ $nested ] ) && is_array( $node[ $nested ] ) ) {
				foreach ( $keys_to_check as $key ) {
					if ( isset( $node[ $nested ][ $key ] ) && is_string( $node[ $nested ][ $key ] ) && $node[ $nested ][ $key ] !== 'action' ) {
						return $node[ $nested ][ $key ];
					}
				}
			}
		}

		// 3. Recursive scan for any allowed action string
		$found = self::recursive_search_allowed( $node, $allowed );
		if ( $found ) {
			return $found;
		}

		return '';
	}

	/**
	 * Extract the configuration array from the node.
	 */
	private static function extract_config_from_node( array $node ): array {
		if ( isset( $node['config'] ) && is_array( $node['config'] ) ) {
			return $node['config'];
		}
		if ( isset( $node['data']['config'] ) && is_array( $node['data']['config'] ) ) {
			return $node['data']['config'];
		}
		if ( isset( $node['flow_details'] ) && is_array( $node['flow_details'] ) ) {
			return $node['flow_details'];
		}
		// If the node itself looks like config (has selectedCourse or user_id)
		if ( isset( $node['selectedCourse'] ) || isset( $node['user_id'] ) || isset( $node['selectedLesson'] ) ) {
			return $node;
		}
		return [];
	}

	/**
	 * Extract course IDs from config, supporting various formats.
	 */
	private static function extract_course_ids( array $config ): array {
		$course_ids = [];
		$keys = [ 'selectedCourse', 'selected_course', 'courses', 'course_id', 'courseIds' ];

		foreach ( $keys as $key ) {
			if ( isset( $config[ $key ] ) && ! empty( $config[ $key ] ) ) {
				$value = $config[ $key ];
				if ( is_array( $value ) ) {
					foreach ( $value as $item ) {
						if ( is_numeric( $item ) ) {
							$course_ids[] = (int) $item;
						} elseif ( is_object( $item ) || is_array( $item ) ) {
							$item = (array) $item;
							if ( isset( $item['courseId'] ) ) {
								$course_ids[] = (int) $item['courseId'];
							} elseif ( isset( $item['id'] ) ) {
								$course_ids[] = (int) $item['id'];
							}
						}
					}
				} elseif ( is_numeric( $value ) ) {
					$course_ids[] = (int) $value;
				}
				if ( ! empty( $course_ids ) ) {
					break;
				}
			}//end if
		}//end foreach

		// If 'all_courses' is true, fetch all course IDs (overrides any selection)
		if ( ! empty( $config['all_courses'] ) ) {
			$all_courses = get_posts([
				'post_type'      => 'academy_courses',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
			]);
			$course_ids = array_map( 'intval', $all_courses );
		}

		return $course_ids;
	}

	/**
	 * Extract lesson IDs from config, supporting various formats.
	 */
	private static function extract_lesson_ids( array $config ): array {
		$lesson_ids = [];
		$keys = [ 'selectedLesson', 'selected_lesson', 'lesson_id', 'lessonIds' ];

		foreach ( $keys as $key ) {
			if ( isset( $config[ $key ] ) && ! empty( $config[ $key ] ) ) {
				$value = $config[ $key ];
				if ( is_array( $value ) ) {
					foreach ( $value as $item ) {
						if ( is_numeric( $item ) ) {
							$lesson_ids[] = (int) $item;
						} elseif ( is_object( $item ) || is_array( $item ) ) {
							$item = (array) $item;
							if ( isset( $item['lessonId'] ) ) {
								$lesson_ids[] = (int) $item['lessonId'];
							} elseif ( isset( $item['id'] ) ) {
								$lesson_ids[] = (int) $item['id'];
							}
						}
					}
				} elseif ( is_numeric( $value ) ) {
					$lesson_ids[] = (int) $value;
				}
				if ( ! empty( $lesson_ids ) ) {
					break;
				}
			}//end if
		}//end foreach

		return $lesson_ids;
	}

	/**
	 * Resolve user ID from email, numeric ID, or fallback to trigger data / current user.
	 */
	private static function resolve_user_id( $user_input, array $input ): int {
		$user_id = 0;

		// 1. Explicit user input (email or ID)
		if ( ! empty( $user_input ) ) {
			if ( is_string( $user_input ) && strpos( $user_input, '@' ) !== false ) {
				$user = get_user_by( 'email', $user_input );
				if ( $user ) {
					return $user->ID;
				} else {
					throw new \Exception( sprintf( 'User with email "%s" not found.', $user_input ) );
				}
			} else {
				$user_id = (int) $user_input;
				if ( $user_id > 0 && get_userdata( $user_id ) ) {
					return $user_id;
				} else {
					throw new \Exception( sprintf( 'User with ID "%d" not found.', $user_id ) );
				}
			}
		}

		// 2. Try to extract from trigger input
		$possible_keys = [ 'user_id', 'user', 'author', 'submitted_by', 'user_ID', 'ID', 'user_email' ];
		foreach ( $possible_keys as $key ) {
			if ( isset( $input[ $key ] ) && ! empty( $input[ $key ] ) ) {
				$value = $input[ $key ];
				if ( is_string( $value ) && strpos( $value, '@' ) !== false ) {
					$user = get_user_by( 'email', $value );
					if ( $user ) {
						return $user->ID;
					}
				} elseif ( is_numeric( $value ) ) {
					$id = (int) $value;
					if ( $id > 0 && get_userdata( $id ) ) {
						return $id;
					}
				}
			}
		}
		// Also check nested 'data'
		if ( isset( $input['data'] ) && is_array( $input['data'] ) ) {
			foreach ( $possible_keys as $key ) {
				if ( isset( $input['data'][ $key ] ) && ! empty( $input['data'][ $key ] ) ) {
					$value = $input['data'][ $key ];
					if ( is_string( $value ) && strpos( $value, '@' ) !== false ) {
						$user = get_user_by( 'email', $value );
						if ( $user ) {
							return $user->ID;
						}
					} elseif ( is_numeric( $value ) ) {
						$id = (int) $value;
						if ( $id > 0 && get_userdata( $id ) ) {
							return $id;
						}
					}
				}
			}
		}

		// 3. Fallback to currently logged‑in user
		$current_user = get_current_user_id();
		if ( $current_user > 0 ) {
			return $current_user;
		}

		throw new \Exception( 'No user specified and no user ID found in trigger data or logged‑in user.' );
	}

	// -------------------------------------------------------------------------
	// ACTIONS – ACTION HANDLERS
	// -------------------------------------------------------------------------

	/**
	 * Enroll a user in the given courses.
	 */
	private static function enroll_course( array $course_ids, int $user_id ): string {
		if ( empty( $course_ids ) ) {
			throw new \Exception( 'No courses selected.' );
		}

		foreach ( $course_ids as $course_id ) {
			add_filter( 'is_course_purchasable', '__return_false', 10 );
			\Academy\Helper::do_enroll( $course_id, $user_id );
			remove_filter( 'is_course_purchasable', '__return_false', 10 );
		}

		return 'Course(s) enrolled successfully.';
	}

	/**
	 * Unenroll a user from the given courses.
	 */
	private static function unenroll_course( array $course_ids, int $user_id ): string {
		if ( empty( $course_ids ) ) {
			throw new \Exception( 'No courses selected.' );
		}

		foreach ( $course_ids as $course_id ) {
			\Academy\Helper::cancel_course_enroll( $course_id, $user_id );
		}

		return 'Course(s) unenrolled successfully.';
	}

	/**
	 * Mark a lesson as complete.
	 */
	private static function complete_lesson( int $course_id, int $lesson_id, int $user_id ): string {
		$topic_type = 'lesson';

		do_action( 'academy/frontend/before_mark_topic_complete', $topic_type, $course_id, $lesson_id, $user_id );

		$option_name = 'academy_course_' . $course_id . '_completed_topics';
		$saved = (array) json_decode( get_user_meta( $user_id, $option_name, true ), true );

		if ( isset( $saved[ $topic_type ][ $lesson_id ] ) ) {
			unset( $saved[ $topic_type ][ $lesson_id ] );
		} else {
			$saved[ $topic_type ][ $lesson_id ] = \Academy\Helper::get_time();
		}

		update_user_meta( $user_id, $option_name, wp_json_encode( $saved ) );
		do_action( 'academy/frontend/after_mark_topic_complete', $topic_type, $course_id, $lesson_id, $user_id );

		return 'Lesson marked as complete.';
	}

	/**
	 * Mark a course as completed.
	 */
	private static function complete_course( int $course_id, int $user_id ): string {
		global $wpdb;

		do_action( 'academy/admin/course_complete_before', $course_id );

		$date = gmdate( 'Y-m-d H:i:s', \Academy\Helper::get_time() );

		// Generate unique hash.
		do {
			$hash = substr( md5( wp_generate_password( 32 ) . $date . $course_id . $user_id ), 0, 16 );
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(comment_ID) FROM {$wpdb->comments}
                    WHERE comment_agent = 'academy' AND comment_type = 'course_completed' AND comment_content = %s",
					$hash
				)
			);
		} while ( $exists > 0 );

		$inserted = $wpdb->insert(
			$wpdb->comments,
			[
				'comment_post_ID'  => $course_id,
				'comment_author'   => $user_id,
				'comment_date'     => $date,
				'comment_date_gmt' => get_gmt_from_date( $date ),
				'comment_content'  => $hash,
				'comment_approved' => 'approved',
				'comment_agent'    => 'academy',
				'comment_type'     => 'course_completed',
				'user_id'          => $user_id,
			]
		);

		do_action( 'academy/admin/course_complete_after', $course_id, $user_id );

		if ( ! $inserted ) {
			throw new \Exception( 'Failed to complete the course.' );
		}

		return 'Course marked as completed.';
	}

	// -------------------------------------------------------------------------
	// ACTIONS – DEFINITIONS AND EXECUTION
	// -------------------------------------------------------------------------

	public static function get_actions(): array {
		return [
			'enroll-course'    => [
				'label'       => 'Enroll in Course',
				'description' => 'Enroll a user in one or more courses.',
			],
			'unenroll-course'  => [
				'label'       => 'Unenroll from Course',
				'description' => 'Unenroll a user from one or more courses.',
			],
			'complete-lesson'  => [
				'label'       => 'Complete Lesson',
				'description' => 'Mark a specific lesson as complete for the user.',
			],
			'complete-course'  => [
				'label'       => 'Complete Course',
				'description' => 'Mark an entire course as completed for the user.',
			],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$user_field = [
			'key'      => 'user_id',
			'label'    => 'User Email',
			'type'     => 'text',
			'help'     => 'Enter the email address of the user. If left empty, the system will try to use the user from the trigger data, otherwise the currently logged‑in user.',
			'required' => false,
		];

		$multi_course_field = [
			'key'      => 'selectedCourse',
			'label'    => 'Courses',
			'type'     => 'select',
			'multiple' => true,
			'dynamic'  => [
				'integration' => 'academy',
				'query'       => 'acourse_no_any',
				'select'      => [ 'name', 'label' ],
			],
			'required' => true,
		];

		$single_course_field = [
			'key'      => 'selectedCourse',
			'label'    => 'Course',
			'type'     => 'select',
			'multiple' => false,
			'dynamic'  => [
				'integration' => 'academy',
				'query'       => 'acourse_no_any',
				'select'      => [ 'name', 'label' ],
			],
			'required' => true,
		];

		$single_lesson_field = [
			'key'      => 'selectedLesson',
			'label'    => 'Lesson',
			'type'     => 'select',
			'multiple' => false,
			'dynamic'  => [
				'integration' => 'academy',
				'query'       => 'lesson_no_any',
				'select'      => [ 'name', 'label' ],
			],
			'required' => true,
		];

		$schemas = [
			'enroll-course'   => [
				$user_field,
				$multi_course_field,
				[
					'key'   => 'all_courses',
					'label' => 'Enroll in all courses',
					'type'  => 'checkbox',
					'help'  => 'If checked, all published courses will be enrolled, ignoring the selection above.',
				],
			],
			'unenroll-course' => [
				$user_field,
				$multi_course_field,
				[
					'key'   => 'all_courses',
					'label' => 'Unenroll from all courses',
					'type'  => 'checkbox',
					'help'  => 'If checked, all courses will be unenrolled, ignoring the selection above.',
				],
			],
			'complete-lesson' => [
				$user_field,
				$single_course_field,
				$single_lesson_field,
			],
			'complete-course' => [
				$user_field,
				$single_course_field,
			],

		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		// 1. Extract and validate action
		$action = self::extract_action_from_node( $node );
		$action = str_replace( '_', '-', $action );
		$allowed = self::get_allowed_actions();

		if ( empty( $action ) || ! in_array( $action, $allowed, true ) ) {
			throw new \Exception(
				sprintf(
					'Unable to determine a valid action. Extracted: "%s". Allowed: %s. Node: %s',
					$action,
					implode( ', ', $allowed ),
					wp_json_encode( $node )
				)
			);
		}

		// 2. Extract configuration
		$config = self::extract_config_from_node( $node );
		if ( empty( $config ) ) {
			throw new \Exception( 'No configuration found in node. Node: ' . wp_json_encode( $node ) );
		}

		// 3. Check Academy plugin
		if ( ! class_exists( 'Academy' ) ) {
			throw new \Exception( 'Academy LMS plugin is not active.' );
		}

		// 4. Resolve user ID
		$user_input = $config['user_id'] ?? '';
		$user_id = self::resolve_user_id( $user_input, $input );

		$message = '';

		// 5. Execute the action
		switch ( $action ) {
			case 'enroll-course':
			case 'unenroll-course':
				$course_ids = self::extract_course_ids( $config );
				if ( empty( $course_ids ) ) {
					throw new \Exception( 'No courses selected. Config: ' . wp_json_encode( $config ) );
				}
				if ( $action === 'enroll-course' ) {
					$message = self::enroll_course( $course_ids, $user_id );
				} else {
					$message = self::unenroll_course( $course_ids, $user_id );
				}
				break;

			case 'complete-lesson':
				$course_ids = self::extract_course_ids( $config );
				if ( empty( $course_ids ) ) {
					throw new \Exception( 'No course selected for lesson completion. Config: ' . wp_json_encode( $config ) );
				}
				$course_id = $course_ids[0];

				$lesson_ids = self::extract_lesson_ids( $config );
				if ( empty( $lesson_ids ) ) {
					throw new \Exception( 'No lesson selected. Config: ' . wp_json_encode( $config ) );
				}
				$lesson_id = $lesson_ids[0];

				$message = self::complete_lesson( $course_id, $lesson_id, $user_id );
				break;

			case 'complete-course':
				$course_ids = self::extract_course_ids( $config );
				if ( empty( $course_ids ) ) {
					throw new \Exception( 'No course selected for completion. Config: ' . wp_json_encode( $config ) );
				}
				$course_id = $course_ids[0];
				$message = self::complete_course( $course_id, $user_id );
				break;

			default:
				throw new \Exception( sprintf( 'Unhandled action: "%s".', $action ) );
		}//end switch

		return [
			'port' => 'main',
			'data' => [
				'message' => $message,
			],
		];
	}

	// -------------------------------------------------------------------------
	// DYNAMIC QUERIES
	// -------------------------------------------------------------------------

	public static function get_dynamic_queries(): array {
		return [
			'acourse'        => [ self::class, 'query_courses' ],
			'acourse_no_any' => [ self::class, 'query_courses_no_any' ],
			'quiz'           => [ self::class, 'query_quiz' ],
			'lesson'         => [ self::class, 'query_lesson' ],
			'lesson_no_any'  => [ self::class, 'query_lesson_no_any' ],
			'user'           => [ self::class, 'query_users' ],
		];
	}

	public static function query_courses(): array {
		$options = [
			[
				'label' => 'Any course',
				'name'  => 'any',
			],
		];

		if ( class_exists( 'Academy' ) ) {
			$courses = get_posts([
				'post_type'      => 'academy_courses',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			]);

			foreach ( $courses as $course ) {
				$options[] = [
					'label' => $course->post_title,
					'name'  => $course->ID,
				];
			}
		}

		return $options;
	}

	public static function query_courses_no_any(): array {
		$options = [];

		if ( class_exists( 'Academy' ) ) {
			$courses = get_posts([
				'post_type'      => 'academy_courses',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			]);

			foreach ( $courses as $course ) {
				$options[] = [
					'label' => $course->post_title,
					'name'  => $course->ID,
				];
			}
		}

		return $options;
	}

	public static function query_quiz(): array {
		$options = [
			[
				'label' => 'Any Quiz',
				'name'  => 'any',
			],
		];

		if ( class_exists( 'Academy' ) ) {
			$quizzes = get_posts([
				'post_type'      => 'academy_quiz',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			]);

			foreach ( $quizzes as $quiz ) {
				$options[] = [
					'label' => $quiz->post_title,
					'name'  => $quiz->ID,
				];
			}
		}

		return $options;
	}

	public static function query_lesson(): array {
		$options = [
			[
				'label' => 'Any lesson',
				'name'  => 'any',
			],
		];

		if ( class_exists( 'Academy' ) ) {
			$lessons = \Academy\Lesson\LessonApi\Lesson::get( 0, -1, 0, '', '', true );

			if ( ! empty( $lessons ) ) {
				foreach ( $lessons as $lesson ) {
					$lesson_data = (object) $lesson->get_data();
					$options[]   = [
						'label' => $lesson_data->lesson_title,
						'name'  => $lesson_data->ID,
					];
				}
			}
		}

		return $options;
	}

	public static function query_lesson_no_any(): array {
		$options = [];

		if ( class_exists( 'Academy' ) ) {
			$lessons = \Academy\Lesson\LessonApi\Lesson::get( 0, -1, 0, '', '', true );

			if ( ! empty( $lessons ) ) {
				foreach ( $lessons as $lesson ) {
					$lesson_data = (object) $lesson->get_data();
					$options[]   = [
						'label' => $lesson_data->lesson_title,
						'name'  => $lesson_data->ID,
					];
				}
			}
		}

		return $options;
	}

	public static function query_users(): array {
		$options = [];

		$users = get_users([
			'fields'  => [ 'ID', 'display_name', 'user_email' ],
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'number'  => 200,
		]);

		foreach ( $users as $user ) {
			$options[] = [
				'label' => $user->display_name . ' (' . $user->user_email . ')',
				'name'  => $user->ID,
			];
		}

		return $options;
	}
}
