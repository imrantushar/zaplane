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
			'now'      => [ 'label' => 'Current Date/Time' ],
			'format'   => [ 'label' => 'Format Date' ],
			'modify'   => [ 'label' => 'Add / Subtract' ],
			'diff'     => [ 'label' => 'Difference' ],
			'parse'    => [ 'label' => 'Parse (extract parts)' ],
			'humanize' => [ 'label' => 'Humanize (relative)' ],
			'boundary' => [ 'label' => 'Start / End of…' ],
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

		$input_format = [
			'key'         => 'input_format',
			'label'       => 'Input format (optional)',
			'type'        => 'text',
			'required'    => false,
			'placeholder' => 'e.g. d/m/Y H:i',
			'help'        => 'Set this when the input is not a standard date, so it can be parsed correctly.',
		];
		$timezone = [
			'key'         => 'timezone',
			'label'       => 'Timezone (optional)',
			'type'        => 'expression',
			'required'    => false,
			'placeholder' => 'e.g. America/New_York',
			'help'        => 'Convert the result to this timezone. Defaults to the site timezone.',
		];

		switch ( $action ) {
			case 'now':
				return [ $format, $timezone ];
			case 'format':
				return [
					[
						'key' => 'input',
						'label' => 'Date input',
						'type' => 'expression',
						'required' => true,
						'help' => 'Any parseable date, e.g. 2026-07-05 or {{ order_date }}.'
					],
					$input_format,
					$timezone,
					$format,
				];
			case 'parse':
				return [
					[
						'key' => 'input',
						'label' => 'Date input',
						'type' => 'expression',
						'required' => true
					],
					$input_format,
					$timezone,
				];
			case 'humanize':
				return [
					[
						'key' => 'input',
						'label' => 'Date input',
						'type' => 'expression',
						'required' => true,
						'help' => 'Returns "2 hours ago" / "in 3 days" relative to now.'
					],
					$input_format,
				];
			case 'boundary':
				return [
					[
						'key' => 'input',
						'label' => 'Date input',
						'type' => 'expression',
						'default' => 'now'
					],
					[
						'key' => 'boundary',
						'label' => 'Boundary',
						'type' => 'select',
						'default' => 'start',
						'options' => [
							[
								'value' => 'start',
								'label' => 'Start of'
							],
							[
								'value' => 'end',
								'label' => 'End of'
							]
						],
					],
					[
						'key' => 'unit',
						'label' => 'Period',
						'type' => 'select',
						'default' => 'day',
						'options' => [
							[
								'value' => 'day',
								'label' => 'Day'
							],
							[
								'value' => 'week',
								'label' => 'Week'
							],
							[
								'value' => 'month',
								'label' => 'Month'
							],
							[
								'value' => 'year',
								'label' => 'Year'
							],
						],
					],
					$timezone,
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
			return [
				'difference' => 5,
				'unit' => 'days',
				'seconds' => 432000
			];
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
			case 'parse':
				$data = self::do_parse( $config );
				break;
			case 'humanize':
				$data = self::do_humanize( $config );
				break;
			case 'boundary':
				$data = self::do_boundary( $config );
				break;
			case 'now':
			default:
				$data = self::do_now( $config );
				break;
		}//end switch

		return [
			'port' => 'main',
			'data' => $data
		];
	}

	protected static function do_now( array $config ): array {
		return self::describe( self::make( 'now', $config ), (string) ( $config['format'] ?? 'Y-m-d H:i:s' ) );
	}

	protected static function do_format( array $config ): array {
		return self::describe( self::make( (string) ( $config['input'] ?? 'now' ), $config ), (string) ( $config['format'] ?? 'Y-m-d H:i:s' ) );
	}

	protected static function do_parse( array $config ): array {
		$dt = self::make( (string) ( $config['input'] ?? 'now' ), $config );
		return [
			'year'        => (int) $dt->format( 'Y' ),
			'month'       => (int) $dt->format( 'n' ),
			'month_name'  => $dt->format( 'F' ),
			'day'         => (int) $dt->format( 'j' ),
			'hour'        => (int) $dt->format( 'G' ),
			'minute'      => (int) $dt->format( 'i' ),
			'second'      => (int) $dt->format( 's' ),
			'weekday'     => $dt->format( 'l' ),
			'weekday_num' => (int) $dt->format( 'N' ),
			'week'        => (int) $dt->format( 'W' ),
			'day_of_year' => (int) $dt->format( 'z' ) + 1,
			'quarter'     => (int) ceil( ( (int) $dt->format( 'n' ) ) / 3 ),
			'am_pm'       => $dt->format( 'A' ),
			'timestamp'   => $dt->getTimestamp(),
			'iso8601'     => $dt->format( 'c' ),
		];
	}

	protected static function do_humanize( array $config ): array {
		$dt   = self::make( (string) ( $config['input'] ?? 'now' ), $config );
		$now  = time();
		$ts   = $dt->getTimestamp();
		$diff = human_time_diff( $ts, $now );
		$human = ( $ts <= $now )
			/* translators: %s is a human-readable time span, e.g. "2 hours". */
			? sprintf( __( '%s ago', 'zaplane' ), $diff )
			: sprintf( __( 'in %s', 'zaplane' ), $diff );

		return [
			'human'     => $human,
			'timestamp' => $ts,
			'iso8601'   => $dt->format( 'c' ),
		];
	}

	protected static function do_boundary( array $config ): array {
		$dt   = self::make( (string) ( $config['input'] ?? 'now' ), $config );
		$edge = 'end' === ( $config['boundary'] ?? 'start' ) ? 'end' : 'start';
		$unit = $config['unit'] ?? 'day';

		switch ( $unit ) {
			case 'week':
				// ISO week: Monday start.
				$dt->modify( 'start' === $edge ? 'monday this week' : 'sunday this week' );
				break;
			case 'month':
				$dt->modify( 'start' === $edge ? 'first day of this month' : 'last day of this month' );
				break;
			case 'year':
				$dt->setDate( (int) $dt->format( 'Y' ), 'start' === $edge ? 1 : 12, 'start' === $edge ? 1 : 31 );
				break;
		}

		$dt->setTime( 'start' === $edge ? 0 : 23, 'start' === $edge ? 0 : 59, 'start' === $edge ? 0 : 59 );

		return self::describe( $dt, (string) ( $config['format'] ?? 'Y-m-d H:i:s' ) );
	}

	protected static function do_modify( array $config ): array {
		$dt     = self::make( (string) ( $config['input'] ?? 'now' ), $config );
		$amount = (int) ( $config['amount'] ?? 0 );
		$unit   = in_array( $config['unit'] ?? '', self::UNITS, true ) ? $config['unit'] : 'days';
		$sign   = 'subtract' === ( $config['operation'] ?? 'add' ) ? '-' : '+';

		$dt->modify( $sign . $amount . ' ' . $unit );

		return self::describe( $dt, (string) ( $config['format'] ?? 'Y-m-d H:i:s' ) );
	}

	protected static function do_diff( array $config ): array {
		$start = self::make( (string) ( $config['start'] ?? 'now' ), $config );
		$end   = self::make( (string) ( $config['end'] ?? 'now' ), $config );
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

	/**
	 * Build a DateTime from an input, optionally parsed via an explicit input
	 * format and/or converted to a target timezone.
	 *
	 * @param array<string,mixed> $config
	 */
	protected static function make( string $input, array $config = [] ): \DateTime {
		$tz          = self::resolve_timezone( (string) ( $config['timezone'] ?? '' ) );
		$input       = '' !== trim( $input ) ? $input : 'now';
		$input_fmt   = trim( (string) ( $config['input_format'] ?? '' ) );

		$dt = false;
		if ( '' !== $input_fmt && 'now' !== $input ) {
			$dt = \DateTime::createFromFormat( $input_fmt, $input, $tz );
		}

		if ( ! $dt instanceof \DateTime ) {
			try {
				$dt = new \DateTime( $input, $tz );
			} catch ( \Exception $e ) {
				$dt = new \DateTime( 'now', $tz );
			}
		}

		$dt->setTimezone( $tz );
		return $dt;
	}

	protected static function resolve_timezone( string $tz ): \DateTimeZone {
		$tz = trim( $tz );
		if ( '' !== $tz ) {
			try {
				return new \DateTimeZone( $tz );
			} catch ( \Exception $e ) {
				// fall through to site timezone
			}
		}
		return wp_timezone();
	}

	protected static function describe( \DateTime $dt, string $format ): array {
		return [
			'formatted' => $dt->format( '' !== $format ? $format : 'Y-m-d H:i:s' ),
			'timestamp' => $dt->getTimestamp(),
			'iso8601'   => $dt->format( 'c' ),
		];
	}
}
