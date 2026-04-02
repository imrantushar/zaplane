<?php

if ( ! function_exists( 'is_email' ) ) {
    function is_email( $email ) {
        return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
    }
}

if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $email ) {
        return (string) $email;
    }
}

if ( ! function_exists( 'sanitize_text_field' )) {
    function sanitize_text_field( $text ) {
        return (string) $text;
    }
}