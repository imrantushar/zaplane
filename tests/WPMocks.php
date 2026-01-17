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
}
