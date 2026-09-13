<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\Expression;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Condition extends IntegrationBase {


	public static function get_slug(): string {
		return 'condition';
	}

	public static function get_name(): string {
		return 'Condition';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_icon(): string {
		return 'condition';
	}

	public static function get_actions(): array {
		return [
			'if' => [ 'label' => 'If Condition' ],
		];
	}



	public static function get_action_config_schema( string $action ): array {
		return [
			[
				'key'      => 'conditions',
				'label'    => 'Conditions',
				'type'     => 'condition_group',
				'required' => true,
				'help'     => 'Build complex conditions with AND/OR groups',
				'fields'   => [
					[
						'key'      => 'left',
						'label'    => 'Left Value',
						'type'     => 'expression',
						'required' => true,
					],
					[
						'key'      => 'operator',
						'label'    => 'Operator',
						'type'     => 'select',
						'options'  => [
							[
								'label' => 'Equals',
								'value' => '=='
							],
							[
								'label' => 'Not Equals',
								'value' => '!='
							],
							[
								'label' => 'Greater Than',
								'value' => '>'
							],
							[
								'label' => 'Less Than',
								'value' => '<'
							],
							[
								'label' => 'Greater or Equal',
								'value' => '>='
							],
							[
								'label' => 'Less or Equal',
								'value' => '<='
							],
							[
								'label' => 'Contains',
								'value' => 'contains'
							],
							[
								'label' => 'Not Contains',
								'value' => 'not_contains'
							],
							[
								'label' => 'Starts With',
								'value' => 'starts_with'
							],
							[
								'label' => 'Ends With',
								'value' => 'ends_with'
							],
							[
								'label' => 'Is Empty',
								'value' => 'is_empty'
							],
							[
								'label' => 'Is Not Empty',
								'value' => 'is_not_empty'
							],
							[
								'label' => 'Equals (ignore case)',
								'value' => 'equals_ci'
							],
							[
								'label' => 'Contains (ignore case)',
								'value' => 'contains_ci'
							],
							[
								'label' => 'Not Starts With',
								'value' => 'not_starts_with'
							],
							[
								'label' => 'Not Ends With',
								'value' => 'not_ends_with'
							],
							[
								'label' => 'Matches Regex',
								'value' => 'matches_regex'
							],
							[
								'label' => 'In List',
								'value' => 'in_list'
							],
							[
								'label' => 'Not In List',
								'value' => 'not_in_list'
							],
							[
								'label' => 'Is True',
								'value' => 'is_true'
							],
							[
								'label' => 'Is False',
								'value' => 'is_false'
							],
							[
								'label' => 'Between',
								'value' => 'between'
							],
							[
								'label' => 'Date Before',
								'value' => 'before'
							],
							[
								'label' => 'Date After',
								'value' => 'after'
							],
						],
						'required' => true,
					],
					[
						'key'      => 'right',
						'label'    => 'Right Value',
						'type'     => 'expression',
						'required' => true,
					],
				],
				'logic_options' => [ 'AND', 'OR' ],
			]
		];
	}

	public static function get_output_ports(): array {
		return [ 'true', 'false' ];
	}



	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];
		$rawConditions = $config['conditions'] ?? $input['conditions'] ?? [];
		$conditions = self::normalizeConditions( $rawConditions );
		$result = self::evaluate_condition_group( $conditions, $input );

		$directInput = array_filter( $input, fn( $k) => ! ctype_digit( (string) $k ), ARRAY_FILTER_USE_KEY );

		return [
			'port' => $result ? 'true' : 'false',
			'data' => $directInput,
		];
	}



	protected static function normalizeConditions( array $conditions ): array {

		if ( isset( $conditions['logic'] ) ) {
			return $conditions;
		}

		if ( empty( $conditions ) ) {
			return [
				'logic' => 'AND',
				'conditions' => []
			];
		}

		$groups = [];
		foreach ( $conditions as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			if ( isset( $group['left'] ) ) {
				$groups[] = $group;
				continue;
			}

			$groups[] = [
				'logic' => 'OR',
				'conditions' => $group,
			];
		}

		return [
			'logic' => 'AND',
			'conditions' => $groups,
		];
	}



	protected static function evaluate_condition_group( array $group, array $input ): bool {
		$logic = $group['logic'] ?? 'AND';
		$results = [];

		foreach ( $group['conditions'] ?? [] as $cond ) {
			if ( ! empty( $cond['conditions'] ) ) {
				$results[] = self::evaluate_condition_group( $cond, $input );
			} else {
				$left  = Expression::evaluate( $cond['left'] ?? '', $input );
				$right = Expression::evaluate( $cond['right'] ?? '', $input );
				$op    = $cond['operator'] ?? '==';

				$results[] = self::compare( $left, $right, $op );
			}
		}

		if ( 'AND' === $logic ) {
			return ! in_array( false, $results, true );
		} else {
			return in_array( true, $results, true );
		}
	}



	public static function compare( $left, $right, string $op ): bool {
		// Numeric strings are coerced to numbers before comparison so a user
		// who types "5" still equals an integer 5 — but we avoid PHP's loose
		// `==` which would treat "0e123" === "0e456" as equal (both are
		// scientific-notation zeros).
		if ( in_array( $op, [ '==', '!=' ], true ) && is_string( $left ) && is_string( $right )
			&& is_numeric( $left ) && is_numeric( $right ) ) {
			$left  = 0 + $left;
			$right = 0 + $right;
		}
		switch ( $op ) {
			case '==':
				return $left === $right;
			case '!=':
				return $left !== $right;
			case '<':
				return $left < $right;
			case '>':
				return $left > $right;
			case '<=':
				return $left <= $right;
			case '>=':
				return $left >= $right;
			case 'contains':
				return str_contains( (string) $left, (string) $right );
			case 'not_contains':
				return ! str_contains( (string) $left, (string) $right );
			case 'starts_with':
				return str_starts_with( (string) $left, (string) $right );
			case 'ends_with':
				return str_ends_with( (string) $left, (string) $right );
			case 'is_empty':
				return empty( $left );
			case 'is_not_empty':
				return ! empty( $left );
			case 'equals_ci':
				return 0 === strcasecmp( (string) $left, (string) $right );
			case 'contains_ci':
				return false !== stripos( (string) $left, (string) $right );
			case 'not_starts_with':
				return ! str_starts_with( (string) $left, (string) $right );
			case 'not_ends_with':
				return ! str_ends_with( (string) $left, (string) $right );
			case 'matches_regex':
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a bad user regex must not fatal the run.
				return '' !== (string) $right && 1 === @preg_match( (string) $right, (string) $left );
			case 'in_list':
				return in_array( (string) $left, self::list_values( $right ), true );
			case 'not_in_list':
				return ! in_array( (string) $left, self::list_values( $right ), true );
			case 'is_true':
				return self::truthy( $left );
			case 'is_false':
				return ! self::truthy( $left );
			case 'between':
				[ $min, $max ] = self::range( $right );
				$l = is_numeric( $left ) ? 0 + $left : ( strtotime( (string) $left ) ?: null );
				return null !== $l && $l >= $min && $l <= $max;
			case 'before':
				return ( strtotime( (string) $left ) ?: 0 ) < ( strtotime( (string) $right ) ?: 0 );
			case 'after':
				return ( strtotime( (string) $left ) ?: 0 ) > ( strtotime( (string) $right ) ?: 0 );
		}//end switch
		return false;
	}

	private static function truthy( $v ): bool {
		if ( is_bool( $v ) ) {
			return $v;
		}
		return in_array( strtolower( trim( (string) $v ) ), [ '1', 'true', 'yes', 'on' ], true );
	}

	/**
	 * @param mixed $right
	 * @return array<int,string>
	 */
	private static function list_values( $right ): array {
		$items = is_array( $right ) ? $right : explode( ',', (string) $right );
		return array_map( static fn( $v ) => trim( (string) $v ), $items );
	}

	/**
	 * @param mixed $right
	 * @return array{0:float|int,1:float|int}
	 */
	private static function range( $right ): array {
		$parts  = is_array( $right ) ? array_values( $right ) : array_map( 'trim', explode( ',', (string) $right ) );
		$to_num = static fn( $v ) => is_numeric( $v ) ? 0 + $v : ( strtotime( (string) $v ) ?: 0 );
		return [ $to_num( $parts[0] ?? 0 ), $to_num( $parts[1] ?? PHP_INT_MAX ) ];
	}
}
