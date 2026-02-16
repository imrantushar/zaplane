<?php

namespace Zaplane\Tests {
    class WPMocks
    {
        private static array $options = [];
        private static array $transients = [];
        private static array $posts = [];
        private static array $users = [];
        private static array $comments = [];
        private static int $lastInsertId = 100;

        public static function reset(): void
        {
            self::$options = [];
            self::$transients = [];
            self::$posts = [];
            self::$users = [];
            self::$comments = [];
            self::$lastInsertId = 100;
        }

        // Posts
        public static function setPost(int $id, array $data): void
        {
            self::$posts[$id] = array_merge(self::getDefaultPost($id), $data);
        }

        public static function getPost(int $id): ?object
        {
            if (!isset(self::$posts[$id])) {
                return null;
            }
            return (object) self::$posts[$id];
        }

        public static function insertPost(array $data): int
        {
            $id = ++self::$lastInsertId;
            self::$posts[$id] = array_merge(self::getDefaultPost($id), $data, ['ID' => $id]);
            return $id;
        }

        public static function updatePost(array $data): int
        {
            $id = $data['ID'] ?? 0;
            if ($id && isset(self::$posts[$id])) {
                self::$posts[$id] = array_merge(self::$posts[$id], $data);
                return $id;
            }
            return 0;
        }

        public static function deletePost(int $id): bool
        {
            if (isset(self::$posts[$id])) {
                unset(self::$posts[$id]);
                return true;
            }
            return false;
        }

        private static function getDefaultPost(int $id): array
        {
            return [
                'ID' => $id,
                'post_author' => 1,
                'post_date' => '2024-01-01 00:00:00',
                'post_date_gmt' => '2024-01-01 00:00:00',
                'post_content' => 'Test content',
                'post_title' => 'Test Post ' . $id,
                'post_excerpt' => '',
                'post_status' => 'publish',
                'comment_status' => 'open',
                'ping_status' => 'open',
                'post_password' => '',
                'post_name' => 'test-post-' . $id,
                'to_ping' => '',
                'pinged' => '',
                'post_modified' => '2024-01-01 00:00:00',
                'post_modified_gmt' => '2024-01-01 00:00:00',
                'post_content_filtered' => '',
                'post_parent' => 0,
                'guid' => 'http://example.com/?p=' . $id,
                'menu_order' => 0,
                'post_type' => 'post',
                'post_mime_type' => '',
                'comment_count' => 0,
            ];
        }

        // Users
        public static function setUser(int $id, array $data): void
        {
            self::$users[$id] = array_merge(self::getDefaultUser($id), $data);
        }

        public static function getUser(int $id): ?object
        {
            if (!isset(self::$users[$id])) {
                return null;
            }
            return (object) self::$users[$id];
        }

        public static function insertUser(array $data): int
        {
            $id = ++self::$lastInsertId;
            self::$users[$id] = array_merge(self::getDefaultUser($id), $data, ['ID' => $id]);
            return $id;
        }

        private static function getDefaultUser(int $id): array
        {
            return [
                'ID' => $id,
                'user_login' => 'user' . $id,
                'user_pass' => '',
                'user_nicename' => 'user' . $id,
                'user_email' => 'user' . $id . '@example.com',
                'user_url' => '',
                'user_registered' => '2024-01-01 00:00:00',
                'user_activation_key' => '',
                'user_status' => 0,
                'display_name' => 'User ' . $id,
            ];
        }

        // Comments
        public static function setComment(int $id, array $data): void
        {
            self::$comments[$id] = array_merge(self::getDefaultComment($id), $data);
        }

        public static function getComment(int $id): ?object
        {
            if (!isset(self::$comments[$id])) {
                return null;
            }
            return (object) self::$comments[$id];
        }

        public static function insertComment(array $data): int
        {
            $id = ++self::$lastInsertId;
            self::$comments[$id] = array_merge(self::getDefaultComment($id), $data, ['comment_ID' => $id]);
            return $id;
        }

