<?php

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        return [
            'response' => ['code' => 200],
            'body' => json_encode(['email' => 'test@example.com']),
        ];
    }
}

if (!function_exists('set_url_scheme')) {
    function set_url_scheme($url, $scheme = null) {
        return $url;
    }
}

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = []) {

        if (str_contains($url, '/forms')) {
            return [
                'response' => ['code' => 201],
                'body' => json_encode([
                    'id' => 'form123',
                    'title' => 'Test Form',
                    '_links' => ['display' => 'https://form.url']
                ]),
            ];
        }

        return [
            'response' => ['code' => 200],
            'body' => json_encode([]),
        ];
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return $text;
    }
}

if (!function_exists('rest_url')) {
    function rest_url($path = '') {
        return 'http://example.com/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('__')) {
    function __($text, $domain = null) {
        return $text;
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

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}