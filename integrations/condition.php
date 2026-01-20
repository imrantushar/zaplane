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

    public static function get_actions(): array {
        return [
            'if' => ['label' => 'If Condition'],
        ];
    }

    public static function get_action_config_schema(string $action): array {
        return [
            [
                'key' => 'expression',
                'label' => 'Condition',
                'type' => 'expression',
                'required' => true,
                'help' => 'Example: {{post.status}} == "publish"'
            ]
        ];
    }

    public static function get_output_ports(): array {
        return ['true', 'false'];
    }

    public static function execute_node(array $node, array $input): array {
        $expr = $node['data']['config']['expression'] ?? '';

        $result = Expression::evaluate($expr, $input);

        return [
            'port' => $result ? 'true' : 'false',
            'data' => $input,
        ];
    }
}