        private static function getDefaultComment(int $id): array
        {
            return [
                'comment_ID' => $id,
                'comment_post_ID' => 1,
                'comment_author' => 'Test Author',
                'comment_author_email' => 'author@example.com',
                'comment_author_url' => '',
                'comment_author_IP' => '127.0.0.1',
                'comment_date' => '2024-01-01 00:00:00',
                'comment_date_gmt' => '2024-01-01 00:00:00',
                'comment_content' => 'Test comment ' . $id,
                'comment_karma' => 0,
                'comment_approved' => '1',
                'comment_agent' => '',
                'comment_type' => 'comment',
                'comment_parent' => 0,
                'user_id' => 0,
            ];
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

    // Post functions
    if (!function_exists('get_post')) {
        function get_post($post = null, $output = OBJECT, $filter = 'raw')
        {
            $id = is_object($post) ? $post->ID : (int) $post;
            return WPMocks::getPost($id);
        }
    }

    if (!function_exists('wp_insert_post')) {
        function wp_insert_post($postarr, $wp_error = false, $fire_after_hooks = true)
        {
            return WPMocks::insertPost($postarr);
        }
    }

    if (!function_exists('wp_update_post')) {
        function wp_update_post($postarr = [], $wp_error = false, $fire_after_hooks = true)
        {
            return WPMocks::updatePost($postarr);
        }
    }

    if (!function_exists('wp_delete_post')) {
        function wp_delete_post($postid = 0, $force_delete = false)
        {
            return WPMocks::deletePost($postid) ? WPMocks::getPost($postid) : null;
        }
    }

    if (!function_exists('wp_trash_post')) {
        function wp_trash_post($post_id = 0)
        {
            $post = WPMocks::getPost($post_id);
            if ($post) {
                WPMocks::setPost($post_id, ['post_status' => 'trash']);
                return $post;
            }
            return false;
        }
    }

    if (!function_exists('get_post_type')) {
        function get_post_type($post = null)
        {
            $post = get_post($post);
            return $post ? $post->post_type : false;
        }
    }

    if (!function_exists('get_post_status')) {
        function get_post_status($post = null)
        {
            $post = get_post($post);
            return $post ? $post->post_status : false;
        }
    }

    if (!function_exists('get_post_types')) {
        function get_post_types($args = [], $output = 'names', $operator = 'and')
        {
            return ['post' => 'post', 'page' => 'page', 'attachment' => 'attachment'];
        }
    }

    if (!function_exists('post_type_exists')) {
        function post_type_exists($post_type)
        {
            return in_array($post_type, ['post', 'page', 'attachment']);
        }
    }

    if (!function_exists('get_post_meta')) {
        function get_post_meta($post_id, $key = '', $single = false)
        {
            return $single ? '' : [];
        }
    }

    if (!function_exists('update_post_meta')) {
        function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '')
        {
            return true;
        }
    }

    if (!function_exists('delete_post_meta')) {
        function delete_post_meta($post_id, $meta_key, $meta_value = '')
        {
            return true;
        }
    }

    // User functions
    if (!function_exists('get_userdata')) {
        function get_userdata($user_id)
        {
            $user = WPMocks::getUser($user_id);
            if ($user) {
                $user->roles = ['subscriber'];
                $user->caps = ['read' => true];
            }
            return $user;
        }
    }

    if (!function_exists('get_user_by')) {
        function get_user_by($field, $value)
        {
            if ($field === 'ID' || $field === 'id') {
                return get_userdata($value);
            }
            return false;
        }
    }

    if (!function_exists('wp_insert_user')) {
        function wp_insert_user($userdata)
        {
            return WPMocks::insertUser($userdata);
        }
    }

    if (!function_exists('wp_update_user')) {
        function wp_update_user($userdata)
        {
            $id = $userdata['ID'] ?? 0;
            if ($id) {
                WPMocks::setUser($id, $userdata);
                return $id;
            }
            return new \WP_Error('invalid_user', 'Invalid user ID');
        }
    }

    if (!function_exists('wp_delete_user')) {
        function wp_delete_user($id, $reassign = null)
        {
            return true;
        }
    }

    if (!function_exists('get_user_meta')) {
        function get_user_meta($user_id, $key = '', $single = false)
        {
            return $single ? '' : [];
        }
    }

    if (!function_exists('update_user_meta')) {
        function update_user_meta($user_id, $meta_key, $meta_value, $prev_value = '')
        {
            return true;
        }
    }

