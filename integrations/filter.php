<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Framework\Classes\Expression;

class Filter extends IntegrationBase {


	public static function get_slug(): string {
		return 'filter';
	}

	public static function get_name(): string {
		return 'Filter';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_actions(): array {
		return [
			'filter' => [ 'label' => 'Filter' ],
		];
	}



	public static function get_action_config_schema( string $action ): array {
		return [
			[
				'key'      => 'conditions',
				'label'    => 'Conditions',
				'type'     => 'condition_group',
				'required' => true,
				'help'     => 'Only continue if these conditions are met',
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
		return [ 'main' ];
	}



	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];
		$rawConditions = $config['conditions'] ?? $input['conditions'] ?? [];

		$directInput = array_filter( $input, fn( $k) => ! ctype_digit( (string) $k ), ARRAY_FILTER_USE_KEY );

		$arrayFilter = self::detectArrayFilter( $rawConditions );
		if ( $arrayFilter !== null ) {
			return self::runArrayFilter( $arrayFilter, $rawConditions, $input, $directInput );
		}

		$conditions = self::normalizeConditions( $rawConditions );
		$result = self::evaluate_condition_group( $conditions, $input );

		return [
			'pass' => $result,
			'data' => $directInput,
		];
	}



	protected static function detectArrayFilter( array $rawConditions ): ?array {

		$leaves = self::flattenConditions( $rawConditions );

		foreach ( $leaves as $cond ) {
			$left = $cond['left'] ?? '';

			if ( preg_match( '/\{\{([^}]*)\[\]\.([^}]+)\}\}/', $left, $m ) ) {
				$arrayPath  = explode( '.', $m[1] );
				$field      = $m[2];
				$localKey   = $arrayPath[0];
				$nestedPath = array_slice( $arrayPath, 1 );

				return [
					'array_path'  => $arrayPath,
					'field'       => $field,
					'local_key'   => $localKey,
					'nested_path' => $nestedPath,
					'left_expr'   => $left,
				];
			}
		}

		return null;
	}



	protected static function flattenConditions( array $conditions ): array {
		$leaves = [];

		if ( isset( $conditions['logic'] ) && isset( $conditions['conditions'] ) ) {
			foreach ( $conditions['conditions'] as $cond ) {
				foreach ( self::flattenConditions( $cond ) as $leaf ) {
					$leaves[] = $leaf;
				}
			}
			return $leaves;
		}

		if ( isset( $conditions[0] ) && is_array( $conditions[0] ) ) {
			foreach ( $conditions as $group ) {
				foreach ( self::flattenConditions( $group ) as $leaf ) {
					$leaves[] = $leaf;
				}
			}
			return $leaves;
		}

		if ( isset( $conditions['left'] ) ) {
			return [ $conditions ];
		}

		return $leaves;
	}



	protected static function runArrayFilter( array $info, array $rawConditions, array $input, array $directInput ): array {

		$arr = $input[ $info['local_key'] ] ?? null;
		foreach ( $info['nested_path'] as $seg ) {
			if ( ! is_array( $arr ) || ! array_key_exists( $seg, $arr ) ) {
				$arr = null;
				break;
			}
			$arr = $arr[ $seg ];
		}

		if ( ! is_array( $arr ) ) {
			return [
				'pass' => false,
				'data' => $directInput
			];
		}

		$conditions = self::normalizeConditions( $rawConditions );

		$filtered = [];
		foreach ( $arr as $element ) {
			$elementInput = $input;
			$target = &$elementInput[ $info['local_key'] ];
			foreach ( $info['nested_path'] as $seg ) {
				$target = &$target[ $seg ];
			}
			$target = $element;
			unset( $target );

			$rewritten = self::rewriteArrayExpressions( $conditions );

			if ( self::evaluate_condition_group( $rewritten, $elementInput ) ) {
				$filtered[] = $element;
			}
		}

		if ( empty( $filtered ) ) {
			return [
				'pass' => false,
				'data' => $directInput
			];
		}

		$localKey   = $info['local_key'];
		$nestedPath = $info['nested_path'];

		if ( ctype_digit( (string) $localKey ) ) {
			$outputKey = ! empty( $nestedPath ) ? end( $nestedPath ) : 'items';
			$data = $directInput;
			$data[ $outputKey ] = $filtered;
		} else {
			$data = $directInput;
			if ( empty( $nestedPath ) ) {
				$data[ $localKey ] = $filtered;
			} else {
				if ( ! isset( $data[ $localKey ] ) || ! is_array( $data[ $localKey ] ) ) {
					$data[ $localKey ] = [];
				}
				$ref = &$data[ $localKey ];
				foreach ( array_slice( $nestedPath, 0, -1 ) as $seg ) {
					if ( ! isset( $ref[ $seg ] ) || ! is_array( $ref[ $seg ] ) ) {
						$ref[ $seg ] = [];
					}
					$ref = &$ref[ $seg ];
				}
				$ref[ end( $nestedPath ) ] = $filtered;
				unset( $ref );
			}
		}//end if

		return [
			'pass' => true,
			'data' => $data
		];
	}



	protected static function rewriteArrayExpressions( array $group ): array {
		if ( isset( $group['conditions'] ) ) {
			$group['conditions'] = array_map(
				[ self::class, 'rewriteArrayExpressions' ],
				$group['conditions']
			);
			return $group;
		}

		if ( isset( $group['left'] ) ) {
			$group['left']  = preg_replace( '/\[\]\./', '.', $group['left'] ?? '' );
			$group['right'] = preg_replace( '/\[\]\./', '.', $group['right'] ?? '' );
		}

		return $group;
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

		if ( $logic === 'AND' ) {
			return ! in_array( false, $results, true );
		} else {
			return in_array( true, $results, true );
		}
	}



	protected static function compare( $left, $right, string $op ): bool {
		switch ( $op ) {
			case '==':
				return $left == $right;
			case '!=':
				return $left != $right;
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
