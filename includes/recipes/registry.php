<?php

namespace Zaplane\Recipes;

use Zaplane\Database\Seeders\RecipeSeeding;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The recipes Zaplane offers: the ones it ships, and any a plugin registers.
 *
 * Register a recipe on the `zaplane_register_recipes` action:
 *
 *     add_action( 'zaplane_register_recipes', function ( \Zaplane\Recipes\Registry $recipes ) {
 *         $recipes->add( 'thank-new-customers', [
 *             'title'       => 'Thank new customers',
 *             'description' => 'Emails a thank-you as soon as someone places an order.',
 *             'steps'       => [
 *                 [ 'trigger' => 'storeengine.product_purchased' ],
 *                 [
 *                     'action' => 'gemcrm.send_email',
 *                     'config' => [
 *                         'recipient_type' => 'custom',
 *                         'custom_email'   => '{{trigger.customer_email}}',
 *                         'subject'        => 'Thank you, {{trigger.first_name}}',
 *                         'content_source' => 'custom',
 *                         'body'           => '<p>Thanks for your order.</p>',
 *                     ],
 *                 ],
 *             ],
 *         ] );
 *     } );
 *
 * A recipe with `workflows` instead of `steps` is a group recipe: several
 * workflows, set up together in a folder. Both kinds get the same setup in the
 * dashboard, built from the recipe: its optional steps, the values it asks for,
 * and the connections its apps need. Recipes appear as soon as they are
 * registered; see docs/recipes/registering-recipes.md for every field.
 */
final class Registry {

	/** The action that collects registered recipes. */
	const HOOK = 'zaplane_register_recipes';

	/** A fingerprint of the recipes the recipes table was last filled from. */
	const HASH_OPTION = 'zaplane_recipe_registry_hash';

	private static ?self $instance = null;

	/** @var array<string,array<string,mixed>> Slug => recipe. */
	private array $recipes = [];

	private bool $collected = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers a recipe. Registering another under the same slug replaces it.
	 *
	 * @param array<string,mixed> $recipe See the class docblock.
	 * @throws \InvalidArgumentException When the slug is empty.
	 */
	public function add( string $slug, array $recipe ): self {
		$slug = sanitize_key( $slug );

		if ( '' === $slug ) {
			throw new \InvalidArgumentException( 'A recipe needs a slug.' );
		}

		$this->recipes[ $slug ] = $recipe;

		return $this;
	}

	public function remove( string $slug ): self {
		unset( $this->recipes[ sanitize_key( $slug ) ] );

		return $this;
	}

	/**
	 * Every registered recipe, slug => recipe. The first call collects them: the
	 * ones Zaplane ships, then the registration action.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function all(): array {
		if ( ! $this->collected ) {
			$this->collected = true;
			ShippedRecipes::register( $this );
			do_action( 'zaplane_register_recipes', $this );
		}

		return $this->recipes;
	}

	/**
	 * Whether the recipes were already collected this request, so a recipe added
	 * on the registration action from now on would be missed.
	 */
	public function collected(): bool {
		return $this->collected;
	}

	/**
	 * Saves the registered recipes to the recipes table when they changed since the
	 * last time, or always with $force. A recipe someone deleted stays deleted, and
	 * one they renamed keeps its name.
	 */
	public function sync( bool $force = false ): void {
		$recipes = $this->all();
		$version = defined( 'ZAPLANE_VERSION' ) ? ZAPLANE_VERSION : '';
		$hash    = md5( $version . wp_json_encode( $recipes ) );

		if ( ! $force && get_option( self::HASH_OPTION ) === $hash ) {
			return;
		}

		foreach ( $recipes as $slug => $recipe ) {
			try {
				RecipeSeeding::save( $slug, RecipeCompiler::record( $recipe ) );
			} catch ( \InvalidArgumentException $e ) {
				// One broken recipe mustn't keep the others out.
				_doing_it_wrong( __METHOD__, esc_html( sprintf( 'The recipe "%1$s" was not added: %2$s', $slug, $e->getMessage() ) ), '1.2.0' );
			}
		}

		update_option( self::HASH_OPTION, $hash, false );
	}

	/**
	 * Forgets every registration, so the next call to all() collects them again.
	 */
	public static function reset(): void {
		self::$instance = null;
	}
}
