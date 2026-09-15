<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait AcademyActions {
	public static function get_actions(): array {
		$actions = [
			'enroll-course' => [ 'Enroll in Course', 'Enroll an existing user in selected courses without requiring checkout.' ],
			'unenroll-course' => [ 'Unenroll from Course', 'Remove enrollment and lesson progress using Academy’s cancellation API.' ],
			'complete-lesson' => [ 'Complete Lesson', 'Mark a curriculum lesson complete. Repeated runs leave it complete.' ],
			'incomplete-lesson' => [ 'Mark Lesson Incomplete', 'Remove completion for a curriculum lesson.' ],
			'complete-course' => [ 'Complete Course', 'Record course completion after all topics are complete, or explicitly override that requirement.' ],
			'get-course-progress' => [ 'Get Course Progress', 'Read enrollment, completion and curriculum progress for a user.' ],
			'add-to-wishlist' => [ 'Add Course to Wishlist', 'Add selected courses to the user’s Academy wishlist.' ],
			'remove-from-wishlist' => [ 'Remove Course from Wishlist', 'Remove selected courses from the user’s Academy wishlist.' ],
			'assign-instructor' => [ 'Assign Instructor to Course', 'Assign an existing approved Academy instructor to selected courses.' ],
			'remove-instructor' => [ 'Remove Instructor from Course', 'Remove an instructor assignment. The course author cannot be removed.' ],
		];
		$result = [];
		foreach ( $actions as $key => $action ) {
			$result[ $key ] = [ 'label' => $action[0], 'description' => $action[1] ];
		}
		return $result;
	}

	public static function get_action_config_schema( string $action ): array {
		if ( ! isset( self::get_actions()[ $action ] ) ) {
			return [];
		}
		$single = in_array( $action, [ 'complete-lesson', 'incomplete-lesson', 'complete-course', 'get-course-progress' ], true );
		$fields = [
			[
				'key' => 'user_id', 'label' => 'User ID or email', 'type' => 'text', 'required' => false,
				'help' => 'An existing user. When empty, use user_id or user_email from the trigger, then the current logged-in user.',
			],
			self::selection( 'selectedCourse', $single ? 'Course' : 'Courses', 'acourse_no_any', ! $single ),
		];
		if ( in_array( $action, [ 'enroll-course', 'unenroll-course' ], true ) ) {
			$fields[1]['required'] = false;
			$fields[] = [
				'key' => 'all_courses', 'label' => 'All courses', 'type' => 'checkbox',
				'help' => 'Enroll in all published courses, or cancel all active enrollments belonging to this user.',
			];
		}
		if ( in_array( $action, [ 'complete-lesson', 'incomplete-lesson' ], true ) ) {
			$fields[] = self::selection( 'selectedLesson', 'Lesson', 'lesson_no_any' );
		}
		if ( 'complete-course' === $action ) {
			$fields[] = [ 'key' => 'force_completion', 'label' => 'Allow completion with unfinished topics', 'type' => 'checkbox', 'help' => 'Records course completion without changing individual lesson or quiz results.' ];
		}
		return $fields;
	}

	private static function action_name( array $node ): string {
		foreach ( [ $node, $node['data'] ?? [], $node['flow_details'] ?? [], $node['config'] ?? [] ] as $scope ) {
			if ( ! is_array( $scope ) ) {
				continue;
			}
			foreach ( [ 'event', 'action', 'actionName', 'name', 'type' ] as $key ) {
				$value = $scope[ $key ] ?? '';
				if ( is_string( $value ) ) {
					$value = str_replace( '_', '-', $value );
					if ( isset( self::get_actions()[ $value ] ) ) {
						return $value;
					}
				}
			}
		}
		throw new \InvalidArgumentException( 'Unknown Academy action.' );
	}

	private static function resolve_user_id( $value, array $input ): int {
		if ( '' === $value || null === $value ) {
			foreach ( [ $input, $input['data'] ?? [] ] as $scope ) {
				foreach ( [ 'user_id', 'user_email' ] as $key ) {
					if ( ! empty( $scope[ $key ] ) ) {
						return self::resolve_user_id( $scope[ $key ], [] );
					}
				}
			}
			$value = get_current_user_id();
		}
		$user = null;
		if ( is_scalar( $value ) && ctype_digit( (string) $value ) && (int) $value > 0 ) {
			$user = get_userdata( (int) $value );
		} elseif ( is_string( $value ) && is_email( trim( $value ) ) ) {
			$user = get_user_by( 'email', trim( $value ) );
		}
		if ( ! $user ) {
			throw new \InvalidArgumentException( 'A valid existing user ID or email is required.' );
		}
		return (int) $user->ID;
	}

	private static function selected_ids( array $config, array $keys ): array {
		foreach ( $keys as $key ) {
			if ( ! isset( $config[ $key ] ) || '' === $config[ $key ] || [] === $config[ $key ] ) {
				continue;
			}
			$values = $config[ $key ];
			if ( is_object( $values ) ) {
				$values = (array) $values;
			}
			if ( is_array( $values ) && ( isset( $values['id'] ) || isset( $values['value'] ) || isset( $values['name'] ) || isset( $values['courseId'] ) || isset( $values['lessonId'] ) ) ) {
				$values = [ $values ];
			}
			$ids = [];
			foreach ( (array) $values as $value ) {
				if ( is_object( $value ) || is_array( $value ) ) {
					$value = (array) $value;
					$value = $value['courseId'] ?? $value['lessonId'] ?? $value['id'] ?? $value['value'] ?? $value['name'] ?? null;
				}
				if ( ! is_scalar( $value ) || ! ctype_digit( (string) $value ) || (int) $value <= 0 ) {
					throw new \InvalidArgumentException( 'Selections must contain positive numeric IDs.' );
				}
				$ids[] = (int) $value;
			}
			return array_values( array_unique( $ids ) );
		}
		return [];
	}

	/** Explicit-user lookup: Academy::is_enrolled returns false in cron workers. */
	private static function enrollment( int $course_id, int $user_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT ID, post_status AS enrolled_status FROM {$wpdb->posts} WHERE post_type = %s AND post_parent = %d AND post_author = %d ORDER BY ID DESC LIMIT 1",
			'academy_enrolled', $course_id, $user_id
		) );
	}

	private static function completion( int $course_id, int $user_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT comment_ID, comment_content, comment_date FROM {$wpdb->comments} WHERE comment_agent = %s AND comment_type = %s AND comment_post_ID = %d AND user_id = %d LIMIT 1",
			'academy', 'course_completed', $course_id, $user_id
		) );
	}

	private static function completed_topics( int $course_id, int $user_id ): array {
		$raw = get_user_meta( $user_id, 'academy_course_' . $course_id . '_completed_topics', true );
		if ( '' === $raw ) {
			return [];
		}
		$topics = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		if ( ! is_array( $topics ) ) {
			throw new \RuntimeException( 'Stored Academy progress is invalid; it was not overwritten.' );
		}
		return $topics;
	}

	/** Flatten sections and nested sub-curricula; count each actual topic once. */
	private static function curriculum_topics( int $course_id ): array {
		$walk = static function ( array $items ) use ( &$walk ): array {
			$result = [];
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				if ( ! empty( $item['topics'] ) && is_array( $item['topics'] ) ) {
					$result += $walk( $item['topics'] );
				}
				if ( ! empty( $item['id'] ) && ! empty( $item['type'] ) && 'sub-curriculum' !== $item['type'] ) {
					$result[ $item['type'] . ':' . $item['id'] ] = $item;
				}
			}
			return $result;
		};
		return $walk( (array) get_post_meta( $course_id, 'academy_course_curriculum', true ) );
	}

	private static function progress( int $course_id, int $user_id ): array {
		$enrollment = self::enrollment( $course_id, $user_id );
		$completion = self::completion( $course_id, $user_id );
		$topics = self::curriculum_topics( $course_id );
		$saved = self::completed_topics( $course_id, $user_id );
		$completed = 0;
		foreach ( $topics as $topic ) {
			if ( isset( $saved[ $topic['type'] ][ $topic['id'] ] ) ) {
				++$completed;
			}
		}
		return [
			'enrollment_id' => $enrollment ? (int) $enrollment->ID : 0,
			'enrollment_status' => $enrollment ? $enrollment->enrolled_status : '',
			'is_enrolled' => $enrollment && 'completed' === $enrollment->enrolled_status,
			'is_completed' => (bool) $completion,
			'completion_id' => $completion ? (int) $completion->comment_ID : 0,
			'completion_date' => $completion ? $completion->comment_date : '',
			'total_topics' => count( $topics ), 'completed_topics' => $completed,
			'progress_percentage' => count( $topics ) ? round( $completed / count( $topics ) * 100, 2 ) : 0,
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$action = self::action_name( $node );
		if ( ! class_exists( '\Academy\Helper' ) ) {
			throw new \RuntimeException( 'Academy LMS plugin is not active.' );
		}
		$config = self::config( $node );
		$user_id = self::resolve_user_id( $config['user_id'] ?? '', $input );
		$course_ids = self::selected_ids( $config, [ 'selectedCourse', 'selected_course', 'courses', 'course_id', 'courseIds' ] );
		$all = in_array( $action, [ 'enroll-course', 'unenroll-course' ], true ) && filter_var( $config['all_courses'] ?? false, FILTER_VALIDATE_BOOLEAN );
		if ( $all && 'enroll-course' === $action ) {
			$course_ids = array_map( 'intval', get_posts( [ 'post_type' => 'academy_courses', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1 ] ) );
		} elseif ( $all && 'unenroll-course' === $action ) {
			global $wpdb;
			$course_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT post_parent FROM {$wpdb->posts} WHERE post_type = %s AND post_author = %d AND post_status = %s",
				'academy_enrolled', $user_id, 'completed'
			) ) );
		}
		if ( ! $course_ids && ! $all ) {
			throw new \InvalidArgumentException( 'Select at least one course.' );
		}
		$single = in_array( $action, [ 'complete-lesson', 'incomplete-lesson', 'complete-course', 'get-course-progress' ], true );
		if ( $single && 1 !== count( $course_ids ) ) {
			throw new \InvalidArgumentException( 'This action requires exactly one course.' );
		}
		// Validate the whole selection before changing any courses.
		foreach ( $course_ids as $course_id ) {
			$post = get_post( $course_id );
			if ( ! $post || 'academy_courses' !== $post->post_type || in_array( $post->post_status, [ 'trash', 'auto-draft' ], true ) ) {
				throw new \InvalidArgumentException( 'Selected course does not exist or is unavailable: ' . $course_id );
			}
			if ( 'enroll-course' === $action && ! in_array( $post->post_status, [ 'publish', 'private' ], true ) ) {
				throw new \InvalidArgumentException( 'Enrollment requires a published or private course.' );
			}
			if ( 'remove-instructor' === $action && (int) $post->post_author === $user_id ) {
				throw new \InvalidArgumentException( 'The course author cannot be removed from instructor assignments.' );
			}
		}
		if ( 'assign-instructor' === $action && 'approved' !== get_user_meta( $user_id, 'academy_instructor_status', true ) ) {
			throw new \InvalidArgumentException( 'The user must be an approved Academy instructor.' );
		}
		$lesson_id = 0;
		if ( in_array( $action, [ 'complete-lesson', 'incomplete-lesson' ], true ) ) {
			$lessons = self::selected_ids( $config, [ 'selectedLesson', 'selected_lesson', 'lesson_id', 'lessonIds' ] );
			if ( 1 !== count( $lessons ) || ! isset( self::curriculum_topics( $course_ids[0] )[ 'lesson:' . $lessons[0] ] ) ) {
				throw new \InvalidArgumentException( 'Select exactly one lesson belonging to this course curriculum.' );
			}
			$lesson_id = $lessons[0];
		}
		$results = [];
		$changed = false;
		foreach ( $course_ids as $course_id ) {
			$result = self::run_course_action( $action, $course_id, $user_id, $lesson_id, $config );
			$changed = $changed || $result['changed'];
			$results[] = array_merge( self::course_data( $course_id ), $result );
		}
		$data = array_merge( [
			'success' => true, 'action' => $action, 'message' => $changed ? 'Academy action completed.' : 'Academy action completed; no changes were needed.',
			'changed' => $changed, 'course_ids' => $course_ids, 'results' => $results,
		], self::user_data( $user_id ) );
		if ( $single ) {
			$data = array_merge( $data, self::course_data( $course_ids[0] ) );
		}
		if ( $lesson_id ) {
			$data['lesson_id'] = $lesson_id;
		}
		if ( 'get-course-progress' === $action ) {
			$data = array_merge( $data, self::progress( $course_ids[0], $user_id ) );
			$data['message'] = 'Academy course progress retrieved.';
		}
		return [ 'port' => 'main', 'data' => $data ];
	}

	private static function run_course_action( string $action, int $course_id, int $user_id, int $lesson_id, array $config ): array {
		if ( in_array( $action, [ 'enroll-course', 'unenroll-course' ], true ) ) {
			$enrollment = self::enrollment( $course_id, $user_id );
			$active = $enrollment && 'completed' === $enrollment->enrolled_status;
			if ( ( 'enroll-course' === $action && $active ) || ( 'unenroll-course' === $action && ! $enrollment ) ) {
				return [ 'changed' => false ];
			}
			if ( $enrollment && ! $active && 'cancel' !== $enrollment->enrolled_status ) {
				throw new \RuntimeException( 'Enrollment is pending or managed by an order. Resolve its status before this action.' );
			}
			if ( 'unenroll-course' === $action && ! $active ) {
				return [ 'changed' => false ];
			}
			// Academy helpers require a logged-in context even with an explicit ID.
			// Restore it on success, failures and exceptions; never set auth cookies.
			$previous_user = get_current_user_id();
			try {
				wp_set_current_user( $user_id );
				if ( 'enroll-course' === $action ) {
					$id = \Academy\Helper::do_enroll( $course_id, $user_id );
					if ( ! $id || is_wp_error( $id ) ) {
						throw new \RuntimeException( 'Academy could not enroll the user.' );
					}
				} else {
					\Academy\Helper::cancel_course_enroll( $course_id, $user_id );
				}
			} finally {
				wp_set_current_user( $previous_user );
			}
			$after = self::enrollment( $course_id, $user_id );
			if ( ( 'enroll-course' === $action && ( ! $after || 'completed' !== $after->enrolled_status ) ) || ( 'unenroll-course' === $action && $after ) ) {
				throw new \RuntimeException( 'Academy enrollment change could not be verified.' );
			}
			return [ 'changed' => true ];
		}
		if ( 'get-course-progress' === $action ) {
			return [ 'changed' => false ];
		}
		if ( in_array( $action, [ 'complete-lesson', 'incomplete-lesson', 'complete-course' ], true ) ) {
			$enrollment = self::enrollment( $course_id, $user_id );
			if ( ! $enrollment || 'completed' !== $enrollment->enrolled_status ) {
				throw new \RuntimeException( 'An active course enrollment is required to change completion.' );
			}
			if ( 'complete-course' === $action ) {
				return [ 'changed' => self::mark_course_complete( $course_id, $user_id, filter_var( $config['force_completion'] ?? false, FILTER_VALIDATE_BOOLEAN ) ) ];
			}
			$saved = self::completed_topics( $course_id, $user_id );
			$complete = 'complete-lesson' === $action;
			if ( isset( $saved['lesson'][ $lesson_id ] ) === $complete ) {
				return [ 'changed' => false ];
			}
			if ( $complete ) {
				do_action( 'academy/frontend/before_mark_topic_complete', 'lesson', $course_id, $lesson_id, $user_id );
				$saved['lesson'][ $lesson_id ] = \Academy\Helper::get_time();
			} else {
				unset( $saved['lesson'][ $lesson_id ] );
			}
			if ( ! update_user_meta( $user_id, 'academy_course_' . $course_id . '_completed_topics', wp_json_encode( $saved ) ) ) {
				throw new \RuntimeException( 'Failed to save lesson completion.' );
			}
			$hook = $complete ? 'academy/frontend/after_mark_topic_complete' : 'academy/frontend/mark_topic_incomplete';
			do_action( $hook, 'lesson', $course_id, $lesson_id, $user_id );
			return [ 'changed' => true ];
		}
		$key = in_array( $action, [ 'assign-instructor', 'remove-instructor' ], true ) ? 'academy_instructor_course_id' : 'academy_course_wishlist';
		$add = in_array( $action, [ 'assign-instructor', 'add-to-wishlist' ], true );
		$exists = in_array( $course_id, array_map( 'intval', get_user_meta( $user_id, $key, false ) ), true );
		if ( $exists === $add ) {
			return [ 'changed' => false ];
		}
		$saved = $add ? add_user_meta( $user_id, $key, $course_id ) : delete_user_meta( $user_id, $key, $course_id );
		if ( ! $saved ) {
			throw new \RuntimeException( 'Failed to save Academy user course metadata.' );
		}
		return [ 'changed' => true ];
	}

	private static function mark_course_complete( int $course_id, int $user_id, bool $force ): bool {
		if ( self::completion( $course_id, $user_id ) ) {
			return false;
		}
		$progress = self::progress( $course_id, $user_id );
		if ( ! $force && $progress['completed_topics'] < $progress['total_topics'] ) {
			throw new \RuntimeException( 'The course has unfinished topics. Complete them or enable the explicit completion override.' );
		}
		do_action( 'academy/admin/course_complete_before', $course_id );
		$date = gmdate( 'Y-m-d H:i:s', \Academy\Helper::get_time() );
		$id = wp_insert_comment( [
			'comment_post_ID' => $course_id, 'comment_author' => $user_id,
			'comment_date' => $date, 'comment_date_gmt' => get_gmt_from_date( $date ),
			'comment_content' => substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 16 ),
			'comment_approved' => 'approved', 'comment_agent' => 'academy',
			'comment_type' => 'course_completed', 'user_id' => $user_id,
		] );
		if ( ! $id || is_wp_error( $id ) ) {
			throw new \RuntimeException( 'Failed to save Academy course completion.' );
		}
		do_action( 'academy/admin/course_complete_after', $course_id, $user_id );
		return true;
	}

	public static function get_action_sample_output( string $action ): array {
		if ( ! isset( self::get_actions()[ $action ] ) ) {
			return [];
		}
		$data = [
			'success' => true, 'action' => $action, 'message' => 'Academy action completed.',
			'changed' => true, 'course_ids' => [ 1 ],
			'results' => [ [ 'course_id' => 1, 'course_title' => 'Sample Course', 'course_url' => 'https://example.com/course/sample-course', 'changed' => true ] ],
			'user_id' => 1, 'user_email' => 'student@example.com', 'first_name' => 'Jane', 'last_name' => 'Smith', 'username' => 'janesmith',
		];
		if ( in_array( $action, [ 'complete-lesson', 'incomplete-lesson', 'complete-course', 'get-course-progress' ], true ) ) {
			$data = array_merge( $data, [ 'course_id' => 1, 'course_title' => 'Sample Course', 'course_url' => 'https://example.com/course/sample-course' ] );
		}
		if ( in_array( $action, [ 'complete-lesson', 'incomplete-lesson' ], true ) ) {
			$data['lesson_id'] = 1;
		}
		if ( 'get-course-progress' === $action ) {
			$data = array_merge( $data, [
				'enrollment_id' => 1, 'enrollment_status' => 'completed', 'is_enrolled' => true,
				'is_completed' => false, 'completion_id' => 0, 'completion_date' => '',
				'total_topics' => 10, 'completed_topics' => 5, 'progress_percentage' => 50.0,
			] );
			$data['changed'] = false;
			$data['results'][0]['changed'] = false;
		}
		return $data;
	}
}
