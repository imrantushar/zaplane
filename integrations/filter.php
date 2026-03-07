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
     *
     * Supports array filter mode when any condition uses {{path[].field}} notation.
     * In that mode, the referenced array is filtered element-by-element and the
     * filtered result replaces the array in the downstream data.
     */
    public static function execute_node(array $node, array $input): array {
        $config = $node['data']['config'] ?? [];
        $rawConditions = $config['conditions'] ?? $input['conditions'] ?? [];

        // Strip context keys (numeric node IDs) — only original data flows downstream.
        $directInput = array_filter($input, fn($k) => !ctype_digit((string) $k), ARRAY_FILTER_USE_KEY);

        // Check if any condition uses [] array filter notation.
        $arrayFilter = self::detectArrayFilter($rawConditions);
        if ($arrayFilter !== null) {
            return self::runArrayFilter($arrayFilter, $rawConditions, $input, $directInput);
        }

        $conditions = self::normalizeConditions($rawConditions);
        $result = self::evaluate_condition_group($conditions, $input);

        return [
            'pass' => $result,
            'data' => $directInput,
        ];
    }

    /**
     * Detect whether any flat condition uses {{path[].field}} notation.
     * Returns info array on match, or null if not an array filter.
     *
     * Info array:
     *   'array_path'  => string[]   — segments to reach the array (e.g. ['11','data','users'])
     *   'field'       => string     — field inside each element (e.g. 'ID')
     *   'local_key'   => string     — top-level key of directInput that holds the array (e.g. '11')
     *   'nested_path' => string[]   — path within local_key to the array (e.g. ['data','users'])
     *   'left_expr'   => string     — original left expression (for non-[] fallback per element)
     */
    protected static function detectArrayFilter(array $rawConditions): ?array {
        // Flatten all leaf conditions from the raw (potentially nested) structure.
        $leaves = self::flattenConditions($rawConditions);

        foreach ($leaves as $cond) {
            $left = $cond['left'] ?? '';
            // Match {{some.path[].field}} — captures everything before [] and the field after.
            if (preg_match('/\{\{([^}]*)\[\]\.([^}]+)\}\}/', $left, $m)) {
                $arrayPath  = explode('.', $m[1]);   // e.g. ['11','data','users']
                $field      = $m[2];                 // e.g. 'ID'
                $localKey   = $arrayPath[0];         // top-level key in $input
                $nestedPath = array_slice($arrayPath, 1); // path within that key

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

    /**
     * Recursively flatten nested condition groups to leaf condition objects.
     */
    protected static function flattenConditions(array $conditions): array {
        $leaves = [];

        // Normalized group: has 'logic' and 'conditions' keys.
        if (isset($conditions['logic']) && isset($conditions['conditions'])) {
            foreach ($conditions['conditions'] as $cond) {
                foreach (self::flattenConditions($cond) as $leaf) {
                    $leaves[] = $leaf;
                }
            }
            return $leaves;
        }

        // Array of groups (frontend raw format).
        if (isset($conditions[0]) && is_array($conditions[0])) {
            foreach ($conditions as $group) {
                foreach (self::flattenConditions($group) as $leaf) {
                    $leaves[] = $leaf;
                }
            }
            return $leaves;
        }

        // Single leaf condition (has 'left' key).
        if (isset($conditions['left'])) {
            return [$conditions];
        }

        return $leaves;
    }

    /**
     * Execute array filter mode.
     * Resolves the array from $input, filters each element by the conditions,
     * and returns the filtered array as data downstream.
     */
    protected static function runArrayFilter(array $info, array $rawConditions, array $input, array $directInput): array {
        // Resolve the array from the input context.
        $arr = $input[$info['local_key']] ?? null;
        foreach ($info['nested_path'] as $seg) {
            if (!is_array($arr) || !array_key_exists($seg, $arr)) {
                $arr = null;
                break;
            }
            $arr = $arr[$seg];
        }

        if (!is_array($arr)) {
            return ['pass' => false, 'data' => $directInput];
        }

        $conditions = self::normalizeConditions($rawConditions);

        // Filter each element: replace [] with the element itself for evaluation.
        $filtered = [];
        foreach ($arr as $element) {
            // Build a temporary input where the array slot is replaced by the element,
            // so {{path[].field}} effectively becomes {{path.field}} for this element.
            $elementInput = $input;
            $target = &$elementInput[$info['local_key']];
            foreach ($info['nested_path'] as $seg) {
                $target = &$target[$seg];
            }
            $target = $element;
            unset($target);

            // Rewrite conditions: replace {{x[].field}} → {{x.field}} so Expression::evaluate works.
            $rewritten = self::rewriteArrayExpressions($conditions);

            if (self::evaluate_condition_group($rewritten, $elementInput)) {
                $filtered[] = $element;
            }
        }

        if (empty($filtered)) {
            return ['pass' => false, 'data' => $directInput];
        }

        // Write the filtered array back into directInput at the correct nested path.
        $localKey   = $info['local_key'];
        $nestedPath = $info['nested_path'];

        // If local_key is a numeric context key (e.g. "11"), the array was pulled from
        // another node's output. The last segment of nested_path (e.g. "users") is the
        // key that exists in directInput — replace it with the filtered result.
        if (ctype_digit((string) $localKey)) {
            $outputKey = !empty($nestedPath) ? end($nestedPath) : 'items';
            $data = $directInput;
            $data[$outputKey] = $filtered;
        } else {
            $data = $directInput;
            if (empty($nestedPath)) {
                $data[$localKey] = $filtered;
            } else {
                if (!isset($data[$localKey]) || !is_array($data[$localKey])) {
                    $data[$localKey] = [];
                }
                $ref = &$data[$localKey];
                foreach (array_slice($nestedPath, 0, -1) as $seg) {
                    if (!isset($ref[$seg]) || !is_array($ref[$seg])) {
                        $ref[$seg] = [];
                    }
                    $ref = &$ref[$seg];
                }
                $ref[end($nestedPath)] = $filtered;
                unset($ref);
            }
        }

        return ['pass' => true, 'data' => $data];
    }

    /**
     * Rewrite {{path[].field}} → {{path.field}} in all condition left/right expressions
     * so that Expression::evaluate() can resolve them against the per-element input.
     */
    protected static function rewriteArrayExpressions(array $group): array {
        if (isset($group['conditions'])) {
            $group['conditions'] = array_map(
                [self::class, 'rewriteArrayExpressions'],
                $group['conditions']
            );
            return $group;
        }

        // Leaf condition.
        if (isset($group['left'])) {
            $group['left']  = preg_replace('/\[\]\./', '.', $group['left']  ?? '');
            $group['right'] = preg_replace('/\[\]\./', '.', $group['right'] ?? '');
        }

        return $group;
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
