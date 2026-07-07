<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Schedule extends IntegrationBase {

	public static function get_slug(): string {
		return 'schedule';
	}

	public static function get_name(): string {
		return 'Schedule';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_icon(): string {
		return 'schedule.svg';
	}

	public static function get_triggers(): array {
		return [
			'interval' => [
				'label' => 'On a Schedule',
				'hook'  => 'zaplane/schedule/tick',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'interval' !== $trigger ) {
			return [];
		}

		return [
			[
				'key'      => 'frequency',
				'label'    => 'Frequency',
				'type'     => 'select',
				'required' => true,
				'default'  => 'every_minutes',
				'options'  => [
					[
						'value' => 'every_minutes',
						'label' => 'Every X minutes'
					],
					[
						'value' => 'hourly',
						'label' => 'Hourly'
					],
					[
						'value' => 'daily',
						'label' => 'Daily at a time'
					],
				],
			],
			[
				'key'        => 'interval_minutes',
				'label'      => 'Every (minutes)',
				'type'       => 'number',
				'required'   => false,
				'default'    => 15,
				'depends_on' => [ 'frequency' => 'every_minutes' ],
			],
			[
				'key'        => 'time',
				'label'      => 'Time (HH:MM, site timezone)',
				'type'       => 'expression',
				'required'   => false,
				'placeholder' => '09:00',
				'depends_on' => [ 'frequency' => 'daily' ],
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		// Fired via run_workflow with a payload; just pass it through.
		$payload = $args[0] ?? [];
		return is_array( $payload ) && ! empty( $payload ) ? $payload : [
			'timestamp' => current_time( 'mysql' ),
			'unix'      => time(),
		];
	}

	public static function get_trigger_sample_output( string $trigger ): array {
		return [
			'timestamp' => current_time( 'mysql' ),
			'unix' => time()
		];
	}
}
