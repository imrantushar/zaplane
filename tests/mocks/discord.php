<?php

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = [])
    {
        return [
            'body' => json_encode([
                'id' => '1',
                'username' => 'MockBot'
            ]),
            'response' => ['code' => 200]
        ];
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        return $response['body'] ?? '';
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code()
    {
        return 200;
    }
}