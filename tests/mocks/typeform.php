<?php

if (!function_exists('set_url_scheme')) {
    function set_url_scheme($url, $scheme = null) {
        return $url;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = null) {
        return $text;
    }
}