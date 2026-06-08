<?php
/**
 * Named data factories for recipes.
 *
 * A recipe's optional `setup.factory` seeds real data before the trigger/action
 * fires and returns a flat array of variables (e.g. [ 'order_id' => 42 ]) that
 * the runner interpolates into the recipe's `input`/`config` via {{var}} tokens.
 *
 * Register additional factories with the `zaplane_recipe_factories` filter:
 *   add_filter( 'zaplane_recipe_factories', function ( $f ) {
 *       $f['create_foo'] = fn( array $args ) => [ 'foo_id' => ... ];
 *       return $f;
 *   } );
 *
 * @package Zaplane
 */

namespace Zaplane\Testing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RecipeFactories {

	/**
	 * Run a named factory and return its variable map.
	 *
	 * @throws \RuntimeException When the factory is unknown or a dependency is missing.
	 */
	public static function run( string $name, array $args = [] ): array {
		$factories = self::all();

		if ( ! isset( $factories[ $name ] ) ) {
			throw new \RuntimeException( "Unknown recipe factory: {$name}" );
		}

		$vars = call_user_func( $factories[ $name ], $args );

		return is_array( $vars ) ? $vars : [];
	}

	public static function has( string $name ): bool {
		return isset( self::all()[ $name ] );
	}

	/** @return array<string, callable> */
	public static function all(): array {
		$factories = [
			'create_post'     => [ self::class, 'create_post' ],
			'create_user'     => [ self::class, 'create_user' ],
			'create_wc_order' => [ self::class, 'create_wc_order' ],
		];

		return apply_filters( 'zaplane_recipe_factories', $factories );
	}

	public static function create_post( array $args ): array {
		$post_id = wp_insert_post(
			[
				'post_title'   => $args['title'] ?? 'Zaplane Recipe Post',
				'post_content' => $args['content'] ?? 'Created by a Zaplane recipe.',
				'post_status'  => $args['status'] ?? 'publish',
				'post_type'    => $args['type'] ?? 'post',
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			throw new \RuntimeException( 'create_post failed: ' . $post_id->get_error_message() );
		}

		// `post` is the WP_Post object, for recipes that pass it as a hook arg
		// (E2E firing of `publish_post` => [ $post_id, $post ]).
		return [
			'post_id' => (int) $post_id,
			'post'    => get_post( (int) $post_id ),
		];
	}

	public static function create_user( array $args ): array {
		$suffix = substr( md5( uniqid( 'zaplane', true ) ), 0, 8 );
		$login  = $args['login'] ?? "zaplane_{$suffix}";
		$email  = $args['email'] ?? "{$login}@example.test";

		$user_id = wp_insert_user(
			[
				'user_login' => $login,
				'user_email' => $email,
				'user_pass'  => wp_generate_password( 16 ),
				'role'       => $args['role'] ?? 'subscriber',
			]
		);

		if ( is_wp_error( $user_id ) ) {
			throw new \RuntimeException( 'create_user failed: ' . $user_id->get_error_message() );
		}

		return [
			'user_id'    => (int) $user_id,
			'user_login' => $login,
			'user_email' => $email,
		];
	}

	public static function create_wc_order( array $args ): array {
		if ( ! function_exists( 'wc_create_order' ) ) {
			throw new \RuntimeException( 'create_wc_order requires WooCommerce to be active.' );
		}

		$order = wc_create_order();

		if ( isset( $args['total'] ) ) {
			$order->set_total( (float) $args['total'] );
		}

		$status = $args['status'] ?? 'processing';
		$order->set_status( $status );
		$order->save();

		return [
			'order_id' => (int) $order->get_id(),
			'total'    => (float) $order->get_total(),
			'status'   => $order->get_status(),
			// The full WC_Order object, for recipes that must pass it as a hook arg
			// (e.g. E2E firing of `woocommerce_new_order` => [ $order_id, $order ]).
			'order'    => $order,
		];
	}
}
