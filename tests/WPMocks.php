<?php

namespace Zaplane\Tests {
	class WPMocks {

		private static array $options    = [];
		private static array $transients = [];
		private static array $posts      = [];
		private static array $users      = [];
		private static array $comments   = [];
		private static array $terms      = [];
		private static array $taxonomies = [];
		private static array $postTypes  = [];
		private static array $roles      = [];
		private static int   $lastInsertId  = 100;
		private static array $httpResponses = [];

		public static function reset(): void {
			self::$options      = [];
			self::$transients   = [];
			self::$posts        = [];
			self::$users        = [];
			self::$comments     = [];
			self::$terms        = [];
			self::$taxonomies   = [];
			self::$postTypes    = [];
			self::$roles        = [];
			self::$lastInsertId = 100;
			self::$httpResponses = [];
		}

		public static function setHttpResponse( array $body, int $status = 200 ): void {
			self::$httpResponses[] = [ 'body' => wp_json_encode( $body ), 'status' => $status ];
		}

		public static function nextHttpResponse(): ?array {
			return array_shift( self::$httpResponses );
		}

		// ── Posts ────────────────────────────────────────────────────────────────

		public static function setPost( int $id, array $data ): void {
			self::$posts[ $id ] = array_merge( self::getDefaultPost( $id ), $data );
		}

		public static function getPost( int $id ): ?object {
			if ( ! isset( self::$posts[ $id ] ) ) {
				return null;
			}
			return (object) self::$posts[ $id ];
		}

		public static function insertPost( array $data ): int {
			$id = ++self::$lastInsertId;
			self::$posts[ $id ] = array_merge( self::getDefaultPost( $id ), $data, [ 'ID' => $id ] );
			return $id;
		}

		public static function updatePost( array $data ): int {
			$id = $data['ID'] ?? 0;
			if ( $id && isset( self::$posts[ $id ] ) ) {
				self::$posts[ $id ] = array_merge( self::$posts[ $id ], $data );
				return $id;
			}
			return 0;
		}

		public static function deletePost( int $id ): bool {
			if ( isset( self::$posts[ $id ] ) ) {
				unset( self::$posts[ $id ] );
				return true;
			}
			return false;
		}

		private static function getDefaultPost( int $id ): array {
			return [
				'ID'                    => $id,
				'post_author'           => 1,
				'post_date'             => '2024-01-01 00:00:00',
				'post_date_gmt'         => '2024-01-01 00:00:00',
				'post_content'          => 'Test content',
				'post_title'            => 'Test Post ' . $id,
				'post_excerpt'          => '',
				'post_status'           => 'publish',
				'comment_status'        => 'open',
				'ping_status'           => 'open',
				'post_password'         => '',
				'post_name'             => 'test-post-' . $id,
				'to_ping'               => '',
				'pinged'                => '',
				'post_modified'         => '2024-01-01 00:00:00',
				'post_modified_gmt'     => '2024-01-01 00:00:00',
				'post_content_filtered' => '',
				'post_parent'           => 0,
				'guid'                  => 'http://example.com/?p=' . $id,
				'menu_order'            => 0,
				'post_type'             => 'post',
				'post_mime_type'        => '',
				'comment_count'         => 0,
			];
		}

		// ── Users ────────────────────────────────────────────────────────────────

		public static function setUser( int $id, array $data ): void {
			self::$users[ $id ] = array_merge( self::getDefaultUser( $id ), $data );
		}

		public static function getUser( int $id ): ?object {
			if ( ! isset( self::$users[ $id ] ) ) {
				return null;
			}
			return (object) self::$users[ $id ];
		}

		public static function insertUser( array $data ): int {
			$id = ++self::$lastInsertId;
			self::$users[ $id ] = array_merge( self::getDefaultUser( $id ), $data, [ 'ID' => $id ] );
			return $id;
		}

		private static function getDefaultUser( int $id ): array {
			return [
				'ID'                  => $id,
				'user_login'          => 'user' . $id,
				'user_pass'           => '',
				'user_nicename'       => 'user' . $id,
				'user_email'          => 'user' . $id . '@example.com',
				'user_url'            => '',
				'user_registered'     => '2024-01-01 00:00:00',
				'user_activation_key' => '',
				'user_status'         => 0,
				'display_name'        => 'User ' . $id,
			];
		}

		// ── Comments ─────────────────────────────────────────────────────────────

		public static function setComment( int $id, array $data ): void {
			self::$comments[ $id ] = array_merge( self::getDefaultComment( $id ), $data );
		}

		public static function getComment( int $id ): ?object {
			if ( ! isset( self::$comments[ $id ] ) ) {
				return null;
			}
			return (object) self::$comments[ $id ];
		}

		public static function insertComment( array $data ): int {
			$id = ++self::$lastInsertId;
			self::$comments[ $id ] = array_merge( self::getDefaultComment( $id ), $data, [ 'comment_ID' => $id ] );
			return $id;
		}

		private static function getDefaultComment( int $id ): array {
			return [
				'comment_ID'           => $id,
				'comment_post_ID'      => 1,
				'comment_author'       => 'Test Author',
				'comment_author_email' => 'author@example.com',
				'comment_author_url'   => '',
				'comment_author_IP'    => '127.0.0.1',
				'comment_date'         => '2024-01-01 00:00:00',
				'comment_date_gmt'     => '2024-01-01 00:00:00',
				'comment_content'      => 'Test comment ' . $id,
				'comment_karma'        => 0,
				'comment_approved'     => '1',
				'comment_agent'        => '',
				'comment_type'         => 'comment',
				'comment_parent'       => 0,
				'user_id'              => 0,
			];
		}

