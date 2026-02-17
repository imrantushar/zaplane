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
            'filter' => ['label' => 'Filter'],
        ];
    }

    /**
     * Filter node config schema - same condition UI as Condition node
     */
    public static function get_action_config_schema(string $action): array {
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
                            ['label' => 'Equals',           'value' => '=='],
                            ['label' => 'Not Equals',       'value' => '!='],
                            ['label' => 'Greater Than',     'value' => '>'],
                            ['label' => 'Less Than',        'value' => '<'],
                            ['label' => 'Greater or Equal', 'value' => '>='],
                            ['label' => 'Less or Equal',    'value' => '<='],
                            ['label' => 'Contains',         'value' => 'contains'],
                            ['label' => 'Not Contains',     'value' => 'not_contains'],
                            ['label' => 'Starts With',      'value' => 'starts_with'],
                            ['label' => 'Ends With',        'value' => 'ends_with'],
                            ['label' => 'Is Empty',         'value' => 'is_empty'],
                            ['label' => 'Is Not Empty',     'value' => 'is_not_empty'],
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
                'logic_options' => ['AND', 'OR'],
            ]
        ];
    }

    /**
     * Filter has only ONE output port - data passes through or stops
     */
    public static function get_output_ports(): array {
        return ['main'];
    }

    /**
     * Execute filter node.
     * Returns 'pass' => true/false. If false, automation engine stops execution.
     */
    public static function execute_node(array $node, array $input): array {
        $config = $node['data']['config'] ?? [];
        $rawConditions = $config['conditions'] ?? $input['conditions'] ?? [];
        $conditions = self::normalizeConditions($rawConditions);
        $result = self::evaluate_condition_group($conditions, $input);

        return [
            'data' => [
                'pass' => $result
            ],
        ];
    }

    /**
     * Normalize frontend condition format to backend format.
     *
     * Frontend sends: [[cond1, cond2], [cond3, cond4]]
     *   - Outer array = AND (all groups must pass)
     *   - Inner array = OR  (at least one must pass)
     *
     * Backend expects: {logic: "AND", conditions: [{logic: "OR", conditions: [...]}, ...]}
     */
    protected static function normalizeConditions(array $conditions): array {
        // Already in normalized format
        if (isset($conditions['logic'])) {
            return $conditions;
        }

        // Empty conditions
        if (empty($conditions)) {
            return ['logic' => 'AND', 'conditions' => []];
        }

        $groups = [];
        foreach ($conditions as $group) {
            if (!is_array($group)) {
                continue;
            }

            // Single condition object (not wrapped in array)
            if (isset($group['left'])) {
                $groups[] = $group;
                continue;
            }

            // Array of conditions = OR group
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

    /**
     * Evaluate a condition group recursively
     */
    protected static function evaluate_condition_group(array $group, array $input): bool {
        $logic = $group['logic'] ?? 'AND';
        $results = [];

        foreach ($group['conditions'] ?? [] as $cond) {
            if (!empty($cond['conditions'])) {
                $results[] = self::evaluate_condition_group($cond, $input);
            } else {
                $left  = Expression::evaluate($cond['left'] ?? '', $input);
                $right = Expression::evaluate($cond['right'] ?? '', $input);
                $op    = $cond['operator'] ?? '==';

                $results[] = self::compare($left, $right, $op);
            }
        }

        if ($logic === 'AND') {
            return !in_array(false, $results, true);
        } else {
            return in_array(true, $results, true);
        }
    }

    /**
     * Compare two values with operator (same logic as Condition)
     */
    protected static function compare($left, $right, string $op): bool {
        switch ($op) {
            case '==':           return $left == $right;
            case '!=':           return $left != $right;
            case '<':            return $left < $right;
            case '>':            return $left > $right;
            case '<=':           return $left <= $right;
            case '>=':           return $left >= $right;
            case 'contains':     return str_contains((string) $left, (string) $right);
            case 'not_contains': return !str_contains((string) $left, (string) $right);
            case 'starts_with':  return str_starts_with((string) $left, (string) $right);
            case 'ends_with':    return str_ends_with((string) $left, (string) $right);
            case 'is_empty':     return empty($left);
            case 'is_not_empty': return !empty($left);
        }
        return false;
    }
}
