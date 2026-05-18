<?php

use Zaplane\Framework\Core\Automation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'zaplane_run_workflow' ) ) {

	/**
	 * Programmatically start a workflow run with custom data.
	 *
	 * @param int   $workflow_id  The workflow to run.
	 * @param array $data         Custom trigger payload passed to every node.
	 * @return int|false          The Run ID on success, false on failure.
	 */
	function zaplane_run_workflow( int $workflow_id, array $data = [] ): int|false {
		$automation = Automation::get_instance();
		if ( ! $automation ) {
			return false;
		}
		return $automation->run_workflow( $workflow_id, $data );
	}
}
