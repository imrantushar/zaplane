<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Delay extends IntegrationBase {

	/* ---------------------------------------------------------
	 * Identity
	 * --------------------------------------------------------- */

	public static function get_slug(): string { return 'delay'; }
	public static function get_name(): string { return 'Delay'; }
	public static function get_icon(): string { return 'delay'; }
	public static function get_category(): string { return 'tool'; }

	/* ---------------------------------------------------------
	 * Actions
	 * --------------------------------------------------------- */

	public static function get_actions(): array {
		return [
			'wait' => [ 'label' => 'Wait / Delay' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		return [
			[
				'key'      => 'seconds',
				'label'    => 'Wait Seconds',
				'type'     => 'number',
				'required' => true,
			],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$seconds = (int) ( $node['data']['config']['seconds'] ?? 0 );

		if ( $seconds <= 0 ) {
			return [ 'port' => 'main', 'data' => $input ];
		}

		// Schedule the continuation via Action Scheduler
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time() + $seconds,
				'zaplane_resume_delayed_node',
				[
					'workflow_id' => $node['workflow_id'] ?? 0,
					'node_id'     => $node['id'] ?? '',
					'input'       => $input,
				],
				'zaplane'
			);
		}

		return [
			'port' => '__halt__',
			'data' => [],
		];
	}
}
