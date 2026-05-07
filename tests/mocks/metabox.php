<?php

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id ) {
		return [
			'your_name' => ['John Doe'],
			'_hidden'   => ['should_skip'],
		];
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id ) {
		return (object) [
			'ID'         => $post_id,
			'post_title' => 'Test Post',
			'post_type'  => 'post',
		];
	}
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $value ) {
		return $value;
	}
}