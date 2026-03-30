<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\Scheduler;

class Delay extends IntegrationBase {

	public static function get_slug(): string {
		return 'delay';
	}

	public static function get_name(): string {
		return 'Delay';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_icon(): string {
		return 'delay';
	}

	public static function get_actions(): array {
		return [
			'wait' => [ 'label' => 'Wait / Delay' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		return [
			[
				'key' => 'unit',
				'label' => 'Delay Unit',
				'type' => 'select',
				'options' => [
					[
						'value' => 'seconds',
						'label' => 'Seconds'
					],
					[
						'value' => 'days',
						'label' => 'Days'
					]
				],
				'default' => 'seconds',
				'required' => true
			],
			[
				'key' => 'amount',
				'label' => 'Amount',
				'type' => 'number',
				'required' => true
			]
		];
	}

	public static function execute_node( array $node, array $input ): array {

		// Fallback to interpreting 'seconds' config for backwards compatibility with old pipelines if 'amount' is missing
		$amount = (int) ( $node['data']['config']['amount'] ?? $node['data']['config']['seconds'] ?? 0 );
		$unit   = $node['data']['config']['unit'] ?? 'seconds';

		$delay_seconds = $amount;
		if ( 'days' === $unit ) {
			$delay_seconds = $amount * 86400; // 24 hours * 60 minutes * 60 seconds
		}

		if ( isset( $node['_run_id'], $node['_node_run_id'] ) ) {
			Scheduler::enqueue(
				time() + $delay_seconds,
				(int) $node['_run_id'],
				(int) $node['_node_run_id'],
				(int) $node['id'],
				$input
			);
		}

		return [
			'port' => '__halt__',
			'status' => 'delayed',
			'data' => []
		];
	}
}
