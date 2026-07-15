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

	private const UNIT_SECONDS = [
		'seconds' => 1,
		'minutes' => 60,
		'hours'   => 3600,
		'days'    => 86400,
		'weeks'   => 604800,
	];

	public static function get_action_config_schema( string $action ): array {
		return [
			[
				'key'     => 'delay_type',
				'label'   => 'Delay Type',
				'type'    => 'select',
				'default' => 'for',
				'options' => [
					[
						'value' => 'for',
						'label' => 'Wait for a duration'
					],
					[
						'value' => 'until',
						'label' => 'Wait until a specific date/time'
					],
				],
			],
			[
				'key'        => 'amount',
				'label'      => 'Amount',
				'type'       => 'number',
				'required'   => false,
				'depends_on' => [ 'delay_type' => 'for' ],
			],
			[
				'key'        => 'unit',
				'label'      => 'Unit',
				'type'       => 'select',
				'default'    => 'minutes',
				'depends_on' => [ 'delay_type' => 'for' ],
				'options'    => [
					[
						'value' => 'seconds',
						'label' => 'Seconds'
					],
					[
						'value' => 'minutes',
						'label' => 'Minutes'
					],
					[
						'value' => 'hours',
						'label' => 'Hours'
					],
					[
						'value' => 'days',
						'label' => 'Days'
					],
					[
						'value' => 'weeks',
						'label' => 'Weeks'
					],
				],
			],
			[
				'key'         => 'until',
				'label'       => 'Wait until',
				'type'        => 'expression',
				'subtype'     => 'datetime',
				'required'    => false,
				'depends_on'  => [ 'delay_type' => 'until' ],
				'placeholder' => '2026-07-15 09:00 or {{ trigger.date }}',
				'help'        => 'A date/time in the site timezone, a Unix timestamp, or a relative string like "next monday 9am". Supports variables.',
			],
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$config    = $node['data']['config'] ?? [];
		$resume_at = self::resume_timestamp( $config );

		// Never schedule in the past — resume on the next tick instead.
		$resume_at = max( $resume_at, time() );

		if ( isset( $node['_run_id'], $node['_node_run_id'] ) ) {
			Scheduler::enqueue(
				$resume_at,
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

	/**
	 * Resolve the absolute epoch second at which the run should resume.
	 *
	 * @param array<string,mixed> $config
	 */
	protected static function resume_timestamp( array $config ): int {
		if ( 'until' === ( $config['delay_type'] ?? 'for' ) ) {
			return self::parse_until( (string) ( $config['until'] ?? '' ) );
		}

		// 'seconds' fallback keeps old pipelines (pre-`amount`) working.
		$amount = (int) ( $config['amount'] ?? $config['seconds'] ?? 0 );
		$unit   = $config['unit'] ?? 'seconds';
		$mult   = self::UNIT_SECONDS[ $unit ] ?? 1;

		return time() + max( 0, $amount ) * $mult;
	}

	/**
	 * Parse a "wait until" value into an epoch second. Accepts a Unix timestamp,
	 * an absolute date/time (interpreted in the site timezone), or a relative
	 * string ("next monday 9am"). Falls back to now on failure.
	 */
	protected static function parse_until( string $value ): int {
		$value = trim( $value );
		if ( '' === $value ) {
			return time();
		}

		if ( ctype_digit( $value ) ) {
			return (int) $value;
		}

		$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		try {
			return ( new \DateTime( $value, $tz ) )->getTimestamp();
		} catch ( \Throwable $e ) {
			$ts = strtotime( $value );
			return $ts ?: time();
		}
	}
}
