<?php
/**
 * Academy-specific recipe factories. Auto-loaded by RecipeFactories when any
 * recipe runs, so recipes in this folder can reference them by name.
 *
 * @package Zaplane
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'zaplane_recipe_factories',
	function ( array $factories ) {
		// Creates a real course + user + enrollment and returns their ids, so a
		// recipe can fire `user_enroll_course` with genuine values.
		$factories['create_academy_enrollment'] = function ( array $args ) {
			if ( ! class_exists( '\Academy\Helper' ) ) {
				throw new \RuntimeException( 'create_academy_enrollment requires Academy to be active.' );
			}

			$course_id = wp_insert_post(
				[
					'post_type'   => 'academy_courses',
					'post_title'  => $args['course_title'] ?? 'Zaplane Recipe Course',
					'post_status' => 'publish',
				],
				true
			);
			if ( is_wp_error( $course_id ) ) {
				throw new \RuntimeException( 'course create failed: ' . $course_id->get_error_message() );
			}

			$suffix  = substr( md5( uniqid( 'academy', true ) ), 0, 8 );
			$user_id = wp_insert_user(
				[
					'user_login' => "academy_{$suffix}",
					'user_email' => "academy_{$suffix}@example.test",
					'user_pass'  => wp_generate_password( 16 ),
				]
			);
			if ( is_wp_error( $user_id ) ) {
				throw new \RuntimeException( 'user create failed: ' . $user_id->get_error_message() );
			}

			$enroll_id = \Academy\Helper::do_enroll( (int) $course_id, (int) $user_id );
			if ( ! $enroll_id || is_wp_error( $enroll_id ) ) {
				throw new \RuntimeException( 'enrollment failed' );
			}

			return [
				'course_id' => (int) $course_id,
				'user_id'   => (int) $user_id,
				'enroll_id' => (int) $enroll_id,
			];
		};

		return $factories;
	}
);
