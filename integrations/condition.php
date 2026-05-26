<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\Expression;

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



	protected static function compare( $left, $right, string $op ): bool {
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
		}//end switch
		return false;
	}
}
