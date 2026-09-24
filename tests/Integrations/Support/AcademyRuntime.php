<?php
namespace Zaplane\Tests\Integrations\Support {
	/** In-memory Academy storage, isolated to tests that explicitly load this file. */
	class AcademyState {
		public static $enrollments = [];
		public static $completions = [];
		public static $multi = [];
		public static $hooks = [];
		public static $current_user = 0;
		public static $fail = '';
		public static $enroll_calls = 0;
		public static function reset() {
			self::$enrollments = self::$completions = self::$multi = self::$hooks = [];
			self::$current_user = 0;
			self::$fail = '';
			self::$enroll_calls = 0;
		}
	}
	class AcademyDatabase extends \Zaplane\Tests\WPDBMock {
		public $posts = 'wp_posts';
		public $comments = 'wp_comments';
		public function get_row( string $query, $output = OBJECT, int $offset = 0 ) {
			if ( strpos( $query, 'academy_enrolled' ) !== false ) {
				preg_match( '/post_parent = (\d+) AND post_author = (\d+)/', $query, $match );
				return AcademyState::$enrollments[ $match[1] . ':' . $match[2] ] ?? null;
			}
			preg_match( '/comment_post_ID = (\d+) AND user_id = (\d+)/', $query, $match );
			return $match ? ( AcademyState::$completions[ $match[1] . ':' . $match[2] ] ?? null ) : null;
		}
		public function get_col( string $query ): array {
			preg_match( '/post_author = (\d+)/', $query, $match );
			$ids = [];
			foreach ( AcademyState::$enrollments as $key => $row ) {
				list( $course, $user ) = explode( ':', $key );
				if ( $user === $match[1] && 'completed' === $row->enrolled_status ) {
					$ids[] = $course;
				}
			}
			return $ids;
		}
	}
}
namespace Academy {
	use Zaplane\Tests\Integrations\Support\AcademyState as State;
	class Helper {
		public static function get_time() {
			return 1700000000;
		}
		public static function do_enroll( $course_id, $user_id ) {
			++State::$enroll_calls;
			if ( State::$current_user !== $user_id ) {
				throw new \RuntimeException( 'Missing target-user context.' );
			}
			if ( 'enroll' === State::$fail ) {
				throw new \RuntimeException( 'Simulated enrollment failure.' );
			}
			State::$enrollments[ "$course_id:$user_id" ] = (object) [ 'ID' => 99, 'enrolled_status' => 'completed' ];
			return 99;
		}
		public static function cancel_course_enroll( $course_id, $user_id ) {
			if ( 'cancel' !== State::$fail ) {
				unset( State::$enrollments[ "$course_id:$user_id" ] );
				\delete_user_meta( $user_id, 'academy_course_' . $course_id . '_completed_topics' );
			}
		}
	}
}
namespace Zaplane\Integrations {
	use Zaplane\Tests\Integrations\Support\AcademyState as State;
	function get_current_user_id() {
		return State::$current_user;
	}
	function wp_set_current_user( $id ) {
		State::$current_user = (int) $id;
	}
	function do_action( $hook, ...$args ) {
		State::$hooks[] = [ $hook, $args ];
	}
	function get_the_title( $id ) {
		$post = \get_post( $id );
		return $post ? $post->post_title : '';
	}
	function get_user_meta( $user_id, $key = '', $single = false ) {
		if ( in_array( $key, [ 'academy_course_wishlist', 'academy_instructor_course_id' ], true ) ) {
			$values = State::$multi[ "$user_id:$key" ] ?? [];
			return $single ? ( $values[0] ?? '' ) : $values;
		}
		return \get_user_meta( $user_id, $key, $single );
	}
	function add_user_meta( $user_id, $key, $value ) {
		State::$multi[ "$user_id:$key" ][] = $value;
		return 1;
	}
	function delete_user_meta( $user_id, $key, $value = '' ) {
		if ( in_array( $key, [ 'academy_course_wishlist', 'academy_instructor_course_id' ], true ) ) {
			State::$multi[ "$user_id:$key" ] = array_values( array_filter( State::$multi[ "$user_id:$key" ] ?? [], static function ( $item ) use ( $value ) { return (int) $item !== (int) $value; } ) );
			return true;
		}
		return \delete_user_meta( $user_id, $key, $value );
	}
	function update_user_meta( $user_id, $key, $value ) {
		return 'meta' === State::$fail ? false : \update_user_meta( $user_id, $key, $value );
	}
	function wp_generate_uuid4() {
		return '12345678-1234-4321-1234-123456789012';
	}
	function wp_insert_comment( $data ) {
		if ( 'comment' === State::$fail ) {
			return false;
		}
		$id = \wp_insert_comment( $data );
		State::$completions[ $data['comment_post_ID'] . ':' . $data['user_id'] ] = \get_comment( $id );
		return $id;
	}
}