    if (!function_exists('delete_user_meta')) {
        function delete_user_meta($user_id, $meta_key, $meta_value = '')
        {
            return true;
        }
    }

    if (!function_exists('user_can')) {
        function user_can($user, $capability, ...$args)
        {
            return true;
        }
    }

    if (!function_exists('wp_get_current_user')) {
        function wp_get_current_user()
        {
            $user = WPMocks::getUser(1);
            if (!$user) {
                WPMocks::setUser(1, []);
                $user = WPMocks::getUser(1);
            }
            $user->roles = ['administrator'];
            return $user;
        }
    }

    // Comment functions
    if (!function_exists('get_comment')) {
        function get_comment($comment = null, $output = OBJECT)
        {
            $id = is_object($comment) ? $comment->comment_ID : (int) $comment;
            return WPMocks::getComment($id);
        }
    }

    if (!function_exists('wp_insert_comment')) {
        function wp_insert_comment($commentdata)
        {
            return WPMocks::insertComment($commentdata);
        }
    }

    if (!function_exists('wp_update_comment')) {
        function wp_update_comment($commentarr, $wp_error = false)
        {
            $id = $commentarr['comment_ID'] ?? 0;
            if ($id) {
                WPMocks::setComment($id, $commentarr);
                return 1;
            }
            return 0;
        }
    }

    if (!function_exists('wp_delete_comment')) {
        function wp_delete_comment($comment_id, $force_delete = false)
        {
            return true;
        }
    }

    if (!function_exists('wp_trash_comment')) {
        function wp_trash_comment($comment_id)
        {
            return true;
        }
    }

    if (!function_exists('wp_set_comment_status')) {
        function wp_set_comment_status($comment_id, $comment_status, $wp_error = false)
        {
            return true;
        }
    }

    // Attachment functions
    if (!function_exists('wp_get_attachment_url')) {
        function wp_get_attachment_url($attachment_id)
        {
            return 'http://example.com/wp-content/uploads/test.jpg';
        }
    }

    if (!function_exists('get_post_mime_type')) {
        function get_post_mime_type($post_id = null)
        {
            return 'image/jpeg';
        }
    }

    if (!function_exists('wp_get_attachment_caption')) {
        function wp_get_attachment_caption($post_id = 0)
        {
            return '';
        }
    }

    if (!function_exists('wp_count_attachments')) {
        function wp_count_attachments($mime_type = '')
        {
            return (object) ['image/jpeg' => 5, 'image/png' => 3];
        }
    }

    // Misc functions
    if (!function_exists('wp_parse_args')) {
        function wp_parse_args($args, $defaults = [])
        {
            if (is_object($args)) {
                $args = get_object_vars($args);
            }
            return array_merge($defaults, $args);
        }
    }

    if (!function_exists('absint')) {
        function absint($maybeint)
        {
            return abs((int) $maybeint);
        }
    }

    if (!function_exists('sanitize_title')) {
        function sanitize_title($title, $fallback_title = '', $context = 'save')
        {
            return strtolower(preg_replace('/[^a-z0-9-]/', '-', strtolower($title)));
        }
    }

    if (!function_exists('sanitize_key')) {
        function sanitize_key($key)
        {
            return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
        }
    }

    if (!function_exists('esc_html')) {
        function esc_html($text)
        {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('esc_attr')) {
        function esc_attr($text)
        {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('wp_roles')) {
        function wp_roles()
        {
            return (object) [
                'roles' => [
                    'administrator' => ['name' => 'Administrator', 'capabilities' => ['manage_options' => true]],
                    'editor' => ['name' => 'Editor', 'capabilities' => ['edit_posts' => true]],
                    'subscriber' => ['name' => 'Subscriber', 'capabilities' => ['read' => true]],
                ],
                'role_names' => [
                    'administrator' => 'Administrator',
                    'editor' => 'Editor',
                    'subscriber' => 'Subscriber',
                ],
                'role_key' => 'wp_user_roles',
            ];
        }
    }

    if (!function_exists('get_role')) {
        function get_role($role)
        {
            $roles = wp_roles()->roles;
            if (isset($roles[$role])) {
                return (object) [
                    'name' => $roles[$role]['name'],
                    'capabilities' => $roles[$role]['capabilities'],
                ];
            }
            return null;
        }
    }
}