		// ── Options ───────────────────────────────────────────────────────────────

		public static function setOption( string $key, $value ): void {
			self::$options[ $key ] = $value;
		}

		public static function getOption( string $key, $default = false ) {
			return self::$options[ $key ] ?? $default;
		}

		public static function deleteOption( string $key ): bool {
			if ( array_key_exists( $key, self::$options ) ) {
				unset( self::$options[ $key ] );
				return true;
			}
			return false;
		}

		// ── Transients ────────────────────────────────────────────────────────────

		public static function setTransient( string $key, $value ): void {
			self::$transients[ $key ] = $value;
		}

		public static function getTransient( string $key ) {
			return self::$transients[ $key ] ?? false;
		}

		public static function deleteTransient( string $key ): void {
			unset( self::$transients[ $key ] );
		}

		// ── Terms ─────────────────────────────────────────────────────────────────

		public static function setTerm( int $id, array $data ): void {
			self::$terms[ $id ] = array_merge( self::getDefaultTerm( $id ), $data );
		}

		public static function getTerm( int $id ): ?object {
			return isset( self::$terms[ $id ] ) ? (object) self::$terms[ $id ] : null;
		}

		public static function insertTerm( string $name, string $taxonomy, array $args = [] ): array {
			$id = ++self::$lastInsertId;
			self::$terms[ $id ] = array_merge(
				self::getDefaultTerm( $id ),
				[ 'name' => $name, 'taxonomy' => $taxonomy ],
				$args,
				[ 'term_id' => $id ]
			);
			return [ 'term_id' => $id, 'term_taxonomy_id' => $id ];
		}

		public static function getTermsByTaxonomy( string $taxonomy ): array {
			return array_values(
				array_filter( self::$terms, fn( $t ) => ( $t['taxonomy'] ?? '' ) === $taxonomy )
			);
		}

		public static function deleteTerm( int $id ): bool {
			if ( isset( self::$terms[ $id ] ) ) {
				unset( self::$terms[ $id ] );
				return true;
			}
			return false;
		}

		private static function getDefaultTerm( int $id ): array {
			return [
				'term_id'          => $id,
				'name'             => 'Test Term ' . $id,
				'slug'             => 'test-term-' . $id,
				'term_group'       => 0,
				'term_taxonomy_id' => $id,
				'taxonomy'         => 'category',
				'description'      => '',
				'parent'           => 0,
				'count'            => 0,
			];
		}

		// ── Taxonomies ────────────────────────────────────────────────────────────

		public static function registerTaxonomy( string $taxonomy, $object_type, array $args = [] ): void {
			self::$taxonomies[ $taxonomy ] = array_merge(
				[ 'label' => ucfirst( $taxonomy ), 'object_type' => (array) $object_type ],
				$args
			);
		}

		public static function getTaxonomy( string $taxonomy ): ?object {
			if ( ! isset( self::$taxonomies[ $taxonomy ] ) ) {
				return null;
			}
			return (object) self::$taxonomies[ $taxonomy ];
		}

		public static function getAllTaxonomies(): array {
			return self::$taxonomies;
		}

		public static function getObjectTaxonomies( string $object_type ): array {
			$result = [];
			foreach ( self::$taxonomies as $slug => $tax ) {
				$types = (array) ( $tax['object_type'] ?? [] );
				if ( in_array( $object_type, $types, true ) ) {
					$result[] = $slug;
				}
			}
			return $result ?: [ 'category', 'post_tag' ]; // sensible default
		}

		// ── Post Types ────────────────────────────────────────────────────────────

		public static function registerPostType( string $post_type, array $args = [] ): void {
			self::$postTypes[ $post_type ] = array_merge(
				[ 'label' => ucfirst( str_replace( '_', ' ', $post_type ) ), 'supports' => [] ],
				$args,
				[ 'name' => $post_type ]
			);
		}

		public static function getPostType( string $post_type ): ?object {
			if ( ! isset( self::$postTypes[ $post_type ] ) ) {
				return null;
			}
			return (object) self::$postTypes[ $post_type ];
		}

		// ── Roles ─────────────────────────────────────────────────────────────────

		public static function addRole( string $role, string $display_name, array $capabilities = [] ): void {
			self::$roles[ $role ] = [ 'name' => $display_name, 'capabilities' => $capabilities ];
		}

		public static function removeRole( string $role ): void {
			unset( self::$roles[ $role ] );
		}

		public static function getCustomRoles(): array {
			return self::$roles;
		}
	}
}

namespace {
	use Zaplane\Tests\WPMocks;

	$mock_dir = __DIR__ . '/mocks/';

	foreach (glob($mock_dir . '*.php') as $file) {
		require_once $file;
	}

