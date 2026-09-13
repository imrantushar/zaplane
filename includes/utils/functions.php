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
