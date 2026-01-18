<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

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

    public static function get_actions(): array {
        return [
            'wait' => ['label'=>'Wait / Delay'],
        ];
    }

    public static function get_action_config_schema(string $action): array {
        return [
            [
                'key'=>'seconds',
                'label'=>'Wait Seconds',
                'type'=>'number',
                'required'=>true
            ]
        ];
    }

    public static function execute_node(array $node, array $input): array {

        $seconds = (int) ($node['data']['config']['seconds'] ?? 0);

        Zaplane_Scheduler::enqueue(
            time() + $seconds,
            $node['workflow_id'],
            $node['id'],
            $input
        );

        return [
            'port' => '__halt__', // engine must stop here
            'data' => []
        ];
    }
}
