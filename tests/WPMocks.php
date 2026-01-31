<?php

namespace Zaplane\Tests {
    class WPMocks
    {
        private static array $options = [];
        private static array $transients = [];

        public static function reset(): void
        {
            self::$options = [];
            self::$transients = [];
        }

        public static function setOption(string $key, $value): void
        {
            self::$options[$key] = $value;
        }

        public static function getOption(string $key, $default = false)
        {
            return self::$options[$key] ?? $default;
        }

        public static function setTransient(string $key, $value): void
        {
            self::$transients[$key] = $value;
        }

        public static function getTransient(string $key)
        {
            return self::$transients[$key] ?? false;
        }

        public static function deleteTransient(string $key): void
        {
            unset(self::$transients[$key]);
        }
    }
}

namespace {
    use Zaplane\Tests\WPMocks;

    if (!function_exists('get_option')) {
        function get_option(string $key, $default = false)
        {
            return WPMocks::getOption($key, $default);
        }
    }

    if (!function_exists('update_option')) {
        function update_option(string $key, $value, $autoload = null): bool
        {
            WPMocks::setOption($key, $value);
            return true;
        }
    }

    if (!function_exists('get_transient')) {
        function get_transient(string $key)
        {
            return WPMocks::getTransient($key);
        }
    }

    if (!function_exists('set_transient')) {
        function set_transient(string $key, $value, int $expiration = 0): bool
        {
            WPMocks::setTransient($key, $value);
            return true;
        }
    }

