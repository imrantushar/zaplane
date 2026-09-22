<?php

use Zaplane\Framework\Core\Automation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'zaplane_register_gemcrm_abandoned_cart_addon' ) ) {
	function zaplane_register_gemcrm_abandoned_cart_addon( callable $register ): void {
		if ( class_exists( 'GemCrm\Addons\AbandonedCart\Bootstrap' ) ) {
			$register( 'gemcrm-abandoned-cart', \GemCrm\Addons\AbandonedCart\Bootstrap::class );
		}
	}
	add_action( 'gemcrm/addon/register', 'zaplane_register_gemcrm_abandoned_cart_addon' );
}

if ( ! function_exists( 'zaplane_run_workflow' ) ) {

	/**
	 * Programmatically start a workflow run with custom data.
	 *
	 * @param int             $workflow_id     The workflow to run.
	 * @param array           $data            Custom trigger payload passed to every node.
	 * @param int|string|null $trigger_node_id The trigger to start from, for a workflow with
	 *                                         several. Defaults to its Manual trigger, or
	 *                                         its first trigger when it has no Manual one.
	 * @return int|false The Run ID on success, false on failure.
	 */
	function zaplane_run_workflow( int $workflow_id, array $data = [], $trigger_node_id = null ) {
		$automation = Automation::get_instance();
		if ( ! $automation ) {
			return false;
		}
		return $automation->run_workflow( $workflow_id, $data, $trigger_node_id );
	}
}

if ( ! function_exists( 'zaplane_register_recipe' ) ) {

	/**
	 * Adds a recipe to Zaplane's Recipes page.
	 *
	 * Name each step by its app and event and give its settings; Zaplane lays the
	 * steps out, connects them, and builds the setup people go through to use it.
	 * A recipe with `workflows` instead of `steps` sets up several workflows in a
	 * folder. See docs/recipes/registering-recipes.md.
	 *
	 *     zaplane_register_recipe( 'thank-new-customers', [
	 *         'title' => 'Thank new customers',
	 *         'steps' => [
	 *             [ 'trigger' => 'storeengine.product_purchased' ],
	 *             [ 'action' => 'gemcrm.send_email', 'config' => [ ... ] ],
	 *         ],
	 *     ] );
	 *
	 * @param string              $slug   Names the recipe for good. Registering the same slug again replaces it.
	 * @param array<string,mixed> $recipe The recipe.
	 */
	function zaplane_register_recipe( string $slug, array $recipe ): void {
		$registry = \Zaplane\Recipes\Registry::instance();

		if ( $registry->collected() ) {
			$registry->add( $slug, $recipe );
			return;
		}

		add_action(
			\Zaplane\Recipes\Registry::HOOK,
			static function ( $recipes ) use ( $slug, $recipe ) {
				$recipes->add( $slug, $recipe );
			}
		);
	}
}
