<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Scheduler {
	public static function enqueue( int $timestamp, int $run_id, int $node_run_id, int $node_key, array $output ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			error_log( 'Zaplane: Action Scheduler is not active. Delay will not work.' );
			return;
		}

		as_schedule_single_action(
			$timestamp,
			'zaplane_resume_delayed_run',
			[
				'run_id'      => $run_id,
				'node_run_id' => $node_run_id,
				'node_key'    => $node_key,
				'output'      => $output,
			],
			'zaplane_delays'
		);
	}
}
