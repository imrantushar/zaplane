<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Models\Run;

class Pause extends IntegrationBase {

	/* ---------------------------------------------------------
	 * Identity
	 * --------------------------------------------------------- */

	public static function get_slug(): string { return 'pause'; }
	public static function get_name(): string { return 'Pause'; }
	public static function get_icon(): string { return 'pause'; }
	public static function get_category(): string { return 'tool'; }

	/* ---------------------------------------------------------
	 * Actions
	 * --------------------------------------------------------- */

	public static function get_actions(): array {
		return [
			'pause' => [ 'label' => 'Pause Execution' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		return [
			[
				'key'      => 'delay',
				'label'    => 'Pause Duration (seconds)',
				'type'     => 'number',
				'required' => true,
				'default'  => 0,
			],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$delay  = (int) ( $node['data']['config']['delay'] ?? $node['config']['delay'] ?? 0 );
		$run_id = $input['_run_id'] ?? 0;

		if ( $run_id ) {
			$run = Run::find( $run_id );
			if ( $run ) {
				$run->status    = 'paused';
				$run->resume_at = date( 'Y-m-d H:i:s', time() + $delay );
				$run->save();
			}
		}

		return [
			'port' => '__halt__',
			'data' => [],
		];
	}
}