    if (!function_exists('delete_transient')) {
        function delete_transient(string $key): bool
        {
            WPMocks::deleteTransient($key);
            return true;
        }
    }

    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data, int $options = 0, int $depth = 512)
        {
            return json_encode($data, $options, $depth);
        }
    }

    if (!function_exists('current_time')) {
        function current_time(string $type, bool $gmt = false): string
        {
            if ($type === 'mysql') {
                return $gmt ? gmdate('Y-m-d H:i:s') : date('Y-m-d H:i:s');
            }
            return (string) time();
        }
    }

    if (!function_exists('rest_url')) {
        function rest_url(string $path = ''): string
        {
            return 'https://example.com/wp-json/' . ltrim($path, '/');
        }
    }

    if (!function_exists('add_action')) {
        function add_action(string $tag, $callback, int $priority = 10, int $accepted_args = 1): bool
        {
            return true;
        }
    }

    if (!function_exists('remove_action')) {
        function remove_action(string $tag, $callback, int $priority = 10): bool
        {
            return true;
        }
    }

    if (!function_exists('do_action')) {
        function do_action(string $tag, ...$args): void
        {
        }
    }

    if (!function_exists('apply_filters')) {
        function apply_filters(string $tag, $value, ...$args)
        {
            return $value;
        }
    }

    if (!function_exists('current_filter')) {
        function current_filter(): string
        {
            return '';
        }
    }

    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing): bool
        {
            return $thing instanceof \WP_Error;
        }
    }

    if (!class_exists('WP_Error')) {
        class WP_Error
        {
            protected string $code;
            protected string $message;
            protected array $data;

            public function __construct(string $code = '', string $message = '', $data = [])
            {
                $this->code = $code;
                $this->message = $message;
                $this->data = is_array($data) ? $data : ['data' => $data];
            }

            public function get_error_code(): string
            {
                return $this->code;
            }

            public function get_error_message(): string
            {
                return $this->message;
            }

            public function get_error_data($code = ''): array
            {
                return $this->data;
            }
        }
    }

    if (!defined('MINUTE_IN_SECONDS')) {
        define('MINUTE_IN_SECONDS', 60);
    }

    if (!defined('HOUR_IN_SECONDS')) {
        define('HOUR_IN_SECONDS', 3600);
    }

    if (!defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 86400);
    }

    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field(string $str): string
        {
            return trim(strip_tags($str));
        }
    }

    if (!function_exists('get_current_user_id')) {
        function get_current_user_id(): int
        {
            return 1;
        }
    }

    if (!function_exists('current_user_can')) {
        function current_user_can(string $capability): bool
        {
            return false;
        }
    }

    if (!function_exists('rest_ensure_response')) {
        function rest_ensure_response($response)
        {
            if ($response instanceof \WP_REST_Response) {
                return $response;
            }
            return new \WP_REST_Response($response);
        }
    }

    if (!class_exists('WP_REST_Response')) {
        class WP_REST_Response
        {
            protected $data;
            protected int $status;

            public function __construct($data = null, int $status = 200)
            {
                $this->data = $data;
                $this->status = $status;
            }

            public function get_data()
            {
                return $this->data;
            }

            public function get_status(): int
            {
                return $this->status;
            }
        }
    }

    if (!class_exists('WP_REST_Controller')) {
        abstract class WP_REST_Controller
        {
            protected string $namespace = '';
            protected string $rest_base = '';

            public function register_routes()
            {
            }
        }
    }

    if (!class_exists('WP_REST_Server')) {
        class WP_REST_Server
        {
            public const READABLE = 'GET';
            public const CREATABLE = 'POST';
            public const EDITABLE = 'POST, PUT, PATCH';
            public const DELETABLE = 'DELETE';
        }
    }

    if (!function_exists('register_rest_route')) {
        function register_rest_route(string $namespace, string $route, array $args = []): bool
        {
            return true;
        }
    }

    if (!function_exists('wp_remote_post')) {
        function wp_remote_post(string $url, array $args = [])
        {
            return new \WP_Error('http_request_failed', 'Mock: HTTP requests disabled in tests');
        }
    }

    if (!function_exists('wp_remote_get')) {
        function wp_remote_get(string $url, array $args = [])
        {
            return new \WP_Error('http_request_failed', 'Mock: HTTP requests disabled in tests');
        }
    }

    if (!function_exists('wp_remote_retrieve_body')) {
        function wp_remote_retrieve_body($response): string
        {
            return '';
        }
    }

    if (!defined('OBJECT')) {
        define('OBJECT', 'OBJECT');
    }

    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }

    if (!defined('ARRAY_N')) {
        define('ARRAY_N', 'ARRAY_N');
    }

    if (!defined('AUTH_KEY')) {
        define('AUTH_KEY', 'test-auth-key-for-phpunit-testing');
    }

    if (!defined('WP_CONTENT_DIR')) {
        define('WP_CONTENT_DIR', sys_get_temp_dir() . '/wp-content');
    }

    if (!function_exists('wp_mkdir_p')) {
        function wp_mkdir_p(string $target): bool
        {
            if (is_dir($target)) {
                return true;
            }
            return @mkdir($target, 0755, true);
        }
    }

    if (!function_exists('wp_timezone_string')) {
        function wp_timezone_string(): string
        {
            return 'UTC';
        }
    }

    // WordPress Post Mocks
    if (!function_exists('get_post')) {
        function get_post($post = null, $output = OBJECT, $filter = 'raw')
        {
            if (!$post) return null;
            $id = is_object($post) ? $post->ID : (int) $post;
            return (object) [
                'ID' => $id,
                'post_title' => 'Test Post ' . $id,
                'post_content' => 'Test content for post ' . $id,
                'post_excerpt' => 'Test excerpt',
                'post_status' => 'publish',
                'post_type' => 'post',
                'post_author' => 1,
                'post_date' => '2024-01-01 00:00:00',
                'post_modified' => '2024-01-01 00:00:00',
                'post_parent' => 0,
            ];
        }
    }

    if (!function_exists('get_userdata')) {
        function get_userdata($user_id)
        {
            if (!$user_id) return false;
            return (object) [
                'ID' => $user_id,
                'user_login' => 'testuser' . $user_id,
                'user_email' => 'test' . $user_id . '@example.com',
                'display_name' => 'Test User ' . $user_id,
                'roles' => ['subscriber'],
            ];
        }
    }

    if (!function_exists('get_comment')) {
        function get_comment($comment = null, $output = OBJECT)
        {
            if (!$comment) return null;
            $id = is_object($comment) ? $comment->comment_ID : (int) $comment;
            return (object) [
                'comment_ID' => $id,
                'comment_post_ID' => 1,
                'comment_content' => 'Test comment content',
                'comment_author' => 'Test Author',
                'comment_author_email' => 'author@example.com',
                'comment_approved' => '1',
                'comment_date' => '2024-01-01 00:00:00',
            ];
        }
    }

    if (!function_exists('get_term')) {
        function get_term($term, $taxonomy = '', $output = OBJECT, $filter = 'raw')
        {
            if (!$term) return null;
            $id = is_object($term) ? $term->term_id : (int) $term;
            return (object) [
                'term_id' => $id,
                'name' => 'Test Term ' . $id,
                'slug' => 'test-term-' . $id,
                'taxonomy' => $taxonomy ?: 'category',
                'description' => 'Test term description',
                'parent' => 0,
                'count' => 5,
            ];
        }
    }

    if (!function_exists('wp_insert_post')) {
        function wp_insert_post($postarr, $wp_error = false, $fire_after_hooks = true)
        {
            static $next_id = 1000;
            if (empty($postarr['post_title'])) {
                return $wp_error ? new \WP_Error('empty_content', 'Content required') : 0;
            }
            return $next_id++;
        }
    }

    if (!function_exists('wp_update_post')) {
        function wp_update_post($postarr, $wp_error = false, $fire_after_hooks = true)
        {
            if (empty($postarr['ID'])) {
                return $wp_error ? new \WP_Error('invalid_post', 'Invalid post ID') : 0;
            }
            return $postarr['ID'];
        }
    }

    if (!function_exists('wp_delete_post')) {
        function wp_delete_post($postid, $force_delete = false)
        {
            return get_post($postid);
        }
    }

    if (!function_exists('wp_trash_post')) {
        function wp_trash_post($post_id = 0)
        {
            return get_post($post_id);
        }
    }

    if (!function_exists('wp_insert_user')) {
        function wp_insert_user($userdata)
        {
            static $next_id = 1000;
            if (empty($userdata['user_login']) || empty($userdata['user_email'])) {
                return new \WP_Error('missing_data', 'User login and email required');
            }
            return $next_id++;
        }
    }

    if (!function_exists('wp_update_user')) {
        function wp_update_user($userdata)
        {
            if (empty($userdata['ID'])) {
                return new \WP_Error('invalid_user', 'Invalid user ID');
            }
            return $userdata['ID'];
        }
    }

    if (!function_exists('wp_delete_user')) {
        function wp_delete_user($id, $reassign = null): bool
        {
            return $id > 0;
        }
    }

    if (!function_exists('wp_insert_comment')) {
        function wp_insert_comment($commentdata)
        {
            static $next_id = 1000;
            if (empty($commentdata['comment_content'])) {
                return 0;
            }
            return $next_id++;
        }
    }

    if (!function_exists('wp_delete_comment')) {
        function wp_delete_comment($comment_id, $force_delete = false): bool
        {
            return $comment_id > 0;
        }
    }

    if (!function_exists('wp_set_comment_status')) {
        function wp_set_comment_status($comment_id, $status, $wp_error = false)
        {
            return true;
        }
    }

    if (!function_exists('wp_insert_term')) {
        function wp_insert_term($term, $taxonomy, $args = [])
        {
            static $next_id = 1000;
            return ['term_id' => $next_id++, 'term_taxonomy_id' => $next_id];
        }
    }

    if (!function_exists('wp_update_term')) {
        function wp_update_term($term_id, $taxonomy, $args = [])
        {
            return ['term_id' => $term_id, 'term_taxonomy_id' => $term_id + 1];
        }
    }

    if (!function_exists('wp_delete_term')) {
        function wp_delete_term($term_id, $taxonomy, $args = []): bool
        {
            return true;
        }
    }

    if (!function_exists('get_post_types')) {
        function get_post_types($args = [], $output = 'names', $operator = 'and')
        {
            return ['post' => 'post', 'page' => 'page'];
        }
    }

    if (!function_exists('get_post_statuses')) {
        function get_post_statuses()
        {
            return ['publish' => 'Published', 'draft' => 'Draft', 'pending' => 'Pending'];
        }
    }

    if (!function_exists('get_editable_roles')) {
        function get_editable_roles()
        {
            return [
                'administrator' => ['name' => 'Administrator'],
                'editor' => ['name' => 'Editor'],
                'author' => ['name' => 'Author'],
                'subscriber' => ['name' => 'Subscriber'],
            ];
        }
    }

    if (!function_exists('wp_get_current_user')) {
        function wp_get_current_user()
        {
            return (object) [
                'ID' => 1,
                'user_login' => 'admin',
                'user_email' => 'admin@example.com',
                'roles' => ['administrator'],
            ];
        }
    }

    if (!function_exists('as_enqueue_async_action')) {
        function as_enqueue_async_action($hook, $args = [], $group = '', $unique = false)
        {
            static $next_id = 1;
            return $next_id++;
        }
    }

    if (!function_exists('as_schedule_single_action')) {
        function as_schedule_single_action($timestamp, $hook, $args = [], $group = '', $unique = false)
        {
            static $next_id = 1000;
            return $next_id++;
        }
    }
}