	// ── Options ───────────────────────────────────────────────────────────────

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( string $key, $default = false ) {
			return WPMocks::getOption( $key, $default );
		}
	}

	if ( ! function_exists( 'update_option' ) ) {
		function update_option( string $key, $value, $autoload = null ): bool {
			WPMocks::setOption( $key, $value );
			return true;
		}
	}

	if ( ! function_exists( 'add_option' ) ) {
		function add_option( string $key, $value = '', $deprecated = '', $autoload = 'yes' ): bool {
			WPMocks::setOption( $key, $value );
			return true;
		}
	}

	if ( ! function_exists( 'delete_option' ) ) {
		function delete_option( string $key ): bool {
			return WPMocks::deleteOption( $key );
		}
	}

	// ── Transients ────────────────────────────────────────────────────────────

	if ( ! function_exists( 'get_transient' ) ) {
		function get_transient( string $key ) {
			return WPMocks::getTransient( $key );
		}
	}

	if ( ! function_exists( 'set_transient' ) ) {
		function set_transient( string $key, $value, int $expiration = 0 ): bool {
			WPMocks::setTransient( $key, $value );
			return true;
		}
	}

	if ( ! function_exists( 'delete_transient' ) ) {
		function delete_transient( string $key ): bool {
			WPMocks::deleteTransient( $key );
			return true;
		}
	}

	// ── Encoding / escaping ───────────────────────────────────────────────────

	if ( ! function_exists( 'wp_json_encode' ) ) {
		function wp_json_encode( $data, int $options = 0, int $depth = 512 ) {
			return json_encode( $data, $options, $depth );
		}
	}

	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( string $str ): string {
			return trim( strip_tags( $str ) );
		}
	}

	if ( ! function_exists( 'sanitize_email' ) ) {
		function sanitize_email( $email ): string {
			$email = trim( (string) $email );
			return filter_var( $email, FILTER_SANITIZE_EMAIL ) ?: '';
		}
	}

	if ( ! function_exists( 'is_email' ) ) {
		function is_email( $email ) {
			return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false ? $email : false;
		}
	}

	if ( ! function_exists( 'sanitize_title' ) ) {
		function sanitize_title( $title, $fallback_title = '', $context = 'save' ) {
			return strtolower( preg_replace( '/[^a-z0-9-]/', '-', strtolower( $title ) ) );
		}
	}

	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( $key ) {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
		}
	}

	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( $text ) {
			return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( $text ) {
			return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'wp_strip_all_tags' ) ) {
		function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string {
			$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
			$text = strip_tags( $text );
			if ( $remove_breaks ) {
				$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
			}
			return trim( $text );
		}
	}

	// ── Date / time ───────────────────────────────────────────────────────────

	if ( ! function_exists( 'current_time' ) ) {
		function current_time( string $type, bool $gmt = false ): string {
			if ( $type === 'mysql' ) {
				return $gmt ? gmdate( 'Y-m-d H:i:s' ) : date( 'Y-m-d H:i:s' );
			}
			return (string) time();
		}
	}

	if ( ! function_exists( 'wp_timezone_string' ) ) {
		function wp_timezone_string(): string {
			return 'UTC';
		}
	}

	if ( ! function_exists( 'get_gmt_from_date' ) ) {
		function get_gmt_from_date( string $string, string $format = 'Y-m-d H:i:s' ): string {
			// In tests, treat local and GMT as identical (UTC timezone assumed).
			$ts = strtotime( $string );
			return $ts ? date( $format, $ts ) : '0000-00-00 00:00:00';
		}
	}

	// ── Hooks ─────────────────────────────────────────────────────────────────

	if ( ! function_exists( 'add_action' ) ) {
		function add_action( string $tag, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
			return true;
		}
	}

	if ( ! function_exists( 'remove_action' ) ) {
		function remove_action( string $tag, $callback, int $priority = 10 ): bool {
			return true;
		}
	}

	if ( ! function_exists( 'do_action' ) ) {
		function do_action( string $tag, ...$args ): void {
		}
	}

	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( string $tag, $value, ...$args ) {
			return $value;
		}
	}

	if ( ! function_exists( 'current_filter' ) ) {
		function current_filter(): string {
			return '';
		}
	}

	// ── WP_Error / is_wp_error ────────────────────────────────────────────────

	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $thing ): bool {
			return $thing instanceof \WP_Error;
		}
	}

	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			protected string $code;
			protected string $message;
			protected array  $data;

			public function __construct( string $code = '', string $message = '', $data = [] ) {
				$this->code    = $code;
				$this->message = $message;
				$this->data    = is_array( $data ) ? $data : [ 'data' => $data ];
			}

			public function get_error_code(): string    { return $this->code; }
			public function get_error_message(): string { return $this->message; }
			public function get_error_data( $code = '' ): array { return $this->data; }
		}
	}

	// ── WP_User ───────────────────────────────────────────────────────────────

	if ( ! class_exists( 'WP_User' ) ) {
		class WP_User {
			public int    $ID    = 0;
			public array  $roles = [];
			public array  $caps  = [];
			public array  $allcaps = [];

			public function __construct( int $id = 0 ) {
				if ( $id ) {
					$this->ID = $id;
					$data = WPMocks::getUser( $id );
					if ( $data ) {
						foreach ( get_object_vars( $data ) as $k => $v ) {
							$this->$k = $v;
						}
					}
				}
				if ( empty( $this->roles ) ) {
					$this->roles = [ 'subscriber' ];
				}
			}

			public function add_role( string $role ): void {
				if ( ! in_array( $role, $this->roles, true ) ) {
					$this->roles[] = $role;
				}
				$this->caps[ $role ] = true;
			}

			public function remove_role( string $role ): void {
				$this->roles = array_values( array_diff( $this->roles, [ $role ] ) );
				unset( $this->caps[ $role ] );
			}

			public function set_role( string $role ): void {
				$this->roles = $role ? [ $role ] : [];
				$this->caps  = $role ? [ $role => true ] : [];
			}

			public function add_cap( string $cap, bool $grant = true ): void {
				$this->caps[ $cap ]    = $grant;
				$this->allcaps[ $cap ] = $grant;
			}

			public function remove_cap( string $cap ): void {
				unset( $this->caps[ $cap ], $this->allcaps[ $cap ] );
			}

			public function has_cap( string $cap ): bool {
				return ! empty( $this->allcaps[ $cap ] ) || ! empty( $this->caps[ $cap ] );
			}

			public function exists(): bool {
				return $this->ID > 0;
			}
		}
	}

	// ── REST ──────────────────────────────────────────────────────────────────

	if ( ! function_exists( 'rest_url' ) ) {
		function rest_url( string $path = '' ): string {
			return 'https://example.com/wp-json/' . ltrim( $path, '/' );
		}
	}

	if ( ! function_exists( 'rest_ensure_response' ) ) {
		function rest_ensure_response( $response ) {
			if ( $response instanceof \WP_REST_Response ) {
				return $response;
			}
			return new \WP_REST_Response( $response );
		}
	}

	if ( ! class_exists( 'WP_REST_Response' ) ) {
		class WP_REST_Response {
			protected $data;
			protected int $status;

			public function __construct( $data = null, int $status = 200 ) {
				$this->data   = $data;
				$this->status = $status;
			}

			public function get_data()   { return $this->data; }
			public function get_status(): int { return $this->status; }
		}
	}

	if ( ! class_exists( 'WP_REST_Controller' ) ) {
		abstract class WP_REST_Controller {
			protected string $namespace  = '';
			protected string $rest_base  = '';
			public function register_routes() {}
		}
	}

	if ( ! class_exists( 'WP_REST_Server' ) ) {
		class WP_REST_Server {
			public const READABLE  = 'GET';
			public const CREATABLE = 'POST';
			public const EDITABLE  = 'POST, PUT, PATCH';
			public const DELETABLE = 'DELETE';
		}
	}

	if ( ! function_exists( 'register_rest_route' ) ) {
		function register_rest_route( string $namespace, string $route, array $args = [] ): bool {
			return true;
		}
	}

	// ── HTTP ──────────────────────────────────────────────────────────────────

	if ( ! function_exists( 'wp_remote_request' ) ) {
		function wp_remote_request( string $url, array $args = [] ) {
			$next = WPMocks::nextHttpResponse();
			if ( $next === null ) {
				return new \WP_Error( 'http_request_failed', 'Mock: no HTTP response queued' );
			}
			return [ 'response' => [ 'code' => $next['status'], 'message' => 'OK' ], 'body' => $next['body'] ];
		}
	}

	if ( ! function_exists( 'wp_remote_post' ) ) {
		function wp_remote_post( string $url, array $args = [] ) {
			$next = WPMocks::nextHttpResponse();
			if ( $next === null ) {
				return new \WP_Error( 'http_request_failed', 'Mock: no HTTP response queued' );
			}
			return [ 'response' => [ 'code' => $next['status'], 'message' => 'OK' ], 'body' => $next['body'] ];
		}
	}

	if ( ! function_exists( 'wp_remote_get' ) ) {
		function wp_remote_get( string $url, array $args = [] ) {
			$next = WPMocks::nextHttpResponse();
			if ( $next === null ) {
				return new \WP_Error( 'http_request_failed', 'Mock: no HTTP response queued' );
			}
			return [ 'response' => [ 'code' => $next['status'], 'message' => 'OK' ], 'body' => $next['body'] ];
		}
	}

	if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
		function wp_remote_retrieve_body( $response ): string {
			return is_array( $response ) ? ( $response['body'] ?? '' ) : '';
		}
	}

	if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
		function wp_remote_retrieve_response_code( $response ) {
			return is_array( $response ) ? ( $response['response']['code'] ?? 200 ) : 0;
		}
	}

	// ── Posts ─────────────────────────────────────────────────────────────────

	if ( ! class_exists( 'WP_Query' ) ) {
		class WP_Query {
			public array  $posts      = [];
			public int    $post_count = 0;
			public int    $found_posts = 0;
			public bool   $have_posts_called = false;

			public function __construct( array $args = [] ) {
				$this->posts      = [];
				$this->post_count = 0;
			}

			public function have_posts(): bool {
				return false;
			}

			public function the_post(): void {}

			public function get_posts(): array {
				return [];
			}
		}
	}

	if ( ! function_exists( 'get_posts' ) ) {
		function get_posts( $args = [] ): array {
			return [];
		}
	}

	if ( ! function_exists( 'get_post' ) ) {
		function get_post( $post = null, $output = OBJECT, $filter = 'raw' ) {
			$id = is_object( $post ) ? $post->ID : (int) $post;
			return WPMocks::getPost( $id );
		}
	}

	if ( ! function_exists( 'wp_insert_post' ) ) {
		function wp_insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
			return WPMocks::insertPost( $postarr );
		}
	}

	if ( ! function_exists( 'wp_update_post' ) ) {
		function wp_update_post( $postarr = [], $wp_error = false, $fire_after_hooks = true ) {
			return WPMocks::updatePost( $postarr );
		}
	}

	if ( ! function_exists( 'wp_delete_post' ) ) {
		function wp_delete_post( $postid = 0, $force_delete = false ) {
			$post = WPMocks::getPost( $postid );
			WPMocks::deletePost( $postid );
			return $post;
		}
	}

	if ( ! function_exists( 'wp_trash_post' ) ) {
		function wp_trash_post( $post_id = 0 ) {
			$post = WPMocks::getPost( $post_id );
			if ( $post ) {
				WPMocks::setPost( $post_id, [ 'post_status' => 'trash' ] );
				return WPMocks::getPost( $post_id );
			}
			return false;
		}
	}

	if ( ! function_exists( 'wp_untrash_post' ) ) {
		function wp_untrash_post( $post_id = 0 ) {
			$post = WPMocks::getPost( $post_id );
			if ( $post ) {
				WPMocks::setPost( $post_id, [ 'post_status' => 'publish' ] );
				return WPMocks::getPost( $post_id );
			}
			return false;
		}
	}

	if ( ! function_exists( 'get_post_type' ) ) {
		function get_post_type( $post = null ) {
			$post = get_post( $post );
			return $post ? $post->post_type : false;
		}
	}

	if ( ! function_exists( 'get_post_status' ) ) {
		function get_post_status( $post = null ) {
			$post = get_post( $post );
			return $post ? $post->post_status : false;
		}
	}

	if ( ! function_exists( 'get_post_types' ) ) {
		function get_post_types( $args = [], $output = 'names', $operator = 'and' ) {
			$make = function ( string $name, string $label, bool $public ) {
				return (object) [
					'name'             => $name,
					'label'            => $label,
					'description'      => '',
					'hierarchical'     => false,
					'rest_base'        => $name,
					'show_in_rest'     => $public,
					'public'           => $public,
					'capability_type'  => 'post',
					'cap'              => (object) [],
					'labels'           => (object) [ 'name' => $label, 'singular_name' => $label ],
					'supports'         => [],
					'menu_icon'        => '',
					'menu_position'    => null,
					'show_ui'          => $public,
					'show_in_menu'     => $public,
					'show_in_nav_menus'=> $public,
					'show_in_admin_bar'=> $public,
					'rewrite'          => [ 'slug' => $name ],
				];
			};

			$types = [
				'post'       => $make( 'post',       'Posts',       true  ),
				'page'       => $make( 'page',       'Pages',       true  ),
				'attachment' => $make( 'attachment', 'Media',       false ),
			];

			if ( $output === 'names' ) {
				return array_combine( array_keys( $types ), array_keys( $types ) );
			}
			return $types; // 'objects'
		}
	}

	if ( ! function_exists( 'get_post_type_object' ) ) {
		function get_post_type_object( $post_type ) {
			$all = get_post_types( [], 'objects' );
			return $all[ $post_type ] ?? null;
		}
	}

	if ( ! function_exists( 'post_type_exists' ) ) {
		function post_type_exists( $post_type ) {
			return in_array( $post_type, [ 'post', 'page', 'attachment' ] );
		}
	}

	if ( ! function_exists( 'register_post_type' ) ) {
		function register_post_type( $post_type, $args = [] ) {
			WPMocks::registerPostType( $post_type, (array) $args );
			return WPMocks::getPostType( $post_type );
		}
	}

	if ( ! function_exists( 'get_post_meta' ) ) {
		function get_post_meta( $post_id, $key = '', $single = false ) {
			return $single ? '' : [];
		}
	}

	if ( ! function_exists( 'update_post_meta' ) ) {
		function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
			return true;
		}
	}

	if ( ! function_exists( 'delete_post_meta' ) ) {
		function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
			return true;
		}
	}

	if ( ! function_exists( 'add_post_meta' ) ) {
		function add_post_meta( $post_id, $meta_key, $meta_value, $unique = false ) {
			return true;
		}
	}

	if ( ! function_exists( 'maybe_unserialize' ) ) {
		function maybe_unserialize( $data ) {
			if ( is_string( $data ) && strlen( $data ) > 1 ) {
				$unserialized = @unserialize( $data );
				return $unserialized !== false ? $unserialized : $data;
			}
			return $data;
		}
	}

	if ( ! function_exists( 'set_post_thumbnail' ) ) {
		function set_post_thumbnail( $post, $thumbnail_id ): bool {
			$post_id = is_object( $post ) ? $post->ID : (int) $post;
			update_post_meta( $post_id, '_thumbnail_id', (int) $thumbnail_id );
			return true;
		}
	}

	if ( ! function_exists( 'get_permalink' ) ) {
		function get_permalink( $post = 0, $leavename = false ) {
			$id = is_object( $post ) ? $post->ID : (int) $post;
			return 'http://example.com/?p=' . $id;
		}
	}

	if ( ! function_exists( 'get_post_permalink' ) ) {
		function get_post_permalink( $post = 0, $leavename = false, $sample = false ) {
			$id = is_object( $post ) ? $post->ID : (int) $post;
			return 'http://example.com/?p=' . $id;
		}
	}

	// ── Post types / taxonomies ───────────────────────────────────────────────

	if ( ! function_exists( 'add_post_type_support' ) ) {
		function add_post_type_support( $post_type, $feature, ...$args ): void {
			// no-op in tests
		}
	}

	if ( ! function_exists( 'register_taxonomy' ) ) {
		function register_taxonomy( $taxonomy, $object_type, $args = [] ) {
			WPMocks::registerTaxonomy( $taxonomy, $object_type, (array) $args );
			return WPMocks::getTaxonomy( $taxonomy );
		}
	}

	if ( ! function_exists( 'unregister_taxonomy' ) ) {
		function unregister_taxonomy( $taxonomy ): bool {
			// no-op; return success
			return true;
		}
	}

	if ( ! function_exists( 'taxonomy_exists' ) ) {
		function taxonomy_exists( $taxonomy ): bool {
			return in_array( $taxonomy, [ 'category', 'post_tag' ], true )
				|| WPMocks::getTaxonomy( $taxonomy ) !== null;
		}
	}

	if ( ! function_exists( 'get_object_taxonomies' ) ) {
		function get_object_taxonomies( $object_type, $output = 'names' ) {
			$type = is_object( $object_type ) ? $object_type->post_type : (string) $object_type;
			return WPMocks::getObjectTaxonomies( $type );
		}
	}

	// ── Terms ─────────────────────────────────────────────────────────────────

	if ( ! function_exists( 'wp_insert_term' ) ) {
		function wp_insert_term( $term, $taxonomy, $args = [] ) {
			return WPMocks::insertTerm( $term, $taxonomy, (array) $args );
		}
	}

	if ( ! function_exists( 'wp_update_term' ) ) {
		function wp_update_term( $term_id, $taxonomy, $args = [] ) {
			$term = WPMocks::getTerm( $term_id );
			if ( $term ) {
				WPMocks::setTerm( $term_id, array_merge( (array) $term, (array) $args ) );
				return [ 'term_id' => $term_id, 'term_taxonomy_id' => $term_id ];
			}
			return new \WP_Error( 'invalid_term', 'Term not found' );
		}
	}

	if ( ! function_exists( 'wp_delete_term' ) ) {
		function wp_delete_term( $term_id, $taxonomy, $args = [] ) {
			return WPMocks::deleteTerm( $term_id );
		}
	}

	if ( ! function_exists( 'get_term' ) ) {
		function get_term( $term, $taxonomy = '', $output = OBJECT, $filter = 'raw' ) {
			$id = is_object( $term ) ? $term->term_id : (int) $term;
			return WPMocks::getTerm( $id );
		}
	}

	if ( ! function_exists( 'get_terms' ) ) {
		function get_terms( $args = [] ) {
			$taxonomy = '';
			if ( is_array( $args ) ) {
				$taxonomy = $args['taxonomy'] ?? ( $args[0] ?? '' );
			} elseif ( is_string( $args ) ) {
				$taxonomy = $args;
			}
			$terms = $taxonomy
				? WPMocks::getTermsByTaxonomy( $taxonomy )
				: array_values( array_map(
					fn( $id ) => WPMocks::getTerm( $id ),
					array_keys( array_filter(
						array_map( fn( $t ) => $t, iterator_to_array( ( function () {
							// return all term IDs via reflection hack — just return empty for now
							return new \ArrayIterator( [] );
						} )() ) )
					) )
				) );
			return array_map( fn( $t ) => (object) $t, $terms );
		}
	}

	if ( ! function_exists( 'wp_set_object_terms' ) ) {
		function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {
			return [];
		}
	}

	if ( ! function_exists( 'wp_get_object_terms' ) ) {
		function wp_get_object_terms( $object_ids, $taxonomies, $args = [] ) {
			return [];
		}
	}

	// ── Attachments / Media ───────────────────────────────────────────────────

	if ( ! function_exists( 'wp_get_attachment_url' ) ) {
		function wp_get_attachment_url( $attachment_id ) {
			return 'http://example.com/wp-content/uploads/test.jpg';
		}
	}

	if ( ! function_exists( 'get_post_mime_type' ) ) {
		function get_post_mime_type( $post_id = null ) {
			return 'image/jpeg';
		}
	}

	if ( ! function_exists( 'wp_get_attachment_caption' ) ) {
		function wp_get_attachment_caption( $post_id = 0 ) {
			return '';
		}
	}

	if ( ! function_exists( 'wp_count_attachments' ) ) {
		function wp_count_attachments( $mime_type = '' ) {
			return (object) [ 'image/jpeg' => 5, 'image/png' => 3 ];
		}
	}

	if ( ! function_exists( 'wp_delete_attachment' ) ) {
		function wp_delete_attachment( $attachment_id, $force_delete = false ) {
			$post = WPMocks::getPost( $attachment_id );
			WPMocks::deletePost( $attachment_id );
			return $post;
		}
	}

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		function wp_generate_attachment_metadata( $attachment_id, $file ) {
			return [
				'width'  => 800,
				'height' => 600,
				'file'   => basename( $file ),
				'sizes'  => [],
			];
		}
	}

	if ( ! function_exists( 'wp_update_attachment_metadata' ) ) {
		function wp_update_attachment_metadata( $attachment_id, $data ) {
			return update_post_meta( $attachment_id, '_wp_attachment_metadata', $data );
		}
	}

	if ( ! function_exists( 'media_sideload_image' ) ) {
		function media_sideload_image( $url, $post_id = 0, $desc = null, $return_type = 'html' ) {
			$id = WPMocks::insertPost( [
				'post_title'     => $desc ?? basename( $url ),
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			] );
			return $return_type === 'id' ? $id : '<img src="' . $url . '">';
		}
	}

	// ── Users ─────────────────────────────────────────────────────────────────

	if ( ! function_exists( 'get_userdata' ) ) {
		function get_userdata( $user_id ) {
			$raw = WPMocks::getUser( (int) $user_id );
			if ( ! $raw ) {
				return false;
			}
			$user = new \WP_User();
			foreach ( get_object_vars( $raw ) as $k => $v ) {
				$user->$k = $v;
			}
			if ( empty( $user->roles ) ) {
				$user->roles = [ 'subscriber' ];
			}
			return $user;
		}
	}

	if ( ! function_exists( 'get_user_by' ) ) {
		function get_user_by( $field, $value ) {
			if ( $field === 'ID' || $field === 'id' ) {
				return get_userdata( (int) $value );
			}
			// Search by email or login — scan stored users
			foreach ( range( 1, 200 ) as $id ) {
				$u = WPMocks::getUser( $id );
				if ( ! $u ) continue;
				if ( $field === 'email' && ( $u->user_email ?? '' ) === $value ) return get_userdata( $id );
				if ( $field === 'login' && ( $u->user_login ?? '' ) === $value ) return get_userdata( $id );
			}
			return false;
		}
	}

	if ( ! function_exists( 'wp_insert_user' ) ) {
		function wp_insert_user( $userdata ) {
			return WPMocks::insertUser( (array) $userdata );
		}
	}

	if ( ! function_exists( 'wp_update_user' ) ) {
		function wp_update_user( $userdata ) {
			$data = (array) $userdata;
			$id   = $data['ID'] ?? 0;
			if ( $id ) {
				WPMocks::setUser( $id, $data );
				return $id;
			}
			return new \WP_Error( 'invalid_user', 'Invalid user ID' );
		}
	}

	if ( ! function_exists( 'wp_delete_user' ) ) {
		function wp_delete_user( $id, $reassign = null ) {
			return true;
		}
	}

	if ( ! function_exists( 'get_avatar_url' ) ) {
		function get_avatar_url( $id_or_email, $args = [] ): string {
			return 'https://www.gravatar.com/avatar/mock';
		}
	}

	if ( ! function_exists( 'get_users' ) ) {
		function get_users( $args = [] ) {
			return [];
		}
	}

	if ( ! function_exists( 'get_user_meta' ) ) {
		function get_user_meta( $user_id, $key = '', $single = false ) {
			return $single ? '' : [];
		}
	}

	if ( ! function_exists( 'update_user_meta' ) ) {
		function update_user_meta( $user_id, $meta_key, $meta_value, $prev_value = '' ) {
			return true;
		}
	}

	if ( ! function_exists( 'delete_user_meta' ) ) {
		function delete_user_meta( $user_id, $meta_key, $meta_value = '' ) {
			return true;
		}
	}

	if ( ! function_exists( 'user_can' ) ) {
		function user_can( $user, $capability, ...$args ) {
			return true;
		}
	}

	if ( ! function_exists( 'get_current_user_id' ) ) {
		function get_current_user_id(): int {
			return 1;
		}
	}

	if ( ! function_exists( 'current_user_can' ) ) {
		function current_user_can( string $capability ): bool {
			return false;
		}
	}

	if ( ! function_exists( 'wp_get_current_user' ) ) {
		function wp_get_current_user() {
			$raw = WPMocks::getUser( 1 );
			if ( ! $raw ) {
				WPMocks::setUser( 1, [] );
				$raw = WPMocks::getUser( 1 );
			}
			$user = new \WP_User();
			foreach ( get_object_vars( $raw ) as $k => $v ) {
				$user->$k = $v;
			}
			$user->roles = [ 'administrator' ];
			return $user;
		}
	}

	if ( ! function_exists( 'wp_authenticate' ) ) {
		function wp_authenticate( $username, $password ) {
			return get_userdata( 1 ) ?: new \WP_Error( 'invalid_username', 'Invalid username.' );
		}
	}

	if ( ! function_exists( 'wp_logout' ) ) {
		function wp_logout(): void {
			// no-op in tests
		}
	}

	if ( ! function_exists( 'wp_set_password' ) ) {
		function wp_set_password( $password, $user_id ): void {
			// no-op in tests
		}
	}

	if ( ! function_exists( 'get_password_reset_key' ) ) {
		function get_password_reset_key( $user ) {
			return 'mock-reset-key-' . time();
		}
	}

	if ( ! function_exists( 'wp_mail' ) ) {
		function wp_mail( $to, $subject, $message, $headers = '', $attachments = [] ): bool {
			return true;
		}
	}

	// ── Roles / Capabilities ──────────────────────────────────────────────────

	if ( ! function_exists( 'wp_roles' ) ) {
		function wp_roles() {
			return (object) [
				'roles' => [
					'administrator' => [ 'name' => 'Administrator', 'capabilities' => [ 'manage_options' => true ] ],
					'editor'        => [ 'name' => 'Editor',        'capabilities' => [ 'edit_posts' => true ] ],
					'subscriber'    => [ 'name' => 'Subscriber',    'capabilities' => [ 'read' => true ] ],
				],
				'role_names' => [
					'administrator' => 'Administrator',
					'editor'        => 'Editor',
					'subscriber'    => 'Subscriber',
				],
				'role_key' => 'wp_user_roles',
			];
		}
	}

	if ( ! function_exists( 'get_role' ) ) {
		function get_role( $role ) {
			$roles = wp_roles()->roles;
			if ( isset( $roles[ $role ] ) ) {
				return (object) [
					'name'         => $roles[ $role ]['name'],
					'capabilities' => $roles[ $role ]['capabilities'],
				];
			}
			return null;
		}
	}

	if ( ! function_exists( 'add_role' ) ) {
		function add_role( $role, $display_name, $capabilities = [] ) {
			WPMocks::addRole( $role, $display_name, $capabilities );
			return get_role( $role ) ?? (object) [ 'name' => $display_name, 'capabilities' => $capabilities ];
		}
	}

	if ( ! function_exists( 'remove_role' ) ) {
		function remove_role( $role ): void {
			WPMocks::removeRole( $role );
		}
	}

	// ── Comments ─────────────────────────────────────────────────────────────

	if ( ! function_exists( 'get_comment' ) ) {
		function get_comment( $comment = null, $output = OBJECT ) {
			$id = is_object( $comment ) ? $comment->comment_ID : (int) $comment;
			return WPMocks::getComment( $id );
		}
	}

	if ( ! function_exists( 'get_comments' ) ) {
		function get_comments( $args = [] ) {

			$results = [];

			// Basic support for post_id filtering
			$post_id = $args['post_id'] ?? null;

			for ( $i = 1; $i <= 500; $i++ ) {
				$comment = WPMocks::getComment( $i );
				if ( ! $comment ) {
					continue;
				}

				if ( $post_id && (int) $comment->comment_post_ID !== (int) $post_id ) {
					continue;
				}

				$results[] = $comment;
			}

			return $results;
		}
	}

	if ( ! function_exists( 'wp_insert_comment' ) ) {
		function wp_insert_comment( $commentdata ) {
			return WPMocks::insertComment( $commentdata );
		}
	}

	if ( ! function_exists( 'wp_update_comment' ) ) {
		function wp_update_comment( $commentarr, $wp_error = false ) {
			$id = $commentarr['comment_ID'] ?? 0;
			if ( $id ) {
				WPMocks::setComment( $id, $commentarr );
				return 1;
			}
			return 0;
		}
	}

	if ( ! function_exists( 'wp_delete_comment' ) ) {
		function wp_delete_comment( $comment_id, $force_delete = false ) {
			return true;
		}
	}

	if ( ! function_exists( 'wp_trash_comment' ) ) {
		function wp_trash_comment( $comment_id ) {
			return true;
		}
	}

	if ( ! function_exists( 'wp_untrash_comment' ) ) {
		function wp_untrash_comment( $comment_id ) {
			return true;
		}
	}

	if ( ! function_exists( 'wp_set_comment_status' ) ) {
		function wp_set_comment_status( $comment_id, $comment_status, $wp_error = false ) {
			return true;
		}
	}

	if ( ! function_exists( 'wp_spam_comment' ) ) {
		function wp_spam_comment( $comment_id ) {
			$comment = WPMocks::getComment( (int) $comment_id );
			if ( $comment ) {
				WPMocks::setComment( (int) $comment_id, [ 'comment_approved' => 'spam' ] );
				return true;
			}
			return false;
		}
	}

	if ( ! function_exists( 'wp_unspam_comment' ) ) {
		function wp_unspam_comment( $comment_id ) {
			$comment = WPMocks::getComment( (int) $comment_id );
			if ( $comment ) {
				WPMocks::setComment( (int) $comment_id, [ 'comment_approved' => '1' ] );
				return true;
			}
			return false;
		}
	}

	if ( ! function_exists( 'get_comment_meta' ) ) {
		function get_comment_meta( $comment_id, $key = '', $single = false ) {
			return $single ? '' : [];
		}
	}

	// ── Misc ──────────────────────────────────────────────────────────────────

	if ( ! function_exists( 'wp_parse_args' ) ) {
		function wp_parse_args( $args, $defaults = [] ) {
			if ( is_object( $args ) ) {
				$args = get_object_vars( $args );
			}
			return array_merge( $defaults, (array) $args );
		}
	}

	if ( ! function_exists( 'absint' ) ) {
		function absint( $maybeint ) {
			return abs( (int) $maybeint );
		}
	}

	if ( ! function_exists( 'wp_mkdir_p' ) ) {
		function wp_mkdir_p( string $target ): bool {
			return is_dir( $target ) || @mkdir( $target, 0755, true );
		}
	}

	if ( ! function_exists( 'get_home_url' ) ) {
		function get_home_url( $blog_id = null, $path = '', $scheme = null ): string {
			return 'http://example.com' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
		}
	}

	if ( ! function_exists( 'get_blog_option' ) ) {
		function get_blog_option( $id, $option, $default = false ) {
			return get_option( $option, $default );
		}
	}

	if ( ! function_exists( 'switch_to_blog' ) ) {
		function switch_to_blog( $new_blog_id ): bool {
			return true;
		}
	}

	if ( ! function_exists( 'restore_current_blog' ) ) {
		function restore_current_blog(): bool {
			return true;
		}
	}

	if ( ! function_exists( 'get_current_blog_id' ) ) {
		function get_current_blog_id(): int {
			return 1;
		}
	}

	// ── Constants / defines ───────────────────────────────────────────────────

	if ( ! defined( 'OBJECT' ) )             define( 'OBJECT',             'OBJECT' );
	if ( ! defined( 'ARRAY_A' ) )            define( 'ARRAY_A',            'ARRAY_A' );
	if ( ! defined( 'ARRAY_N' ) )            define( 'ARRAY_N',            'ARRAY_N' );
	if ( ! defined( 'AUTH_KEY' ) )           define( 'AUTH_KEY',           'test-auth-key-for-phpunit-testing' );
	if ( ! defined( 'WP_CONTENT_DIR' ) )     define( 'WP_CONTENT_DIR',     sys_get_temp_dir() . '/wp-content' );
	if ( ! defined( 'MINUTE_IN_SECONDS' ) )  define( 'MINUTE_IN_SECONDS',  60 );
	if ( ! defined( 'HOUR_IN_SECONDS' ) )    define( 'HOUR_IN_SECONDS',    3600 );
	if ( ! defined( 'DAY_IN_SECONDS' ) )     define( 'DAY_IN_SECONDS',     86400 );
}