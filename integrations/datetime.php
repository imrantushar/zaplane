<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DateTime_Tool extends IntegrationBase {

	private const UNITS = [ 'seconds', 'minutes', 'hours', 'days', 'weeks', 'months', 'years' ];

	public static function get_slug(): string {
		return 'datetime';
	}

	public static function get_name(): string {
		return 'Date / Time';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_icon(): string {
		return 'datetime.svg';
	}

	public static function get_actions(): array {
		return [
			'now'    => [ 'label' => 'Current Date/Time' ],
			'format' => [ 'label' => 'Format Date' ],
			'modify' => [ 'label' => 'Add / Subtract' ],
			'diff'   => [ 'label' => 'Difference' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$format = [
			'key' => 'format',
			'label' => 'Output format (PHP date)',
			'type' => 'text',
			'default' => 'Y-m-d H:i:s'
		];
		$units  = array_map( fn( $u ) => [
			'value' => $u,
			'label' => ucfirst( $u )
		], self::UNITS );

		switch ( $action ) {
			case 'now':
				return [ $format ];
			case 'format':
				return [
					[
						'key' => 'input',
						'label' => 'Date input',
						'type' => 'expression',
						'required' => true,
						'help' => 'Any parseable date, e.g. 2026-07-05 or {{ order_date }}.'
					],
					$format,
				];
			case 'modify':
				return [
					[
						'key' => 'input',
						'label' => 'Date input',
						'type' => 'expression',
						'default' => 'now'
					],
					[
						'key' => 'operation',
						'label' => 'Operation',
						'type' => 'select',
						'default' => 'add',
						'options' => [
							[
								'value' => 'add',
								'label' => 'Add'
							],
							[
								'value' => 'subtract',
								'label' => 'Subtract'
							]
						]
					],
					[
						'key' => 'amount',
						'label' => 'Amount',
						'type' => 'number',
						'required' => true
					],
					[
						'key' => 'unit',
						'label' => 'Unit',
						'type' => 'select',
						'default' => 'days',
						'options' => $units
					],
					$format,
				];
			case 'diff':
				return [
					[
						'key' => 'start',
						'label' => 'Start date',
						'type' => 'expression',
						'required' => true
					],
					[
						'key' => 'end',
						'label' => 'End date',
						'type' => 'expression',
						'required' => true
					],
					[
						'key' => 'unit',
						'label' => 'Difference in',
						'type' => 'select',
						'default' => 'days',
						'options' => $units
					],
				];
		}//end switch
		return [];
	}

	public static function get_action_sample_output( string $action ): array {
		if ( 'diff' === $action ) {
			return [ 'difference' => 5, 'unit' => 'days', 'seconds' => 432000 ];
		}
		return [
			'formatted' => '2026-07-05 12:00:00',
			'timestamp' => 1783252800,
			'iso8601'   => '2026-07-05T12:00:00+00:00',
		];
	}

	public static function execute_node( array $node, array $input ): array {
		$action = $node['data']['event'] ?? 'now';
		$config = $node['data']['config'] ?? [];

		switch ( $action ) {
			case 'format':
				$data = self::do_format( $config );
				break;
			case 'modify':
				$data = self::do_modify( $config );
				break;
			case 'diff':
				$data = self::do_diff( $config );
				break;
			case 'now':
			default:
				$data = self::do_now( $config );
				break;
		}

		return [
			'port' => 'main',
			'data' => $data
		];
	}

	protected static function do_now( array $config ): array {
		return self::describe( self::make( 'now' ), (string) ( $config['format'] ?? 'Y-m-d H:i:s' ) );
	}

	protected static function do_format( array $config ): array {
		return self::describe( self::make( (string) ( $config['input'] ?? 'now' ) ), (string) ( $config['format'] ?? 'Y-m-d H:i:s' ) );
	}

	protected static function do_modify( array $config ): array {
		$dt     = self::make( (string) ( $config['input'] ?? 'now' ) );
		$amount = (int) ( $config['amount'] ?? 0 );
		$unit   = in_array( $config['unit'] ?? '', self::UNITS, true ) ? $config['unit'] : 'days';
		$sign   = 'subtract' === ( $config['operation'] ?? 'add' ) ? '-' : '+';

		$dt->modify( $sign . $amount . ' ' . $unit );

		return self::describe( $dt, (string) ( $config['format'] ?? 'Y-m-d H:i:s' ) );
	}

	protected static function do_diff( array $config ): array {
		$start = self::make( (string) ( $config['start'] ?? 'now' ) );
		$end   = self::make( (string) ( $config['end'] ?? 'now' ) );
		$unit  = in_array( $config['unit'] ?? '', self::UNITS, true ) ? $config['unit'] : 'days';

		$seconds = $end->getTimestamp() - $start->getTimestamp();
		$divisor = [
			'seconds' => 1,
			'minutes' => MINUTE_IN_SECONDS,
			'hours'   => HOUR_IN_SECONDS,
			'days'    => DAY_IN_SECONDS,
			'weeks'   => WEEK_IN_SECONDS,
			'months'  => MONTH_IN_SECONDS,
			'years'   => YEAR_IN_SECONDS,
		][ $unit ];

		return [
			'difference' => round( $seconds / $divisor, 2 ),
			'unit'       => $unit,
			'seconds'    => $seconds,
		];
	}

	protected static function make( string $input ): \DateTime {
		$tz = wp_timezone();
		try {
			return new \DateTime( '' !== trim( $input ) ? $input : 'now', $tz );
		} catch ( \Exception $e ) {
			return new \DateTime( 'now', $tz );
		}
	}

	protected static function describe( \DateTime $dt, string $format ): array {
		return [
			'formatted' => $dt->format( '' !== $format ? $format : 'Y-m-d H:i:s' ),
			'timestamp' => $dt->getTimestamp(),
			'iso8601'   => $dt->format( 'c' ),
		];
	}
}
