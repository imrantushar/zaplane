<?php
/**
 * Recipe data factories for the woocommerce integration.
 * Auto-scaffolded by `wp zaplane recipe generate`. Fill in each stub's body.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'zaplane_recipe_factories',
	function ( array $factories ) {
		$factories['create_woocommerce_restore_order'] = function ( array $args ) {
			// TODO: create the real data this trigger needs (posts, users, orders, …),
			// then delete the throw below and return the values for {{arg0}}..{{argN}}.
			throw new \Zaplane\Testing\RecipeSkip( "Stub factory 'create_woocommerce_restore_order' — implement it in recipes-test/woocommerce/factories.php" );

			// phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable
			return [
				'arg0' => null, // TODO: real value the trigger expects at hook arg 0
				'arg1' => null, // TODO: real value the trigger expects at hook arg 1
			];
		};
		return $factories;
	}
);

add_filter(
	'zaplane_recipe_factories',
	function ( array $factories ) {
		$factories['create_woocommerce_order_status_changed'] = function ( array $args ) {
			// TODO: create the real data this trigger needs (posts, users, orders, …),
			// then delete the throw below and return the values for {{arg0}}..{{argN}}.
			throw new \Zaplane\Testing\RecipeSkip( "Stub factory 'create_woocommerce_order_status_changed' — implement it in recipes-test/woocommerce/factories.php" );

			// phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable
			return [
				'arg0' => null, // TODO: real value the trigger expects at hook arg 0
				'arg1' => null, // TODO: real value the trigger expects at hook arg 1
				'arg2' => null, // TODO: real value the trigger expects at hook arg 2
			];
		};
		return $factories;
	}
);

add_filter(
	'zaplane_recipe_factories',
	function ( array $factories ) {
		$factories['create_woocommerce_create_customer'] = function ( array $args ) {
			// TODO: create the real data this trigger needs (posts, users, orders, …),
			// then delete the throw below and return the values for {{arg0}}..{{argN}}.
			throw new \Zaplane\Testing\RecipeSkip( "Stub factory 'create_woocommerce_create_customer' — implement it in recipes-test/woocommerce/factories.php" );

			// phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable
			return [
				'arg0' => null, // TODO: real value the trigger expects at hook arg 0
				'arg1' => null, // TODO: real value the trigger expects at hook arg 1
				'arg2' => null, // TODO: real value the trigger expects at hook arg 2
			];
		};
		return $factories;
	}
);

add_filter(
	'zaplane_recipe_factories',
	function ( array $factories ) {
		$factories['create_woocommerce_delete_customer'] = function ( array $args ) {
			// TODO: create the real data this trigger needs (posts, users, orders, …),
			// then delete the throw below and return the values for {{arg0}}..{{argN}}.
			throw new \Zaplane\Testing\RecipeSkip( "Stub factory 'create_woocommerce_delete_customer' — implement it in recipes-test/woocommerce/factories.php" );

			// phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable
			return [
				'arg0' => null, // TODO: real value the trigger expects at hook arg 0
			];
		};
		return $factories;
	}
);

add_filter(
	'zaplane_recipe_factories',
	function ( array $factories ) {
		$factories['create_woocommerce_delete_product'] = function ( array $args ) {
			// TODO: create the real data this trigger needs (posts, users, orders, …),
			// then delete the throw below and return the values for {{arg0}}..{{argN}}.
			throw new \Zaplane\Testing\RecipeSkip( "Stub factory 'create_woocommerce_delete_product' — implement it in recipes-test/woocommerce/factories.php" );

			// phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable
			return [
				'arg0' => null, // TODO: real value the trigger expects at hook arg 0
				'arg1' => null, // TODO: real value the trigger expects at hook arg 1
				'arg2' => null, // TODO: real value the trigger expects at hook arg 2
			];
		};
		return $factories;
	}
);

add_filter(
	'zaplane_recipe_factories',
	function ( array $factories ) {
		$factories['create_woocommerce_restore_product'] = function ( array $args ) {
			// TODO: create the real data this trigger needs (posts, users, orders, …),
			// then delete the throw below and return the values for {{arg0}}..{{argN}}.
			throw new \Zaplane\Testing\RecipeSkip( "Stub factory 'create_woocommerce_restore_product' — implement it in recipes-test/woocommerce/factories.php" );

			// phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable
			return [
				'arg0' => null, // TODO: real value the trigger expects at hook arg 0
				'arg1' => null, // TODO: real value the trigger expects at hook arg 1
				'arg2' => null, // TODO: real value the trigger expects at hook arg 2
			];
		};
		return $factories;
	}
);

add_filter(
	'zaplane_recipe_factories',
	function ( array $factories ) {
		$factories['create_woocommerce_product_status_updated'] = function ( array $args ) {
			// TODO: create the real data this trigger needs (posts, users, orders, …),
			// then delete the throw below and return the values for {{arg0}}..{{argN}}.
			throw new \Zaplane\Testing\RecipeSkip( "Stub factory 'create_woocommerce_product_status_updated' — implement it in recipes-test/woocommerce/factories.php" );

			// phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable
			return [
				'arg0' => null, // TODO: real value the trigger expects at hook arg 0
				'arg1' => null, // TODO: real value the trigger expects at hook arg 1
			];
		};
		return $factories;
	}
);

add_filter(
	'zaplane_recipe_factories',
	function ( array $factories ) {
		$factories['create_woocommerce_product_status_changed'] = function ( array $args ) {
			// TODO: create the real data this trigger needs (posts, users, orders, …),
			// then delete the throw below and return the values for {{arg0}}..{{argN}}.
			throw new \Zaplane\Testing\RecipeSkip( "Stub factory 'create_woocommerce_product_status_changed' — implement it in recipes-test/woocommerce/factories.php" );

			// phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable
			return [
				'arg0' => null, // TODO: real value the trigger expects at hook arg 0
				'arg1' => null, // TODO: real value the trigger expects at hook arg 1
				'arg2' => null, // TODO: real value the trigger expects at hook arg 2
			];
		};
		return $factories;
	}
);
