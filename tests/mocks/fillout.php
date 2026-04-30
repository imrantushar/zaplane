<?php

class WP_Mock_Data {
    public static array $remote_get_response = [];
    public static array $remote_request_response = [];
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {

        if (!empty(WP_Mock_Data::$remote_get_response)) {
            return WP_Mock_Data::$remote_get_response;
        }

        return [
            'response' => ['code' => 200],
            'body' => json_encode(['default' => true]),
        ];
    }
}

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = []) {

        if (!empty(WP_Mock_Data::$remote_request_response)) {
            return WP_Mock_Data::$remote_request_response;
        }

        if (str_contains($url, '/forms')) {
            return [
                'response' => ['code' => 201],
                'body' => json_encode([
                    'formId' => 'form123',
                    'name'   => 'Test Form'
                ]),
            ];
        }

        return [
            'response' => ['code' => 200],
            'body' => json_encode([]),
        ];
    }
}

if (!function_exists('set_url_scheme')) {
    function set_url_scheme($url, $scheme = null) {
        return $url;
    }
}

if (!function_exists('rest_url')) {
    function rest_url($path = '') {
        return 'http://example.com/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return $response['response']['code'] ?? 200;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '';
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return $text;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = null) {
        return $text;
    }
}